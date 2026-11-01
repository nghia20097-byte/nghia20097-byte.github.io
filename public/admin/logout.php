<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
unset($_SESSION['admin_user']);
session_destroy();
header('Location: login.php');
exit;
