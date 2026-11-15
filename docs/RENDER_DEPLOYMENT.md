# Render deployment and release checklist

SchoolPilot is a Laravel application whose central registry and each school's SQLite database must survive container restarts. A Render disk is required when using SQLite in production.

## Render service configuration

Create or update one Render **Web Service** with:

| Setting | Value |
|---|---|
| Repository | `kristopher1027/SchoolPilot` |
| Root Directory | `schoolpilot` |
| Runtime | Docker |
| Dockerfile Path | `Dockerfile` |
| Health Check Path | `/up` |
| Instance count | One instance when using local SQLite |

Attach a persistent disk before the first production deploy:

- Mount path: `/var/data`
- Keep the disk attached to this service across deploys.
- Do not use the container filesystem for production database files.

Set these environment variables in Render:

| Variable | Value / guidance |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | A stable Laravel key. Generate once and retain it across deploys. Never rotate it casually because existing encrypted data and sessions depend on it. |
| `APP_URL` | The public HTTPS URL of this Render service |
| `DB_CONNECTION` | `central_sqlite` |
| `DB_DATABASE` | `/var/data/schoolpilot/central.sqlite` |
| `SCHOOLPILOT_TENANT_DATABASE_PATH` | `/var/data/schoolpilot/tenants` |
| `SCHOOLPILOT_PUBLIC_STORAGE_PATH` | `/var/data/schoolpilot/public` |
| `SESSION_DRIVER` | `database` |
| `SESSION_CONNECTION` | `central_sqlite` |
| `CACHE_STORE` | `database` |
| `QUEUE_CONNECTION` | `database` |
| `SCHOOLPILOT_DEMO_MODE` | `true` only for the public Greenfield demo; `false` for a real school production service |

For the public Greenfield demo only, also set:

- `SCHOOLPILOT_DEMO_ADMIN_PASSWORD`: a strong password of at least 12 characters.
- `SCHOOLPILOT_DEMO_ADMIN_EMAIL`: the demo administrator email (defaults to `admin@schoolpilot-demo.test`).
- `SCHOOLPILOT_DEMO_SCHOOL_SLUG`: `greenfield` (default).
- `SCHOOLPILOT_DEMO_SCHOOL_NAME`: the displayed demo school name (optional).

Do not set a demo administrator password for a real-school service with demo mode disabled. Do not share secrets in GitHub, source code, screenshots, or chat.

## What the container startup does

The Docker entrypoint:

1. Verifies the Laravel application key and absolute SQLite paths.
2. Creates the central and tenant database directories on the mounted disk.
3. Caches Laravel configuration.
4. Runs central database migrations.
5. Runs the Greenfield demo setup only when `SCHOOLPILOT_DEMO_MODE=true` (or `1`).
6. Gives the Apache `www-data` process access to the SQLite files and their containing directories.
7. Starts Apache on Render's expected port.

The demo setup applies pending tenant migrations and refreshes the configured demo administrator password. It is not run for a real-school service when demo mode is disabled.

## Provisioning a real school

For a real-school service, keep `SCHOOLPILOT_DEMO_MODE=false`. Provision the first school through the application container using the existing Artisan command:

```sh
php artisan school:create "School Name" school-slug admin@example.com
```

The command securely prompts for and confirms a temporary administrator password. Deliver that password to the administrator through a secure channel. Never put it in a command-line argument, commit, or deployment log.

## Before going live

- [ ] Confirm the persistent disk is attached at `/var/data`.
- [ ] Confirm the central and tenant paths match the environment variables above.
- [ ] Confirm `APP_KEY` is stable and `APP_DEBUG=false`.
- [ ] Confirm demo mode is enabled only for the demo service.
- [ ] Confirm the deployment logs show central migrations completed and Apache started.
- [ ] Open `/up` and confirm it returns HTTP 200.
- [ ] Sign in to the correct school path and verify dashboard, student, fee, payroll, and parent portal pages.
- [ ] Create or update a harmless test record, redeploy, and confirm it remains.
- [ ] Verify two tenant school accounts cannot access each other's records.
- [ ] Take a backup of the persistent disk before the first live release and establish a recurring backup schedule.

## Important SQLite hosting limitation

This database-per-school SQLite deployment is intended for a single application instance with persistent disk storage. Do not scale the Render service horizontally across multiple instances sharing SQLite files. For multi-instance/high-concurrency production, plan a migration to a managed database service (for example, PostgreSQL) and a tenant database strategy designed for concurrent access.

School logos use Laravel's public filesystem disk. The container entrypoint links `storage/app/public` to `/var/data/schoolpilot/public` on the persistent disk and recreates Laravel's public storage link on startup. Keep the Render disk mounted at `/var/data`; uploaded logos then survive redeploys. Each school's logo is stored under `schools/{school-slug}/`, and its relative path is saved in that school's own settings database.

## Release process

1. Merge only after GitHub Actions passes.
2. Confirm Render deploys the latest `main` commit.
3. Inspect startup logs before testing login.
4. Run the smoke checks above.
5. If a deploy fails, keep the previous known-good deploy available and inspect logs before retrying. Do not delete or recreate the persistent disk to fix an application error.


## Creating and verifying a SchoolPilot backup

The application includes an Artisan command that creates a ZIP archive containing a consistent SQLite snapshot of the central registry, every regular `.sqlite` tenant database in the configured tenant directory, public uploads, and a SHA-256 manifest.

**Do not attach, detach, resize, or reconfigure the Render disk until you have created and downloaded a backup of the current live data.** The command does not recover files that have already disappeared from an ephemeral container filesystem.

Run this from the running Render service's Shell (if Shell access is available). First choose a destination on a filesystem with enough free space and which you can retrieve securely. For example:

```sh
mkdir -p /tmp/schoolpilot-export
php artisan schoolpilot:backup --destination=/tmp/schoolpilot-export/schoolpilot-before-disk-change.zip
```

The destination directory must already exist, the destination must be an absolute path, and the archive must not already exist. The command does not upload the archive anywhere. Download or securely transfer the archive off the Render instance before making infrastructure changes; files under `/tmp` and the container filesystem are not durable across deploys or instance replacement. If Render Shell or a secure download/transfer path is unavailable, stop and arrange a secure backup destination before proceeding.

After retrieving the archive, verify it on a trusted machine:

1. Confirm the ZIP opens successfully.
2. Confirm `manifest.json` is present.
3. Extract the archive to a protected location and compare SHA-256 hashes of each listed file with the corresponding `files_sha256` value in the manifest.
4. Keep at least one copy outside Render, restrict access because it contains student and staff data, and retain the archive until a restore has been tested.

The archive contains sensitive, unencrypted database and upload data. Store and transfer it only through a private, access-controlled channel. Do not place it in the public web root, commit it to GitHub, or share it in chat. This command creates a one-time backup; it does not schedule recurring backups or automatically transfer files to off-site storage.

A backup is not proven until a restore has been tested. Perform restore tests against a separate, isolated environment and never overwrite live school data during a test.
