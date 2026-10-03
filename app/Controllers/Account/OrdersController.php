<?php

declare(strict_types=1);

namespace App\Controllers\Account;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Models\Order;

/**
 * Historique des commandes du client connecté.
 *
 * Le scoping est fait dans la requête (Order::findForUser) : un client
 * ne voit que ses propres commandes, l'identifiant n'est jamais utilisé
 * seul comme preuve d'accès.
 */
final class OrdersController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();

        if ($user === null) {
            return $this->redirect('/login');
        }

        $perPage = 10;
        $page    = $this->page($request);

        $result = Order::listForUser(
            (int) $user->id(),
            (string) $user->email,
            $perPage,
            ($page - 1) * $perPage
        );

        $orders = $result['orders'];
        $total  = (int) $result['total'];
        $pages  = (int) max(1, (int) ceil($total / $perPage));

        // Une page demandée au-delà du dernier folio revient au dernier.
        if ($page > $pages) {
            return $this->redirect('/account/orders?page=' . $pages);
        }

        return $this->view('account/orders/index', [
            'title'   => __('account.orders'),
            'orders'  => $orders,
            'total'   => $total,
            'page'    => $page,
            'pages'   => $pages,
            'prevUrl' => $page > 1 ? '/account/orders?page=' . ($page - 1) : null,
            'nextUrl' => $page < $pages ? '/account/orders?page=' . ($page + 1) : null,
        ], 'layouts/shop');
    }

    public function show(Request $request): Response
    {
        $user = Auth::user();

        if ($user === null) {
            return $this->redirect('/login');
        }

        $order = Order::findForUser(
            $this->id($request),
            (int) $user->id(),
            (string) $user->email
        );

        if ($order === null) {
            abort(404);
        }

        return $this->view('account/orders/show', [
            'title' => __('account.order_detail') . ' — ' . $order->reference,
            'order' => $order,
        ], 'layouts/shop');
    }
}
