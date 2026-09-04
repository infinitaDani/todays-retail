# Today's Retail — Architecture

## 1. Project Overview

Today's Retail is a multi-tenant SaaS ERP for retail businesses.

The application is being developed with Laravel 11 and MariaDB.

The application will be commercially available to multiple independent businesses.

Each business is an independent tenant with its own database.

---

## 2. Multi-Tenant Architecture

Today's Retail uses a database-per-tenant architecture.

There are two levels of data:

### Core Database

The Core Database stores global SaaS information.

Current database:

podiqdte_todays_core

Laravel must access this database through the explicit `core` connection. All
Core models use that connection directly and must never depend on Laravel's
default connection, which may later be used or replaced by a tenant context.

Core responsibilities:

- User authentication
- Accounts
- Account/user relationships
- Roles associated with account membership
- Tenant database identification

Internal Core administration is restricted separately from customer access.
Until an internal permissions system is introduced, the `core.admin` middleware
allows only emails listed in `CORE_ADMIN_EMAILS`. This is not an account role
and does not change the `AccountUser` role model.

An allowlisted internal administrator may access Core Admin without an active
account membership. All other users must retain the normal account-selection
flow and cannot access Core Admin.

Core tables:

- accounts
- users
- roles
- account_user

Laravel infrastructure tables may also exist in the Core Database:

- migrations
- cache
- cache_locks
- jobs
- job_batches
- failed_jobs
- sessions

---

## 3. Accounts / Tenants

An account represents one independent customer/business.

IMPORTANT:

An account is NOT a parent of another account.

For example:

- LOVE = one account
- Touché = one account
- Empresa XYZ = one account
- Tienda 1 = one account
- Tienda 2 = one account

Accounts are completely independent tenants.

A single user may belong to multiple accounts.

Example:

danielle@insightful.lat

may belong to:

- LOVE
- Tienda 2

The user selects which account to enter after authentication.

---

## 4. RUC / Identification

Each account is identified by a unique Ecuadorian RUC or cédula.

The accounts.ruc field must be unique.

No two accounts may have the same RUC/cédula.

Current accounts structure:

- id
- name
- ruc
- database_name
- status
- created_at
- updated_at

---

## 5. Users

Users are global and live in the Core Database.

A user does NOT belong directly to one account.

The relationship between users and accounts is many-to-many through:

account_user

The same email must not create multiple global users.

users.email is unique.

Current users structure:

- id
- name
- email
- password
- status
- created_at
- updated_at

Passwords must always be securely hashed using Laravel's authentication/password hashing system.

Never store plaintext passwords.

---

## 6. Account Membership and Roles

The account_user table represents a user's membership in an account.

Current structure:

- id
- account_id
- user_id
- role_id
- created_at
- updated_at

The combination account_id + user_id must be unique.

A user can therefore belong to multiple accounts.

A user's role may differ between accounts.

Example:

Danielle
- LOVE → admin
- Tienda 2 → manager

Roles are stored in the Core Database.

Current roles structure:

- id
- name
- code
- created_at
- updated_at

roles.code is unique.

---

## 7. Authentication Flow

Expected authentication flow:

User enters email/password.

The application authenticates the global user against the Core Database.

The application finds the user's active accounts.

If the user belongs to only one active account:

- Enter the account automatically.

If the user belongs to multiple active accounts:

- Show an account selector.
- User selects an account.
- Resolve the tenant.
- Connect to the tenant database.
- Load the dashboard.

Inside the dashboard, the user should be able to switch between accounts they are authorized to access.

---

## 8. Tenant Database

Each account has its own independent database.

The Core Database stores the database identifier in:

accounts.database_name

Example:

Account: LOVE
RUC: 0999999999001
Database: tenant_0999999999001

Another account:

Account: Tienda 2
RUC: 0998888888001
Database: tenant_0998888888001

Tenant data must never be mixed between accounts.

Once a tenant is resolved, application queries for tenant-specific functionality must use the active tenant database connection.

### Tenant connection

Laravel uses a dynamic connection named `tenant`. Its shared host, port,
username, and password come only from `TENANT_DB_*` environment variables.
No tenant credential is stored in Core or tenant tables.

The database name is never fixed in `.env`: it is read from
`accounts.database_name` for the validated active account. Before every tenant
connection is configured, the application validates the authenticated user's
membership through `account_user` and confirms that the account is active.

