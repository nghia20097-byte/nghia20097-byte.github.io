<?php
require_once __DIR__ . '/includes/auth.php';
use App\Core\Database;

$pageTitle = 'Quản lý ROM Profiles';
$db = Database::getConnection();
$message = null;

$filterModelId = isset($_GET['model_id']) ? (int)$_GET['model_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $modelId = (int)$_POST['model_id'];
        $name = trim($_POST['name'] ?? 'NChangerOS');
        $code = trim($_POST['profile_code'] ?? '');
        $version = trim($_POST['version'] ?? '1.0.0');
        $androidVer = trim($_POST['android_version'] ?? '12.1');
        $apiLevel = (int)($_POST['api_level'] ?? 32);
        $buildId = trim($_POST['build_id'] ?? '');

        if ($modelId > 0 && !empty($code)) {
            $stmt = $db->prepare('
                INSERT INTO rom_profiles (model_id, profile_code, name, version, android_version, api_level, build_id, status)
                VALUES (:mid, :code, :name, :ver, :aver, :api, :bid, "pending_validation")
            ');
            try {
                $stmt->execute([
                    ':mid'  => $modelId,
                    ':code' => $code,
                    ':name' => $name,
                    ':ver'  => $version,
                    ':aver' => $androidVer,
                    ':api'  => $apiLevel,
                    ':bid'  => $buildId
                ]);
                $newRomId = $db->lastInsertId();

                // Create default capability profile
                $db->prepare('
                    INSERT INTO capability_profiles (rom_id, capabilities_json)
                    VALUES (:rid, :caps)
                ')->execute([
                    ':rid'  => $newRomId,
                    ':caps' => json_encode([
                        'device_info' => true,
                        'diagnostics' => true,
                        'backup'      => true,
                        'restore'     => false,
                        'app_install' => true,
                        'recovery'    => true
                    ])
                ]);

                $message = ['type' => 'success', 'text' => "Đã tạo ROM profile {$name} ({$code})"];
            } catch (\Throwable $e) {
                $message = ['type' => 'danger', 'text' => "Lỗi: " . $e->getMessage()];
            }
        }
    } elseif ($action === 'toggle_validation') {
        $id = (int)$_POST['id'];
        $currentStatus = $_POST['status'] ?? 'pending_validation';
        $newStatus = ($currentStatus === 'active') ? 'pending_validation' : 'active';

        $db->prepare('UPDATE rom_profiles SET status = :st WHERE id = :id')->execute([':st' => $newStatus, ':id' => $id]);

        // If validated active, enable restore capability automatically
        if ($newStatus === 'active') {
            $db->prepare('
                UPDATE capability_profiles 
                SET capabilities_json = JSON_SET(capabilities_json, "$.restore", true) 
                WHERE rom_id = :id
            ')->execute([':id' => $id]);
        }

        $message = ['type' => 'success', 'text' => "Đã chuyển trạng thái ROM sang {$newStatus}"];
    }
}

