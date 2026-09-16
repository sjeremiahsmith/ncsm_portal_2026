# National County Sports System

A full-featured web-based sports management system for the Ministry of Youth & Sports, Republic of Liberia. Manage player registrations, approval workflows, matches, live scores, league standings, documents, and reports across multiple sports disciplines.

## Features

### 👥 Role-Based Access
- **Super Admin** — full system control: manage players, matches, documents, counties, reports, seed data
- **County Coordinator** — register and manage players within their assigned county group (A/B/C/D)
- **Association Admin** — approve/reject player registrations for their specific sport (LFA, LKA, LBA, LAA)

### 📋 Player Registration
- Full name, DOB, gender, and nationality
- Age dropdown (0–35), city, last club, current club
- County of representation (grouped by A/B/C/D)
- Sport discipline with primary level (1st Division–Virgin, Mass)
- Photo upload with preview
- Save as draft or submit for approval

### ✅ Approval Workflow
- Submit → Association Admin reviews → Approve / Return for Revision / Reject
- Full audit trail via `approval_workflow` table
- Notifications sent to registrant on action

### 🏟️ Games & Live Scores
- Create matches with home/away teams, date, round, group
- Quick score entry for live/in-progress matches
- Auto-calculated league standings (P/W/D/L/GF/GA/GD/Pts)
- Kickball-specific standings with HRF/HRA/HRD columns
- Live scores page auto-refreshes every 30 seconds
- Color-coded status badges (Scheduled / LIVE / Completed)

### 📄 Document Management
- Upload documents (PDF, DOC, DOCX, XLS, XLSX, TXT, JPG, PNG) — Super Admin only
- All users can browse and download documents
- Paginated listing with file size and upload info

### 📊 Reports
- Overview stats, per county, per sport, and per group views
- CSV export for offline analysis

### 📈 Dashboard
- Stat cards: Total Players, Female, Male, Approved, Rejected, Counties
- Recent registrations table with inline actions
- Charts: gender distribution (doughnut), sports distribution (bar), players by county (polar area)
- Pending approvals widget (Association Admin only)

## Tech Stack

| Component | Technology |
|-----------|-----------|
| **Backend** | PHP 8.x (PDO, prepared statements) |
| **Database** | PostgreSQL (Render PostgreSQL) |
| **Server** | Apache (XAMPP) |
| **CSS** | Bootstrap 5.3.2, Bootstrap Icons 1.11.3 |
| **JavaScript** | jQuery 3.7.1, Select2 4.1.0, Chart.js 4.4.1 |
| **Auth** | bcrypt password hashing, PHP sessions |

## Installation

### Prerequisites
- XAMPP (or any Apache + PHP + MySQL stack)
- PHP 8.0+
- PostgreSQL 13+

### Setup
```bash
# 1. Clone the repository into XAMPP's htdocs
git clone https://github.com/your-username/sports-meet-portal.git
# or copy the folder to C:\xampp\htdocs\<your-folder>

# 2. Create/select the target PostgreSQL database and import the schema
psql "$DATABASE_URL" -f database/schema.sql

Do not import `database/sports_meet_portal.sql`; it is the legacy MySQL dump kept only
as a possible source for the migration utility.

# 3. Configure the PostgreSQL connection with DATABASE_URL or NCSM_DATABASE_URL.

# 4. Start Apache and PostgreSQL locally, or use Docker.

# 5. Seed the database with default data from the command line
$env:NCSM_SEED_PASSWORD = "use-a-unique-password-at-least-12-characters"
php seed_cli.php
# Log in with username `admin` and the seed password, then change it.

# 6. Access the application
# Visit: http://localhost/<your-folder>/
```

> **Note:** The application auto-detects its folder, so it works from any
> folder name with no code changes.

## Render Deployment

This repository includes a `Dockerfile` and `render.yaml` for a Docker web service and
Render PostgreSQL database:

- `NCSM_APP_URL` — the complete HTTPS application URL with no trailing slash
- `DATABASE_URL` — Render's internal PostgreSQL connection string
- `NCSM_SEED_PASSWORD` — a unique password of at least 12 characters

### Required deployment order

1. Deploy the blueprint in `render.yaml`. It creates a Render PostgreSQL database and injects
	its `connectionString` into `DATABASE_URL`. Set the remaining `sync: false` variables:
	`NCSM_APP_URL` and `NCSM_SEED_PASSWORD`. Keep secrets in Render's environment settings,
	never in Git.
2. Import the schema into Render PostgreSQL:

	```bash
	psql "$DATABASE_URL" -f database/schema.sql
	```

3. If this is a new installation with no existing data, seed once after the schema import.
	Run this from a trusted machine with the target database environment variables set:

	```bash
	NCSM_SEED_PASSWORD='use-a-new-password-at-least-12-chars' php seed_cli.php
	```

	Change the `admin` password immediately after the first login.
4. For existing MySQL records, do not run the PostgreSQL schema against the old database.
	Import the schema into the new Render database, set `NCSM_SOURCE_DB_HOST`,
	`NCSM_SOURCE_DB_PORT`, `NCSM_SOURCE_DB_NAME`, `NCSM_SOURCE_DB_USER`, and
	`NCSM_SOURCE_DB_PASS`, and keep `DATABASE_URL` set to Render PostgreSQL, then run:

	```bash
	php database/migrate_existing.php
	```

	Run this before seeding so existing IDs and users are preserved. The migration preserves
	IDs and skips duplicate rows. Take a backup first and verify row counts before switching
	traffic. After migration, run `php seed_cli.php` once only if default reference data or
	accounts are missing.
