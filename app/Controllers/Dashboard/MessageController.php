<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Uploader;
use App\Models\Listing;
use App\Services\ChatService;

final class MessageController extends Controller
{
    protected string $layout = 'layouts/dashboard';

    public function index(Request $request): Response
    {
        return $this->view('chat/index', [
            'title' => 'Messages',
            'conversations' => ChatService::conversations($this->userId(), [
                'unread' => $request->query('unread') ? 1 : 0,
            ]),
            'active' => null,
        ]);
    }

    public function show(Request $request): Response
    {
        $conversationId = $request->paramInt('id');
        $conversation = ChatService::detail($conversationId, $this->userId());
        if ($conversation === null) {
            throw new HttpException(404, 'Conversation not found.');
        }

        ChatService::markRead($conversationId, $this->userId());
        $messages = ChatService::messages($conversationId, 0, 200);

        return $this->view('chat/show', [
            'title' => 'Chat with ' . ($conversation['other']['business_name'] ?? $conversation['other']['full_name'] ?? 'user'),
            'conversations' => ChatService::conversations($this->userId()),
            'conversation' => $conversation,
            'messages' => $messages,
            'attachments' => ChatService::attachments(array_map(static fn (array $m): int => (int) $m['id'], $messages)),
            'active' => $conversationId,
            'last_id' => $messages !== [] ? (int) end($messages)['id'] : 0,
        ]);
    }

    public function send(Request $request): Response
    {
        $conversationId = $request->paramInt('id');

        $attachments = [];
        foreach ($request->fileList('attachments') as $file) {
            $uploader = Uploader::documents('chat', 5);
            $path = $uploader->store($file, 1400);
            if ($path === null) {
                if ($request->wantsJson()) {
                    return $this->fail((string) $uploader->firstError());
                }
                flash('warning', (string) $uploader->firstError());
                continue;
            }
            $attachments[] = [
                'path' => $path,
                'name' => $file['name'] ?? '',
                'mime' => $file['type'] ?? '',
                'size' => (int) ($file['size'] ?? 0),
            ];
        }

        $result = ChatService::send(
            $conversationId,
            $this->userId(),
            (string) $request->input('body', ''),
            $attachments
        );

        if ($request->wantsJson()) {
            if (!$result['ok']) {
                return $this->fail((string) $result['error']);
            }
            $messages = ChatService::messages($conversationId, (int) $request->int('after'), 50);
            return $this->ok([
                'messages' => $this->presentMessages($messages),
                'last_id' => $messages !== [] ? (int) end($messages)['id'] : 0,
            ], 'Sent.');
        }

        if (!$result['ok']) {
            flash('danger', (string) $result['error']);
        }
        return $this->redirect('/dashboard/messages/' . $conversationId);
    }

    /** AJAX long-poll: return only messages newer than the cursor. */
    public function poll(Request $request): Response
    {
        $conversationId = $request->paramInt('id');
        $conversation = ChatService::detail($conversationId, $this->userId());
        if ($conversation === null) {
            return $this->fail('Conversation not found.', 404);
        }

        $after = $request->int('after');
        $messages = ChatService::messages($conversationId, $after, 50);
        if ($messages !== []) {
            ChatService::markRead($conversationId, $this->userId());
        }

        return $this->json([
            'success' => true,
            'messages' => $this->presentMessages($messages),
            'last_id' => $messages !== [] ? (int) end($messages)['id'] : $after,
            'unread_total' => ChatService::unreadCount($this->userId()),
            'server_time' => now(),
        ]);
    }

    /** Start (or resume) a conversation about a listing/requirement. */
    public function start(Request $request): Response
    {
        $listingId = $request->int('listing_id') ?: null;
        $requirementId = $request->int('requirement_id') ?: null;
        $sellerId = $request->int('seller_id') ?: null;
        $userId = $this->userId();

        $context = [];
        if ($listingId !== null) {
            $listing = Listing::find($listingId);
            if ($listing === null) {
                return $this->fail('Listing not found.', 404);
            }
            $sellerId = (int) $listing['user_id'];
            $context = ['listing_id' => $listingId, 'subject' => $listing['title']];
            $buyerId = $userId;
        } elseif ($requirementId !== null) {
            $requirement = \App\Models\WantedRequirement::find($requirementId);
            if ($requirement === null) {
                return $this->fail('Requirement not found.', 404);
            }
            // On a requirement the poster is the buyer and the visitor is the seller.
            $buyerId = (int) $requirement['user_id'];
            $sellerId = $userId;
            $context = ['requirement_id' => $requirementId, 'subject' => $requirement['title']];
        } else {
            if ($sellerId === null) {
                return $this->fail('Nothing to talk about — no listing or seller given.');
            }
            $buyerId = $userId;
        }

        if ($sellerId === $buyerId) {
            return $this->fail('You cannot message yourself.');
        }
        if (ChatService::isBlocked($buyerId, $sellerId)) {
            return $this->fail('Messaging is blocked between these accounts.');
        }

        $conversationId = ChatService::ensureConversation($buyerId, $sellerId, $context);

        $body = trim((string) $request->input('body', ''));
        if ($body !== '') {
            $result = ChatService::send($conversationId, $userId, $body);
            if (!$result['ok']) {
                return $this->fail((string) $result['error']);
            }
            if ($listingId !== null) {
                \App\Core\Database::instance()->statement(
                    'UPDATE listings SET enquiry_count = enquiry_count + 1 WHERE id = :l',
                    ['l' => $listingId]
                );
            }
        }

        if ($request->wantsJson()) {
            return $this->ok(
                ['conversation_id' => $conversationId, 'redirect' => base_url('dashboard/messages/' . $conversationId)],
                'Message sent.'
            );
        }
        return $this->redirect('/dashboard/messages/' . $conversationId);
    }

    public function block(Request $request): Response
    {
        $conversationId = $request->paramInt('id');
        $conversation = ChatService::detail($conversationId, $this->userId());
        if ($conversation === null) {
            throw new HttpException(404);
        }

        $targetId = (int) $conversation['other']['id'];
        $unblock = $request->bool('unblock');

        if ($unblock) {
            ChatService::unblock($this->userId(), $targetId);
            $message = 'User unblocked.';
        } else {
            ChatService::block($this->userId(), $targetId, (string) $request->input('reason', ''));
            $message = 'User blocked. They can no longer message you.';
        }

        \App\Services\AuditService::log($unblock ? 'user_unblocked' : 'user_blocked', 'user', $targetId);
        flash('success', $message);
        return $this->redirect('/dashboard/messages');
    }

    private function presentMessages(array $messages): array
    {
        $attachments = ChatService::attachments(array_map(static fn (array $m): int => (int) $m['id'], $messages));
        $userId = $this->userId();

        return array_map(static function (array $message) use ($attachments, $userId): array {
            return [
                'id' => (int) $message['id'],
                'body' => (string) ($message['body'] ?? ''),
                'type' => (string) $message['message_type'],
                'is_mine' => (int) $message['sender_id'] === $userId,
                'sender' => $message['sender_business'] ?: $message['sender_name'],
                'time' => fmt_dt($message['created_at'], 'd M, h:i A'),
                'ago' => time_ago($message['created_at']),
                'attachments' => array_map(static fn (array $a): array => [
                    'url' => upload_url($a['file_path']),
                    'name' => $a['original_name'] ?: 'attachment',
                    'size' => human_bytes((int) $a['file_size']),
                    'is_image' => str_starts_with((string) $a['mime_type'], 'image/'),
                ], $attachments[(int) $message['id']] ?? []),
            ];
        }, $messages);
    }
}
