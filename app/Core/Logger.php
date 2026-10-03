<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Journalisation sur fichier avec rotation (cf. §13.5 : les détails
 * techniques ne quittent jamais le serveur).
 */
final class Logger
{
    private const LEVELS = [
        'debug'     => 100,
        'info'      => 200,
        'warning'   => 300,
        'error'     => 400,
        'critical'  => 500,
    ];

    private const MAX_BYTES = 2 * 1024 * 1024;

    public static function debug(string $message): void
    {
        self::log('debug', $message);
    }

    public static function info(string $message): void
    {
        self::log('info', $message);
    }

    public static function warning(string $message): void
    {
        self::log('warning', $message);
    }

    public static function error(string $message): void
    {
        self::log('error', $message);
    }

    public static function critical(string $message): void
    {
        self::log('critical', $message);
    }

    public static function log(string $level, string $message, array $context = []): void
    {
        $level = strtolower($level);

        if (!isset(self::LEVELS[$level])) {
            $level = 'info';
        }

        // Hors debug, on ignore debug/info pour ne pas saturer les logs.
        if (in_array($level, ['debug', 'info'], true) && !Config::isDebug()) {
            return;
        }

        $line = sprintf(
            "[%s] %s: %s%s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            self::sanitize($message),
            $context === [] ? '' : ' ' . self::encode($context)
        );

        $dir = rtrim((string) Config::get('app.paths.logs', ''), '/\\');

        if ($dir === '' || !is_dir($dir)) {
            return;
        }

        $file = $dir . DIRECTORY_SEPARATOR . 'app-' . date('Y-m-d') . '.log';

        self::rotateIfNeeded($file);

        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    /** Journalise une exception avec sa trace complète. */
    public static function exception(\Throwable $e, string $context = ''): void
    {
        self::log('error', trim($context . ' | ' . $e::class . ': ' . $e->getMessage()), [
            'file'  => $e->getFile() . ':' . $e->getLine(),
            'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 12),
        ]);
    }

    private static function rotateIfNeeded(string $file): void
    {
        if (!is_file($file) || filesize($file) < self::MAX_BYTES) {
            return;
        }

        @rename($file, $file . '.' . date('Ymd-His'));
    }

    private static function sanitize(string $message): string
    {
        $message = str_replace(["\r", "\n"], ' ', $message);

        // Neutralise une éventuelle injection de logs.
        return trim(str_replace(["\033", "\x1b"], '', $message));
    }

    /** @param array<string, mixed> $context */
    private static function encode(array $context): string
    {
        $json = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $json === false ? '{}' : $json;
    }
}
