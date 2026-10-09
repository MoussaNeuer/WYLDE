<?php

declare(strict_types=1);

return [
    'name'     => env('APP_NAME', 'WYLDE'),

    // Clé applicative (HMAC des empreintes IP dans audit_logs et
    // Request::ipHash). Sans elle, ip_hash serait calculé avec une clé
    // vide, donc forgeable par quiconque connaît l'IP.
    'key'      => env('APP_KEY', ''),

    'env'      => env('APP_ENV', 'production'),
    'debug'    => (bool) env('APP_DEBUG', false),
    'url'      => rtrim((string) env('APP_URL', 'http://localhost'), '/'),

    // URL de base derivée de la requête courante plutôt que de APP_URL :
    // indispensable quand l'application est servie à la racine d'un
    // DocumentRoot (test sur l'IP du réseau local) ou derrière un reverse
    // proxy. Sans cela, tous les liens pointent vers le hôte configuré
    // dans APP_URL et la page est sans CSS ni JS sur les autres hôtes.
    'url_from_request' => env('APP_URL_FROM_REQUEST', false),

    // Hôtes acceptés quand url_from_request est actif. Liste vide = tous
    // acceptés (pratique en développement). En production, la renseigner
    // empêche d'empoisonner les liens générés via l'en-tête Host.
    'trusted_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('APP_TRUSTED_HOSTS', ''))
    ))),

    'timezone' => env('APP_TIMEZONE', 'Africa/Dakar'),
    'locale'   => env('APP_LOCALE', 'fr'),
    'fallback_locale' => 'fr',

    // Locales disponibles (bascule FR/EN par cookie + session, URLs identiques).
    'available_locales' => ['fr', 'en'],
    'locale_cookie'    => 'wylde_locale',
    'locale_session_key' => 'locale',

    'paths' => [
        'root'     => dirname(__DIR__),
        'app'      => dirname(__DIR__) . '/app',
        'views'    => dirname(__DIR__) . '/resources/views',
        'lang'     => dirname(__DIR__) . '/resources/lang',
        'public'   => dirname(__DIR__) . '/public',
        'uploads'  => dirname(__DIR__) . '/storage/uploads',
        'logs'     => dirname(__DIR__) . '/storage/logs',
        'cache'    => dirname(__DIR__) . '/storage/cache',
    ],

    // Montants : FCFA, DECIMAL(12,0), jamais de centimes.
    'currency' => [
        'code'      => env('CURRENCY', 'XOF'),
        'symbol'    => 'FCFA',
        'position'  => env('CURRENCY_POSITION', 'after'),
        'decimals'  => (int) env('CURRENCY_DECIMALS', 0),
        'separator' => env('CURRENCY_SEPARATOR', "\u{202F}"),
    ],

    'stock' => [
        'low_threshold' => (int) env('STOCK_LOW_THRESHOLD', 5),
    ],

// Médias produits : formats acceptés et taille maximale.
      // Les fichiers sont écrits dans storage/uploads, hors document root,
      // et servis par /media/{chemin} (cf. §13.4).
      'uploads' => [
          'max_size'   => (int) env('UPLOAD_MAX_SIZE', 5 * 1024 * 1024),
          'max_width'  => (int) env('UPLOAD_MAX_WIDTH', 3000),
          'extensions' => ['jpg', 'jpeg', 'png', 'webp', 'avif'],
          'folder'     => 'products',
      ],

      // Variantes d'image générées à l'upload (WebP).
      // largeurs : les suffixes -400/-800/-1600 produits à côté de l'original.
      // quality   : qualité WebP (82 est un bon compromis poids/rendu).
      // webp      : false pour forcer le JPEG si GD n'a pas le support WebP.
      'media' => [
          'widths' => [400, 800, 1600],
          'quality' => (int) env('MEDIA_QUALITY', 82),
          'webp'    => (bool) env('MEDIA_WEBP', true),
      ],

    // Paiement. Aucune vérification automatique :
    // - cod  : paiement à la livraison, l'admin encaisse
    // - wave : le client paie via le lien global, l'admin bascule en « payée »
    'payment' => [
        'default_method' => 'cod',
        'methods'        => ['cod', 'wave'],
        'wave_link'      => env('WAVE_PAYMENT_LINK', ''),
    ],

    'pagination' => [
        'shop'  => 12,
        'admin' => 20,
    ],
];
