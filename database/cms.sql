-- =====================================================================
--  NIC CMS  -  DGL 123 Introduction to PHP
--  A dynamic, role-based database for a small Content Management System
--
--  HOW TO USE (XAMPP):
--    1. Start Apache + MySQL in the XAMPP Control Panel
--    2. Open http://localhost/phpmyadmin
--    3. Click "Import" -> choose this file -> "Import"   (it creates the DB itself)
--
--  WHY "DYNAMIC"?
--    Nothing about who-can-do-what is hard-coded in PHP. Roles, permissions,
--    menus, site settings and page content all live in tables. Change a row
--    in phpMyAdmin (or the admin panel) and the website changes immediately -
--    no PHP edits needed.
--
--  All demo accounts use the password:  Password123!
-- =====================================================================

DROP DATABASE IF EXISTS nic_cms;
CREATE DATABASE nic_cms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE nic_cms;

-- ---------------------------------------------------------------------
-- 1. ROLES  - the "levels" of user (admin, editor, author, subscriber)
--    `level` lets PHP compare roles: a higher number = more power.
-- ---------------------------------------------------------------------
CREATE TABLE roles (
    role_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_name   VARCHAR(50)  NOT NULL UNIQUE,     -- machine name: 'admin'
    label       VARCHAR(100) NOT NULL,            -- display name: 'Administrator'
    level       TINYINT UNSIGNED NOT NULL DEFAULT 1,
    description VARCHAR(255) NULL,
    is_default  TINYINT(1) NOT NULL DEFAULT 0,    -- role given to new sign-ups
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. PERMISSIONS - single actions a user can be allowed to do.
--    PHP only ever asks: "does this user have permission X?"
-- ---------------------------------------------------------------------
CREATE TABLE permissions (
    permission_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    perm_key      VARCHAR(50)  NOT NULL UNIQUE,   -- 'manage_users'
    label         VARCHAR(100) NOT NULL,
    perm_group    VARCHAR(50)  NOT NULL DEFAULT 'General'
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. ROLE_PERMISSIONS - junction table (many-to-many)
--    One role has many permissions; one permission belongs to many roles.
-- ---------------------------------------------------------------------
CREATE TABLE role_permissions (
    role_id       INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_rp_role FOREIGN KEY (role_id)
        REFERENCES roles(role_id) ON DELETE CASCADE,
    CONSTRAINT fk_rp_perm FOREIGN KEY (permission_id)
        REFERENCES permissions(permission_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. USERS - every account belongs to exactly ONE role (one-to-many)
--    Passwords are NEVER stored in plain text: password_hash() in PHP.
-- ---------------------------------------------------------------------
CREATE TABLE users (
    user_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id       INT UNSIGNED NOT NULL,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    email         VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name     VARCHAR(100) NOT NULL,
    bio           TEXT NULL,
    status        ENUM('active','suspended') NOT NULL DEFAULT 'active',
    last_login    DATETIME NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id)
        REFERENCES roles(role_id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. CATEGORIES - groups for blog posts
-- ---------------------------------------------------------------------
CREATE TABLE categories (
    category_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    slug        VARCHAR(120) NOT NULL UNIQUE,     -- URL-friendly: 'web-development'
    description VARCHAR(255) NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. POSTS - blog articles. Each post has ONE author and ONE category.
--    status controls visibility: only 'published' shows on the public site.
-- ---------------------------------------------------------------------
CREATE TABLE posts (
    post_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED NOT NULL,
    category_id  INT UNSIGNED NULL,
    title        VARCHAR(200) NOT NULL,
    slug         VARCHAR(220) NOT NULL UNIQUE,
    excerpt      VARCHAR(300) NULL,
    content      TEXT NOT NULL,
    status       ENUM('draft','pending','published') NOT NULL DEFAULT 'draft',
    views        INT UNSIGNED NOT NULL DEFAULT 0,
    published_at DATETIME NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_posts_user FOREIGN KEY (user_id)
        REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_posts_category FOREIGN KEY (category_id)
        REFERENCES categories(category_id) ON DELETE SET NULL,
    INDEX idx_posts_status (status, published_at)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7. PAGES - static pages (About, Contact...). show_in_menu + menu_order
--    build the navigation bar dynamically.
-- ---------------------------------------------------------------------
CREATE TABLE pages (
    page_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title        VARCHAR(200) NOT NULL,
    slug         VARCHAR(220) NOT NULL UNIQUE,
    content      TEXT NOT NULL,
    show_in_menu TINYINT(1) NOT NULL DEFAULT 1,
    menu_order   INT NOT NULL DEFAULT 0,
    status       ENUM('draft','published') NOT NULL DEFAULT 'published',
    updated_by   INT UNSIGNED NULL,
    updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_pages_user FOREIGN KEY (updated_by)
        REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 8. COMMENTS - logged-in users comment on posts; moderated before showing
-- ---------------------------------------------------------------------
CREATE TABLE comments (
    comment_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id    INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    body       TEXT NOT NULL,
    status     ENUM('pending','approved') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_comments_post FOREIGN KEY (post_id)
        REFERENCES posts(post_id) ON DELETE CASCADE,
    CONSTRAINT fk_comments_user FOREIGN KEY (user_id)
        REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 9. SETTINGS - key/value pairs: site name, tagline, posts per page...
--    The website reads these instead of hard-coding them.
-- ---------------------------------------------------------------------
CREATE TABLE settings (
    setting_key   VARCHAR(50)  PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL,
    label         VARCHAR(100) NOT NULL,
    input_type    ENUM('text','number','checkbox') NOT NULL DEFAULT 'text'
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 10. ACTIVITY_LOG - who did what, and when (audit trail)
-- ---------------------------------------------------------------------
CREATE TABLE activity_log (
    log_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NULL,
    action     VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_log_user FOREIGN KEY (user_id)
        REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;


-- =====================================================================
--  SEED DATA
-- =====================================================================

INSERT INTO roles (role_name, label, level, description, is_default) VALUES
('admin',      'Administrator', 100, 'Full control of the site, users and roles', 0),
('editor',     'Editor',         70, 'Manages all content: posts, pages, categories, comments', 0),
('author',     'Author',         40, 'Writes and publishes their own posts', 0),
('subscriber', 'Subscriber',     10, 'Reads the site, comments, edits own profile', 1);

INSERT INTO permissions (perm_key, label, perm_group) VALUES
('view_dashboard',     'Access the admin dashboard',            'General'),
('create_posts',       'Write new posts',                       'Posts'),
('publish_posts',      'Publish posts (not just submit)',       'Posts'),
('edit_any_post',      'Edit or delete ANY user''s post',       'Posts'),
('manage_categories',  'Create / edit / delete categories',     'Content'),
('manage_pages',       'Create / edit / delete pages',          'Content'),
('moderate_comments',  'Approve or delete comments',            'Content'),
('manage_users',       'Create, edit, suspend users',           'Administration'),
('manage_roles',       'Change what each role can do',          'Administration'),
('manage_settings',    'Change site settings',                  'Administration'),
('view_activity_log',  'View the activity log',                 'Administration');

-- Admin gets EVERY permission (a sub-query, so it stays correct if you add more)
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id FROM roles r CROSS JOIN permissions p
WHERE r.role_name = 'admin';

-- Editor
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id FROM roles r JOIN permissions p
WHERE r.role_name = 'editor'
  AND p.perm_key IN ('view_dashboard','create_posts','publish_posts','edit_any_post',
                     'manage_categories','manage_pages','moderate_comments');

-- Author: writes and publishes own posts only
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id FROM roles r JOIN permissions p
WHERE r.role_name = 'author'
  AND p.perm_key IN ('view_dashboard','create_posts','publish_posts');

-- Subscriber: no back-end permissions at all

-- Demo users  (password for all: Password123!)
INSERT INTO users (role_id, username, email, password_hash, full_name, bio) VALUES
((SELECT role_id FROM roles WHERE role_name='admin'),      'admin',  'admin@niccms.test',
 '$2y$10$Z2SuIwH4pRaZlEXpQAOdhObHlI57yhvFVsTjEqr8kc739JaLjc0d6', 'Ada Admin', 'Runs the site.'),
((SELECT role_id FROM roles WHERE role_name='editor'),     'editor', 'editor@niccms.test',
 '$2y$10$Z2SuIwH4pRaZlEXpQAOdhObHlI57yhvFVsTjEqr8kc739JaLjc0d6', 'Eddie Editor', 'Keeps content tidy.'),
((SELECT role_id FROM roles WHERE role_name='author'),     'author', 'author@niccms.test',
 '$2y$10$Z2SuIwH4pRaZlEXpQAOdhObHlI57yhvFVsTjEqr8kc739JaLjc0d6', 'Aria Author', 'Writes about the web.'),
((SELECT role_id FROM roles WHERE role_name='author'),     'writer2','writer2@niccms.test',
 '$2y$10$Z2SuIwH4pRaZlEXpQAOdhObHlI57yhvFVsTjEqr8kc739JaLjc0d6', 'Sam Second', 'Second author, to test "own posts only".'),
((SELECT role_id FROM roles WHERE role_name='subscriber'), 'reader', 'reader@niccms.test',
 '$2y$10$Z2SuIwH4pRaZlEXpQAOdhObHlI57yhvFVsTjEqr8kc739JaLjc0d6', 'Riley Reader', NULL);

INSERT INTO categories (name, slug, description) VALUES
('Web Development', 'web-development', 'HTML, CSS, PHP and everything in between'),
('Databases',       'databases',       'MySQL, SQL and data design'),
('Campus News',     'campus-news',     'What is happening at NIC');

INSERT INTO posts (user_id, category_id, title, slug, excerpt, content, status, published_at) VALUES
(3, 1, 'Hello PHP: Your First Dynamic Page', 'hello-php-your-first-dynamic-page',
 'Static HTML shows the same thing to everyone. PHP changes that.',
 'Static HTML shows the same thing to every visitor. PHP runs on the server before the page is sent, so it can build a different page for every request.\n\nIn this CMS, every post you are reading was pulled from a MySQL table a fraction of a second ago.',
 'published', NOW() - INTERVAL 6 DAY),
(3, 2, 'Why We Never Store Plain-Text Passwords', 'why-we-never-store-plain-text-passwords',
 'password_hash() and password_verify() in two minutes.',
 'If a database leaks, plain-text passwords leak with it. PHP gives us password_hash() to store a one-way hash and password_verify() to check a login attempt against it.\n\nOpen the users table in phpMyAdmin and look at the password_hash column - you will not find a single real password.',
 'published', NOW() - INTERVAL 4 DAY),
(2, 2, 'Joins Explained with Our Own Tables', 'joins-explained-with-our-own-tables',
 'Posts, users and categories, stitched together with JOIN.',
 'The home page shows the author name and category for every post, but the posts table only stores user_id and category_id. A JOIN stitches the three tables together in a single query.',
 'published', NOW() - INTERVAL 2 DAY),
(1, 3, 'Welcome to the NIC CMS', 'welcome-to-the-nic-cms',
 'A tiny CMS built live in DGL 123.',
 'This site was built from scratch in class with PHP, PDO and MySQL running on XAMPP. Log in with one of the demo accounts to see how the admin panel changes depending on your role.',
 'published', NOW() - INTERVAL 1 DAY),
(4, 1, 'Draft: Sessions and Cookies', 'draft-sessions-and-cookies',
 'Work in progress.', 'This post is a draft. Only its author, editors and admins can see it in the admin panel.',
 'draft', NULL),
(3, 1, 'Pending: Prepared Statements', 'pending-prepared-statements',
 'Waiting for review.', 'This post is waiting for an editor to publish it.',
 'pending', NULL);

INSERT INTO pages (title, slug, content, show_in_menu, menu_order, updated_by) VALUES
('About',   'about',   'NIC CMS is a teaching project for DGL 123 at North Island College. Everything you see - menus, settings, posts - comes from the database.', 1, 1, 1),
('Contact', 'contact', 'North Island College, Comox Valley campus.\n\nThis page lives in the `pages` table. Edit it in the admin panel and refresh.', 1, 2, 1),
('Privacy', 'privacy', 'Demo site. No real personal data is collected.', 0, 3, 1);

INSERT INTO comments (post_id, user_id, body, status) VALUES
(1, 5, 'This finally made PHP click for me!', 'approved'),
(2, 5, 'Checked phpMyAdmin - can confirm, no passwords in sight.', 'approved'),
(3, 5, 'Can we do LEFT JOIN next week?', 'pending');

INSERT INTO settings (setting_key, setting_value, label, input_type) VALUES
('site_name',          'NIC CMS',                         'Site name',                 'text'),
('site_tagline',       'Built with PHP + MySQL on XAMPP', 'Tagline',                   'text'),
('posts_per_page',     '3',                               'Posts per page (home)',     'number'),
('allow_registration', '1',                               'Allow new sign-ups',        'checkbox'),
('allow_comments',     '1',                               'Allow comments on posts',   'checkbox'),
('footer_text',        'DGL 123 - North Island College',  'Footer text',               'text');

INSERT INTO activity_log (user_id, action, ip_address) VALUES
(1, 'Installed the database', '127.0.0.1');


-- =====================================================================
--  A VIEW - a saved query you can SELECT from like a table.
--  Handy for demos: SELECT * FROM v_user_permissions WHERE username='author';
-- =====================================================================
CREATE VIEW v_user_permissions AS
SELECT u.user_id, u.username, r.role_name, p.perm_key
FROM users u
JOIN roles r            ON r.role_id = u.role_id
JOIN role_permissions rp ON rp.role_id = r.role_id
JOIN permissions p      ON p.permission_id = rp.permission_id;
