# Unified Café Business Management System

This folder merges your three existing applications — **HRMS**, **POS**,
and **Inventory / Procurement / Finance (Café IFMS)** — into one system
with one login, one database, and role-based navigation across every
module. It reuses the original code, UI, and features as much as
possible; the integration work is concentrated in the config/, includes/,
and database/ layers described below.

## 1. Setup (XAMPP / local)

1. Create an empty database called `unified_cafe_system` in phpMyAdmin.
2. Import `database/unified_schema.sql` into it (this is the ONLY schema
   file you need — do not import the three original .sql files).
3. Copy this whole `unified/` folder into `htdocs/` (e.g.
   `htdocs/unified/`).
4. Confirm `config/constants.php` → `BASE_URL` matches your folder name
   (`/unified/` by default).
5. Visit `http://localhost/unified/` and log in with one of the seeded
   demo accounts (all passwords are `Password123!`):

   | Role             | Email                    |
   |------------------|--------------------------|
   | Owner            | owner@cafe.test          |
   | HR Manager       | hrmanager@cafe.test      |
   | HR Staff         | hrstaff@cafe.test        |
   | Employee Manager | empmanager@cafe.test     |
   | Employee         | employee1@cafe.test      |
   | Cashier          | cashier1@cafe.test       |
   | Inventory Staff  | invstaff1@cafe.test      |

   **Change these before going live.**

## 2. What changed, and why

### One database
`database/unified_schema.sql` merges all ~40 tables from the three
original databases into a single schema. The main changes from the
originals:

- **`users` + `roles`** — one identity table for the whole system,
  replacing the three separate `users` tables (HRMS, Café IFMS, POS).
  8 business roles plus a 9th, `CUSTOMER`, reserved for the POS
  self-order kiosk (see below).
- **`categories` → `menu_categories`** (POS only) — renamed to avoid
  colliding with the Inventory module's own `categories` table (they
  serve different purposes: menu categories vs. ingredient categories).
- **New tables**:
  - `product_ingredients` — recipe/BOM mapping. Each POS product can
    list the Inventory ingredients (and quantities) it consumes. This
    is what powers the POS → Inventory stock deduction.
  - `purchase_requests` — Inventory → Procurement handoff (see below).
  - `module_permissions` — mirrors the `MODULE_ACCESS` matrix in
    `config/constants.php`, driving role-based navigation and guards.
- All original tables' primary keys, `AUTO_INCREMENT`, and foreign key
  constraints were carried over from the original phpMyAdmin exports.

### One login
- `index.php` is the single login page for every staff role (this was
  the original HRMS landing/login page, now checking against the
  unified `users` table). `dashboard.php` reads the logged-in user's
  role and redirects to the right home page — HRMS module dashboards,
  the Café IFMS dashboard, or `pos/dashboard.php`.
- `includes/auth.php` is the single session/RBAC source. It keeps every
  original HRMS function name (`isLoggedIn()`, `requireRole()`,
  `loginUser()`, etc.) so ~40 HRMS pages needed **zero code changes**.
  It also defines POS-compatible shims (`require_login()`,
  `is_admin()`, etc.) so the original POS pages didn't need changes
  either — they just needed to `require` this file instead of managing
  their own bare session.
- `includes/auth_check.php` is a new module-level guard used by the
  Inventory/Procurement/Finance/Reports pages. The original Café IFMS
  app let any logged-in user see every page; this version checks the
  requesting page's folder name against `MODULE_ACCESS` in
  `config/constants.php`, so e.g. a Cashier can't open Finance pages.
- **POS self-order kiosk**: kiosk customer accounts use their own
  session (`POS_KIOSK` cookie name, set in `pos/kiosk_bootstrap.php`)
  and a dedicated `CUSTOMER` role with zero module permissions, so a
  customer account can never reach the staff dashboard, and a cashier's
  POS session never collides with a kiosk session on the same device.

### One dashboard shell, role-based navigation
- `includes/header.php` / `sidebar.php` / `footer.php` are now shared by
  every module. The sidebar shows a role-based set of links spanning
  **all** modules a role can use — e.g. the Owner's sidebar has HR,
  POS, Inventory, Procurement, Finance, and Reports links in one place,
  not three separate menus in three separate apps.
- Original CSS/JS/images from HRMS and Café IFMS were namespaced under
  `assets/hrms/` and `assets/inv/` (both had a same-named `style.css`)
  so neither overwrites the other; existing page markup and classes are
  unaffected.
- POS pages render their own self-contained page chrome (see
  `pos/app.php` → `page_header()`/`page_footer()`) and were left as-is
  visually; only their session/DB plumbing changed.

