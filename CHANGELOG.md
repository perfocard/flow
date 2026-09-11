# Changelog

All notable changes to `perfocard/flow` are documented here.

## 1.5.1

### Fixed

- `Contracts\BackedEnum` now extends native `\BackedEnum`, and `Idempotency` types status parameters as the Flow contract instead of the native interface. Stops static-analysis false positives when `PendingCallback` passes `Callback::complete()` into `Idempotency::guards()` / `claim()`.

## 1.5.0

### Added

- `FlowEndpoint::timeout()` and `FlowEndpoint::connectTimeout()` — outbound HTTP timeouts are now configurable per endpoint. Previously `PendingEndpoint` used the HTTP client defaults with no way to override them.
- `FlowEndpoint::throw()` — return `false` when a 4xx response body carries a business verdict that `processResponse()` has to read. Defaults to `true`, which is the previous behaviour.
- `flow.endpoint` configuration section with `FLOW_ENDPOINT_TIMEOUT` (30s) and `FLOW_ENDPOINT_CONNECT_TIMEOUT` (10s).

### Changed — breaking for direct contract implementers

`Perfocard\Flow\Contracts\Endpoint` declares three new methods: `timeout()`, `connectTimeout()`, and `throw()`.

Classes extending `Perfocard\Flow\FlowEndpoint` are **not** affected — the base class implements all three, and no existing endpoint needs editing.

A class implementing `Contracts\Endpoint` directly must add them. The published configuration file does not need republishing: `endpoint` is a new top-level key, and `mergeConfigFrom` merges shallowly, so an existing `config/flow.php` picks it up from the package.

### Notes

No retry option was added on purpose. Repeating a state-changing request without an idempotency key is a double charge. Retries stay with the queued listener (`$tries` / `$backoff`), defibrillation, or the attempt-and-probe pattern for verdicts issued elsewhere.
