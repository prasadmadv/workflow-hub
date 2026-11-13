# WorkFlow Hub — System Architecture

## 1. Architecture Overview

WorkFlow Hub follows a **Modular Monolith Architecture**.

The application will consist of:

- A single Laravel backend application.
- A single primary MySQL database.
- A React frontend application.
- A single backend deployment unit.

Internally, the application will be organized into separate business modules such as Organizations, Projects, Tasks, Members, Clients, and Collaboration.

Each module will have clearly defined responsibilities while sharing the same application infrastructure.

**Why Modular Monolith?**

We are building an MVP for small software development teams. A modular monolith keeps development, deployment, and maintenance simple while maintaining a structured and maintainable codebase.

We do not need the operational complexity of microservices for our current requirements.

## 2. Technology Stack

| Layer            | Technology       | Responsibility                          |
| ---------------- | ---------------- | --------------------------------------- |
| Frontend         | React            | User interface                          |
| Language         | TypeScript       | Type safety                             |
| Build Tool       | Vite             | Frontend development and build          |
| Frontend Routing | React Router     | Client-side navigation                  |
| Backend          | Laravel 13       | Business logic and REST APIs            |
| Authentication   | Laravel Sanctum  | SPA authentication                      |
| Authorization    | Laravel Policies | Access control                          |
| Database         | MySQL            | Persistent data storage                 |
| ORM              | Laravel Eloquent | Database interaction                    |
| Cache / Queue    | Redis            | Caching and background jobs when needed |
| Testing          | PHPUnit          | Automated testing                       |
| Version Control  | Git / GitHub     | Source code management                  |

## 3. Domain Modules and Responsibilities

WorkFlow Hub consists of six primary business modules.

### 3.1 Organizations

An organization represents a workspace for a software development team.

Responsibilities:

- Create and manage organizations.
- Manage organization members.
- Maintain organization-level settings.
- Provide the tenant boundary for data isolation.
- Support multiple projects within an organization.

### 3.2 Projects

Projects represent the actual work being managed by an organization.

A project can be either:

- Client Project
- Internal Project

Responsibilities:

- Create, update, archive, and manage projects.
- Associate projects with organizations.
- Assign team members to projects.
- Associate clients with client projects.
- Track project status and timelines.

### 3.3 Tasks

Tasks represent individual units of work within a project.

Responsibilities:

- Create and manage tasks.
- Associate tasks with projects.
- Assign tasks to team members.
- Maintain task status, priority, and deadlines.
- Support task filtering and search.

Task statuses:

- Todo
- In Progress
- In Review
- Done

Task priorities:

- Low
- Medium
- High
- Critical

### 3.4 Team Members

Team members are users who belong to an organization and participate in its projects.

Responsibilities:

- Manage organization membership.
- Assign organization roles.
- Associate members with projects.
- Assign tasks to members.
- Enforce role-based access permissions.

V1 roles:

- Organization Owner
- Project Manager
- Team Member

### 3.5 Clients

Clients are informational records associated with client projects.

Responsibilities:

- Store client name and contact details.
- Associate clients with relevant projects.
- Allow organization members to manage client information.

Clients will not have login access or an external client portal in V1.

### 3.6 Collaboration

Collaboration enables team members to communicate and track project activity.

Responsibilities:

- Task comments.
- Activity history.
- Basic notifications.

## 4. Authentication and Authorization

Authentication and authorization are handled as separate responsibilities.

### Authentication

Authentication establishes the identity of a user.

Laravel Sanctum will handle authentication for the first-party React SPA using cookie-based authentication.

Only authenticated users can access protected API endpoints.

### Authorization

Authorization determines what an authenticated user is allowed to access or perform.

We will use:

- Laravel Policies.
- Role-based permissions.
- Organization membership validation.
- Project and resource-level access checks.

For example, a team member belonging to Organization A must not be able to access projects belonging to Organization B without valid authorization.

Authentication alone does not grant access to every resource.

## 5. Multi-Tenancy Strategy

