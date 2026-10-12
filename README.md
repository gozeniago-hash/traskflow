# TaskFlow — work management + client portal

An Asana-style project management app built with **Laravel 13**, **Livewire 4** and **MySQL**, with a separate **client portal** for sharing progress, files and approvals with your clients.

## Features

**Work management (internal team)**
- **Workspaces** with a switcher; sign-up creates your own workspace. Roles: Owner, Admin, Member, Client.
- **Teams** to group people; projects can be visible to the whole workspace, a team, or only project members.
- **Projects** with color, owner, team, client, status (On track / At risk / Off track / On hold / Complete), start and due dates, and archive.
- **Five views per project:** Overview, List, Board, Calendar and Files.
  - **List:** collapsible sections, drag-and-drop ordering between sections, inline "Add task", filters.
  - **Board:** Kanban columns (sections) with drag-and-drop cards.
  - **Calendar:** month grid by due date; add a task on any day.
  - **Overview:** description, progress, members, "coming up", workload by person, and **status updates**.
  - **Files:** every attachment in the project, plus project-level uploads.
- **Tasks** open in a slide-over panel: assignee, due and start dates, priority, section, description, **subtasks**, **attachments**, **comments** and an **activity log**.
- **My tasks:** everything assigned to you across projects, grouped by Overdue / Today / Next 7 days / Later.
- **Home** dashboard, global **search**, member **invitations** by email (with copyable links).

**Client portal**
- Link a project to a **client** and turn on **Show in client portal**.
- Clients see only what you share: tasks marked **Visible to client**, shared status updates and shared files. Internal comments stay internal.
- **Approvals:** request approval on a shared task; the client clicks **Approve** or **Request changes** (with feedback).
- Clients can **comment**, **upload files** to shared tasks, and **submit new requests**, which land in a "Client requests" section.

## Requirements

- PHP **8.3+** with `pdo_mysql`, `mbstring`, `fileinfo`, `openssl`
- Composer 2
- **MySQL 8.0+** (or MariaDB 10.6+)
- Node 20+ is **optional**. Compiled CSS/JS ships in `public/build`; you only need Node to change styles.

## Setup

```bash
cd taskflow
composer install
cp .env.example .env
php artisan key:generate
```

Create a database, then set the credentials in `.env`:

```sql
CREATE DATABASE taskflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=taskflow
DB_USERNAME=root
DB_PASSWORD=your-password
```

Run the migrations with demo data and start the server:

```bash
php artisan migrate --seed
php artisan serve
```

Open http://localhost:8000.

### Demo accounts (password: `password`)

| Who | Email | Sees |
|---|---|---|
| Owner | `admin@taskflow.test` | Everything |
| Admin | `maya@taskflow.test` | Everything |
| Member | `leo@taskflow.test`, `jo@taskflow.test` | Projects in their teams or shared with the workspace |
| Client | `client@brightside.test` | Brightside portal only |

For a clean install without demo data, run `php artisan migrate` and register at `/register`.

## Email

Invitations and password resets are sent by email. By default `MAIL_MAILER=log`, so emails are written to `storage/logs/laravel.log`. Invitation screens also show a copyable link. Configure SMTP (or Mailgun, SES, Postmark…) in `.env` for real delivery.

## File uploads

Attachments are stored privately in `storage/app/private/attachments` and served only through an authorization check.

- App limit: `TASKFLOW_MAX_UPLOAD_KB` in `.env` (default 20 MB).
- PHP limits must allow it too: raise `upload_max_filesize` and `post_max_size` in `php.ini`.
- To use S3, configure the `s3` disk and change `'local'` in `App\Models\Attachment::storeUpload()`.

## Changing styles

```bash
npm install
npm run dev     # or: npm run build
```

Tailwind CSS v4 lives in `resources/css/app.css`. The drag-and-drop helper (SortableJS) is in `resources/js/app.js`.

## Tests

```bash
php artisan test
```

Feature tests in `tests/Feature/AccessTest.php` cover login redirects, registration, project privacy, and what clients can see.

## How it fits together

```
app/
  Models/            Workspace, Team, Client, Project, Section, Task, Comment,
                     Attachment, StatusUpdate, Activity, Invitation, User
  Policies/          ProjectPolicy, TaskPolicy, AttachmentPolicy  (who can view/edit)
  Http/Middleware/   EnsureWorkspace, EnsureMember, EnsureClient, EnsureAdmin
  Livewire/
    Home, MyTasks, Search, Profile, TaskPanel
    Projects/        Index, Show, Overview, ListView, BoardView, CalendarView, Files
    Admin/           Teams, Clients, Members, Settings
    Portal/          Dashboard, ProjectView
resources/views/
  layouts/           app (team sidebar), portal (client), guest (auth)
  livewire/…         one view per component
```

**Access rules in one place:** `Project::scopeVisibleTo()` decides which projects a user can see:
- Admins and owners see everything in the workspace.
- Members see workspace-wide projects, their teams' projects, and projects they own or belong to.
- Clients see only their own client's projects with the portal turned on.

Policies build on that scope, and client views additionally filter by `client_visible`.

## Deploying

Standard Laravel deployment applies. For example, with Forge, Ploi, Laravel Cloud, a VPS with Nginx + PHP-FPM, or Railway:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Set `APP_ENV=production`, `APP_DEBUG=false`, a real `APP_URL`, and mail settings. Make sure `storage/` and `bootstrap/cache/` are writable.

## Ideas for next iterations

Timeline/Gantt view, custom fields, task dependencies, project templates, in-app notifications and an inbox, recurring tasks, and time tracking.
