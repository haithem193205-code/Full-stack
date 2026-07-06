/**
 * ================================================================
 *  AI Assistant — Front-end Application Logic
 * ================================================================
 *  Modern ES6, no frameworks. Organized into small, single-purpose
 *  functions grouped by concern: DOM refs, rendering, networking,
 *  and UI micro-interactions (toasts, mobile nav).
 * ================================================================
 */

(() => {
    'use strict';

    // ------------------------------------------------------------
    // DOM References (guarded — chat elements only exist on assistant.php)
    // ------------------------------------------------------------
    const chatForm       = document.getElementById('chatForm');
    const messageInput   = document.getElementById('messageInput');
    const sendBtn        = document.getElementById('sendBtn');
    const stopBtn         = document.getElementById('stopBtn');
    const chatMessages    = document.getElementById('chatMessages');
    const typingIndicator = document.getElementById('typingIndicator');
    const clearChatBtn    = document.getElementById('clearChatBtn');
    const chatStatus       = document.getElementById('chatStatus');

    // Attachments (paperclip button → real file/image upload)
    const attachBtn          = document.getElementById('attachBtn');
    const fileInput           = document.getElementById('fileInput');
    const attachmentPreview   = document.getElementById('attachmentPreview');
    const attachmentThumb     = document.getElementById('attachmentThumb');
    const attachmentFileIcon  = document.getElementById('attachmentFileIcon');
    const attachmentName      = document.getElementById('attachmentName');
    const attachmentRemove    = document.getElementById('attachmentRemove');

    const MAX_ATTACHMENT_BYTES = 5 * 1024 * 1024; // 5 MB
    const TEXT_FILE_PATTERN = /\.(txt|md|csv|json|log)$/i;

    // Small i18n helper — falls back to the raw key if lang.js hasn't
    // loaded yet for some reason, so the UI never shows "undefined".
    function t(key, vars) {
        return window.FLTH_I18N ? window.FLTH_I18N.t(key, vars) : key;
    }

    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const navLinks      = document.getElementById('navLinks');
    const themeToggle    = document.getElementById('themeToggle');

    const toastContainer = document.getElementById('toastContainer');

    // Account menu (present when the user is logged in)
    const userMenuTrigger  = document.getElementById('userMenuTrigger');
    const userMenuDropdown = document.getElementById('userMenuDropdown');

    // Chat sidebar (conversation history)
    const chatSidebar      = document.getElementById('chatSidebar');
    const sidebarToggle    = document.getElementById('sidebarToggle');
    const newChatBtn       = document.getElementById('newChatBtn');
    const conversationList = document.getElementById('conversationList');

    // Endpoints for the PHP backend
    const API_ENDPOINT           = 'api.php';
    const STREAM_ENDPOINT        = 'stream.php';
    const CONVERSATIONS_ENDPOINT = 'conversations.php';

    // In-memory conversation history (sent to backend for context).
    // Each entry: { id: number|null, role: 'user'|'assistant', content: string }
    let conversationHistory = [];
    let isWaitingForReply = false;
    let activeConversationId = chatForm ? Number(chatForm.dataset.conversationId || 0) : 0;

    // The file/image currently attached but not sent yet.
    // { kind: 'image', dataUrl, mime, name } | { kind: 'text', fileText, name } | null
    let pendingAttachment = null;

    // Tracks the in-flight request so the Stop button can cancel it.
    let currentAbortController = null;

    // ------------------------------------------------------------
    // Init
    // ------------------------------------------------------------
    function init() {
        bindMobileNav();
        bindToastDemo();
        bindUserMenu();
        bindThemeToggle();

        if (chatForm) {
            bindChatEvents();
            bindSidebarEvents();
            autoResizeTextarea();
            hydrateInitialConversation();
        }
    }

    /**
     * Seed conversationHistory from the server-rendered messages so the
     * AI has context immediately, without an extra network round-trip.
     */
    function hydrateInitialConversation() {
        const script = document.getElementById('initialMessages');
        if (!script) return;

        try {
            const data = JSON.parse(script.textContent || '[]');
            conversationHistory = Array.isArray(data) ? data : [];
        } catch (err) {
            conversationHistory = [];
        }
    }

    /** Remove an entry from conversationHistory by its database message id. */
    function removeFromHistoryById(messageId) {
        conversationHistory = conversationHistory.filter((m) => Number(m.id) !== Number(messageId));
    }

    // ------------------------------------------------------------
    // Account Menu Toggle
    // ------------------------------------------------------------
    function bindUserMenu() {
        if (!userMenuTrigger || !userMenuDropdown) return;

        userMenuTrigger.addEventListener('click', (e) => {
            e.stopPropagation();
            userMenuDropdown.classList.toggle('open');
        });

        document.addEventListener('click', () => {
            userMenuDropdown.classList.remove('open');
        });
    }

    // ------------------------------------------------------------
    // Mobile Nav Toggle
    // ------------------------------------------------------------
    function bindMobileNav() {
        if (!hamburgerBtn || !navLinks) return;

        hamburgerBtn.addEventListener('click', () => {
            navLinks.classList.toggle('open');
            const icon = hamburgerBtn.querySelector('i');
            icon.classList.toggle('fa-bars');
            icon.classList.toggle('fa-xmark');
        });
    }

    // Placeholder hook in case future pages want a demo toast trigger
    function bindToastDemo() {
        // No-op by default — showToast() is exposed for use elsewhere.
    }

    // ------------------------------------------------------------
    // Theme Toggle (dark / light)
    // ------------------------------------------------------------
    const THEME_STORAGE_KEY = 'flth_theme';

    function bindThemeToggle() {
        if (!themeToggle) return;

        const icon = themeToggle.querySelector('i');

        function currentTheme() {
            return document.documentElement.getAttribute('data-theme') || 'dark';
        }

        function updateIcon() {
            if (!icon) return;
            // Icon shows the theme you'd switch TO.
            icon.className = currentTheme() === 'light' ? 'fa-solid fa-moon' : 'fa-solid fa-sun';
        }

        updateIcon();

        themeToggle.addEventListener('click', () => {
            const next = currentTheme() === 'light' ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', next);
            localStorage.setItem(THEME_STORAGE_KEY, next);
            updateIcon();
        });
    }

    // ================================================================
    //  CHAT FUNCTIONALITY
    // ================================================================

    function bindChatEvents() {
        chatForm.addEventListener('submit', handleSubmit);

        // Enter to send, Shift+Enter for newline
        messageInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                chatForm.requestSubmit();
            }
        });

        messageInput.addEventListener('input', autoResizeTextarea);

        if (clearChatBtn) {
            clearChatBtn.addEventListener('click', clearConversation);
        }

        if (stopBtn) {
            stopBtn.addEventListener('click', cancelInFlightMessage);
        }

        bindAttachmentEvents();

        // One delegated listener handles the delete ("undo") button on
        // every message bubble, past and future.
        chatMessages.addEventListener('click', handleMessageDeleteClick);
    }

    // ================================================================
    //  ATTACHMENTS — real file/image upload
    // ================================================================

    function bindAttachmentEvents() {
        if (!attachBtn || !fileInput) return;

        attachBtn.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', handleFileSelected);

        if (attachmentRemove) {
            attachmentRemove.addEventListener('click', clearAttachment);
        }
    }

    function handleFileSelected() {
        const file = fileInput.files && fileInput.files[0];
        if (!file) return;

        if (file.size > MAX_ATTACHMENT_BYTES) {
            showToast('error', t('chat.toastFileTooLarge'));
            fileInput.value = '';
            return;
        }

        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = () => {
                pendingAttachment = { kind: 'image', dataUrl: reader.result, mime: file.type, name: file.name };
                showAttachmentPreview({ imageSrc: reader.result, name: file.name });
            };
            reader.readAsDataURL(file);
            return;
        }

        const looksLikeText = file.type.startsWith('text/') || TEXT_FILE_PATTERN.test(file.name);
        if (looksLikeText) {
            const reader = new FileReader();
            reader.onload = () => {
                pendingAttachment = { kind: 'text', fileText: String(reader.result || ''), name: file.name };
                showAttachmentPreview({ name: file.name });
            };
            reader.readAsText(file);
            return;
        }

        // PDFs, Word docs, etc. — we don't parse these yet, so be upfront about it.
        showToast('error', t('chat.toastUnsupportedFile'));
        fileInput.value = '';
    }

    function showAttachmentPreview({ imageSrc, name }) {
        if (!attachmentPreview) return;

        if (imageSrc) {
            attachmentThumb.src = imageSrc;
            attachmentThumb.hidden = false;
            attachmentFileIcon.hidden = true;
        } else {
            attachmentThumb.hidden = true;
            attachmentFileIcon.hidden = false;
        }

        attachmentName.textContent = name;
        attachmentPreview.hidden = false;
    }

    function clearAttachment() {
        pendingAttachment = null;
        fileInput.value = '';
        if (attachmentPreview) attachmentPreview.hidden = true;
        if (attachmentThumb) { attachmentThumb.hidden = true; attachmentThumb.src = ''; }
    }

    /**
     * Auto-grow the textarea as the user types, capped by CSS max-height.
     */
    function autoResizeTextarea() {
        messageInput.style.height = 'auto';
        messageInput.style.height = `${messageInput.scrollHeight}px`;
    }

    /**
     * Handle the chat form submission.
     * @param {SubmitEvent} e
     */
    async function handleSubmit(e) {
        e.preventDefault();

        const text = messageInput.value.trim();
        const attachment = pendingAttachment;
        if ((!text && !attachment) || isWaitingForReply) return;

        const displayText = text || (attachment && attachment.kind === 'image' ? '📎 Image' : `📎 ${attachment.name}`);
        const userWrapper = appendMessage('user', displayText);
        const historyEntry = { id: null, role: 'user', content: displayText };
        conversationHistory.push(historyEntry);

        messageInput.value = '';
        autoResizeTextarea();
        clearAttachment();

        await requestAIReply(text, attachment, userWrapper, historyEntry);
    }

    /** Cancels whatever request is currently in flight, if any. */
    function cancelInFlightMessage() {
        if (currentAbortController) {
            currentAbortController.abort();
        }
    }

    /**
     * Send the message (plus an optional image/file attachment) to the
     * backend and stream the AI's reply into the chat as it arrives.
     *
     * A Stop button lets the user cancel mid-flight — if they sent
     * something by mistake, aborting removes both bubbles from the screen
     * and asks the server to delete the saved user message too.
     *
     * @param {string} text
     * @param {object|null} attachment
     * @param {HTMLElement} userWrapper The DOM node for the user's bubble.
     * @param {object} historyEntry The conversationHistory entry for this message.
     */
    async function requestAIReply(text, attachment, userWrapper, historyEntry) {
        setLoadingState(true);
        showTypingIndicator(true);

        const aiBubble = createAIMessagePlaceholder();
        let fullReply = '';
        let firstChunkReceived = false;
        let wasAborted = false;

        currentAbortController = new AbortController();

        const requestBody = {
            message: text,
            history: conversationHistory.slice(-10).filter((m) => m !== historyEntry || true),
            conversation_id: activeConversationId || 0,
        };

        if (attachment && attachment.kind === 'image') {
            requestBody.image = attachment.dataUrl;
        } else if (attachment && attachment.kind === 'text') {
            requestBody.file_name = attachment.name;
            requestBody.file_text = attachment.fileText;
        }

        try {
            const response = await fetch(STREAM_ENDPOINT, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(requestBody),
                signal: currentAbortController.signal,
            });

            if (response.status === 401) {
                window.location.href = 'login.php';
                return;
            }

            if (!response.ok || !response.body) {
                throw new Error(t('chat.toastGenericError'));
            }

            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';
            let doneData = null;
            let errorMessage = null;

            while (true) {
                const { value, done } = await reader.read();
                if (done) break;

                buffer += decoder.decode(value, { stream: true });

                let sepIndex;
                while ((sepIndex = buffer.indexOf('\n\n')) !== -1) {
                    const rawEvent = buffer.slice(0, sepIndex);
                    buffer = buffer.slice(sepIndex + 2);

                    const { event, data } = parseSSEEvent(rawEvent);
                    if (!event) continue;

                    if (event === 'start' && data.user_message_id) {
                        // The server has saved the user's message — remember its
                        // id so the trash/undo button and Stop-cancel can target it.
                        historyEntry.id = data.user_message_id;
                        userWrapper.dataset.messageId = data.user_message_id;
                        const delBtn = userWrapper.querySelector('.message-delete-btn');
                        if (delBtn) delBtn.dataset.messageId = data.user_message_id;
                    } else if (event === 'chunk' && typeof data.text === 'string') {
                        if (!firstChunkReceived) {
                            firstChunkReceived = true;
                            showTypingIndicator(false);
                        }
                        fullReply += data.text;
                        aiBubble.textContent = fullReply;
                        scrollToBottom();
                    } else if (event === 'image' && data.url) {
                        showTypingIndicator(false);
                        renderImageIntoBubble(aiBubble, data.url);
                        fullReply = 'IMAGE::' + data.url;
                    } else if (event === 'done') {
                        doneData = data;
                    } else if (event === 'error') {
                        errorMessage = data.error || t('chat.toastGenericError');
                    }
                }
            }

            if (errorMessage && !fullReply) {
                throw new Error(errorMessage);
            }

            if (!fullReply) {
                throw new Error(t('chat.toastGenericError'));
            }

            conversationHistory.push({ id: doneData?.assistant_message_id ?? null, role: 'assistant', content: fullReply });

            if (doneData) {
                const aiWrapper = aiBubble.closest('.message');
                if (aiWrapper && doneData.assistant_message_id) {
                    aiWrapper.dataset.messageId = doneData.assistant_message_id;
                    const delBtn = aiWrapper.querySelector('.message-delete-btn');
                    if (delBtn) delBtn.dataset.messageId = doneData.assistant_message_id;
                }

                // First message in a brand-new chat: remember the id, refresh the sidebar.
                if (!activeConversationId && doneData.conversation_id) {
                    activeConversationId = doneData.conversation_id;
                    chatForm.dataset.conversationId = String(activeConversationId);
                    history.replaceState(null, '', `assistant.php?id=${activeConversationId}`);
                }
                refreshConversationList(activeConversationId);

                if (doneData.demo) {
                    updateStatusPill(t('chat.demoMode'));
                }
            }
        } catch (err) {
            if (err && err.name === 'AbortError') {
                wasAborted = true;
                // The user hit Stop — remove both bubbles and clean up the
                // message we already saved server-side (if we know its id).
                aiBubble.closest('.message')?.remove();
                userWrapper.remove();
                removeFromHistoryById(historyEntry.id);
                if (historyEntry.id) {
                    deleteMessageById(historyEntry.id, { silent: true });
                }
                showToast('success', t('chat.toastCancelled'));
            } else if (fullReply) {
                // We already streamed part of a reply — keep it visible as-is
                // rather than discarding it, the interruption note (if any)
                // was already appended server-side.
                conversationHistory.push({ id: null, role: 'assistant', content: fullReply });
            } else {
                aiBubble.closest('.message')?.remove();
                appendMessage('ai', err.message || t('chat.toastGenericError'), true);
                showToast('error', err.message || t('chat.toastSendFailed'));
            }
        } finally {
            currentAbortController = null;
            setLoadingState(false);
            showTypingIndicator(false);
        }
    }

    /** Injects an <img> into a streaming AI bubble once an image reply arrives. */
    function renderImageIntoBubble(bubbleEl, url) {
        bubbleEl.textContent = '';
        bubbleEl.classList.add('message-bubble-image');
        const img = document.createElement('img');
        img.src = url;
        img.alt = 'AI generated image';
        img.loading = 'lazy';
        bubbleEl.appendChild(img);
        scrollToBottom();
    }

    /**
     * Create an empty AI message bubble to be filled in as stream chunks
     * arrive, and return the bubble element itself.
     * @returns {HTMLElement}
     */
    function createAIMessagePlaceholder() {
        const wrapper = document.createElement('div');
        wrapper.className = 'message message-ai';

        wrapper.innerHTML = `
            <div class="message-avatar">
                <i class="fa-solid fa-robot"></i>
            </div>
            <div class="message-body">
                <div class="message-bubble"></div>
                <span class="message-time">${formatTime(new Date())}</span>
            </div>
            <button type="button" class="message-delete-btn" title="${t('chat.deleteMessage')}">
                <i class="fa-solid fa-trash-can"></i>
            </button>
        `;

        chatMessages.appendChild(wrapper);
        scrollToBottom();

        return wrapper.querySelector('.message-bubble');
    }

    /**
     * Parse one raw SSE event block (the part between two blank lines) into
     * its event name and JSON-decoded data payload.
     * @param {string} raw
     * @returns {{event: string|null, data: any}}
     */
    function parseSSEEvent(raw) {
        let event = null;
        const dataLines = [];

        raw.split('\n').forEach((line) => {
            if (line.startsWith('event:')) {
                event = line.slice(6).trim();
            } else if (line.startsWith('data:')) {
                dataLines.push(line.slice(5).trim());
            }
        });

        let data = {};
        try {
            data = JSON.parse(dataLines.join('\n'));
        } catch (err) {
            data = {};
        }

        return { event, data };
    }

    /**
     * Append a chat bubble to the messages area.
     * @param {'user'|'ai'} role
     * @param {string} text
     * @param {boolean} isError
     * @param {number|null} messageId
     * @returns {HTMLElement} the message wrapper element
     */
    function appendMessage(role, text, isError = false, messageId = null) {
        const wrapper = document.createElement('div');
        wrapper.className = `message message-${role === 'user' ? 'user' : 'ai'}${isError ? ' message-error' : ''}`;
        if (messageId) wrapper.dataset.messageId = messageId;

        wrapper.innerHTML = `
            <div class="message-avatar">
                <i class="fa-solid ${role === 'user' ? 'fa-user' : 'fa-robot'}"></i>
            </div>
            <div class="message-body">
                <div class="message-bubble"></div>
                <span class="message-time">${formatTime(new Date())}</span>
            </div>
            <button type="button" class="message-delete-btn" title="${t('chat.deleteMessage')}" ${messageId ? `data-message-id="${messageId}"` : ''}>
                <i class="fa-solid fa-trash-can"></i>
            </button>
        `;

        const bubble = wrapper.querySelector('.message-bubble');

        if (typeof text === 'string' && text.startsWith('IMAGE::')) {
            renderImageIntoBubble(bubble, text.slice(7));
        } else {
            // Set text via textContent to prevent HTML/script injection.
            bubble.textContent = text;
        }

        chatMessages.appendChild(wrapper);
        scrollToBottom();

        return wrapper;
    }

    /**
     * Show/hide the animated typing indicator.
     * @param {boolean} show
     */
    function showTypingIndicator(show) {
        if (!typingIndicator) return;
        typingIndicator.hidden = !show;
        if (show) scrollToBottom();
    }

    /**
     * Toggle the "waiting for AI" UI state (disable input/button, spinner).
     * @param {boolean} isLoading
     */
    function setLoadingState(isLoading) {
        isWaitingForReply = isLoading;
        sendBtn.disabled = isLoading;
        messageInput.disabled = isLoading;

        const icon = sendBtn.querySelector('i');
        const spinner = sendBtn.querySelector('.spinner');

        icon.hidden = isLoading;
        spinner.hidden = !isLoading;

        sendBtn.hidden = isLoading;
        if (stopBtn) stopBtn.hidden = !isLoading;

        if (!isLoading) messageInput.focus();
    }

    // ================================================================
    //  DELETE / UNDO A MESSAGE
    // ================================================================

    /**
     * Handles clicks on any ".message-delete-btn" (event delegation), so
     * this works for messages rendered by PHP on page load and for ones
     * appended later by JS alike. Lets someone remove a message they sent
     * by mistake — with a confirmation to avoid accidental taps.
     */
    function handleMessageDeleteClick(e) {
        const btn = e.target.closest('.message-delete-btn');
        if (!btn) return;

        const wrapper = btn.closest('.message');
        const messageId = Number(btn.dataset.messageId || wrapper?.dataset.messageId || 0);

        if (!messageId) {
            // Not saved yet (still streaming) — just remove it locally.
            wrapper?.remove();
            return;
        }

        if (!window.confirm(t('chat.confirmDeleteMessage'))) return;

        deleteMessageById(messageId).then((ok) => {
            if (ok) {
                wrapper?.remove();
                removeFromHistoryById(messageId);
            }
        });
    }

    /**
     * Deletes a single message on the server.
     * @param {number} messageId
     * @param {{silent?: boolean}} opts silent=true skips the success/error toasts
     * @returns {Promise<boolean>}
     */
    async function deleteMessageById(messageId, opts = {}) {
        try {
            const response = await fetch(CONVERSATIONS_ENDPOINT, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete_message', id: messageId }),
            });
            const payload = await response.json().catch(() => ({ success: response.ok }));

            if (payload.success && !opts.silent) {
                showToast('success', t('chat.toastMessageDeleted'));
            } else if (!payload.success && !opts.silent) {
                showToast('error', t('chat.toastMessageDeleteFailed'));
            }

            return Boolean(payload.success);
        } catch (err) {
            if (!opts.silent) showToast('error', t('chat.toastMessageDeleteFailed'));
            return false;
        }
    }

    /**
     * Smoothly scroll the chat area to the latest message.
     */
    function scrollToBottom() {
        requestAnimationFrame(() => {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        });
    }

    /**
     * Delete the currently open conversation (if any) and start fresh.
     */
    async function clearConversation() {
        if (activeConversationId) {
            try {
                await fetch(CONVERSATIONS_ENDPOINT, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete', id: activeConversationId }),
                });
            } catch (err) {
                // Non-fatal — we still reset the local view.
            }
        }

        startNewChat();
        refreshConversationList(0);
        showToast('success', t('chat.toastConversationDeleted'));
    }

    /**
     * Reset the chat window to a blank, unsaved conversation.
     */
    function startNewChat() {
        activeConversationId = 0;
        conversationHistory = [];
        chatForm.dataset.conversationId = '0';
        chatMessages.innerHTML = '';
        history.replaceState(null, '', 'assistant.php');
        clearAttachment();

        appendMessage('ai', t('chat.newChatGreeting'));
    }

    // ================================================================
    //  CONVERSATION SIDEBAR
    // ================================================================

    function bindSidebarEvents() {
        if (sidebarToggle && chatSidebar) {
            sidebarToggle.addEventListener('click', () => chatSidebar.classList.toggle('open'));
        }

        if (newChatBtn) {
            newChatBtn.addEventListener('click', () => {
                startNewChat();
                refreshConversationList(0);
                if (chatSidebar) chatSidebar.classList.remove('open');
            });
        }

        if (conversationList) {
            conversationList.addEventListener('click', handleConversationListClick);
        }
    }

    async function handleConversationListClick(e) {
        const deleteBtn = e.target.closest('.conversation-delete');
        if (deleteBtn) {
            e.stopPropagation();
            const id = Number(deleteBtn.dataset.id);
            await deleteConversationById(id);
            return;
        }

        const item = e.target.closest('.conversation-item');
        if (item) {
            const id = Number(item.dataset.id);
            await loadConversation(id);
            if (chatSidebar) chatSidebar.classList.remove('open');
        }
    }

    async function deleteConversationById(id) {
        try {
            await fetch(CONVERSATIONS_ENDPOINT, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', id }),
            });

            if (id === activeConversationId) {
                startNewChat();
            }

            refreshConversationList(activeConversationId);
            showToast('success', t('chat.toastConversationDeleted'));
        } catch (err) {
            showToast('error', t('chat.toastConversationDeleteFailed'));
        }
    }

    async function loadConversation(id) {
        try {
            const response = await fetch(`${CONVERSATIONS_ENDPOINT}?action=messages&id=${id}`);

            if (response.status === 401) {
                window.location.href = 'login.php';
                return;
            }

            const payload = await response.json();
            if (!payload.success) throw new Error(payload.error || t('chat.toastLoadFailed'));

            activeConversationId = id;
            chatForm.dataset.conversationId = String(id);
            history.replaceState(null, '', `assistant.php?id=${id}`);
            clearAttachment();

            conversationHistory = payload.data.map((m) => ({ id: m.id, role: m.role, content: m.content }));
            chatMessages.innerHTML = '';

            if (payload.data.length === 0) {
                appendMessage('ai', t('chat.loadedGreeting'));
            } else {
                payload.data.forEach((m) => appendMessage(m.role === 'user' ? 'user' : 'ai', m.content, false, m.id));
            }

            refreshConversationList(id);
        } catch (err) {
            showToast('error', err.message || t('chat.toastLoadFailed'));
        }
    }

    async function refreshConversationList(activeId) {
        if (!conversationList) return;

        try {
            const response = await fetch(`${CONVERSATIONS_ENDPOINT}?action=list`);
            if (!response.ok) return;

            const payload = await response.json();
            if (!payload.success) return;

            conversationList.innerHTML = '';

            if (payload.data.length === 0) {
                const empty = document.createElement('p');
                empty.className = 'conversation-empty';
                empty.textContent = t('chat.noConversations');
                conversationList.appendChild(empty);
                return;
            }

            payload.data.forEach((conv) => {
                const item = document.createElement('div');
                item.className = `conversation-item${Number(conv.id) === Number(activeId) ? ' active' : ''}`;
                item.dataset.id = conv.id;
                item.innerHTML = `
                    <i class="fa-solid fa-message"></i>
                    <span class="conversation-title"></span>
                    <button class="conversation-delete" title="${t('chat.delete')}" data-id="${conv.id}">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                `;
                item.querySelector('.conversation-title').textContent = conv.title;
                conversationList.appendChild(item);
            });
        } catch (err) {
            // Silent — sidebar refresh is best-effort.
        }
    }

    /**
     * Update the small status label in the chat header (e.g. demo mode).
     * @param {string} label
     */
    function updateStatusPill(label) {
        if (!chatStatus) return;
        chatStatus.innerHTML = `<span class="status-dot"></span> ${label}`;
    }

    /**
     * Format a Date object as a friendly local time string.
     * @param {Date} date
     * @returns {string}
     */
    function formatTime(date) {
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    // ================================================================
    //  TOAST NOTIFICATIONS
    // ================================================================

    /**
     * Show a toast notification.
     * @param {'success'|'error'} type
     * @param {string} message
     */
    function showToast(type, message) {
        if (!toastContainer) return;

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <i class="fa-solid ${type === 'success' ? 'fa-circle-check toast-icon-success' : 'fa-circle-exclamation toast-icon-error'}"></i>
            <span></span>
        `;
        toast.querySelector('span').textContent = message;

        toastContainer.appendChild(toast);

        setTimeout(() => {
            toast.classList.add('toast-out');
            toast.addEventListener('animationend', () => toast.remove());
        }, 3500);
    }

    // Expose showToast globally for reuse across pages if needed.
    window.AIAssistant = { showToast };

    // ------------------------------------------------------------
    document.addEventListener('DOMContentLoaded', init);
})();