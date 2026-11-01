<?php
namespace App\Core;

class Response
{
    // Standard error codes specified in architecture
    public const ERR_AUTH_REQUIRED       = 'AUTH_REQUIRED';
    public const ERR_AUTH_INVALID        = 'AUTH_INVALID';
    public const ERR_LICENSE_INVALID     = 'LICENSE_INVALID';
    public const ERR_LICENSE_EXPIRED     = 'LICENSE_EXPIRED';
    public const ERR_DEVICE_NOT_FOUND    = 'DEVICE_NOT_REGISTERED';
    public const ERR_MODEL_NOT_FOUND     = 'MODEL_NOT_FOUND';
    public const ERR_ROM_NOT_FOUND       = 'ROM_NOT_FOUND';
    public const ERR_ROM_NOT_SUPPORTED   = 'ROM_NOT_SUPPORTED';
    public const ERR_ROM_NOT_VALIDATED   = 'ROM_NOT_VALIDATED';
    public const ERR_PROFILE_NOT_FOUND   = 'PROFILE_NOT_FOUND';
    public const ERR_VERSION_NOT_SUPP    = 'VERSION_NOT_SUPPORTED';
    public const ERR_BACKUP_NOT_FOUND    = 'BACKUP_NOT_FOUND';
    public const ERR_RESTORE_NOT_ALLOWED = 'RESTORE_NOT_ALLOWED';
    public const ERR_RATE_LIMITED        = 'RATE_LIMITED';
    public const ERR_SERVER_ERROR        = 'SERVER_ERROR';
    public const ERR_VALIDATION_FAILED   = 'VALIDATION_FAILED';

    private static ?string $requestId = null;

    public static function getRequestId(): string
    {
        if (self::$requestId === null) {
            self::$requestId = 'req_' . bin2hex(random_bytes(8));
        }
        return self::$requestId;
    }

    public static function json(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    public static function success(mixed $data = [], int $statusCode = 200, array $extraMeta = []): void
    {
        $payload = [
            'success' => true,
            'data'    => $data,
            'meta'    => array_merge(['request_id' => self::getRequestId()], $extraMeta)
        ];
        self::json($payload, $statusCode);
    }

    public static function error(string $code, string $message, int $statusCode = 400, array $details = []): void
    {
        $errObj = [
            'code'    => $code,
            'message' => $message
        ];

        if (!empty($details)) {
            $errObj['details'] = $details;
        }

        $payload = [
            'success' => false,
            'error'   => $errObj,
            'meta'    => ['request_id' => self::getRequestId()]
        ];
        self::json($payload, $statusCode);
    }
}
