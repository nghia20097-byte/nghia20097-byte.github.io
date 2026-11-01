<?php
require_once __DIR__ . '/includes/auth.php';
use App\Core\Database;

$pageTitle = 'Quản lý Thiết Bị (Devices)';
$db = Database::getConnection();
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'toggle_block') {
        $id = (int)$_POST['id'];
        $current = $_POST['status'];
        $newStatus = ($current === 'blocked') ? 'offline' : 'blocked';
        $db->prepare('UPDATE devices SET status = :st WHERE id = :id')->execute([':st' => $newStatus, ':id' => $id]);
        $message = ['type' => 'success', 'text' => "Đã chuyển trạng thái thiết bị sang {$newStatus}"];
    }
}

$devices = $db->query('
    SELECT d.*, b.name AS brand, m.model_code AS hardware_model, m.codename, r.name AS rom_name, r.version AS rom_version, l.license_key, l.plan
    FROM devices d
    LEFT JOIN models m ON m.id = d.model_id
    LEFT JOIN brands b ON b.id = m.brand_id
    LEFT JOIN rom_profiles r ON r.id = d.rom_id
    LEFT JOIN licenses l ON l.id = d.license_id
    ORDER BY d.last_seen_at DESC
')->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/header.php';
?>

<?php if ($message): ?>
    <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid var(--<?= $message['type'] === 'success' ? 'success' : 'danger' ?>); color: #fff; padding: 12px; border-radius: 8px; margin-bottom: 20px;">
        <?= htmlspecialchars($message['text']) ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <div class="card-title">Danh sách Thiết Bị Đã Đăng Ký</div>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Device UUID</th>
                    <th>Model</th>
                    <th>ROM</th>
                    <th>Client / Platform</th>
                    <th>License</th>
                    <th>IP / Vị trí</th>
                    <th>Trạng thái</th>
                    <th>Lần cuối hoạt động</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($devices)): ?>
                    <tr><td colspan="9" style="text-align: center; color: var(--text-muted);">Chưa có thiết bị nào kết nối</td></tr>
                <?php else: ?>
                    <?php foreach ($devices as $d): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($d['device_uuid']) ?></code></td>
                            <td>
                                <b><?= htmlspecialchars($d['hardware_model'] ?? 'N/A') ?></b>
                                <div style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($d['codename'] ?? '') ?></div>
                            </td>
                            <td>
                                <?= htmlspecialchars($d['rom_name'] ?? 'Chưa gắn') ?>
                                <div style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($d['rom_version'] ?? '') ?></div>
                            </td>
                            <td>
                                <?= htmlspecialchars($d['platform']) ?>
                                <span style="font-size: 11px; color: var(--text-muted);">v<?= htmlspecialchars($d['client_version']) ?></span>
                            </td>
                            <td>
                                <?php if (!empty($d['license_key'])): ?>
                                    <span class="badge badge-info"><?= htmlspecialchars($d['plan']) ?></span>
                                    <div style="font-size: 11px; margin-top: 2px;"><code><?= htmlspecialchars(substr($d['license_key'], 0, 10)) ?>...</code></div>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-size: 12px;">Không có</span>
                                <?php endif; ?>
                            </td>
                            <td><span style="font-size: 12px; color: var(--text-muted);"><?= htmlspecialchars($d['last_ip'] ?? '-') ?></span></td>
                            <td>
                                <?php if ($d['status'] === 'online'): ?>
                                    <span class="badge badge-success">Online</span>
                                <?php elseif ($d['status'] === 'blocked'): ?>
                                    <span class="badge badge-danger">Blocked</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Offline</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 12px; color: var(--text-muted);"><?= $d['last_seen_at'] ? date('H:i d/m/Y', strtotime($d['last_seen_at'])) : '-' ?></td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="toggle_block">
                                    <input type="hidden" name="id" value="<?= $d['id'] ?>">
                                    <input type="hidden" name="status" value="<?= $d['status'] ?>">
                                    <button type="submit" class="btn btn-secondary btn-sm">
                                        <?= $d['status'] === 'blocked' ? 'Mở khóa' : 'Khóa thiết bị' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
