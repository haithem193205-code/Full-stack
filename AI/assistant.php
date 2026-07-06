<?php

/**
 * ================================================================
 *  assistant.php — Chat Interface
 * ================================================================
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
$auth->requireLogin('login.php');

$activePage = 'chat';
$user = $auth->user();

$chatRepo = new ChatRepository($config);
$conversations = $chatRepo->listConversations($user['id']);

// If an id is passed and belongs to this user, preload its messages.
$activeConversationId = 0;
$initialMessages = [];

if (isset($_GET['id']) && ctype_digit((string) $_GET['id'])) {
  $requestedId = (int) $_GET['id'];
  if ($chatRepo->ownsConversation($requestedId, $user['id'])) {
    $activeConversationId = $requestedId;
    $initialMessages = $chatRepo->getMessages($requestedId);
  }
}

require __DIR__ . '/header.php';
?>

<main class="chat-page">
  <div class="chat-shell">

    <!-- ============ Sidebar: conversation history ============ -->
    <aside class="chat-sidebar" id="chatSidebar">
      <button class="new-chat-btn" id="newChatBtn" type="button">
        <i class="fa-solid fa-plus"></i> <span data-i18n="chat.newChat">New chat</span>
      </button>

      <div class="conversation-list" id="conversationList">
        <?php foreach ($conversations as $conv): ?>
          <div class="conversation-item<?= $conv['id'] == $activeConversationId ? ' active' : '' ?>" data-id="<?= (int) $conv['id'] ?>">
            <i class="fa-solid fa-message"></i>
            <span class="conversation-title"><?= htmlspecialchars($conv['title']) ?></span>
            <button class="conversation-delete" title="Delete" data-i18n-title="chat.delete" data-id="<?= (int) $conv['id'] ?>">
              <i class="fa-solid fa-trash-can"></i>
            </button>
          </div>
        <?php endforeach; ?>
        <?php if (empty($conversations)): ?>
          <p class="conversation-empty" data-i18n="chat.noConversations">No conversations yet — say hello!</p>
        <?php endif; ?>
      </div>
    </aside>

    <div class="chat-container glass-card">

      <!-- ============ Chat Header ============ -->
      <div class="chat-header">
        <div class="chat-header-left">
          <button class="icon-btn sidebar-toggle" id="sidebarToggle" title="Toggle history">
            <i class="fa-solid fa-bars"></i>
          </button>
          <div class="ai-avatar">
            <i class="fa-solid fa-robot"></i>
            <span class="avatar-status"></span>
          </div>
          <div class="chat-header-info">
            <h2><?= htmlspecialchars(APP_NAME) ?></h2>
            <span class="chat-status" id="chatStatus">
              <span class="status-dot"></span>
              <span data-i18n="chat.online">Online — ready to help</span>
            </span>
          </div>
        </div>
        <div class="chat-header-actions">
          <button class="icon-btn" id="clearChatBtn" title="Delete this conversation" data-i18n-title="chat.deleteConversation">
            <i class="fa-solid fa-trash-can"></i>
          </button>
        </div>
      </div>

      <!-- ============ Scrollable Chat Area ============ -->
      <div class="chat-messages" id="chatMessages">

        <?php if (empty($initialMessages)): ?>
          <div class="message message-ai">
            <div class="message-avatar">
              <i class="fa-solid fa-robot"></i>
            </div>
            <div class="message-body">
              <div class="message-bubble">
                <p data-i18n="chat.greeting" data-i18n-vars='{"name":"<?= htmlspecialchars($user['name'], ENT_QUOTES) ?>"}'>👋 Hi <?= htmlspecialchars($user['name']) ?>! I'm your AI Assistant. Ask me anything — from quick questions to deep dives, I'm here to help.</p>
              </div>
              <span class="message-time"><?= date('h:i A') ?></span>
            </div>
          </div>
        <?php else: ?>
          <?php foreach ($initialMessages as $m): ?>
            <div class="message message-<?= $m['role'] === 'user' ? 'user' : 'ai' ?>" data-message-id="<?= (int) $m['id'] ?>">
              <div class="message-avatar">
                <i class="fa-solid <?= $m['role'] === 'user' ? 'fa-user' : 'fa-robot' ?>"></i>
              </div>
              <div class="message-body">
                <?php if (str_starts_with($m['content'], 'IMAGE::')): ?>
                  <div class="message-bubble message-bubble-image">
                    <img src="<?= htmlspecialchars(substr($m['content'], 7)) ?>" alt="AI generated image" loading="lazy">
                  </div>
                <?php else: ?>
                  <div class="message-bubble"><?= nl2br(htmlspecialchars($m['content'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) ?></div>
                <?php endif; ?>
                <span class="message-time"><?= date('h:i A', strtotime($m['created_at'])) ?></span>
              </div>
              <button type="button" class="message-delete-btn" data-message-id="<?= (int) $m['id'] ?>" title="Delete this message" data-i18n-title="chat.deleteMessage">
                <i class="fa-solid fa-trash-can"></i>
              </button>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

      </div>

      <!-- ============ Typing Indicator (hidden by default) ============ -->
      <div class="typing-indicator" id="typingIndicator" hidden>
        <div class="message-avatar">
          <i class="fa-solid fa-robot"></i>
        </div>
        <div class="typing-dots">
          <span></span><span></span><span></span>
        </div>
      </div>

      <!-- ============ Attachment preview (image / file chip) ============ -->
      <div class="attachment-preview" id="attachmentPreview" hidden>
        <img id="attachmentThumb" alt="" hidden>
        <i class="fa-solid fa-file-lines" id="attachmentFileIcon" hidden></i>
        <span class="attachment-name" id="attachmentName"></span>
        <button type="button" class="attachment-remove" id="attachmentRemove" title="Remove attachment" data-i18n-title="chat.removeAttachment">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>

      <!-- ============ Input Area ============ -->
      <form class="chat-input-area" id="chatForm" autocomplete="off" data-conversation-id="<?= $activeConversationId ?>">
        <input type="file" id="fileInput" hidden accept="image/*,.txt,.md,.csv,.json,.log">

        <button type="button" class="icon-btn" id="attachBtn" title="Attach a file or image" data-i18n-title="chat.attach">
          <i class="fa-solid fa-paperclip"></i>
        </button>

        <textarea
          id="messageInput"
          class="chat-input"
          placeholder="Message AI Assistant..."
          data-i18n-placeholder="chat.inputPlaceholder"
          rows="1"
          maxlength="2000"
          aria-label="Type your message"></textarea>

        <button type="button" class="icon-btn stop-btn" id="stopBtn" title="Stop / cancel this message" data-i18n-title="chat.stop" hidden>
          <i class="fa-solid fa-stop"></i>
        </button>

        <button type="submit" class="send-btn" id="sendBtn" title="Send message" data-i18n-title="chat.send">
          <i class="fa-solid fa-paper-plane"></i>
          <span class="spinner" hidden></span>
        </button>
      </form>

      <p class="chat-disclaimer" data-i18n="chat.disclaimer">AI Assistant can make mistakes. Consider checking important information.</p>
    </div>
  </div>
</main>

<script id="initialMessages" type="application/json">
  <?= json_encode(array_map(
    fn($m) => ['id' => (int) $m['id'], 'role' => $m['role'], 'content' => $m['content']],
    $initialMessages
  ), JSON_UNESCAPED_UNICODE) ?>
</script>

<?php require __DIR__ . '/footer.php'; ?>