When the active account changes, the previous `tenant` connection is purged
before Laravel reconnects with the new `database_name`. This prevents reuse of
the prior tenant PDO connection.

---

## 9. Tenancy Layer

Tenant resolution is infrastructure and belongs outside the business modules.

Location:

app/Tenancy/

Responsibilities include:

- Resolving the active account
- Resolving the tenant database
- Establishing the tenant database connection
- Making the active tenant available to the application
- Preventing access to unauthorized tenants
- Switching tenant context safely

Business modules must not implement their own tenant resolution logic.

Current infrastructure:

- `TenantResolver` validates `active_account_id`, authenticated user,
  `account_user` membership, and active account status.
- `TenantConnectionManager` writes the account database name into the dynamic
  `tenant` connection, purges the old connection, and reconnects it.
- `InitializeTenant` middleware initializes the validated context for
  tenant-aware web routes. Core Admin does not use this middleware.

Tenant models must explicitly declare `protected $connection = 'tenant';`.
Core models must continue to declare `protected $connection = 'core';`.
Do not create Eloquent relations that require joins between Core and tenant
tables.

### Migrations

- `database/migrations/` contains only Core migrations.
- `database/migrations/tenant/` contains migrations that run separately inside
  each tenant database.

Tenant migrations are never run for every account automatically. To migrate one
active tenant explicitly, run:

```bash
php artisan tenant:migrate ACCOUNT_ID --force
```

The command loads the selected active Core account, configures `tenant` from
its `database_name`, and runs only `database/migrations/tenant/`. It does not
run Core migrations, create databases, or provision MySQL users.

---

## 10. Application Structure

Current application architecture:

app/
├── Core/
│   ├── Accounts/
│   ├── Users/
│   └── Billing/
│
├── Tenancy/
│
├── Modules/
│   ├── Operations/
│   ├── Tasks/
│   ├── Knowledge/
│   ├── Merchandising/
│   └── Reports/
│
├── Models/
├── Providers/
└── Http/
    └── Controllers/

Core contains global SaaS functionality.

Tenancy contains tenant-resolution infrastructure.

Modules contain tenant/business functionality.

---

## 11. Operations Module

Location:

app/Modules/Operations/

This module contains the retail operational structure.

It replaces the following functionality previously implemented in WordPress:

- Sucursales
- Turnos
- Asignaciones
- Horarios mensuales

Expected tenant entities include:

- Branches
- Shifts
- Assignments
- Employee schedules

All Operations data belongs to the active tenant.

The tenant Operations tables are `branches`, `shifts`, and `assignments`.
An assignment stores one global `core_user_id`, branch, shift, and real date.
Schedule is a monthly calendar view derived from `assignments.date`; it is not
a persistent month-only schedule entity. `core_user_id` is validated through
Core `account_user` before it is stored and has no cross-database foreign key.

### Operational staff profiles and branch scope

Each tenant database has a `staff_profiles` table. It contains one profile per
global Core user in that tenant (`core_user_id` is unique) and an optional
`branch_id`. The Core user and the Account membership are always validated in
Core first; the tenant table does not create a cross-database foreign key.

The only operational role codes assignable from the tenant Team interface are:

- `management`: `staff_profiles.branch_id` may be null and the user has scope
  over all branches of the active account.
- `store_admin`: `staff_profiles.branch_id` is required and server-side scope
  is limited to that branch.
- `advisor`: `staff_profiles.branch_id` is required. Advisors do not access
  tenant administrative interfaces.

Roles remain Core records and continue to belong to `account_user`. Tenant Team
may update only the active account membership, only to one of these three
codes; it cannot create, edit, delete, or assign any other Core role. A future
need for specific multi-branch access will be handled through a new tenant
migration, not by changing these rules silently.

### Account Administrator

The existing Core role code `admin` on an `account_user` membership identifies
an Account Administrator for that specific account. It is not a fourth tenant
operational role and must not be assigned from Tenant Team. An Account
Administrator has full administrative access only while that account is the
validated active account, but is not automatically `management` and does not
receive a `staff_profile`, branch, shift, assignment, or personal operational
work. This permission is resolved centrally from Core `account_user` data.

---

## 12. Tasks Module

Location:

app/Modules/Tasks/