5. Uploads are intentionally excluded from Git and Docker. Copy the old uploads directory
	to the persistent disk using the configured target path:

	```bash
	php scripts/migrate_uploads.php /path/to/old/uploads
	```

	On Render, the persistent disk is mounted at `/var/www/html/uploads`. For large or highly
	available media, use object storage instead of the single-service disk.

After deployment, verify `/`, login, document download authorization, player registration,
an existing migrated player, and an uploaded image. Never paste database passwords into the
repository, README, shell history, or chat.

## Default Users

The initial accounts are created with the one-time `NCSM_SEED_PASSWORD` value. Change it
after the first login and do not commit that value to the repository.

### Super Admin
| Username | Role |
|----------|------|
| `admin` | System Administrator |

### County Coordinators (viewer-only)
| Username | County | Group |
|----------|--------|-------|
| `lofa_coord` | Lofa | C |
| `bong_coord` | Bong | B |
| `gedeh_coord` | Grand Gedeh | C |
| `kru_coord` | Grand Kru | D |

### County Admins (can register players)
| Username | County | Group |
|----------|--------|-------|
| `lofa_admin` | Lofa | C |
| `bong_admin` | Bong | B |
| `gedeh_admin` | Grand Gedeh | C |
| `kru_admin` | Grand Kru | D |

### Sports Bureau
| Username | Role |
|----------|------|
| `sports_coord` | Super Admin (Sports Bureau) |

### Association Admins
| Username | Association | Sport |
|----------|-------------|-------|
| `lfa_admin` | LFA | Football |
| `lka_admin` | LKA | Kickball |
| `lba_admin` | LBA | Basketball |
| `laa_admin` | LAA | Athletics |

## Database Overview

**Database name:** supplied through Render's `DATABASE_URL`.

### Tables (16)
| Table | Purpose |
|-------|---------|
| `users` | System users with role-based access |
| `counties` | 15 counties grouped into A/B/C/D |
| `sports_disciplines` | Football, Kickball, Basketball, Athletics |
| `players` | Core player registration with 20+ fields |
| `approval_workflow` | Audit trail for registration approvals |
| `matches` | Fixtures, scores, status per sport |
| `match_goals` | Goal scorers per match |
| `match_reports` | Match commissioner reports (cards, notes) |
| `match_report_cards` | Individual card incidents |
| `match_squad_players` | Starting XI / substitutes per report |
| `documents` | Uploaded file metadata |
| `notifications` | User notifications |
| `activity_logs` | Audit trail for all actions |
| `contact_messages` | Contact form submissions |
| `gallery_photos` | Photo gallery uploads |
| `videos` | Video uploads and embed links |

## County Groupings

| Group A | Group B | Group C | Group D |
|---------|---------|---------|---------|
| Montserrado | Nimba | Grand Gedeh | Grand Cape Mount |
| Margibi | Lofa | River Gee | Bomi |
| Grand Bassa | Bong | Sinoe | Grand Kru |
| River Cess | Gbarpolu | Maryland | |

## Roles & Capabilities

| Capability | Super Admin | County Coordinator | Association Admin |
|------------|:-----------:|:------------------:|:-----------------:|
| Register players | ✅ | ✅ (group-scoped) | ❌ |
| List players | ✅ (all) | ✅ (group-scoped) | ✅ (sport-scoped) |
| Edit players | ✅ (any) | ✅ (own group + own drafts) | ❌ |
| Delete players | ✅ | ❌ | ❌ |
| Approve/Reject players | ❌ | ❌ | ✅ (sport-scoped) |
| Manage matches | ✅ | ❌ | ❌ |
| Upload documents | ✅ | ❌ | ❌ |
| View reports | ✅ | ✅ (group-scoped) | ✅ (sport-scoped) |
| Seed database | ✅ | ❌ | ❌ |

## Project Structure

```
├── auth/                    # Login / logout
├── assets/
│   ├── css/style.css        # Custom styles
│   ├── js/main.js           # jQuery UI interactions, Chart.js
│   └── images/              # Logos, county flags, default avatar
├── database/schema.sql      # Full MySQL schema
├── includes/
│   ├── config.php           # Constants, session start
│   ├── db.php               # PDO singleton
│   └── functions.php        # 30+ helper functions
├── pages/
│   ├── dashboard.php        # Home page with stats, charts, recent players
│   ├── profile.php          # User profile
│   ├── change_password.php  # Password change
│   ├── players/             # Register, list, view, edit, delete
│   ├── approvals/           # Pending reviews, history
│   ├── games/               # Live scores, manage, standings, kickball standings
│   ├── counties/manage.php  # County reference
│   ├── documents/list.php   # Document browser
│   └── reports/index.php    # Tabbed reports with CSV export
├── templates/
│   ├── header.php           # Navbar, sidebar, HTML head
│   └── footer.php           # JS includes, closing tags
├── uploads/
│   ├── photos/              # Player photos
│   └── documents/           # Uploaded documents
├── index.php                # Entry point
└── seed_cli.php             # Database seeder (CLI only)
```

## Screenshots

*(Add screenshots of the dashboard, registration form, games page, and standings here.)*

## License

This project is developed for the Ministry of Youth & Sports, Republic of Liberia.
