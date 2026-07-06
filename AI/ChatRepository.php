<?php

declare(strict_types=1);

/**
 * ================================================================
 *  ChatRepository — persists conversations & messages per user
 * ================================================================
 */
final class ChatRepository
{
    private PDO $db;

    public function __construct(array $config)
    {
        $this->db = Database::connection($config);
    }

    public function createConversation(int $userId, string $title = 'New chat'): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO conversations (user_id, title, created_at, updated_at)
             VALUES (?, ?, NOW(), NOW())'
        );
        $stmt->execute([$userId, $title]);

        return (int) $this->db->lastInsertId();
    }

    public function ownsConversation(int $conversationId, int $userId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT id FROM conversations WHERE id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$conversationId, $userId]);

        return (bool) $stmt->fetch();
    }

    public function listConversations(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, title, updated_at FROM conversations
             WHERE user_id = ? ORDER BY updated_at DESC LIMIT 200'
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    public function getMessages(int $conversationId): array
    {
        $stmt = $this->db->prepare(
            'SELECT role, content, created_at FROM messages
             WHERE conversation_id = ? ORDER BY id ASC'
        );
        $stmt->execute([$conversationId]);

        return $stmt->fetchAll();
    }

    public function addMessage(int $conversationId, string $role, string $content, ?string $provider = null): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO messages (conversation_id, role, content, provider, created_at)
             VALUES (?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$conversationId, $role, $content, $provider]);

        $messageId = (int) $this->db->lastInsertId();

        $this->db->prepare('UPDATE conversations SET updated_at = NOW() WHERE id = ?')
            ->execute([$conversationId]);

        return $messageId;
    }

    /**
     * Deletes a single message, but only if it belongs to a conversation
     * owned by this user (prevents deleting someone else's messages by id).
     */
    public function deleteMessage(int $messageId, int $userId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT m.id FROM messages m
             INNER JOIN conversations c ON c.id = m.conversation_id
             WHERE m.id = ? AND c.user_id = ? LIMIT 1'
        );
        $stmt->execute([$messageId, $userId]);

        if (!$stmt->fetch()) {
            return false;
        }

        $this->db->prepare('DELETE FROM messages WHERE id = ?')->execute([$messageId]);

        return true;
    }

    /** Auto-titles a fresh conversation from the first user message. */
    public function maybeSetTitleFromFirstMessage(int $conversationId, string $message): void
    {
        $stmt = $this->db->prepare('SELECT title FROM conversations WHERE id = ?');
        $stmt->execute([$conversationId]);
        $row = $stmt->fetch();

        if ($row && $row['title'] === 'New chat') {
            $title = mb_substr(trim($message), 0, 60);
            $this->db->prepare('UPDATE conversations SET title = ? WHERE id = ?')
                ->execute([$title !== '' ? $title : 'New chat', $conversationId]);
        }
    }

    public function renameConversation(int $conversationId, int $userId, string $title): bool
    {
        if (!$this->ownsConversation($conversationId, $userId)) {
            return false;
        }

        $title = mb_substr(trim($title), 0, 100);
        if ($title === '') {
            return false;
        }

        $this->db->prepare('UPDATE conversations SET title = ? WHERE id = ?')
            ->execute([$title, $conversationId]);

        return true;
    }

    public function deleteConversation(int $conversationId, int $userId): bool
    {
        if (!$this->ownsConversation($conversationId, $userId)) {
            return false;
        }

        $this->db->prepare('DELETE FROM conversations WHERE id = ?')->execute([$conversationId]);

        return true;
    }
}