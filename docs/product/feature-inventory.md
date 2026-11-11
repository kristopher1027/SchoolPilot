# SchoolPilot Product Feature Inventory

**Status:** Product-planning baseline  
**Decision:** Complete school-management platform; database-per-school tenancy; original SchoolPilot design inspired by the workflows reviewed in iEdu.  
**Implementation rule:** This document defines the target scope. A feature is not considered implemented until its workflow, permissions, validation, persistence, tests, and user interface have been verified in the repository.

## 1. Product goals

SchoolPilot is a multi-tenant school-management platform for Nigerian nursery, primary, and secondary schools. It should support day-to-day academic, administrative, financial, communication, and operational work from one secure platform.

Product principles:

- Each school is an independent tenant with strict data isolation.
- One platform-level administration area manages tenant onboarding and platform operations.
- School users only access the modules and records allowed by their role and school.
- Workflows must be auditable; important approvals and financial changes must retain an activity history.
- Interfaces should be responsive, accessible, consistent, and designed around real school tasks.
- Nigerian school practices should be supported without making the product impossible to configure for other systems.

## 2. User groups and portals

### Platform-level users
- Platform super administrator: tenant onboarding, tenant status, plans, platform settings, operational monitoring, and support.
- Platform support staff: controlled support access with explicit audit trails.

### School-level users
- Proprietor / school owner
- School administrator
- Principal and vice principal
- Dean of studies
- Heads of department
- Class teachers
- Subject teachers
- Bursar / accountant
- Human-resources / payroll staff
- Librarian
- Store / inventory officer
- Transport manager
- Receptionist / admissions officer

### External school community
- Parents / guardians
- Students
- Applicants / prospective parents (where admissions are enabled)

Each role requires a permission matrix. Role names alone must not be treated as sufficient authorization; permissions must be enforced server-side.

## 3. Target feature inventory

The following is the planned scope. The implementation status of each item remains **to be audited** unless verified in a later repository assessment.

### A. Platform administration and multi-tenancy
- Create, review, activate, suspend, and archive school tenants.
- School profile, branding, contact details, school type, and academic configuration.
- Tenant database provisioning, migrations, backups, and health checks.
- Tenant isolation tests covering database connections, cache, files, queues, sessions, and authorization.
- Platform-wide user and tenant administration.
- Subscription plans, feature entitlements, billing status, invoices, and renewal history.
- Platform audit logs and support actions.
- Safe tenant onboarding and offboarding processes.

### B. School setup and configuration
- School profile, logo, motto, address, contacts, and administrator details.
- Academic year/session and term setup.
- School sections: nursery, primary, junior secondary, and senior secondary.
- Class/arm/stream configuration.
- Subjects, departments, grading scales, and assessment policies.
- School calendar, holidays, events, and configurable school hours.
- Configurable report-card templates and school rules.

### C. Admissions and student lifecycle
- Online enquiry and application forms.
- Applicant records, document collection, review, assessment, and admission decisions.
- Admission offers, acceptance, and conversion into student records.
- Unique admission numbers and student profiles.
- Class placement, promotion, transfer, withdrawal, and graduation.
- Student medical/emergency information with restricted access.
- Guardian records, relationships, and authorized pickup contacts.
- Bulk import/export with validation, duplicate detection, and error reports.
- Student status and complete enrollment history.

### D. Student information and daily administration
- Student directory, search, filters, and profile history.
- Class lists, student ID cards, and printable registers.
- Attendance, lateness, absence reasons, and attendance summaries.
- Daily attendance by class and subject/period where configured.
- Student discipline, incident records, and interventions with restricted permissions.
- Student welfare notes with appropriate access controls.
- Timetables and classroom allocation.

### E. Staff, HR, and payroll
- Staff profiles, employment details, qualifications, and documents.
- Staff roles, departments, and reporting relationships.
- Teacher-to-class and teacher-to-subject assignments.
- Staff attendance, leave requests, approvals, and leave balances.
- Staff timetable and workload.
- Payroll setup, salary components, deductions, allowances, payslips, and payroll periods.
- Payroll review and approval workflow.
- Staff performance/appraisal records where enabled.
- Staff exit and employment history.

### F. Academic planning and curriculum
- Academic sessions, terms, classes, arms, subjects, and departments.
- Curriculum/scheme of work and lesson planning.
- Teacher assignment and workload management.
- Timetable creation, conflict detection, publishing, and revision history.
- Lesson notes and teaching resources.
- Homework, assignments, projects, and submission tracking.
- Class and subject academic progress.

### G. Assessment, examinations, and results
- Configurable continuous-assessment and examination components.
- Nigerian grading defaults with school-configurable score limits and grading bands.
- Nursery observation-based assessments and developmental domains.
- Score entry, bulk entry, validation, and submission.
- Missing-score and out-of-range detection.
- Result compilation, totals, averages, grades, and positions where school policy permits.
- Configurable result approval chain (teacher, class teacher, HOD, dean, principal).
- Result correction requests and immutable approval history.
- Result publication controls.
- Report cards, comments, attendance summaries, skills/behaviour ratings, and signatures.
- Printable/downloadable reports and secure result verification.
- Broadsheets, performance analysis, subject analysis, and class summaries.

### H. Fees, billing, and finance
- Fee categories, fee structures, class-based charges, and student-specific charges.
- Invoices, discounts, scholarships, concessions, and payment plans.
- Receipts, payment allocation, part-payments, balances, and arrears.
- Cash, bank transfer, card, and supported payment-gateway reconciliation.
- Refunds, reversals, waivers, and approval controls.
- Expense records, income/expense categories, and budgets.
- Cashbook, financial summaries, and audit-ready transaction history.
- Parent statements and downloadable receipts.
- Separation of duties for payment entry, approval, and reconciliation.

