# NFSU CTF Platform

A production-ready Capture The Flag competition platform built for university-level cybersecurity competitions.

## Tech Stack

- **Backend**: Node.js + Express
- **Database**: PostgreSQL (Supabase)
- **Frontend**: HTML + CSS + Vanilla JS
- **Auth**: JWT (httpOnly cookies + Bearer tokens)
- **Password Hashing**: bcrypt (12 rounds)
- **Validation**: Zod
- **Logging**: Pino
- **Real-time**: WebSocket

## Quick Start

### 1. Install Dependencies

```bash
npm install
```

### 2. Configure Environment

```bash
cp .env.example .env
# Edit .env with your Supabase credentials
```

Required variables:
- `DATABASE_URL` — Supabase PostgreSQL connection string
- `SUPABASE_URL` — Supabase project URL
- `SUPABASE_SERVICE_ROLE_KEY` — Supabase service role key
- `JWT_SECRET` — A strong random secret (generate with `openssl rand -hex 32`)

### 3. Run Database Migration

Execute the SQL files against your Supabase database (via SQL Editor in dashboard):

1. Run `database/schema.sql` — Creates all tables and indexes
2. Run `database/seed.sql` — Creates default admin account and sample data

### 4. Create Supabase Storage Bucket

In your Supabase dashboard:
1. Go to Storage
2. Create a new bucket named `challenge-files`
3. Set it to Public

### 5. Start the Server

```bash
# Development (with auto-reload)
npm run dev

# Production
npm start
```

Server starts at `http://localhost:3000`

### 6. Default Admin Login

- **Username**: `admin`
- **Password**: `admin123`
- **⚠️ Change this immediately in production!**

## Features

### For Teams
- Dashboard with stats and announcements
- Challenge browser with category filters
- Flag submission with format validation (`NFSUCTF{...}`)
- Real-time leaderboard with auto-refresh
- Team profile with solve history
- Rules & information page

### For Admins
- Full team management (create, reset passwords, bonus/deduct points, activate/deactivate)
- Challenge management (CRUD, file attachments, flag setting)
- Event lifecycle control (start, stop, freeze, archive, maintenance mode)
- Announcements system
- Analytics dashboard
- CSV exports (leaderboard, solves, submissions)
- Emergency overrides (manual solve, remove solve, edit points, recalculate scores)
- Anti-cheat monitoring (suspicious activity detection)
- Admin action logging

### Security
- Transaction-safe scoring with UNIQUE constraint protection
- bcrypt-hashed passwords and flags
- Flag hashes never exposed via API
- Rate limiting on login and submissions
- Input validation with Zod
- IP and user-agent logging
- CORS and Helmet protection
- httpOnly cookie support

## Project Structure

```
├── backend/
│   ├── server.js              # Express server entry point
│   ├── controllers/           # Route handlers
│   │   ├── admin.js           # Admin operations
│   │   ├── announcements.js   # Public announcements/info
│   │   ├── auth.js            # Authentication
│   │   ├── challenges.js      # Challenge operations
│   │   ├── leaderboard.js     # Leaderboard
│   │   └── team.js            # Team profile
│   ├── middleware/
│   │   ├── auth.js            # JWT + role verification
│   │   ├── errorHandler.js    # Global error handler
│   │   └── rateLimiter.js     # Rate limiting
│   ├── routes/                # Express route definitions
│   ├── services/
│   │   ├── scoring.js         # Transaction-safe scoring
│   │   └── export.js          # CSV generation
│   ├── utils/
│   │   ├── db.js              # PostgreSQL pool
│   │   ├── logger.js          # Pino logger
│   │   ├── supabase.js        # Supabase storage client
│   │   └── websocket.js       # WebSocket server
│   └── validators/            # Zod schemas
├── frontend/
│   ├── index.html             # SPA shell
│   ├── css/main.css           # Dark cyber theme
│   └── js/
│       ├── api.js             # API client
│       ├── router.js          # Hash router
│       ├── utils.js           # Toast, formatting, helpers
│       ├── websocket.js       # WS client
│       └── pages/             # Page components
├── database/
│   ├── schema.sql             # Database schema
│   └── seed.sql               # Seed data
├── .env.example               # Environment template
└── package.json
```

## API Endpoints

### Public
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/health` | Health check |
| GET | `/api/event/status` | Event status |
| GET | `/api/leaderboard` | Leaderboard |
| GET | `/api/announcements` | Active announcements |
| GET | `/api/info` | Rules/info content |

### Auth
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/login` | Team login |
| POST | `/api/admin/login` | Admin login |
| POST | `/api/logout` | Logout |
| GET | `/api/me` | Current user |

### Team (Auth Required)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/challenges` | List challenges |
| GET | `/api/challenges/:id` | Challenge detail |
| GET | `/api/challenges/:id/stats` | Solve stats |
| POST | `/api/submit` | Submit flag |
| GET | `/api/team/profile` | Team profile |

### Admin (Auth + Admin Required)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST | `/api/admin/teams` | Team management |
| POST | `/api/admin/challenges` | Challenge CRUD |
| POST | `/api/admin/event/control` | Event lifecycle |
| POST | `/api/admin/announcements` | Announcements |
| GET | `/api/admin/analytics` | Analytics |
| GET | `/api/admin/export/:type` | CSV exports |
| POST | `/api/admin/solve/manual` | Emergency overrides |

## Deployment

### Production Checklist

1. Set `NODE_ENV=production`
2. Change default admin password
3. Use a strong `JWT_SECRET` (`openssl rand -hex 32`)
4. Enable Supabase RLS policies
5. Restrict `SUPABASE_SERVICE_ROLE_KEY` to backend only

### Deploy to Render (Recommended)

Render supports Express + WebSockets and has a free tier.

**Step 1: Push to GitHub**
```bash
git init
git add .
git commit -m "CTF Platform"
git remote add origin https://github.com/YOUR_USERNAME/YOUR_REPO.git
git push -u origin main
```

**Step 2: Create Render Web Service**
1. Go to [render.com](https://render.com) → Sign up / Log in
2. Click **New** → **Web Service**
3. Connect your GitHub repo
4. Configure:
   - **Name**: `ctf-platform`
   - **Runtime**: `Node`
   - **Build Command**: `npm install`
   - **Start Command**: `node backend/server.js`

**Step 3: Add Environment Variables**
In Render dashboard → your service → **Environment** tab, add:

| Key | Value |
|-----|-------|
| `NODE_ENV` | `production` |
| `DATABASE_URL` | `postgresql://postgres:...` (from Supabase) |
| `SUPABASE_URL` | `https://your-project.supabase.co` |
| `SUPABASE_SERVICE_ROLE_KEY` | Your service role key |
| `SUPABASE_ANON_KEY` | Your anon key |
| `JWT_SECRET` | Your random 32-byte hex string |
| `PORT` | `10000` |

**Step 4: Deploy**
Click **Deploy** — Render will build and start your app automatically.
Your CTF will be live at `https://ctf-platform.onrender.com`.

> **Note**: The included `render.yaml` blueprint file can also auto-configure everything — use Render's "Blueprint" deploy if preferred.

### Using PM2 (VPS)

```bash
npm install -g pm2
pm2 start backend/server.js --name ctf-platform
pm2 save
```

### Using Docker

```bash
docker build -t ctf-platform .
docker run -p 3000:3000 --env-file .env ctf-platform
```

## License

MIT
