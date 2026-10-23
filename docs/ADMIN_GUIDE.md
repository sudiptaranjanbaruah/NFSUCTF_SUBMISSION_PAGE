# Admin guide

How to run an event on the CTF platform, from setup to final results. Everything here happens in the admin console at **/admin**.

## Signing in

1. Go to **/login** and sign in with your admin email and password. You can also use the **Email code** tab, which needs no password.
2. Admin accounts always need a second step: a 6-digit code is emailed to you. Enter it on the next screen.
3. Open the console from your avatar menu → **Admin console**, or go straight to **/admin**.

Change your password under **Profile** (avatar menu) the first time you sign in.

To add more organisers, ask them to register normally. Then open **People → Users**, find them, and set **Role** to Admin. Only super admins can change roles.

## Before the event

### 1. Event settings — Configure → Settings

- **Event:** name, tagline, organiser, contact email and flag format (e.g. `NFSUCTF{...}`).
  - **Starts / Ends:** challenges are hidden before the start, and submissions close at the end. Leave both empty to control the event by hand.
  - **Freeze scoreboard:** after this time, players see the scoreboard as it stood at that moment, so the final hour stays suspenseful. You always see live scores.
- **Access and registration:**
  - Turn registration on or off, allow solo players, and set the maximum team size.
  - **Allowed email domains** restricts sign-up to e.g. `nfsu.ac.in`.
  - **Scoreboard** can be public, signed-in players only, or hidden.
- **First-blood bonus:** extra points for the 1st, 2nd and 3rd team to solve a challenge, e.g. `50,30,10`. **Bonus type** is either fixed points or a percentage of the challenge value. Bonuses are awarded automatically.
- **Brand:** upload your logo and pick an accent colour.
- **Email:** click **Send me a test email** to confirm mail delivery works before inviting anyone.

### 2. Pages — Configure → Pages

Edit the **Rules**, **About** and **Home page extra** pages (prizes, schedule, sponsors). They use Markdown.

### 3. Challenges — Event → Challenges

Click **New challenge** and fill in:

| Field | What it does |
|---|---|
| Title, category, difficulty, author | Shown on the challenge card. Manage categories at the bottom of the challenge list. |
| Description | Markdown, with a live preview on the right. |
| Connection info | e.g. `nc chall.example.com 31337`. Players get a copy button. |
| Tags | Searchable on the challenge board. |
| **Flags** | Add one or more; any match solves it. **Exact** compares the text; **Regex** must match the whole submission (e.g. `NFSUCTF\{[a-f0-9]{8}\}`). Tick **Ignore case** if capitalisation shouldn't matter. |
| **Hints** | Each hint has a point cost that is deducted from the team when they reveal it. Use 0 for free hints. |
| **Visibility** | *Draft* (work in progress), *Hidden* (ready but not shown) or *Visible*. A challenge needs at least one flag to be made visible. |
| **Release at** | A visible challenge stays hidden until this time. Use it to release challenges in waves. |
| **Unlock after solving** | Locks the challenge until the team has solved another one. |
| **Scoring** | *Static* keeps a fixed value. *Dynamic* starts at the starting value and drops towards the minimum as more teams solve it. Every solver holds the current value, but first-blood bonuses are kept. |
| Max wrong attempts | Optional per-team limit. |
| First-blood bonus | Overrides the event default for this challenge, e.g. `100,50,25`, or `off`. |

After saving, upload files in the **Files** section (drag and drop). Files are only downloadable by signed-in players, and each shows a SHA-256 hash so players can verify it.

Use **Preview as player** to check how it looks. **Duplicate** copies a challenge (without files) as a draft.

**Moving challenges between events:** on the challenge list, **Import / export** downloads every challenge as JSON, optionally with files. Importing adds them as *Hidden* so nothing goes live by accident.

### 4. Participants — People → Invites & import

Players can register themselves at **/register**. To register a list of people instead:

1. Paste CSV lines as `email,name,team`, or upload a .csv file. Name and team are optional.
   ```
   ananya@college.edu,Ananya Das,Team Alpha
   rohan@college.edu,Rohan Bora,Team Alpha
   priya@college.edu,Priya Sharma,
   ```
2. Keep **Email the invites** ticked and click **Create invites**.
3. Each person gets a one-time link to choose a username and password. People with the same team name are put on the same team, which is created if needed.

