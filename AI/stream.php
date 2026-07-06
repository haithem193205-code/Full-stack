<?php
/**
 * ================================================================
 *  stream.php — Chat API Endpoint (Server-Sent Events)
 * ================================================================
 *  Same contract as api.php (auth, validation, persistence) but the
 *  AI's reply is streamed to the browser token-by-token instead of
 *  being sent as one JSON blob once the whole reply is ready.
 *
 *  Events sent to the client:
 *    event: chunk   data: {"text": "..."}         (repeated)
 *    event: done    data: {conversation_id, provider, demo, ...}
 *    event: error   data: {"error": "..."}
 * ================================================================
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/AIService.php';
error_log("OLLAMA KEY: " . substr($config['ai']['providers']['ollama']['api_key'], 0, 10));
error_log("OLLAMA ENDPOINT: " . $config['ai']['providers']['ollama']['endpoint']);
error_log("OLLAMA MODEL: " . $config['ai']['providers']['ollama']['model']);

// ---------------------------------------------------------------
// 1. Request hygiene + auth (identical to api.php)
// ---------------------------------------------------------------
requireMethod('POST');
$auth->requireLoginApi();

$user = $auth->user();
$chatRepo = new ChatRepository($config);

// ---------------------------------------------------------------
// 2. SSE headers — disable every layer of buffering so chunks reach
//    the browser as soon as they're echoed, not all at once at exit.
// ---------------------------------------------------------------
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no'); // nginx: don't buffer this response
header('Connection: keep-alive');

if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', '1');
}
ini_set('zlib.output_compression', '0');
ini_set('output_buffering', 'off');
ini_set('implicit_flush', '1');

while (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(true);
set_time_limit(0);

/**
 * Write one SSE event to the client and flush immediately.
 */
function sseSend(string $event, array $data): void
{
    echo "event: {$event}\n";
    echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";

    if (ob_get_level() > 0) {
        @ob_flush();
    }
    @flush();
}

