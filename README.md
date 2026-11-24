# SGVMS: Student Security & Guardian Verification Management System

PHP + HTML + CSS web app (MVC structure) with a MySQL database loaded from `.sql` files.
Flow: **register students and guardians, scan the QR code, verify the guardian's photo, release or deny, and keep a log.**

This is **version 2.3**. Its database is the version 2.1 diagram (`SGVMS-ERD-v2.1`, 13 tables) plus the 2.2 `archived` student status and the 2.3 per-period attendance tables (`periods`, `subjects`, `class_schedules`, `period_attendance`).

---
## 1. Run it locally on XAMPP
1. Copy this folder to `C:\xampp\htdocs\sgvms`.
2. In the XAMPP Control Panel, **start Apache and MySQL**.
3. Open **http://localhost/phpmyadmin**. Click **New**, create an empty database named `sgvms` (collation `utf8mb4_unicode_ci`), then **click `sgvms` in the left list** so it is selected.
4. Use the **Import** tab on that database:
   - import `database/schema.sql` (creates all the tables)
   - then import `database/sample_data.sql` (demo students, guardians, schedules and accounts)
   - the SQL files do not name a database: they import into whichever one is selected.
5. Open **http://localhost/sgvms/**

XAMPP's default database login (`root`, empty password) is already set in `config/config.php`.

| Role | Username | Password | Can do |
|---|---|---|---|
| Administrator | `admin` | `admin123` | Everything: dashboard, students, guardians, sections, staff accounts, audit trail |
| Teacher | `teacher` | `teacher123` | Gate station, students (limited details), attendance, activity logs |
| Gate staff | `guard` | `guard123` | Gate station, students (limited details), activity logs |

### Starting again with a fresh database
`schema.sql` is for a **new, empty** database. To start over, delete all the tables (or create a new database) and import `schema.sql` again. The old `migrate_*.sql` upgrade scripts were removed: they only worked on older databases.

---
## 2b. What is new in version 2.3
- **Attendance per class period.** Admin sets up **Schedules** once: the bell schedule (periods and times), the subjects, and which teacher teaches which section on which day and period. Nothing is fixed; there are buttons to start from a sample bell schedule and sample subjects.
- **Teachers mark their own classes.** *Attendance > By period* opens the class happening now by itself (no "Show class" button). Statuses: present, late, absent, excused, no class. Students already marked from the gate or the daily record are preselected, and *Mark all present* is one click. Teachers can record today and earlier days of the current week; the administrator can record any date.
- **Daily (gate) attendance** is still there under the *Daily (gate)* tab.
- **Download as Excel (.xlsx)** on both tabs: pick the dates (and a section, for the administrator). Period exports have an *Attendance* sheet and a *Summary* sheet of totals per student. Teachers only get their own classes. If the host has no zip support, a CSV that Excel opens is sent instead.
- Schedules and sections that already have attendance records cannot be deleted, so history is safe.

## 2a. What is new in version 2.2
- **School name** is now **Green Acres Christian School**. Put the logo at `assets/img/logo.png` (or `.jpg`, `.webp`, `.svg`); it replaces the shield on the sidebar and sign-in page.
- **Email messages to parents** (free) replace SMS by default: `notifications.channels` is `['email']`. Mail goes through any SMTP server with a small built-in client (no libraries). For Gmail: turn on 2-step verification, create an **App Password**, then set `notifications.email` (`driver` `smtp`, `username`, `password`) in `config/config.local.php`. Until then messages are simulated. Each guardian needs an **email**; add or edit it on the student's page (*Edit contact*). SMS still works by adding `'sms'` to `channels`.
  - Gmail limits how many emails a free account may send per day. If the school grows past it, use a school email service.
  - InfinityFree blocks PHP `mail()`, which is why SMTP is used.
- **Archive instead of delete.** Students are never deleted: *Archive* hides them everywhere except *Students > Show archived*, keeping all records, and *Restore* brings them back. Archived students cannot be timed in at the gate.
- **Import students from CSV** (*Students > Import CSV*): upload, review the preview, then confirm. Download the template there. Missing sections are created, duplicates are skipped, and a guardian with the same email is linked, not duplicated.
- **Grades** now run from Kinder to Grade 10.
- **Safe defaults:** without `config.local.php`, demo mode and detailed errors are switched off on any address except localhost.

