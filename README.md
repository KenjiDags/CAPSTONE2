# TESDA Inventory

PHP/MySQL inventory system. Git shares committed source files, migrations, and
static assets. It does not copy the running MySQL database, installed libraries,
uploads, browser storage, or local configuration between computers.

## Share changes with your teammate

Run these commands in the project folder:

```powershell
git status --short
git add <changed-file-or-folder>
git diff --cached --stat
git commit -m "Describe the change"
git push origin main
```

Replace the angle-bracket placeholder with the actual files or folders. Check
the staged changes before committing; do not include credentials, uploads, or
private database exports. New files must be added and committed before a push
can share them. Source files under `db/`, SQL migrations, and static images under
`images/` are eligible for tracking. Keep private exports in `backups/` or
`db/backups/`, which are ignored.

## Update an existing installation after pulling

Save or commit local work first. Both teammates should use the same repository
and branch. For the current `main` branch:

```powershell
git switch main
git pull --ff-only origin main
& C:\xampp\php\php.exe db/migrate_stockout_tracking.php
```

Run the migration while inventory writes are paused. It installs the stockout
table and triggers and recovers dates where history exists. It preserves
existing stockout dates when rerun. Each teammate must run it against their own
database; pulling its PHP file does not execute it. Unknown historical dates
start tracking when that installation runs the migration, so counters may differ
between separate databases.

For future database changes, commit the migration and document its command here.
Do not reimport `tesda_inventory.sql` to update an existing database: it contains
DROP TABLE statements and can replace local records. Back up existing databases
before schema changes.

If Composer dependencies changed, run `composer install`. Commit `composer.lock`
when generated so teammates install the same dependency versions; do not commit
the generated `vendor/` directory. Reload the page with Ctrl+F5 after updating.

## First-time setup

1. Clone the project into XAMPP's `htdocs` folder.
2. Start Apache and MySQL/MariaDB.
3. Import `tesda_inventory.sql` only into a new, disposable local database.
4. Check the database connection in `config.php`. This file is currently tracked;
   do not commit private database credentials.
5. Run the stockout migration command above.
6. Run `composer install` for the dependencies in `composer.json`.
7. Open `http://localhost/CAPSTONE2/` (adjust the folder name if needed).

## Diagnose missing changes

On both computers, compare:

```powershell
git remote -v
git branch --show-current
git rev-parse HEAD
git status --short
```

Matching commit hashes and clean working trees mean the tracked source files
match. If behavior still differs, check the database migrations, database
contents, configuration, installed dependencies, and the folder Apache serves.
Browser preferences such as dark mode are local to each browser.

To investigate a missing file (replace the placeholder):

```powershell
git ls-files -- <path-to-file>
git check-ignore -v -- <path-to-file>
```

The first command confirms whether Git tracks it; the second explains any ignore
rule affecting an untracked file. Never use a force push or hard reset simply to
make two installations look alike.
