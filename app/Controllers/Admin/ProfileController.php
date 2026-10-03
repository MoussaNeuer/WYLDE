<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditService;
use App\Validators\UserValidator;

/**
 * Profil, sécurité et journal d'activité de l'administrateur connecté.
 */
final class ProfileController extends AdminController
{
    public function index(Request $request): Response
    {
        return $this->view('admin/profile/index', [
            'title' => __('admin.profile'),
            'user'  => Auth::user(),
        ]);
    }

    public function update(Request $request): Response
    {
        $user = Auth::user();

        if ($user === null) {
            return redirect('/admin/login');
        }

        $data = $request->all();

        // L'identité est imposée par la session, jamais par le formulaire :
        // elle sert à exclure la ligne courante du contrôle d'unicité.
        $data['user_id'] = (int) $user->id();

        $validator = new UserValidator($data);

        $validator->validate();

        if ($validator->fails()) {
            return $this->redirectWithErrors('/admin/profile', $validator->errors(), $request->all());
        }

        $data = $validator->profileData();
        $data['email'] = strtolower(trim($request->str('email', (string) $user->email)));

        $user->fill($data);
        $user->save();

        AuditService::log(AuditService::ACTION_PROFILE_UPDATE, 'users', (int) $user->id());

        return $this->redirectWithSuccess('/admin/profile', __('flash.profile_saved'));
    }

    public function security(Request $request): Response
    {
        $user   = Auth::user();
        $page   = max(1, $request->int('page', 1));
        $result = AuditLog::paginate([], $page, 30);

        return $this->view('admin/profile/security', [
            'title' => __('admin.security'),
            'user'  => $user,
            'actions' => AuditLog::actions(),
            'logs'  => $result['logs'],
            'total' => $result['total'],
            'page'  => $page,
            'pages' => $result['pages'],
        ]);
    }

    public function updatePassword(Request $request): Response
    {
        $user = Auth::user();

        if ($user === null) {
            return redirect('/admin/login');
        }

        $current = (string) $request->str('current_password');

        if (!$user->verifyPassword($current)) {
            return $this->redirectWithErrors('/admin/security#password', [
                'current_password' => 'Le mot de passe actuel est incorrect.',
            ]);
        }

        $validator = new UserValidator($request->all(), UserValidator::MODE_SECURITY);

        $validator->validatePasswordOnly();

        if ($validator->fails()) {
            return $this->redirectWithErrors('/admin/security#password', $validator->errors());
        }

        $user->setPassword((string) $request->str('password'));
        $user->save();

        AuditService::log(AuditService::ACTION_PASSWORD_CHANGE, 'users', (int) $user->id());

        return $this->redirectWithSuccess('/admin/security', __('flash.password_changed'));
    }

    /**
     * Révocation de toutes les sessions admin sauf celle en cours.
     *
     * Les sessions PHP natifs vivent dans des fichiers horodatés :
     * on supprime ceux qui ne correspondent pas au cookie courant.
     */
    public function revokeSessions(Request $request): Response
    {
        $savePath = trim((string) session_save_path());

        if ($savePath === '') {
            $savePath = sys_get_temp_dir();
        }

        $prefix = session_name() . '_';
        $current = session_id();

        $count = 0;

        foreach ((array) glob(rtrim($savePath, '/\\') . DIRECTORY_SEPARATOR . $prefix . '*') as $file) {
            if (basename($file) === $prefix . $current) {
                continue;
            }

            // Ne touche pas aux fichiers encore utilisés par un autre
            // processus : un échec ici est sans gravité.
            if (@unlink($file)) {
                $count++;
            }
        }

        AuditService::log(AuditService::ACTION_SESSIONS_REVOKE, 'users', (int) auth()?->id(), [
            'revoked' => $count,
        ]);

        return $this->redirectWithSuccess('/admin/security', __('flash.sessions_revoked'));
    }
}