## 2. What is new in version 2.1
- **Messages to parents.** When a child arrives (time-in) and when a child is picked up, every authorized guardian who has messages switched on gets a text. After a substitute pickup the message names the substitute and the approver. Everything is listed under **Messages**, with its status.
  - By default messages are **simulated**: they appear on the Messages page and on the gate confirmation screen, but nothing is really sent. This is safe for demos and testing.
  - To send real SMS, see the checklist below (section 4, "Real text messages").
  - An administrator can turn messages off for a guardian on the student's page. A guardian whose pickup was revoked is never messaged.
  - A problem with messaging (no signal, wrong key) **never blocks or undoes a release**. The gate waits at most 4 seconds in total; anything unsent stays listed, and the **Send unsent again** button on the Messages page retries it.
- **Substitute pickup now follows the school's process: a confirmation letter OR a phone call to a registered guardian** (or both). The stricter rule, "both", is one setting (`substitute_pickup.require`).
- **Who may approve a substitute pickup:** a teacher or an administrator, with their own username and password, and never the same person who is recording at the gate (an administrator may approve their own release). This is also a setting (`substitute_pickup.approver_roles`).
- The school name is now **Greenacres Elementary Christian School** (`school_name` in `config/config.php`).

## 2b. What changed in version 2
- **Sections and teachers.** Students belong to a section (grade, name, school year) with an adviser. Manage them under *Sections* and *Staff accounts*.
- **Staff accounts screen.** Add staff, change roles, reset passwords, and disable an account; a disabled account is signed out immediately.
- **Attendance.** Students are marked present automatically at time-in. Teachers can mark absent, late or excused.
- **Substitute pickup, done properly.** A person who is not a registered guardian is recorded with their details, how the pickup was confirmed, and who approved it, all saved (`collectors`, `guardian_confirmations`) and shown in the activity log. (The exact rule is described under version 2.1.)
- **Release rules are enforced by the server, not only the screen.** A student cannot be released twice, even if two guards scan at the same moment (the student's record is locked while a release is saved).
- **Privacy.** Only administrators see birth date, address and guardians' phone numbers on a student's profile. (Gate staff still see a guardian's phone on the gate screen, because they may need to call.) Deleting a student also deletes their photo and any guardians linked only to them. Gate history stays and keeps the names.
- **Audit trail.** Every change an administrator makes is recorded (*Audit trail* page). Sign-ins and logouts are recorded too.
- **Names** are stored as first, middle and last name plus suffix.

## 3. Demo script
1. Sign in as **guard** and open **Gate station**. Dismissal: tap *Maria Clara Santos*, compare the photos, choose **This is the person**. Try releasing her again: it is refused.
2. Tap *Paolo Bautista*: his father is marked **Not authorized** (no release button) and he has no time-in today.
3. *Juan Miguel Dela Cruz* > "not on this list" > **Substitute pickup**: fill in the form, attach any small image as the letter (or choose "The guardian confirmed the pickup" after pretending to phone), and approve with `teacher` / `teacher123` or `admin` / `admin123`.
   After each time-in and release, the confirmation screen shows which parents were messaged, and **Messages** (admin) lists the texts.
4. Sign in as **admin**: the dashboard, **Activity logs** (shows who approved, with the letter), **Audit trail**, **Sections**, **Staff accounts**.
5. Sign in as **teacher** and open **Attendance** to mark a student late.
6. Dashboard > **Reset demo logs** restarts the demo.

---
## 4. Putting it on the internet (checklist)
**You need:** hosting with PHP 8.0+ and MySQL/MariaDB (shared cPanel hosting is enough) and **HTTPS**. Never run this on plain HTTP: it carries children's photos and guardians' contact details.

1. **Create the database** in your host's panel: a new database and a *dedicated user* with a long random password, granted only on that database. Do **not** use `root`.
2. **Import `database/schema.sql` only**, never `sample_data.sql`. Click *your* database's name in phpMyAdmin first so it is selected, then use Import. The file works with any database name (such as `if0_12345_sgvms`) and needs no editing.
3. **Create the first administrator** on your own computer:
   `php database/create_admin.php "First name" "Last name" username "a-long-password"`
   Run the printed `INSERT` in phpMyAdmin (SQL tab). All other staff are added from the **Staff accounts** page.
4. **Configure:** copy `config/config.local.example.php` to `config/config.local.php` and fill in the school name and database details. It sets `demo_mode` and `debug` to `false`.
5. **Upload limits:** photos and letters can be up to 5 MB. The included `.user.ini` raises PHP's limits; if uploads fail on your host, set `upload_max_filesize` to at least 6M and `post_max_size` to at least 12M.
6. **Upload** the project by FTP. Do **not** upload `database/sample_data.sql`. Make `uploads/` writable (permissions 755 or 775).
7. **Force HTTPS:** in `.htaccess` uncomment the three redirect lines.
8. **Real email messages.** Messages are simulated until you set the Gmail details (see section 2a) in `config/config.local.php`. Test with one guardian first, and check the spam folder. Parents need to be told and give consent. (Paid SMS is optional: add `'sms'` to `notifications.channels` and set up Semaphore.)
9. **Test**, then delete the test data.
10. **Back up** regularly: phpMyAdmin > Export **and** the `uploads/` folder.

On nginx, `.htaccess` is ignored: add equivalent rules that deny web access to `app/`, `config/`, `database/` and `uploads/`.

### Built in
Password hashing, sign-in lockout (5 failures in 10 minutes, also applied to approver passwords), prepared statements, output escaping, CSRF tokens, role-based access checked on every request, **private photos and letters**, upload validation, transaction-safe gate actions, non-editable logs, audit trail, secure cookies and security headers, generic errors in production.

### Still to do before real use
- **Data Privacy Act (RA 10173):** privacy notice, parental consent form, retention policy, and a data protection officer for the school.
- Resizing uploaded photos (and removing their location data), releasing siblings together, and printable reports.
- A Content-Security-Policy header, and a limited database user that cannot delete log rows.
- A parent-facing app or page, email as a second channel, offline fallback, two-factor sign-in, automatic backups, and an independent security review.

---
## 5. MVC structure
```
sgvms/
  index.php               Front controller (every request enters here)
  config/                 config.php (defaults), config.local.example.php (live settings template)
  app/
    core/                 App (router), Controller, Model, Database, helpers
    models/               Mailer, Period, Subject, Schedule, PeriodAttendance, User, Student, Guardian, Section, Teacher, Collector, Confirmation, Attendance, Log, LoginLog, Audit, Notification, Notifier
    controllers/          Auth, Dashboard, Students, Import, Schedule, Gate, Sections, Staff, Attendance, Logs, Notifications, Photo
    views/                HTML pages for each controller
  assets/                 css, js, fonts, offline QR libraries
  uploads/                Uploaded photos and letters (blocked from direct web access)
  database/               schema.sql, sample_data.sql, create_admin.php
```
URLs look like `index.php?url=controller/method/param` (example: `?url=gate/verify/3`).

### Database tables (see the ERD)
`users` - `teachers` - `sections` - `students` - `guardians` - `student_guardians` - `collectors` - `guardian_confirmations` - `gate_logs` - `notifications` - `attendance` - `login_logs` - `audit_logs`

### Design
Chalkboard green and sky blue, set in Lexend (a typeface designed for readability), bundled so it works offline.
Colors are CSS variables at the top of `assets/css/style.css`.

## Reports (version 2.4)

- **Activity logs → date range.** Tabs for *Today*, *This week*, *Month* and *Custom range* (admin). Teachers see the current week only.
- **Filters.** Grade level, section and action. The CSV export follows the same filters.
- **Attendance summary.** A table per grade level (or per section once a grade or section is chosen) with present, late, absent, excused and the attendance rate. Source is the daily (gate) attendance or the class-period records.
- **Printable attendance sheet** (`Attendance → Open printable attendance sheet`, or from the logs summary). Weekly or monthly grid per section, or per subject. It has the school logo, adviser, legend, totals and signature lines, and prints in landscape.

## Version 2.5

- **Teachers see only their own sections.** A teacher's sections are the one they advise plus any section on their timetable. This applies to the student list, student pages and ID cards, daily attendance, the printable sheet, the Excel export, the activity log and the summary. The gate station still looks up every student, because the gate has to work for anyone.
- **Minimal look.** A white sidebar, flat cards, thin borders and a single green accent.

## Version 2.7

- **Whole-day (gate) attendance belongs to the class adviser.** Teachers who advise a section see the Daily tab, the daily sheet, the daily export and the daily summary for that section only. Subject teachers use attendance by period and the "By subject" sheet. The administrator can still use everything.
- **Teachers with no classes yet** see a notice asking them to contact the Vice Principal, so an empty page is never unexplained.

