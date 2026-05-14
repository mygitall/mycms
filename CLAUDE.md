# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

资源目录管理系统 — a resource directory manager with guestbook. Single-file vanilla PHP backend + vanilla JS frontend, no framework, no build step. Runs on MAMP (Apache + MySQL + PHP).

## Architecture

```
index.php          # Frontend SPA: HTML + CSS + JS in one file (~2300 lines)
api/index.php      # REST API: single entry, routes by ?action= and ?type=
api/db.php         # DB connection, schema, lazy migrations
api/upload.php     # File upload endpoint
api/compat.php     # PHP 5.6–8.x polyfills (random_bytes, hash_equals, password_hash)
api/init.php       # DB init script (CLI or browser)
admin/index.php    # Backend management page
config.php         # DB credentials (gitignored, from config.example.php)
install.php        # Web installer
```

## API routing

All API routes go through `api/index.php`. Request method + `?action=` determines behavior:

- **GET** — no action: returns all dirs + messages. `?type=dirs`: dirs only. `?type=messages`: messages only.
- **POST** — `?action=dir`, `file_create`, `message`, `login`, `logout`, `check`, `change_password`, `dir_update`, etc.
- **PUT** — `?action=dir`, `file`, `message`, `reorder_dir`, `toggle_pin`
- **DELETE** — `?action=dir&id=N`, `file&dir_id=N&file_name=X`, `message&id=N`

Auth via PHP sessions (`$_SESSION['logged_in']`). Single admin account in `auth` table.

CSRF: state-changing methods (POST/PUT/DELETE) require `X-CSRF-Token` header matching `$_SESSION['csrf_token']`, except login/logout/check actions.

## Database patterns

MySQL via PDO. Every `createDb()` call auto-runs lazy migrations — functions that do `ALTER TABLE … ADD COLUMN` wrapped in try/catch to silently skip if column/index already exists. Pattern in `api/db.php`:

```php
function migrateXxx($db) {
    try {
        $db->exec("ALTER TABLE …");
    } catch (PDOException $e) {
        // column already exists, ignore
    }
}
```

Tables: `directories`, `messages`, `auth`, `login_attempts`.

### Directory ordering

Directories ordered by `is_pinned DESC, sort_order ASC, id DESC`. Pinned items always on top, within pinned group most recently pinned first (negative sort_order), within unpinned group by manual sort_order.

### `toggle_pin` behavior (api/index.php PUT section)

- Pinning: sets `sort_order` to `MIN(existing_pinned_sort_order) - 1` so most recently pinned gets the most negative value and appears first among pinned items.
- Unpinning: sets `sort_order` to `MAX(existing_unpinned_sort_order) + 1` so it goes to the end of unpinned items.

## Frontend patterns

`index.php` is the entire frontend — HTML structure, CSS styles, and JS logic in one file. No JS modules or bundler.

Key JS globals: `isLoggedIn`, `csrfToken`, `allDirs`, `PAGE_SIZE`, `currentPage`, `isMobile`.

DOM rendering: `createDirItemEl(dir)` builds directory rows with inline HTML strings. `createSubmenuEl(dir)` builds file lists. Both use `esc()` for JS string escaping and `escHtml()` for HTML escaping in attribute values.

Admin visibility: CSS class `admin-only` hides elements by default. When logged in, `<body>` gets `class="show-admin"` which overrides display to `block`/`unset`.

Mobile mode: `window.innerWidth <= 768` triggers infinite-scroll ("load more") instead of pagination.

## Common tasks

**Add a new DB column**: 1) Write a `migrateXxx()` in `api/db.php`, 2) Call it in `createDb()`, 3) Update `getCreateTableSQL()` for new installs.

**Add a new API action**: Add an `if ($action === 'xxx')` block in the appropriate HTTP method section of `api/index.php`.

**Add a UI feature**: Modify `index.php` — HTML structure (top of file), CSS styles (in `<style>`), and JS logic (in `<script>` at bottom). For icon-menu items, edit `createDirItemEl()`.
