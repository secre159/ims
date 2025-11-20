<?php
/**
 * CLI Backup & Restore Script
 * Usage:
 *   php backup_cli.php backup [description]
 *   php backup_cli.php restore <filename>
 *   php backup_cli.php list
 */

require_once 'includes/load.php';

class BackupCLI {
    private $host;
    private $user;
    private $pass;
    private $dbname;
    private $port;
    private $backupDir;
    
    public function __construct() {
        $this->host = getenv('MYSQL_ADDON_HOST') ?: 'localhost';
        $this->user = getenv('MYSQL_ADDON_USER') ?: 'root';
        $this->pass = getenv('MYSQL_ADDON_PASSWORD') ?: '';
        $this->dbname = getenv('MYSQL_ADDON_DB') ?: 'inv_system';
        $this->port = getenv('MYSQL_ADDON_PORT') ?: 3306;
        
        $this->backupDir = __DIR__ . '/backups/';
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }
    
    public function backup($description = '') {
        $timestamp = date('Y-m-d_His');
        $filename = "backup_{$this->dbname}_{$timestamp}.sql";
        $filepath = $this->backupDir . $filename;
        
        $this->log("Creating backup: {$filename}");
        
        // Build mysqldump command
        $command = sprintf(
            'mysqldump --host=%s --port=%s --user=%s --password=%s --single-transaction --routines --triggers %s > %s 2>&1',
            escapeshellarg($this->host),
            escapeshellarg($this->port),
            escapeshellarg($this->user),
            escapeshellarg($this->pass),
            escapeshellarg($this->dbname),
            escapeshellarg($filepath)
        );
        
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0 && file_exists($filepath)) {
            $size = $this->formatBytes(filesize($filepath));
            $this->log("✓ Backup created successfully: {$size}");
            
            // Save metadata
            $metadata = [
                'created' => date('Y-m-d H:i:s'),
                'description' => $description,
                'database' => $this->dbname
            ];
            file_put_contents($filepath . '.meta', json_encode($metadata));
            
            return true;
        } else {
            $this->error("✗ Backup failed: " . implode("\n", $output));
            return false;
        }
    }
    
    public function restore($filename) {
        $filepath = $this->backupDir . $filename;
        
        if (!file_exists($filepath)) {
            $this->error("✗ Backup file not found: {$filename}");
            return false;
        }
        
        $this->log("Restoring from: {$filename}");
        $this->log("WARNING: This will overwrite all current data!");
        
        echo "Continue? (yes/no): ";
        $handle = fopen("php://stdin", "r");
        $line = trim(fgets($handle));
        fclose($handle);
        
        if (strtolower($line) !== 'yes') {
            $this->log("Restore cancelled.");
            return false;
        }
        
        // Build mysql command
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
            $this->log("✓ Database restored successfully");
            return true;
        } else {
            $this->error("✗ Restore failed: " . implode("\n", $output));
            return false;
        }
    }
    
    public function listBackups() {
        $files = glob($this->backupDir . 'backup_*.sql');
        
        if (empty($files)) {
            $this->log("No backups found.");
            return;
        }
        
        $this->log(sprintf("%-50s %-20s %-15s %s", "Filename", "Created", "Size", "Description"));
        $this->log(str_repeat("-", 120));
        
        foreach ($files as $file) {
            $filename = basename($file);
            $created = date('Y-m-d H:i:s', filemtime($file));
            $size = $this->formatBytes(filesize($file));
            
            $metadata = [];
            $metafile = $file . '.meta';
            if (file_exists($metafile)) {
                $metadata = json_decode(file_get_contents($metafile), true);
            }
            
            $description = $metadata['description'] ?? '-';
            
            $this->log(sprintf("%-50s %-20s %-15s %s", 
                substr($filename, 0, 48), 
                $created, 
                $size, 
                substr($description, 0, 30)
            ));
        }
    }
    
    private function log($message) {
        echo "[" . date('H:i:s') . "] {$message}\n";
    }
    
    private function error($message) {
        fwrite(STDERR, "[" . date('H:i:s') . "] {$message}\n");
    }
    
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    public function showHelp() {
        echo "Database Backup & Restore CLI\n\n";
        echo "Usage:\n";
        echo "  php backup_cli.php backup [description]   - Create a new backup\n";
        echo "  php backup_cli.php restore <filename>     - Restore from backup\n";
        echo "  php backup_cli.php list                   - List all backups\n";
        echo "  php backup_cli.php help                   - Show this help\n\n";
        echo "Examples:\n";
        echo "  php backup_cli.php backup \"Before migration\"\n";
        echo "  php backup_cli.php restore backup_inv_system_2025-11-20_070000.sql\n";
        echo "  php backup_cli.php list\n\n";
        echo "Database: {$this->dbname} @ {$this->host}:{$this->port}\n";
    }
}

// Main execution
if (php_sapi_name() !== 'cli') {
    die("This script must be run from command line.\n");
}

$cli = new BackupCLI();

$action = $argv[1] ?? 'help';

switch ($action) {
    case 'backup':
        $description = $argv[2] ?? '';
        exit($cli->backup($description) ? 0 : 1);
        
    case 'restore':
        if (!isset($argv[2])) {
            echo "Error: Please specify backup filename\n";
            $cli->showHelp();
            exit(1);
        }
        exit($cli->restore($argv[2]) ? 0 : 1);
        
    case 'list':
        $cli->listBackups();
        exit(0);
        
    case 'help':
    default:
        $cli->showHelp();
        exit(0);
}
