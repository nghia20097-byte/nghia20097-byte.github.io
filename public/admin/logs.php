<?php
require_once __DIR__ . '/includes/auth.php';
use App\Core\Database;

$pageTitle = 'Nhật Ký Hệ Thống (Audit Logs)';
$db = Database::getConnection();

$actionFilter = trim($_GET['action'] ?? '');
$sql = '
    SELECT a.*, u.username 
    FROM audit_logs a
    LEFT JOIN users u ON u.id = a.user_id
';
$params = [];
if (!empty($actionFilter)) {
    $sql .= ' WHERE a.action LIKE :act';
    $params[':act'] = '%' . $actionFilter . '%';
}
$sql .= ' ORDER BY a.id DESC LIMIT 100';

$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <div class="card-title">Nhật Ký Thao Tác & Hoạt Động (Audit Logs)</div>
        <form method="GET" style="display: flex; gap: 8px;">
            <input type="text" name="action" class="form-control" style="width: 220px;" placeholder="Lọc theo hành động..." value="<?= htmlspecialchars($actionFilter) ?>">
            <button type="submit" class="btn btn-secondary btn-sm">Lọc</button>
            <?php if (!empty($actionFilter)): ?>
                <a href="logs.php" class="btn btn-secondary btn-sm">Xóa lọc</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Thời gian</th>
                    <th>Hành động</th>
                    <th>Đối tượng tác động</th>
                    <th>Người thực hiện</th>
                    <th>Địa chỉ IP</th>
                    <th>Dữ liệu kèm theo</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="7" style="text-align: center; color: var(--text-muted);">Không có nhật ký nào được ghi nhận</td></tr>
                <?php else: ?>
                    <?php foreach ($logs as $l): ?>
                        <tr>
                            <td>#<?= $l['id'] ?></td>
                            <td style="font-size: 12px; color: var(--text-muted); white-space: nowrap;"><?= date('Y-m-d H:i:s', strtotime($l['created_at'])) ?></td>
                            <td><span class="badge badge-info"><?= htmlspecialchars($l['action']) ?></span></td>
                            <td>
                                <b><?= htmlspecialchars($l['target_type'] ?? '-') ?></b>
                                <div style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($l['target_id'] ?? '') ?></div>
                            </td>
                            <td><?= htmlspecialchars($l['username'] ?? 'Khách / Device') ?></td>
                            <td><code><?= htmlspecialchars($l['ip_address']) ?></code></td>
                            <td>
                                <?php if (!empty($l['metadata_json'])): ?>
                                    <pre style="max-width: 280px; overflow-x: auto; font-size: 10px; background: rgba(0,0,0,0.3); padding: 4px; border-radius: 4px; color: var(--text-muted);"><?= htmlspecialchars($l['metadata_json']) ?></pre>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-size: 11px;">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
