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

## 4. Client, Project and Project Membership Tables

### 4.1 clients

Represents client records belonging to an organization.

| Column          | Data Type       | Constraints                 |
| --------------- | --------------- | --------------------------- |
| id              | BIGINT UNSIGNED | Primary Key, Auto Increment |
| organization_id | BIGINT UNSIGNED | Foreign Key                 |
| name            | VARCHAR(255)    | NOT NULL                    |
| contact_person  | VARCHAR(255)    | NULL                        |
| email           | VARCHAR(255)    | NULL                        |
| phone           | VARCHAR(30)     | NULL                        |
| created_at      | TIMESTAMP       | NULL                        |
| updated_at      | TIMESTAMP       | NULL                        |

Relationships:

- An organization can have multiple clients.
- A client belongs to one organization.
- A client can be associated with multiple projects.
- Clients do not have application login accounts in V1.

### 4.2 projects

Represents client or internal projects managed by an organization.

| Column          | Data Type       | Constraints                 |
| --------------- | --------------- | --------------------------- |
| id              | BIGINT UNSIGNED | Primary Key, Auto Increment |
| organization_id | BIGINT UNSIGNED | Foreign Key, NOT NULL       |
| client_id       | BIGINT UNSIGNED | Nullable Foreign Key        |
| name            | VARCHAR(255)    | NOT NULL                    |
| description     | TEXT            | NULL                        |
| type            | VARCHAR(30)     | NOT NULL                    |
| status          | VARCHAR(30)     | NOT NULL                    |
| start_date      | DATE            | NULL                        |
| due_date        | DATE            | NULL                        |
| created_at      | TIMESTAMP       | NULL                        |
| updated_at      | TIMESTAMP       | NULL                        |
| deleted_at      | TIMESTAMP       | NULL                        |

Allowed project types:

- client
- internal

Allowed project statuses:

- planning
- active
- on_hold
- completed
- cancelled

Relationships:

- An organization can have multiple projects.
- A project belongs to one organization.
- A project may optionally be associated with one client.
- A client can be associated with multiple projects.
- A project can have multiple members.

Every project must belong to an organization, including internal projects. Client association is optional.

### 4.3 project_members

Connects projects and users through a many-to-many relationship.

| Column     | Data Type       | Constraints                 |
| ---------- | --------------- | --------------------------- |
| id         | BIGINT UNSIGNED | Primary Key, Auto Increment |
| project_id | BIGINT UNSIGNED | Foreign Key                 |
| user_id    | BIGINT UNSIGNED | Foreign Key                 |
| created_at | TIMESTAMP       | NULL                        |
| updated_at | TIMESTAMP       | NULL                        |

Constraints:

- Foreign key: project_id references projects.id.
- Foreign key: user_id references users.id.
- Unique combination: project_id and user_id.

Relationships:

- A project can have multiple members.
- A user can participate in multiple projects.

Only active members of the project's organization can be assigned to that project.

## 5. Project and Client Data Isolation

Every project and client record belongs to an organization through organization_id.

The application must validate that an associated client belongs to the same organization as its project.

Project membership must also be validated against organization membership to prevent cross-organization assignments.

## 6. Collaboration Tables

### 6.1 task_comments

Stores comments posted by team members on tasks.

| Column     | Data Type       | Constraints                 |
| ---------- | --------------- | --------------------------- |
| id         | BIGINT UNSIGNED | Primary Key, Auto Increment |
| task_id    | BIGINT UNSIGNED | Foreign Key, NOT NULL       |
| user_id    | BIGINT UNSIGNED | Foreign Key, NOT NULL       |
| body       | TEXT            | NOT NULL                    |
| created_at | TIMESTAMP       | NULL                        |
| updated_at | TIMESTAMP       | NULL                        |
| deleted_at | TIMESTAMP       | NULL                        |

Relationships:

- A task can have multiple comments.
- A comment belongs to one task.
- A user can create multiple comments.
- A comment belongs to one user (author).

Business rules:

- Users can edit and soft-delete their own comments.
- Owners and Managers can moderate comments according to permissions.
- Permanently deleting a task cascades its comments.
- User deletion should be restricted while authored comments exist. User accounts should be deactivated instead of deleted.
- Organization ownership is derived through the task's project; no separate organization_id is required.

### 6.2 activity_logs

Stores activity history for important actions performed across the application.

