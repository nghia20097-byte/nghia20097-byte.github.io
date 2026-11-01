<?php
// Session & Auth Guard for Admin Web
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('ADMIN_ROOT', dirname(__DIR__));
define('SERVER_ROOT', dirname(ADMIN_ROOT));

require_once SERVER_ROOT . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register(SERVER_ROOT);

use App\Core\Database;

$config = require SERVER_ROOT . '/config/config.php';
try {
    Database::init($config['database']);
} catch (\Throwable $e) {
    die('Database initialization failed: ' . htmlspecialchars($e->getMessage()));
}

$currentPage = basename($_SERVER['PHP_SELF'] ?? '');

// If user is not authenticated and not on login page, redirect
if (empty($_SESSION['admin_user']) && $currentPage !== 'login.php') {
    header('Location: login.php');
    exit;
}

$currentUser = $_SESSION['admin_user'] ?? null;
