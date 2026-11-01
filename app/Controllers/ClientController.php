<?php
namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use PDO;

class ClientController
{
    public function version(Request $request): void
    {
        $clientVersion = $request->getHeader('x-nchanger-client') ?? $request->getQuery('version', '1.0.0');

        $db = Database::getConnection();
        $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('min_client_version', 'latest_client_version', 'client_download_url', 'client_changelog')");
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $minVersion = $settings['min_client_version'] ?? '1.0.0';
        $latestVersion = $settings['latest_client_version'] ?? '1.0.0';
        $downloadUrl = $settings['client_download_url'] ?? 'https://domain.com/downloads/NChanger-latest.zip';
        $changelog = $settings['client_changelog'] ?? 'Initial release with Galaxy S9+ (star2lte) support.';

        $updateRequired = version_compare($clientVersion, $minVersion, '<');
        $updateAvailable = version_compare($clientVersion, $latestVersion, '<');

        Response::success([
            'client_version'   => $clientVersion,
            'latest_version'   => $latestVersion,
            'minimum_version'  => $minVersion,
            'update_available' => $updateAvailable,
            'update_required'  => $updateRequired,
            'download_url'     => $downloadUrl,
            'changelog'        => $changelog
        ]);
    }

    public function update(Request $request): void
    {
        $this->version($request);
    }
}
