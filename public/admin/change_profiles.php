<?php
require_once __DIR__ . '/includes/auth.php';
use App\Core\Database;

$pageTitle = 'Quản lý Change Profiles & Workflow';
$db = Database::getConnection();
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $modelId = (int)$_POST['model_id'];
        $romId = !empty($_POST['rom_profile_id']) ? (int)$_POST['rom_profile_id'] : null;
        $name = trim($_POST['name'] ?? '');
        $version = trim($_POST['version'] ?? '1.0.0');
        $wfCode = trim($_POST['workflow_code'] ?? 'NCHANGER_STANDARD_V1');
        $policyJsonRaw = trim($_POST['policy_json'] ?? '');

        // Validate JSON
        $policyData = json_decode($policyJsonRaw, true);
        if ($policyData === null && !empty($policyJsonRaw)) {
            $message = ['type' => 'danger', 'text' => 'Cú pháp Policy JSON không hợp lệ!'];
        } elseif ($modelId > 0 && !empty($name)) {
            $stmt = $db->prepare('
                INSERT INTO change_profiles (model_id, rom_profile_id, name, version, workflow_code, policy_json, status)
                VALUES (:mid, :rid, :name, :ver, :wf, :pol, "active")
            ');
            try {
                $stmt->execute([
                    ':mid'  => $modelId,
                    ':rid'  => $romId,
                    ':name' => $name,
                    ':ver'  => $version,
                    ':wf'   => $wfCode,
                    ':pol'  => json_encode($policyData ?: [
                        'backup'      => ['enabled' => true, 'required_before_workflow' => true],
                        'restore'     => ['enabled' => true],
                        'diagnostics' => ['enabled' => true],
                        'recovery'    => ['enabled' => true],
                        'app_install' => ['enabled' => true],
                        'rom_check'   => ['required' => true],
                        'safe_mode'   => ['enabled' => true]
                    ], JSON_PRETTY_PRINT)
                ]);
                $message = ['type' => 'success', 'text' => "Đã tạo Change Profile '{$name}'"];
            } catch (\Throwable $e) {
                $message = ['type' => 'danger', 'text' => "Lỗi: " . $e->getMessage()];
            }
        }
    } elseif ($action === 'toggle_status') {
        $id = (int)$_POST['id'];
        $newStatus = $_POST['status'] === 'active' ? 'disabled' : 'active';
        $db->prepare('UPDATE change_profiles SET status = :st WHERE id = :id')->execute([':st' => $newStatus, ':id' => $id]);
        $message = ['type' => 'success', 'text' => 'Đã cập nhật trạng thái Change Profile'];
    }
}

// Fetch all models & ROMs for modal dropdowns
$models = $db->query('SELECT m.id, b.name AS brand, m.model_code, m.codename FROM models m JOIN brands b ON b.id = m.brand_id WHERE m.status = "active"')->fetchAll(PDO::FETCH_ASSOC);
$roms = $db->query('SELECT id, name, version, model_id FROM rom_profiles WHERE status != "disabled"')->fetchAll(PDO::FETCH_ASSOC);

