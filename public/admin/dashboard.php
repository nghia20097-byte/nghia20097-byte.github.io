<?php
require_once __DIR__ . '/includes/auth.php';
use App\Core\Database;

$pageTitle = 'Dashboard Tổng Quan';
$db = Database::getConnection();

// 1. License Stats
$licStmt = $db->query('
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status = "active" AND (expires_at IS NULL OR expires_at > NOW()) THEN 1 ELSE 0 END) AS active,
        SUM(CASE WHEN expires_at <= NOW() THEN 1 ELSE 0 END) AS expired,
        SUM(CASE WHEN status = "disabled" THEN 1 ELSE 0 END) AS disabled
    FROM licenses
');
$licStats = $licStmt->fetch(PDO::FETCH_ASSOC);

// 2. Device Stats
$devStmt = $db->query('
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status = "online" AND last_seen_at >= NOW() - INTERVAL 10 MINUTE THEN 1 ELSE 0 END) AS online,
        SUM(CASE WHEN DATE(last_seen_at) = CURDATE() THEN 1 ELSE 0 END) AS today
    FROM devices
');
$devStats = $devStmt->fetch(PDO::FETCH_ASSOC);

// 3. Models & ROMs Stats
$modelCount = (int)$db->query('SELECT COUNT(*) FROM models WHERE status = "active"')->fetchColumn();
$romCount = (int)$db->query('SELECT COUNT(*) FROM rom_profiles WHERE status = "active"')->fetchColumn();
$pendingRomCount = (int)$db->query('SELECT COUNT(*) FROM rom_profiles WHERE status = "pending_validation"')->fetchColumn();

// 4. Audit & API logs today
$logsToday = (int)$db->query('SELECT COUNT(*) FROM audit_logs WHERE DATE(created_at) = CURDATE()')->fetchColumn();

// 5. Recent devices
$recentDevices = $db->query('
    SELECT d.*, b.name AS brand, m.model_code AS hardware_model, m.codename, r.name AS rom_name, l.license_key
    FROM devices d
    LEFT JOIN models m ON m.id = d.model_id
    LEFT JOIN brands b ON b.id = m.brand_id
    LEFT JOIN rom_profiles r ON r.id = d.rom_id
    LEFT JOIN licenses l ON l.id = d.license_id
    ORDER BY d.last_seen_at DESC
    LIMIT 5
')->fetchAll(PDO::FETCH_ASSOC);

// 6. Recent Audit Logs
$recentLogs = $db->query('
    SELECT a.*, u.username 
    FROM audit_logs a
    LEFT JOIN users u ON u.id = a.user_id
    ORDER BY a.id DESC 
    LIMIT 6
')->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/header.php';
?>

<!-- Section: Stats KPI Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">TỔNG LICENSE</div>
        <div class="stat-value"><?= number_format((int)$licStats['total']) ?></div>
        <div class="stat-sub">
            <span style="color: var(--success);">● Active: <?= (int)$licStats['active'] ?></span> | 
            <span style="color: var(--warning);">● Expired: <?= (int)$licStats['expired'] ?></span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-label">DEVICES ONLINE</div>
        <div class="stat-value" style="color: var(--success);"><?= number_format((int)$devStats['online']) ?></div>
        <div class="stat-sub">
            Hôm nay: <b><?= (int)$devStats['today'] ?></b> | Tổng: <?= (int)$devStats['total'] ?>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-label">MODELS HỖ TRỢ</div>
        <div class="stat-value"><?= number_format($modelCount) ?></div>
        <div class="stat-sub">Phần cứng đã kiểm thử</div>
    </div>

    <div class="stat-card">
        <div class="stat-label">ROM PROFILES</div>
        <div class="stat-value"><?= number_format($romCount) ?></div>
        <div class="stat-sub">
            <?php if ($pendingRomCount > 0): ?>
                <span style="color: var(--warning);">⚠ Chờ xác minh: <?= $pendingRomCount ?></span>
            <?php else: ?>
                Tất cả đã xác minh
            <?php endif; ?>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-label">HOẠT ĐỘNG HÔM NAY</div>
        <div class="stat-value"><?= number_format($logsToday) ?></div>
        <div class="stat-sub">Audit logs ghi nhận</div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 20px;">
    <!-- Recent Devices -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">Thiết bị kết nối gần đây</div>
            <a href="devices.php" class="btn btn-secondary btn-sm">Xem tất cả</a>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Device UUID</th>
                        <th>Model</th>
                        <th>ROM</th>
                        <th>Trạng thái</th>
                        <th>Last Seen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentDevices)): ?>
                        <tr><td colspan="5" style="text-align: center; color: var(--text-muted);">Chưa có thiết bị nào kết nối</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentDevices as $d): ?>
                            <tr>
                                <td><code><?= htmlspecialchars(substr($d['device_uuid'], 0, 14)) ?>...</code></td>
                                <td><?= htmlspecialchars($d['hardware_model'] ?? 'N/A') ?> <span style="font-size: 11px; color: var(--text-muted);">(<?= htmlspecialchars($d['codename'] ?? '') ?>)</span></td>
                                <td><?= htmlspecialchars($d['rom_name'] ?? 'Chưa xác định') ?></td>
                                <td>
                                    <?php if ($d['status'] === 'online'): ?>
                                        <span class="badge badge-success">Online</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">Offline</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 12px; color: var(--text-muted);"><?= $d['last_seen_at'] ? date('H:i d/m', strtotime($d['last_seen_at'])) : '-' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Logs -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">Nhật ký Audit gần nhất</div>
            <a href="logs.php" class="btn btn-secondary btn-sm">Chi tiết</a>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Hành động</th>
                        <th>Người / IP</th>
                        <th>Thời gian</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentLogs)): ?>
                        <tr><td colspan="3" style="text-align: center; color: var(--text-muted);">Chưa có nhật ký ghi nhận</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentLogs as $log): ?>
                            <tr>
                                <td>
                                    <span class="badge badge-info"><?= htmlspecialchars($log['action']) ?></span>
                                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 3px;"><?= htmlspecialchars($log['target_type'] ?? '') ?> <?= htmlspecialchars($log['target_id'] ?? '') ?></div>
                                </td>
                                <td style="font-size: 12px;">
                                    <b><?= htmlspecialchars($log['username'] ?? 'System') ?></b>
                                    <div style="color: var(--text-muted); font-size: 11px;"><?= htmlspecialchars($log['ip_address']) ?></div>
                                </td>
                                <td style="font-size: 11px; color: var(--text-muted);"><?= date('H:i:s d/m', strtotime($log['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