This module replaces the existing WordPress task functionality.

It includes:

- Tasks
- Checklists
- Checklist items
- Task statuses
- Task assignment
- Task completion
- Task performance tracking

The existing WordPress functionality included:

- Tareas
- Checklists
- Estados
- Task completion UI
- Performance dashboard

The Laravel implementation should preserve the business functionality while improving the underlying architecture.

Tasks and checklists are tenant definitions. `checklist_items` orders tasks and
stores start and due times. Executions are stored separately in
`checklist_executions` and `task_executions`, so future reporting can evaluate
real daily work without changing templates. Task state is calculated from
completion time and the item due time (`in_progress`, `due_soon`, `overdue`,
`completed_on_time`, or `completed_late`); it is not an editable status table.

---

## 13. Knowledge Module

Location:

app/Modules/Knowledge/

This replaces the previously planned Protocols module.

The product name is:

Knowledge Center

It is an internal knowledge and operational information center, not merely an FAQ.

It may contain:

- Procedures
- Protocols
- Manuals
- Internal communications
- Training material
- Guides
- Videos
- Policies
- FAQs
- Operational documentation

Knowledge Center must support tracking of user interaction.

Expected entities include:

- Knowledge articles
- Categories
- Assignments
- Tracking
- Versions

The system should be able to determine which users have viewed, read, or confirmed required content.

Knowledge Center uses `knowledge_articles`, `knowledge_assignments`, and
`knowledge_trackings` in the tenant database. Assignments store a validated
global `core_user_id` without a cross-database foreign key. Tracking is created
and updated by article interaction (opened, completed, confirmed), not through
a manual tracking CRUD.

### Knowledge categories, versions and readings

Categories are tenant-owned records in `knowledge_categories`; an article may
have many categories through `knowledge_article_category`. Published content is
immutable: edits to a published article create or update a draft in
`knowledge_article_versions`, while the previous published version is archived
and retained when the draft is published. The legacy article fields remain as a
compatibility snapshot of its current publication.

`knowledge_version_readings` tracks a global `core_user_id` against one
published version, including first/last open, indicative active seconds and an
idempotent reading confirmation. A new published version deliberately has no
reading rows, so it is pending again. Audience is stored on each version using
the operational role codes `all`, `management`, `store_admin` and `advisor`.
Account Administrators and Management have backoffice access; Store Admin and
Advisor access only the published, audience-filtered collaborator experience.
Publishing dispatches `KnowledgeArticlePublished`, reserved for future in-app,
browser or email notifications; it does not send notifications itself.

Historical `knowledge_assignments` and `knowledge_trackings` remain immutable
legacy records. Official per-version reading tracking begins with Knowledge 2.0;
the incremental migration intentionally does not infer or fabricate readings,
confirmations, or active time from the legacy schema. Legacy free-text article
categories are normalized into tenant categories and the article/category pivot
without modifying the original `knowledge_articles.category` value.

---

## 14. Products Module

Products are tenant-owned and use flexible variants: `products` is the parent
catalog record, while each SKU lives in `product_variants`. Attributes such as
Size and Color are tenant-configured `product_attributes`; variants receive one
or more values through `product_variant_attribute_value`. They are not rigid
product columns, so a future Contífico importer can apply tenant-specific SKU
inference rules without changing the catalog schema.

Categories support only a main category and one child category. Collections and
collection lines are optional commercial structures controlled by the tenant's
`product_settings`. All product classification data may remain null to support
incomplete external source data and later bulk enrichment.

Tasks may reference zero or more Knowledge articles through the tenant pivot
`knowledge_article_task`. The association targets the article, never a frozen
version: collaborators always open the currently published, audience-authorized
version through Knowledge Center and therefore use the same version tracking.

## 15. Merchandising Module

Location:

app/Modules/Merchandising/

Visual Merchandising is tenant-owned. A `MerchandisingFixtureType` describes a
reusable structure or accessory, and stores only a logical `icon_path` reference.
The 24 platform defaults use shared public assets; tenant-created fixture types
remain valid without an icon and use a visual fallback.

Each Branch may have multiple `MerchandisingFloorPlan` records for its floors or
zones. A plan contains positioned `MerchandisingFloorPlanItem` records. An item
references a fixture type and keeps its own canvas geometry. Accessories may
optionally reference a root structure in the same plan through `parent_item_id`;
the relationship is logical and does not replace the accessory geometry.

