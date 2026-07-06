# Accounts, Login, Password Reset & Saved Chat History

This adds a full authentication layer on top of your existing app: sign up,
log in, forgot/reset password by email, and every conversation is now saved
per-user with a chat-history sidebar (like ChatGPT).

## ⚠️ Rotate your API keys first

Your uploaded `.env` file contains **live, working API keys** (Gemini, Claude,
and an Ollama cloud key) in plain text. Since this file was shared in this
conversation, treat those keys as compromised:

1. Revoke/regenerate the Gemini key in Google AI Studio.
2. Revoke/regenerate the Claude key in the Anthropic Console.
3. Revoke/regenerate the Ollama cloud key.
4. Put the new keys only in the real `.env` file on your server — never
   commit or paste it anywhere public again.

## 1. New files

```
Database.php          PDO connection (singleton)
Auth.php               register / login / logout / password reset
Mailer.php              sends the reset email (SMTP, no external library needed)
ChatRepository.php      saves & loads conversations/messages per user
bootstrap.php           loaded by every page: config + session + services
session_bootstrap.php    hardened session start
register.php / login.php / logout.php
forgot-password.php / reset-password.php
conversations.php       AJAX endpoint: list / load / rename / delete chats
sql/schema.sql          run this once against your database
```

## 2. Modified files

- `config.php` — added `db` and `mail` config blocks (reads from `.env`).
- `functions.php` — added CSRF helpers, flash messages, reset-email template.
- `header.php` — shows Login/Sign up, or an account menu when logged in.
- `index.php`, `assisstant.php` — now load through `bootstrap.php`.
- `assisstant.php` — requires login, adds the conversation-history sidebar.
- `api.php` — now requires login and saves every message to the database.
- `app.js` — sidebar behaviour, account menu, sends `conversation_id`.
- `style.css` — styles for the sidebar, account menu, and auth pages.

## 3. Install

1. **Create the database tables:**
   ```bash
   mysql -u your_db_user -p your_db_name < sql/schema.sql
   ```

2. **Add environment variables** — copy the contents of
   `.env.additions.txt` into your real `.env` file and fill in your
   actual DB credentials and SMTP details.

   For Gmail SMTP, use an **App Password** (not your normal password):
   Google Account → Security → 2-Step Verification → App passwords.
   Any other SMTP provider (Mailgun, SendGrid, Brevo, your host's own
   mail server, etc.) works the same way — just fill in host/port/user/pass.

   If you don't have SMTP available yet, set `MAIL_MAILER=mail` to fall
   back to PHP's native `mail()` function (works out of the box on many
   shared hosting providers, but less reliable for deliverability).

3. **Requirements on the server:** PHP 8.1+, the `pdo_mysql` and
   `mbstring` extensions (both are enabled by default on almost all
   hosting panels).

4. **HTTPS in production:** once `APP_ENV=production`, session cookies
   are marked `secure`, so the site must be served over HTTPS or logins
   won't persist.

## 4. How it works

- Passwords are hashed with `password_hash()` (bcrypt) — never stored
  in plain text.
- Password-reset tokens are random 32-byte values; only their SHA-256
  hash is stored in the database, they expire after 60 minutes, and
  each one can only be used once. Reset requests are also rate-limited
  (3 per hour per account) to prevent abuse.
- Every form (register, login, forgot/reset password) is protected by
  a CSRF token.
- `assisstant.php` now requires a logged-in session; guests are redirected to
  `login.php` and returned to the page they wanted afterward.
- Every chat message (yours and the AI's) is saved to `messages`,
  grouped under `conversations`. The sidebar lists your past chats,
  lets you rename... actually renaming isn't wired into the UI yet —
  the `conversations.php` endpoint supports a `rename` action if you'd
  like to add that button later, and `delete` is already wired up.

## 5. Testing locally without a mail server

If you don't want to configure SMTP while developing, set
`MAIL_MAILER=mail` — `forgot-password.php` will still work and log any
failures, but delivery depends on your local machine having `sendmail`
configured (most local dev environments don't). In that case, the
safest way to test the reset flow locally is to temporarily
`error_log()` the token/link from `forgot-password.php`, or point
`SMTP_HOST` at a tool like Mailtrap.io for a fake inbox during development.
