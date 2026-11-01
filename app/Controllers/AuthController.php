<?php
namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use PDO;

class AuthController
{
    public function login(Request $request): void
    {
        $username = trim((string)$request->get('username', ''));
        $password = (string)$request->get('password', '');

        if (empty($username) || empty($password)) {
            Response::error(Response::ERR_VALIDATION_FAILED, 'Username and password are required.', 422);
        }

        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT * FROM users WHERE username = :u OR email = :u LIMIT 1');
        $stmt->execute([':u' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            Response::error(Response::ERR_AUTH_INVALID, 'Invalid username or password.', 401);
        }

        if ($user['status'] !== 'active') {
            Response::error(Response::ERR_AUTH_INVALID, 'Account is not active.', 403);
        }

        // Generate tokens
        $accessToken = bin2hex(random_bytes(32));
        $refreshToken = bin2hex(random_bytes(32));
        $accessExpires = date('Y-m-d H:i:s', time() + 86400); // 24 hours
        $refreshExpires = date('Y-m-d H:i:s', time() + (86400 * 30)); // 30 days

        // Store hashed tokens in DB
        $ins = $db->prepare('
            INSERT INTO access_tokens (user_id, token_hash, type, expires_at)
            VALUES (:uid, :thash, :ttype, :exp)
        ');

        $ins->execute([
            ':uid'   => $user['id'],
            ':thash' => hash('sha256', $accessToken),
            ':ttype' => 'access',
            ':exp'   => $accessExpires
        ]);

        $ins->execute([
            ':uid'   => $user['id'],
            ':thash' => hash('sha256', $refreshToken),
            ':ttype' => 'refresh',
            ':exp'   => $refreshExpires
        ]);

        // Audit log
        $this->logAudit($user['id'], 'AUTH_LOGIN', 'users', (string)$user['id'], $request);

        Response::success([
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type'    => 'Bearer',
            'expires_in'    => 86400,
            'user' => [
                'id'       => $user['id'],
                'username' => $user['username'],
                'role'     => $user['role']
            ]
        ]);
    }

    public function refresh(Request $request): void
    {
        $refreshToken = trim((string)$request->get('refresh_token', ''));
        if (empty($refreshToken)) {
            Response::error(Response::ERR_VALIDATION_FAILED, 'Refresh token is required.', 422);
        }

        $db = Database::getConnection();
        $tokenHash = hash('sha256', $refreshToken);

        $stmt = $db->prepare('
            SELECT * FROM access_tokens
            WHERE token_hash = :thash AND type = "refresh" AND revoked = 0 AND expires_at > NOW()
            LIMIT 1
        ');
        $stmt->execute([':thash' => $tokenHash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            Response::error(Response::ERR_AUTH_INVALID, 'Invalid or expired refresh token.', 401);
        }

        // Revoke used refresh token
        $db->prepare('UPDATE access_tokens SET revoked = 1 WHERE id = :id')->execute([':id' => $row['id']]);

        // Issue new pair
        $newAccess = bin2hex(random_bytes(32));
        $newRefresh = bin2hex(random_bytes(32));
        $accessExpires = date('Y-m-d H:i:s', time() + 86400);
        $refreshExpires = date('Y-m-d H:i:s', time() + (86400 * 30));

        $ins = $db->prepare('INSERT INTO access_tokens (user_id, token_hash, type, expires_at) VALUES (:uid, :thash, :ttype, :exp)');
        $ins->execute([':uid' => $row['user_id'], ':thash' => hash('sha256', $newAccess), ':ttype' => 'access', ':exp' => $accessExpires]);
        $ins->execute([':uid' => $row['user_id'], ':thash' => hash('sha256', $newRefresh), ':ttype' => 'refresh', ':exp' => $refreshExpires]);

        Response::success([
            'access_token'  => $newAccess,
            'refresh_token' => $newRefresh,
            'token_type'    => 'Bearer',
            'expires_in'    => 86400
        ]);
    }

    public function logout(Request $request): void
    {
        $token = $request->getBearerToken();
        if ($token) {
            $db = Database::getConnection();
            $db->prepare('UPDATE access_tokens SET revoked = 1 WHERE token_hash = :thash')
               ->execute([':thash' => hash('sha256', $token)]);
        }
        Response::success(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request): void
    {
        $user = $request->getAttribute('user');
        Response::success([
            'user' => [
                'id'       => $user['user_id'],
                'username' => $user['username'],
                'email'    => $user['email'],
                'role'     => $user['role'],
                'status'   => $user['status']
            ]
        ]);
    }

    private function logAudit(int $userId, string $action, string $targetType, string $targetId, Request $request): void
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare('
                INSERT INTO audit_logs (user_id, action, target_type, target_id, ip_address, user_agent)
                VALUES (:uid, :action, :tt, :tid, :ip, :ua)
            ');
            $stmt->execute([
                ':uid'    => $userId,
                ':action' => $action,
                ':tt'     => $targetType,
                ':tid'    => $targetId,
                ':ip'     => $request->getClientIp(),
                ':ua'     => $request->getHeader('user-agent') ?? 'unknown'
            ]);
        } catch (\Throwable) {
            // Ignore audit logging error to avoid blocking response
        }
    }
}
