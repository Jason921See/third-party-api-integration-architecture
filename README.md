# Tenant Integration API Architecture

## Overview

This repository is a Laravel-based tenant-aware integration platform for connecting tenant accounts to external marketing APIs. It currently implements a Facebook integration flow and provides:

- Tenant-scoped API routes under `routes/tenant.php`
- A centralized `IntegrationService` to coordinate connect and sync requests
- A dedicated external service layer for provider-specific API interactions
- Background sync jobs to fetch external insight data
- Feature and unit test coverage with mocked third-party API responses

## Architecture Decisions

- **Tenant-aware routing**: Tenant routes are registered via `app/Providers/TenancyServiceProvider.php` and prefixed with `/api`.
- **Service layer separation**: `App\Services\Tenant\IntegrationService` handles generic integration workflows, while provider-specific logic lives in `App\Integrations\Tenant\ExternalService\Facebook`.
- **Provider abstraction**: The `FacebookService` encapsulates Facebook-specific token validation, refresh, and ad account retrieval.
- **Job-based sync**: Sync requests queue `FetchFacebookInsightJob` instead of performing long-running HTTP calls during the request.
- **HTTP mocking support**: Tests use `Http::fake()` to simulate third-party Facebook API responses without network access.
- **Data persistence**: `Integration` and `IntegrationProvider` models store tenant integrations and provider metadata separately.

## How to Add a New Integration

1. **Add a provider record**
   - Create an `IntegrationProvider` row for the new provider with `slug`, `config`, `scopes`, and `is_active`.
   - Example storage is in `database/migrations/tenant/2026_04_13_093246_create_integration_providers_table.php`.

2. **Create a provider service**
   - Add a new service class under `app/Integrations/Tenant/ExternalService/<ProviderName>/<ProviderName>Service.php`.
   - Implement provider-specific methods for authentication, validation, and API calls.

3. **Implement a client**
   - Add an HTTP client helper similar to `FacebookClient` for common request methods and logging.

4. **Wire into `IntegrationService`**
   - Extend `App\Services\Tenant\IntegrationService::connect()` to map the new provider slug to the new service.
   - Extend `sync()` and add a dispatch method for the provider’s sync job.

5. **Add sync job(s)**
   - Implement a tenant job in `app/Jobs/Tenant/` to fetch provider insights and persist them.
   - Ensure the job accepts the required parameters and is dispatchable.

6. **Add feature tests**
   - Add API tests under `tests/Feature/Tenant/Integration`.
   - Use `Http::fake()` to mock external API responses.
   - Validate that the endpoint returns the expected status and payload.

7. **Add unit tests**
   - Add service unit tests under `tests/Unit/Tenant/Integration`.
   - Cover token validation, refresh logic, request/response handling, and error cases.

## Failure & Retry Strategy

- **Request validation failures** return HTTP 422 or 404 from controllers.
- **Connect errors** from provider validation or account lookup return structured failure arrays and are translated to JSON responses.
- **Sync dispatch** always returns a 202 when job dispatch succeeds; actual provider failures are handled asynchronously by queued jobs.
- **Token refresh errors** are logged and returned as failure arrays from `FacebookService::refreshToken()`.
- **Queue retry policy** is managed by Laravel queue configuration. The current setup uses `QUEUE_CONNECTION=sync` for tests, but production can use `redis`, `database`, or other queue drivers with retry settings.
- **HTTP failures** in provider services are detected by `Response::successful()` and mapped to friendly errors.

## Security Considerations

- **Access tokens are encrypted at rest** via the `Integration` model’s mutators for `access_token` and `refresh_token`.
- **Provider configuration is isolated** within `IntegrationProvider.config` and not hard-coded in business logic.
- **Tenant authentication** is enforced using `auth:sanctum` middleware on tenant routes.
- **Third-party calls are mocked in tests** so no real credentials are required during automated runs.
- **Input validation** is implemented on controller requests before any service call.
- **Sensitive token refresh logic** uses Laravel’s HTTP client and config values from `config/services.php`.

## Assumptions & Trade-offs

- **Single provider implemented first**: The current code focuses on Facebook and uses a provider-specific service pattern to make future providers easier.
- **Simplified tenancy in tests**: Feature tests disable tenant-specific middleware so they can run without full domain-based tenant bootstrapping.
- **No full OAuth redirect flow**: The repository assumes access tokens and account IDs are already available at connect time.
- **Sync is async-only**: The API returns immediately after queuing sync jobs rather than waiting for external insight fetch completion.
- **Local validation differs from production**: `FacebookService::validateToken()` chooses direct API validation in local environments and debug-token validation in production.
- **Provider config as JSON**: Storing provider endpoints and scopes in JSON enables runtime flexibility but requires consistent config schema.

## Testing

- Feature tests are placed under `tests/Feature/Tenant/Integration`.
- Unit tests for integration services are under `tests/Unit/Tenant/Integration`.
- Third-party API responses are mocked using `Http::fake()`.

## Key Files

- `routes/tenant.php` — Tenant API route definitions
- `app/Http/Controllers/Tenant/IntegrationController.php` — connect and sync endpoints
- `app/Services/Tenant/IntegrationService.php` — integration orchestration
- `app/Integrations/Tenant/ExternalService/Facebook/FacebookService.php` — Facebook integration logic
- `app/Jobs/Tenant/FetchFacebookInsightJob.php` — queued sync job
- `app/Models/Tenant/Integration.php` — tenant integration model
- `app/Models/Tenant/IntegrationProvider.php` — provider metadata

---

This README is tailored to the current project scope and should be updated as additional integrations and tenant behaviors are added.
