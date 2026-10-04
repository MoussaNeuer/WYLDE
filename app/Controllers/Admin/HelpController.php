<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;

/**
 * Aide du back-office : démarrage rapide, raccourcis clavier et
 * dépannage. Les contenus vivent dans resources/lang/{fr,en}.php.
 */
final class HelpController extends AdminController
{
    public function index(Request $request): Response
    {
        return $this->view('admin/help', [
            'title'      => __('admin.help.title'),
            'steps'      => [
                ['title' => __('admin.help.step_1_title'), 'text' => __('admin.help.step_1_text')],
                ['title' => __('admin.help.step_2_title'), 'text' => __('admin.help.step_2_text')],
                ['title' => __('admin.help.step_3_title'), 'text' => __('admin.help.step_3_text')],
                ['title' => __('admin.help.step_4_title'), 'text' => __('admin.help.step_4_text')],
            ],
            'shortcuts'  => [
                ['keys' => 'Alt + 1…5', 'action' => __('admin.help.shortcut_nav')],
                ['keys' => '/', 'action' => __('admin.help.shortcut_search')],
                ['keys' => 'N', 'action' => __('admin.help.shortcut_new')],
                ['keys' => 'B', 'action' => __('admin.help.shortcut_menu')],
                ['keys' => '?', 'action' => __('admin.help.shortcut_help')],
                ['keys' => 'Échap', 'action' => __('admin.help.shortcut_close')],
            ],
            'topics'     => [
                ['q' => __('admin.help.topic_stock_q'), 'a' => __('admin.help.topic_stock_a')],
                ['q' => __('admin.help.topic_order_q'), 'a' => __('admin.help.topic_order_a')],
                ['q' => __('admin.help.topic_payment_q'), 'a' => __('admin.help.topic_payment_a')],
                ['q' => __('admin.help.topic_access_q'), 'a' => __('admin.help.topic_access_a')],
            ],
        ]);
    }
}
