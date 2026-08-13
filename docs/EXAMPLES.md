# Examples

These examples use synthetic data and apply to Goosialize Leads 1.0.0.

## Grav Forms capture

Enable the approved form:

```yaml
forms:
  enabled: true
  forms:
    - contact
  source: website
  locale: en
  consent_version: privacy-v1
  success_redirect: /thank-you
```

Add the process action to the form:

```yaml
process:
  goosialize_leads_capture: true
```

Only explicitly listed forms are accepted.

## Public JSON capture

Enable one canonical Origin:

```yaml
public_api:
  enabled: true
  allowed_origins:
    - https://www.example.com
  locale: en
  consent_version: privacy-v1
  body_max_bytes: 16384
  json_max_depth: 4
  rate_limit_count: 10
  rate_limit_window_seconds: 60
```

Send a synthetic request:

```bash
curl   --request POST   --header 'Content-Type: application/json'   --header 'Accept: application/json'   --header 'Origin: https://www.example.com'   --header 'Idempotency-Key: demo-lead-0001'   --data '{
    "name": "Example Person",
    "email": "example.person@example.com",
    "message": "Synthetic release verification request",
    "consent": true
  }'   https://www.example.com/api/v1/goosialize-leads/capture
```

Exact replay with the same canonical payload and idempotency key returns the existing Lead. Reusing the key with a conflicting payload returns an idempotency conflict.

## Admin2 Lead index

Enable the Lead workspace:

```yaml
admin2_index:
  enabled: true
  timezone: UTC
```

Grant:

```text
api.goosialize_leads.read
```

Grant `api.goosialize_leads.write` for Edit, status, Active/Inactive and
Restore. Grant `api.goosialize_leads.delete` for reversible Delete. Read-only
users see no mutation controls. Workflow changes update metadata sidecars;
primary Lead records remain immutable.

## CSV export

Enable bounded CSV export:

```yaml
admin2_csv_export:
  enabled: true
  max_response_bytes: 131072
```

Grant both permissions:

```text
api.goosialize_leads.read
api.goosialize_leads.export
```

The native filename is:

```text
goosialize-leads-YYYY-MM-DD-HHmm.csv
```

## Notification delivery

Enable the outbox and manual delivery:

```yaml
notifications:
  outbox:
    enabled: true
    max_event_bytes: 512
  delivery:
    enabled: true
    recipients:
      - leads@example.com
    sender_address: notifications@example.com
    sender_name: Goosialize Leads
    default_limit: 10
```

Run a bounded delivery batch:

```bash
php bin/plugin goosialize-leads deliver-notifications --limit=10
```

## Retry-only delivery

Enable durable retry state:

```yaml
notifications:
  delivery_retry:
    enabled: true
    maximum_attempts: 5
    delays_seconds: []
    processing_limit: 10
    state_max_bytes: 1024
```

Process retry-eligible events:

```bash
php bin/plugin goosialize-leads deliver-notifications --retries-only --limit=10
```

## Scheduled delivery

Enable scheduler registration:

```yaml
notifications:
  scheduling:
    enabled: true
    frequency_minutes: 5
    batch_limit: 10
    timeout_seconds: 300
```

The registered job is:

```text
goosialize-leads-notification-delivery
```

Its command includes:

```bash
bin/plugin goosialize-leads deliver-notifications --limit=10
```

## Operational status

Inspect bounded human-readable state:

```bash
php bin/plugin goosialize-leads notification-status --limit=10
```

Inspect machine-readable state:

```bash
php bin/plugin goosialize-leads notification-status --limit=10 --json
```

## Reconciliation

After independently confirming the current event state and revision, use one explicit action:

```bash
php bin/plugin goosialize-leads reconcile-notification event-123 4 confirm-delivered --yes
php bin/plugin goosialize-leads reconcile-notification event-123 4 retry-duplicate-risk --yes
php bin/plugin goosialize-leads reconcile-notification event-123 4 dead-letter --yes
```

A stale revision fails closed and does not mutate durable state.

## Public capability integration

Discover the public service:

```text
goosialize-leads.public-capture.v1
```

Request capability:

```text
goosialize-leads.capture
```

The integration must request contract version `1` and use only the public contract. It must not bind to internal classes, Admin2 implementation details or runtime filesystem paths.

## Safety

Use only synthetic Lead data in development, testing, documentation and support material.

Never include:

- production Lead data;
- idempotency secrets;
- SMTP or provider credentials;
- configured recipient lists;
- filesystem paths from production;
- raw exception traces.
