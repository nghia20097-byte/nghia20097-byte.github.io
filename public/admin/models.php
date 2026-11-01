<?php
require_once __DIR__ . '/includes/auth.php';
use App\Core\Database;

$pageTitle = 'Quản lý Hardware Models';
$db = Database::getConnection();
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $brandId = (int)($_POST['brand_id'] ?? 0);
        $modelCode = trim($_POST['model_code'] ?? '');
        $name = trim($_POST['marketing_name'] ?? '');
        $codename = trim($_POST['codename'] ?? '');
        $soc = trim($_POST['soc'] ?? '');
        $arch = trim($_POST['architecture'] ?? 'arm64');
        $androidMin = trim($_POST['android_min'] ?? '');
        $androidMax = trim($_POST['android_max'] ?? '');

        if ($brandId > 0 && !empty($modelCode) && !empty($name)) {
            $stmt = $db->prepare('
                INSERT INTO models (brand_id, model_code, marketing_name, codename, soc, architecture, android_min, android_max, status)
                VALUES (:bid, :code, :name, :codename, :soc, :arch, :amin, :amax, "active")
            ');
            try {
                $stmt->execute([
                    ':bid'      => $brandId,
                    ':code'     => $modelCode,
                    ':name'     => $name,
                    ':codename' => !empty($codename) ? $codename : null,
                    ':soc'      => !empty($soc) ? $soc : null,
                    ':arch'     => $arch,
                    ':amin'     => !empty($androidMin) ? $androidMin : null,
                    ':amax'     => !empty($androidMax) ? $androidMax : null
                ]);
                $message = ['type' => 'success', 'text' => "Đã thêm Model {$modelCode} ({$name})"];
            } catch (\Throwable $e) {
                $message = ['type' => 'danger', 'text' => "Lỗi: " . $e->getMessage()];
            }
        }
    } elseif ($action === 'toggle_status') {
        $id = (int)$_POST['id'];
        $newStatus = $_POST['status'] === 'active' ? 'disabled' : 'active';
        $db->prepare('UPDATE models SET status = :st WHERE id = :id')->execute([':st' => $newStatus, ':id' => $id]);
        $message = ['type' => 'success', 'text' => "Đã cập nhật trạng thái model"];
    }
}

// Fetch all brands
$brands = $db->query('SELECT * FROM brands ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);

// Fetch models with brand join
$models = $db->query('
    SELECT m.*, b.name AS brand_name, COUNT(DISTINCT r.id) AS rom_count, COUNT(DISTINCT cp.id) AS change_profile_count
    FROM models m
    JOIN brands b ON b.id = m.brand_id
    LEFT JOIN rom_profiles r ON r.model_id = m.id
    LEFT JOIN change_profiles cp ON cp.model_id = m.id
    GROUP BY m.id
    ORDER BY b.name ASC, m.model_code ASC
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
        <div class="card-title">Danh sách Thiết Bị / Models Hỗ Trợ</div>
        <button class="btn btn-primary" onclick="openModal('addModelModal')">＋ Thêm Model Mới</button>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Hãng (Brand)</th>
                    <th>Mã Model</th>
                    <th>Tên thương mại</th>
                    <th>Codename</th>
                    <th>SoC / CPU</th>
                    <th>Android Hỗ trợ</th>
                    <th>ROM Profiles</th>
                    <th>Change Profiles</th>
                    <th>Trạng thái</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="11" style="text-align: center; color: var(--text-muted);">Chưa có model nào</td></tr>
                <?php else: ?>
                    <?php foreach ($models as $m): ?>
                        <tr>
                            <td>#<?= $m['id'] ?></td>
                            <td><b><?= htmlspecialchars($m['brand_name']) ?></b></td>
                            <td><code><?= htmlspecialchars($m['model_code']) ?></code></td>
                            <td><?= htmlspecialchars($m['marketing_name']) ?></td>
                            <td><span class="badge badge-info"><?= htmlspecialchars($m['codename']) ?></span></td>
                            <td><?= htmlspecialchars($m['soc'] ?? 'N/A') ?></td>
                            <td style="font-size: 12px; color: var(--text-muted);">
                                <?= htmlspecialchars($m['android_min'] ?? '-') ?> → <?= htmlspecialchars($m['android_max'] ?? '-') ?>
                            </td>
                            <td>
                                <a href="roms.php?model_id=<?= $m['id'] ?>" style="color: var(--accent); text-decoration: none;">
                                    <b><?= (int)$m['rom_count'] ?> ROMs</b> ↗
                                </a>
                            </td>
                            <td>
                                <a href="change_profiles.php" style="color: var(--accent); text-decoration: none;">
                                    <b><?= (int)$m['change_profile_count'] ?> Profiles</b> ↗
                                </a>
                            </td>
                            <td>
                                <?php if ($m['status'] === 'active'): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Disabled</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                    <input type="hidden" name="status" value="<?= $m['status'] ?>">
                                    <button type="submit" class="btn btn-secondary btn-sm">
                                        <?= $m['status'] === 'active' ? 'Vô hiệu hóa' : 'Kích hoạt lại' ?>
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

<!-- Modal: Thêm Model -->
<div id="addModelModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">Thêm Thiết Bị / Model Mới</div>
            <button class="close-btn" onclick="closeModal('addModelModal')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label class="form-label">Chọn Hãng (Brand)</label>
                    <select name="brand_id" class="form-control" required>
                        <?php foreach ($brands as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Mã Model (Model Code)</label>
                    <input type="text" name="model_code" class="form-control" placeholder="SM-G965F" required>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Tên thương mại (Marketing Name)</label>
                <input type="text" name="marketing_name" class="form-control" placeholder="Galaxy S9+" required>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label class="form-label">Codename thiết bị</label>
                    <input type="text" name="codename" class="form-control" placeholder="star2lte">
                </div>
                <div class="form-group">
                    <label class="form-label">Chip xử lý (SoC)</label>
                    <input type="text" name="soc" class="form-control" placeholder="Exynos9810">
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label class="form-label">Kiến trúc (Architecture)</label>
                    <input type="text" name="architecture" class="form-control" value="arm64">
                </div>
                <div class="form-group">
                    <label class="form-label">Android Min</label>
                    <input type="text" name="android_min" class="form-control" placeholder="12">
                </div>
                <div class="form-group">
                    <label class="form-label">Android Max</label>
                    <input type="text" name="android_max" class="form-control" placeholder="12.1">
                </div>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addModelModal')">Hủy</button>
                <button type="submit" class="btn btn-primary">Lưu Model</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
