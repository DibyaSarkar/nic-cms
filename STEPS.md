# Building a Role-Based CMS with PHP + MySQL (XAMPP)

**DGL 123 – Introduction to PHP · Live demo walkthrough**

By the end of this demo we have a working Content Management System where **what you can do depends on who you are** — and that is controlled by the *database*, not by `if ($role == 'admin')` scattered through the code.

| Role | Can do |
|---|---|
| **Administrator** | Everything: users, roles, settings, activity log |
| **Editor** | All posts, pages/menu, categories, comments |
| **Author** | Write and publish **their own** posts |
| **Subscriber** | Read, comment, edit own profile |

---

## Step 0 — The big picture

```
Browser  ──request──▶  Apache (XAMPP)  ──runs──▶  PHP  ──PDO──▶  MySQL
   ▲                                                  │
   └──────────────── HTML built by PHP ◀──────────────┘
```

PHP sits in the middle. It reads the request (`$_GET`, `$_POST`, `$_SESSION`), asks MySQL for data with SQL, and builds HTML. **The browser never talks to MySQL directly.**

---

## Step 1 — Set up XAMPP

1. Open the **XAMPP Control Panel** and click **Start** on **Apache** and **MySQL**. Both should turn green.
2. Copy the `nic-cms` folder into `htdocs`:
   - Windows: `C:\xampp\htdocs\nic-cms`
   - macOS: `/Applications/XAMPP/htdocs/nic-cms`
3. Visit `http://localhost/` — the XAMPP welcome page means Apache works.

> **If MySQL won't start:** something else is already using port **3306** (often a separately installed MySQL Server). Either stop that service, or change XAMPP's port in *Config → my.ini* and update `DB_PORT` in `config/config.php` to match.
>
> **"MySQL" in XAMPP:** recent XAMPP builds label the database "MySQL" but ship MariaDB, a drop-in fork. Everything in this project is standard MySQL 8 syntax and was tested on MySQL 8.0, so it runs on either.

---

## Step 2 — Design the database (before writing any PHP)

Open `database/cms.sql` and walk through it top to bottom. Ten tables:

```
roles ──< users ──< posts >── categories
  │          │        │
  │          │        └──< comments >── users
  │          └──< activity_log
  └──< role_permissions >── permissions

pages      settings      (stand-alone, drive menu + site config)
```

Talking points:

- **One-to-many:** a role has many users; a user has many posts. The "many" side holds the foreign key (`users.role_id`, `posts.user_id`).
- **Many-to-many:** a role has many permissions *and* a permission belongs to many roles. That needs a **junction table**: `role_permissions(role_id, permission_id)` with a composite primary key.
- **Foreign-key actions are decisions, not defaults:**
  - Delete a user → `ON DELETE CASCADE` removes their posts.
  - Delete a category → `ON DELETE SET NULL` keeps the posts, just uncategorised.
  - Delete a role that still has users → `ON DELETE RESTRICT` blocks it.
- **`ENUM`** for fixed choices (`draft`/`pending`/`published`).
- **`level`** on roles lets us compare roles numerically ("you can't assign a role above your own").
- **`is_default`** decides which role new sign-ups receive.
- **`password_hash VARCHAR(255)`** — we store a hash, never the password.

### Why this database is "dynamic"

| Want to… | Old way (hard-coded) | This CMS |
|---|---|---|
| Let authors moderate comments | Edit PHP `if` statements | Tick one box in **Roles & Permissions** |
| Add a "Moderator" role | Edit PHP everywhere | Add a row in `roles` |
| Add a menu link | Edit `header.php` | Add a row in `pages` |
| Rename the site | Find/replace in code | Change a row in `settings` |
| Add a new setting field | Edit the form HTML | Add a row in `settings` — the form builds itself |

---

## Step 3 — Import the database

1. Go to `http://localhost/phpmyadmin`
2. Click **Import** → **Choose file** → `database/cms.sql` → **Import** (bottom of page).
3. You should now see a `nic_cms` database with 10 tables and a view.

Try these in the **SQL** tab to show the data is connected:

```sql
-- Who can do what? (uses the view at the bottom of cms.sql)
SELECT * FROM v_user_permissions WHERE username = 'author';

-- Posts with their author and category names
SELECT p.title, u.username, c.name AS category, p.status
FROM posts p
JOIN users u ON u.user_id = p.user_id
LEFT JOIN categories c ON c.category_id = p.category_id;

-- Notice: no real passwords anywhere
SELECT username, password_hash FROM users;
```

