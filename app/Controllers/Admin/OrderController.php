<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\UploadService;
use App\Validators\OrderValidator;

/**
 * Commandes : liste filtrable, détail, statut, paiement et notes.
 */
final class OrderController extends AdminController
{
    public function index(Request $request): Response
    {
        $params = array_filter([
            'q'              => $request->str('q'),
            'status'         => $request->str('status'),
            'payment_status' => $request->str('payment_status'),
            'payment_method' => $request->str('payment_method'),
            'from'           => $request->str('from'),
            'to'             => $request->str('to'),
            'sort'           => $request->str('sort'),
            'page'           => $request->int('page', 1),
        ], static fn (mixed $value): bool => $value !== '');

        $result = Order::adminList($params, (int) config('app.pagination.admin', 20));
        $page   = $result['page'];
        $last   = $result['pages'];

        return $this->view('admin/orders/index', [
            'title'          => __('admin.orders'),
            'orders'         => $result['orders'],
            'total'          => $result['total'],
            'page'           => $page,
            'pages'          => $last,
            'filters'        => $params,
            'statuses'       => Order::filterStatuses(),
            'paymentStatuses'=> Order::paymentStatuses(),
            'prevUrl'        => $page > 1 ? pagination_url($page - 1) : null,
            'nextUrl'        => $page < $last ? pagination_url($page + 1) : null,
        ]);
    }

    public function show(Request $request): Response
    {
        $order = Order::findOrFail($this->id($request));

        return $this->view('admin/orders/show', [
            'title'    => '#' . $order->reference,
            'order'    => $order,
            'items'    => $order->items(),
            'history'  => $order->history(),
            'warnings' => OrderService::warnings($order),
            'transitions' => $order->allowedTransitions(),
            'paymentStatuses' => Order::paymentStatuses(),
        ]);
    }

    public function updateStatus(Request $request): Response
    {
        $order  = Order::findOrFail($this->id($request));
        $status = $request->str('status');
        $note   = $request->str('note');

        $validator = new OrderValidator($request->all());

        $validator->validateStatus($status, $order);

        if ($validator->fails()) {
            return $this->redirectWithErrors('/admin/orders/' . $order->id() . '#status', $validator->errors(), $request->all());
        }

        $result = OrderService::changeStatus($order, $status, $note);

        if (!$result['ok']) {
            return $this->redirectWithErrors('/admin/orders/' . $order->id() . '#status', ['status' => $result['error']], $request->all());
        }

        if ($result['auto_paid']) {
            return $this->redirectWithSuccess('/admin/orders/' . $order->id() . '#status', __('flash.order_status_changed'));
        }

        return $this->redirectWithSuccess('/admin/orders/' . $order->id(), __('flash.order_status_changed'));
    }

    public function updatePayment(Request $request): Response
    {
        $order    = Order::findOrFail($this->id($request));
        $payment  = $request->str('payment_status');

        $validator = new OrderValidator($request->all());

        $validator->validatePayment($payment, $order);

        if ($validator->fails()) {
            return $this->redirectWithErrors('/admin/orders/' . $order->id() . '#payment', $validator->errors(), $request->all());
        }

        $result = OrderService::changePaymentStatus($order, $payment);

        if (!$result['ok']) {
            return $this->redirectWithErrors('/admin/orders/' . $order->id() . '#payment', ['payment_status' => $result['error']], $request->all());
        }

        return $this->redirectWithSuccess('/admin/orders/' . $order->id(), __('flash.payment_updated'));
    }

    public function deleteProof(Request $request): Response
    {
        $order = Order::findOrFail($this->id($request));

        $path = (string) ($order->getAttribute('payment_proof_path') ?? '');

        if ($path !== '') {
            UploadService::delete($path);

            Database::update('orders', ['payment_proof_path' => null], ['id' => $order->id()]);

            OrderService::addHistory(
                $order->id(),
                (string) $order->getAttribute('status'),
                'Preuve de paiement Wave supprimée.'
            );

            return $this->redirectWithSuccess('/admin/orders/' . $order->id(), __('flash.proof_removed'));
        }

        return $this->redirect('/admin/orders/' . $order->id());
    }

    public function updateNotes(Request $request): Response
    {
        $order = Order::findOrFail($this->id($request));

        $notes    = $request->has('admin_notes') ? $request->str('admin_notes') : null;
        $tracking = $request->has('tracking_number') ? $request->str('tracking_number') : null;

        $validator = new OrderValidator($request->all());

        $validator->validateNotes($notes, $tracking);

        if ($validator->fails()) {
            return $this->redirectWithErrors('/admin/orders/' . $order->id() . '#notes', $validator->errors(), $request->all());
        }

        $result = OrderService::updateNotes($order, $notes, $tracking);

        if (!$result['ok']) {
            return $this->redirectWithErrors('/admin/orders/' . $order->id() . '#notes', ['admin_notes' => $result['error']], $request->all());
        }

        return $this->redirectWithSuccess('/admin/orders/' . $order->id(), __('flash.notes_saved'));
    }
}