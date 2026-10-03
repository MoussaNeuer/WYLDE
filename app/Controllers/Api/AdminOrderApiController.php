<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Order;
use App\Services\AnalyticsService;
use App\Services\OrderService;

/**
 * API commandes du back-office : rafraîchissement asynchrone de la liste
 * et changement de statut sans rechargement. Les règles de transition
 * restent entièrement côté serveur (Order::allowedTransitions) : le client
 * ne fait que proposer un statut.
 */
final class AdminOrderApiController extends Controller
{
    public function index(Request $request): Response
    {
        $params = array_filter([
            'q'     => $request->str('q'),
            'status' => $request->str('status'),
            'payment_status' => $request->str('payment_status'),
            'sort'  => $request->str('sort'),
            'page'  => $request->int('page', 1),
        ], static fn (mixed $value): bool => $value !== '');

        $result = Order::adminList($params, (int) config('app.pagination.admin', 20));

        $orders = array_map(static fn (Order $order): array => [
            'id'         => (int) $order->id(),
            'reference'  => (string) $order->reference,
            'customer'   => $order->customerName(),
            'total'      => (int) $order->total,
            'status'     => (string) $order->status,
            'payment_status' => (string) $order->payment_status,
            'created_at' => (string) $order->created_at,
            'item_count' => count($order->items()),
            'url'        => url('/admin/orders/' . $order->id()),
        ], $result['orders']);

        return $this->json([
            'orders'   => $orders,
            'total'    => (int) $result['total'],
            'page'     => (int) $result['page'],
            'pages'    => (int) $result['pages'],
            'per_page' => (int) $result['per_page'],
            'actionable' => Order::countActionable(),
        ]);
    }

    public function updateStatus(Request $request): Response
    {
        $order  = Order::findOrFail($this->id($request));
        $status = $request->str('status');

        $result = OrderService::changeStatus($order, $status, $request->str('note'));

        if (!$result['ok']) {
            return $this->json([
                'error'   => $result['error'],
                'allowed' => $order->allowedTransitions(),
            ], 422);
        }

        /** @var Order $updated */
        $updated = $result['order'];

        return $this->json([
            'status'       => (string) $updated->status,
            'payment_status' => (string) $updated->payment_status,
            'auto_paid'    => (bool) $result['auto_paid'],
            'history'      => array_map(static fn (array $line): array => [
                'status' => (string) $line['status'],
                'note'   => (string) ($line['note'] ?? ''),
                'at'     => (string) $line['created_at'],
            ], $updated->history()),
            'warnings'     => OrderService::warnings($updated),
        ]);
    }

    /** Chiffres clés du back-office, pour rafraîchir le tableau de bord. */
    public function stats(Request $request): Response
    {
        $days = max(7, min(180, $request->int('days', 30)));

        return $this->json([
            'summary'       => AnalyticsService::summary(),
            'by_status'     => AnalyticsService::ordersByStatus(),
            'series'        => AnalyticsService::revenueSeries($days),
            'top_products'  => AnalyticsService::topProducts(),
            'top_customers' => AnalyticsService::topCustomers(),
            'weekday'       => AnalyticsService::weekdayBreakdown($days),
            'comparison'    => AnalyticsService::comparison($days),
        ]);
    }
}
