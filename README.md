# SGVMS: Student Security & Guardian Verification Management System

PHP + HTML + CSS web app (MVC structure) with a MySQL database loaded from `.sql` files.
Flow: **register students and guardians, scan the QR code, verify the guardian's photo, release or deny, and keep a log.**

This is **version 2.1**. Its database matches the diagram `SGVMS-ERD-v2.1` (13 tables).

---
## 1. Run it locally on XAMPP
1. Copy this folder to `C:\xampp\htdocs\sgvms`.
2. In the XAMPP Control Panel, **start Apache and MySQL**.
3. Open **http://localhost/phpmyadmin** and use the **Import** tab:
   - import `database/schema.sql` (creates the `sgvms` database and all tables)
   - then import `database/sample_data.sql` (demo students, guardians and accounts)
4. Open **http://localhost/sgvms/**

XAMPP's default database login (`root`, empty password) is already set in `config/config.php`.

| Role | Username | Password | Can do |
|---|---|---|---|
| Administrator | `admin` | `admin123` | Everything: dashboard, students, guardians, sections, staff accounts, audit trail |
| Teacher | `teacher` | `teacher123` | Gate station, students (limited details), attendance, activity logs |
| Gate staff | `guard` | `guard123` | Gate station, students (limited details), activity logs |

### Upgrading from version 2 (keep your data)
1. In phpMyAdmin, **Export** your `sgvms` database first, as a backup.
2. Replace the project files with this version (keep your `uploads/` folder and your `config/config.local.php`, if you have one).
3. In phpMyAdmin, select `sgvms` and import `database/migrate_v2_to_v2_1.sql`. **Run it once.** It adds the `notifications` table and the per-guardian "receive messages" switch, and keeps all your data.
4. Accounts that only exist in `sample_data.sql` (such as `teacher`) do not exist on an upgraded database; add them from **Staff accounts**.

### Upgrading from version 1 (keep your data)
1. In phpMyAdmin, **Export** your `sgvms` database first, as a backup.
2. Replace the project files with this version (keep your `uploads/` folder).
3. In phpMyAdmin, select `sgvms`, open the **Import** tab and import `database/migrate_v1_to_v2.sql` (it goes straight to version 2.1; do not run the v2 script afterwards).
   It renames the old tables to `*_v1`, creates the new ones and copies everything across.
   Guardians' and staff names are split at the last space ("Maria Clara Santos" becomes first name "Maria Clara", last name "Santos"), so check them afterwards.
   The old `*_v1` tables stay as a safety copy; the last lines of the script show how to drop them once you are happy.

---
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
## 3b. If the camera does not start
The **Use camera** button on the Gate station explains what is wrong and what to do. The usual cause:

- **"Camera access is blocked."** The first time, the browser asks "Allow this site to use the camera?". If someone clicks **Block** (or closes the box several times), the browser remembers that for this website, **even after the computer is restarted**, and a web page is not allowed to ask again. This cannot be changed from the system's code. Turn it back on in the browser:
  - **Chrome / Edge:** click the icon at the left end of the address bar, set **Camera** to **Allow** (or choose **Reset permission**), then press **Try again** on the page. The system notices the change and starts the camera by itself, with no reload.
  - **Firefox:** click the camera or lock icon in the address bar and remove the block next to "Use the Camera".
  - **Safari:** Safari > Settings for This Website > Camera > Allow (on iPad: aA > Website Settings).
  - Or open `chrome://settings/content/camera` (Edge: `edge://settings/content/camera`) and remove this site from the blocked list. The page has a **Copy settings address** button for this.
- **"The camera needs a secure address."** Browsers only allow the camera on `https://` and on `http://localhost`. On the school computer use `http://localhost/sgvms/`. Other devices need HTTPS (see section 4).
- **"The camera is busy."** Another app or tab (Teams, Zoom, the Camera app) is using it.
- **"No camera found."** Plug one in, or use the search box or a USB scanner.
- Windows: Settings > Privacy & security > Camera must allow camera access and desktop apps.

