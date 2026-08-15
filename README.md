# Renewal Guard + Client Task Desk

An internal tool for a Chennai web agency. Two jobs:

1. **Renewal Guard** — every domain, host, certificate and licence has an expiry date, an owner, and a reminder that escalates. Nothing goes down because nobody noticed.
2. **Client Task Desk** — client work has an owner, a status and a history, instead of living in WhatsApp.

Built with Laravel 12, Livewire 3, Alpine and Tailwind v4. Hand-built UI — no admin theme.

---

## Where it runs

Production target is **Hostinger shared hosting**, which shapes several stack choices:

| Concern | Choice | Why |
| --- | --- | --- |
| Queue | `database` driver, drained by cron | No Supervisor, no long-running workers |
| Cache / session | `database` driver | No Redis on shared hosting |
| Scheduler | hPanel cron → `schedule:run` | Same as any host |
| Real-time | Pusher Channels (Pusher protocol) | Reverb needs a persistent process and an open port |
| Assets | Built locally, `public/build` committed | No Node runtime on the server |
| Voice notes | Encoded client-side | No ffmpeg binary |

Broadcasting speaks the Pusher protocol, which is what Laravel Echo speaks. Moving to a self-hosted Reverb server on a VPS later is an `.env` change plus `composer require laravel/reverb` — no application code changes.

---

## Local setup

Requires PHP 8.2+ (8.3 recommended), Composer, Node 20+, and MySQL 8 or MariaDB.

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate

# Create the database, then:
php artisan migrate --seed

npm run build      # or: npm run dev
php artisan serve
```

Optional containers instead of XAMPP — MySQL and Mailpit only:

```bash
docker compose up -d
# Mailpit UI: http://localhost:8025
```

### Seeded accounts

Password for all three is `password`.

| Role | Email |
| --- | --- |
| Admin | `admin@renewalguard.test` |
| Manager | `manager@renewalguard.test` |
| Employee | `employee@renewalguard.test` |

There is no public registration — accounts are created by an admin.

### Checks

```bash
php vendor/bin/pest      # test suite
php vendor/bin/pint      # formatting
```

---

## Design system

Everything is built from the tokens in `resources/css/app.css`. Read that file before adding a screen.

- **Type** — DM Sans (self-hosted, variable) at weights 400, 500 and 700 only. Semantic classes: `t-page-title`, `t-section`, `t-body`, `t-sub`, `t-meta`, `t-metric`. Numeric columns get `tnum`.
- **Colour** — a warm neutral ink ramp, one indigo accent, and three status colours. **Colour encodes status only.** Red = overdue or ≤2 days, amber = ≤5 days, green = done or healthy, neutral = normal. The accent is for links and the single primary button on a screen.
- **Dark mode** — a `.dark` class on `<html>`, set before first paint by the inline script in `resources/views/partials/theme-script.blade.php`. Three states: explicit light, explicit dark, or follow the OS. Every token has a dark counterpart; check both.
- **Elevation** — one shadow (`shadow-float`), used only on modals, dropdowns and the mobile bottom sheet. Everything else is flat with a 1px `ink-100` border.
- **Icons** — Lucide, inlined as SVG through `<x-icon name="…">`. Paths live in `app/Support/Lucide.php`; an unknown name throws rather than rendering nothing, and a test scans every view for names that do not exist.

### Components

`resources/views/components/ui/` — `button`, `badge`, `card`, `list-row`, `avatar`, `modal`, `dropdown` (+ `dropdown-item`), `field`, `empty-state`, `skeleton`.

Browse them all, in both themes, at **`/kitchen-sink`** (registered outside production only).

### Copy rules

Sentence case. No exclamation marks, no "please", no "successfully". Buttons are verb-first — "Renew domain", not "Submit". Empty states invite rather than apologise: "No renewals due in the next 30 days", not "Nothing here yet".

### Mobile first

Every screen is built at 375px first. Bottom tab bar on mobile (respecting `env(safe-area-inset-bottom)`), collapsible sidebar from `lg:`. Rows are 64px on mobile and 52px on desktop; tap targets never go below 44px. Lists are stacked cards on mobile — tables only appear at `lg:` and above. Inputs are 16px on mobile so iOS Safari does not zoom on focus.

---

## Roles

| Role | Reach |
| --- | --- |
| **admin** | Everything, including users, settings and the credential vault |
| **manager** | Everything except users, settings and the credential vault |
| **employee** | Only their own tasks and the clients those tasks belong to |

Permissions live in `app/Support/Permissions.php` and are seeded by `RolesAndPermissionsSeeder`. Admins hold everything through a `Gate::before` check.

Hiding a navigation link is never the protection. Every route is guarded, and from phase 4 so is every broadcast channel. `tests/Feature/AppShellTest.php` proves an employee gets a 403 rather than a hidden link.

---

## Deploying to Hostinger shared hosting

> The websocket and queue notes below are the parts that bite late. Test them on the real host early, not the day before launch.

### 1. Directory layout

Laravel's application code must sit **outside** the web root. On Hostinger:

```
~/domains/<your-domain>/
├── app/                 ← the Laravel project (everything except public/)
└── public_html/         ← the contents of Laravel's public/ directory
```

In `public_html/index.php`, repoint the two requires:

```php
require __DIR__.'/../app/vendor/autoload.php';
$app = require_once __DIR__.'/../app/bootstrap/app.php';
```

### 2. Upload

```bash
npm run build          # locally — the server has no Node
composer install --no-dev --optimize-autoloader
```

Upload the project, then on the server:

```bash
php artisan key:generate      # only on first deploy
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

