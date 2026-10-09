<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\User;
use App\Services\LoginThrottle;

/**
 * Connexion au back-office. Un client ordinaire n'accède pas ici.
 */
final class AuthController extends Controller
{
    public function showLogin(Request $request): Response
    {
        if (Auth::isStaff()) {
            return redirect('/admin');
        }

        return $this->view('admin/auth/login', [
            'title' => __('admin.login_title'),
        ], 'layouts/admin-login');
    }

    public function login(Request $request): Response
    {
        $email    = $request->str('email');
        $password = $request->str('password');

        $errors = [];

        // Anti brute-force : bloque avant même de vérifier le mot de passe.
        // Le bucket est distinct de celui des clients : un assaut sur un
        // côté ne draine pas le quota de l'autre.
        $lockedFor = LoginThrottle::lockedFor('login:admin', $email, $request->ip());

        if ($lockedFor > 0) {
            $errors['email'] = __('auth.locked', [
                'minutes' => (int) ceil($lockedFor / 60),
            ]);
        } else {
            if ($email === '') {
                $errors['email'] = __('validation.required', ['field' => __('auth.email')]);
            }

            if ($password === '') {
                $errors['password'] = __('validation.required', ['field' => __('auth.password')]);
            }

            $user = $errors === [] ? Auth::attempt($email, $password) : null;

            // Le middleware AdminMiddleware refuse les non-staff ; on le vérifie
            // ici pour rediriger vers /admin au lieu d'échouer après coup.
            if ($user === null || !$user->isStaff() || !$user->isActive()) {
                LoginThrottle::recordFailure('login:admin', $email, $request->ip());
                $errors['email'] = __('auth.failed');
            }
        }

        if ($errors !== []) {
            Session::flashErrors($errors, ['email' => $email]);

            return $this->redirect('/admin/login');
        }

        // Réinitialise le compteur pour cette adresse (e-mail + IP).
        LoginThrottle::forget('login:admin', $email, $request->ip());

        $user->touchLogin($request->ipHash());
        Auth::login($user);

        return $this->redirect((string) (Session::pull('intended_url') ?: '/admin'));
    }
}