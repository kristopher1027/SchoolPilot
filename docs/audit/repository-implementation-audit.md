# SchoolPilot Repository Implementation Audit

**Audit basis:** Static inspection of the repository's `main` branch on 11 November 2026.  
**Scope:** Routes, controllers, models, middleware, migrations, Blade views, tests, Composer configuration, and CI workflow paths.  
**Important limitation:** This is a source-level audit, not a successful runtime or production verification. No test suite or Render environment was executed from this inspection. A file, table, route, or README mention is not proof of a complete working feature.

## 1. Status definitions

- **Implemented and verified:** End-to-end behavior has been exercised successfully in an appropriate test or live environment.
- **Partially implemented:** Some underlying code exists, but the target workflow is incomplete.
- **Present but unverified:** Relevant code or schema exists, but behavior has not been demonstrated end-to-end in this audit.
- **Missing:** No meaningful implementation was found in the inspected application paths.
- **Blocked:** Verification or implementation depends on an unresolved defect, missing provider, deployment configuration, or product decision.

No feature is marked **implemented and verified** by this static audit alone.

## 2. Executive summary

The repository contains a Laravel application with a database-per-school direction, a central tenant registry, tenant provisioning command, role middleware, student/staff/academic CRUD, teacher assignments, score entry, result approval, and report-card views.

The strongest existing foundation is tenant provisioning and the academic/results workflow. The product is not yet a complete school-management platform. Many target areas have only database tables or README descriptions, with no corresponding user-facing workflow.

The most important immediate risks are:
1. Authentication and tenant-session behavior need an end-to-end regression pass; the previous Greenfield login redirect loop remains a known issue from the project's debugging history.
2. Tenant database switching and session handling are security-critical and need integration coverage across every tenant-aware route and guard.
3. Result approval migrations include multiple migrations with overlapping names/purpose and require a migration-history review before production use.
4. Root documentation describes Laravel 11 while the inspected Composer manifest requires Laravel `^13.17`; documentation and deployment assumptions need reconciliation.
5. The application is currently a compact Blade application with a limited set of controllers and views, not the full set of portals and operational modules in the product inventory.

## 3. Feature-by-feature status