try {
    $body = readJsonBody();

    $rawMessage      = $body['message'] ?? '';
    $history         = $body['history'] ?? [];
    $conversationId  = isset($body['conversation_id']) ? (int) $body['conversation_id'] : 0;

    // Optional attachments coming from the paperclip button in the chat UI.
    // - image: a data: URL (e.g. "data:image/png;base64,....") for pictures.
    // - file_name / file_text: extracted plain-text content of a normal
    //   file (txt/csv/json/md) the user attached, read client-side.
    $imageDataUrl = is_string($body['image'] ?? null) ? $body['image'] : '';
    $fileName     = is_string($body['file_name'] ?? null) ? $body['file_name'] : '';
    $fileText     = is_string($body['file_text'] ?? null) ? $body['file_text'] : '';

    if (!is_string($rawMessage)) {
        sseSend('error', ['error' => 'Invalid message format.']);
        exit;
    }

    if (!is_array($history)) {
        $history = [];
    }

    // ---------------------------------------------------------------
    // 3. Validate & sanitize (identical rules to api.php)
    // ---------------------------------------------------------------
    $message = sanitizeInput($rawMessage);
    $maxLen  = $config['ai']['max_message_length'] ?? 2000;
    $hasAttachment = ($imageDataUrl !== '') || ($fileText !== '');

    // An empty typed message is fine as long as a file/image is attached
    // (e.g. the user just drops a picture and expects a description/edit).
    if ($message === '' && $hasAttachment) {
        $message = $imageDataUrl !== '' ? 'Please look at this image.' : 'Please read the attached file.';
    }

    $validation = validateMessage($message, $maxLen);

    if (!$validation['valid']) {
        sseSend('error', ['error' => $validation['error']]);
        exit;
    }

    $cleanHistory = [];
    foreach (array_slice($history, -10) as $turn) {
        if (!isset($turn['role'], $turn['content']) || !is_string($turn['content'])) {
            continue;
        }
        $cleanHistory[] = [
            'role'    => $turn['role'] === 'user' ? 'user' : 'assistant',
            'content' => sanitizeInput((string) $turn['content']),
        ];
    }

    // ---------------------------------------------------------------
    // 4. Resolve (or create) the conversation this message belongs to
    // ---------------------------------------------------------------
    if ($conversationId > 0 && !$chatRepo->ownsConversation($conversationId, $user['id'])) {
        sseSend('error', ['error' => 'Conversation not found.']);
        exit;
    }

    if ($conversationId === 0) {
        $conversationId = $chatRepo->createConversation($user['id']);
    }

    // What gets *stored and shown* for the user's own bubble stays short,
    // even if a big file was attached — the raw file text is only used as
    // extra context sent to the AI, never saved verbatim in the database.
    $storedUserContent = $message;
    if ($imageDataUrl !== '') {
        $storedUserContent = '📎 [Image] ' . $message;
    } elseif ($fileText !== '') {
        $label = $fileName !== '' ? $fileName : 'file';
        $storedUserContent = "📎 [File: {$label}] " . $message;
    }

    $userMessageId = $chatRepo->addMessage($conversationId, 'user', $storedUserContent);
    $chatRepo->maybeSetTitleFromFirstMessage($conversationId, $storedUserContent);

    // Let the client know the id of the message it just sent *before* we
    // start talking to the AI provider — this is what powers the "cancel /
    // undo" button: if the user aborts mid-reply, the client can still ask
    // the server to delete that exact message even though no 'done' event
    // ever arrives.
    sseSend('start', [
        'user_message_id' => $userMessageId,
        'conversation_id' => $conversationId,
    ]);

    $aiService = new AIService($config);

    // ---------------------------------------------------------------
    // 5a. An image was attached — analyze or edit it (not a token stream).
    // ---------------------------------------------------------------
    if ($imageDataUrl !== '') {
        if (!preg_match('/^data:([^;]+);base64,(.+)$/s', $imageDataUrl, $m)) {
            sseSend('error', ['error' => 'Invalid image data.']);
            exit;
        }
        [$whole, $mimeType, $base64Payload] = $m;

        try {
            $result = $aiService->handleImageMessage($message, $base64Payload, $mimeType);
        } catch (Throwable $aiError) {
            logAppError('AIService image handling failed: ' . $aiError->getMessage());
            sseSend('error', ['error' => 'AI provider temporarily unavailable.']);
            exit;
        }

        if ($result['type'] === 'image') {
            $relativePath = saveGeneratedImage($result['content'], $result['mime'] ?? 'image/png');

            sseSend('image', [
                'b64'  => $result['content'],
                'mime' => $result['mime'] ?? 'image/png',
                'url'  => $relativePath,
            ]);

            $chatRepo->addMessage($conversationId, 'assistant', 'IMAGE::' . $relativePath, $result['provider']);
        } else {
            sseSend('chunk', ['text' => $result['content']]);
            $chatRepo->addMessage($conversationId, 'assistant', $result['content'], $result['provider']);
        }

        sseSend('done', [
            'id'              => generateMessageId(),
            'conversation_id' => $conversationId,
            'provider'        => $result['provider'],
            'demo'            => false,
            'timestamp'       => date(DATE_ATOM),
        ]);
        exit;
    }

    // ---------------------------------------------------------------
    // 5b. A plain-text file was attached — fold its content into the
    //     prompt sent to the AI, without bloating the saved chat history.
    // ---------------------------------------------------------------
    $promptForAI = $message;
    if ($fileText !== '') {
        $trimmedFileText = mb_substr($fileText, 0, 8000);
        $label = $fileName !== '' ? $fileName : 'attached file';
        $promptForAI = $message . "\n\n[Content of {$label}]\n---\n{$trimmedFileText}\n---";
    }

    // ---------------------------------------------------------------
    // 5c. Normal text streaming, forwarding every delta to the browser
    // ---------------------------------------------------------------
    try {
        $result = $aiService->streamResponse($promptForAI, $cleanHistory, function (string $delta) {
            sseSend('chunk', ['text' => $delta]);
        });
    } catch (Throwable $aiError) {
        logAppError('AIService streaming failed: ' . $aiError->getMessage());
        sseSend('error', ['error' => 'AI provider temporarily unavailable.']);
        exit;
    }

    $chatRepo->addMessage($conversationId, 'assistant', $result['reply'], $result['provider']);

    // ---------------------------------------------------------------
    // 6. Final event — everything the client needs to update its state
    // ---------------------------------------------------------------
    sseSend('done', [
        'id'              => generateMessageId(),
        'conversation_id' => $conversationId,
        'provider'        => $result['provider'],
        'demo'            => $result['demo'],
        'timestamp'       => date(DATE_ATOM),
    ]);
} catch (Throwable $error) {
    logAppError('Stream request failed: ' . $error->getMessage());

    $isDev = ($config['app']['env'] ?? 'production') !== 'production';

    sseSend('error', [
        'error' => $isDev ? $error->getMessage() : 'AI provider temporarily unavailable.',
    ]);
}

exit;