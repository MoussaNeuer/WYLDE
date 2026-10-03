<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Lang;
use Tests\T;

T::group('Langue');

T::it('get() remplace les paramètres de la clé', static function (): void {
    Lang::reset();
    Config::set('app.locale', 'fr');

    $line = Lang::get('validation.required', ['field' => 'Nom']);

    T::contains('Nom', $line);
});

T::it('field() retombe sur le FR quand la traduction EN est vide', static function (): void {
    Config::set('app.locale', 'en');
    Lang::reset();

    T::same('EN', Lang::field(['name' => 'FR', 'name_en' => 'EN'], 'name'));
    T::same('FR', Lang::field(['name' => 'FR', 'name_en' => '   '], 'name'));
    T::same('FR', Lang::field(['name' => 'FR'], 'name'));

    Config::set('app.locale', 'fr');
    Lang::reset();
});

T::it('transliterate() supprime les diacritiques', static function (): void {
    T::same('eac', Lang::transliterate('éàç'));
    T::same('', Lang::transliterate('   '));
});
