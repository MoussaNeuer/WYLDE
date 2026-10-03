<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\Customer;
use App\Models\Order;

/**
 * Clients : liste avec historiques d'achats et fiche détail.
 *
 * La fiche client est volontairement en lecture seule en V1 : ses données
 * (email, téléphone) vivent sur users/orders et toute édition aurait des
 * répercussions sur l'historique des commandes.
 */
final class CustomerController extends AdminController
{
    public function index(Request $request): Response
    {
        $query = trim($request->str('q'));
        $page  = max(1, $request->int('page', 1));
        $perPage = max(1, min(100, (int) config('app.pagination.admin', 20)));

        $clauses  = [];
        $bindings = [];

        if ($query !== '') {
            $like                  = '%' . str_replace(['%', '_'], ['\%', '\_'], $query) . '%';
            $clauses[]             = '(c.first_name LIKE :q_first OR c.last_name LIKE :q_last'
                                  . ' OR c.email LIKE :q_email OR c.phone LIKE :q_phone)';
            $bindings['q_first']   = $like;
            $bindings['q_last']    = $like;
            $bindings['q_email']   = $like;
            $bindings['q_phone']   = $like;
        }

        $where = $clauses === [] ? '' : 'WHERE ' . implode(' AND ', $clauses);

        $total = (int) Database::selectValue('SELECT COUNT(*) FROM `customers` c ' . $where, $bindings);

        $rows = Database::select(
            'SELECT c.*,
                    COUNT(DISTINCT o.id) AS orders_count,
                    COALESCE(SUM(o.total), 0) AS lifetime_value
             FROM `customers` c
             LEFT JOIN `orders` o ON o.customer_id = c.id
             ' . $where . '
             GROUP BY c.id
             ORDER BY c.created_at DESC, c.id DESC
             LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
            $bindings
        );

        $pages = (int) max(1, (int) ceil($total / $perPage));

        return $this->view('admin/customers/index', [
            'title'  => __('admin.customers'),
            'rows'   => $rows,
            'total'  => $total,
            'page'   => $page,
            'pages'  => $pages,
            'query'  => $query,
            'prevUrl' => $page > 1 ? pagination_url($page - 1) : null,
            'nextUrl' => $page < $pages ? pagination_url($page + 1) : null,
        ]);
    }

    public function show(Request $request): Response
    {
        $customer = Customer::findOrFail($this->id($request));

        $orders = Order::allWhereCustomer((int) $customer->id, 0, 100);

        return $this->view('admin/customers/show', [
            'title'    => $customer->fullName(),
            'customer' => $customer,
            'orders'   => $orders,
        ]);
    }
}