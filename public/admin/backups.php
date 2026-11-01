<?php
require_once __DIR__ . '/includes/auth.php';
use App\Core\Database;

$pageTitle = 'Quản lý Backups & Restore Jobs';
$db = Database::getConnection();

// Backups list
$backups = $db->query('
    SELECT b.*, d.device_uuid, m.model_code AS hardware_model, m.codename, r.name AS rom_name
    FROM backups b
    LEFT JOIN devices d ON d.id = b.device_id
    LEFT JOIN models m ON m.id = b.model_id
    LEFT JOIN rom_profiles r ON r.id = b.rom_id
    ORDER BY b.id DESC
    LIMIT 50
')->fetchAll(PDO::FETCH_ASSOC);

// Restore jobs list
$restoreJobs = $db->query('
    SELECT rj.*, d.device_uuid, b.backup_code
    FROM restore_jobs rj
    LEFT JOIN devices d ON d.id = rj.device_id
    LEFT JOIN backups b ON b.id = rj.backup_id
    ORDER BY rj.id DESC
    LIMIT 50
')->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <div class="card-title">Danh sách Bản Sao Lưu (Backups Metadata)</div>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Backup Code</th>
                    <th>Thiết bị</th>
                    <th>Model / ROM</th>
                    <th>Loại</th>
                    <th>Dung lượng</th>
                    <th>Checksum SHA-256</th>
                    <th>Trạng thái</th>
                    <th>Thời gian tạo</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($backups)): ?>
                    <tr><td colspan="8" style="text-align: center; color: var(--text-muted);">Chưa có bản sao lưu nào</td></tr>
                <?php else: ?>
                    <?php foreach ($backups as $b): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($b['backup_code']) ?></code></td>
                            <td><code><?= htmlspecialchars(substr($b['device_uuid'] ?? '', 0, 12)) ?>...</code></td>
                            <td>
                                <?= htmlspecialchars($b['hardware_model'] ?? 'N/A') ?>
                                <div style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($b['rom_name'] ?? '') ?></div>
                            </td>
                            <td><span class="badge badge-info"><?= htmlspecialchars($b['backup_type']) ?></span></td>
                            <td><?= number_format($b['size_bytes'] / (1024 * 1024), 2) ?> MB</td>
                            <td>
                                <?php if (!empty($b['sha256'])): ?>
                                    <code style="font-size: 11px;"><?= htmlspecialchars(substr($b['sha256'], 0, 16)) ?>...</code>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-size: 11px;">Chưa hash</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($b['status'] === 'verified'): ?>
                                    <span class="badge badge-success">Verified</span>
                                <?php elseif ($b['status'] === 'corrupt'): ?>
                                    <span class="badge badge-danger">Corrupt</span>
                                <?php elseif ($b['status'] === 'deleted'): ?>
                                    <span class="badge badge-secondary">Deleted</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 12px; color: var(--text-muted);"><?= date('H:i d/m/Y', strtotime($b['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">Tiến Trình Khôi Phục (Restore Jobs)</div>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Job Code</th>
                    <th>Thiết bị</th>
                    <th>Bản sao lưu</th>
                    <th>Trạng thái</th>
                    <th>Ghi chú / Lỗi</th>
                    <th>Bắt đầu</th>
                    <th>Hoàn tất</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($restoreJobs)): ?>
                    <tr><td colspan="7" style="text-align: center; color: var(--text-muted);">Chưa có tiến trình khôi phục nào</td></tr>
                <?php else: ?>
                    <?php foreach ($restoreJobs as $rj): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($rj['job_code']) ?></code></td>
                            <td><code><?= htmlspecialchars(substr($rj['device_uuid'] ?? '', 0, 12)) ?>...</code></td>
                            <td><code><?= htmlspecialchars($rj['backup_code'] ?? '') ?></code></td>
                            <td>
                                <?php if ($rj['status'] === 'completed'): ?>
                                    <span class="badge badge-success">Completed</span>
                                <?php elseif ($rj['status'] === 'failed'): ?>
                                    <span class="badge badge-danger">Failed</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">In Progress</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 12px; color: var(--danger);"><?= htmlspecialchars($rj['error_message'] ?? '-') ?></td>
                            <td style="font-size: 12px; color: var(--text-muted);"><?= date('H:i:s d/m', strtotime($rj['created_at'])) ?></td>
                            <td style="font-size: 12px; color: var(--text-muted);"><?= $rj['completed_at'] ? date('H:i:s d/m', strtotime($rj['completed_at'])) : '-' ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