The fixture-type defaults are synchronized idempotently:
missing defaults are created and missing platform icon references are filled,
without deleting tenant customizations.

Product, product-line and assortment placements are intentionally outside the
current phase. They must be added later through tenant-owned incremental schema,
without coupling fixture behavior to a default name, code or filename.

---

## 15. Reports Module

Location:

app/Modules/Reports/

This module contains performance and reporting functionality.

Initial reporting requirements include:

- Task completion
- On-time compliance
- Late tasks
- Employee performance
- Team performance
- Knowledge Center completion
- Operational metrics

The existing WordPress performance dashboard is the functional reference for the initial implementation.

---

## 16. Billing

Location:

app/Core/Billing/

Billing is reserved for future SaaS commercialization.

It is not part of the initial implementation.

---

## 17. Database Strategy

Laravel migrations are the source of truth for database structure.

Do not manually modify production database structure without a corresponding Laravel migration.

Future database changes must normally be implemented as new migrations.

Do not repeatedly delete or recreate existing migrations to introduce normal application changes.

migrate:fresh is only appropriate during early development when the database contains no important data.

---

## 18. Development Rules

Before implementing a new feature:

1. Determine whether the data belongs to Core or Tenant.
2. Determine which module owns the functionality.
3. Define the database structure.
4. Create or update Laravel migrations.
5. Create Models.
6. Define relationships.
7. Implement Services or Actions when appropriate.
8. Implement Controllers.
9. Implement Views or API endpoints.
10. Test tenant isolation.

Never put tenant-specific data into the Core Database.

Never allow a user to access a tenant that is not present in account_user.

---

## 19. Tenant Isolation Rule

This is a critical security rule.

A user authenticated in one account must never be able to access another tenant's data unless that account is explicitly associated with the user through account_user.

Tenant context must be resolved before executing tenant-specific queries.

Never trust an account ID supplied directly by the client without validating that the authenticated user has access to that account.

---

## 20. Current Development Status

### Completed

- Laravel 11 installed
- Git repository configured
- GitHub repository configured
- Application architecture folders created
- Core database created
- Core database connected to Laravel
- Core migrations created
- Core tables created and verified

### Core tables currently implemented

- accounts
- users
- roles
- account_user

### Next development phase

1. Create Core Models
2. Configure Laravel authentication
3. Create initial Core seeders
4. Create account selection flow
5. Implement TenantResolver
6. Implement tenant database connection
7. Create tenant database migration structure
8. Implement Operations module
9. Implement Tasks module
10. Implement Knowledge module
11. Implement Merchandising module
12. Implement Reports module

---

## 21. Important Architectural Principle

Today's Retail is not a single-company application.

It is a multi-tenant SaaS platform.

Every architectural decision should preserve:

- Tenant isolation
- Scalability
- Maintainability
- Security
- Clear separation between Core and Tenant data
- Ability to onboard additional accounts without modifying application code

---

## 22. Inventory Administration and Contífico Foundation

Inventory operational data belongs exclusively to each Tenant Database.

The authoritative inventory hierarchy remains:

```text
Branch
  -> Warehouse
    -> InventoryStock
      -> ProductVariant (SKU)
```

`inventory_stocks` remains the local source of stock quantities by warehouse.
The Inventory administration module does not introduce a second stock model.

Warehouse administration is restricted to the Account Administrator. Management,
Store Admin and Advisor may read warehouses according to their tenant operational
scope. Store Admin and Advisor are always restricted to the branch identified by
their `StaffProfile`.

Contífico configuration is tenant-specific. Its API Key is stored encrypted and is
never rendered or logged in full. The application stores only a generic mapping
code in `warehouses.contifico_code`; future product queries will use that value in
the `pos` query parameter.

Commercial entitlements belong to Core because they are part of the Account plan:

- `accounts.contifico_enabled`
- `accounts.manual_bulk_syncs_per_day`
- `accounts.manual_bulk_min_interval_minutes`

Tenant and per-user limits may only reduce the Core plan allowance. They can never
increase it.

`inventory_sync_executions` is the tenant audit structure reserved for future
manual, automatic and per-product synchronizations. This phase only supports a
safe connection test and does not execute or schedule stock synchronization.
