# WARP.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## Project Overview

This is an AdminLTE-based Inventory Management System (IMS) built for Benguet State University - Bokod Campus. It's a PHP web application with MySQL/PostgreSQL database support, featuring real-time inventory tracking, property & equipment management, request & approval workflows, and role-based access control.

## Development Commands

### Local Development Setup
```powershell
# Note: Docker Compose file not currently in repository
# For manual PHP development:
php -S localhost:8080

# Or use Docker directly:
docker build -t adminlte-inventory .
docker run -p 8080:80 adminlte-inventory
```

### Build Commands
```powershell
# Build Docker image
docker build -t adminlte-inventory .

# Install PHP dependencies
composer install

# Install Node.js dependencies (for AdminLTE theme)
npm install
```

### Database Management
```powershell
# Web-based initialization (RECOMMENDED)
# Visit: http://localhost:8080/init_db.php

# Manual MySQL import (if needed)
mysql -h localhost -P 3306 -u root -p inv_system < "inv_system (4).sql"

# Connect to database
mysql -h localhost -P 3306 -u root -p inv_system
```

### Database Backup & Restore
```powershell
# Web-based backup management (RECOMMENDED)
# Visit: http://localhost:8080/backup_restore.php

# CLI backup operations
php backup_cli.php backup "Description here"
php backup_cli.php list
php backup_cli.php restore backup_dbname_2025-11-20_070000.sql

# Manual backup using mysqldump (requires mysql client installed)
mysqldump --host=bawsq3bp1lnrza6owc7h-mysql.services.clever-cloud.com --port=3306 --user=urksc91tu6d1ykrt --password=KsC6GdxKNJQelbeXUlH4 bawsq3bp1lnrza6owc7h > backup.sql

# Manual restore
mysql --host=bawsq3bp1lnrza6owc7h-mysql.services.clever-cloud.com --port=3306 --user=urksc91tu6d1ykrt --password=KsC6GdxKNJQelbeXUlH4 bawsq3bp1lnrza6owc7h < backup.sql
```

### Production Deployment
```powershell
# Deploy to Render (automatic on git push to main)
git add .
git commit -m "Deploy updates"
git push origin main
```

## Architecture & Structure

### Core Components
- **Frontend**: AdminLTE 3.2.0 theme with Bootstrap 5, custom CSS styling
- **Backend**: PHP 8.1 with Apache web server
- **Database**: MySQL 8.0 (local) / PostgreSQL (Render production)
- **Authentication**: Session-based with role management (Admin, User, IT)

### Key Directories
- `includes/` - Core PHP configuration, database connections, utility functions
- `layouts/` - Shared templates (header.php, footer.php)
- `templates/` - Page-specific templates
- `css/` - Custom stylesheets
- `dist/` - AdminLTE distribution files
- `plugins/` - Third-party plugins
- `uploads/` - File upload storage
- `vendor/` - Composer dependencies

### Database Configuration
The application uses environment variables for database configuration:
- Local development: MySQL on port 3306 (default) or 3308
- Production: Currently configured for Clever Cloud MySQL (via MYSQL_ADDON_* env vars)
- Previous deployment: Render PostgreSQL support

Key files:
- `includes/config.php` - Database connection setup (currently uses Clever Cloud env vars)
- `includes/database.php` - MySqli database wrapper class with query helpers
- `includes/functions.php` - Sanitization (remove_junk, real_escape) and utility functions
- `includes/sql.php` - Database operations (find_all, find_by_id, archive/restore functions)
- `includes/load.php` - Central loader that initializes all includes in correct order
- `includes/session.php` - Session management
- `includes/upload.php` - File upload handling

### Main Application Files
- `index.php` - Landing page with modern UI
- `login.php` - Authentication entry point
- `admin.php` - Admin dashboard
- `home.php` - Main dashboard after login
- Various module files: `items.php`, `requests.php`, `reports.php`, etc.

### Default Credentials
- Admin: username `admin`, password `admin`
- User: username `user`, password `user`
- IT: username `IT`, password `user`

## Environment Configuration

### Required Environment Variables
```env
DB_HOST=<database_host>
DB_USER=<database_username>
DB_PASS=<database_password>
DB_NAME=<database_name>
DB_PORT=<database_port>
```

### Docker Environment
- Web server runs on port 80 (mapped to 8080 locally)
- MySQL service on port 3306 (local development)
- Automatic SSL/HTTPS on Render deployment

## Development Practices