| Product area | Audit status | Evidence and gap |
|---|---|---|
| Central tenant registry | **Present but unverified** | Stancl tenancy tenant/domain migrations and platform Tenant model exist. Verify registry metadata lifecycle and production DB behavior. |
| School provisioning | **Present but unverified** | `SchoolCreateCommand`, demo setup command, provisioning log model, and provisioning tests exist. Verify full rollback, retries, idempotency, and production storage behavior. |
| Database-per-school isolation | **Present but unverified** | Central and tenant SQLite connections, `UseSchoolDatabase`, and isolation tests exist. Must re-run full tests and validate cache, files, queues, sessions, and concurrent requests. |
| Login/logout and session security | **Blocked** | Universal login scans tenant databases and school guard is used. Prior reported Greenfield login loop is unresolved in project history; verify login → dashboard → refresh → logout → login and cross-tenant session rejection before expanding features. |
| Role-based access | **Partially implemented** | `RoleMiddleware` and `EnsureActiveUser` exist and are attached to route groups. The role/permission model is hard-coded by role strings; no granular permission administration UI was found. |
| Platform super-admin portal | **Missing** | PlatformAdmin model and central migration exist, but no platform admin controller, routes, or views were found in inspected paths. |
| Subscription and plan management | **Partially implemented** | A central subscriptions migration exists. No subscription controller, billing workflow, plan UI, or entitlement enforcement was found in inspected routes/controllers/views. |
| School profile and setup | **Partially implemented** | Tenant settings table and seeder exist. No complete school settings/configuration portal was found. |
| Academic sessions and terms | **Partially implemented** | Models, schema, and AcademicController routes/views exist for sessions and terms. Verify validation, edit/archive flows, and session/term constraints. |
| Sections, classes, subjects, departments | **Partially implemented** | Models, schema, and some AcademicController actions exist. No complete management workflows for all entities were confirmed. |
| Student directory and enrollment | **Partially implemented** | Student model, schema, and StudentController index/create/store with Blade views exist. Admission pipeline, guardian workflow, lifecycle history, bulk import, and student profile depth are not present in inspected routes/views. |
| Parent/guardian records | **Present but unverified** | `parents` and `student_parents` tables exist. No parent portal, parent controller, or parent views were found. |
| Student portal | **Missing** | No student-facing routes, controller, or views were found. |
| Parent portal | **Missing** | No parent-facing routes, controller, or views were found. |
| Staff directory | **Partially implemented** | Staff model, schema, and StaffController index/create/store with views exist. Full HR lifecycle and employment workflows are absent from inspected routes/views. |
| Teacher assignment | **Partially implemented** | TeacherSubject/ClassTeacher models/tables, AssignmentController, routes, and authorization test exist. Verify assignment lifecycle and schedule integration. |
| Timetable and classroom scheduling | **Missing** | No timetable model/table, controller, route, or view was found. |
| Curriculum and lesson planning | **Present but unverified** | `curriculum_guides` table exists, but no corresponding controller/routes/views were found. |
| Homework and assignment tracking | **Partially implemented** | AssignmentController and assignment view exist. Student submissions, feedback, deadlines, and parent/student access were not found. |
| Attendance | **Present but unverified** | `attendance_records` table exists. No attendance controller/routes/views were found. |
| Assessment configuration | **Partially implemented** | Assessment components, grading scales/bands, skills rubrics, and related models/schema exist. Full configuration UI and validation coverage need review. |
| Score entry | **Partially implemented** | ScoreController, score entry routes/views, and teacher-assignment authorization logic/tests exist. Verify all score limits, edit locks, bulk workflows, and submission states. |
| Result compilation and approval | **Partially implemented** | ResultController, result models/schema, approval routes, and result views exist. Verify all approval transitions, separation of duties, immutable history, and publication permissions. |
| Report cards | **Partially implemented** | Report-card Blade view and controller action exist. PDF generation, QR verification, secure parent access, and complete template configuration were not found. |
| Fees and invoicing | **Present but unverified** | Fee items, invoices, and invoice items tables exist. No fee/invoice controllers, routes, or views were found. |
| Payments and receipts | **Present but unverified** | Payments table exists. No payment entry, receipt, gateway, reconciliation, refund, or finance UI workflow was found. |
| Accounting and expenses | **Missing** | No expense, cashbook, budget, or accounting workflow was found in inspected migrations/routes/controllers/views. |
| HR, leave, payroll, payslips | **Missing** | No payroll/leave schema or corresponding workflows were found. |
| Library | **Missing** | No library schema or workflows were found. |
| Inventory, assets, procurement | **Missing** | No inventory/asset/procurement schema or workflows were found. |
| Transport | **Missing** | No transport schema or workflows were found. |
| School public website/CMS | **Missing** | A landing page exists, but no school-specific CMS/pages/news/gallery management was found. |
| Events and clubs | **Missing** | No events/clubs schema or workflows were found. |
| Notifications and messaging | **Present but unverified** | Notifications table exists. No notification center, delivery integration, messaging UI, or provider configuration was found in inspected paths. |
| Reports and analytics | **Partially implemented** | Dashboard displays basic student/staff/class counts and current session. No broader reporting/export module was found. |
| Audit trail | **Present but unverified** | `audit_logs` table exists. No centralized audit service, event coverage, or audit viewer was found in inspected paths. |
| Help desk/support tickets | **Missing** | No support-ticket workflow or help-center views were found. |
| Automated tests and CI | **Partially implemented** | Feature/unit tests cover provisioning, tenant isolation, active users, role middleware, and teacher assignment authorization. CI workflow exists; test execution and coverage were not run in this audit. |
| Production operations | **Blocked** | Dockerfile and environment example exist. Production storage, durable tenant database provisioning, backups/restore, queue/session/cache isolation, and deployment behavior require a dedicated Render review. |

## 4. Repository evidence

