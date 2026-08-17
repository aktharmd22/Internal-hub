# Deploying to internal.gnext.space

Hostinger shared hosting behind Cloudflare DNS.

Do the steps in order. **Step 3 and step 6 are the ones that bite** — the SSL certificate cannot be issued while Cloudflare's proxy is on, and the SSL/TLS mode must be Full (strict) or you get an infinite redirect.

---

## 1. Create the subdomain on Hostinger

hPanel → **Domains → Subdomains**

| Field | Value |
| --- | --- |
| Subdomain | `internal` |
| Domain | `gnext.space` |
| Custom folder | leave default |

Hostinger creates `~/domains/internal.gnext.space/public_html`.

**Write down the server's IP address** — hPanel → Websites → Dashboard, or **Hosting → Details**. It looks like `193.203.x.x`. You need it in the next step.

---

## 2. Point Cloudflare at it

Cloudflare dashboard → `gnext.space` → **DNS → Records → Add record**

| Field | Value |
| --- | --- |
| Type | `A` |
| Name | `internal` |
| IPv4 address | the Hostinger IP from step 1 |
| Proxy status | **DNS only (grey cloud)** ← for now |
| TTL | Auto |

> Grey cloud is deliberate and temporary. Leave it grey until step 5 is done.

Check it resolves before moving on:

```bash
nslookup internal.gnext.space
```

It should return the Hostinger IP. Give it 2–5 minutes if not.

---

## 3. Upload the application

Build locally first — Hostinger has no Node:

```bash
npm run build
composer install --no-dev --optimize-autoloader
```

Upload via hPanel **File Manager** or SFTP so the layout is:

```
~/domains/internal.gnext.space/
├── app/                  ← the whole project EXCEPT public/
│   ├── app/  bootstrap/  config/  database/  routes/
│   ├── storage/  vendor/  artisan  composer.json
│   └── public/           ← do NOT upload this here
└── public_html/          ← the CONTENTS of the project's public/ folder
    ├── index.php  .htaccess  build/  icons/
    ├── sw.js  manifest.webmanifest  offline.html  favicon.ico
    └── Logo.png
```

**The application code must sit outside `public_html`.** If `.env` is web-reachable, your database password and `APP_KEY` are public.

Then edit `public_html/index.php` and change the two paths:

```php
require __DIR__.'/../app/vendor/autoload.php';
$app = require_once __DIR__.'/../app/bootstrap/app.php';
```

---

## 4. Database and environment

hPanel → **Databases → MySQL Databases**. Create a database and a user, and note all three values.

Create `~/domains/internal.gnext.space/app/.env` from `.env.example`, then set at minimum:

```dotenv
APP_NAME="Gnext Hub"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://internal.gnext.space
APP_TIMEZONE=Asia/Kolkata

DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=u123456789_gnext
DB_USERNAME=u123456789_gnext
DB_PASSWORD=...

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=database

HEALTHCHECK_URL=
```

`APP_DEBUG=false` is not optional. With it on, any error page prints your environment variables to the visitor.

Then over SSH (hPanel → Advanced → SSH Access):

```bash
cd ~/domains/internal.gnext.space/app
php artisan key:generate
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan db:seed --class=ReminderRuleSeeder --force
```

Create your admin account:

```bash
php artisan tinker
>>> $u = App\Models\User::create(['name' => 'Your Name', 'email' => 'admin@gnext.com', 'password' => 'a-long-password-here', 'email_verified_at' => now(), 'timezone' => 'Asia/Kolkata']);
>>> $u->assignRole('admin');
>>> exit
```

Permissions:

```bash
chmod -R 755 storage bootstrap/cache
```

---

## 5. Issue the SSL certificate — while the proxy is still grey

hPanel → **Security → SSL** → select `internal.gnext.space` → **Install SSL**.

This is why the record is grey in step 2. Hostinger validates ownership by answering an HTTP challenge on your origin. With Cloudflare's proxy on, the challenge hits Cloudflare instead and **the certificate never issues** — which then makes step 6 fail with a 526 error.

Wait for it to report Active. Confirm:

```bash
curl -I https://internal.gnext.space
```

---

## 6. Turn the proxy on, and set SSL to Full (strict)

Now go back to Cloudflare:

1. **DNS → Records** → edit the `internal` record → Proxy status → **Proxied (orange cloud)**.
2. **SSL/TLS → Overview** → set encryption mode to **Full (strict)**.

