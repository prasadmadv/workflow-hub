# WorkFlow Hub

## Project identity and technology stack

- This repository is WorkFlow Hub, a Laravel application with a versioned REST API.
- The verified stack is Laravel 13, PHP compatible with `^8.3` (including PHP 8.5), Eloquent, and MySQL for application database use.
- PHPUnit is the repository’s test framework. Feature tests use Laravel’s HTTP testing helpers.
- Do not add dependencies, frameworks, or testing tools unless explicitly requested.

## Architecture and coding conventions

- Follow the existing Laravel structure:
    - `routes/` defines web and API routes.
    - `app/Http/Controllers/` handles HTTP requests.
    - `app/Http/Requests/` contains request validation.
    - `app/Http/Resources/` shapes API resource data.
    - `app/Models/` defines Eloquent models and relationships.
    - `database/migrations/`, `database/factories/`, and `database/seeders/` contain database schema and setup code.
    - `tests/Feature/` and `tests/Unit/` contain feature and unit tests.
- API routes are versioned under `/api/v1` and protected routes use Sanctum authentication where configured.
- Follow the nearest relevant file for naming, namespaces, formatting, imports, and response conventions. Test namespaces are not uniform across all nested directories.
- Preserve existing architecture and behavior unless a refactor or behavior change is explicitly requested.
- Use the existing Form Request, Resource, model, and controller patterns. Avoid unnecessary abstractions and unrelated changes.
- Successful API responses commonly include `message` and `data`. Follow the endpoint’s existing response shape; do not assume a universal `{success, data, error}` envelope.
- Validation failures are tested with Laravel’s validation assertions. Match each endpoint’s actual error behavior instead of imposing a new format.

## Laravel and PHP compatibility

- Keep code compatible with the project’s Laravel 13 dependency and PHP `^8.3`, including PHP 8.5.
- Check `composer.json`, `composer.lock`, and nearby code before using framework or language features.
- Do not change dependency versions or introduce packages as part of ordinary implementation or test work.

## PHPUnit feature-testing conventions

- PHPUnit is configured in `phpunit.xml` with `Unit` and `Feature` test suites.
- Follow the existing `*Test` class naming and `test_*` method naming patterns.
- Put tests near the relevant feature under `tests/Feature/Api/V1/` or under the appropriate unit-test directory.
- Match the namespace used by the nearest related tests.
- Use Laravel JSON request helpers such as `getJson`, `postJson`, and `deleteJson` for API behavior.
- Assert relevant HTTP status, response fields, and database effects. Use exact paths or values when they express the behavior under test.
- Use `assertDatabaseHas`, `assertDatabaseMissing`, and `assertDatabaseCount` when persistence or non-persistence is part of the expected behavior.
- Keep each test focused on a meaningful behavior. Avoid duplicating coverage without a distinct reason.

## Factories and database testing

- Use existing model factories for test data, overriding attributes explicitly when the scenario depends on them.
- Database-backed tests commonly use Laravel’s `RefreshDatabase` trait.
- `phpunit.xml` sets `APP_ENV=testing`, `DB_CONNECTION=sqlite`, and `DB_DATABASE=:memory:`. It also configures in-memory or test-specific cache, mail, queue, and session drivers.
- Keep tests on the configured test database. Do not point tests at a developer, shared, staging, or production database.
- `RefreshDatabase` may run migrations against the configured test database. Inspect the test configuration before running database-backed tests.
- Do not run migrations, seeders, database resets, or other database-changing commands unless explicitly requested and the target database is confirmed safe.
- Never use destructive database commands such as `migrate:fresh` or `db:wipe` without explicit approval.

## Authentication and authorization testing

- Protected API routes use `auth:sanctum` where defined. Use `$this->actingAs($user)` for authenticated feature-test cases, following nearby tests.
- Include the `Origin` and `Referer` headers used by existing SPA authentication tests when testing those flows.
- Cover guest behavior as well as authorized and unauthorized user behavior where relevant.
- Assert the endpoint’s observed status behavior. For example, routes that use `firstOrFail` are tested with 404 responses for inaccessible resources; guests are commonly tested with 401 responses.
- Check both the response and relevant database state when authorization must prevent a change.

