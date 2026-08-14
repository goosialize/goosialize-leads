# Configuration

The canonical configuration file is:

```text
user/config/plugins/goosialize-leads.yaml
```

## Core

```yaml
enabled: true
```

Disabling the plugin makes its runtime integrations inert.

## Idempotency

```yaml
idempotency:
  active_key_version: null
  keys: {}
```

Fresh installations can capture through Grav Forms without a key ring. That
mode deliberately stores no idempotency digest; the public JSON API and public
capture capability remain unavailable until an active key is configured.

Configure keyed capture with a canonical Base64 encoding of exactly 32 random
bytes. Native Admin2 exposes the version 1 secret as a password field, and the
API plugin redacts every nested `secret` value. Additional rotation keys use
the same nested form:

```yaml
idempotency:
  active_key_version: 1
  keys:
    1:
      secret: <canonical-base64-secret>
```

Idempotency keys are configuration secrets and must not be committed, logged or
included in support material.

## Grav Forms

```yaml
forms:
  enabled: false
  forms: []
  source: website
  locale: null
  consent_version: privacy-v1
  success_redirect: /
```

Only explicitly listed forms are accepted.

## Public JSON API

```yaml
public_api:
  enabled: false
  allowed_origins: []
  locale: null
  consent_version: privacy-v1
  body_max_bytes: 16384
  json_max_depth: 4
  rate_limit_count: 10
  rate_limit_window_seconds: 60
```

Wildcard Origins are not accepted.

The endpoint suffix is `/goosialize-leads/capture`; its base is derived from
the API plugin's `route` and `version_prefix` settings. With API defaults the
complete path is `/api/v1/goosialize-leads/capture`.

## Admin2 Lead index

```yaml
admin2_index:
  enabled: false
  timezone: UTC
```

The index remains bounded to the latest 100 primary records. Read-only users
receive no mutation controls. Edit, status, Active/Inactive, Delete and Restore
are capability-gated and update metadata sidecars rather than primary records.

## CSV export

```yaml
admin2_csv_export:
  enabled: false
  max_response_bytes: 131072
```

Export requires a separate ACL permission.

## Notification outbox

```yaml
notifications:
  outbox:
    enabled: false
    max_event_bytes: 512
```

## Manual delivery

```yaml
notifications:
  delivery:
    enabled: false
    recipients: []
    sender_address: null
    sender_name: null
    default_limit: 10
```

There must be between one and five unique valid recipients.

## Durable retries

```yaml
notifications:
  delivery_retry:
    enabled: false
    maximum_attempts: 5
    delays_seconds: []
    processing_limit: 10
    state_max_bytes: 1024
```

The maximum attempt count is fixed at five.

## Scheduling

```yaml
notifications:
  scheduling:
    enabled: false
    frequency_minutes: 5
    batch_limit: 10
    timeout_seconds: 300
```

The batch limit must remain between 1 and 50. The timeout is fixed at 300
seconds.
