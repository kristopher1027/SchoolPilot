# SchoolPilot Current Feature Audit

**Audit date:** 16 November 2026  
**Branch reviewed:** `main` at `eee836c20c2d3fb51d588d914ad8275cad634cb3`  
**Method:** GitHub source inspection plus the latest recorded GitHub Actions result. No local terminal or Render Shell was available.

## Executive conclusion

**SchoolPilot is not yet verified as fully working across every feature.** The latest GitHub Actions run passed, and the repository contains a substantially broader set of modules than the 11 November audit documented. However, CI success proves only that the automated test suite and configured syntax checks passed. It does not prove every screen, form, role, workflow, or deployed Render service works.

The live Render application was not reachable from this audit environment, so deployed behavior remains **unverified**.

## Verification evidence

- Latest reviewed `main` commit: `eee836c20c2d3fb51d588d914ad8275cad634cb3` (merge of PR #89).
- Latest GitHub Actions run: [SchoolPilot CI #43772595408](https://github.com/actions/runs/43772595408) — completed successfully.
- CI ran the PHPUnit suite, PHP syntax checks, and Render entrypoint shell syntax check.
- The test suite includes coverage for tenant provisioning, login flow, tenant database isolation, result pages, academic calendar, fees/payment reconciliation, payroll, staff HR, school settings, inventory/transport schemas, and backup creation.
- A passing test suite is not evidence that untested workflows or the live Render deployment are healthy.

## Current feature map

| Area | Current evidence | Audit status |
|---|---|---|
| School registration and tenant provisioning | Provisioning command, central tenant registry, provisioning tests | Present; CI-covered, production flow not independently exercised |
| Login, logout and tenant sessions | Universal login, school guard, active-user middleware, login-flow tests | Partially verified; live Greenfield sign-in, refresh, logout and cross-tenant behavior still need browser verification |
| Tenant data isolation | Tenant database middleware and isolation tests | CI-covered; production concurrency, cache, queue, file and session isolation need verification |
| Student and guardian management | Student and guardian controllers/routes; CRUD routes | Present; full CRUD and relationship workflows need end-to-end tests |
| Staff and HR | Staff and HR controllers/routes; leave and attendance workflows; tests | Present; full role and lifecycle coverage needs verification |
| Academic setup | Sessions, terms, classes, subjects and related routes | Present; full validation and lifecycle coverage needs verification |
| Assignments and teaching | Assignment and teacher controllers/routes | Present; assignment submission/feedback workflow is not established by current evidence |
| Attendance and timetable | Controllers, routes and schema | Present; full teacher/class workflows need verification |
| Scores and results | Score/result controllers, approval routes, report-card pages and tests | Present; complete approval, publication, permissions and report output need broader tests |
| Fees and payments | Fee controller/routes, invoices, payments, reconciliation, refunds and related tests | Present; complete finance lifecycle and provider integration need verification |
| Payroll | Payroll controller/routes, payroll schema and calculation tests | Present; full payroll run, authorization and payslip workflow needs verification |
| Library | Library controller/routes and schema | Present; workflow and role assignment need verification |
| Inventory and procurement | Inventory controller/routes and schema | Present; complete stock and procurement lifecycle needs verification |
| Transport | Transport controller/routes and schema | Present; complete route, assignment, trip and incident workflows need verification |
| Admissions | Public application and admin review/conversion routes | Present; public-to-enrollment lifecycle needs end-to-end verification |
| Parent portal and notifications | Parent portal and notification controllers/routes | Present; access scoping and delivery/read workflows need verification |
| School website/CMS and events | Public and admin website routes/controller | Present; publishing and registration workflows need verification |
| Reports and analytics | Report controller and export routes | Present; report correctness and role access need verification |
| Platform administration and subscriptions | Platform models and central schema exist | Not established as a complete user-facing platform workflow |
| Backup and recovery | Backup command, manifest and hash generation; CI tests | Backup archive creation is CI-covered; no production backup has been run or restore tested |

## Confirmed source-level issues and risks

### 1. Two tenant-routing approaches coexist

The main browser application uses the custom `UseSchoolDatabase` middleware and `tenant_sqlite` connection. A separate `routes/tenant.php` file defines Stancl path-based API routes under `/api/school/{tenant}`.

The inspected `bootstrap/app.php` registers the web routes, console routes and health route, but does not register an API route file or explicitly load `routes/tenant.php`. Therefore the separate tenant API routes appear not to be active through the current Laravel bootstrap. Confirm whether they are intended; either register and test them or remove/document the unused route path before relying on it.

### 2. Librarian role is inconsistent

The library routes and navigation allow a `librarian` role, but the main tenant users migration's role enum does not include `librarian`. This makes the role definition, account provisioning and route authorization inconsistent. Decide the supported school roles and update the schema, validation, seeders and tests together.

### 3. PostgreSQL migration is not a configuration-only change

The main tenant user model explicitly pins its connection to `tenant_sqlite`; the current tenant middleware dynamically selects SQLite database files; and the tenant schema is migrated into separate databases. Although PostgreSQL connection and Stancl manager configuration entries exist, the application is not thereby PostgreSQL-ready.

Before migration, decide whether to retain database-per-tenant PostgreSQL databases or move to a shared PostgreSQL database with explicit tenant scoping. Then update provisioning, models, migrations, auth, sessions, queues, storage, backups and isolation tests as one designed migration. Do not change the live database configuration as a shortcut.

### 4. Production persistence and recovery remain unverified

The backup command added in PR #89 has passing CI coverage, but it has not been run against the live Render data and no restore test has been demonstrated. The Render service's durable storage and the location of existing tenant databases/uploads must be confirmed before any disk, database or deployment change. Never treat a generated archive as a verified backup until it is safely copied off the service and restored in an isolated environment.

### 5. Role permissions are hard-coded

Route groups use role strings in middleware. This provides basic role gating, but there is no evidence in this inspection of a granular permission-management workflow. Every module needs a role/action matrix and tests for both allowed and forbidden actions.

## Required next verification

1. Verify the live login journey in a browser: sign in, reach the correct school dashboard, refresh, navigate between modules, sign out, and sign in again.
2. Verify cross-tenant session rejection and inactive/suspended school behavior.
3. Resolve the unused/unregistered API route decision and the librarian role mismatch.
4. Add or expand feature tests for student/staff CRUD, admissions conversion, parent portal scoping, attendance, timetable, assignments, library, inventory, transport, website publishing, refunds and role-denied requests.
5. Exercise critical flows in a deployed test school without modifying the existing Greenfield demo data.
6. Run a production backup, securely transfer it off Render, and perform an isolated restore test before any storage or database migration.
7. Design and test the PostgreSQL tenancy strategy before changing production.

## Final status

- **Latest GitHub CI:** PASS.
- **Source-level module presence:** Broad; many modules now have controllers, routes and schema.
- **All feature workflows verified:** NO.
- **Live Render application verified:** NO — unavailable to this audit environment.
- **Safe to migrate production to PostgreSQL now:** NO — backup/restore and tenancy migration design are prerequisites.

This report deliberately separates code presence, automated test coverage, and live production verification. Update each status only when the corresponding workflow has been exercised successfully.
