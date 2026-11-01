<?php

declare(strict_types=1);

namespace App\Support;

/**
 * JSON API response helper.
 *
 * All responses follow the standard envelope:
 * {
 *   "success": bool,
 *   "data": mixed,
 *   "error": {"code": string, "message": string} | null,
 *   "meta": {"request_id": string, ...}
 * }
 *
 * @package App\Support
 */
class Response
{
    /**
     * Send a successful JSON response.
     *
     * @param mixed $data     The response payload.
     * @param int   $code     HTTP status code (default 200).
     * @param array $meta     Additional meta fields to include.
     * @return never
     */
    public static function success(mixed $data = null, int $code = 200, array $meta = []): void
    {
        global $requestId;

        $meta['request_id'] = $requestId ?? uniqid('req_');

        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'success' => true,
            'data'    => $data,
            'error'   => null,
            'meta'    => $meta,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        exit();
    }

    /**
     * Send an error JSON response.
     *
     * @param string $errorCode Error code identifier (e.g. AUTH_REQUIRED, LICENSE_INVALID).
     * @param string $message   Human-readable error message.
     * @param int    $httpCode  HTTP status code (default 400).
     * @param array  $meta      Additional meta fields to include.
     * @return never
     */
    public static function error(string $errorCode, string $message, int $httpCode = 400, array $meta = []): void
    {
        global $requestId;

        $meta['request_id'] = $requestId ?? uniqid('req_');

        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'success' => false,
            'data'    => null,
            'error'   => [
                'code'    => $errorCode,
                'message' => $message,
            ],
            'meta'    => $meta,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        exit();
    }

    /**
     * Send a paginated JSON response.
     *
     * @param array $data  The array of items for the current page.
     * @param int   $total Total number of items across all pages.
     * @param int   $page  Current page number (1-based).
     * @param int   $limit Number of items per page.
     * @param array $meta  Additional meta fields to include.
     * @return never
     */
    public static function paginated(array $data, int $total, int $page, int $limit, array $meta = []): void
    {
        global $requestId;

        $meta['request_id'] = $requestId ?? uniqid('req_');
        $meta['pagination'] = [
            'total'        => $total,
            'page'         => $page,
            'limit'        => $limit,
            'total_pages'  => $limit > 0 ? (int) ceil($total / $limit) : 0,
            'has_next'     => ($page * $limit) < $total,
            'has_previous' => $page > 1,
        ];

        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'success' => true,
            'data'    => $data,
            'error'   => null,
            'meta'    => $meta,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        exit();
    }
}
