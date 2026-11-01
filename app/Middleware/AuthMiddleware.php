<?php
namespace App\Middleware;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use PDO;

class AuthMiddleware
{
    public function handle(Request $request): void
    {
        $token = $request->getBearerToken();
        if (!$token) {
            Response::error(Response::ERR_AUTH_REQUIRED, 'Authorization Bearer token is required.', 401);
        }

        $tokenHash = hash('sha256', $token);
        $db = Database::getConnection();

        $stmt = $db->prepare('
            SELECT t.id AS token_id, t.user_id, t.expires_at, u.username, u.email, u.role, u.status
            FROM access_tokens t
            JOIN users u ON u.id = t.user_id
            WHERE t.token_hash = :token_hash
              AND t.revoked = 0
              AND t.type = "access"
              AND t.expires_at > NOW()
            LIMIT 1
        ');
        $stmt->execute([':token_hash' => $tokenHash]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            Response::error(Response::ERR_AUTH_INVALID, 'Token is invalid or has expired.', 401);
        }

        if ($user['status'] !== 'active') {
            Response::error(Response::ERR_AUTH_INVALID, 'User account is not active.', 403);
        }

        $request->setAttribute('user', $user);
    }
}