The search box and USB scanners always work, with or without the camera.

---
## 4. Putting it on the internet (checklist)
**You need:** hosting with PHP 8.0+ and MySQL/MariaDB (shared cPanel hosting is enough) and **HTTPS**. Never run this on plain HTTP: it carries children's photos and guardians' contact details.

1. **Create the database** in your host's panel: a new database and a *dedicated user* with a long random password, granted only on that database. Do **not** use `root`.
2. **Import `database/schema.sql` only**, never `sample_data.sql`. On shared hosting, delete the first lines `CREATE DATABASE ...` and `USE ...`, then select *your* database in phpMyAdmin before importing.
3. **Create the first administrator** on your own computer:
   `php database/create_admin.php "First name" "Last name" username "a-long-password"`
   Run the printed `INSERT` in phpMyAdmin (SQL tab). All other staff are added from the **Staff accounts** page.
4. **Configure:** copy `config/config.local.example.php` to `config/config.local.php` and fill in the school name and database details. It sets `demo_mode` and `debug` to `false`.
5. **Upload limits:** photos and letters can be up to 5 MB. The included `.user.ini` raises PHP's limits; if uploads fail on your host, set `upload_max_filesize` to at least 6M and `post_max_size` to at least 12M.
6. **Upload** the project by FTP. Do **not** upload `database/sample_data.sql`. Make `uploads/` writable (permissions 755 or 775).
7. **Force HTTPS:** in `.htaccess` uncomment the three redirect lines.
8. **Real text messages (optional).** Messages are simulated until you connect an SMS service. The system supports Semaphore (semaphore.co, a Philippine provider): create an account, get an API key, and put this in `config/config.local.php`:
   `'notifications' => ['driver' => 'semaphore', 'semaphore' => ['api_key' => 'YOUR-KEY']]`
   Each text costs a credit, so check the price and have the school agree to it. Parents need to be told and give consent, and each guardian's mobile number must be correct (09xx xxx xxxx). Test with one guardian first.
9. **Test**, then delete the test data.
10. **Back up** regularly: phpMyAdmin > Export **and** the `uploads/` folder.

On nginx, `.htaccess` is ignored: add equivalent rules that deny web access to `app/`, `config/`, `database/` and `uploads/`.

### Built in
Password hashing, sign-in lockout (5 failures in 10 minutes, also applied to approver passwords), prepared statements, output escaping, CSRF tokens, role-based access checked on every request, **private photos and letters**, upload validation, transaction-safe gate actions, non-editable logs, audit trail, secure cookies and security headers, generic errors in production.

### Still to do before real use
- **Data Privacy Act (RA 10173):** privacy notice, parental consent form, retention policy, and a data protection officer for the school.
- Safe production defaults when `config.local.php` is missing (today the default is demo mode).
- Resizing uploaded photos (and removing their location data), editing a guardian's details, releasing siblings together, and printable reports.
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
    models/               User, Student, Guardian, Section, Teacher, Collector, Confirmation, Attendance, Log, LoginLog, Audit, Notification, Notifier
    controllers/          Auth, Dashboard, Students, Gate, Sections, Staff, Attendance, Logs, Notifications, Photo
    views/                HTML pages for each controller
  assets/                 css, js, fonts, offline QR libraries
  uploads/                Uploaded photos and letters (blocked from direct web access)
  database/               schema.sql, sample_data.sql, migrate_v1_to_v2.sql, migrate_v2_to_v2_1.sql, create_admin.php
```
URLs look like `index.php?url=controller/method/param` (example: `?url=gate/verify/3`).

### Database tables (see the ERD)
`users` - `teachers` - `sections` - `students` - `guardians` - `student_guardians` - `collectors` - `guardian_confirmations` - `gate_logs` - `notifications` - `attendance` - `login_logs` - `audit_logs`

### Design
Chalkboard green and sky blue, set in Lexend (a typeface designed for readability), bundled so it works offline.
Colors are CSS variables at the top of `assets/css/style.css`.