> **Not Flexible.** Flexible makes Cloudflare talk to your origin over plain HTTP while telling the browser the connection is HTTPS. This app sets `URL::forceScheme('https')` in production, so Laravel keeps redirecting to HTTPS, Cloudflare keeps requesting HTTP, and you get `ERR_TOO_MANY_REDIRECTS`. Full (strict) is also the only mode that actually verifies your origin certificate.

Optional but worth it, under **SSL/TLS → Edge Certificates**:

- **Always Use HTTPS** → On
- **Automatic HTTPS Rewrites** → On
- **Minimum TLS Version** → 1.2

---

## 7. Cron jobs

hPanel → **Advanced → Cron Jobs**. Use the full PHP binary path hPanel shows for your PHP version.

```cron
# Scheduler — every minute
* * * * * /usr/bin/php ~/domains/internal.gnext.space/app/artisan schedule:run >> /dev/null 2>&1

# Queue worker — no Supervisor here, so cron restarts a short-lived worker
* * * * * /usr/bin/php ~/domains/internal.gnext.space/app/artisan queue:work --stop-when-empty --max-time=55 --tries=3 >> /dev/null 2>&1
```

**Without the second line no email ever sends.** Reminders are queued; nothing drains the queue on its own.

If your plan only offers a 5-minute minimum, that is fine. The reminder command is idempotent and guarded by a unique index, so a late or repeated run cannot double-send.

---

## 8. Cache and verify

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link
php artisan cloudflare:ips     # refresh the trusted proxy ranges
```

Then check:

```bash
# Should return 200
curl -I https://internal.gnext.space/login

# Should return "OK"
curl https://internal.gnext.space/up

# Should NOT be reachable — if this returns your file, stop and fix step 3
curl https://internal.gnext.space/../app/.env
```

Sign in, then go to **Settings → Mail**, fill in the SMTP details and press **Send a test email**. It reports the real error if it fails.

Finally, set `HEALTHCHECK_URL` in Settings → Company to a Healthchecks.io ping URL. **If cron stops firing, this application cannot tell you** — that is the one failure it is powerless against, and an outside service noticing the missing ping is what catches it.

---

## Why the app already knows it is behind Cloudflare

`bootstrap/app.php` trusts Cloudflare's published IP ranges as proxies. This matters for three things:

- **Rate limiting.** Otherwise every visitor arrives as the same Cloudflare address and the login limiter throttles the whole company as one bucket.
- **The credential vault audit log.** It records `request()->ip()`. Untrusted, that is a Cloudflare edge node, and the one record that has to be trustworthy is worthless.
- **HTTPS detection.** Otherwise Laravel thinks the request arrived over HTTP.

The ranges are listed explicitly rather than trusting `*`, because your origin has a public IP of its own. Anyone who finds it could otherwise forge `X-Forwarded-For` and write a false address into the audit log. Worth doing on top:

- Cloudflare → **SSL/TLS → Origin Server → Authenticated Origin Pulls** → On.
- Or in hPanel, restrict the site to Cloudflare's IP ranges at the firewall.

Refresh the ranges occasionally: `php artisan cloudflare:ips`.

---

## Redeploying

```bash
# Locally
npm run build
composer install --no-dev --optimize-autoloader
```

Upload changed files, then:

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Then purge Cloudflare's cache: **Caching → Configuration → Purge Everything**. Otherwise visitors keep the old CSS and JS, and `public/build` filenames change on every build.

---

## Troubleshooting

| Symptom | Cause |
| --- | --- |
| `ERR_TOO_MANY_REDIRECTS` | SSL/TLS mode is Flexible. Set it to Full (strict). |
| **526 Invalid SSL certificate** | The origin certificate never issued. Go grey cloud, install SSL in hPanel, then go orange again. |
| **521 Web server is down** | Wrong IP in the A record, or the origin is blocking Cloudflare. |
| 500 with a blank page | `storage/` is not writable, or `APP_KEY` is unset. Check `storage/logs/laravel.log`. |
| CSS missing, page unstyled | `public/build` was not uploaded, or Cloudflare is serving a stale cache. |
| Login says "too many attempts" for everyone | Trusted proxies are not being applied — run `php artisan config:cache`. |
| No emails arrive | The queue worker cron is missing. Check `php artisan queue:failed`. |
| Logo missing in production, fine locally | Filename case. Linux is case-sensitive; the app handles `Logo.png`, but the file must actually be uploaded. |
