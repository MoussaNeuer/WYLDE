<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Models\Order;
use App\Services\AnalyticsService;

/**
 * Analytics : tendances sur une période, top produits et clients.
 */
final class AnalyticsController extends AdminController
{
    public function index(Request $request): Response
    {
        $days = AnalyticsService::normalizeDays($request->int('days'), 30);

        $periods = [];

        foreach (AnalyticsService::periods() as $value => $label) {
            $periods[$value] = __('admin.period.' . $label);
        }

        $comparison = AnalyticsService::comparison($days);
        $series     = AnalyticsService::revenueSeries($days);
        $weekdays   = AnalyticsService::weekdayBreakdown($days);
        $topProducts = AnalyticsService::topProducts(10);
        $topCustomers = AnalyticsService::topCustomers(5);
        $byStatus    = AnalyticsService::ordersByStatus();

        return $this->view('admin/analytics/index', [
            'title'       => __('admin.analytics_title'),
            'days'        => $days,
            'periods'     => $periods,
            'periodLabel' => __('admin.period.' . AnalyticsService::periodLabel($days)),
            'comparison'  => $comparison,
            'series'      => $series,
            'weekdays'    => $weekdays,
            'topProducts' => $topProducts,
            'topCustomers'=> $topCustomers,
            'byStatus'    => $byStatus,
            'orderStatuses' => Order::statuses(),
        ]);
    }
}