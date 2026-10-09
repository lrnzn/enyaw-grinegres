# SGVMS — Student Security & Guardian Verification Management System (Prototype)

PHP + HTML + CSS web app in MVC structure, with a MySQL database imported from `.sql` files.
Flow: **register students & guardians → QR time-in → scan at dismissal → verify guardian photo → release / deny → logs.**

---
## 1. Run it locally on XAMPP
1. Copy this folder to `C:\xampp\htdocs\sgvms`.
2. In the XAMPP Control Panel, **start Apache and MySQL**.
3. Open **http://localhost/phpmyadmin** → **Import** tab:
   - choose `database/schema.sql` → **Go** (creates the `sgvms` database and tables)
   - then import `database/sample_data.sql` → **Go** (demo students, guardians, accounts)
4. Open **http://localhost/sgvms/**

XAMPP's default database login (`root`, empty password) is already set in `config/config.php`.

| Role | Username | Password | Can do |
|---|---|---|---|
| Administrator | `admin` | `admin123` | Dashboard, register students/guardians, ID cards, everything |
| Gate staff | `guard` | `guard123` | Gate Station, view students, view logs |

## 2. Demo script (follows the slides)
1. Sign in as **admin** → Dashboard: today's picture. **Students → Register student**, add guardians with photos, open **ID card & QR**.
2. Sign in as **guard** → **Gate Station**. *Morning drop-off*: tap *Paolo Bautista* (simulates a scan) → **Record time-in**.
3. *Dismissal*: tap *Maria Clara Santos* → compare guardian photos → **This is the person → Release**.
4. Siblings *Angela* and *Carlo Reyes* share the same registered mother and uncle.
5. *Paolo Bautista* has a guardian marked **NOT AUTHORIZED** — no release button is offered.
6. Unknown person: "not on the list" → **Deny**, or **Release with letter** (supervisor password `admin123`).
7. Release the same student twice → "Already released today" warning.
8. **Activity Logs** → filter → **Export CSV**. Dashboard → **Reset demo logs** restarts the demo.

USB QR scanners work like a keyboard. **Use camera** scans with a phone/laptop camera (needs `localhost` or HTTPS).

---
## 3. Putting it on the internet (checklist)
**You need:** hosting with PHP 8.0+ and MySQL/MariaDB (shared cPanel hosting is enough) and **HTTPS** (free via Let's Encrypt/Cloudflare). Never run this on plain HTTP — it carries children's photos and guardians' contact details.

1. **Create the database** in your host's panel (cPanel → MySQL Databases): a new database and a *dedicated user* with a long random password, granted only on that database. Do **not** use `root`.
2. **Import `database/schema.sql` only** — never `sample_data.sql`. On shared hosting, open the file and delete the first lines `CREATE DATABASE …` and `USE …`, then select *your* database in phpMyAdmin before importing.
3. **Create the real administrator** on your own computer:
   `php database/create_admin.php "Full Name" username "a-long-password"`
   Run the printed `INSERT` in phpMyAdmin (SQL tab). Gate staff: add a 4th argument `guard`.
4. **Configure:** copy `config/config.local.example.php` to `config/config.local.php` and fill in the school name and database details. It sets `demo_mode` and `debug` to `false` (hides demo banners/logins, disables reset, shows generic errors).
5. **Upload limits:** photos can be up to 5 MB. The included `.user.ini` raises PHP's limits for this; if uploads fail on your host, set `upload_max_filesize` to at least 6M and `post_max_size` to at least 12M in the hosting panel.
6. **Upload** the project by FTP/File Manager. Do **not** upload `database/sample_data.sql`. Make `uploads/` writable (permissions 755 or 775).
7. **Force HTTPS:** in `.htaccess` uncomment the three redirect lines.
8. **Test:** sign in, register a test student, run a release, then delete the test data.
9. **Back up** regularly: phpMyAdmin → Export (database) **and** download the `uploads/` folder.

On nginx (not Apache), `.htaccess` is ignored: add equivalent rules that deny web access to `app/`, `config/`, `database/` and `uploads/`.

### Already built in for online use
Password hashing · sign-in lockout (5 failures / 10 min) · prepared statements · output escaping · CSRF tokens · role-based access · **private photos** (served only to signed-in staff) · upload validation · server-side re-check of guardian authorization on release · supervisor password for letter-based releases · non-editable logs · secure cookies + security headers · generic errors in production.

### Still needed before real students' data is used
- **Data Privacy Act (RA 10173):** privacy notice, parental consent form, retention policy, and an appointed data protection officer for the school.
- Screens for changing passwords and managing staff accounts (currently done through SQL).
- SMS/email notice to the guardian on release, offline fallback for internet outages, two-factor sign-in, automatic backups, and an independent security review.

---
## 4. MVC structure
```
sgvms/
├─ index.php              Front controller (every request enters here)
├─ config/                config.php (defaults), config.local.example.php (live settings template)
├─ app/
│  ├─ core/               App (router), Controller, Model, Database, helpers
│  ├─ models/             User, Student, Guardian, Log        ← data & business rules
│  ├─ controllers/        Auth, Dashboard, Students, Gate, Logs, Photo ← request handling
│  └─ views/              layouts/, auth/, dashboard/, students/, gate/, logs/ ← HTML pages
├─ assets/                css, js, offline QR libraries
├─ uploads/               Uploaded photos (blocked from direct web access)
└─ database/              schema.sql, sample_data.sql, create_admin.php
```
URLs look like `index.php?url=controller/method/param` (example: `?url=gate/verify/3`).

### Design
Chalkboard green and sky blue, set in Lexend (a typeface designed for readability). Fonts are bundled in `assets/fonts/`,
so the app looks the same offline. Colors and sizes are CSS variables at the top of `assets/css/style.css`; change `--board`, `--leaf` and `--sky-*` to restyle the whole system.

### Database tables
`users` · `students` · `guardians` · `student_guardians` (who may pick up whom; `is_authorized` can be revoked) · `logs` (time-in / released / released by letter / denied) · `login_attempts`.
