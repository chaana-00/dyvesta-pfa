# DYVESTA — Personal File Audit (PFA)

A full source-code, database-backed rebuild of the Personal File Audit system,
built with plain **HTML, CSS, JavaScript, PHP and MySQL** (no framework, no
Composer dependencies) so it can be opened directly in VS Code and deployed to
any standard PHP + MySQL host (XAMPP/WAMP/MAMP, LAMP, cPanel, etc).

This package ships **empty of any personal/employee data** — you bring your
own Excel files for Employee Master, Auditor Master and Audit Checklist
Master, or start entering records by hand.

---

## 1. What's inside

```
dyvesta-pfa/
├─ index.php                 Login screen
├─ setup.php                 First-run screen: create the first admin account
├─ login_process.php / logout.php
├─ dashboard.php              Overview, stats, checklist compliance, charts
├─ my_audits.php              6-point checklist entry screen
├─ issues_found.php           Flagged (No/Incorrect) audits
├─ pending_audits.php         Not-started / in-progress audits
├─ employee_allocation.php    Who-audits-whom, allocate/reassign (admin)
├─ master_data.php            Employee / Auditor / Checklist masters (admin)
├─ search.php                 Global top-bar search
├─ dashboard_print.php        Print-friendly view used for "Export PDF"
├─ config/db.php              Database connection settings
├─ database/schema.sql        Full MySQL schema (run this first)
├─ includes/                  Shared PHP layout & helper functions
├─ lib/XLSX.php               Dependency-free .xlsx reader & writer
├─ api/                       AJAX / form endpoints (see below)
└─ assets/css, assets/js      Purple dark theme + front-end helpers
```

## 2. Requirements

- PHP 8.0+ with the **pdo_mysql**, **zip** and **simplexml** extensions
  (all enabled by default on almost every install — no Composer needed)
- MySQL 5.7+ / MariaDB 10.3+
- Any web server (Apache/Nginx) or `php -S localhost:8000` for local testing

## 3. Setup

1. **Create the database.** Import the schema:
   ```bash
   mysql -u root -p < database/schema.sql
   ```
   This creates the `dyvesta_pfa` database, all tables, the default
   6-item audit checklist, and the `active_cycle` setting
   ("Personal File Audit — 2026"). **No employees, auditors, or logins
   are seeded** — see step 5.

2. **Point the app at your database.** Edit `config/db.php` (or set the
   `DYVESTA_DB_HOST` / `DYVESTA_DB_NAME` / `DYVESTA_DB_USER` /
   `DYVESTA_DB_PASS` / `DYVESTA_DB_PORT` environment variables):
   ```php
   define('DB_HOST', '127.0.0.1');
   define('DB_NAME', 'dyvesta_pfa');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```

3. **Serve the folder.** In VS Code with the PHP extension, or plain CLI:
   ```bash
   php -S localhost:8000
   ```
   Then open `http://localhost:8000/`.

4. **First-run setup screen.** Because the `users` table is empty, the
   app automatically redirects you to `setup.php` — a one-time form to
   create the first **Admin** account. It's pre-filled with the name
   **Chanaka** (per your request), so you only need to add an email and
   choose a password. This calls PHP's own `password_hash()` on your
   server, so there's no risk of a mismatched hash.

5. **Sign in** at `index.php` with the email/password you just created.

## 4. Getting your data in

Everything is driven from **Master Data** (admin only):

1. **Auditor Master** — create each auditor first (Employee No, Name,
   Email). A password is generated and shown once; share it with them.
2. **Employee Master** — either add employees one by one, or download
   the template and bulk-import an `.xlsx` with columns:
   `Employee No · Employee Name · Designation · Supervisor`
   (Supervisor = the auditor's employee no). Rows are matched on
   Employee No — re-importing updates existing rows instead of
   duplicating them.
3. **Audit Checklist Master** — the 6-point checklist ships pre-loaded
   (it's the audit template itself, not personal data) but you can add,
   edit, or bulk-import more items with columns:
   `Code · Short Name · Audit Item · Category · Answer Type`.

Once employees have a Supervisor set, go to **Employee Allocation** and
click **Load Employee Master** to generate this cycle's allocations from
the Supervisor column in one go, or use **+ New Allocation** to allocate
one employee at a time.

## 5. How auditing works

- Each auditor signs in and sees only **their** employees under **My Audits**.
- Selecting an employee shows the standardized checklist with
  Yes / No / N/A dropdowns. Choosing **No** reveals a mandatory remark box.
- Every answer is saved instantly via AJAX (`api/save_answer.php`) and the
  employee's status (Not Started → In Progress → Completed, or Issues Found
  if any "No" is present) recalculates automatically.
- **Issues Found** and **Pending Audits** are simply filtered views of the
  same `allocations` table.
- Admins can see everything (toggle "My Assigned" / "All Employees" on
  My Audits), reassign auditors, and manage Master Data.

## 6. Excel import/export

`lib/XLSX.php` contains a small, dependency-free `.xlsx` reader and writer
built only on PHP's built-in `ZipArchive` + `SimpleXML` — there is
intentionally no Composer/PhpSpreadsheet requirement, so the project runs
on a bare PHP install. It supports exactly what this app needs: a single
sheet of a header row plus plain text/number rows, which covers every
Download Template / Import Excel / Export Excel action in the system.

"Export PDF" on the Dashboard opens a print-formatted page
(`dashboard_print.php`) and triggers the browser's print dialog — pick
"Save as PDF" there. This avoids bundling a heavyweight PDF library.

## 7. Theme

Dark **purple** theme by default (CSS variables in
`assets/css/style.css`), with a light-mode toggle (moon icon, bottom-left)
that persists via `localStorage`. Rebrand by changing `settings.app_name`
in the database, or editing the `<?= app_name() ?>` calls.

## 8. Security notes for production

- Change the demo default settings in `config/db.php` before deploying.
- Serve over HTTPS and set `session.cookie_secure` in `php.ini`.
- The `.htaccess` files under `config/`, `includes/`, `lib/` and
  `database/` block direct web access to those folders on Apache; if you
  use Nginx, add an equivalent `location` block denying those paths.
- Consider rate-limiting `login_process.php` if exposed publicly.

---

Built for **DYVESTA** — Personal File Audit, 2026 cycle.
