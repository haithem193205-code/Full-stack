<?php
/**
 * ================================================================
 *  conversations.php — Conversation management endpoint
 * ================================================================
 *  GET  ?action=list                → user's conversations
 *  GET  ?action=messages&id=123     → messages for one conversation
 *  POST action=rename  {id,title}   → rename a conversation
 *  POST action=delete  {id}         → delete a conversation
 * ================================================================
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

header('X-Content-Type-Options: nosniff');
$auth->requireLoginApi();

$user = $auth->user();
$chatRepo = new ChatRepository($config);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        $action = $_GET['action'] ?? 'list';

        if ($action === 'list') {
            jsonResponse(['success' => true, 'data' => $chatRepo->listConversations($user['id'])]);
        }

        if ($action === 'messages') {
            $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

            if (!$chatRepo->ownsConversation($id, $user['id'])) {
                jsonResponse(['success' => false, 'error' => 'Conversation not found.'], 404);
            }

            jsonResponse(['success' => true, 'data' => $chatRepo->getMessages($id)]);
        }

        jsonResponse(['success' => false, 'error' => 'Unknown action.'], 400);
    }

    if ($method === 'POST') {
        $body = readJsonBody();
        $action = $body['action'] ?? '';
        $id = isset($body['id']) ? (int) $body['id'] : 0;

        if ($action === 'rename') {
            $ok = $chatRepo->renameConversation($id, $user['id'], (string) ($body['title'] ?? ''));
            jsonResponse(['success' => $ok], $ok ? 200 : 422);
        }

        if ($action === 'delete') {
            $ok = $chatRepo->deleteConversation($id, $user['id']);
            jsonResponse(['success' => $ok], $ok ? 200 : 404);
        }

        // POST action=delete_message {id} → delete a single message
        // (used for "undo" when a message was sent by mistake).
        if ($action === 'delete_message') {
            $ok = $chatRepo->deleteMessage($id, $user['id']);
            jsonResponse(['success' => $ok], $ok ? 200 : 404);
        }

        jsonResponse(['success' => false, 'error' => 'Unknown action.'], 400);
    }

    jsonResponse(['success' => false, 'error' => 'Method not allowed.'], 405);

} catch (Throwable $e) {
    logAppError('conversations.php failed: ' . $e->getMessage());
    jsonResponse(['success' => false, 'error' => 'Something went wrong.'], 500);
}