---

## Step 4 — Connect PHP to MySQL

**`config/config.php`** — the only file with connection details. XAMPP defaults: user `root`, empty password.

Open **`http://localhost/nic-cms/test-connection.php`**. A green "Connected" and a table of users means PHP ↔ MySQL works. This file is deliberately standalone (no includes) so students see a bare `new PDO(...)` with nothing hidden.

Then open **`includes/db.php`** — the version the real site uses:

```php
$pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // errors become exceptions
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // $row['title'], not $row[0]
    PDO::ATTR_EMULATE_PREPARES   => false,                   // real prepared statements
]);
```

Key idea: `static $pdo` inside `db()` means **one connection per request**, reused by every query.

---

## Step 5 — Shared building blocks

| File | Job |
|---|---|
| `includes/functions.php` | `e()` escape output · `url()` · `redirect()` · `setting()` · `slugify()` · flash messages · CSRF token · `log_activity()` |
| `includes/auth.php` | sessions, `current_user()`, `can()`, `require_permission()`, login/logout |
| `includes/header.php` / `footer.php` | Layout; menu built from the `pages` table |

The two habits to drill:

1. **Every value going *into* SQL** goes through a prepared statement `?` placeholder.
2. **Every value going *out* to HTML** goes through `e()`.

---

## Step 6 — READ: the public site (`index.php`, `post.php`, `page.php`)

`index.php` shows several SQL ideas in one page:

- `WHERE p.status = 'published'` — drafts stay hidden.
- `JOIN users` and `LEFT JOIN categories` — why `LEFT`? A post may have no category.
- `LIMIT … OFFSET …` + `COUNT(*)` → pagination; *posts per page* comes from `settings`.
- `?category=databases` → the WHERE clause is built from an array of conditions + a matching array of parameters.
- Sidebar: `COUNT()` + `GROUP BY` for posts per category.

