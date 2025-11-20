<?php
/**
 * Database Backup & Restore System
 * Web-based interface for backing up and restoring the database
 * Works with external MySQL services (Clever Cloud, etc.)
 */

require_once 'includes/load.php';

// Security check - only allow logged in users (session check)
if (!$session->isUserLoggedIn()) {
    die('Access denied. Please login first.');
}

// Additional check: only allow admin level (level 1)
page_require_level(1);

class DatabaseBackupRestore {
    private $host;
    private $user;
    private $pass;
    private $dbname;
    private $port;
    private $backupDir;
    
    public function __construct() {
        // Get database credentials from environment
        $this->host = getenv('MYSQL_ADDON_HOST') ?: DB_HOST;
        $this->user = getenv('MYSQL_ADDON_USER') ?: DB_USER;
        $this->pass = getenv('MYSQL_ADDON_PASSWORD') ?: DB_PASS;
        $this->dbname = getenv('MYSQL_ADDON_DB') ?: DB_NAME;
        $this->port = getenv('MYSQL_ADDON_PORT') ?: 3306;
        
        // Create backup directory if it doesn't exist
        // Use persistent disk on Render if available, otherwise local directory
        $persistentDir = '/var/data/backups/';
        if (is_dir('/var/data') && is_writable('/var/data')) {
            $this->backupDir = $persistentDir;
        } else {
            $this->backupDir = __DIR__ . '/backups/';
        }
        
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
        
        // Protect backup directory with .htaccess
        $htaccess = $this->backupDir . '.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Deny from all");
        }
    }
    
    /**
     * Create database backup using mysqldump
     */
    public function createBackup($description = '') {
        $timestamp = date('Y-m-d_His');
        $filename = "backup_{$this->dbname}_{$timestamp}.sql";
        $filepath = $this->backupDir . $filename;
        
        // Try using mysqldump if available
        if ($this->isMysqldumpAvailable()) {
            return $this->createBackupWithMysqldump($filepath, $description);
        } else {
            // Fallback to PHP-based backup
            return $this->createBackupWithPHP($filepath, $description);
        }
    }
    
    /**
     * Create backup using mysqldump command
     */
    private function createBackupWithMysqldump($filepath, $description) {
        $command = sprintf(
            'mysqldump --add-drop-table --host=%s --port=%s --user=%s --password=%s %s > %s 2>&1',
            escapeshellarg($this->host),
            escapeshellarg($this->port),
            escapeshellarg($this->user),
            escapeshellarg($this->pass),
            escapeshellarg($this->dbname),
            escapeshellarg($filepath)
        );
        
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0 && file_exists($filepath)) {
            $this->saveBackupMetadata($filepath, $description);
            return [
                'success' => true,
                'message' => 'Backup created successfully using mysqldump',
                'filename' => basename($filepath),
                'size' => $this->formatBytes(filesize($filepath))
            ];
        }
        
        // If mysqldump failed, try PHP method
        return $this->createBackupWithPHP($filepath, $description);
    }
    
    /**
     * Create backup using PHP (fallback method)
     */
    private function createBackupWithPHP($filepath, $description) {
        try {
            $pdo = new PDO(
                "mysql:host={$this->host};port={$this->port};dbname={$this->dbname}",
                $this->user,
                $this->pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            $backup = "-- Database Backup\n";
            $backup .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
            $backup .= "-- Database: {$this->dbname}\n";
            $backup .= "-- Description: {$description}\n\n";
            $backup .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
            
            // Get all tables
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($tables as $table) {
                // Get CREATE TABLE statement
                $createTable = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
                $backup .= "-- Table: {$table}\n";
                $backup .= "DROP TABLE IF EXISTS `{$table}`;\n";
                $backup .= $createTable['Create Table'] . ";\n\n";
                
                // Get table data
                $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
                
                if (count($rows) > 0) {
                    $backup .= "-- Data for table: {$table}\n";
                    
                    foreach ($rows as $row) {
                        $columns = array_keys($row);
                        $values = array_map(function($value) use ($pdo) {
                            return $value === null ? 'NULL' : $pdo->quote($value);
                        }, array_values($row));
                        
                        $backup .= "INSERT INTO `{$table}` (`" . implode('`, `', $columns) . "`) VALUES (";
                        $backup .= implode(', ', $values) . ");\n";
                    }
                    $backup .= "\n";
                }
            }
            
            $backup .= "SET FOREIGN_KEY_CHECKS=1;\n";
            
            file_put_contents($filepath, $backup);
            $this->saveBackupMetadata($filepath, $description);
            
            return [
                'success' => true,
                'message' => 'Backup created successfully using PHP',
                'filename' => basename($filepath),
                'size' => $this->formatBytes(filesize($filepath))
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Backup failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Restore database from backup file
     */
    public function restoreBackup($filename) {
        $filepath = $this->backupDir . $filename;
        
        if (!file_exists($filepath)) {
            return ['success' => false, 'message' => 'Backup file not found'];
        }
        
        // Try using mysql command if available
        if ($this->isMysqlAvailable()) {
            return $this->restoreWithMysql($filepath);
        } else {
            // Fallback to PHP-based restore
            return $this->restoreWithPHP($filepath);
        }
    }
    
    /**
     * Restore using mysql command
     */
    private function restoreWithMysql($filepath) {
        $command = sprintf(
            'mysql --host=%s --port=%s --user=%s --password=%s %s < %s 2>&1',
            escapeshellarg($this->host),
            escapeshellarg($this->port),
            escapeshellarg($this->user),
            escapeshellarg($this->pass),
            escapeshellarg($this->dbname),
            escapeshellarg($filepath)
        );
        
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0) {
            return [
                'success' => true,
                'message' => 'Database restored successfully using mysql command'
            ];
        }
        
        // If mysql failed, try PHP method
        return $this->restoreWithPHP($filepath);
    }
    
    /**
     * Restore using PHP (fallback method)
     */
    private function restoreWithPHP($filepath) {
        try {
            $pdo = new PDO(
                "mysql:host={$this->host};port={$this->port};dbname={$this->dbname}",
                $this->user,
                $this->pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            $sql = file_get_contents($filepath);
            
            // Remove comments
            $sql = preg_replace('/^--.*$/m', '', $sql);
            
            // Split SQL into individual statements, handling multi-line statements
            $statements = [];
            $buffer = '';
            $lines = explode("\n", $sql);
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                $buffer .= $line . " ";
                
                // Check if statement ends with semicolon
                if (substr(rtrim($line), -1) === ';') {
                    $stmt = trim($buffer);
                    if (!empty($stmt)) {
                        $statements[] = $stmt;
                    }
                    $buffer = '';
                }
            }
            
            // Disable foreign key checks and autocommit for restore
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            $pdo->exec('SET AUTOCOMMIT=0');
            
            $pdo->beginTransaction();
            
            foreach ($statements as $statement) {
                if (!empty($statement) && $statement !== ';') {
                    try {
                        $pdo->exec($statement);
                    } catch (Exception $e) {
                        // Skip statements that fail (like SET commands that may not be needed)
                        // Only throw if it's a critical error (CREATE, INSERT, DROP)
                        if (stripos($statement, 'CREATE TABLE') !== false || 
                            stripos($statement, 'INSERT INTO') !== false ||
                            stripos($statement, 'DROP TABLE') !== false) {
                            throw $e;
                        }
                    }
                }
            }
            
            $pdo->commit();
            
            // Re-enable foreign key checks
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            $pdo->exec('SET AUTOCOMMIT=1');
            
            return [
                'success' => true,
                'message' => 'Database restored successfully using PHP'
            ];
            
        } catch (Exception $e) {
            if (isset($pdo)) {
                $pdo->rollBack();
                $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
                $pdo->exec('SET AUTOCOMMIT=1');
            }
            return [
                'success' => false,
                'message' => 'Restore failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * List all available backups
     */
    public function listBackups() {
        $backups = [];
        $files = glob($this->backupDir . 'backup_*.sql');
        
        foreach ($files as $file) {
            $filename = basename($file);
            $metadata = $this->getBackupMetadata($file);
            
            $backups[] = [
                'filename' => $filename,
                'size' => $this->formatBytes(filesize($file)),
                'created' => date('Y-m-d H:i:s', filemtime($file)),
                'description' => $metadata['description'] ?? '',
                'method' => $metadata['method'] ?? 'Unknown'
            ];
        }
        
        // Sort by creation time (newest first)
        usort($backups, function($a, $b) {
            return strtotime($b['created']) - strtotime($a['created']);
        });
        
        return $backups;
    }
    
    /**
     * Delete a backup file
     */
    public function deleteBackup($filename) {
        $filepath = $this->backupDir . $filename;
        
        if (!file_exists($filepath)) {
            return ['success' => false, 'message' => 'Backup file not found'];
        }
        
        // Delete metadata file too
        $metafile = $filepath . '.meta';
        if (file_exists($metafile)) {
            unlink($metafile);
        }
        
        if (unlink($filepath)) {
            return ['success' => true, 'message' => 'Backup deleted successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to delete backup'];
    }
    
    /**
     * Download backup file
     */
    public function downloadBackup($filename) {
        $filepath = $this->backupDir . $filename;
        
        if (!file_exists($filepath)) {
            return false;
        }
        
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    }
    
    /**
     * Upload and restore from uploaded file
     */
    public function uploadAndRestore($file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'File upload failed'];
        }
        
        // Validate file extension
        if (pathinfo($file['name'], PATHINFO_EXTENSION) !== 'sql') {
            return ['success' => false, 'message' => 'Only .sql files are allowed'];
        }
        
        // Move uploaded file to backup directory
        $filename = 'uploaded_' . date('Y-m-d_His') . '_' . basename($file['name']);
        $filepath = $this->backupDir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            return $this->restoreBackup($filename);
        }
        
        return ['success' => false, 'message' => 'Failed to save uploaded file'];
    }
    
    /**
     * Check if mysqldump is available
     */
    private function isMysqldumpAvailable() {
        exec('mysqldump --version 2>&1', $output, $returnCode);
        return $returnCode === 0;
    }
    
    /**
     * Check if mysql command is available
     */
    private function isMysqlAvailable() {
        exec('mysql --version 2>&1', $output, $returnCode);
        return $returnCode === 0;
    }
    
    /**
     * Save backup metadata
     */
    private function saveBackupMetadata($filepath, $description) {
        $metadata = [
            'created' => date('Y-m-d H:i:s'),
            'description' => $description,
            'method' => $this->isMysqldumpAvailable() ? 'mysqldump' : 'PHP',
            'database' => $this->dbname,
            'user' => $_SESSION['name'] ?? 'Unknown'
        ];
        
        file_put_contents($filepath . '.meta', json_encode($metadata));
    }
    
    /**
     * Get backup metadata
     */
    private function getBackupMetadata($filepath) {
        $metafile = $filepath . '.meta';
        if (file_exists($metafile)) {
            return json_decode(file_get_contents($metafile), true);
        }
        return [];
    }
    
    /**
     * Format bytes to human readable size
     */
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}

// Handle AJAX requests
if (isset($_POST['action'])) {
    $backup = new DatabaseBackupRestore();
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'create':
                $description = $_POST['description'] ?? '';
                echo json_encode($backup->createBackup($description));
                break;
                
            case 'list':
                echo json_encode(['success' => true, 'backups' => $backup->listBackups()]);
                break;
                
            case 'restore':
                $filename = $_POST['filename'] ?? '';
                echo json_encode($backup->restoreBackup($filename));
                break;
                
            case 'delete':
                $filename = $_POST['filename'] ?? '';
                echo json_encode($backup->deleteBackup($filename));
                break;
                
            case 'upload_restore':
                if (isset($_FILES['backup_file'])) {
                    echo json_encode($backup->uploadAndRestore($_FILES['backup_file']));
                } else {
                    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
                }
                break;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Handle download request
if (isset($_GET['download'])) {
    $backup = new DatabaseBackupRestore();
    $backup->downloadBackup($_GET['download']);
}

$backup = new DatabaseBackupRestore();
$backups = $backup->listBackups();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Backup & Restore</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .backup-item { transition: all 0.2s; }
        .backup-item:hover { background-color: #f8f9fa; }
        .log-container { height: 300px; overflow-y: auto; background: #1e1e1e; color: #d4d4d4; }
        .status-badge { font-size: 0.875rem; }
    </style>
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row">
            <div class="col-lg-10 mx-auto">
                <!-- Header -->
                <div class="text-center mb-4">
                    <h1><i class="fas fa-database"></i> Database Backup & Restore</h1>
                    <p class="lead">Manage your database backups for <?php echo htmlspecialchars($backup->dbname ?? 'Unknown'); ?></p>
                </div>

                <!-- Quick Actions -->
                <div class="row mb-4">
                    <div class="col-md-6 mb-3">
                        <div class="card h-100">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="fas fa-save"></i> Create Backup</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Description (optional)</label>
                                    <input type="text" class="form-control" id="backupDescription" 
                                           placeholder="e.g., Before migration, Weekly backup">
                                </div>
                                <button class="btn btn-success btn-lg w-100" onclick="createBackup()">
                                    <i class="fas fa-save"></i> Create Backup Now
                                </button>
                                <small class="text-muted d-block mt-2">
                                    <i class="fas fa-info-circle"></i> Backup will be saved securely on the server
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <div class="card h-100">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-upload"></i> Upload & Restore</h5>
                            </div>
                            <div class="card-body">
                                <form id="uploadForm">
                                    <div class="mb-3">
                                        <label class="form-label">Select .sql file</label>
                                        <input type="file" class="form-control" id="backupFile" 
                                               accept=".sql" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-lg w-100">
                                        <i class="fas fa-upload"></i> Upload & Restore
                                    </button>
                                </form>
                                <small class="text-danger d-block mt-2">
                                    <i class="fas fa-exclamation-triangle"></i> This will overwrite all data!
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Backup List -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-list"></i> Available Backups</h5>
                        <button class="btn btn-sm btn-outline-secondary" onclick="refreshBackups()">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="backupsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Filename</th>
                                        <th>Description</th>
                                        <th>Created</th>
                                        <th>Size</th>
                                        <th>Method</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="backupsTableBody">
                                    <?php if (empty($backups)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                            No backups found. Create your first backup above.
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($backups as $bk): ?>
                                    <tr class="backup-item">
                                        <td>
                                            <i class="fas fa-file-code text-primary"></i>
                                            <small class="font-monospace"><?php echo htmlspecialchars($bk['filename']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($bk['description'] ?: '-'); ?></td>
                                        <td><small><?php echo htmlspecialchars($bk['created']); ?></small></td>
                                        <td><span class="badge bg-secondary"><?php echo $bk['size']; ?></span></td>
                                        <td><span class="badge bg-info"><?php echo $bk['method']; ?></span></td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm">
                                                <a href="?download=<?php echo urlencode($bk['filename']); ?>" 
                                                   class="btn btn-outline-success" title="Download">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                                <button onclick="restoreBackup('<?php echo htmlspecialchars($bk['filename']); ?>')" 
                                                        class="btn btn-outline-primary" title="Restore">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                                <button onclick="deleteBackup('<?php echo htmlspecialchars($bk['filename']); ?>')" 
                                                        class="btn btn-outline-danger" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Activity Log -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <h5 class="mb-0"><i class="fas fa-terminal"></i> Activity Log</h5>
                        <button class="btn btn-sm btn-outline-secondary" onclick="clearLog()">
                            <i class="fas fa-eraser"></i> Clear
                        </button>
                    </div>
                    <div class="card-body log-container p-3 font-monospace small" id="activityLog">
                        <div class="text-muted">Ready to perform backup operations...</div>
                    </div>
                </div>

                <!-- Navigation -->
                <div class="text-center mt-4">
                    <a href="admin.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <a href="migrate.php" class="btn btn-outline-primary">
                        <i class="fas fa-database"></i> Migrations
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function log(message, type = 'info') {
            const logContainer = document.getElementById('activityLog');
            const timestamp = new Date().toLocaleTimeString();
            const colors = {
                info: '#4FC3F7',
                success: '#81C784',
                error: '#E57373',
                warning: '#FFB74D'
            };
            
            logContainer.innerHTML += `<div style="color: ${colors[type]}">[${timestamp}] ${message}</div>`;
            logContainer.scrollTop = logContainer.scrollHeight;
        }

        function clearLog() {
            document.getElementById('activityLog').innerHTML = '<div class="text-muted">Log cleared...</div>';
        }

        async function createBackup() {
            const description = document.getElementById('backupDescription').value;
            const button = event.target;
            
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating backup...';
            log('Starting backup creation...', 'info');

            try {
                const response = await fetch('backup_restore.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=create&description=${encodeURIComponent(description)}`
                });

                const result = await response.json();
                
                if (result.success) {
                    log(`✓ ${result.message}`, 'success');
                    log(`Filename: ${result.filename}, Size: ${result.size}`, 'info');
                    document.getElementById('backupDescription').value = '';
                    await refreshBackups();
                } else {
                    log(`✗ ${result.message}`, 'error');
                }
            } catch (error) {
                log(`✗ Error: ${error.message}`, 'error');
            } finally {
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-save"></i> Create Backup Now';
            }
        }

        async function restoreBackup(filename) {
            if (!confirm(`Are you sure you want to restore from "${filename}"?\n\nThis will OVERWRITE all current data!`)) {
                return;
            }

            log(`Starting restore from ${filename}...`, 'warning');

            try {
                const response = await fetch('backup_restore.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=restore&filename=${encodeURIComponent(filename)}`
                });

                const result = await response.json();
                
                if (result.success) {
                    log(`✓ ${result.message}`, 'success');
                    alert('Database restored successfully! Please refresh the application.');
                } else {
                    log(`✗ ${result.message}`, 'error');
                    alert('Restore failed: ' + result.message);
                }
            } catch (error) {
                log(`✗ Error: ${error.message}`, 'error');
                alert('Restore failed: ' + error.message);
            }
        }

        async function deleteBackup(filename) {
            if (!confirm(`Are you sure you want to delete "${filename}"?`)) {
                return;
            }

            log(`Deleting ${filename}...`, 'info');

            try {
                const response = await fetch('backup_restore.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=delete&filename=${encodeURIComponent(filename)}`
                });

                const result = await response.json();
                
                if (result.success) {
                    log(`✓ ${result.message}`, 'success');
                    await refreshBackups();
                } else {
                    log(`✗ ${result.message}`, 'error');
                }
            } catch (error) {
                log(`✗ Error: ${error.message}`, 'error');
            }
        }

        async function refreshBackups() {
            log('Refreshing backup list...', 'info');

            try {
                const response = await fetch('backup_restore.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=list'
                });

                const result = await response.json();
                
                if (result.success) {
                    updateBackupsTable(result.backups);
                    log(`✓ Found ${result.backups.length} backup(s)`, 'success');
                }
            } catch (error) {
                log(`✗ Error: ${error.message}`, 'error');
            }
        }

        function updateBackupsTable(backups) {
            const tbody = document.getElementById('backupsTableBody');
            
            if (backups.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                            No backups found. Create your first backup above.
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = backups.map(bk => `
                <tr class="backup-item">
                    <td>
                        <i class="fas fa-file-code text-primary"></i>
                        <small class="font-monospace">${escapeHtml(bk.filename)}</small>
                    </td>
                    <td>${escapeHtml(bk.description || '-')}</td>
                    <td><small>${escapeHtml(bk.created)}</small></td>
                    <td><span class="badge bg-secondary">${bk.size}</span></td>
                    <td><span class="badge bg-info">${bk.method}</span></td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            <a href="?download=${encodeURIComponent(bk.filename)}" 
                               class="btn btn-outline-success" title="Download">
                                <i class="fas fa-download"></i>
                            </a>
                            <button onclick="restoreBackup('${escapeHtml(bk.filename)}')" 
                                    class="btn btn-outline-primary" title="Restore">
                                <i class="fas fa-undo"></i>
                            </button>
                            <button onclick="deleteBackup('${escapeHtml(bk.filename)}')" 
                                    class="btn btn-outline-danger" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `).join('');
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Upload form handler
        document.getElementById('uploadForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const fileInput = document.getElementById('backupFile');
            const file = fileInput.files[0];
            
            if (!file) {
                alert('Please select a file');
                return;
            }

            if (!confirm('Are you sure you want to upload and restore this backup?\n\nThis will OVERWRITE all current data!')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'upload_restore');
            formData.append('backup_file', file);

            log(`Uploading ${file.name}...`, 'info');

            try {
                const response = await fetch('backup_restore.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                
                if (result.success) {
                    log(`✓ ${result.message}`, 'success');
                    alert('Database restored successfully! Please refresh the application.');
                    fileInput.value = '';
                    await refreshBackups();
                } else {
                    log(`✗ ${result.message}`, 'error');
                    alert('Upload/Restore failed: ' + result.message);
                }
            } catch (error) {
                log(`✗ Error: ${error.message}`, 'error');
                alert('Upload/Restore failed: ' + error.message);
            }
        });
    </script>
</body>
</html>
