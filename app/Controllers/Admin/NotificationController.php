<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Services\NotificationService;

/**
 * Notifications : tout ce qui demande une action, avec le détail des
 * lignes concernées et un raccourci vers l'écran qui les traite.
 */
final class NotificationController extends AdminController
{
    public function index(Request $request): Response
    {
        $alerts = NotificationService::alerts();

        return $this->view('admin/notifications', [
            'title'    => __('admin.notifications.title'),
            'alerts'   => $alerts,
            'sections' => array_map(
                static fn (array $alert): array => [
                    'alert' => $alert,
                    'items' => NotificationService::items((string) $alert['type'], 8),
                ],
                $alerts
            ),
        ]);
    }
}
