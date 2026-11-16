# Phase 1: role and permission baseline

This document records the route-level authorization currently declared in `routes/web.php`.
It is a source-code baseline, not a claim that every workflow has been end-to-end verified.

## Tenant roles

- `school_admin`
- `principal`
- `vice_principal`
- `dean`
- `hod`
- `class_teacher`
- `subject_teacher`
- `bursar`
- `parent`
- `librarian`

## Route authorization matrix

| Area | Roles currently allowed |
|---|---|
| Tenant dashboard | school_admin, principal, vice_principal, dean, hod, class_teacher, subject_teacher, bursar |
| Teacher dashboard | class_teacher, subject_teacher |
| School settings | school_admin |
| Website management | school_admin, principal |
| Reports and exports | school_admin, principal, vice_principal, dean, hod, bursar |
| Parent portal and parent notifications | parent |
| Student and guardian management | school_admin, principal, dean |
| Admissions administration | school_admin, principal, dean |
| Staff and academic setup | school_admin, principal |
| Staff leave application | school_admin, principal, vice_principal, dean, hod, class_teacher, subject_teacher, bursar |
| Staff attendance and leave administration | school_admin, principal |
| Payroll administration | school_admin, principal, bursar |
| Payslips | principal, vice_principal, dean, hod, class_teacher, subject_teacher, bursar, school_admin |
| Fees and payment operations | school_admin, principal, bursar |
| Refund approval/rejection | school_admin, principal |
| Refund settlement | school_admin, principal, bursar |
| Library | school_admin, principal, bursar, librarian |
| Inventory | school_admin, principal, bursar |
| Transport administration | school_admin, principal, bursar |
| Parent transport view | parent |
| Timetable view | school_admin, principal, vice_principal, dean, hod, class_teacher, subject_teacher |
| Timetable administration | school_admin, principal |
| Attendance | school_admin, principal, dean, hod, class_teacher |
| Score entry and submission | school_admin, principal, dean, hod, class_teacher, subject_teacher |
| Results and class results | school_admin, principal, dean, hod, class_teacher |
| Result approval | school_admin, principal, vice_principal, dean, hod, class_teacher |

## Important observations

1. `RoleMiddleware` currently performs exact role-string matching. There is no evidence in this route layer of configurable, per-school permissions.
2. Public admissions application and public school website routes are intentionally outside the authenticated route group; their controllers still require input validation, throttling and safe publication rules.
3. `routes/tenant.php` contains a separate Stancl tenancy API login route, but `bootstrap/app.php` does not register it. It is currently not part of the active Laravel route configuration. Do not enable it until its authentication, token/session strategy, tenant resolution and authorization are designed and tested.
4. Tenant isolation tests cover separate tenant databases and prevent a session authenticated for one tenant from being reused in another. They do not replace full authorization tests for every controller action.
5. The route matrix must be revisited whenever routes or roles change.

## Phase 1 verification priorities

- Verify forbidden access for representative cross-role requests to sensitive modules.
- Verify inactive users are denied before protected actions.
- Verify parent access is constrained to linked students and published results.
- Verify tenant switching cannot reuse an authenticated session.
- Decide and document the intended API architecture before registering any API routes.
