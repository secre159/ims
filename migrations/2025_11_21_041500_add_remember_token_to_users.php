<?php
/**
 * Migration: Add remember_token column to users table
 * Created: 2025-11-21 04:15:00
 */

require_once(__DIR__ . '/../includes/Migration.php');

class Migration_2025_11_21_041500_add_remember_token_to_users extends Migration {
    
    public function up() {
        // Add remember_token column if it doesn't exist
        if (!$this->columnExists('users', 'remember_token')) {
            $this->addColumn('users', 'remember_token', 'VARCHAR(255) NULL DEFAULT NULL');
            echo "Added remember_token column to users table\n";
        } else {
            echo "remember_token column already exists in users table\n";
        }
    }
    
    public function down() {
        // Remove remember_token column
        if ($this->columnExists('users', 'remember_token')) {
            $this->dropColumn('users', 'remember_token');
            echo "Removed remember_token column from users table\n";
        }
    }
    
    public function getDescription() {
        return 'Add remember_token column to users table for "Remember Me" functionality';
    }
}
