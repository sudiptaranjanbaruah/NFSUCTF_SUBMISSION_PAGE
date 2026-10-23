# NFSU Guwahati CTF

A jeopardy-style Capture The Flag platform for the National Forensic Sciences University, Guwahati Campus. It is plain PHP 8 with MySQL, so it runs on any Hostinger plan with no Node, Docker or build step.

## What's inside

**Players**
- Sign up with email and password, verified by a 6-digit code emailed through Hostinger SMTP.
- Sign in with a password, a passwordless email code, Google or GitHub. Password reset works by email code.
- Create a team (you get an invite code), join one with a code, or play solo.
- The challenge board groups challenges by category with search and filters. Each challenge opens in a drawer with its files, connection info, hints and solver list.
- Flag submission gives instant feedback, and solved challenges get a SOLVED or FIRST BLOOD stamp.
- The live scoreboard has a top-10 chart, a first-blood feed and a college filter, and respects the freeze time.
- Team pages show score history, a category breakdown, awards and members. Captains can manage members.
- Live toasts announce first bloods, bonus awards and new announcements.

**Organisers** (`/admin`)
- **Challenge editor:** Markdown with live preview, several flags (exact or regex, optional case-insensitive), hints with point costs, file uploads (served only to signed-in players), connection info, tags and prerequisites ("unlock after solving X").
- **Release control:** draft, hidden or visible states, plus scheduled release times so challenges can go out in waves.
- **Static or dynamic scoring:** dynamic values decay as more teams solve.
- **First-blood bonus:** awarded automatically to the 1st, 2nd and 3rd solver, as fixed points or a percentage. It is set globally and can be overridden per challenge, and the bonus is kept even when a dynamic value later drops.
- **Manual awards and penalties:** unique solve, write-up, bug report, other or penalty. They can go to a team, several teams or a specific player, with a reason, can be public or private, and can be revoked or restored.
- **Analytics:** KPIs, score progression, submissions per hour, a difficulty check (solves vs wrong attempts), sign-ups, category completion, a weekday/hour heatmap and sign-in methods. Each challenge also has a stats page showing its most common wrong flags.
- **Anti-cheat signals:** the same wrong flag from several teams, IPs shared across teams, teams solving in lockstep, bursts of wrong flags, and failed sign-ins.
- **People:** user and team management (ban, hide, move, roles), plus CSV bulk import that emails each person a one-time activation link.
- **Content:** announcements (optionally emailed to everyone), editable Rules/About/home pages, brand settings (logo, accent colour), and event times, freeze, pause and maintenance mode.
- **Audit and export:** an audit log of every admin action and solve, CSV exports, and a JSON challenge import/export (optionally including files).

**Security**
- Passwords are hashed with argon2id.
- Email codes are HMAC-hashed, expire after 10 minutes and lock after 5 wrong attempts.
- CSRF tokens protect every form and fetch request.
- Rate limits stored in the database cover sign-in, codes, sign-up and flag submission.
- Admins always confirm sign-in with an emailed code.
- Solves are transaction-safe (the challenge row is locked, so concurrent submits produce exactly one solve and correct first-blood ranks).
- Challenge files are kept outside the web root, and responses send strict security headers (CSP and others).

For running an event day to day, see the [admin guide](docs/ADMIN_GUIDE.md).

## Project layout

```
public_html/        Web root (Hostinger's public_html). Front controller + assets.
app/
  config/.env       Secrets (SMTP, DB, OAuth). Never committed.
  src/              Core (router, DB, rate limit), Auth, Mail, Services, Controllers
  views/            PHP templates: layouts/, auth/, player/, admin/, emails/
  migrations/       SQL migrations (run with bin/migrate.php)
  bin/              migrate, seed-admin, cron, selftest
  storage/          uploads/, logs/, sessions/, cache/ (must be writable)
composer.json       PHPMailer + league/commonmark
```