### I. Parent and student portals
- Secure parent accounts linked to authorized children.
- Secure student accounts with age-appropriate permissions.
- Attendance, timetable, assignments, and academic progress.
- Published report cards and result history.
- Fee invoices, balances, payment history, and receipts.
- School announcements, calendar, events, and notifications.
- Parent-teacher communication and meeting requests.
- Profile updates and document access subject to school policy.

### J. Communication and notifications
- School-wide, class-level, group, and individual announcements.
- Parent, student, and staff messaging with permission controls.
- Email, SMS, and push/WhatsApp integrations where configured and legally appropriate.
- Notification preferences, delivery status, and message history.
- Emergency announcements and targeted communication.
- Templates and scheduled communications.

### K. Library management
- Book and resource catalog.
- Copies, categories, accession numbers, and availability.
- Member records and borrowing privileges.
- Issue, return, renewal, reservation, and overdue tracking.
- Fines and library reports.

### L. Inventory, assets, and procurement
- Inventory items, categories, stock locations, and stock movements.
- Suppliers and purchase requests/orders.
- Goods received, stock adjustments, and low-stock alerts.
- School assets, assignment, maintenance, and disposal history.
- Asset maintenance schedules and service records.
- Approval and audit history for stock and procurement changes.

### M. Transport management
- Vehicles, routes, stops, and capacity.
- Drivers, attendants, and vehicle documents.
- Student route assignments and transport fees.
- Trip/route attendance and incident records.
- Vehicle inspection, maintenance, and renewal reminders.
- Parent-facing transport information and notifications.

### N. School website, events, and activities
- Public school website with configurable pages and branding.
- Admissions information and enquiry forms.
- News, announcements, galleries, and downloadable documents.
- School calendar, events, clubs, and extracurricular activities.
- Event registration and participation records.
- Public pages must not expose private student or staff data.

### O. Reports and analytics
- Enrollment, admissions, attendance, and class-strength reports.
- Academic performance and assessment reports.
- Fee collection, arrears, expenses, and financial summaries.
- Staff, payroll, leave, and workload reports.
- Library, inventory, asset, and transport reports.
- Filters by session, term, class, department, and date range.
- Export to PDF/Excel/CSV with permission checks.
- Dashboard metrics must be traceable to underlying records.

### P. Settings, security, and compliance
- User management, role/permission management, and account lifecycle.
- Password reset, session management, and optional multi-factor authentication.
- Audit logs for sensitive actions and record changes.
- Data export, retention, archival, and deletion policies.
- Backups, restore drills, and disaster-recovery procedures.
- Secure file uploads, access-controlled downloads, and malware-safe handling.
- Rate limiting, input validation, CSRF protection, and secure authorization.
- Privacy controls and alignment with applicable Nigerian data-protection requirements.
- Accessibility, responsive design, localization, and configurable school branding.

### Q. Help, support, and subscriptions
- In-app help center, onboarding guides, and FAQs.
- Support requests and ticket history.
- Platform support workflow with tenant-scoped access.
- Subscription plans, trial periods, renewals, invoices, and payment status.
- Feature limits and plan entitlements enforced by the platform.
- Service status and maintenance announcements.

## 4. Cross-cutting workflow requirements

Every module must define:

1. Who can view, create, edit, approve, publish, export, and delete each record.
2. Required fields, validation rules, and duplicate handling.
3. State transitions and who may perform each transition.
4. Audit events and history requirements.
5. Tenant boundaries and data ownership.
6. Notifications and downstream effects.
7. Search, filters, pagination, and export behavior.
8. Empty, loading, success, and error states.
9. Automated tests for permissions, validation, and critical workflows.

## 5. Quality gates

A feature is only ready when:

- It works end-to-end in the intended portal.
- Authorization is enforced on the server, not only hidden in the interface.
- Tenant isolation is covered by automated tests.
- Inputs and state transitions are validated.
- Sensitive actions are audited.
- Failure cases are handled without losing or duplicating data.
- Relevant automated tests pass.
- The UI works on mobile and desktop.
- Documentation and deployment/configuration requirements are updated.

## 6. Delivery approach

Build in dependency order rather than attempting every module at once:

1. Stabilize platform foundation: authentication, tenant resolution, authorization, audit, backups, and school provisioning.
2. Complete school setup and core student/staff records.
3. Deliver academic planning, attendance, assessment, approval, and report cards.
4. Deliver fees, payments, receipts, and financial reporting.
5. Deliver parent/student portals and communication.
6. Add admissions, HR/payroll, library, inventory/assets, and transport.
7. Add public school websites, subscriptions, advanced analytics, and platform support.
8. Harden security, performance, accessibility, backups, and operational monitoring.

This sequence is provisional and will be adjusted after the codebase audit and detailed comparison with the reference workflows.

## 7. Open product decisions

These decisions must be finalized before implementing the related modules:

- Payment gateway(s), settlement and reconciliation rules.
- SMS, email, and messaging providers.
- Whether parent/student accounts are enabled by default or per school.
- Subscription tiers, trials, limits, and billing cadence.
- Required approval steps and grading policies per school.
- Data retention, backup frequency, and recovery objectives.
- Public website customization and domain strategy.
- Which features are included in the first production release versus later releases.

## 8. Next artifact

Create a repository implementation audit mapping every target feature to existing routes, controllers, models, migrations, views, middleware, tests, and deployment dependencies. Use explicit statuses: **implemented and verified**, **partially implemented**, **present but unverified**, **missing**, or **blocked**. Do not infer implementation from README claims alone.
