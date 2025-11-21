<?php
/**
 * Base Migration Class
 * All migration files should extend this class
 */

abstract class Migration {
    protected $pdo;
    protected $db;
    
    public function __construct() {
        // Get database connection
        global $db;
        $this->db = $db;
        
        // Parse DB_HOST to handle host:port format
        $host = DB_HOST;
        $port = 3306;
        if (strpos($host, ':') !== false) {
            list($host, $port) = explode(':', $host, 2);
        } elseif (defined('DB_PORT')) {
            $port = DB_PORT;
        }
        
        // Also create PDO connection for more advanced operations
        $this->pdo = new PDO(
            "mysql:host={$host};port={$port};dbname=" . DB_NAME,
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
        
        // Ensure migrations table exists
        $this->ensureMigrationsTable();
    }
    
    /**
     * Create migrations tracking table if it doesn't exist
     */
    private function ensureMigrationsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL UNIQUE,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_migration (migration)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        $this->pdo->exec($sql);
    }
    
    /**
     * Check if migration has already been run
     */
    public function hasRun($filename) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM migrations WHERE migration = ?");
        $stmt->execute([$filename]);
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Mark migration as run
     */
    public function markAsRun($filename) {
        $stmt = $this->pdo->prepare("INSERT INTO migrations (migration) VALUES (?)");
        $stmt->execute([$filename]);
    }
    
    /**
     * Execute raw SQL query
     */
    protected function query($sql) {
        return $this->pdo->exec($sql);
    }
    
    /**
     * Create a table
     */
    protected function createTable($tableName, $columns, $options = '') {
        $columnDefs = [];
        foreach ($columns as $name => $definition) {
            $columnDefs[] = "`{$name}` {$definition}";
        }
        
        $sql = "CREATE TABLE IF NOT EXISTS `{$tableName}` (\n";
        $sql .= "  " . implode(",\n  ", $columnDefs) . "\n";
        $sql .= ") {$options}";
        
        return $this->query($sql);
    }
    
    /**
     * Drop a table
     */
    protected function dropTable($tableName) {
        return $this->query("DROP TABLE IF EXISTS `{$tableName}`");
    }
    
    /**
     * Add a column to existing table
     */
    protected function addColumn($tableName, $columnName, $definition, $after = null) {
        $sql = "ALTER TABLE `{$tableName}` ADD COLUMN `{$columnName}` {$definition}";
        if ($after) {
            $sql .= " AFTER `{$after}`";
        }
        return $this->query($sql);
    }
    
    /**
     * Drop a column from table
     */
    protected function dropColumn($tableName, $columnName) {
        return $this->query("ALTER TABLE `{$tableName}` DROP COLUMN `{$columnName}`");
    }
    
    /**
     * Modify a column
     */
    protected function modifyColumn($tableName, $columnName, $definition) {
        return $this->query("ALTER TABLE `{$tableName}` MODIFY COLUMN `{$columnName}` {$definition}");
    }
    
    /**
     * Rename a column
     */
    protected function renameColumn($tableName, $oldName, $newName, $definition) {
        return $this->query("ALTER TABLE `{$tableName}` CHANGE `{$oldName}` `{$newName}` {$definition}");
    }
    
    /**
     * Add an index
     */
    protected function addIndex($tableName, $indexName, $columns, $type = 'INDEX') {
        $columnList = is_array($columns) ? implode('`, `', $columns) : $columns;
        return $this->query("ALTER TABLE `{$tableName}` ADD {$type} `{$indexName}` (`{$columnList}`)");
    }
    
    /**
     * Drop an index
     */
    protected function dropIndex($tableName, $indexName) {
        return $this->query("ALTER TABLE `{$tableName}` DROP INDEX `{$indexName}`");
    }
    
    /**
     * Check if table exists
     */
    protected function tableExists($tableName) {
        $stmt = $this->pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$tableName]);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Check if column exists
     */
    protected function columnExists($tableName, $columnName) {
        $stmt = $this->pdo->prepare("SHOW COLUMNS FROM `{$tableName}` LIKE ?");
        $stmt->execute([$columnName]);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Execute multiple SQL statements
     */
    protected function executeMultiple($sql) {
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            function($stmt) {
                return !empty($stmt);
            }
        );
        
        foreach ($statements as $statement) {
            $this->query($statement);
        }
    }
    
    /**
     * Get description of what this migration does
     * Override this in child classes
     */
    abstract public function getDescription();
    
    /**
     * Run the migration (upgrade)
     * Override this in child classes
     */
    abstract public function up();
    
    /**
     * Rollback the migration (downgrade)
     * Override this in child classes
     */
    abstract public function down();
}
