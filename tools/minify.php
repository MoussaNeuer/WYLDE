<?php

declare(strict_types=1);

/**
 * Minification CSS/JS pour la mise en ligne.
 *
 * Lancez-le avant le déploiement :
 *     php tools/minify.php
 *
 * Chaque fichier public/assets/css/*.css et *.js (hors *.min.*) est
 * réduit : commentaires et espaces superflus retirés, dernière
 * newline préservée. Le résultat va dans un fichier .min. à côté
 * de l'original. `asset()` sert automatiquement la version .min.
 * en production.
 *
 * Bootstrap est déjà minifié par ses auteurs : le script l'ignore.
 */

$root = dirname(__DIR__);

$targets = [
    $root . '/public/assets/css' => 'css',
    $root . '/public/assets/js'  => 'js',
];

$totalBefore = 0;
$totalAfter  = 0;
$count       = 0;

foreach ($targets as $dir => $type) {
    if (!is_dir($dir)) {
        continue;
    }

    $files = glob($dir . '/*.' . $type) ?: [];

    foreach ($files as $file) {
        $base = basename($file);

        // Ne pas toucher aux .min. ni aux fichiers déjà minifiés.
        if (str_ends_with($base, '.min.' . $type)) {
            continue;
        }

        $source = file_get_contents($file);

        if ($source === false) {
            fwrite(STDERR, "Lecture impossible : {$file}\n");
            continue;
        }

        $minified = $type === 'css' ? minifyCss($source) : minifyJs($source);

        if ($minified === '') {
            fwrite(STDERR, "Résultat vide (fichier vide ?) : {$file}\n");
            continue;
        }

        $target = preg_replace('#\.' . preg_quote($type, '#') . '$#', '.min.' . $type, $file) ?? ($file . '.min');

        // Toujours écrire, même si le fichier est identique : cela
        // garantit que le déploiement a bien des .min. à jour.
        if (file_put_contents($target, $minified . "\n") === false) {
            fwrite(STDERR, "Écriture impossible : {$target}\n");
            continue;
        }

        $before    = strlen($source);
        $after     = strlen($minified);
        $saved     = $before > 0 ? round((1 - $after / $before) * 100) : 0;

        $totalBefore += $before;
        $totalAfter  += $after;
        $count++;

        printf(
            "  %-28s %7.1f Ko → %7.1f Ko  (-%d%%)\n",
            $base,
            $before / 1024,
            $after / 1024,
            $saved
        );
    }
}

if ($count === 0) {
    echo "Aucun fichier à minifier.\n";
    exit(0);
}

printf(
    "\n%d fichier(s) minifié(s) : %.1f Ko → %.1f Ko (-%d%% au total)\n",
    $count,
    $totalBefore / 1024,
    $totalAfter / 1024,
    $totalBefore > 0 ? round((1 - $totalAfter / $totalBefore) * 100) : 0
);

/**
 * Minification CSS basique : commentaires et espaces superflus.
 *
 * Pas de parseur complet — on ne casse pas les chaînes ni les url().
 * Les commentaires de licence en tête de fichier sont conservés
 * lorsque la première ligne commence par « /*! ».
 */
function minifyCss(string $css): string
{
    // Conserve les commentaires de licence /*! ... */.
    $css = preg_replace(
        '#(?<!/)\*/\s*/\*#',
        '*/ /*',
        $css
    ) ?? $css;

    // Retire les commentaires (sauf /*! ... */).
    $css = preg_replace('#/\*!(?:[^*]|\*(?!/))*\*/#', '/*!KEEP*/', $css) ?? $css;
    $css = preg_replace('#/\*(?:[^*]|\*(?!/))*\*/#s', '', $css) ?? $css;
    $css = str_replace('/*!KEEP*/', '/*!', $css) ?? $css;

    // Espaces réduits.
    $css = preg_replace('#\s+#', ' ', $css) ?? $css;
    $css = preg_replace('#\s*([{};:,])\s*#', '$1', $css) ?? $css;
    $css = preg_replace('#;\}#', '}', $css) ?? $css;

    return trim($css);
}

/**
 * Minification JS : commentaires et espaces superflus, en parcourant
 * la source caractère par caractère.
 *
 * Les chaînes ('…', "…"), les gabarits (`…`) et les littéraux regex
 * sont copiés à l'identique : rien de ce qui vit dans un littéral
 * n'est interprété comme un commentaire ni reformaté. Sans cela, un
 * double antislash-slash dans une URL ou une séquence étoile / barre
 * dans une expression régulière détruirait la source à tort.
 *
 * Les commentaires de licence « notification bang » sont conservés.
 */
