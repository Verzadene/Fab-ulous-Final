# FABulous

A community platform for sharing 3D printed projects, built on PHP 8.2, MySQL, and Bootstrap 5.3.

**Live site:** https://thefablab.site/Fab-ulous  
**Repo:** https://github.com/Verzadene/Fab-ulous

---

## Project Structure

```text
Fab-ulous/
├── admin/                        # Admin-only pages and tools
│   ├── admin.php                 # Main admin dashboard (user mgmt, commissions fulfillment, audit log)
│   ├── admin_login.php           # Admin credential + MFA + reCAPTCHA entry point; redirects authenticated users to their dashboard
│   ├── admin_logout.php          # Session teardown for admin: writes audit log → destroys session → landing
│   ├── admin_login.css           # Admin login standalone styles
│   ├── admin.css                 # Admin dashboard styles
│   ├── AdminRepository.php       # DB abstraction: user banning, deletion, audit log, commission oversight
│   └── commission_update.php     # AJAX endpoint: update commission status/notes from dashboard
│
├── config.php                    # Single source of truth — all constants, DB factory, auth helpers, SMTP,
│                                 # reCAPTCHA helper (verify_recaptcha()), email domain whitelist
│                                 # (is_email_domain_allowed()), and all mail template functions
├── config.local.php              # Production overrides (gitignored): live DB credentials per domain,
│                                 # OAuth URIs, APP_URL, RECAPTCHA_SITE_KEY, RECAPTCHA_SECRET_KEY,
│                                 # ALLOWED_EMAIL_DOMAINS, PAYMONGO keys.
│                                 # ⚠️  Must define DB_CONFIG for ALL 12 domains before config.php loads.
│                                 #     On Hostinger, each entry uses its own dedicated MySQL user.
│                                 # ⚠️  NEVER commit this file — it is gitignored.
│
├── database/
│   ├── setup_micro_dbs.sql       # Canonical fresh install: creates all 12 micro-databases and their tables (use this)
│   ├── migration_messages_canonical.sql # Renames sender_id/receiver_id → senderID/receiverID in messages table (idempotent)
│   └── migration_messages_read_status.sql # Adds is_read column to messages table for unread count feature (idempotent)
│
├── documentation/
│   └── FABulous_ProjectDocs_v0.2.0.docx
│
├── images/
│   ├── Big Logo.png              # Large brand logo (left panel of auth pages)
│   ├── Top_Left_Nav_Logo.png     # Small nav logo — also used as the browser tab favicon on every page
│   └── source/                   # Original vector/source assets
│       ├── Green_Logo_Tab.png
│       └── Green_Logo_Top_left.png
│
├── includes/
│   └── app_nav.php               # Shared top nav + burger drawer + Help offcanvas (included by all authenticated pages)
│
├── landing/
│   ├── landing.html              # Public-facing landing page
│   └── landing.css
│
├── login/
│   ├── login.php                 # User login: reCAPTCHA check → credential check → MFA challenge → verify_mfa.php;
│                                 # redirects authenticated users
│   ├── login.css                 # Shared auth page styles (used by login, admin login, MFA, forgot-pw, reset-pw)
│   ├── auth_slider.js            # Shared auth-panel slide animation + bfcache Back-button fix
│                                 # (used by login.php, admin/admin_login.php, register/register.html)
│   ├── logout.php                # User session teardown: writes audit log → destroys session → landing
│   ├── verify_mfa.php            # Email MFA code entry; writes audit log on success; completes session after correct code
│   ├── verify_mfa.css            # MFA-specific style overrides
│   ├── forgot_password.php       # Request 6-digit reset code by email
│   └── reset_password.php        # Submit reset code + new password
│
├── oauth/
│   └── oauth2callback.php        # Receives Google OAuth redirect; wipes stale session; checks email domain whitelist;
│                                 # validates account ID > 0; calls begin_user_session();
│                                 # verifies session commit before writing audit log
│
├── post/
│   ├── post.php                  # Main authenticated feed (posts, friend actions, notifications)
│   ├── post.css                  # Feed page styles (also defines topnav for all post/ pages)
│   ├── post.html                 # Static shell / redirect shim
│   ├── feed_api.php              # GET: returns main feed as JSON
│   ├── create_post.php           # POST handler: create post with optional image upload (photos of 3D prints)
│   ├── edit_post.php             # POST handler: edit post caption (owner only)
│   ├── delete_post.php           # POST handler: delete post (owner only); writes self-deletion audit log entry
│   ├── like.php                  # POST handler: toggle like; returns updated count as JSON
│   ├── comment.php               # GET/POST: fetch or add comments for a post
│   ├── messages.php              # Messaging UI (requires messages table from v5)
│   ├── messages.css
│   ├── friends.php               # GET/POST API: friendship state machine and directory
│   ├── notifications.php         # GET/POST API: list notifications, count unread, mark as read
│   ├── messages_api.php          # GET/POST API: load conversation history and send messages
│   ├── PostRepository.php        # DB abstraction: Post data, likes, comments (reference implementation for Pattern B)
│   ├── FriendRepository.php      # DB abstraction: Friendships and requests (Pattern B)
│   ├── InteractionRepository.php # DB abstraction: Likes and comments (Pattern B; orphan — controllers use PostRepository)
│   ├── MessageRepository.php     # DB abstraction: Messaging, unread badge, mark-as-read (Pattern B)
│   ├── NotificationRepository.php# DB abstraction: Notification operations (Pattern B)
│   ├── CommissionRepository.php  # DB abstraction: Commission requests and updates (Pattern B — refactored)
│   ├── PaymentRepository.php     # DB abstraction: PayMongo payment lifecycle (pending → checkout → paid)
│   ├── commissions.php           # Commission submit (any role) + personal 3D print request tracking — client-facing only
│   ├── commissions.css
│   ├── paymongo_checkout.php     # POST handler: create PayMongo checkout session; redirect to payment URL
│   ├── paymongo_webhook.php      # POST handler: receive PayMongo webhook; update commission_payments
│   └── config.php                # ⚠️ Exact duplicate of root config.php — legacy include-path shim for post/ files.
│                                 #    Do not edit independently; keep in sync with root config.php, or
│                                 #    migrate all post/ files to require_once __DIR__ . '/../config.php'.
│
├── profile/
│   ├── profile.php               # View and edit account details, change password, upload profile pic
│   ├── profile.css
│   ├── ProfileRepository.php     # DB abstraction: profile reads, updates, password change, pic upload (accounts DB only)
│   └── profile_api.php           # GET/POST API: returns or updates profile data as JSON
│
├── register/
│   ├── register.html             # Registration form UI (includes reCAPTCHA widget)
│   ├── register.php              # POST handler: reCAPTCHA check → email domain whitelist check →
│                                 # validate → upsert pending_registrations → send verify email
│   ├── register.js               # Prefills form fields from Google OAuth prefill data
│   ├── register.css
│   ├── verify_registration.css   # Style overrides for the email verification page
│   ├── prefill.php               # GET: returns Google-prefilled data as JSON for register.js
│   └── verify_registration.php   # Accepts 6-digit code; moves pending → accounts on success
│
├── uploads/                      # User-generated content — gitignored
│   ├── profile_pics/             # Profile pictures (named by account ID)
│   ├── posts/                    # Post images
│   └── commissions/              # Commission attachments (STL, PDF, images)
│
├── auth_status.php               # GET: JSON session-status ping used by HTML pages for auth redirect
├── CLAUDE.md                     # AI assistant context and project guardrails
└── README.md                     # This file
```

