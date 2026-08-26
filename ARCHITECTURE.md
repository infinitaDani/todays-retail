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

---

## 14. Merchandising Module

Location:

app/Modules/Merchandising/

This is a new module planned for Today's Retail.

It will manage store merchandising/perchaje.

The system should allow management of:

- Branch
- Shelf/panel
- Shelf lines
- Products
- Product positioning

Expected entities include:

- Shelf panels
- Shelf lines
- Products
- Product placement

The objective is to make the store merchandising process easier for employees.

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
