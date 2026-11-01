<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('ADMIN_ROOT', __DIR__);
define('SERVER_ROOT', dirname(ADMIN_ROOT));

require_once SERVER_ROOT . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register(SERVER_ROOT);

use App\Core\Database;

$config = require SERVER_ROOT . '/config/config.php';
try {
    Database::init($config['database']);
} catch (\Throwable $e) {
    $dbError = $e->getMessage();
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT * FROM users WHERE username = :u OR email = :u LIMIT 1');
        $stmt->execute([':u' => $username]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['status'] === 'active') {
                $_SESSION['admin_user'] = [
                    'id'       => $user['id'],
                    'username' => $user['username'],
                    'email'    => $user['email'],
                    'role'     => $user['role']
                ];
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Tài khoản của bạn đã bị khóa hoặc chưa kích hoạt.';
            }
        } else {
            $error = 'Tên đăng nhập hoặc mật khẩu không chính xác.';
        }
    } else {
        $error = 'Vui lòng nhập đầy đủ thông tin đăng nhập.';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - NChanger Web Admin</title>
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body style="background: radial-gradient(circle at top right, #1e293b, #0b0f19);">
    <div class="login-container">
        <div class="login-box">
            <div style="text-align: center; margin-bottom: 25px;">
                <h2 style="font-size: 22px; margin-bottom: 6px;">NChanger <span style="color: var(--accent);">Admin</span></h2>
                <p style="color: var(--text-muted); font-size: 13px;">Hệ thống quản trị cấu hình thiết bị & ROM</p>
            </div>

            <?php if (!empty($error)): ?>
                <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: #fca5a5; padding: 10px; border-radius: 6px; margin-bottom: 15px; font-size: 13px;">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Tên đăng nhập / Email</label>
                    <input type="text" name="username" class="form-control" placeholder="admin" required autofocus>
                </div>
                <div class="form-group">
                    <label class="form-label">Mật khẩu</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>
                <div style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 11px;">
                        Đăng nhập hệ thống
                    </button>
                </div>
            </form>

            <div style="margin-top: 20px; font-size: 12px; color: var(--text-muted); text-align: center;">
                Tài khoản mặc định: <b>admin</b> / <b>Admin@123456</b>
            </div>
        </div>
    </div>
</body>
</html>
