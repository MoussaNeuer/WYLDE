<?php

declare(strict_types=1);

/**
 * Création sécurisée du compte administrateur initial.
 *
 * Lancement (PowerShell, dans la racine du projet) :
 *   C:\wamp64\bin\php\php8.4.20\php.exe database\create-admin.php
 *
 * Le mot de passe est saisi masqué, n'est jamais affiché et n'est stocké
 * que sous forme de hash. Rien n'est écrit dans le dépôt (§22).
 *
 * Aucun email n'étant envoyé en V1, il n'existe pas de procédure de
 * « mot de passe oublié » : ce script sert aussi à réinitialiser un
 * mot de passe administrateur égaré.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ce script s'exécute uniquement en ligne de commande.\n");
}

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Models\User;

define('BASE_PATH', dirname(__DIR__));

// Composer est optionnel : le projet n'a aucune dépendance externe,
// un chargeur PSR-4 suffit (même logique que public/index.php).
if (is_file(BASE_PATH . '/vendor/autoload.php')) {
    require BASE_PATH . '/vendor/autoload.php';
} else {
    require BASE_PATH . '/app/Core/Autoloader.php';
    App\Core\Autoloader::register();
    App\Core\Autoloader::loadHelpers();
}

Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH . '/config');
date_default_timezone_set((string) Config::get('app.timezone', 'UTC'));

/**
 * Options en ligne de commande (usage non interactif) :
 *   php database/create-admin.php --name="Admin" --email=a@b.sn --password="Secret123"
 * Sans option, les valeurs sont demandées au clavier.
 *
 * @return array<string, string>
 */
function cliOptions(): array
{
    $options = [];

    // $argv n'existe qu'à la portée globale.
    $arguments = $GLOBALS['argv'] ?? [];

    foreach (array_slice((array) $arguments, 1) as $argument) {
        if (!str_starts_with($argument, '--')) {
            continue;
        }

        $argument = substr($argument, 2);

        if (str_contains($argument, '=')) {
            [$key, $value] = explode('=', $argument, 2);
            $options[$key] = $value;
        } else {
            $options[$argument] = '1';
        }
    }

    return $options;
}

/** Valeur d'option, ou saisie clavier. */
function askOption(array $options, string $key, string $prompt, bool $hidden = false): string
{
    if (isset($options[$key]) && $options[$key] !== '') {
        fwrite(STDOUT, $prompt . $options[$key] . PHP_EOL);

        return $options[$key];
    }

    return $hidden ? askHidden($prompt) : ask($prompt);
}

/** Lit une ligne depuis stdin. */
function ask(string $prompt): string
{
    fwrite(STDOUT, $prompt);
    $line = fgets(STDIN);

    return trim($line === false ? '' : $line);
}

/** Lit une ligne sans écho. */
function askHidden(string $prompt): string
{
    fwrite(STDOUT, $prompt);

    if (DIRECTORY_SEPARATOR === '\\') {
        // Masquage sous Windows via PowerShell non disponible : on désactive l'écho.
        @shell_exec('stty -echo 2>/dev/null');
        $line = fgets(STDIN);
        @shell_exec('stty echo 2>/dev/null');
        fwrite(STDOUT, PHP_EOL);

        return trim($line === false ? '' : $line);
    }

    shell_exec('stty -echo');
    $line = fgets(STDIN);
    shell_exec('stty echo');
    fwrite(STDOUT, PHP_EOL);

    return trim($line === false ? '' : $line);
}

function fail(string $message): never
{
    fwrite(STDERR, "\n[ERREUR] " . $message . "\n");
    exit(1);
}

fwrite(STDOUT, <<<TXT

  ╔══════════════════════════════════════════╗
  ║           WYLDE · Administration         ║
  ╚══════════════════════════════════════════╝

  Création ou réinitialisation d'un compte administrateur.

TXT);

// ── Connexion à la base ────────────────────────────────────────────────
try {
    Database::connection();
} catch (Throwable $e) {
    fail('Connexion à la base impossible : ' . $e->getMessage());
}

fwrite(STDOUT, 'Base : ' . Config::get('database.connections.mysql.database') . PHP_EOL . PHP_EOL);

// ── Collecte ───────────────────────────────────────────────────────────
$options = cliOptions();

$name = askOption($options, 'name', 'Nom complet           : ');

if ($name === '' || mb_strlen($name) > 120) {
    fail('Le nom est obligatoire (120 caractères maximum).');
}

$email = mb_strtolower(askOption($options, 'email', 'Adresse e-mail       : '));

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
    fail('Adresse e-mail invalide.');
}

$password = askOption($options, 'password', 'Mot de passe         : ', true);
$confirm  = isset($options['password']) ? $password : askOption([], 'confirm', 'Confirmation         : ', true);

$minLength = (int) Config::get('security.password.min_length', 8);

if (mb_strlen($password) < $minLength) {
    fail("Le mot de passe doit contenir au moins {$minLength} caractères.");
}

if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
    fail('Le mot de passe doit contenir au moins une lettre et un chiffre.');
}

if ($password !== $confirm) {
    fail('La confirmation ne correspond pas.');
}

$existing = User::findByEmail($email);

// ── Écriture ───────────────────────────────────────────────────────────
try {
    Database::transaction(static function () use ($name, $email, $password, $existing): void {
        if ($existing !== null) {
            $existing->setAttribute('name', $name);
            $existing->setAttribute('role', User::ROLE_ADMIN);
            $existing->setAttribute('status', User::STATUS_ACTIVE);
            $existing->setPassword($password);
            $existing->save();

            return;
        }

        $user = new User([
            'name'          => $name,
            'email'         => $email,
            'role'          => User::ROLE_ADMIN,
            'status'        => User::STATUS_ACTIVE,
        ]);
        $user->setPassword($password);
        $user->save();
    });
} catch (Throwable $e) {
    fail('Écriture en base impossible : ' . $e->getMessage());
}

$action = $existing === null ? 'créé' : 'réinitialisé';

fwrite(STDOUT, <<<TXT

  [OK] Compte administrateur {$action} : {$email}

  Connexion : /admin/login

  Le mot de passe n'a pas été affiché et n'est stocké que sous forme
  de hash. Conservez-le en lieu sûr.

TXT);

exit(0);