// Fetch all models for dropdown
$allModels = $db->query('
    SELECT m.id, b.name AS brand, m.model_code, m.codename 
    FROM models m 
    JOIN brands b ON b.id = m.brand_id 
    ORDER BY b.name ASC
')->fetchAll(PDO::FETCH_ASSOC);

// Fetch ROMs
$sql = '
    SELECT r.*, b.name AS brand, m.model_code AS hardware_model, m.codename, cp.capabilities_json
    FROM rom_profiles r
    JOIN models m ON m.id = r.model_id
    JOIN brands b ON b.id = m.brand_id
';
if ($filterModelId > 0) {
    $sql .= " WHERE r.model_id = {$filterModelId}";
}
$sql .= ' ORDER BY r.id DESC';

$roms = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/header.php';
?>

<?php if ($message): ?>
    <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid var(--<?= $message['type'] === 'success' ? 'success' : 'danger' ?>); color: #fff; padding: 12px; border-radius: 8px; margin-bottom: 20px;">
        <?= htmlspecialchars($message['text']) ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <div class="card-title">
            Danh sách ROM Profiles
            <?php if ($filterModelId > 0): ?>
                <span style="font-size: 13px; color: var(--text-muted); font-weight: normal;">(Đang lọc theo Model ID: <?= $filterModelId ?> - <a href="roms.php" style="color: var(--accent);">Bỏ lọc</a>)</span>
            <?php endif; ?>
        </div>
        <button class="btn btn-primary" onclick="openModal('addRomModal')">＋ Thêm ROM Profile</button>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Mã Profile</th>
                    <th>Tên ROM</th>
                    <th>Phần cứng áp dụng</th>
                    <th>Android / API</th>
                    <th>Build ID</th>
                    <th>Trạng thái Xác minh</th>
                    <th>Capabilities</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($roms)): ?>
                    <tr><td colspan="9" style="text-align: center; color: var(--text-muted);">Chưa có ROM profile nào</td></tr>
                <?php else: ?>
                    <?php foreach ($roms as $r): ?>
                        <tr>
                            <td>#<?= $r['id'] ?></td>
                            <td><code><?= htmlspecialchars($r['profile_code']) ?></code></td>
                            <td>
                                <b><?= htmlspecialchars($r['name']) ?></b>
                                <div style="font-size: 11px; color: var(--text-muted);">v<?= htmlspecialchars($r['version']) ?></div>
                            </td>
                            <td>
                                <?= htmlspecialchars($r['brand']) ?> <?= htmlspecialchars($r['hardware_model']) ?>
                                <span class="badge badge-info"><?= htmlspecialchars($r['codename']) ?></span>
                            </td>
                            <td>
                                Android <?= htmlspecialchars($r['android_version']) ?>
                                <span style="font-size: 11px; color: var(--text-muted);">(API <?= (int)$r['api_level'] ?>)</span>
                            </td>
                            <td style="font-size: 12px; color: var(--text-muted);"><?= htmlspecialchars($r['build_id'] ?? 'N/A') ?></td>
                            <td>
                                <?php if ($r['status'] === 'active'): ?>
                                    <span class="badge badge-success">✓ Validated</span>
                                <?php elseif ($r['status'] === 'pending_validation'): ?>
                                    <span class="badge badge-warning">⚠ Safe Mode</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Disabled</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                    $caps = json_decode($r['capabilities_json'] ?? '{}', true);
                                    $capsList = [];
                                    foreach ($caps as $k => $v) {
                                        if ($v) $capsList[] = $k;
                                    }
                                    echo '<span style="font-size: 11px; color: var(--text-muted);">' . implode(', ', array_slice($capsList, 0, 3)) . '...</span>';
                                ?>
                            </td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="toggle_validation">
                                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                    <input type="hidden" name="status" value="<?= $r['status'] ?>">
                                    <?php if ($r['status'] === 'active'): ?>
                                        <button type="submit" class="btn btn-secondary btn-sm">Chuyển Safe Mode</button>
                                    <?php else: ?>
                                        <button type="submit" class="btn btn-primary btn-sm">✓ Xác minh Active</button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Thêm ROM -->
<div id="addRomModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">Thêm ROM Profile Mới</div>
            <button class="close-btn" onclick="closeModal('addRomModal')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="form-group">
                <label class="form-label">Chọn Phần Cứng / Model</label>
                <select name="model_id" class="form-control" required>
                    <?php foreach ($allModels as $m): ?>
                        <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['brand']) ?> <?= htmlspecialchars($m['model_code']) ?> (<?= htmlspecialchars($m['codename']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label class="form-label">Tên ROM</label>
                    <input type="text" name="name" class="form-control" value="NChangerOS" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Mã Profile Code</label>
                    <input type="text" name="profile_code" class="form-control" placeholder="rom_star2lte_a12" required>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label class="form-label">Version</label>
                    <input type="text" name="version" class="form-control" value="1.0.0" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Android</label>
                    <input type="text" name="android_version" class="form-control" value="12.1" required>
                </div>
                <div class="form-group">
                    <label class="form-label">API Level</label>
                    <input type="number" name="api_level" class="form-control" value="32" required>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Build ID</label>
                <input type="text" name="build_id" class="form-control" placeholder="SQ3A.220705.004">
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addRomModal')">Hủy</button>
                <button type="submit" class="btn btn-primary">Lưu ROM Profile</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