## 3. The 5 integration flows

| Flow | Where it lives | What happens |
|---|---|---|
| **POS → Inventory** | `pos/sales_transaction.php` calls `applyPosSaleToInventoryAndFinance()` (`includes/functions.php`) | On checkout, for each product sold, its `product_ingredients` recipe rows are looked up and the matching quantity is deducted from `items.current_stock`, with a `stock_movements` row logged for traceability. |
| **POS → Finance** | Same function, same transaction | The sale total is posted as an `Income` row in `finance_transactions` (category: Sales). |
| **Inventory → Procurement** | `modules/procurement/purchase_requests.php` (new page) | Items at/under `reorder_level` show up as low-stock alerts; Inventory Staff/Owner can raise a Purchase Request with one click, or submit one manually. |
| **Procurement approval → conversion** | Same page | Owner approves/rejects requests; an approved request can be converted straight into a real Purchase Order (`purchase_orders`/`purchase_order_items`), picking a supplier and unit price. |
| **Procurement → Inventory** | `modules/procurement/receive.php` (already existed in Café IFMS) | Recording a delivery/GRN against a PO increases `items.current_stock` and logs `stock_movements` — unchanged, already worked this way. |
| **HRMS → Finance** | `modules/owner/payroll.php` (new page — the Owner dashboard already linked here, but the page didn't exist yet) | HR Manager prepares a draft payslip; Owner **Approves**, then **Releases** it. Releasing posts the net pay as an `Expense` row in `finance_transactions` (category: Salaries) in the same DB transaction that marks the payroll `PAID`. |
| **All modules → Reports** | `modules/reports/index.php` | Reads directly from the shared schema, so it can be extended to pull HRMS, POS, Inventory, and Finance data side by side. |

## 4. Recipes: linking POS products to Inventory ingredients

`product_ingredients` starts empty — you decide which ingredients each
menu item consumes. Example, for a product `Iced Latte` (id 5) that
uses 1 ingredient `Milk` (item_id 12) at 0.3 units per cup:

```sql
INSERT INTO product_ingredients (product_id, item_id, qty_per_unit)
VALUES (5, 12, 0.3);
```

A product with no rows in this table simply won't touch Inventory when
sold (useful for e.g. merchandise that isn't made from tracked
ingredients). A small admin page for managing these mappings is a
natural next addition (see "Known follow-ups" below).

## 5. Role → module access matrix

Defined in `config/constants.php` (`MODULE_ACCESS`) and mirrored in the
`module_permissions` table:

| Role | Modules |
|---|---|
| Owner | Dashboard, HRMS, POS, Inventory, Procurement, Finance, Reports, Profile |
| HR Manager | Dashboard, HRMS, Reports, Profile |
| HR Staff | Dashboard, HRMS, Profile |
| Employee Manager | Dashboard, HRMS, Profile |
| Employee | Dashboard, HRMS, Profile |
| Applicant | Profile (job portal only) |
| Cashier | Dashboard, POS, Profile |
| Inventory Staff | Dashboard, Inventory, Procurement, Reports, Profile |
| Customer (kiosk only) | none — self-order kiosk uses its own session, never the staff dashboard |

## 6. Known follow-ups (not done in this pass)

This is a large merge of three independent codebases (150+ PHP files).
What's in place is a working foundation with all 5 requested data flows
wired end-to-end, but a few things are worth doing before a production
rollout:

- **Test against a real MySQL instance.** Everything here was built and
  reviewed by reading code carefully (no live PHP/MySQL environment was
  available while building this), so run through each role's flows
  once end-to-end after import, especially the checkout → inventory →
  finance chain and the payroll release chain.
- **Product-ingredient mapping UI.** Right now `product_ingredients` is
  managed by direct SQL; a simple admin page under Inventory or POS
  product management would let staff maintain recipes without SQL.
- **Owner's main dashboard** currently routes to the original HRMS
  owner dashboard (HR-focused). A true cross-module landing page
  (today's sales + low stock count + pending payroll + HR alerts in one
  view) would make "one main dashboard" fully literal for the Owner
  role — the pieces (shared DB, shared session) are all in place for
  this, it's just a new page to build.
- **`setup.php`** (the original HRMS first-run installer) was
  intentionally **not** carried over, since `unified_schema.sql`
  already seeds roles/demo users — running it against the unified DB
  could create conflicting data.
- **Migrating real (non-demo) existing user accounts**, if any, from
  the three original databases into the unified `users` table needs a
  one-time mapping script (matching emails, re-pointing every table's
  old `user_id`/`created_by`/etc. to the new unified ids) — not
  attempted here since no real production data was provided.