WorkFlow Hub will use a **Shared Database, Shared Schema** multi-tenancy approach.

All organizations will share the same MySQL database and database schema.

Tenant-owned records will contain an `organization_id` column to associate them with the correct organization.

For example, two different organizations can have projects with the same name without conflicts because each project belongs to a different organization.

### Tenant Data Isolation

The application must ensure that users can access only the data belonging to their authorized organization.

We will enforce isolation through:

- Organization membership validation.
- Organization-scoped Eloquent queries.
- Laravel Policies and authorization checks.
- Database foreign keys and appropriate indexes.
- Automated tenant-isolation tests.

The application must never trust an organization ID supplied by the frontend without validating the user's membership and permissions.

Changing a project or task ID in an API request must not allow access to another organization's data.

## 6. Database and Infrastructure

MySQL will be the primary relational database for WorkFlow Hub.

Laravel Eloquent ORM will provide the application-level interface for database interactions.

### Database Interaction

- **MySQL:** Stores application data persistently.
- **Eloquent ORM:** Interacts with database records through Laravel models.
- **Migrations:** Define and version-control database schema changes.
- **Relationships:** Define associations between models.
- **Query Builder:** Provides a fluent interface for constructing database queries.
- **Raw SQL:** Available for complex queries or performance-sensitive operations when necessary.

We will use appropriate database constraints and indexes to maintain data integrity and query performance.

Redis may be introduced for caching and queue processing when required.

## 7. API Conventions

WorkFlow Hub will expose a versioned REST API.

Current API version:

`/api/v1`

API conventions:

- Follow REST resource-based endpoint design.
- Use appropriate HTTP methods and status codes.
- Use Laravel Form Requests for request validation.
- Use Laravel API Resources for consistent response formatting.
- Apply authentication and authorization to protected endpoints.
- Support pagination, filtering, and search where appropriate.
- Maintain consistent error responses.

Primary API resources will include:

- Authentication
- Organizations
- Members
- Clients
- Projects
- Tasks
- Comments
- Dashboard
- Notifications

## 8. Testing Strategy

Automated testing will help maintain application reliability, security, and correctness.

PHPUnit will be the primary testing framework.

Testing will include:

### Unit Tests

Test individual classes and business logic independently.

### Feature / API Tests

Test API endpoints, request validation, database interactions, and application workflows.

### Authorization Tests

Verify that users can perform only the actions allowed by their roles and permissions.

### Tenant Isolation Tests

Verify that users from one organization cannot access another organization's data.

Each major business module will have relevant automated tests.

## 9. Security Considerations

Security will be implemented at the backend level.

Key considerations:

- Secure authentication using Laravel Sanctum.
- Role-based authorization using Laravel Policies.
- Strict organization-level data isolation.
- Server-side request validation.
- Protection against unauthorized resource access.
- Secure handling of environment variables and credentials.
- Appropriate database constraints.
- Avoid exposing sensitive information in API responses.

Frontend restrictions alone must never be considered sufficient security.

## 10. Deployment Architecture

Source code will be maintained in the GitHub repository.

GitHub will serve as the central version-control platform and may be used for CI/CD automation through GitHub Actions.

The application will eventually be deployed to a suitable hosting provider with a configured domain and production database.

Deployment approach:

- Local development environment.
- GitHub source-code repository.
- Automated testing and CI/CD pipeline.
- Production hosting environment.
- Production MySQL database.

Docker may be introduced when appropriate to improve environment consistency and deployment reliability.

For the initial MVP, deployment will remain simple and aligned with our modular monolith architecture.

---

## Architecture Principles

The following principles will guide implementation:

1. Keep the architecture simple and maintainable.
2. Maintain clear separation of business modules.
3. Enforce organization-level data isolation.
4. Apply authorization at the backend level.
5. Follow consistent REST API conventions.
6. Write automated tests for critical business logic and security.
7. Avoid unnecessary abstractions and infrastructure complexity.
8. Prioritize a reliable, working MVP over premature optimization.