Invite emails go through a queue. The cron job sends them every minute within the hourly limit, and **Send a batch now** pushes one out immediately. **Resend** issues a fresh link to anyone who lost theirs.

### 5. Final checks

- [ ] Event start and end times are set, or you plan to control the event manually.
- [ ] Every visible challenge has a flag. The list shows a red **No flag** badge if one is missing.
- [ ] You've solved each challenge once from a test player account. Hide that team afterwards: **People → Teams** → team → **Hide from scoreboard**.
- [ ] The test email arrived.
- [ ] Rules page is up to date.

## During the event

### Watching the event — Event → Overview

- **Top tiles:** registered players, teams, players active in the last 15 minutes, solves, submissions and accuracy.
- **Charts:** score progression of the leaders, submissions per hour, solves vs. wrong attempts per challenge, sign-ups, category completion, and a weekday/hour activity map.
- **Difficulty check:** a challenge with many wrong attempts and no solves may be broken or too hard. Open its **Stats** page (chart icon on the challenge list) to see the most common wrong flags. These often reveal a typo in the flag or a misleading description.

The public **Scoreboard** shows the "How far teams got" chart. Each line shows what percentage of scoring teams solved at least 10%, 20% … 100% of the challenges, overall and per category.

### Announcements — Event → Announcements

Use announcements for clarifications, hints for everyone and new challenge waves. Players see a toast, a dot on the bell icon, and the post on their dashboard. **Pin** keeps an announcement on top, and **Also email all verified players** sends it by email too (use this sparingly).

### Awarding or deducting points — Event → Scoring & awards

- **Type:** Unique solve, Write-up, Bug report, Other award or Penalty. Penalties are subtracted.
- **Who:** a specific **Player** (credits their team), or one or more **Teams**.
- **Related challenge** (optional) and a **Reason**, which the team sees.
- **Show on the team page and live activity feed:** untick this for a private adjustment.

Shortcuts: on **Submissions**, the **Bonus** button next to a correct solve pre-fills the form for that team, player and challenge. On a team's admin page, the award icon next to each solve does the same.

The **Award history** table lists every award. **Revoke** removes the points (and **Restore** brings them back).

### Fixing problems

| Situation | What to do |
|---|---|
| A team's correct flag failed during an outage | **Submissions** → *Record a solve manually*. Tick the bonus option if they would have been first. |
| A solve should not count | **Teams** → team → trash icon next to the solve. Later solvers move up a place. |
| A challenge is broken | Edit it and set **Visibility** to *Hidden*. Solves are kept. Fix it, then make it visible again. |
| Wrong flag configured | Edit the challenge's flags. Existing solves are unaffected. If players submitted the right answer earlier, find them in **Submissions** (filter by challenge) and record manual solves. |
| Player can't sign in | They can use **Email code** on the sign-in page or **Forgot password**. You can mark their email verified, move them to another team, or ban them under **Users**. |
| Need to stop submissions briefly | **Settings → Pause submissions**. Everything stays visible. |
| Need to take the site down | **Settings → Maintenance mode**. Only admins can use the site. |

### Spotting cheating — Integrity → Anti-cheat

The page flags:
- The same wrong flag submitted by several teams (a shared guess or leak).
- IP addresses used by more than one team.
- Teams repeatedly solving the same challenge within a minute of each other.
- Bursts of 8+ wrong flags in a minute (brute forcing).
- Repeated failed sign-ins.

None of these is proof on its own; campus Wi-Fi puts many teams on one IP. Investigate in **Submissions** (click an IP to filter by it), then use a **Penalty**, **Disqualify team** (removes them from the scoreboard), or **Ban user** if needed.

Every admin action and every solve is recorded in **Integrity → Audit log**.

## After the event

1. When the end time passes, submissions close automatically. Challenges stay readable.
2. To reveal the final standings, clear **Freeze scoreboard** in Settings.
3. Download results from the Overview buttons or the export links: **Scoreboard CSV**, **Solves CSV**, plus Submissions and Users CSVs on their pages.
4. Export challenges (**Challenges → Import / export**) to reuse them next time.
5. Post a closing announcement with the winners.

## Housekeeping

- **Server cron:** the job `php app/bin/cron.php` must run every minute (see README). Without it, invite and announcement emails stay queued.
- **Secrets:** SMTP, database and OAuth credentials live only in `app/config/.env` on the server.
- **Google / GitHub sign-in:** the buttons appear once the client IDs are added to `.env`. See the README for setup.
