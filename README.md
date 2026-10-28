
# SchoolPilot 🎓

**SchoolPilot** is a modern, multi-tenant School Management System built specifically for the Nigerian education system. It is designed to handle the complex academic, administrative, and financial workflows of K-12 schools (Nursery, Primary, and Secondary) from a single platform.

Built with **Laravel 11** and **Stancl/Tenancy**, SchoolPilot allows a single platform to manage multiple schools, each with its own isolated database, ensuring maximum security and data privacy.

---

## 🚀 Core Features

### 1. Multi-Tenant Architecture
- **Isolated Databases:** Every school gets its own dedicated SQLite/MySQL database. Data from "Greenfield Academy" can never be seen by "Sunrise School".
- **Automated Provisioning:** A single Artisan command (`php artisan school:create`) automatically spins up a new school, creates its database, runs all migrations, seeds default data, and creates the first admin account.
- **Subdomain/Path Routing:** Schools are accessed via their unique slug (e.g., `/school/greenfield/login`).

### 2. Role-Based Access Control (RBAC)
SchoolPilot has strict role-based permissions to ensure users only see what they are supposed to see:
- **Developer:** Platform-level access to create and manage schools.
- **School Admin / Proprietor:** Full access to one school's data.
- **Principal / Vice Principal:** Final approval of results, staff management, and high-level reporting.
- **Dean of Studies:** Academic oversight, curriculum management, and result approval.
- **HOD (Head of Department):** Subject-specific result approval.
- **Class Teacher:** Result compilation, comments, and class management.
- **Subject Teacher:** Score entry for assigned subjects only.
- **Bursar:** Fees and payment management (Phase 2).
- **Student / Parent:** View published report cards and results.

### 3. Academic Setup
- **Sessions & Terms:** Configure academic sessions (e.g., 2025/2026) and terms (First, Second, Third).
- **Sections:** Nursery, Primary, and Secondary.
- **Classes & Subjects:** Create classes (e.g., Primary 1A, JSS 2B) and assign subjects to them.
- **Teacher Assignments:** Assign teachers to specific classes and subjects for a given session and term.

### 4. Students & Staff Management
- **Student Directory:** Enroll students, assign admission numbers, and track demographics.
- **Staff Directory:** Add teachers and administrative staff with specific roles and system access.
- **Bulk Import / Export:** (Coming soon) Upload students via Excel.

### 5. Modern Assessment Engine
- **Configurable CA/Exam Structure:** Defaults to the Nigerian standard: **CA1 (10), CA2 (10), CA3 (10), Exam (70)**.
- **Nursery Observation:** Separate assessment system for Nursery using observation-based ratings (Cognitive, Social, Motor Skills, etc.).
- **Score Entry Grid:** Teachers enter scores for all students in their assigned classes directly from the dashboard.

### 6. Approval Workflow 🏆
A complete, real-world result approval chain to prevent data tampering:
1. **Subject Teacher** enters scores and clicks "Submit for Approval".
2. **Class Teacher** reviews the compiled results (Total, Average, Grade, Position) and approves.
3. **HOD** reviews and approves for their department.
4. **Dean of Studies** reviews and approves.
5. **Principal** adds final remarks, signature, and **Publishes** the result.
6. **Parents/Students** can now view the final report card.

### 7. Report Cards
- **Automatic Calculations:** Total scores, WAEC grading (A1, B2, C3, etc.), average, and class positions are automatically calculated.
- **Printable PDF:** Beautiful, print-ready report cards with school branding, student details, subject grades, and signature lines.
- **Verification QR Code:** (Coming soon) Parents and employers can verify the authenticity of a report card online.

---

## 🛠️ Technology Stack

- **Framework:** Laravel 11 (PHP 8.4)
- **Multi-Tenancy:** `stancl/tenancy` (Database-per-tenant)
- **Database:** SQLite (for development) / MySQL (for production)
- **Frontend:** Laravel Blade + Tailwind CSS (via CDN)
- **Authentication:** Laravel Native Auth with Session
- **PDF Generation:** Browser Print (Phase 1) / DomPDF (Phase 2)

---

## 📦 Installation & Setup

### Prerequisites
- PHP 8.2 or higher
- Composer
- SQLite or MySQL

### Step 1: Clone the repository
```bash
git clone https://github.com/kristopher1027/schoolpilot.git
cd schoolpilot