---

## Architecture

FABulous is a **folder-modular monolith** running on Apache + PHP 8.2 + MySQL. There is no framework; each feature area owns its folder, its PHP files, and its CSS. All pages share one configuration entry point.

### Micro-Database Design — "1 Database · 1 Table · 1 MySQL User"

The application runs on **12 separate MySQL databases**. Each database contains exactly **one table** and is accessed by exactly **one dedicated MySQL user**. This is the hard constraint imposed by Hostinger Business Web Hosting and is the defining architectural fact of this codebase.

```
config.php  →  db_connect('domain')  →  one of 12 MySQL databases (Hostinger)
                                         ┌──────────────────────────────────────────────────────────┐
                                         │  Database (Hostinger name)        MySQL user              │
                                         ├──────────────────────────────────────────────────────────┤
                                         │  u934684110_fab_accounts          u934684110_fab_app_user  │
                                         │  u934684110_fab_posts             u934684110_fab_app_user2 │
                                         │  u934684110_fab_likes             u934684110_fab_app_user3 │
                                         │  u934684110_fab_comments          u934684110_fab_app_user4 │
                                         │  u934684110_fab_commission        u934684110_fab_app_user5 │
                                         │  u934684110_fab_comm_pays         u934684110_fab_app_user6 │
                                         │  u934684110_fab_friends           u934684110_fab_app_user7 │
                                         │  u934684110_fab_notifs            u934684110_fab_app_user8 │
                                         │  u934684110_fab_messages          u934684110_fab_app_user9 │
                                         │  u934684110_fab_pendings          u934684110_fab_app_user10│
                                         │  u934684110_fab_pw_resets         u934684110_fab_app_user11│
                                         │  u934684110_fab_audit_log         u934684110_fab_app_user12│
                                         └──────────────────────────────────────────────────────────┘
```

