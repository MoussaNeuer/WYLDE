<?php

declare(strict_types=1);

namespace App\Controllers\Account;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Tableau de bord du client connecté.
 */
final class AccountController extends Controller
{
    public function index(Request $request): Response
    {
        $user     = Auth::user();
        $customer = $user?->customer();

        return $this->view('account/index', [
            'title'        => __('account.title'),
            'user'         => $user,
            'customer'     => $customer,
            'ordersCount'  => $user?->ordersCount() ?? 0,
            'totalSpent'   => $user?->totalSpent() ?? 0,
        ], 'layouts/shop');
    }

    public function update(Request $request): Response
    {
        $user = Auth::user();

        if ($user === null) {
            return redirect('/login');
        }

        $name  = trim($request->str('name'));
        $phone = trim($request->str('phone'));
        $errors = [];

        if ($name === '') {
            $errors['name'] = __('validation.required', ['field' => __('auth.name')]);
        }

        if ($errors !== []) {
            return $this->redirectWithErrors('/account', $errors, [
                'name'  => $name,
                'phone' => $phone,
            ]);
        }

        $user->setAttribute('name', $name);
        $user->setAttribute('phone', $phone !== '' ? $phone : null);
        $user->save();

        return $this->redirectWithSuccess('/account', __('account.saved'));
    }
}