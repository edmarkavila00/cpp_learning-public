# CppQuest

CppQuest is a browser-based, gamified C++ learning platform. Learners practice
program tracing and C++ concepts through challenges, progress through a themed
adventure, write and run C++17 programs in Code Lab, and track progress with
experience points, levels, streaks, badges, reviews, and leaderboards. Co-op
rooms and invite-code study groups add collaborative practice.

This repository is an educational project/MVP intended to run locally with
XAMPP. It is not a hosted commercial learning management system.

This clean release copy has no private Git history and excludes local uploads,
installed dependencies, editor-specific task files, the raw database dump, and
the destructive development seed/reset utilities.

The project is distributed under the [MIT License](./LICENSE).

## Technology stack

| Area | Technology | How it is used |
| --- | --- | --- |
| Web server / local environment | Apache and MySQL through XAMPP | Serves the PHP application and provides the local database environment. |
| Server-side application | PHP | Handles pages, sessions, authentication, challenge evaluation, progress, and JSON endpoints. |
| Database access | PDO | Connects PHP to MySQL and executes parameterized database queries. |
| Database | MySQL | Stores accounts, challenges, progress, profiles, groups, reviews, and game state. |
| Browser interface | HTML, CSS, and vanilla JavaScript | Renders the game interface and handles browser interactions and settings. There is no PHP or JavaScript application framework. |
| Utility CSS | Tailwind CSS 3 | Generates the locally served `assets/tailwind.css` stylesheet from PHP templates and `assets/tailwind.input.css`. |
| Shared visual theme | Custom CSS | Provides the pixel-art styling, responsive presentation, and light/dark theme. |
| Icons, syntax highlighting, fonts | Font Awesome 6.5, Prism 1.29, Google Fonts (Pixelify Sans and Silkscreen) | Loaded from external CDNs by the shared header/theme. Internet access is needed for these external assets; custom CSS has fallback fonts. |
| Build tooling | Node.js and npm | Installs Tailwind's development dependency, builds the generated CSS, and runs the PHP smoke-check script. |
| C++ learning language | C++17 | The language used by the Code Lab compiler and its programming missions. |
| Code Lab service | Node.js built-in HTTP server | Accepts authenticated local requests from PHP and starts isolated compiler containers. No npm runtime package is used by this server. |
| Code execution | Docker, Python 3.12, and `g++` | Each Code Lab submission is compiled and tested inside a disposable Linux container. Python is the small in-container test harness. |
| Automated checks | PHP smoke-check script | Checks for important expected integration points in the source. It is not a comprehensive functional, browser, or penetration test. |

### Technologies not used

The application does not currently use Laravel, React, Vue, a JavaScript
framework, a hosted identity provider, or a cloud database. PHP templates
render pages on the server; JavaScript enhances selected browser interactions.

## Main capabilities

- **Accounts and profiles:** registration, login/logout, onboarding, profile
  details, avatar selection, and signed-in password changes.
- **Challenge practice:** a catalog of 90 seeded challenges, arranged by
  difficulty and topic, with code/output prediction, hints, answer feedback,
  attempt/progress tracking, and unlock progression.
- **Code Lab:** write C++17 programs, compile and run against mission test cases,
  view compiler/runtime feedback, and earn progress for newly solved missions.
- **Adventure:** answer themed C++ questions across challenge-linked stages,
  receive explanations, and track cleared stages.
- **Gamification:** XP, levels, streaks, badges, rewards/purchases, and a
  leaderboard.
- **Co-op rooms:** host or join private rooms by invite code. Room modes include
  team answer, study room/chat, and speed race.
- **Spaced review:** solved challenges can be revisited through a due-review
  queue; missed reviews are rescheduled.
- **Study groups:** create/join invite-code groups, assign challenges, and
  track member completion. Group owners do not receive submitted answers.
- **Challenge reports:** learners can submit issue reports; configured reviewers
  can manage them in the admin reports page.
- **Settings and preferences:** theme, sound/music, and reduced-motion choices.
  Appearance/audio preferences and answer drafts are stored in the current
  browser, not synchronized to the account.

## How the application is organized

### Typical request flow

1. The browser requests a PHP page from Apache.
2. PHP starts or reads the session, checks authentication/authorization where
   needed, and uses PDO to read/write MySQL.
3. PHP returns HTML or a JSON response. Browser JavaScript handles interactions
   such as answer submission, settings, and co-op requests.
4. For a Code Lab run, PHP validates the signed-in user, CSRF token, mission,
   source size, and progression. It calls the local Node.js runner over
   `127.0.0.1` with a private bearer token.
5. The runner validates the request and launches a fresh Docker container.
   The container compiles the source with `g++ -std=c++17`, runs the configured
   test cases, and returns results through the runner to the browser.

### Important directories and files

| Path | Purpose |
| --- | --- |
| `*.php` in the project root | Page controllers and form/API endpoints for the learner-facing features. |
| `includes/auth.php` | Session configuration, login checks, CSRF helpers, and progression/unlock helpers. |
| `includes/db.php` | PDO connection to the `cpp_learning` MySQL database. |
| `includes/header.php` | Shared page header/navigation, stylesheets, browser scripts, and game UI helpers. |
| `includes/code_game.php` | Code Lab missions, sample inputs/outputs, and expected test cases. |
| `includes/adventure_game.php` | Adventure questions and game rules. |
| `includes/coop.php` | Shared co-op room lookup and helper logic. |
| `includes/code_runner_client.php` | PHP-to-local-runner HTTP client and response validation. |
| `db/schema.sql` and other `db/*.sql` files | Base schema, challenge catalog seed data, content upgrades, and feature migrations. |
| `db/setup.bat` | Windows/XAMPP database setup and upgrade script. |
| `code_runner/server.js` | Local authenticated HTTP service that coordinates container runs. |
| `code_runner/runner.py` | In-container source compilation, test execution, and output comparison. |
| `code_runner/Dockerfile` | Builds the Linux compiler image with Python and `g++`. |
| `assets/tailwind.input.css` | Tailwind directives used to generate the utility stylesheet. |
| `assets/tailwind.css` | Generated, locally served Tailwind output; rebuild after changing utility classes. |
| `assets/theme.css` | Shared visual theme, typography, pixel styling, and dark-mode colors. |
| `tests/run_smoke_tests.php` | Lightweight source-level checks for important application wiring. |
| `package.json` / `package-lock.json` | Tailwind build and smoke-test commands plus locked npm development dependencies. |

