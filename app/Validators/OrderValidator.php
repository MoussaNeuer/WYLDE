<?php

declare(strict_types=1);

namespace App\Validators;

use App\Models\Order;

/**
 * Validation des actions du back-office sur une commande.
 */
class OrderValidator extends Validator
{
    public function validateStatus(string $status, Order $order): void
    {
        $this->inList('status', __('common.status'), Order::statuses());

        if ($status !== '' && !in_array($status, Order::statuses(), true)) {
            return;
        }

        if (in_array($status, Order::statuses(), true) && !$order->canTransitionTo($status)) {
            $this->addError('status', __('admin.order.change_status') . ': transition interdite');
        }
    }

    public function validatePayment(string $paymentStatus, Order $order): void
    {
        $this->inList('payment_status', 'payment', Order::paymentStatuses());

        if (!in_array($paymentStatus, Order::paymentStatuses(), true)) {
            return;
        }

        // Un remboursement n'a de sens que si la commande avait été payée.
        // Réappliquer « remboursée » sur une commande déjà remboursée reste
        // sans effet et ne doit donc pas être bloqué.
        if ($paymentStatus === Order::PAYMENT_REFUNDED
            && $order->payment_status !== Order::PAYMENT_PAID
            && $order->payment_status !== Order::PAYMENT_REFUNDED
        ) {
            $this->addError('payment_status', __('admin.order.refund_requires_paid'));
        }
    }

    public function validateNotes(?string $notes, ?string $tracking = null): void
    {
        $this->maxLength('admin_notes', 'Notes internes', 5000);
        $this->maxLength('tracking_number', 'Numéro de suivi', 120);

        if ($notes !== null && trim($notes) !== '' && mb_strlen(trim($notes), 'UTF-8') > 5000) {
            $this->addError('admin_notes', __('validation.max', ['field' => 'Notes internes', 'max' => 5000]));
        }

        if ($tracking !== null && trim($tracking) !== '' && mb_strlen(trim($tracking), 'UTF-8') > 120) {
            $this->addError('tracking_number', __('validation.max', ['field' => 'Numéro de suivi', 'max' => 120]));
        }
    }
}