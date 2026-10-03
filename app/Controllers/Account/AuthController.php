<?php

declare(strict_types=1);

namespace App\Controllers\Account;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\User;

/**
 * Inscription et connexion du client.
 *
 * Le robot n'est pas nécessaire en V1 : la protection est assurée par le
 * jeton CSRF et la limitation de débit sur ces deux routes.
 */
final class AuthController extends Controller
{
    public function showLogin(Request $request): Response
    {
        return $this->view('account/login', [
            'title' => __('auth.login_title'),
        ], 'layouts/auth');
    }

    public function login(Request $request): Response
    {
        $email    = $request->str('email');
        $password = $request->str('password');
        $remember = $request->bool('remember');
        $errors   = [];

        if ($email === '') {
            $errors['email'] = __('validation.required', ['field' => __('auth.email')]);
        }

        if ($password === '') {
            $errors['password'] = __('validation.required', ['field' => __('auth.password')]);
        }

        if ($errors === []) {
            $user = Auth::attempt($email, $password);

            if ($user === null || !$user->isCustomer()) {
                $errors['email'] = __('auth.failed');
            }
        }

        if ($errors !== []) {
            Session::flashErrors($errors, [
                'email'    => $email,
                'remember' => $remember ? '1' : '0',
            ]);

            return redirect('/login');
        }

        // Le filtre d'adresse IP est appliqué à la connexion effective.
        $user->touchLogin($request->ipHash());

        Auth::login($user);

        $fallback = $user->isStaff() ? '/admin' : '/account';

        return redirect(Session::pull('intended_url') ?: $fallback);
    }

    public function showRegister(Request $request): Response
    {
        return $this->view('account/register', [
            'title' => __('auth.register_title'),
        ], 'layouts/auth');
    }

    public function register(Request $request): Response
    {
        $name     = trim($request->str('name'));
        $email    = $request->str('email');
        $password = $request->str('password');
        $errors   = [];

        if ($name === '') {
            $errors['name'] = __('validation.required', ['field' => __('auth.name')]);
        } elseif (mb_strlen($name, 'UTF-8') < 2) {
            $errors['name'] = __('validation.min', ['field' => __('auth.name'), 'min' => 2]);
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = __('validation.email', ['field' => __('auth.email')]);
        } elseif (User::findByEmail($email) !== null) {
            $errors['email'] = __('auth.email_taken');
        }

        if (mb_strlen($password, 'UTF-8') < 8) {
            $errors['password'] = __('validation.min', ['field' => __('auth.password'), 'min' => 8]);
        }

        if ($errors !== []) {
            Session::flashErrors($errors, ['name' => $name, 'email' => $email]);

            return redirect('/register');
        }

        $user = new User();
        $user->setAttribute('name', $name);
        $user->setAttribute('email', mb_strtolower($email, 'UTF-8'));
        $user->setPassword($password);
        $user->setAttribute('role', User::ROLE_CUSTOMER);
        $user->setAttribute('status', User::STATUS_ACTIVE);
        $user->save();

        $user->touchLogin($request->ipHash());
        Auth::login($user);

        Session::flashSuccess(__('auth.registered'));

        return redirect('/account');
    }

    public function logout(Request $request): Response
    {
        Auth::logout();

        // La destruction efface la session : on en ouvre une neuve pour
        // délivrer le message de confirmation au prochain chargement.
        Session::start();
        Session::flashSuccess(__('auth.logout_success'));

        return redirect('/');
    }
}