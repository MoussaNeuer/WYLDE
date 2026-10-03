<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Envoi des en-têtes de sécurité (cf. §13.5).
 *
 * - HTTPS obligatoire en production
 * - X-Content-Type-Options: nosniff
 * - Referrer-Policy restrictive
 * - Protection contre le clickjacking
 * - Permissions-Policy
 * - CSP déployée progressivement (report-only par défaut)
 */
final class SecurityHeaders
{
    public static function send(): void
    {
        if (headers_sent()) {
            return;
        }

        $isLocal = Config::get('app.env', 'production') !== 'production';

        if (!$isLocal) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        /** @var array<string, string> $headers */
        $headers = (array) Config::get('security.headers', []);

        foreach ($headers as $name => $value) {
            header($name . ': ' . $value, true);
        }

        self::sendCsp();
    }

    private static function sendCsp(): void
    {
        if ((bool) Config::get('security.csp.enabled', false) === false) {
            return;
        }

        /** @var array<int, string> $directives */
        $directives = (array) Config::get('security.csp.directives', []);

        if ($directives === []) {
            return;
        }

        $policy = implode('; ', $directives);

        if ((bool) Config::get('security.csp.report_only', true)) {
            header('Content-Security-Policy-Report-Only: ' . $policy, true);

            return;
        }

        header('Content-Security-Policy: ' . $policy, true);
    }
}