// Fetch change profiles
$profiles = $db->query('
    SELECT cp.*, b.name AS brand, m.model_code, m.codename, r.name AS rom_name, r.version AS rom_version
    FROM change_profiles cp
    JOIN models m ON m.id = cp.model_id
    JOIN brands b ON b.id = m.brand_id
    LEFT JOIN rom_profiles r ON r.id = cp.rom_profile_id
    ORDER BY cp.id DESC
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
        <div class="card-title">Danh sách Change Profiles & Chính Sách Vận Hành (Policies)</div>
        <button class="btn btn-primary" onclick="openModal('addProfileModal')">＋ Tạo Change Profile</button>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tên Profile</th>
                    <th>Phần cứng / Model</th>
                    <th>ROM Baseline</th>
                    <th>Mã Workflow</th>
                    <th>Phiên bản</th>
                    <th>Chính sách (Policy JSON)</th>
                    <th>Trạng thái</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($profiles)): ?>
                    <tr><td colspan="9" style="text-align: center; color: var(--text-muted);">Chưa có Change Profile nào</td></tr>
                <?php else: ?>
                    <?php foreach ($profiles as $p): ?>
                        <tr>
                            <td>#<?= $p['id'] ?></td>
                            <td>
                                <b><?= htmlspecialchars($p['name']) ?></b>
                            </td>
                            <td>
                                <?= htmlspecialchars($p['brand']) ?> <code><?= htmlspecialchars($p['model_code']) ?></code>
                                <div style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($p['codename']) ?></div>
                            </td>
                            <td>
                                <?= htmlspecialchars($p['rom_name'] ?? 'Generic baseline') ?>
                                <div style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($p['rom_version'] ?? '') ?></div>
                            </td>
                            <td>
                                <span class="badge badge-info"><?= htmlspecialchars($p['workflow_code']) ?></span>
                            </td>
                            <td>v<?= htmlspecialchars($p['version']) ?></td>
                            <td>
                                <pre style="font-size: 10px; max-width: 200px; max-height: 60px; overflow-y: auto; background: rgba(0,0,0,0.3); padding: 4px; border-radius: 4px;"><?= htmlspecialchars($p['policy_json']) ?></pre>
                            </td>
                            <td>
                                <?php if ($p['status'] === 'active'): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Disabled</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <input type="hidden" name="status" value="<?= $p['status'] ?>">
                                    <button type="submit" class="btn btn-secondary btn-sm">
                                        <?= $p['status'] === 'active' ? 'Vô hiệu hóa' : 'Kích hoạt' ?>
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

<!-- Modal: Tạo Change Profile -->
<div id="addProfileModal" class="modal">
    <div class="modal-content" style="max-width: 650px;">
        <div class="modal-header">
            <div class="modal-title">Tạo Change Profile Mới</div>
            <button class="close-btn" onclick="closeModal('addProfileModal')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label class="form-label">Chọn Phần cứng (Model)</label>
                    <select name="model_id" class="form-control" required>
                        <?php foreach ($models as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['brand']) ?> <?= htmlspecialchars($m['model_code']) ?> (<?= htmlspecialchars($m['codename']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Gắn với ROM Profile</label>
                    <select name="rom_profile_id" class="form-control">
                        <option value="">-- Generic / Tất cả ROM --</option>
                        <?php foreach ($roms as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?> (v<?= htmlspecialchars($r['version']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label class="form-label">Tên Profile</label>
                    <input type="text" name="name" class="form-control" placeholder="NChangerOS Star2LTE A12" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Phiên bản</label>
                    <input type="text" name="version" class="form-control" value="1.0.0" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Mã Nghiệp Vụ Cố Định (Workflow Code)</label>
                <select name="workflow_code" class="form-control">
                    <option value="NCHANGER_STANDARD_V1" selected>NCHANGER_STANDARD_V1 (Quy trình chuẩn)</option>
                    <option value="NCHANGER_BACKUP_V1">NCHANGER_BACKUP_V1 (Chỉ sao lưu)</option>
                    <option value="NCHANGER_RESTORE_V1">NCHANGER_RESTORE_V1 (Chỉ phục hồi)</option>
                    <option value="NCHANGER_DIAGNOSTICS_V1">NCHANGER_DIAGNOSTICS_V1 (Chẩn đoán thiết bị)</option>
                    <option value="NCHANGER_RECOVERY_V1">NCHANGER_RECOVERY_V1 (Recovery tool)</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Chính sách vận hành (Policy JSON)</label>
                <textarea name="policy_json" class="form-control" rows="8" style="font-family: monospace; font-size: 11px;">{
  "backup": {
    "enabled": true,
    "required_before_workflow": true
  },
  "restore": {
    "enabled": true
  },
  "diagnostics": {
    "enabled": true
  },
  "recovery": {
    "enabled": true
  },
  "app_install": {
    "enabled": true
  },
  "rom_check": {
    "required": true
  },
  "safe_mode": {
    "enabled": true
  },
  "workflow": {
    "timeout": 1800,
    "retry": 2
  }
}</textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addProfileModal')">Hủy</button>
                <button type="submit" class="btn btn-primary">Lưu Profile</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
