<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Response;

/**
 * Base des contrôleurs du back-office.
 *
 * Diffère du contrôleur commun uniquement par son layout par défaut :
 * aucune donnée métier n'est dupliquée ici.
 */
abstract class AdminController extends Controller
{
    protected function view(string $template, array $data = [], ?string $layout = 'layouts/admin', int $status = 200): Response
    {
        return parent::view($template, $data, $layout, $status);
    }
}