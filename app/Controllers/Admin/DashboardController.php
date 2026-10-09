<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Models\Order;
use App\Services\AnalyticsService;

/**
 * Tableau de bord : chiffres du jour, flux des commandes, les KPIs qui
 * ne demandent pas d'aller fouiller chaque écran.
 */
final class DashboardController extends AdminController
{
    public function index(Request $request): Response
    {
        $summary     = AnalyticsService::summary();
        $orders      = Order::recent(8);
        $byStatus    = AnalyticsService::ordersByStatus();
        $topProducts = AnalyticsService::topProducts(5);
        $series      = AnalyticsService::revenueSeries(7);

        return $this->view('admin/dashboard', [
            'title'       => __('admin.dashboard'),
            'summary'     => $summary,
            'orders'      => $orders,
            'byStatus'    => $byStatus,
            'topProducts' => $topProducts,
            'series'      => $series,
        ]);
    }
}