function minifyJs(string $js): string
{
    // Mots-clés après lesquels « / … / » introduit une regex et non une division.
    $keywords = [
        'return', 'typeof', 'instanceof', 'in', 'of', 'new', 'delete',
        'void', 'throw', 'yield', 'await', 'case', 'else', 'do',
    ];

    $n    = strlen($js);
    $out  = '';
    $i    = 0;
    $prevSignificant = ''; // dernier caractère émis (hors espace)
    $lastWord        = ''; // identifiant/mot courant, pour les mots-clés
    $pendingSpace    = false;

    $isWord = static function (string $c): bool {
        return ($c !== '') && preg_match('/[A-Za-z0-9_$]/', $c) === 1;
    };

    $isRegexStart = static function (string $prev, string $word) use ($keywords): bool {
        if ($word !== '' && in_array($word, $keywords, true)) {
            return true;
        }
        if ($prev === '') {
            return true;
        }
        return preg_match('#[({[\];,=:!?&|+\-*%^~<>@]#', $prev) === 1;
    };

    while ($i < $n) {
        $c = $js[$i];

        // Commentaire de ligne : supprimé jusqu'à la fin de ligne.
        if ($c === '/' && $i + 1 < $n && $js[$i + 1] === '/') {
            $i += 2;

            while ($i < $n && $js[$i] !== "\n" && $js[$i] !== "\r") {
                $i++;
            }
            continue;
        }

        // Commentaire de bloc : supprimé, sauf licence « /*! … */ ».
        if ($c === '/' && $i + 1 < $n && $js[$i + 1] === '*') {
            $isLicense = $i + 2 < $n && $js[$i + 2] === '!';
            $start     = $i;

            $end = strpos($js, '*/', $i + 2);
            $end = ($end === false) ? $n : $end + 2;

            if ($isLicense) {
                $out .= substr($js, $start, $end - $start);
            }

            $i = $end;
            continue;
        }

        // Chaîne ou gabarit : recopié tel quel, échappement compris.
        if ($c === "'" || $c === '"' || $c === '`') {
            $quote = $c;
            $out  .= $c;
            $i++;
            $prevSignificant = $quote;
            $lastWord        = '';
            $pendingSpace    = false;

            while ($i < $n) {
                $sc = $js[$i];
                $out .= $sc;

                if ($sc === '\\') {
                    $i++;
                    if ($i < $n) {
                        $out .= $js[$i];
                    }
                    $i++;
                    continue;
                }

                if ($sc === $quote) {
                    $i++;
                    break;
                }

                if ($sc === "\n" && $quote !== '`') {
                    $i++; // chaîne non terminée : on s'arrête, source invalide.
                    break;
                }

                $i++;
            }
            continue;
        }

        // Expression régulière : recopiée telle quelle (classes et
        // drapeaux compris). Le « / » est une regex après un opérateur,
        // une ouverture ou un mot-clé ; sinon c'est une division.
        if ($c === '/' && $isRegexStart($prevSignificant, $lastWord)) {
            $out .= $c;
            $i++;
            $prevSignificant = '/';
            $lastWord        = '';
            $pendingSpace    = false;
            $inClass         = false;

            while ($i < $n) {
                $rc = $js[$i];
                $out .= $rc;

                if ($rc === '\\') {
                    $i++;
                    if ($i < $n) {
                        $out .= $js[$i];
                    }
                    $i++;
                    continue;
                }

                if ($rc === '[') {
                    $inClass = true;
                } elseif ($rc === ']' && $inClass) {
                    $inClass = false;
                } elseif ($rc === '/' && !$inClass) {
                    $i++;
                    break;
                } elseif ($rc === "\n") {
                    $i++;
                    break;
                }

                $i++;
            }

            // Drapeaux possibles après « / … / ».
            while ($i < $n && preg_match('/[A-Za-z]/', $js[$i]) === 1) {
                $out .= $js[$i];
                $i++;
            }
            continue;
        }

        // Espace significatif : retenu uniquement entre deux « mots ».
        if ($c === ' ' || $c === "\t" || $c === "\n" || $c === "\r") {
            $pendingSpace = true;
            $i++;
            continue;
        }

        // Caractère courant.
        if ($pendingSpace && $isWord($prevSignificant) && $isWord($c)) {
            $out .= ' ';
        }

        $pendingSpace = false;
        $out .= $c;

        if ($isWord($c)) {
            $lastWord .= $c;
        } else {
            $lastWord = '';
        }

        $prevSignificant = $c;
        $i++;
    }

    return trim($out);
}