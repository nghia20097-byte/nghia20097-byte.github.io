<?php
namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use PDO;

class SystemController
{
    public function status(Request $request): void
    {
        Response::success([
            'name'        => 'NChanger API',
            'version'     => '1.0.0',
            'api_version' => 1,
            'status'      => 'online',
            'timestamp'   => date('c')
        ]);
    }

    public function version(Request $request): void
    {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('api_version', 'min_client_version')");
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        Response::success([
            'api'            => 1,
            'api_version'    => $settings['api_version'] ?? '1.0.0',
            'minimum_client' => $settings['min_client_version'] ?? '1.0.0',
            'latest_client'  => '1.0.0'
        ]);
    }

    public function config(Request $request): void
    {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT setting_key, setting_value, description FROM system_settings");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        Response::success([
            'system' => $settings,
            'server_time' => date('c')
        ]);
    }
}