Each MySQL user is granted exactly: `GRANT SELECT, INSERT, UPDATE, DELETE ON <db_name>.* TO '<user>'@'localhost';`  
**No user has visibility into any other database.** Cross-database qualified syntax (`other_db.table_name`) fails silently — it returns zero rows rather than an error, which is the most dangerous class of production bug.

### Cross-Database Query Policy — Application-Level Aggregation

> **⚠️ Cross-database JOINs are strictly prohibited in all SQL queries.**

Because each MySQL user can only see its own database, queries like `FROM commissions JOIN fab_ulous_accounts.accounts` silently return zero rows on Hostinger. The connected user has no `SELECT` privilege on the foreign database.

**All cross-domain reads use Application-Level Aggregation:**

1. Fetch the primary rows from Database A through `db_connect('domain_a')`.
2. Collect the IDs you need from Database B.
3. Run a separate prepared statement against Database B through `db_connect('domain_b')`.
4. Merge the two result sets in PHP.

This pattern is implemented in every repository:

| Repository method | Databases crossed | Pattern |
|---|---|---|
| `PostRepository::getFeed()` | friendships → posts → accounts → likes → comments | Application-level aggregation |
| `PostRepository::getComments()` | comments → accounts | Application-level aggregation |
| `CommissionRepository::getAllCommissions()` | commissions → accounts → commission_payments | Application-level aggregation |
| `InteractionRepository::getComments()` | comments → accounts | Application-level aggregation |
| `FriendRepository::getFriendDirectory()` | accounts → friendships | Application-level aggregation |
| `MessageRepository::getContacts()` | accounts → friendships | Application-level aggregation |
| `MessageRepository::getConversation()` | messages → accounts | Application-level aggregation |
| `MessageRepository::getUnreadCount()` | messages (single-domain) | Single-domain — no aggregation needed |
| `MessageRepository::markThreadAsRead()` | messages → notifications | Application-level aggregation (two separate connections) |
| `NotificationRepository::getUnreadNotifications()` | notifications → accounts | Application-level aggregation |
| `AdminRepository::getAllPosts()` | posts → accounts → likes → comments | Application-level aggregation |
| `AdminRepository::searchAuditLogs()` | audit_log → accounts | Application-level aggregation |
| `AdminRepository::processPromoteToAdmin()` | accounts → audit_log | Application-level aggregation |
| `AdminRepository::processDemoteToUser()` | accounts → audit_log | Application-level aggregation |
| `PaymentRepository::getCommissionForPayment()` | commissions → accounts | Application-level aggregation (refactored from cross-DB JOIN) |

