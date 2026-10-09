<?php

declare(strict_types=1);

use Tests\T;

/**
 * Panier — logique de mise à jour des quantités.
 *
 * Deux bugs ont motivé ces tests :
 *
 *  1. le formulaire du panier envoie quantity[variant_id] (une quantité
 *     par ligne), alors que le contrôleur lisait un scalaire quantity :
 *     « Mettre à jour » ne touchait donc aucune ligne ;
 *  2. une quantité à 0 doit retirer SA ligne, sans vider les autres —
 *     c'est le cas vérifié en navigation manuelle avec deux articles.
 *
 * cart_update_plan() est pure (aucune base de données), ce qui permet de
 * verrouiller ces règles sans créer de données réelles.
 */

T::group('Panier — mise à jour des quantités');

T::it('une quantité à 0 ne retire que sa propre ligne', static function (): void {
    // Deux articles au panier : le variant 39 à 5 unités, le 40 à 3.
    $plan = cart_update_plan(
        ['39' => '0', '40' => '2'],
        [39 => 5, 40 => 3]
    );

    // Le 39 est retiré...
    T::same([39], $plan['remove']);

    // ...et le 40 est mis à jour, pas retiré : un panier à deux articles
    // ne doit pas se vider quand on met l'un d'eux à zéro.
    T::same([40 => 2], $plan['set']);
    T::same([], $plan['capped']);
});

T::it('plafonne au stock et ignore une variante absente du panier', static function (): void {
    $plan = cart_update_plan(
        // 99 demandés pour le 39 (stock 5), 3 pour le 40 (stock 3), et le
        // variant 999 qui n'est pas au panier.
        ['39' => '99', '40' => '3', '999' => '4'],
        [39 => 5, 40 => 3]
    );

    // Le 39 est ramené au stock, et le plafonnement est signalé pour que le
    // contrôleur prévienne le visiteur.
    T::same([39 => 5, 40 => 3], $plan['set']);
    T::same([39 => 5], $plan['capped']);

    // Le variant 999 est ignoré : un champ quantity[] forgé ne doit pas
    // créer de ligne ni modifier un autre panier.
    T::same([], $plan['remove']);
    T::false(array_key_exists(999, $plan['set']));
    T::false(array_key_exists(999, $plan['capped']));
});