# Phase 1 production database and PostgreSQL readiness assessment

**Assessment date:** 18 November 2026  
**Status:** Source review only; no live database or Render configuration was inspected or changed.  
**Decision:** Do not change the production database connection or run migrations against live school data yet.

## 1. Current architecture confirmed in source

| Area | Current implementation |
|---|---|
| Central registry | SQLite connection `central_sqlite`; tenant metadata is stored in the `tenants` table as JSON data. |
| Platform data | Central SQLite migrations create platform admins, provisioning logs, and subscription records on `central_sqlite`. |
| School data | One SQLite file per school, located under `SCHOOLPILOT_TENANT_DATABASE_PATH` (default `database/tenants`). |
| Tenant connection | The custom web middleware sets `tenant_sqlite` to the selected school's file. |
| Tenant models | `App\Models\Tenant\User` explicitly pins the model to `tenant_sqlite`. |
| Provisioning | `school:create` creates a SQLite file, runs tenant migrations and seeders against `tenant_sqlite`, then records the tenant centrally. |
| Backup | `schoolpilot:backup` snapshots the central SQLite database, each regular `.sqlite` tenant file, and public uploads into a ZIP with SHA-256 hashes. |
| PostgreSQL config | A `pgsql` Laravel connection and Stancl PostgreSQL database manager are configured, but the active web path remains SQLite-specific. |

The existence of PostgreSQL configuration is not evidence that SchoolPilot is PostgreSQL-ready.

## 2. Hosting and persistence constraints

The Render deployment guide requires a persistent disk mounted at `/var/data`, with the central DB, tenant DB directory, and public uploads stored there. It also specifies one application instance for the current SQLite deployment.

This document cannot verify whether that disk is currently attached, whether the live paths match the guide, or whether current production records still exist. Those facts must be checked in the Render dashboard and by a real backup/restore exercise.

The backup command is a useful snapshot mechanism, but it does not encrypt or transfer the archive off Render. A backup is not considered secured until an authorized operator has retrieved it to private storage, verified the manifest hashes, and restored it into an isolated environment.

**Do not detach, resize, replace, or reconfigure the Render disk; change DB environment variables; or run a production migration until that backup and restore process is complete.**

## 3. Tenant architecture options

### Option A — PostgreSQL database per school

Each school keeps a separate PostgreSQL database, with a central PostgreSQL database for tenant registry and platform records.

**Advantages**
- Preserves the current database-per-school isolation model.
- Limits the scope of many accidental cross-school queries.
- Fits the existing provisioning and tenant lifecycle concept.
- Avoids adding a tenant key to every existing school table as a prerequisite.

**Costs and risks**
- More databases and connections to provision, monitor, back up, migrate, and restore.
- Connection lifecycle and pooling need careful handling.
- The current model and middleware hardcode `tenant_sqlite`; they must be redesigned to select a PostgreSQL tenant connection safely.
- Stancl's tenant connection and the custom central-registry middleware must be reconciled; they must not independently select different schools.

### Option B — Shared PostgreSQL database with school_id on tenant tables

All schools share one PostgreSQL database and tenant-owned rows are scoped by a school identifier.

**Advantages**
- Fewer database instances and simpler connection pooling.
- Centralized migrations and operational management.

**Costs and risks**
- Requires adding and backfilling tenant ownership on every tenant-owned table.
- Every query, relationship, job, export, report, notification, and file operation must enforce tenant scope.
- Requires robust database-level protections such as PostgreSQL row-level security where appropriate, plus application-level authorization.
- A missed tenant filter can expose one school's records to another.

**Preliminary recommendation:** Option A is the safer migration path for the current codebase because it preserves the isolation boundary the application already uses. This is a design recommendation, not approval to provision production databases. Confirm expected school count, hosting budget, and operational support before final selection.

## 4. Required application changes before PostgreSQL support

1. Define one authoritative tenant resolver using the central registry, tenant status, and validated school identifier.
2. Replace the SQLite-only connection assumptions in middleware, tenant models, provisioning, login, commands, tests, and backup/restore workflows with a driver-aware tenant connection strategy.
3. Decide whether to use Stancl's PostgreSQL database manager as the sole tenant connection lifecycle or retain a custom resolver. Do not mix both without explicit integration tests.
4. Move the central registry and platform tables to PostgreSQL as part of a planned central-database migration; do not point existing SQLite migration state at a new empty database and assume data is migrated.
5. Review every migration and query for PostgreSQL compatibility, including foreign keys, JSON, enum declarations, indexes, unique constraints, raw SQL, SQLite-specific functions, and migration rollback behavior.
6. Define connection pooling and limits, tenant database provisioning/deprovisioning, migrations across all tenants, backup retention, point-in-time recovery, and restore procedures.
7. Keep uploaded files outside the application container on private object storage or a durable persistent volume; database migration does not migrate files automatically.
8. Add tests for tenant switching, connection cleanup after exceptions, suspended/unknown tenants, concurrent requests, queued jobs, scheduled tasks, exports, and cross-tenant denial.
9. Run a rehearsed migration against a copy of production data, compare table counts and critical aggregates, verify file references, and test login and business workflows before any cutover.

## 5. Data migration and cutover safety plan

### Stage 0 — Protect the current data
- Confirm actual Render disk attachment and live configured paths in the dashboard.
- Create the existing `schoolpilot:backup` archive using a secure operator-controlled route.
- Transfer it off Render through a private access-controlled channel.
- Verify ZIP integrity and every SHA-256 manifest entry.
- Restore into a separate isolated environment and verify representative records and uploads.
- Record the backup timestamp, source deployment, archive hash, and restore result.

### Stage 1 — Build and test PostgreSQL support
- Implement the selected connection strategy in a non-production environment.
- Add automated tests for fresh school provisioning and tenant database migrations on PostgreSQL.
- Test conversion of all supported schema features and seed data.
- Test login, student/staff records, fees/payments, payroll, results, parent portal, and reports.

### Stage 2 — Rehearse data conversion
- Convert a verified backup copy, never the only production copy.
- Migrate the central registry and each tenant database deterministically.
- Preserve school IDs, tenant slugs, user IDs, foreign-key relationships, timestamps, financial references, and uploaded-file paths.
- Compare table counts and critical financial/academic aggregates between source and target.
- Run the full automated suite and a documented user-acceptance checklist.

### Stage 3 — Controlled cutover
- Announce a maintenance window and prevent writes during the final snapshot/copy.
- Take and verify a final backup.
- Apply the rehearsed migration procedure.
- Switch application configuration only after target validation.
- Run smoke tests and monitor errors, database connections, and tenant isolation.
- Keep the original SQLite backup untouched for rollback. Define rollback triggers and the exact restore process before starting.

## 6. Current blockers and acceptance criteria

| Blocker | Required evidence to close it |
|---|---|
| Live disk/persistence state unknown | Render dashboard confirmation and post-redeploy persistence test. |
| No verified off-site backup/restore | Private archive, verified hashes, and successful isolated restore. |
| Tenant connection is SQLite-specific | PostgreSQL-capable tenant resolver and model connection tests. |
| Provisioning is SQLite-specific | Fresh PostgreSQL tenant provisioning test. |
| PostgreSQL migration compatibility unknown | All central and tenant migrations pass on target PostgreSQL version. |
| Data conversion unproven | Rehearsal report with row counts, financial/academic reconciliations, and file checks. |
| Production cutover/rollback undefined | Approved runbook, maintenance window, rollback triggers, and tested recovery. |

**Phase 1 database readiness is not complete** until these blockers have evidence. This assessment intentionally makes no production changes and does not claim a live backup or PostgreSQL migration has occurred.
