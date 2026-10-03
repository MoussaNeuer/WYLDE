<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

/**
 * Moteur de vue : templates PHP en resources/views, layout, sections,
 * composants et données partagées.
 *
 * Aucune donnée n'est interpolée sans échappement : les templates
 * utilisent e() (cf. §13.3 — XSS).
 */
final class View
{
    /** @var array<string, mixed> */
    private static array $shared = [];

    /** @var array<string, string> */
    private static array $sections = [];

    /** @var array<int, string> */
    private static array $sectionStack = [];

    private static ?string $currentLayout = null;

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    /** @param array<string, mixed> $data */
    public static function shareMany(array $data): void
    {
        self::$shared = array_merge(self::$shared, $data);
    }

    public static function getShared(string $key, mixed $default = null): mixed
    {
        return self::$shared[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public static function shared(): array
    {
        return self::$shared;
    }

    /**
     * Rend un template puis, si fourni, l'enveloppe dans un layout.
     *
     * @param array<string, mixed> $data
     */
    public static function render(string $template, array $data = [], ?string $layout = null): string
    {
        $content = self::capture($template, $data);

        if ($layout === null) {
            return $content;
        }

        return self::capture($layout, array_merge($data, ['content' => $content]));
    }

    /** @param array<string, mixed> $data */
    private static function capture(string $template, array $data): string
    {
        $file = self::resolve($template);

        if (!is_file($file)) {
            throw new RuntimeException("Template introuvable : {$template} ({$file})");
        }

        $scope = array_merge(self::$shared, $data);

        $previousLayout              = self::$currentLayout;
        self::$currentLayout         = $template;

        try {
            self::includeTemplate($file, $scope);
        } finally {
            self::$currentLayout = $previousLayout;
        }

        return (string) ob_get_clean();
    }

    /**
     * Inclusion statique : isole les variables locales du template appelant.
     *
     * @param array<string, mixed> $scope
     */
    private static function includeTemplate(string $file, array $scope): void
    {
        extract($scope, EXTR_SKIP);

        ob_start();

        try {
            include $file;
        } catch (Throwable $e) {
            ob_end_clean();

            throw $e;
        }
    }

    private static function resolve(string $template): string
    {
        $base = rtrim((string) Config::get('app.paths.views', ''), '/\\');

        return $base . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $template) . '.php';
    }

    // ── Sections (title, meta, scripts) ──────────────────────

    public static function start(string $name): void
    {
        self::$sectionStack[] = $name;
        ob_start();
    }

    public static function stop(): void
    {
        $name = array_pop(self::$sectionStack);

        if ($name === null) {
            throw new RuntimeException('View::stop() appelé sans View::start().');
        }

        $content = (string) ob_get_clean();

        // Un section incluse dans une autre l'emporte si elle est plus longue.
        if (!isset(self::$sections[$name]) || strlen($content) > strlen(self::$sections[$name])) {
            self::$sections[$name] = $content;
        }
    }

    public static function section(string $name, string $default = ''): string
    {
        return self::$sections[$name] ?? $default;
    }

    public static function hasSection(string $name): bool
    {
        return isset(self::$sections[$name]) && trim(self::$sections[$name]) !== '';
    }

    public static function clearSections(): void
    {
        self::$sections = [];
    }

    // ── Composants ───────────────────────────────────────────

    /**
     * Rend un composant de resources/views/components avec ses données.
     *
     * @param array<string, mixed> $data
     */
    public static function component(string $name, array $data = []): void
    {
        echo self::capture('components/' . $name, $data);
    }

    /** Inclut un autre template depuis l'intérieur d'un template. */
    public static function include(string $template, array $data = []): void
    {
        echo self::capture($template, $data);
    }

    /** Template courant (utile pour lier la navigation active). */
    public static function current(): ?string
    {
        return self::$currentLayout;
    }
}
