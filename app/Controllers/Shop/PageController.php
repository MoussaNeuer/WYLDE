<?php

declare(strict_types=1);

namespace App\Controllers\Shop;

use App\Controllers\Controller;
use App\Core\Config;
use App\Core\Lang;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Product;

/**
 * Pages éditoriales et bascule de langue.
 */
final class PageController extends Controller
{
    /**
     * Bascule FR/EN. Les URLs ne changent pas : la langue est mémorisée
     * en session puis en cookie, ce qui évite toute duplication d'URL.
     */
    public function switchLocale(Request $request): Response
    {
        $code = (string) $request->routeParam('code', 'fr');

        if (!Lang::isAvailable($code)) {
            $code = (string) Config::get('app.locale', 'fr');
        }

        Lang::setLocale($code);

        $cookieName = (string) Config::get('app.locale_cookie', 'wylde_locale');
        $expires    = time() + 365 * 86400;

        if (!headers_sent()) {
            setcookie($cookieName, $code, [
                'expires'  => $expires,
                'path'     => (string) Config::get('security.session.cookie_path', '/'),
                'secure'   => (bool) Config::get('security.session.secure', false),
                'httponly' => false, // lu par le JavaScript de la page
                'samesite' => 'Lax',
            ]);
        }

        return redirect($request->header('Referer') ?: '/');
    }

    public function about(Request $request): Response
    {
        return $this->view('shop/about', [
            'title' => __('page.about'),
        ], 'layouts/shop');
    }

    public function contact(Request $request): Response
    {
        return $this->view('shop/contact', [
            'title' => __('contact.title'),
        ], 'layouts/shop');
    }

    public function privacy(Request $request): Response
    {
        return $this->view('shop/privacy', [
            'title' => __('page.privacy'),
        ], 'layouts/shop');
    }

    public function terms(Request $request): Response
    {
        return $this->view('shop/terms', [
            'title' => __('page.terms'),
        ], 'layouts/shop');
    }

    /**
     * Réception du formulaire de contact.
     *
     * Aucun envoi d'email en V1 (§22) : le message est seulement journalisé
     * et l'utilisateur reçoit une confirmation. Le filtrage spam CSRF est
     * assuré par le middleware 'csrf' sur la route.
     */
    public function submitContact(Request $request): Response
    {
        $data = [
            'name'    => $request->str('name'),
            'email'   => $request->str('email'),
            'subject' => $request->str('subject'),
            'message' => $request->str('message'),
        ];

        $errors = [];

        if ($data['name'] === '') {
            $errors['name'] = __('validation.required', ['field' => __('contact.name')]);
        }

        if ($data['email'] === '' || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = __('validation.email', ['field' => __('contact.email')]);
        }

        if ($data['message'] === '') {
            $errors['message'] = __('validation.required', ['field' => __('contact.message')]);
        }

        if ($errors !== []) {
            Session::flashErrors($errors, $data);

            return $request->wantsJson()
                ? Response::json(['ok' => false, 'errors' => $errors], 422)
                : redirect('/contact');
        }

        Logger::info('Message de contact reçu', [
            'email'   => $data['email'],
            'subject' => mb_substr($data['subject'], 0, 120),
        ]);

        Session::flashSuccess(__('contact.sent'));

        return $request->wantsJson()
            ? Response::json(['ok' => true])
            : redirect('/contact');
    }

    /**
     * Sitemap XML : pages publiques et produits publiés.
     */
    public function sitemap(Request $request): Response
    {
        $urls = [
            ['loc' => url('/'),             'priority' => '1.0', 'freq' => 'daily'],
            ['loc' => url('/shop'),         'priority' => '0.9', 'freq' => 'daily'],
            ['loc' => url('/about'),        'priority' => '0.5', 'freq' => 'monthly'],
            ['loc' => url('/contact'),      'priority' => '0.5', 'freq' => 'monthly'],
            ['loc' => url('/privacy'),      'priority' => '0.3', 'freq' => 'yearly'],
            ['loc' => url('/terms'),        'priority' => '0.3', 'freq' => 'yearly'],
        ];

        try {
            $products = Product::publishedForSitemap();

            foreach ($products as $product) {
                if (($product->slug ?? '') === '') {
                    continue;
                }

                $urls[] = [
                    'loc'      => url('/product/' . $product->slug),
                    'lastmod'  => ($product->updated_at ?? $product->created_at ?? null),
                    'priority' => '0.8',
                    'freq'     => 'weekly',
                ];
            }
        } catch (Throwable) {
            // Un produit illisible ne doit pas casser le sitemap.
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $entry) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . e($entry['loc']) . "</loc>\n";

            if (!empty($entry['lastmod'])) {
                $timestamp = strtotime((string) $entry['lastmod']);

                if ($timestamp !== false) {
                    $xml .= '    <lastmod>' . date('Y-m-d', $timestamp) . "</lastmod>\n";
                }
            }

            $xml .= '    <changefreq>' . $entry['freq'] . "</changefreq>\n";
            $xml .= '    <priority>' . $entry['priority'] . "</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        return Response::make($xml)
            ->setHeader('Content-Type', 'application/xml; charset=UTF-8');
    }

    /**
     * Manifeste PWA : permet d'installer la boutique sur l'écran d'accueil
     * Android/iOS avec la même icône que l'onglet du navigateur.
     *
     * Le contenu est calculé (nom, slogan, langue) pour rester aligné sur la
     * configuration, et non figé dans un fichier statique.
     */
    public function manifest(Request $request): Response
    {
        $icon = static fn (string $file): string => asset('assets/images/logo/' . $file);

        $manifest = [
            'name'             => (string) config('app.name', 'WYLDE'),
            'short_name'       => mb_substr((string) config('app.name', 'WYLDE'), 0, 12),
            'description'      => (string) __('app.tagline'),
            'lang'             => locale(),
            'dir'              => 'ltr',
            'start_url'        => url('/'),
            'scope'            => url('/'),
            'display'          => 'standalone',
            'orientation'      => 'portrait-primary',
            'background_color' => '#FFFFFF',
            'theme_color'      => '#FFFFFF',
            'categories'       => ['shopping', 'lifestyle'],
            'icons'            => [
                [
                    'src'     => $icon('favicon.svg'),
                    'sizes'   => 'any',
                    'type'    => 'image/svg+xml',
                    'purpose' => 'any',
                ],
                [
                    'src'   => $icon('icon-192.png'),
                    'sizes' => '192x192',
                    'type'  => 'image/png',
                ],
                [
                    'src'   => $icon('icon-512.png'),
                    'sizes' => '512x512',
                    'type'  => 'image/png',
                ],
                [
                    'src'     => $icon('icon-192-maskable.png'),
                    'sizes'   => '192x192',
                    'type'    => 'image/png',
                    'purpose' => 'maskable',
                ],
                [
                    'src'     => $icon('icon-512-maskable.png'),
                    'sizes'   => '512x512',
                    'type'    => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
        ];

        return Response::json($manifest)
            ->setHeader('Content-Type', 'application/manifest+json; charset=UTF-8');
    }
}