### Database overview

The database is named `cpp_learning`. Its base tables include:

- `users`, `challenges`, and `user_progress` for accounts, the challenge
  catalog, and solved/attempt progress.
- Feature tables created by `db/features.sql`, including `user_profiles`,
  `code_game_progress`, `adventure_progress`, `user_badges`, `user_xp_purchases`,
  `coop_rooms`, `coop_room_members`, `coop_room_messages`,
  `challenge_reviews`, `challenge_reports`, `study_groups`,
  `study_group_members`, and `study_group_assignments`.

Foreign keys connect user- and challenge-owned data, and unique keys prevent
duplicate progress or group membership records. Setup scripts are intended to
preserve existing account/progress data when upgrading. Back up the database
before applying changes to any database with data you need to keep.

## Requirements

For the documented Windows/XAMPP setup:

- Windows with XAMPP installed. The scripts default to `C:\xampp`.
- Apache and MySQL running in XAMPP.
- PHP with PDO MySQL and cURL extensions enabled.
- Node.js and npm for building CSS and running the smoke checks.
- Docker Desktop using Linux containers, Node.js 18 or newer, and PHP cURL for
  the Code Lab runner.
- A modern browser. Internet access is needed to load the external Font
  Awesome, Prism, and Google Fonts assets.

The application paths and setup scripts assume it is served at
`http://localhost/cpp_learning/`. A different installation path requires
updating the scripts and application URLs that use `/cpp_learning`.

## Local setup

1. Put the repository in `C:\xampp\htdocs\cpp_learning`.
2. Start Apache and MySQL from the XAMPP control panel.
3. Run `db\setup.bat` to create/upgrade the database and load the challenge
   catalog. The script uses the XAMPP MySQL client at
   `C:\xampp\mysql\bin\mysql.exe` and defaults to the local `root` account with
   no password. Adjust it for your local database configuration if needed.
4. In PowerShell, from the project directory, install the locked CSS build
   dependencies and generate Tailwind CSS:

   ```powershell
   npm ci
   npm.cmd run build:css
   ```

5. Open `http://localhost/cpp_learning/` in a browser and register an account.
6. To use Code Lab, follow [the Code Lab runner setup](./code_runner/README.md).
   The runner is a separate local service and must be started while using Code
   Lab.

## Build and checks

Build the local Tailwind stylesheet after adding or changing utility classes
used by PHP templates:

```powershell
npm.cmd run build:css
```

Run the repository's configured smoke checks:

```powershell
npm.cmd test
```

The test script invokes `tests/run_smoke_tests.php`, which checks for selected
source-code strings and schema tables. Passing these checks does **not** prove
that all user workflows work in a browser, that database migrations work on
every MySQL version, or that the system is secure under attack. Supplement them
with manual workflow checks and user evaluation before a capstone defense.

## Security and privacy notes

- Session cookies are configured as HTTP-only and SameSite=Lax, and marked
  Secure when the app is served over HTTPS.
- Forms and relevant endpoints use CSRF-token checks. Database operations use
  PDO prepared statements in the application code.
- The Code Lab runner binds to localhost, checks a private token, limits request
  sizes/concurrency, and runs compiler jobs in disposable containers without
  network access. The containers use a read-only root filesystem, a non-root
  user, dropped capabilities, and resource/time/output limits.
- Keep the runner's token configuration outside the web root. Do not expose
  port `8787`, mount host files into compiler containers, or use this local
  setup as a public code-execution service without a separate security review.
- Study groups use private invite codes, but group creators are not verified
  teachers. Group owners can see member names and assignment completion. Do not
  store regulated or sensitive student records in this MVP.
- `CPPQUEST_ADMIN_IDS` is an Apache environment variable containing comma-
  separated account IDs allowed to access `admin_reports.php`, for example
  `1,2`. Configure it only for trusted reviewers.
- There is no email delivery provider configured, so email-based password
  recovery is not available. Users can change their password while signed in.
- Code drafts and theme/audio preferences are browser-local and do not follow a
  learner to another device. Account data and saved learning progress are in
  the MySQL database, so they can be available after signing in on another
  device **only if both devices reach the same server/database**.

## Known limitations and capstone evaluation

- This is a local PHP/MySQL MVP, not a cloud-hosted or production-scaled service.
- The Code Lab has a small authored mission set; it is not an unrestricted
  online compiler platform.
- Group creators are not institutionally verified, and there is no email
  verification or self-service password reset.
- Browser-local preferences and drafts are not synchronized between devices.
- Smoke tests are source checks rather than comprehensive automated
  integration, browser, accessibility, load, or security tests.
- Feature availability alone does not demonstrate learning effectiveness.
  Evaluate the platform with the intended learner group, document the test
  method and results, and obtain any required adviser/institution approval
  before collecting participant data.

For the runner's detailed setup and isolation behavior, see
[code_runner/README.md](./code_runner/README.md).
