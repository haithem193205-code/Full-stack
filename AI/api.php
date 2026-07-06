<?php
/**
 * ================================================================
 *  api.php — Chat API Endpoint
 * ================================================================
 *  Single responsibility: orchestrate the request/response cycle.
 *
 *    1. Enforce method + auth + basic request hygiene
 *    2. Validate & sanitize input
 *    3. Persist the user message, delegate to AIService, persist reply
 *    4. Return a clean, predictable JSON contract
 *
 *  Response contract (success):
 *  {
 *    "success": true,
 *    "data": {
 *      "id": "msg_xxxxx",
 *      "conversation_id": 12,
 *      "reply": "...",
 *      "provider": "demo|openai|claude|gemini",
 *      "timestamp": "2026-07-02T10:15:00+00:00"
 *    }
 *  }
 *
 *  Response contract (error):
 *  {
 *    "success": false,
 *    "error": "Human readable message"
 *  }
 * ================================================================
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/AIService.php';

// ---------------------------------------------------------------
// 1. Request hygiene + auth
// ---------------------------------------------------------------
header('X-Content-Type-Options: nosniff');
requireMethod('POST');
$auth->requireLoginApi();

$user = $auth->user();
$chatRepo = new ChatRepository($config);

try {
    $body = readJsonBody();

    $rawMessage = $body['message'] ?? ($_POST['message'] ?? '');
    $history    = $body['history'] ?? [];
    $conversationId = isset($body['conversation_id']) ? (int) $body['conversation_id'] : 0;

    if (!is_string($rawMessage)) {
        jsonResponse(['success' => false, 'error' => 'Invalid message format.'], 422);
    }

    if (!is_array($history)) {
        $history = [];
    }

    // ---------------------------------------------------------------
    // 2. Validate & sanitize
    // ---------------------------------------------------------------
    $message = sanitizeInput($rawMessage);
    $maxLen  = $config['ai']['max_message_length'] ?? 2000;

    $validation = validateMessage($message, $maxLen);

    if (!$validation['valid']) {
        jsonResponse(['success' => false, 'error' => $validation['error']], 422);
    }

    // Sanitize conversation history entries defensively.
    $cleanHistory = [];
    foreach (array_slice($history, -10) as $turn) { // cap context window
        if (!isset($turn['role'], $turn['content']) || !is_string($turn['content'])) {
            continue;
        }
        $cleanHistory[] = [
            'role'    => $turn['role'] === 'user' ? 'user' : 'assistant',
            'content' => sanitizeInput((string) $turn['content']),
        ];
    }

    // ---------------------------------------------------------------
    // 3. Resolve (or create) the conversation this message belongs to
    // ---------------------------------------------------------------
    if ($conversationId > 0 && !$chatRepo->ownsConversation($conversationId, $user['id'])) {
        jsonResponse(['success' => false, 'error' => 'Conversation not found.'], 404);
    }

    if ($conversationId === 0) {
        $conversationId = $chatRepo->createConversation($user['id']);
    }

    $chatRepo->addMessage($conversationId, 'user', $message);
    $chatRepo->maybeSetTitleFromFirstMessage($conversationId, $message);

    // ---------------------------------------------------------------
    // 4. Delegate to the AI service layer
    // ---------------------------------------------------------------
    $aiService = new AIService($config);

    try {
        $result = $aiService->getResponse($message, $cleanHistory);
    } catch (Throwable $aiError) {
        logAppError('AIService failed: ' . $aiError->getMessage());

        jsonResponse([
            'success' => false,
            'error'   => 'AI provider temporarily unavailable.',
        ], 502);
    }

    $chatRepo->addMessage($conversationId, 'assistant', $result['reply'], $result['provider']);

    // ---------------------------------------------------------------
    // 5. Respond
    // ---------------------------------------------------------------
    jsonResponse([
        'success' => true,
        'data'    => [
            'id'              => generateMessageId(),
            'conversation_id' => $conversationId,
            'reply'           => $result['reply'],
            'provider'        => $result['provider'],
            'demo'            => $result['demo'],
            'timestamp'       => date(DATE_ATOM),
        ],
    ]);
} catch (Throwable $error) {

    logAppError('API request failed: ' . $error->getMessage());

    $isDev = ($config['app']['env'] ?? 'production') !== 'production';

    jsonResponse([
        'success' => false,
        'error'   => $isDev
            ? $error->getMessage()
            : 'AI provider temporarily unavailable.',
    ], 502);
}
