<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

ob_start();

// -----------------------------------------------------------------------
// DEFINE SEPARATOR ALIASES
// -----------------------------------------------------------------------
define("URL_SEPARATOR", '/');
define("DS", DIRECTORY_SEPARATOR);

// -----------------------------------------------------------------------
// DEFINE ROOT PATHS
// -----------------------------------------------------------------------
defined('SITE_ROOT') or define('SITE_ROOT', realpath(dirname(__FILE__)));
define("LIB_PATH_INC", SITE_ROOT . DS);

// Load configuration first
require_once(LIB_PATH_INC . 'config.php');

// Load functions next (includes make_date function)
require_once(LIB_PATH_INC . 'functions.php');

// Load database before session (session needs DB for remember me)
require_once(LIB_PATH_INC . 'database.php');
require_once(LIB_PATH_INC . 'sql.php');

// Session should load after database to support remember me feature
require_once(LIB_PATH_INC . 'session.php');

// Other required system files
require_once(LIB_PATH_INC . 'upload.php');
require_once(LIB_PATH_INC . 'cache.php');

// Clean accidental output to prevent header issues
ob_end_clean();

?>