Design tokens live in `public_html/assets/css/tokens.css`: a neutral, product-style palette with one accent colour, Geist and Geist Mono type, and light and dark themes that follow the visitor's OS (they can switch with the toggle). To change the accent or logo, use **Admin → Settings → Brand** or edit the tokens.

## Run locally

Requires PHP 8.1+ (with pdo_mysql, curl, mbstring), MySQL 8 or MariaDB 10.4+, and Composer.

```bash
composer install
```

```bash
cp app/config/.env.example app/config/.env
```

Edit `app/config/.env`: set the DB credentials, `APP_URL=http://localhost:8000`, `APP_DEBUG=true`, and generate `APP_KEY` with `openssl rand -hex 32`. Set `MAIL_DRIVER=log` to write emails to `app/storage/logs/mail.log` instead of sending them.

```bash
mysql -u root -e "CREATE DATABASE nfsuctf CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
```

```bash
php app/bin/migrate.php
```

```bash
php app/bin/seed-admin.php you@example.com yourname 'a-strong-password'
```

```bash
php -S localhost:8000 -t public_html public_html/index.php
```

`php app/bin/selftest.php` checks the scoring maths, flag matching and email-code limits.

## Deploy to Hostinger

1. **Create the database.** In hPanel → Databases → MySQL, create a database and user. From the app, connect to host `127.0.0.1`.
2. **Upload the files** so the site folder looks like this:
   ```
   domains/<your-domain>/
     public_html/   ← contents of this repo's public_html/
     app/
     vendor/        ← run `composer install --no-dev` locally or over SSH
     composer.json
   ```
   Make sure `app/storage/*` is writable (755 is usually enough on Hostinger).
3. **Configure** `app/config/.env` on the server (copy from `.env.example`):
   - `APP_URL=https://ctf.yourdomain`
   - `APP_DEBUG=false`
   - `TRUST_PROXY=true`
   - a fresh `APP_KEY`
   - the DB credentials
   - `MAIL_DRIVER=smtp`, `MAIL_HOST=smtp.hostinger.com`, `MAIL_PORT=465`, `MAIL_ENCRYPTION=ssl`, plus your mailbox username and password and `MAIL_FROM`.
4. **Migrate and create your admin** over SSH:
   ```bash
   php app/bin/migrate.php
   ```
   ```bash
   php app/bin/seed-admin.php you@yourdomain yourname
   ```
5. **Add the cron job.** In hPanel → Advanced → Cron Jobs, run every minute:
   ```
   /usr/bin/php /home/<user>/domains/<your-domain>/app/bin/cron.php
   ```
   It sends queued email (invites, announcements) within `MAIL_HOURLY_CAP` and cleans up expired codes.
6. **Raise upload limits** if your challenge files are large. In hPanel → Advanced → PHP Configuration, set `upload_max_filesize` and `post_max_size` above `UPLOAD_MAX_MB`.
7. Sign in, open **Admin → Settings** and set the event times, branding and first-blood bonus. Then use **Send me a test email** to confirm SMTP works.

### Google and GitHub sign-in

The buttons appear only once credentials are set in `.env`.

- **Google:** in Google Cloud Console → APIs & Services → Credentials, create an OAuth client ID of type *Web application*. Set the authorised redirect URI to `https://ctf.yourdomain/auth/google/callback`, then fill in `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET`.
- **GitHub:** in GitHub → Settings → Developer settings → OAuth Apps → New, set the callback URL to `https://ctf.yourdomain/auth/github/callback`, then fill in `GITHUB_CLIENT_ID` and `GITHUB_CLIENT_SECRET`.

## Operating notes

- **Scores are never stored as totals.** They are always computed from solves, first-blood bonuses, awards and hint costs, so removing a solve or revoking an award updates everything at once.
- **Freeze:** after the freeze time, players see the scoreboard as it stood at that moment, while admins always see live scores. Clear the freeze time to reveal the final standings.
- **Pause** stops submissions but keeps everything visible. **Maintenance mode** blocks everyone except admins.
- **Bulk import CSV** format is `email,name,team`, one row per person. Invitees with the same team name end up on the same team.