| Column       | Data Type       | Constraints                 |
| ------------ | --------------- | --------------------------- |
| id           | BIGINT UNSIGNED | Primary Key, Auto Increment |
| user_id      | BIGINT UNSIGNED | Nullable Foreign Key        |
| subject_type | VARCHAR(100)    | NOT NULL                    |
| subject_id   | BIGINT UNSIGNED | NOT NULL                    |
| action       | VARCHAR(100)    | NOT NULL                    |
| properties   | JSON            | NULL                        |
| created_at   | TIMESTAMP       | NULL                        |

Column details:

- user_id identifies the user who performed the action. It can be NULL for system-generated activities.
- subject_type identifies the related entity type, such as Task, Project or Comment.
- subject_id identifies the specific record associated with the activity.
- action describes the performed operation, such as task.created or task.status_changed.
- properties stores additional activity-specific metadata, such as old and new status or priority.

Relationships:

- A user can generate multiple activity logs.
- An activity log can belong to a Task, Project or Comment through a polymorphic relationship.
- Tasks, Projects and Comments can each have multiple activity logs.

Business rules:

- Activity logs are append-only during normal application workflows.
- Activity records should be created within the same database transaction as the associated business operation.
- The polymorphic subject relationship does not use a conventional foreign key because subject_id can reference different entity tables.
- Organization ownership is derived through the related entity.

### 6.3 Collaboration Relationships

- Tasks and Task Comments: One-to-many.
- Users and Task Comments: One-to-many.
- Users and Activity Logs: One-to-many.
- Tasks and Activity Logs: One-to-many polymorphic relationship.
- Projects and Activity Logs: One-to-many polymorphic relationship.
- Comments and Activity Logs: One-to-many polymorphic relationship.

## 7. Task Management Tables

### 7.1 tasks

Represents individual work items within a project.

| Column          | Data Type       | Constraints                 |
| --------------- | --------------- | --------------------------- |
| id              | BIGINT UNSIGNED | Primary Key, Auto Increment |
| project_id      | BIGINT UNSIGNED | Foreign Key, NOT NULL       |
| created_by      | BIGINT UNSIGNED | Foreign Key, NOT NULL       |
| title           | VARCHAR(255)    | NOT NULL                    |
| description     | TEXT            | NULL                        |
| status          | VARCHAR(30)     | NOT NULL                    |
| priority        | VARCHAR(30)     | NOT NULL                    |
| estimated_hours | DECIMAL(8,2)    | NULL                        |
| start_date      | DATE            | NULL                        |
| due_date        | DATE            | NULL                        |
| completed_at    | TIMESTAMP       | NULL                        |
| created_at      | TIMESTAMP       | NULL                        |
| updated_at      | TIMESTAMP       | NULL                        |
| deleted_at      | TIMESTAMP       | NULL                        |

Allowed task statuses:

- todo
- in_progress
- in_review
- done

Allowed task priorities:

- low
- medium
- high
- critical

Relationships:

- A project can have multiple tasks.
- A task belongs to one project.
- A user can create multiple tasks.
- A task has one creator.
- A task can have multiple assignees through the task_assignees table.

Business rules:

- Tasks belong to a project. Organization ownership is derived through the project; no separate organization_id is required.
- The task creator must be an active member of the project's organization.
- Task assignees must be active members of the project's organization.
- Status transitions are flexible; tasks do not need to follow a fixed sequence.
- completed_at records when a task is marked Done. It is cleared if the task is reopened and updated again if completed later.
- estimated_hours must be positive when provided.
- Tasks support soft deletion.

### 7.2 task_assignees

Connects tasks and users through a many-to-many relationship and supports multiple assignees per task.

| Column      | Data Type       | Constraints                 |
| ----------- | --------------- | --------------------------- |
| id          | BIGINT UNSIGNED | Primary Key, Auto Increment |
| task_id     | BIGINT UNSIGNED | Foreign Key, NOT NULL       |
| user_id     | BIGINT UNSIGNED | Foreign Key, NOT NULL       |
| assigned_by | BIGINT UNSIGNED | Foreign Key, NOT NULL       |
| created_at  | TIMESTAMP       | NULL                        |
| updated_at  | TIMESTAMP       | NULL                        |

Constraints:

- Foreign key: task_id references tasks.id.
- Foreign key: user_id references users.id.
- Foreign key: assigned_by references users.id.
- Unique combination: task_id and user_id.
- Permanently deleting a task cascades its task assignments.
- User deletion is restricted while assignment references exist. User accounts should be deactivated instead.

Relationships:

- A task can have multiple assignees.
- A user can be assigned to multiple tasks.
- assigned_by identifies the user who created the assignment.
