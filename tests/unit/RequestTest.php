<?php

declare(strict_types=1);

use App\Core\Request;
use Tests\T;

/**
 * Capture une requête à partir de globals simulés.
 *
 * @param array<string, mixed>  $get
 * @param array<string, mixed>  $post
 * @param array<string, string> $server
 */
function makeRequest(array $get = [], array $post = [], array $server = []): Request
{
    $_GET    = $get;
    $_POST   = $post;
    $_FILES  = [];
    $_SERVER = array_merge([
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI'    => '/',
        'SCRIPT_NAME'    => '/index.php',
        'HTTP_HOST'      => 'localhost',
        'REMOTE_ADDR'    => '127.0.0.1',
        'CONTENT_TYPE'   => '',
    ], $server);

    return Request::capture();
}

T::group('Request');

T::it('path() retire le préfixe du dossier public', static function (): void {
    T::same('/shop', makeRequest([], [], [
        'REQUEST_URI' => '/WYLDE/public/shop?x=1',
        'SCRIPT_NAME' => '/WYLDE/public/index.php',
    ])->path());

    T::same('/', makeRequest([], [], [
        'REQUEST_URI' => '/WYLDE/public/',
        'SCRIPT_NAME' => '/WYLDE/public/index.php',
    ])->path());

    T::same('/', makeRequest([], [], [
        'REQUEST_URI' => '/WYLDE/public',
        'SCRIPT_NAME' => '/WYLDE/public/index.php',
    ])->path());

    T::same('/account/login', makeRequest([], [], [
        'REQUEST_URI' => '/WYLDE/public/account/login/',
        'SCRIPT_NAME' => '/WYLDE/public/index.php',
    ])->path());
});

T::it('method() applique le _method de formulaire', static function (): void {
    T::same('PUT', makeRequest([], ['_method' => 'put'], ['REQUEST_METHOD' => 'POST'])->method());
    T::same('DELETE', makeRequest([], ['_method' => 'DELETE'], ['REQUEST_METHOD' => 'POST'])->method());
    T::same('GET', makeRequest([], [], ['REQUEST_METHOD' => 'GET'])->method());
});

T::it('str()/int()/bool()/array() lisent les entrées', static function (): void {
    $request = makeRequest(
        ['q' => '  hello  '],
        ['n' => '42', 'flag' => 'on', 'off' => '0', 'list' => ['a', 'b']]
    );

    T::same('hello', $request->str('q'));
    T::same(42, $request->int('n'));
    T::true($request->bool('flag'));
    T::false($request->bool('off'));
    T::same(['a', 'b'], $request->array('list'));
    T::same('def', $request->str('absent', 'def'));
});

T::it('input() fait primer le corps sur la query string', static function (): void {
    T::same('body', makeRequest(['k' => 'query'], ['k' => 'body'])->input('k'));
    T::true(makeRequest(['k' => 'v'])->has('k'));
    T::false(makeRequest()->has('k'));
});

T::it('wantsJson() détecte Accept et X-Requested-With', static function (): void {
    T::true(makeRequest([], [], ['HTTP_ACCEPT' => 'application/json'])->wantsJson());
    T::true(makeRequest([], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'])->wantsJson());
    T::false(makeRequest([], [], ['HTTP_ACCEPT' => 'text/html'])->wantsJson());
});

T::it('header() normalise le nom d’en-tête', static function (): void {
    T::same('test-agent', makeRequest([], [], ['HTTP_USER_AGENT' => 'test-agent'])->header('User-Agent'));
    T::same('fallback', makeRequest()->header('X-Absent', 'fallback'));
});

T::it('ipHash() produit un HMAC SHA-256 de 64 hexadécimaux', static function (): void {
    $hash = makeRequest([], [], ['REMOTE_ADDR' => '203.0.113.9'])->ipHash();

    T::notNull($hash);
    T::matches('/^[0-9a-f]{64}$/', (string) $hash);
    T::same(hash_hmac('sha256', '203.0.113.9', (string) config('app.key', '')), $hash);
});