All cascading deletes (e.g. removing a user's posts, likes, friendships when the account is deleted) are handled explicitly in `AdminRepository::processDeleteUser()` — each domain is deleted in sequence via its own `db_connect()` call.

### Session & Audit Integrity

FABulous uses a three-layer defence against stale-session audit entries:

**Layer 1 — OAuth callback (`oauth/oauth2callback.php`)**
Clears all existing session keys before starting the OAuth token exchange.
Validates that the resolved account `id` is greater than zero before calling
`begin_user_session()`. After `begin_user_session()` regenerates the session
ID, reads the values back out of `$_SESSION` and only proceeds to write the
audit log if both `id > 0` and `username` are present.

**Layer 2 — Admin dashboard (`admin/admin.php`)**
Sends `Cache-Control: no-store` before any session read to prevent the
browser's Back-Forward Cache from replaying a stale page snapshot. The RBAC
guard checks for a valid role **and** `id > 0` **and** a non-empty username;
any failure destroys the session and redirects to the admin login page.

**Layer 3 — Repository (`admin/AdminRepository::logAuditAction()`)**
Acts as a last line of defence: if `$adminId` is 0 or negative, attempts to
recover from `$_SESSION`; if that also yields 0, emits an `error_log` entry
and returns without writing to the database. The audit trail can therefore
never contain a row with `admin_id = 0` under normal or Back-button conditions.

**Time Zone — Philippine Time (PHT / UTC+8)**
`config.php` calls `date_default_timezone_set('Asia/Manila')` as the global standard. `logAuditAction()` computes `$phtNow = date('Y-m-d H:i:s')` in PHP and supplies it explicitly as the `created_at` value in the `INSERT`, ensuring audit timestamps are always in PHT regardless of the MySQL server clock. Do not revert audit log timestamps to MySQL `NOW()`.

Every other direct `INSERT INTO audit_log` in the codebase (`verify_mfa.php`, `oauth2callback.php`, `logout.php`) follows the same rule: `created_at` is always supplied as an explicit PHP `date('Y-m-d H:i:s')` value, never omitted. Omitting it causes MySQL to fill the column from its UTC server clock, making the row invisible to the time-window filter.

**`visibility_role` — Role-Aware Audit Entries**
Every audit INSERT derives `visibility_role` from the acting user's actual role: `super_admin` actions get `visibility_role = 'super_admin'` (visible only to super admins), all other roles get `'admin'` (visible to all admins). This applies to all `AdminRepository` methods, `CommissionRepository::logAuditAction()`, and the inline INSERTs in `verify_mfa.php`, `oauth2callback.php`, and `logout.php`. Do not hardcode `'admin'` in any audit INSERT.

**Audit Log Time Filter**
`AdminRepository::searchAuditLogs()` computes the time-window cutoff in PHP (`$cutoff = date('Y-m-d H:i:s', time() - ($hours * 3600))`) and binds it as a string. `DATE_SUB(NOW(), INTERVAL ? HOUR)` is prohibited — MySQL's `NOW()` is UTC on Hostinger, so rows written in PHT would appear invisible.

**Code Expiry — PHP-Computed Datetime Strings**  
All time-sensitive verification codes (MFA, registration, password reset) compute their expiry in PHP using `date('Y-m-d H:i:s', time() + $window)` and check expiry using `strtotime()` in PHP. MySQL `NOW()` / `INTERVAL` / `expires_at > NOW()` comparisons are prohibited for these flows because the Hostinger shared-hosting MySQL server clock may be out of sync with PHP's Asia/Manila environment and cannot be corrected via `SET time_zone` with per-database restricted users.

The affected columns and their actual SQL types (no schema changes required):

| Database | Table | Column | SQL type | Expiry window |
|---|---|---|---|---|
| `accounts` | `accounts` | `mfa_code_expires_at` | `DATETIME` | 10 min |
| `pending_registrations` | `pending_registrations` | `expires_at` | `DATETIME` | 60 min |
| `password_resets` | `password_resets` | `created_at` | `TIMESTAMP` | 10 min (checked in `reset_password.php`) |

**No schema migrations are required.** The columns keep their existing types. PHP writes `date('Y-m-d H:i:s', ...)` strings rather than calling `DATE_ADD(NOW(), ...)` in SQL, and reads them back with `strtotime()` — so the MySQL server clock is never consulted for expiry decisions.

### Security — reCAPTCHA & Email Domain Whitelist

**Google reCAPTCHA v2 (Checkbox)** is enforced server-side at all manual credential entry points: `login/login.php`, `admin/admin_login.php`, and `register/register.php`. The `verify_recaptcha()` helper in `config.php` calls Google's `siteverify` API using `file_get_contents()`. Set `RECAPTCHA_SITE_KEY` and `RECAPTCHA_SECRET_KEY` in `config.local.php`. The Google OAuth flow (`oauth2callback.php`) does not require reCAPTCHA.

**Email Domain Whitelist** — `register/register.php` and `oauth/oauth2callback.php` both call `is_email_domain_allowed(string $email): bool` before proceeding. The allowed domains are defined in `ALLOWED_EMAIL_DOMAINS` (default: `gmail.com`, `dlsud.edu.ph`, `outlook.com`). Override in `config.local.php` if needed. An email from an unlisted domain is rejected at both registration and Google OAuth sign-in.

---

## Setup

### 1. Clone and place

```bash
git clone https://github.com/Verzadene/Fab-ulous.git
# Move to XAMPP htdocs:
# Windows: C:\xampp\htdocs\Fab-ulous
# macOS/Linux: /Applications/XAMPP/htdocs/Fab-ulous
```

### 2. Start XAMPP

Start **Apache** and **MySQL** from the XAMPP control panel. You do not need to create any databases manually.

### 3. Run the micro-database setup script

```bash
# Fresh install — creates all 12 databases and their tables from scratch
mysql -u root < C:/xampp/htdocs/Fab-ulous/database/setup_micro_dbs.sql

# Existing install only — rename legacy messages.sender_id / receiver_id
# to canonical senderID / receiverID. Idempotent; no-op on fresh installs.
mysql -u root < C:/xampp/htdocs/Fab-ulous/database/migration_messages_canonical.sql

# Existing install only — add is_read column to messages table (enables unread badge).
# Idempotent; no-op if column already exists.
mysql -u root < C:/xampp/htdocs/Fab-ulous/database/migration_messages_read_status.sql
```

### 4. Configure local credentials via `config.local.php`

Create `config.local.php` in the project root (it is gitignored). This file must define `DB_CONFIG` **before** `config.php` loads it (since PHP constants cannot be redefined).

> **Hostinger note:** On Hostinger, each of the 12 databases requires its own entry in `DB_CONFIG` with its own `user` and `pass`. The `host` value remains `'localhost'` on both local and Hostinger — MySQL is accessed over the loopback interface, not an external IP.

A minimal `config.local.php` for **local development** (all 12 domains, single root user):

```php
<?php
define('DB_CONFIG', [
    'accounts'              => ['host' => 'localhost', 'user' => 'root', 'pass' => '', 'name' => 'fab_ulous_accounts',              'port' => 3306],
    'posts'                 => ['host' => 'localhost', 'user' => 'root', 'pass' => '', 'name' => 'fab_ulous_posts',                 'port' => 3306],
    'likes'                 => ['host' => 'localhost', 'user' => 'root', 'pass' => '', 'name' => 'fab_ulous_likes',                 'port' => 3306],
    'comments'              => ['host' => 'localhost', 'user' => 'root', 'pass' => '', 'name' => 'fab_ulous_comments',              'port' => 3306],
    'commissions'           => ['host' => 'localhost', 'user' => 'root', 'pass' => '', 'name' => 'fab_ulous_commissions',           'port' => 3306],
    'commission_payments'   => ['host' => 'localhost', 'user' => 'root', 'pass' => '', 'name' => 'fab_ulous_commission_payments',   'port' => 3306],
    'friendships'           => ['host' => 'localhost', 'user' => 'root', 'pass' => '', 'name' => 'fab_ulous_friendships',           'port' => 3306],
    'notifications'         => ['host' => 'localhost', 'user' => 'root', 'pass' => '', 'name' => 'fab_ulous_notifications',         'port' => 3306],
    'messages'              => ['host' => 'localhost', 'user' => 'root', 'pass' => '', 'name' => 'fab_ulous_messages',              'port' => 3306],
    'pending_registrations' => ['host' => 'localhost', 'user' => 'root', 'pass' => '', 'name' => 'fab_ulous_pending_registrations', 'port' => 3306],
    'password_resets'       => ['host' => 'localhost', 'user' => 'root', 'pass' => '', 'name' => 'fab_ulous_password_resets',       'port' => 3306],
    'audit_log'             => ['host' => 'localhost', 'user' => 'root', 'pass' => '', 'name' => 'fab_ulous_audit_log',             'port' => 3306],
]);
```

A `config.local.php` for **Hostinger production** (each domain has its own user):

```php
<?php
// IMPORTANT: Each of the 12 databases uses a SEPARATE MySQL user.
// A user for one database cannot read or write to any other database.
// All 12 entries are required. Missing entries will throw a RuntimeException at runtime.
define('DB_CONFIG', [
    'accounts'              => ['host' => 'localhost', 'user' => 'u934684110_fab_app_user',   'pass' => 'YOUR_PASS_1',  'name' => 'u934684110_fab_accounts',   'port' => 3306],
    'posts'                 => ['host' => 'localhost', 'user' => 'u934684110_fab_app_user2',  'pass' => 'YOUR_PASS_2',  'name' => 'u934684110_fab_posts',       'port' => 3306],
    'likes'                 => ['host' => 'localhost', 'user' => 'u934684110_fab_app_user3',  'pass' => 'YOUR_PASS_3',  'name' => 'u934684110_fab_likes',       'port' => 3306],
    'comments'              => ['host' => 'localhost', 'user' => 'u934684110_fab_app_user4',  'pass' => 'YOUR_PASS_4',  'name' => 'u934684110_fab_comments',    'port' => 3306],
    'commissions'           => ['host' => 'localhost', 'user' => 'u934684110_fab_app_user5',  'pass' => 'YOUR_PASS_5',  'name' => 'u934684110_fab_commission',  'port' => 3306],
    'commission_payments'   => ['host' => 'localhost', 'user' => 'u934684110_fab_app_user6',  'pass' => 'YOUR_PASS_6',  'name' => 'u934684110_fab_comm_pays',   'port' => 3306],
    'friendships'           => ['host' => 'localhost', 'user' => 'u934684110_fab_app_user7',  'pass' => 'YOUR_PASS_7',  'name' => 'u934684110_fab_friends',     'port' => 3306],
    'notifications'         => ['host' => 'localhost', 'user' => 'u934684110_fab_app_user8',  'pass' => 'YOUR_PASS_8',  'name' => 'u934684110_fab_notifs',      'port' => 3306],
    'messages'              => ['host' => 'localhost', 'user' => 'u934684110_fab_app_user9',  'pass' => 'YOUR_PASS_9',  'name' => 'u934684110_fab_messages',    'port' => 3306],
    'pending_registrations' => ['host' => 'localhost', 'user' => 'u934684110_fab_app_user10', 'pass' => 'YOUR_PASS_10', 'name' => 'u934684110_fab_pendings',    'port' => 3306],
    'password_resets'       => ['host' => 'localhost', 'user' => 'u934684110_fab_app_user11', 'pass' => 'YOUR_PASS_11', 'name' => 'u934684110_fab_pw_resets',   'port' => 3306],
    'audit_log'             => ['host' => 'localhost', 'user' => 'u934684110_fab_app_user12', 'pass' => 'YOUR_PASS_12', 'name' => 'u934684110_fab_audit_log',   'port' => 3306],
]);

// ── SMTP (required for MFA, registration, password reset) ──
define('SMTP_HOST',         'smtp.gmail.com');
define('SMTP_PORT',         465);
define('SMTP_ENCRYPTION',   'ssl');
define('SMTP_USERNAME',     'your-email@gmail.com');
define('SMTP_PASSWORD',     'your-app-password');
define('MAIL_FROM_ADDRESS', 'your-email@gmail.com');
define('MAIL_FROM_NAME',    'FABulous');

// ── Google OAuth ──
define('GOOGLE_CLIENT_ID',     'your-client-id.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'your-client-secret');
define('GOOGLE_REDIRECT_URI',  'https://thefablab.site/Fab-ulous/oauth/oauth2callback.php');

// ── Google reCAPTCHA v2 ──
define('RECAPTCHA_SITE_KEY',   'your-site-key');    // public — rendered in HTML
define('RECAPTCHA_SECRET_KEY', 'your-secret-key');  // private — server-side only, never output to browser

// ── Email Domain Whitelist ──
define('ALLOWED_EMAIL_DOMAINS', [
    'gmail.com',
    'dlsud.edu.ph',
    'outlook.com',
    // add more domains here as needed
]);

// ── PayMongo ──
define('PAYMONGO_SECRET_KEY',    'sk_live_...');
define('PAYMONGO_WEBHOOK_SECRET','whsk_...');  // raw webhook secret, NOT a URL

define('APP_ENV', 'production');
define('APP_URL', 'https://thefablab.site/Fab-ulous');
```

> **Why define `DB_CONFIG` in `config.local.php`?**  
> `config.php` uses `defined('DB_CONFIG') || define('DB_CONFIG', [...])`. If `config.local.php` defines it first, `config.php` skips its default. This is the only way to supply per-domain Hostinger credentials without editing the tracked `config.php`.

> **Why 12 separate users on Hostinger?**  
> Hostinger Business Web Hosting enforces strict per-database privilege separation. A user granted access to `u934684110_fab_accounts` cannot read `u934684110_fab_posts`, even with fully-qualified `db.table` SQL syntax. This is not a configuration choice — it is a hard platform constraint. The application's Pattern B aggregation strategy exists specifically to work within this constraint.

### 5. Create an admin account

Register normally at `http://localhost/Fab-ulous/register/register.html`, then promote via SQL:

```sql
USE fab_ulous_accounts;
UPDATE accounts SET role = 'admin'       WHERE username = 'your_username';
-- Or for super admin:
UPDATE accounts SET role = 'super_admin' WHERE username = 'your_username';
```

### 6. Open the app

```
http://localhost/Fab-ulous/landing/landing.html
```

---

## Current Feature Status

| Feature | Status | Notes |
|---|---|---|
| Google OAuth 2.0 | ✅ Live | Links `google_id` on first login; existing accounts linked by email; email domain whitelist enforced |
| Email MFA | ✅ Live | 6-digit code on every login; audit-logged on success |
| Password reset by email | ✅ Live | 6-digit reset code; SMTP failure does not silently swallow |
| Registration with email verify | ✅ Live | `pending_registrations` → `accounts` on code confirm; email domain whitelist enforced |
| Google reCAPTCHA v2 | ✅ Live | Checkbox reCAPTCHA on user login, admin login, and registration; server-side verified via `verify_recaptcha()` |
| 1-Minute Login Cooldown (UI Lockout) | ✅ Live | Triggered after **5** consecutive failed credential or reCAPTCHA attempts on `login/login.php` and `admin/admin_login.php`; attempt counter shown after each failure ("Failed attempt N of 5 — X remaining"); all inputs and the submit button are immediately `disabled` server-side on lockout; PHP rejects POST requests during the window regardless of client-side state; session-persisted expiry survives page refreshes; countdown auto-reloads the form when the 60-second window expires; Google OAuth (`oauth2callback.php`) is unaffected |
| Email domain whitelist | ✅ Live | `ALLOWED_EMAIL_DOMAINS` constant; enforced at registration and Google OAuth sign-in via `is_email_domain_allowed()` |
| Friend system | ✅ Live | Request / accept / decline / unfriend; feed is friend-only |
| Posts & feed | ✅ Live | Create, edit, delete (owner); image upload of 3D prints; friend-only feed |
| Post self-deletion audit trail | ✅ Live | Every owner-initiated delete writes to `fab_ulous_audit_log`; action: `"User deleted their own post (ID: #N)"` |
| Likes & comments | ✅ Live | Toggle like; add/view comments per post |
| Notifications | ✅ Live | In-app unread badge; mark-as-read on open |
| Messaging | ✅ Live | Per-user threads; unread badge; mark-as-read on open |
| Profile management | ✅ Live | Edit name/bio, change password, upload profile pic |
| Commission requests | ✅ Live | Any user can submit a 3D print request; STL/PDF/image attachment support |
| PayMongo checkout | ✅ Live | GCash/card via PayMongo; webhook updates `commission_payments` |
| Giphy GIF Picker | ✅ Live | Inline GIF selection in Comments and Messages; `gif_url VARCHAR(255)` column added to `comments` and `messages` tables; host-validated server-side; powered by `post/giphy.js` (`window.Giphy` API) |
| Admin: User Management | ✅ Live | Ban/unban, promote/demote (super admin), delete accounts; **server-side Limit filter** (default 10, `?user_limit=N`); `processUnbanUser()` now role-aware for `visibility_role` |
| Admin: Feed Moderator | ✅ Live | View all posts, remove with email notify; **server-side Limit filter** (default 10, `?post_limit=N`); `processDeletePost()` now role-aware for `visibility_role` |
| Admin: Commission oversight | ✅ Live | Status updates, deletion with email notify; **server-side Limit filter** (default 10, `?comm_limit=N`); "Showing X of X commissions" counter; `processUpdateCommission()` now role-aware for `visibility_role` |
| Admin: Audit Log | ✅ Live | Searchable, sortable, windowed (8h/24h/72h/7d/30d); Limit input; **Action filter** (Ban/Unban/Delete/Commission/Login/Logout/All); visibility-gated by role; **time-window filter uses PHP-computed cutoff (not MySQL `NOW()`)** |
| Admin: Role promotion/demotion | ✅ Live | Super admin only; double-gated at PHP + SQL layer; **`processDemoteToUser` undefined-variable fatal fixed** |
| Admin: Account deletion | ✅ Live | **`urlencode(array)` TypeError fixed** — `processDeleteUser()` result now correctly unpacked before redirect; audit entry delegates to `logAuditAction()` |
| Audit trail integrity | ✅ Live | All audit INSERTs supply explicit PHT `created_at`; all derive `visibility_role` from actor's role; `logAuditAction()` refuses `admin_id = 0`; login/logout/OAuth events fully recorded |

### Admin Dashboard Filter Parameters

The admin dashboard accepts the following `GET` parameters to control server-side data limits:

| Parameter | Tab | Default | Effect |
|---|---|---|---|
| `user_limit` | User Management | `10` | Max accounts fetched from DB (`0` = no limit) |
| `post_limit` | Feed Moderator | `10` | Max posts fetched from DB (`0` = no limit) |
| `comm_limit` | Commissions | `10` | Max commissions fetched from DB (`0` = no limit) |
| `audit_limit` | Audit Log | `30` | Max audit entries returned (`0` = no limit) |
| `audit_hours` | Audit Log | `8` | Time window in hours (8 / 24 / 72 / 168 / 720) |
| `audit_sort` | Audit Log | `desc` | Sort order: `asc` = oldest first, `desc` = newest first |
| `audit_search` | Audit Log | _(empty)_ | Free-text search against action / username fields |
| `audit_action` | Audit Log | _(empty / All)_ | Restricts results to one action category: `ban`, `unban`, `delete`, `commission`, `login`, `logout`; empty = show all |
| `tab` | All tabs | `dashboard` | Auto-activates the named tab on page load (used internally by limit navigation) |

---

## External API Integrations

### Giphy GIF Picker

FABulous integrates the [Giphy API](https://developers.giphy.com/) to allow users to attach animated GIFs to comments and messages.

**API Key configuration** — add to `config.local.php`:

```php
// ── Giphy ──
define('GIPHY_API_KEY', '0yFOakYWQIIlRFSCVs1ztIkmaytA3Gfc');
```

The key is already wired into `config.php` as the fallback default, so no `config.local.php` change is strictly required for a fresh deployment — but overriding it in `config.local.php` is recommended so you can rotate the key without touching tracked files.

**Database migration** — run `giphy_migration.sql` against both affected databases:

```bash
# On Hostinger: use phpMyAdmin — select each database and run the SQL file.
# Locally:
mysql -u root fab_ulous_comments < database/giphy_migration.sql
mysql -u root fab_ulous_messages < database/giphy_migration.sql
```

**Frontend** — `post/giphy.js` is loaded by both `post.php` and `messages.php`. It exposes `window.Giphy.init(key)`, `Giphy.open(ctx, cb)`, and `Giphy.close()`.

**Security** — `gif_url` values are validated server-side against an allowed-host list (`*.giphy.com`, `i.giphy.com`) in both `comment.php` and `MessageRepository::processSendMessage()`. Arbitrary image URLs are rejected.
