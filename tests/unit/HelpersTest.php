<?php

declare(strict_types=1);

use Tests\T;

T::group('Helpers');

T::it('e() échappe le HTML et neutralise les types non scalaires', static function (): void {
    T::same('&lt;b&gt;x&lt;/b&gt;', e('<b>x</b>'));
    T::contains('&quot;', e('"'));
    T::contains('&apos;', e("'"));
    T::same('', e(null));
    T::same('', e(false));
    T::same('', e(['a']));
});

T::it('slugify() translittère et normalise', static function (): void {
    T::same('cafe-creme-deja-vu', slugify('Café Crème Déjà Vu'));
    T::same('t-shirt', slugify('  T-Shirt  '));
    T::same('', slugify('###'));
});

T::it('str_limit() tronque en respectant les caractères UTF-8', static function (): void {
    T::same('abc…', str_limit('abcdef', 3));
    T::same('ab', str_limit('ab', 5));
    T::same('', str_limit('', 5));
});

T::it('money_int() convertit un montant entier', static function (): void {
    T::same(1235, money_int('1234.9'));
    T::same(9000, money_int(9000));
    T::same(0, money_int('abc'));
});

T::it('money_raw() groupe les milliers', static function (): void {
    T::contains('000', money_raw(250000));
    T::same('0', money_raw('nope'));
});

T::it('route() remplit les paramètres et préfixe par url()', static function (): void {
    T::same(url('/product/t-shirt'), route('/product/{slug}', ['slug' => 't-shirt']));
});

/**
 * Execute un callback avec un $_SERVER simule, puis le restaure.
 *
 * Les tests partagent le processus : sans restauration, un SCRIPT_NAME
 * pose par un test fuiterait dans les suivants.
 *
 * @param array<string, string> $server
 */
function withServer(array $server, callable $callback): void
{
    $backup = $_SERVER;

    $_SERVER = array_merge([
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI'    => '/',
        'REMOTE_ADDR'    => '127.0.0.1',
    ], $server);

    try {
        $callback();
    } finally {
        $_SERVER = $backup;
    }
}

T::it('url() conserve le préfixe du dossier public', static function (): void {
    $base = rtrim((string) config('app.url'), '/');

    // Sans contexte HTTP (CLI), seule la configuration peut fournir la base.
    withServer([], static function () use ($base): void {
        T::same($base . '/', url('/'));
        T::same($base . '/shop', url('/shop'));
    });
});

T::it('url() déduit le préfixe du dossier public de SCRIPT_NAME', static function (): void {
    withServer([
        'HTTP_HOST'   => 'localhost',
        'SCRIPT_NAME' => '/WYLDE/public/index.php',
    ], static function (): void {
        T::same('http://localhost/WYLDE/public/', url('/'));
        T::same('http://localhost/WYLDE/public/shop', url('/shop'));
    });
});

T::it('url() suit l\'hôte quand public/ est la racine du DocumentRoot', static function (): void {
    withServer([
        'HTTP_HOST'   => '192.168.1.4',
        'SCRIPT_NAME' => '/index.php',
    ], static function (): void {
        T::same('http://192.168.1.4/', url('/'));
        T::same('http://192.168.1.4/product/t-shirt', url('/product/t-shirt'));
    });
});

T::it('url() retombe sur app.url si l\'hôte n\'est pas de confiance', static function (): void {
    withServer([
        'HTTP_HOST'   => 'attaquant.example',
        'SCRIPT_NAME' => '/index.php',
    ], static function (): void {
        T::same(rtrim((string) config('app.url'), '/') . '/', url('/'));
    });
});

T::it('url() retombe sur app.url quand le tableau $_SERVER est vide', static function (): void {
    withServer([
        'SCRIPT_NAME' => '',
    ], static function (): void {
        T::same(rtrim((string) config('app.url'), '/') . '/shop', url('/shop'));
    });
});

T::it('la livraison offerte est masquée quand le seuil vaut zéro', static function (): void {
    $progress = free_shipping_progress(120_000, 0);

    T::false($progress['enabled']);
    T::same(0, $progress['percent']);
    T::same('', $progress['threshold_text']);
});

T::it('la barre de livraison offerte mesure le reste à franchir', static function (): void {
    $progress = free_shipping_progress(20_000, 50_000);

    T::true($progress['enabled']);
    T::false($progress['reached']);
    T::same(50_000, $progress['threshold']);
    T::same(30_000, $progress['remaining']);
    T::same(40, $progress['percent']);
});

T::it('la livraison offerte est atteinte pile au seuil', static function (): void {
    $progress = free_shipping_progress(50_000, 50_000);

    T::true($progress['reached']);
    T::same(0, $progress['remaining']);
    T::same(100, $progress['percent']);
});

T::it('la barre plafonne à 100 au-delà du seuil', static function (): void {
    T::same(100, free_shipping_progress(500_000, 50_000)['percent']);
});

T::it('le seuil négatif est traité comme désactivé', static function (): void {
    T::false(free_shipping_progress(20_000, -1)['enabled']);
});

T::it('le message de rareté cite la taille et le stock restant', static function (): void {
    $low = scarcity_message(2, 'M');

    T::notNull($low);
    T::same('critical', $low['level']);
    T::true(str_contains($low['text'], '2'));
    T::true(str_contains($low['text'], 'M'));

    T::same('low', scarcity_message(4, 'L')['level']);
});

T::it('aucun message de rareté quand le stock est confortable ou épuisé', static function (): void {
    T::null(scarcity_message(40, 'M'));
    T::null(scarcity_message(0, 'M'));
});

T::it('la rareté sans taille réelle ne cite aucun nom de taille', static function (): void {
    $low = scarcity_message(2, null);
    $uni = scarcity_message(2, 'UNIQUE');

    T::notNull($low);
    T::notNull($uni);
    T::false(str_contains($low['text'], ':size'));
    T::false(str_contains($uni['text'], ':size'));
    T::false(str_contains($uni['text'], 'UNIQUE'));
});

T::it('size_label() remplace le marqueur UNIQUE par une taille lisible', static function (): void {
    T::same('M', size_label('M'));
    T::same(__('product.size_unique'), size_label('UNIQUE'));
    T::same(__('product.size_unique'), size_label(''));
    T::same(__('product.size_unique'), size_label(null));
});
