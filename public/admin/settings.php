<?php
require_once __DIR__ . '/includes/auth.php';
use App\Core\Database;

$pageTitle = 'Cấu Hình Hệ Thống';
$db = Database::getConnection();
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = $_POST['settings'] ?? [];
    $stmt = $db->prepare('UPDATE system_settings SET setting_value = :val WHERE setting_key = :k');

    try {
        foreach ($settings as $key => $val) {
            $stmt->execute([':val' => trim($val), ':k' => $key]);
        }
        $message = ['type' => 'success', 'text' => 'Đã lưu cấu hình hệ thống thành công.'];
    } catch (\Throwable $e) {
        $message = ['type' => 'danger', 'text' => 'Lỗi lưu cấu hình: ' . $e->getMessage()];
    }
}

$rows = $db->query('SELECT * FROM system_settings ORDER BY setting_key ASC')->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/header.php';
?>

<?php if ($message): ?>
    <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid var(--<?= $message['type'] === 'success' ? 'success' : 'danger' ?>); color: #fff; padding: 12px; border-radius: 8px; margin-bottom: 20px;">
        <?= htmlspecialchars($message['text']) ?>
    </div>
<?php endif; ?>

<div class="card" style="max-width: 800px;">
    <div class="card-header">
        <div class="card-title">Cấu hình Hệ Thống & Client Updates</div>
    </div>

    <form method="POST">
        <?php foreach ($rows as $s): ?>
            <div class="form-group">
                <label class="form-label">
                    <code><?= htmlspecialchars($s['setting_key']) ?></code>
                    <?php if (!empty($s['description'])): ?>
                        <span style="font-weight: normal; font-size: 11px; color: var(--text-muted);"> - <?= htmlspecialchars($s['description']) ?></span>
                    <?php endif; ?>
                </label>
                <?php if (strlen($s['setting_value']) > 80 || in_array($s['setting_key'], ['client_changelog'])): ?>
                    <textarea name="settings[<?= htmlspecialchars($s['setting_key']) ?>]" class="form-control" rows="3"><?= htmlspecialchars($s['setting_value']) ?></textarea>
                <?php else: ?>
                    <input type="text" name="settings[<?= htmlspecialchars($s['setting_key']) ?>]" class="form-control" value="<?= htmlspecialchars($s['setting_value']) ?>">
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <div style="margin-top: 25px;">
            <button type="submit" class="btn btn-primary" style="padding: 10px 20px;">Lưu Thay Đổi</button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
