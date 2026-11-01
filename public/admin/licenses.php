<?php
require_once __DIR__ . '/includes/auth.php';
use App\Core\Database;

$pageTitle = 'Quản lý License';
$db = Database::getConnection();
$message = null;

// Handle Actions (Create / Disable / Extend)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $plan = $_POST['plan'] ?? 'standard';
        $maxDevices = (int)($_POST['max_devices'] ?? 1);
        $days = (int)($_POST['valid_days'] ?? 365);
        $customKey = trim($_POST['license_key'] ?? '');

        $licenseKey = !empty($customKey) ? $customKey : 'NC-' . strtoupper(bin2hex(random_bytes(2))) . '-' . strtoupper(bin2hex(random_bytes(2))) . '-' . strtoupper(bin2hex(random_bytes(2)));
        $expiresAt = date('Y-m-d H:i:s', time() + ($days * 86400));

        $stmt = $db->prepare('
            INSERT INTO licenses (license_key, plan, status, max_devices, expires_at)
            VALUES (:key, :plan, "active", :max, :exp)
        ');
        try {
            $stmt->execute([
                ':key'  => $licenseKey,
                ':plan' => $plan,
                ':max'  => $maxDevices,
                ':exp'  => $expiresAt
            ]);
            $message = ['type' => 'success', 'text' => "Đã tạo License mới: {$licenseKey}"];
        } catch (\Throwable $e) {
            $message = ['type' => 'danger', 'text' => "Lỗi: " . $e->getMessage()];
        }
    } elseif ($action === 'toggle_status') {
        $id = (int)$_POST['id'];
        $newStatus = $_POST['status'] === 'active' ? 'disabled' : 'active';
        $db->prepare('UPDATE licenses SET status = :st WHERE id = :id')->execute([':st' => $newStatus, ':id' => $id]);
        $message = ['type' => 'success', 'text' => "Cập nhật trạng thái thành {$newStatus}"];
    } elseif ($action === 'extend') {
        $id = (int)$_POST['id'];
        $db->prepare('UPDATE licenses SET expires_at = DATE_ADD(COALESCE(expires_at, NOW()), INTERVAL 30 DAY) WHERE id = :id')->execute([':id' => $id]);
        $message = ['type' => 'success', 'text' => "Đã gia hạn thêm 30 ngày"];
    }
}

// Fetch all licenses
$licenses = $db->query('
    SELECT l.*, COUNT(d.id) AS registered_devices
    FROM licenses l
    LEFT JOIN devices d ON d.license_id = l.id
    GROUP BY l.id
    ORDER BY l.id DESC
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
        <div class="card-title">Danh sách License Keys</div>
        <button class="btn btn-primary" onclick="openModal('createModal')">＋ Tạo License Mới</button>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>License Key</th>
                    <th>Gói (Plan)</th>
                    <th>Trạng thái</th>
                    <th>Thiết bị (Dùng / Tối đa)</th>
                    <th>Hết hạn</th>
                    <th>Ngày tạo</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($licenses)): ?>
                    <tr><td colspan="8" style="text-align: center; color: var(--text-muted);">Chưa có license nào</td></tr>
                <?php else: ?>
                    <?php foreach ($licenses as $l): ?>
                        <tr>
                            <td>#<?= $l['id'] ?></td>
                            <td><code><?= htmlspecialchars($l['license_key']) ?></code></td>
                            <td><span class="badge badge-info"><?= strtoupper(htmlspecialchars($l['plan'])) ?></span></td>
                            <td>
                                <?php if ($l['status'] === 'active'): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php elseif ($l['status'] === 'expired'): ?>
                                    <span class="badge badge-warning">Expired</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Disabled</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <b><?= (int)$l['registered_devices'] ?></b> / <?= (int)$l['max_devices'] ?>
                            </td>
                            <td style="font-size: 12px;">
                                <?= $l['expires_at'] ? date('d/m/Y H:i', strtotime($l['expires_at'])) : 'Vĩnh viễn' ?>
                            </td>
                            <td style="font-size: 11px; color: var(--text-muted);"><?= date('d/m/Y', strtotime($l['created_at'])) ?></td>
                            <td>
                                <div style="display: flex; gap: 6px;">
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="id" value="<?= $l['id'] ?>">
                                        <input type="hidden" name="status" value="<?= $l['status'] ?>">
                                        <button type="submit" class="btn btn-secondary btn-sm">
                                            <?= $l['status'] === 'active' ? 'Vô hiệu' : 'Kích hoạt' ?>
                                        </button>
                                    </form>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="extend">
                                        <input type="hidden" name="id" value="<?= $l['id'] ?>">
                                        <button type="submit" class="btn btn-secondary btn-sm">+30 ngày</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Tạo License -->
<div id="createModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">Tạo License Mới</div>
            <button class="close-btn" onclick="closeModal('createModal')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="form-group">
                <label class="form-label">License Key (Để trống để tự động sinh mã)</label>
                <input type="text" name="license_key" class="form-control" placeholder="NC-XXXX-XXXX-XXXX">
            </div>
            <div class="form-group">
                <label class="form-label">Gói bản quyền</label>
                <select name="plan" class="form-control">
                    <option value="standard">Standard</option>
                    <option value="pro" selected>Pro</option>
                    <option value="enterprise">Enterprise</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Số thiết bị tối đa</label>
                <input type="number" name="max_devices" class="form-control" value="3" min="1" max="100">
            </div>
            <div class="form-group">
                <label class="form-label">Thời hạn sử dụng</label>
                <select name="valid_days" class="form-control">
                    <option value="30">30 Ngày (1 tháng)</option>
                    <option value="90">90 Ngày (3 tháng)</option>
                    <option value="365" selected>365 Ngày (1 năm)</option>
                    <option value="1825">5 Năm</option>
                </select>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createModal')">Hủy</button>
                <button type="submit" class="btn btn-primary">Xác nhận tạo</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