`storage/` and `bootstrap/cache/` must be writable (755 is enough on Hostinger).

### 3. Cron

Add these in hPanel → Advanced → Cron Jobs. Use the full path to the PHP binary that hPanel shows for your chosen PHP version.

```cron
# Laravel scheduler — every minute
* * * * * /usr/bin/php ~/domains/<domain>/app/artisan schedule:run >> /dev/null 2>&1

# Queue worker — no Supervisor here, so cron restarts a short-lived worker
* * * * * /usr/bin/php ~/domains/<domain>/app/artisan queue:work --stop-when-empty --max-time=55 --tries=3 >> /dev/null 2>&1
```

If your plan only allows a 5-minute minimum interval, the daily reminder run still fires: the reminder command is idempotent and guarded by a unique index, so a late or repeated run cannot send a duplicate.

### 4. Healthcheck

Set `HEALTHCHECK_URL` to a Healthchecks.io (or similar) ping URL. The reminder command pings it on every successful run. **If the scheduler dies, this app fails silently — which is the exact failure it exists to prevent.** The external service is what catches that.

### 5. Real-time (phase 4)

Create a Pusher Channels app, fill in `PUSHER_*` and the mirrored `VITE_PUSHER_*` values, and set `BROADCAST_CONNECTION=pusher`. Nothing needs to be installed on the server — the browser connects to Pusher directly, and PHP only makes outbound HTTPS calls.

### 6. HTTPS

Enable the free SSL certificate in hPanel and set `SESSION_SECURE_COOKIE=true`. `APP_ENV=production` forces `https://` on generated URLs.

---

## Build phases

| Phase | Delivers | State |
| --- | --- | --- |
| 1 | Foundation: design system, app shell, auth, roles | **Done** |
| 2 | Renewal Guard: clients, assets, reminder engine, imports, digests | Next |
| 3 | Tasks: projects, status machine, review gate, Kanban, time logs | |
| 4 | Real-time: chat, attachments, voice notes, presence, push, PWA | |
| 5 | Depth: RDAP/SSL verification, WhatsApp, vault, reports, 2FA | |
| 6 | Hardening: N+1 audit, Lighthouse, dark-mode and 375px review, docs | |

Screens that arrive in a later phase are already routed, so the shell is complete and route names never change.
