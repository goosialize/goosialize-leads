# Troubleshooting

This guide covers common operational failures in Goosialize Leads 1.0.0.

## Plugin is not discovered

Verify:

- the plugin directory is `user/plugins/goosialize-leads`;
- blueprints and plugin entry points are present;
- the configuration file is valid YAML;
- `enabled: true` is set;
- the host uses a supported Grav and PHP version.

Do not rename the plugin directory or the main plugin file.

## Admin2 page is unavailable

Verify:

- the plugin is enabled;
- the relevant facility is enabled;
- the user has the required ACL permission;
- Admin2 is version `2.0.15`;
- the authenticated user has the capability required by the missing control.

The read-only Lead index requires:

```text
api.goosialize_leads.read
```

CSV export also requires:

```text
api.goosialize_leads.export
```

Edit, status, Active/Inactive and Restore require
`api.goosialize_leads.write`. Delete requires
`api.goosialize_leads.delete`. If a Lead is Deleted, restore it before editing;
direct deleted-state mutations intentionally return a conflict. A revision
conflict means another update won and the workspace must reload before retry.

Leads outside the latest-100 display are still addressable through a valid
exact ID. A 404 for such an ID indicates no validated immutable record exists;
a storage error indicates containment, permissions, size or canonical-record
validation failed closed.

## Grav Form does not capture a Lead

Verify:

- `forms.enabled` true;
- the exact form name is listed in `forms.forms`;
- the form uses the exact process action:

```yaml
process:
  goosialize_leads_capture: true
```

- idempotency is configured;
- required fields and consent values are valid;
- the runtime-data directory is writable.

Invalid configuration, idempotency conflict or storage failure must fail closed.

## Public JSON capture returns 403

Verify:

- `public_api.enabled` is true;
- the request contains an `Origin` header;
- the Origin exactly matches one `allowed_origins` entry;
- the Origin is canonical and does not use a wildcard;
- the request is sent to the exact capture endpoint.

Endpoint:

```http
POST /api/v1/goosialize-leads/capture
```

## Public JSON capture returns 400

Verify:

- `Content-Type: application/json`;
- the raw body is valid JSON;
- the body is no larger than 16,384 bytes;
- JSON depth does not exceed 4;
- exactly one valid `Idempotency-Key` header is present;
- required fields and consent values are valid.

## Public JSON capture returns 409

The idempotency key was previously used with a different canonical payload.

Use a new unique key for a new payload. Do not delete or mutate the existing Lead record to bypass the conflict.

## Public JSON capture returns 429

The rate limit was exceeded.

Respect the positive `retry_after` value before sending another request.

Verify only one request path is submitting the capture and that client retry logic does not ignore the rate-limit response.

## Public JSON capture returns 503

Verify:

- idempotency configuration has an active key version;
- the matching secret key exists;
- the runtime-data directory is writable;
- resolved paths remain contained;
- there is no symlink, file-identity or storage-format mismatch.

Do not expose secrets, filesystem paths or raw exceptions in client diagnostics.

## CSV export is forbidden

Verify the user has both:

```text
api.goosialize_leads.read
api.goosialize_leads.export
```

Also verify `admin2_csv_export.enabled` true.

## CSV export is empty or incomplete

Verify:

- canonical Lead records exist;
- the records pass format and containment validation;
- the output is not truncated by `max_response_bytes`;
- the requested records are within the bounded index scope.

Idempotency sidecars and notification state are not Lead records.

## Notifications are not delivered

Verify:

- Grav Email is installed and enabled;
- `notifications.outbox.enabled` is true;
- `notifications.delivery.enabled` is true;
- between one and five unique valid recipients are configured;
- a valid sender address is configured;
- the mail provider is operational.

Run:

```bash
php bin/plugin goosialize-leads notification-status --limit=10
```

Then test a bounded manual delivery:

```bash
php bin/plugin goosialize-leads deliver-notifications --limit=10
```

## Retries are not processed

Verify:

- `delivery_retry.enabled` true;
- the event is retry-eligible;
- the maximum attempt count has not been reached;
- the event is not dead-lettered;
- the event is not blocked by duplicate-risk state.

Run:

```bash
php bin/plugin goosialize-leads deliver-notifications --retries-only --limit=10
```

## Scheduled delivery does not run

Verify:

- `notifications.scheduling.enabled` is true;
- outbox and delivery are also enabled;
- Grav Email is available;
- the registered job is `goosialize-leads-notification-delivery`;
- the host Grav scheduler runner is active.

The registered command should include:

```bash
bin/plugin goosialize-leads deliver-notifications --limit=10
```

## Dead-letter event keeps reappearing

Dead-lettered events are not automatically retried.

Verify that no unsupported script is mutating durable state or resetting attempt counts.

Inspect the current revision before any operator action:

```bash
php bin/plugin goosialize-leads notification-status --limit=10 --json
```

## Reconciliation fails with a revision conflict

The provided revision is stale or incorrect.

Inspect the event again and use the exact current revision.

Do not bypass the revision check or directly mutate the durable state file.

## Notification status command fails

Verify:

- the plugin is enabled;
- runtime data is contained in `user/data/goosialize-leads/v1`;
- the requested limit is valid;
- state files pass format, size, containment and identity validation.

For machine-readable output, use:

```bash
php bin/plugin goosialize-leads notification-status --limit=10 --json
```

## Not supported in version 1.0.0

The following are not troubleshooting targets because they are intentionally absent from version 1.0.0:

- native Admin2 Lead detail;
- Lead status mutation;
- reversible Lead delete and restore.

These features remain blocked by the Admin2 2.0.15 native contract.

## Safe support bundle

When creating a support report, include:

- plugin version;
- Grav, Admin2 and PHP versions;
- a redacted reproduction procedure;
- the affected command or entry point;
- the stable error category;
- bounded, redacted operational output.

Do not include:

- real Lead data;
- idempotency keys;
- SMTP or provider credentials;
- recipient lists;
- filesystem paths;
- raw exception traces.