## Multi-tenant and organization membership security

- Treat organization and membership ownership as a security boundary.
- For organization-scoped behavior, test that a user cannot read or change another organization’s records by changing an ID in the route.
- Test relevant roles and statuses explicitly. Membership behavior distinguishes owner, manager, and member roles, and invited, active, and suspended statuses.
- When testing invitation flows, cover ownership, self-invitation, duplicate membership, pending status, acceptance, cancellation, and preservation of unrelated memberships as applicable.
- Assert that unauthorized or invalid requests leave protected records unchanged.
- Follow existing membership response and not-found behavior; do not leak whether another tenant’s resource exists unless the API is intentionally designed to do so.
- Before claiming tenant isolation is enforced, inspect the actual routes, queries, relationships, middleware, and policies. Distinguish implemented protections from intended or planned protections.

## Test quality principles

- Test externally observable behavior and meaningful side effects rather than private implementation details.
- Make assertions specific enough to fail when the behavior is wrong. Avoid assertions that pass for unrelated reasons or only confirm that a request completed.
- Avoid false positives: verify the target record, tenant, role, status, and relevant before/after values when they matter.
- Avoid redundant cases unless they cover a distinct role, status, boundary, or security condition.
- Do not delete existing tests to make a change pass.
- Do not run lint or other checks unless requested. When verification is requested, use the repository’s configured PHPUnit setup and report exactly what was run.

## Safe command execution and database protection

- Inspect scripts and configuration before running commands that may modify files, clear caches, invoke migrations, seed data, or access external services.
- The `composer test` script runs `artisan config:clear` before `artisan test`; account for that side effect. Do not run it in a read-only task.
- Do not run migrations, seeders, database resets, or tests against an unverified database connection.
- Never read or print `.env` secrets. Do not modify `.env` values automatically.
- Do not install dependencies unless explicitly requested.
- Do not use destructive shell or Git commands without explicit approval.

## AI-assisted PHPUnit test-generation workflow

For requests involving new PHPUnit tests:

1. Inspect the relevant routes, controllers, Form Requests, Resources, models, policies, factories, migrations, and existing tests.
2. Explain the endpoint or feature behavior based on the actual implementation and requirements.
3. Propose a test scenario matrix containing:
    - Scenario
    - Preconditions
    - Request/action
    - Expected HTTP response
    - Expected database state
4. Identify missing information or ambiguous business rules instead of inventing expected behavior.
5. Wait for explicit approval of the proposed scenarios before generating or modifying test files.
6. Generate focused tests following existing repository conventions.
7. Explain what each generated test verifies.
8. Run tests only when explicitly requested or approved.
9. Review generated tests for meaningful assertions, isolation, security coverage, and false-positive risks.
10. Report test results accurately, distinguishing generated tests from tests actually executed.

Never assume that implementation behavior is correct merely because it exists. Compare implementation behavior with the stated requirements and identify discrepancies separately.

## Command execution permissions

- Read-only inspection commands are allowed when relevant to the task.
- Commands that modify files, database state, dependencies, Git state, or external services require explicit user approval.
- Running PHPUnit or Artisan test commands requires explicit approval because database-backed tests may execute migrations.
- Never commit, push, reset, stash, or change branches without explicit approval.

## AI-assisted coding rules

- Inspect the relevant implementation, tests, configuration, and repository instructions before editing.
- Before making a material or risky change, explain the proposed change and its likely effects.
- Prefer minimal, scoped edits and preserve existing code style and folder structure.
- Avoid unrelated file modifications and unnecessary refactors.
- Never create, edit, delete, or format files when the request is read-only.
- Review the final diff and describe the changes and verification performed.
- Never commit or push without explicit approval.
