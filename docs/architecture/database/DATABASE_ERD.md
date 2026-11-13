# WorkFlow Hub — Database ERD

## 1. Database Overview

WorkFlow Hub uses MySQL as its primary relational database.

The database follows a shared database, shared schema multi-tenancy approach.

Multiple organizations share the same database, while tenant-owned records are associated with their respective organization through `organization_id`.

The database design prioritizes:

- Data integrity.
- Organization-level data isolation.
- Clear entity relationships.
- Referential integrity through foreign keys.
- Efficient querying through appropriate indexes.
- Maintainable Laravel Eloquent relationships.

## 2. Identity and Organization Tables

### 2.1 users

Represents individual user accounts.

| Column            | Data Type       | Constraints                 |
| ----------------- | --------------- | --------------------------- |
| id                | BIGINT UNSIGNED | Primary Key, Auto Increment |
| name              | VARCHAR(255)    | NOT NULL                    |
| email             | VARCHAR(255)    | NOT NULL, UNIQUE            |
| email_verified_at | TIMESTAMP       | NULL                        |
| password          | VARCHAR(255)    | NOT NULL                    |
| remember_token    | VARCHAR(100)    | NULL                        |
| created_at        | TIMESTAMP       | NULL                        |
| updated_at        | TIMESTAMP       | NULL                        |

A user account can belong to multiple organizations.

### 2.2 organizations

Represents a workspace for a software development team.

| Column     | Data Type       | Constraints                 |
| ---------- | --------------- | --------------------------- |
| id         | BIGINT UNSIGNED | Primary Key, Auto Increment |
| name       | VARCHAR(255)    | NOT NULL                    |
| slug       | VARCHAR(255)    | NOT NULL, UNIQUE            |
| created_at | TIMESTAMP       | NULL                        |
| updated_at | TIMESTAMP       | NULL                        |

Organization ownership is represented through the owner role in the organization_members table.

### 2.3 organization_members

Connects users and organizations through a many-to-many relationship.

| Column          | Data Type       | Constraints                 |
| --------------- | --------------- | --------------------------- |
| id              | BIGINT UNSIGNED | Primary Key, Auto Increment |
| organization_id | BIGINT UNSIGNED | Foreign Key                 |
| user_id         | BIGINT UNSIGNED | Foreign Key                 |
| role            | VARCHAR(50)     | NOT NULL                    |
| status          | VARCHAR(30)     | NOT NULL                    |
| joined_at       | TIMESTAMP       | NULL                        |
| created_at      | TIMESTAMP       | NULL                        |
| updated_at      | TIMESTAMP       | NULL                        |

Allowed roles:

- owner
- manager
- member

Membership statuses:

- invited
- active
- suspended

Constraints:

- Foreign key: organization_id references organizations.id.
- Foreign key: user_id references users.id.
- Unique combination: organization_id and user_id.

This prevents duplicate memberships for the same user within an organization.

## 3. Relationships

- Users and Organizations: Many-to-many through organization_members.
- Organizations and Organization Members: One-to-many.
- Users and Organization Memberships: One-to-many.

A user can belong to multiple organizations and can have different roles in each organization.
