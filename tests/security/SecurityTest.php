<?php

declare(strict_types=1);

use App\Controllers\Api\MediaController;
use App\Core\HttpException;
use Tests\T;

T::group('Sécurité');

T::it('e() neutralise les vecteurs XSS courants', static function (): void {
    T::contains('&lt;script&gt;', e('<script>alert(1)</script>'));
    T::contains('&quot;', e('" onmouseover="alert(1)'));
    T::contains('&apos;', e("'"));
    T::contains('&lt;img', e('<img src=x onerror=alert(1)>'));
});

T::it('MediaController::sanitizePath() bloque la traversée de répertoire', static function (): void {
    $controller = new MediaController();
    $method     = new ReflectionMethod(MediaController::class, 'sanitizePath');
    $method->setAccessible(true);

    $sanitize = static fn (string $path): ?string => $method->invoke($controller, $path);

    // Interdits.
    T::null($sanitize('../../.env'));
    T::null($sanitize('..\\..\\web.config'));
    T::null($sanitize('products/../../.env'));
    T::null($sanitize('/etc/passwd'));
    T::null($sanitize('C:\\Windows\\win.ini'));
    T::null($sanitize("a\0b"));
    T::null($sanitize(''));
    T::null($sanitize('.'));

    // Autorises et normalises.
    T::same('products/2026/10/3-abc.jpg', $sanitize('products/2026/10/3-abc.jpg'));
    T::same('a/b/c.png', $sanitize('a//b/./c.png'));
    T::same('sub/img.webp', $sanitize('sub\\img.webp'));
});

T::it('les fabriques HttpException portent le bon statut', static function (): void {
    T::same(400, HttpException::badRequest()->statusCode());
    T::same(401, HttpException::unauthorized()->statusCode());
    T::same(403, HttpException::forbidden()->statusCode());
    T::same(404, HttpException::notFound()->statusCode());
    T::same(429, HttpException::tooManyRequests()->statusCode());
});

T::it('le durcissement .htaccess est présent (régression)', static function (): void {
    $root = (string) file_get_contents(BASE_PATH . '/.htaccess');

    // Traversée de répertoire et exécution PHP hors public/.
    T::contains('\\.\\.', $root);
    T::contains('[F,L]', $root);
    T::contains('FilesMatch "\\.php$"', $root);

    T::true(is_file(BASE_PATH . '/public/.htaccess'));
    T::true(is_file(BASE_PATH . '/storage/.htaccess'));
});
