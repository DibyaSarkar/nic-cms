# NIC CMS — PHP + MySQL role-based CMS (DGL 123)

A small, fully commented Content Management System for teaching PHP back-end development with XAMPP.

## Quick start (5 minutes)

1. Start **Apache** and **MySQL** in the XAMPP Control Panel.
2. Copy this folder to `htdocs/nic-cms`.
3. In `http://localhost/phpmyadmin` → **Import** → `database/cms.sql`.
4. Visit `http://localhost/nic-cms/test-connection.php` (should be green), then `http://localhost/nic-cms/`.

If your folder name or MySQL login differs, edit `config/config.php`.

## Demo accounts

All passwords: **`Password123!`**

| Username | Role |
|---|---|
| `admin` | Administrator |
| `editor` | Editor |
| `author` | Author |
| `writer2` | Author (second one, to show "own posts only") |
| `reader` | Subscriber |

## Teaching walkthrough

See **[STEPS.md](STEPS.md)** — 12 steps from XAMPP setup to dynamic roles, with demo scripts and practice tasks.

Requires PHP 8.1+ (any current XAMPP). Tested on MySQL 8.0.
