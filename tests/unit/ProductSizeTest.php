<?php

declare(strict_types=1);

use App\Models\ProductSize;
use Tests\T;

/**
 * Catalogue des tailles.
 *
 * normalize() est la porte d'entrée du catalogue : c'est elle qui
 * décide que « xl » et « XL » sont la même taille, et qui borne la
 * longueur à celle de product_variants.size. Le reste du modèle touche
 * la base et n'est pas couvert ici.
 */
T::group('ProductSize');

T::it('normalize() met en majuscules et coupe les espaces', static function (): void {
    T::same('XL', ProductSize::normalize('xl'));
    T::same('S', ProductSize::normalize('  s  '));
    T::same('L', ProductSize::normalize("l\t"));
});

T::it('normalize() réduit les espaces internes', static function (): void {
    T::same('38 1/2', ProductSize::normalize('38   1/2'));
    T::same('TAILLE UNIQUE', ProductSize::normalize('taille   unique'));
});

T::it('normalize() conserve les tailles non alphabétiques', static function (): void {
    T::same('38', ProductSize::normalize('38'));
    T::same('2XL', ProductSize::normalize('2xl'));
    T::same('10.5', ProductSize::normalize('10.5'));
});

T::it('normalize() renvoie une chaîne vide pour une saisie vide', static function (): void {
    T::same('', ProductSize::normalize(''));
    T::same('', ProductSize::normalize('   '));
});

T::it('normalize() borne la longueur à celle de la colonne', static function (): void {
    $long = ProductSize::normalize(str_repeat('X', 60));

    T::same(ProductSize::MAX_LENGTH, mb_strlen($long));
    T::same(str_repeat('X', ProductSize::MAX_LENGTH), $long);
});

T::it('normalize() est idempotent', static function (): void {
    $once = ProductSize::normalize('  xxl ');

    T::same($once, ProductSize::normalize($once));
});
