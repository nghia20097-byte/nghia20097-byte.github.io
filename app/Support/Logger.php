<?php

declare(strict_types=1);

namespace App\Support;

/**
 * File-based logger for application and API events.
 *
 * Writes to:
 *   storage/logs/app-YYYY-MM-DD.log  (info, warning, error)
 *   storage/logs/api-YYYY-MM-DD.log  (API request logging)
 *
 * Log format: [YYYY-MM-DD HH:MM:SS] LEVEL: message {context_json}
 *
 * @package App\Support
 */
class Logger
{
    /**
     * Base path for log storage. Resolved relative to the project root.
     *
     * @var string
     */
    private static string $logPath = '';

    /**
     * Get the base log directory path, creating it if necessary.
     *
     * @return string Absolute path to the logs directory.
     */
    private static function getLogPath(): string
    {
        if (self::$logPath === '') {
            self::$logPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
        }

        if (!is_dir(self::$logPath)) {
            mkdir(self::$logPath, 0755, true);
        }

        return self::$logPath;
    }

    /**
     * Write a log entry to the specified log file.
     *
     * @param string $level   Log level (INFO, WARNING, ERROR, API).
     * @param string $message The log message.
     * @param array  $context Additional context data to encode as JSON.
     * @param string $prefix  Log file prefix ('app' or 'api').
     * @return void
     */
    private static function write(string $level, string $message, array $context, string $prefix = 'app'): void
    {
        $logDir = self::getLogPath();
        $date = date('Y-m-d');
        $timestamp = date('Y-m-d H:i:s');
        $file = $logDir . DIRECTORY_SEPARATOR . "{$prefix}-{$date}.log";

        $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
        $line = "[{$timestamp}] {$level}: {$message}{$contextStr}" . PHP_EOL;

        file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Log an informational message.
     *
     * @param string $message The log message.
     * @param array  $context Additional context data.
     * @return void
     */
    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    /**
     * Log a warning message.
     *
     * @param string $message The log message.
     * @param array  $context Additional context data.
     * @return void
     */
    public static function warning(string $message, array $context = []): void
    {
        self::write('WARNING', $message, $context);
    }

    /**
     * Log an error message.
     *
     * @param string $message The log message.
     * @param array  $context Additional context data.
     * @return void
     */
    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    /**
     * Log an API request/response event.
     *
     * @param string $method     HTTP method (GET, POST, etc.).
     * @param string $endpoint   The API endpoint path.
     * @param int    $statusCode The HTTP response status code.
     * @param float  $time       Request processing time in seconds.
     * @param array  $context    Additional context data.
     * @return void
     */
    public static function api(string $method, string $endpoint, int $statusCode, float $time, array $context = []): void
    {
        $context['method']      = $method;
        $context['endpoint']    = $endpoint;
        $context['status_code'] = $statusCode;
        $context['time_ms']     = round($time * 1000, 2);

        $message = "{$method} {$endpoint} -> {$statusCode} ({$context['time_ms']}ms)";

        self::write('API', $message, $context, 'api');
    }
}
