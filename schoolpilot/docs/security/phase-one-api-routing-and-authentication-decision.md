# Phase 1 API routing and authentication decision

**Status:** API routes are not enabled.  
**Decision:** Keep the legacy tenant API route file unregistered until a supported API authentication and authorization design is implemented and tested.

## Current source findings

- `bootstrap/app.php` registers the web routes, console routes, and health endpoint. It does not register an API route file.
- `routes/tenant.php` defines a path-scoped endpoint at `POST /api/school/{tenant}/login` using Stancl tenancy middleware.
- The endpoint checks an email and password, then returns basic user details. It does not issue a session, personal access token, OAuth token, or other credential for subsequent API requests.
- The tenant `User` model explicitly uses the `tenant_sqlite` connection.
- The active web application resolves tenants through the custom `UseSchoolDatabase` middleware and the central tenant registry. The legacy API route instead relies on Stancl tenancy initialization. These are separate tenant-resolution paths and should not be mixed without an explicit design.
- CSRF exclusion for `api/*` in the web middleware configuration does not itself make an API endpoint secure or register the route.

## Security decision

Do not register `routes/tenant.php` or expose its login endpoint as part of this change.

Registering it now would expose a login response that cannot authenticate later requests. It would also introduce a second tenant-resolution mechanism without evidence that it resolves the same tenant database and status rules as the active web middleware.

The current route file is treated as legacy/inactive until an API product requirement is approved. No production route or authentication behavior is changed by this decision.

## Requirements before enabling a SchoolPilot API

1. **Confirm API use cases and clients.** Define whether the API serves the first-party mobile app, parent/student portals, external school integrations, or all of these.
2. **Choose one tenant-resolution contract.** Resolve a school only from an active central tenant record; reject unknown, suspended, malformed, or mismatched tenant identifiers before querying tenant data.
3. **Choose token authentication.** Use a maintained token system such as Laravel Sanctum if compatible with the selected Laravel version and client requirements. Never return a successful login response without issuing a usable, revocable credential.
4. **Authorize every request.** Apply active-account checks and role/permission policies to every protected API resource. Login success must not imply access to all school data.
5. **Enforce tenant isolation.** Every authenticated request must bind the credential to its school and reject attempts to reuse it against another school. Do not trust a tenant ID supplied only in a request body.
6. **Protect credentials.** Hash stored tokens, support revocation/logout, rate-limit login and sensitive actions, validate inputs, and avoid logging passwords or bearer tokens.
7. **Define API response contracts.** Use consistent JSON errors, status codes, pagination, validation formats, and versioning.
8. **Test denial paths.** Cover invalid credentials, inactive users, suspended schools, unknown tenants, expired/revoked tokens, cross-tenant token reuse, forbidden roles, and cross-family parent access.
9. **Test operational behavior.** Verify migrations, queues, rate limits, monitoring, and API behavior against the intended production database architecture before release.

## Phase 1 acceptance criteria

The API is ready to be enabled only when the API authentication design is approved, the implementation uses the same authoritative tenant registry as the web application, and automated feature tests prove the denial paths above.

Until then, the existing browser-based school application remains the supported interface. This decision does not claim that a public or mobile API is implemented.