`post.php` adds `UPDATE posts SET views = views + 1` and the **Post/Redirect/Get** pattern for comments (refreshing won't re-submit).

**Demo:** in phpMyAdmin set a post's `status` to `draft`, refresh the home page — it vanishes.

---

## Step 7 — Users: register, log in, log out

**`register.php`**
1. Validate (regex for username, `filter_var` for email, length + confirm for password).
2. Check `username`/`email` aren't taken.
3. Find the default role: `SELECT role_id FROM roles WHERE is_default = 1`.
4. `INSERT` with `password_hash($password, PASSWORD_DEFAULT)`.

**`login.php` → `attempt_login()`**
1. `SELECT … WHERE username = ? OR email = ?`
2. `password_verify($typed, $row['password_hash'])`
3. Same error for "no such user" and "wrong password" (don't tell attackers which).
4. `session_regenerate_id(true)` then `$_SESSION['user_id'] = …`

The session stores **only the user id**. Role and permissions are re-read from the database on each request, so a change made by an admin takes effect immediately — even for someone already logged in.

**Demo:** register a new account → it lands as *Subscriber*. Show the new row and hash in phpMyAdmin.

---

## Step 8 — Permissions: the heart of the CMS (`includes/auth.php`)

```php
function can(string $permission): bool {
    return in_array($permission, user_permissions(), true);
}
```

`user_permissions()` runs one query:

```sql
SELECT p.perm_key
FROM role_permissions rp
JOIN permissions p ON p.permission_id = rp.permission_id
WHERE rp.role_id = ?
```

Every protected page starts with one line:

```php
require_permission('manage_users');   // 403 page if the role lacks it
```

and the admin sidebar (`admin/_layout_top.php`) only prints links the user `can()` reach.

**Demo:** log in as each demo account (password `Password123!`) and compare the sidebar:

| Account | Sidebar shows |
|---|---|
| `admin` | everything |
| `editor` | Dashboard, Posts, Categories, Pages, Comments |
| `author` | Dashboard, Posts |
| `reader` | no Admin link at all |

Then, as `author`, type `/nic-cms/admin/users.php` into the address bar. **Hiding a link is not security — the page check is.**

---

## Step 9 — CREATE / UPDATE / DELETE with ownership (`admin/posts.php`, `admin/post-edit.php`)

One form handles both insert and update: no `?id=` → `INSERT` (then `lastInsertId()`), with `?id=` → `UPDATE`.

Two rules come straight from permissions:

```php
// Ownership: authors only touch their own rows
'DELETE FROM posts WHERE post_id = ?' . ($seeAll ? '' : ' AND user_id = ?')

// Workflow: no publish_posts → can only choose draft or pending
$allowedStatuses = can('publish_posts') ? ['draft','pending','published'] : ['draft','pending'];
```

Note the server re-checks the submitted status — never trust a `<select>` because a student can edit the HTML in DevTools.

**Demo:** log in as `author`, try `admin/post-edit.php?id=3` (an editor's post) → blocked. Log in as `writer2` and confirm only Sam's draft shows.

Same CRUD pattern, smaller: `categories.php`, `pages.php` (which also controls the **menu**), `comments.php`.

---

## Step 10 — Make roles dynamic (`admin/roles.php`)

A grid of roles × permissions. Saving:

```php
$pdo->beginTransaction();
  DELETE FROM role_permissions WHERE role_id = ?   -- clear
  INSERT INTO role_permissions VALUES (?, ?)       -- one per ticked box
$pdo->commit();       // or rollBack() if anything failed
```

**The money demo:**
1. As `admin`, tick **Approve or delete comments** for **Author**. Save.
2. In another browser (or private window) logged in as `author`, refresh → **Comments** appears in the sidebar and the page works.
3. Untick it → it disappears. Zero lines of PHP changed.

Also: **Add a role** ("Moderator", level 50), give it permissions, assign a user to it in **Users**. Try to delete a role that still has users → blocked by the foreign key.

Built-in safety: Administrator is locked (can't remove its own powers), admins can't demote/suspend/delete themselves, and nobody can hand out a role above their own level.

---

## Step 11 — Dynamic settings (`admin/settings.php`)

The settings form is generated from the `settings` rows; `input_type` picks the widget.

**Demo:**
1. Change *Site name* → header and browser tab update everywhere.
2. Untick *Allow new sign-ups* → the Sign up button disappears and `register.php` is closed.
3. In phpMyAdmin run:
   ```sql
   INSERT INTO settings VALUES ('contact_email', 'info@nic.bc.ca', 'Contact email', 'text');
   ```
   Refresh Settings — a new field appears. (Then challenge: show it in the footer.)

---

## Step 12 — Security checklist (recap)

| Threat | Where we handle it |
|---|---|
| SQL injection | Prepared statements everywhere; table names whitelisted in `unique_slug()` |
| XSS | `e()` on every output |
| Stolen DB → stolen passwords | `password_hash()` / `password_verify()` |
| Session fixation | `session_regenerate_id(true)` on login |
| Forged form posts (CSRF) | Hidden token in every form, `verify_csrf()` on every POST |
| Deleting via a link | Deletes only via POST forms |
| Privilege escalation | `require_permission()` on every admin page, ownership in SQL `WHERE`, server-side status check, role-level ceiling |
| Leaking errors | `DEBUG` flag in config — turn it off on a real server |

---

## File map

```
nic-cms/
├── config/config.php          DB settings (edit this)
├── database/cms.sql           schema + seed data (import this)
├── includes/
│   ├── db.php                 PDO connection
│   ├── functions.php          helpers
│   ├── auth.php               sessions + permissions
│   ├── header.php / footer.php
├── admin/
│   ├── _layout_top/bottom.php dynamic sidebar
│   ├── index.php              dashboard
│   ├── posts.php, post-edit.php
│   ├── categories.php, pages.php, comments.php
│   ├── users.php, roles.php, settings.php, activity.php
├── assets/style.css
├── index.php, post.php, page.php
├── login.php, register.php, logout.php, profile.php
└── test-connection.php        delete after Step 4
```

---

## Practice tasks for students

1. **Easy** — Show the author's `bio` under each post on `post.php` (hint: you already JOIN `users`).
2. **Easy** — Display the new `contact_email` setting in the footer.
3. **Medium** — Add a `view_drafts_preview` permission so editors can open a draft on the public `post.php`. Add the row in `permissions`, tick it in the grid, then use `can()` in `post.php`.
4. **Medium** — On the Users page, add a search box that filters by username or email with `LIKE`.
5. **Challenge** — Add a `tags` table and a `post_tags` junction table (many-to-many, just like `role_permissions`) and let authors tag their posts.

**Reset at any time:** re-import `database/cms.sql` — it drops and recreates `nic_cms`.
