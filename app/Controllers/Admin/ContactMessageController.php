<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\ContactMessage;
use App\Services\AuditService;

/**
 * Messages reçus depuis la page contact.
 *
 * L'ouverture d'un message le marque « lu » ; la suppression supprime la
 * ligne conservée en base (pas de corbeille en V1).
 */
final class ContactMessageController extends AdminController
{
    public function index(Request $request): Response
    {
        $query   = trim($request->str('q'));
        $status  = $request->str('status');
        $page    = max(1, $request->int('page', 1));
        $perPage = max(1, min(100, (int) config('app.pagination.admin', 20)));

        $clauses  = [];
        $bindings = [];

        if ($query !== '') {
            $like                  = '%' . str_replace(['%', '_'], ['\%', '\_'], $query) . '%';
            $clauses[]             = '(cm.name LIKE :q_name OR cm.email LIKE :q_email'
                                  . ' OR cm.subject LIKE :q_subject OR cm.message LIKE :q_message)';
            $bindings['q_name']    = $like;
            $bindings['q_email']   = $like;
            $bindings['q_subject'] = $like;
            $bindings['q_message'] = $like;
        }

        if ($status === ContactMessage::STATUS_NEW || $status === ContactMessage::STATUS_READ) {
            $clauses[]          = 'cm.status = :status';
            $bindings['status'] = $status;
        }

        $where = $clauses === [] ? '' : 'WHERE ' . implode(' AND ', $clauses);

        $total = (int) Database::selectValue(
            'SELECT COUNT(*) FROM `contact_messages` cm ' . $where,
            $bindings
        );

        $rows = Database::select(
            'SELECT * FROM `contact_messages` cm '
            . $where . ' ORDER BY cm.created_at DESC, cm.id DESC '
            . 'LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
            $bindings
        );

        $pages = (int) max(1, (int) ceil($total / $perPage));

        return $this->view('admin/messages/index', [
            'title'   => __('admin.messages.title'),
            'rows'    => $rows,
            'total'   => $total,
            'unread'  => ContactMessage::countUnread(),
            'status'  => $status,
            'query'   => $query,
            'page'    => $page,
            'pages'   => $pages,
            'prevUrl' => $page > 1 ? pagination_url($page - 1) : null,
            'nextUrl' => $page < $pages ? pagination_url($page + 1) : null,
        ]);
    }

    public function show(Request $request): Response
    {
        $message = ContactMessage::findOrFail($this->id($request));

        if (!$message->isRead()) {
            $message->markRead();
        }

        return $this->view('admin/messages/show', [
            'title'   => __('admin.messages.title'),
            'message' => $message,
        ]);
    }

    public function destroy(Request $request): Response
    {
        $message = ContactMessage::findOrFail($this->id($request));

        $subject = mb_substr(
            (string) ($message->subject !== '' && $message->subject !== null ? $message->subject : $message->name),
            0,
            120
        );

        $message->delete();

        AuditService::log(AuditService::ACTION_MESSAGE_DELETE, 'contact_messages', (int) $message->id(), [
            'subject' => $subject,
        ]);

        return $this->redirectWithSuccess('/admin/messages', __('flash.message_deleted'));
    }
}