### Application structure observed
- Controllers: Academic, Assignment, Login, Result, Score, Staff, Student, Teacher.
- Middleware: UseSchoolDatabase, RoleMiddleware, EnsureActiveUser.
- Platform models: PlatformAdmin, ProvisioningLog, Tenant.
- Tenant models: AcademicSession, AssessmentComponent, Department, GradingBand, GradingScale, ResultApproval, SchoolClass, ScoreEntry, Section, Staff, Student, Subject, TeacherSubject, Term, User.
- Blade views: landing, login, dashboard, academic, assignments, results, scores, staff, students, teacher dashboard.
- Commands: SchoolCreateCommand and SchoolPilotDemoSetupCommand.

### Tenant schema observed
The main tenant migration creates: academic_sessions, terms, sections, departments, classes, subjects, class_subjects, users, parents, students, student_parents, staff, staff_departments, teacher_subjects, class_teachers, assessment_components, grading_scales, grading_bands, skills_rubrics, observation_ratings, score_entries, result_sheets, result_sheet_subjects, subject_approvals, skill_ratings, curriculum_guides, attendance_records, fee_items, invoices, invoice_items, payments, audit_logs, notifications, and settings.

A schema table alone does not establish a usable module. Each of these requires controller/service logic, authorization, routes, views/API, validation, tests, and operational behavior.

### Tests observed
- School provisioning command tests.
- Tenant database isolation tests.
- Active-user middleware tests.
- Role middleware tests.
- Teacher assignment authorization tests.
- Laravel example feature/unit tests.

### Documentation/configuration discrepancy
The root README describes Laravel 11, while `schoolpilot/composer.json` requires Laravel `^13.17` and PHP `^8.3`. Reconcile README, lockfile, Docker image, and Render runtime before making deployment changes.

## 5. Priority remediation and build sequence

### P0 — Stabilize the foundation before adding modules
1. Reproduce and fix the known Greenfield login/session redirect loop.
2. Run the full PHPUnit suite and resolve all failures.
3. Review middleware order, tenant DB switching, session guard behavior, and connection restoration on exceptions.
4. Add regression tests for login, logout, refresh, suspended tenant, unknown tenant, and cross-tenant session reuse.
5. Review duplicate/overlapping result-approval migrations and establish a clean migration path.
6. Verify production tenant database persistence and backup/restore on Render.

### P1 — Complete the school core
1. School profile and academic configuration.
2. Student lifecycle, guardian management, and student profile.
3. Staff lifecycle and permission administration.
4. Attendance.
5. Timetable and teacher workload.
6. Curriculum, lesson planning, and homework submissions.

### P2 — Complete academic delivery
1. Assessment configuration and grading policies.
2. Score entry validation and submission workflow.
3. Approval chain with auditable state transitions.
4. Report-card generation, publishing, and secure access.
5. Academic analytics and exports.

### P3 — Deliver finance and community portals
1. Fee structures, invoices, payments, receipts, reconciliation, and arrears.
2. Parent portal and student portal.
3. Notifications and parent-teacher communication.
4. Finance reporting and controls.

### P4 — Expand operational modules
1. Admissions.
2. HR, leave, and payroll.
3. Library.
4. Inventory, assets, and procurement.
5. Transport.
6. Events and school website/CMS.

### P5 — Platform productization
1. Super-admin portal.
2. Subscription plans and entitlements.
3. Support desk.
4. Advanced analytics and operational monitoring.
5. Security, privacy, accessibility, and disaster-recovery hardening.

## 6. Next verification tasks

Before changing application code, perform these checks:

- Run all tests on the current branch and capture exact pass/fail output.
- Inspect bootstrap middleware registration and auth/database/session configuration.
- Inspect all result approval migrations in timestamp order.
- Review the complete Dockerfile, Render start command, environment variables, and tenant storage paths.
- Exercise the deployed Greenfield login flow with browser network evidence.
- Inspect the remaining controller and Blade implementations for validation, authorization, and incomplete actions.

Update this audit as each check is completed. Do not upgrade a status based solely on code being present.