### File Handling
- File uploads go to `uploads/` directory
- Proper permissions set via Docker (www-data:www-data)
- Temporary file storage on Render (files lost on restart)

### Security Considerations
- Input sanitization via `remove_junk()` and `real_escape()` functions
- SQL injection protection using prepared statements
- Session-based authentication with role verification
- XSS protection through HTML entity encoding

### Code Style
- PHP files follow standard formatting
- Database operations abstracted through helper functions
- Frontend uses Bootstrap classes with custom CSS variables
- Modular structure with shared includes

## Testing & Debugging

### Local Testing
```powershell
# Test database connection
php -r "require 'includes/load.php'; echo 'Loaded successfully';"

# Check PHP syntax in a file
php -l path/to/file.php

# Test Apache configuration (if using Docker)
docker exec <container_id> apache2ctl configtest
```

### Debugging Tips
- Check `includes/config.php` for current database configuration
- The application uses MySqli, not PDO (except in migrations)
- Session data stored in `$_SESSION` with keys: 'user_id', 'user_level', 'name'
- User levels: 1 = Admin, 2 = User, 3 = IT
- Error reporting configured in PHP (check php.ini or Dockerfile)

### Common Issues
- Database connection: Verify environment variables (MYSQL_ADDON_HOST, MYSQL_ADDON_USER, etc.)
- Session issues: Check if `session_start()` is called (done in includes/load.php)
- Permission errors: Ensure uploads/ directory is writable (755)
- Migration errors: Run via web interface at init_db.php or migrate.php

## Database Migrations

The application includes a custom migration system that works with both MySQL and PostgreSQL databases, perfect for Render deployment without shell access.

### Migration Commands
```powershell
# One-click database initialization (RECOMMENDED)
# Visit: http://localhost:8080/init_db.php

# Auto-generate all migrations from schema
# Visit: http://localhost:8080/auto_migrate.php

# Create new migration via web interface
# Visit: http://localhost:8080/create_migration.php

# Create migration via CLI (local development)
php create_migration.php "add_user_settings_table"

# Run migrations via web interface
# Visit: http://localhost:8080/migrate.php
```

### Migration Files Structure
- Location: `migrations/` directory
- Naming: `YYYY_MM_DD_HHMMSS_description.php`
- Class: `Migration_description` extending `Migration`

### Web-based Migration System
- **URL**: `/init_db.php` - **One-click database initialization (RECOMMENDED)**
- **URL**: `/auto_migrate.php` - **Auto-generate all migrations from existing schema**  
- **URL**: `/migrate.php` - Main migration management interface
- **URL**: `/create_migration.php` - Generate new migration files
- Features: Complete automation, run individual migrations, rollback, bulk execution
- Real-time logging and status tracking
- Works on free Render accounts (no shell access needed)

### Automatic Migration Generation
The system can automatically analyze your `inv_system (4).sql` file and generate migrations for:
- **25+ database tables** including users, items, requests, employees, etc.
- **Complete table structures** with all columns and data types
- **Indexes and constraints** for optimal performance
- **Sample data** for immediate testing
- **Foreign key relationships** between tables

### Migration Methods Available
```php
// Table operations
$this->createTable($name, $columns);
$this->dropTable($name);

// Column operations
$this->addColumn($table, $column, $definition);
$this->dropColumn($table, $column);

// Index operations
$this->addIndex($table, $name, $columns);
$this->dropIndex($name, $table);

// Data operations
$this->insertData($table, $dataArray);
$this->executeSQL($sql);
$this->executeSQLBatch($sqlArray);
```

### Example Migration
```php
class Migration_add_user_settings extends Migration {
    public function up() {
        $columns = [
            'id INT AUTO_INCREMENT PRIMARY KEY',
            'user_id INT NOT NULL',
            'setting_key VARCHAR(100)',
            'setting_value TEXT'
        ];
        $this->createTable('user_settings', $columns);
    }
    
    public function down() {
        $this->dropTable('user_settings');
    }
}
```

## Code Patterns & Best Practices

### Database Operations
- Use `$db->escape()` or `$db->con->real_escape_string()` for SQL inputs
- Prefer `find_by_sql()` for SELECT queries (returns array of results)
- Use `find_by_id($table, $id)` for single record lookups
- Archive/restore functions: `archive($table, $id, $classification)` and `restore_from_archive($archive_id)`

### Input Sanitization
- `remove_junk($str)` - Strips HTML tags and encodes special characters
- `real_escape($str)` - Escapes strings for SQL queries
- Always sanitize user inputs from $_POST and $_GET

