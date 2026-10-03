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

T::it('url() conserve le préfixe du dossier public', static function (): void {
    $base = rtrim((string) config('app.url'), '/');

    T::same($base . '/', url('/'));
    T::same($base . '/shop', url('/shop'));
});
