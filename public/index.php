<?php
// ============================================================
// NChanger REST API Entry Point (v1.1.0)
// ============================================================

declare(strict_types=1);

// Error reporting configuration
error_reporting(E_ALL);
ini_set('display_errors', '0');

// Define base root
define('BASE_PATH', dirname(__DIR__));

// Register Autoloader
require_once BASE_PATH . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register(BASE_PATH);

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Controllers\SystemController;
use App\Controllers\AuthController;
use App\Controllers\LicenseController;
use App\Controllers\ModelController;
use App\Controllers\RomController;
use App\Controllers\DeviceController;
use App\Controllers\ProfileController;
use App\Controllers\ChangeProfileController;
use App\Controllers\BackupController;
use App\Controllers\RestoreController;
use App\Controllers\ClientController;
use App\Controllers\OpenApiController;

// Load Config
$config = require BASE_PATH . '/config/config.php';

// Global CORS & JSON header handling
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-NChanger-Client, X-NChanger-API');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Initialize Database
try {
    Database::init($config['database']);
} catch (\Throwable $e) {
    Response::error(Response::ERR_SERVER_ERROR, 'System initialization error: ' . $e->getMessage(), 500);
}

// Global Exception Handler
set_exception_handler(function (\Throwable $e) use ($config) {
    $message = $config['app']['debug'] ? $e->getMessage() : 'Internal Server Error';
    $details = $config['app']['debug'] ? ['trace' => $e->getTraceAsString()] : [];
    Response::error(Response::ERR_SERVER_ERROR, $message, 500, $details);
});

$request = new Request();
$router = new Router();

// ============================================================
// API v1 Routes Definition
// ============================================================

$router->group('/api/v1', [], function (Router $r) {

    // 0. OPENAPI & DOCUMENTATION
    $r->get('/openapi.json',     [OpenApiController::class, 'spec']);
    $r->get('/docs',             [OpenApiController::class, 'docs']);

    // 1. SYSTEM
    $r->get('/system/status',    [SystemController::class, 'status']);
    $r->get('/system/version',   [SystemController::class, 'version']);
    $r->get('/system/config',    [SystemController::class, 'config']);

    // 2. AUTHENTICATION
    $r->post('/auth/login',      [AuthController::class, 'login']);
    $r->post('/auth/refresh',    [AuthController::class, 'refresh']);
    $r->post('/auth/logout',     [AuthController::class, 'logout']);
    $r->get('/auth/me',          [AuthController::class, 'me'], [AuthMiddleware::class]);

    // 3. LICENSE
    $r->post('/license/check',      [LicenseController::class, 'check']);
    $r->post('/license/activate',   [LicenseController::class, 'activate']);
    $r->post('/license/deactivate', [LicenseController::class, 'deactivate']);
    $r->get('/license/info',        [LicenseController::class, 'info']);

    // 4. HARDWARE MODELS (Full REST CRUD & Sub-resources)
    $r->get('/models',                          [ModelController::class, 'index']);
    $r->post('/models',                         [ModelController::class, 'create']);
    $r->get('/models/{id}',                     [ModelController::class, 'show']);
    $r->patch('/models/{id}',                   [ModelController::class, 'update']);
    $r->delete('/models/{id}',                  [ModelController::class, 'delete']);
    $r->get('/models/{id}/roms',                [ModelController::class, 'roms']);
    $r->get('/models/{id}/rom-profile',         [ModelController::class, 'romProfile']);
    $r->get('/models/{id}/capabilities',        [ModelController::class, 'capabilities']);
    $r->get('/models/{id}/change-profile',      [ModelController::class, 'changeProfile']);

    // 5. CHANGE PROFILES (Dedicated CRUD)
    $r->get('/change-profiles',                 [ChangeProfileController::class, 'index']);
    $r->post('/change-profiles',                [ChangeProfileController::class, 'create']);
    $r->get('/change-profiles/{id}',            [ChangeProfileController::class, 'show']);
    $r->patch('/change-profiles/{id}',          [ChangeProfileController::class, 'update']);
    $r->delete('/change-profiles/{id}',         [ChangeProfileController::class, 'delete']);

    // 6. ROM PROFILES & PRE-CHANGE COMPATIBILITY CHECK
    $r->get('/roms',               [RomController::class, 'index']);
    $r->get('/roms/{id}',          [RomController::class, 'show']);
    $r->get('/roms/{id}/manifest', [RomController::class, 'manifest']);
    $r->post('/roms/check',        [RomController::class, 'check']);
    $r->post('/rom/probe',         [RomController::class, 'probe']);
    $r->post('/rom/compatibility', [RomController::class, 'compatibility']);

    // 7. ADMIN CREATION ENDPOINTS
    $r->post('/admin/models',           [ModelController::class, 'create']);
    $r->post('/admin/roms',             [RomController::class, 'adminCreate']);
    $r->post('/admin/change-profiles',  [ChangeProfileController::class, 'create']);

    // 8. PROFILES (Legacy alias)
    $r->get('/profiles',                         [ProfileController::class, 'index']);
    $r->get('/profiles/{id}',                    [ProfileController::class, 'show']);
    $r->get('/profiles/{id}/capabilities',       [ProfileController::class, 'capabilities']);
    $r->get('/profiles/{id}/change-policy',      [ProfileController::class, 'changePolicy']);

    // 9. DEVICES
    $r->post('/device/register',    [DeviceController::class, 'register']);
    $r->post('/device/heartbeat',   [DeviceController::class, 'heartbeat']);
    $r->get('/device/{id}',         [DeviceController::class, 'show']);
    $r->get('/device/{id}/status',  [DeviceController::class, 'status']);
    $r->post('/device/unregister',  [DeviceController::class, 'unregister']);

    // 10. BACKUPS
    $r->post('/backup/create',      [BackupController::class, 'create']);
    $r->get('/backup/list',         [BackupController::class, 'list']);
    $r->get('/backup/{id}',         [BackupController::class, 'show']);
    $r->post('/backup/{id}/verify', [BackupController::class, 'verify']);
    $r->delete('/backup/{id}',      [BackupController::class, 'delete']);

    // 11. RESTORES
    $r->post('/restore/prepare',          [RestoreController::class, 'prepare']);
    $r->post('/restore/verify',           [RestoreController::class, 'verify']);
    $r->post('/restore/execute',          [RestoreController::class, 'execute']);
    $r->get('/restore/{id}/status',       [RestoreController::class, 'status']);
    $r->post('/restore/{id}/complete',    [RestoreController::class, 'complete']);

    // 12. CLIENT UPDATES
    $r->get('/client/version',      [ClientController::class, 'version']);
    $r->get('/client/update',       [ClientController::class, 'update']);
});

// Root welcome / health ping
$router->get('/', function () {
    Response::success([
        'service'   => 'NChanger API Server',
        'version'   => '1.1.0',
        'status'    => 'running',
        'docs'      => '/api/v1/docs',
        'spec'      => '/api/v1/openapi.json',
        'admin_web' => '/admin/'
    ]);
});

// Dispatch request
$router->dispatch($request);