### Session & Authentication
- Check user level: `$_SESSION['user_level']` (1=Admin, 2=User, 3=IT)
- Redirect helper: `redirect($url, $permanent = false)`
- Session initialized automatically in includes/load.php

### File Structure
- Page files (e.g., items.php, requests.php) are in root directory
- Each page typically requires 'includes/load.php' first
- Use layouts/header.php and layouts/footer.php for page structure
- Templates in templates/ directory for reusable components

### Adding New Features
1. Create migration if database changes needed (via create_migration.php)
2. Add page file in root (e.g., new_feature.php)
3. Include 'includes/load.php' at top
4. Use existing functions from includes/sql.php and includes/functions.php
5. Follow existing patterns for forms, tables, and modals

## Database Backup & Restore System

The application includes a comprehensive backup and restore system for the external MySQL database (Clever Cloud).

### Web Interface (backup_restore.php)
```powershell
# Access the web interface
http://localhost:8080/backup_restore.php
```

**Features:**
- **Create Backup**: One-click backup with optional description
- **Upload & Restore**: Upload .sql files and restore database
- **Download**: Download backups to local machine
- **Delete**: Remove old backups
- **Activity Log**: Real-time logging of all operations

**Methods:**
- Primary: `mysqldump` command (if available)
- Fallback: PHP-based backup (works without mysql client)

**Storage:**
- Backups stored in `backups/` directory
- Protected with `.htaccess` (Deny from all)
- Metadata stored in `.meta` files (description, timestamp, creator)
- Filename format: `backup_dbname_YYYY-MM-DD_HHmmss.sql`

### CLI Interface (backup_cli.php)
```powershell
# Create backup
php backup_cli.php backup "Before major update"

# List all backups
php backup_cli.php list

# Restore from backup (with confirmation)
php backup_cli.php restore backup_bawsq3bp1lnrza6owc7h_2025-11-20_070000.sql

# Show help
php backup_cli.php help
```

### External Database Connection
**Service:** Clever Cloud MySQL
**Database:** bawsq3bp1lnrza6owc7h
**Host:** bawsq3bp1lnrza6owc7h-mysql.services.clever-cloud.com
**Port:** 3306
**User:** urksc91tu6d1ykrt

### Manual Backup Commands
```powershell
# Full backup with all options
mysqldump --host=bawsq3bp1lnrza6owc7h-mysql.services.clever-cloud.com \
  --port=3306 \
  --user=urksc91tu6d1ykrt \
  --password=KsC6GdxKNJQelbeXUlH4 \
  --single-transaction \
  --routines \
  --triggers \
  bawsq3bp1lnrza6owc7h > backup_$(date +%Y%m%d_%H%M%S).sql

# Restore from backup
mysql --host=bawsq3bp1lnrza6owc7h-mysql.services.clever-cloud.com \
  --port=3306 \
  --user=urksc91tu6d1ykrt \
  --password=KsC6GdxKNJQelbeXUlH4 \
  bawsq3bp1lnrza6owc7h < backup_file.sql

# Windows PowerShell backup
$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
mysqldump --host=bawsq3bp1lnrza6owc7h-mysql.services.clever-cloud.com --port=3306 --user=urksc91tu6d1ykrt --password=KsC6GdxKNJQelbeXUlH4 bawsq3bp1lnrza6owc7h > "backup_$timestamp.sql"
```

### Best Practices
- **Before migrations**: Always create backup before running database migrations
- **Before major updates**: Backup before deploying significant code changes
- **Regular schedule**: Consider weekly automated backups
- **Download important backups**: Store critical backups off-server
- **Test restores**: Periodically test restore process on development environment
- **Keep multiple versions**: Don't delete old backups immediately

### Security Notes
- Backup files contain sensitive data - protect them carefully
- `.htaccess` prevents direct web access to backups directory
- Admin privileges required for web interface access
- Never commit backup files to git repository (add `backups/` to .gitignore)

## Additional Notes

- Application primarily uses MySQL (MySqli extension), with PostgreSQL support in migrations
- Uses AdminLTE 3.2.0 theme with Bootstrap 5
- Implements PHPWord and PHPSpreadsheet for document/Excel generation
- Session-based authentication with role verification
- Notification system via notifications table
- Bulk operations for inventory management (bulk_edit_items.php, process_bulk_issue.php)
- Custom migration system for schema changes without shell access
- Comprehensive backup/restore system for external MySQL database (Clever Cloud)
- No formal testing framework in place (no PHPUnit or similar)
