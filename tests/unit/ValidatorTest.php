<?php

declare(strict_types=1);

use App\Validators\Validator;
use Tests\T;

/**
 * Expose les règles protégées du validateur pour les tests.
 */
final class TestValidator extends Validator
{
    public function ruleRequired(string $field): void
    {
        $this->required($field, 'Champ');
    }

    public function ruleEmail(string $field): void
    {
        $this->email($field, 'E-mail');
    }

    public function ruleMin(string $field, int $min): void
    {
        $this->minLength($field, 'Texte', $min);
    }

    public function ruleMax(string $field, int $max): void
    {
        $this->maxLength($field, 'Texte', $max);
    }

    public function ruleInteger(string $field): void
    {
        $this->integer($field, 'Nombre');
    }

    public function rulePositive(string $field): void
    {
        $this->positive($field, 'Nombre');
    }

    /** @param array<int, string> $allowed */
    public function ruleInList(string $field, array $allowed): void
    {
        $this->inList($field, 'Statut', $allowed);
    }

    public function ruleConfirmed(string $field, string $confirmation): void
    {
        $this->confirmed($field, $confirmation);
    }

    public function orValue(string $field, string $default): string
    {
        return $this->stringOr($field, $default);
    }

    public function nullableValue(string $field): ?string
    {
        return $this->nullable($field);
    }
}

T::group('Validateur');

T::it('required() rejette une valeur vide', static function (): void {
    $v = new TestValidator(['name' => '']);
    $v->ruleRequired('name');

    T::true($v->fails());
    T::true(isset($v->errors()['name']));
});

T::it('required() accepte une valeur présente', static function (): void {
    $v = new TestValidator(['name' => 'Fatou']);
    $v->ruleRequired('name');

    T::true($v->passes());
});

T::it('email() valide le format', static function (): void {
    $bad = new TestValidator(['email' => 'pas-un-email']);
    $bad->ruleEmail('email');
    T::true($bad->fails());

    $good = new TestValidator(['email' => 'ok@example.sn']);
    $good->ruleEmail('email');
    T::true($good->passes());
});

T::it('inList() ignore vide mais rejette hors liste', static function (): void {
    $empty = new TestValidator(['status' => '']);
    $empty->ruleInList('status', ['active', 'inactive']);
    T::true($empty->passes());

    $valid = new TestValidator(['status' => 'active']);
    $valid->ruleInList('status', ['active', 'inactive']);
    T::true($valid->passes());

    $invalid = new TestValidator(['status' => 'autre']);
    $invalid->ruleInList('status', ['active', 'inactive']);
    T::true($invalid->fails());
});

T::it('integer() et positive() contrôlent les nombres', static function (): void {
    $int = new TestValidator(['n' => '42']);
    $int->ruleInteger('n');
    T::true($int->passes());

    $notInt = new TestValidator(['n' => '4.2']);
    $notInt->ruleInteger('n');
    T::true($notInt->fails());

    $zero = new TestValidator(['n' => '0']);
    $zero->rulePositive('n');
    T::true($zero->fails());

    $ok = new TestValidator(['n' => '5']);
    $ok->rulePositive('n');
    T::true($ok->passes());
});

T::it('minLength()/maxLength() bornent la longueur', static function (): void {
    $short = new TestValidator(['t' => 'ab']);
    $short->ruleMin('t', 3);
    T::true($short->fails());

    $long = new TestValidator(['t' => 'abcd']);
    $long->ruleMax('t', 3);
    T::true($long->fails());

    $ok = new TestValidator(['t' => 'abc']);
    $ok->ruleMin('t', 3);
    $ok->ruleMax('t', 3);
    T::true($ok->passes());
});

T::it('stringOr() fournit un repli ENUM sur chaîne vide (régression)', static function (): void {
    T::same('active', (new TestValidator(['status' => '']))->orValue('status', 'active'));
    T::same('active', (new TestValidator([]))->orValue('status', 'active'));
    T::same('inactive', (new TestValidator(['status' => 'inactive']))->orValue('status', 'inactive'));
});

T::it('nullable() transforme la chaîne vide en null', static function (): void {
    T::null((new TestValidator(['x' => '']))->nullableValue('x'));
    T::same('v', (new TestValidator(['x' => 'v']))->nullableValue('x'));
});

T::it('confirmed() détecte une confirmation différente', static function (): void {
    $v = new TestValidator(['password' => 'secret', 'password_confirmation' => 'autre']);
    $v->ruleConfirmed('password', 'password_confirmation');

    T::true($v->fails());
    T::true(isset($v->errors()['password_confirmation']));
});
