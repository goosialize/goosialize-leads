# Examples

These examples use synthetic data. Canonical guides contain the complete
contracts and should be preferred over copying isolated fragments from this
page.

## Grav Forms capture

Use the complete native form and allowlist configuration in
[Forms integration](FORMS_INTEGRATION.md). The required custom action is:

```yaml
process:
  goosialize_leads_capture: true
```

Trusted Forms capture supports the shipped empty idempotency key ring.

## Public JSON capture

The request consent member is a nested object, never a scalar boolean:

```json
{
  "full_name": "Example Person",
  "email": "person@example.com",
  "message": "Synthetic documentation request",
  "consent": {
    "granted": true
  }
}
```

For the **default API configuration**, a request can target
`/api/v1/goosialize-leads/capture`. That path is not universal; derive the
actual endpoint from the API plugin's configured route and version prefix.
Use the complete headers, curl command, field reference and response examples
in [Public JSON API](JSON_API_INTEGRATION.md).

## Admin2 Lead management

The shipped configuration enables the bounded Lead index. Grant read access:

```text
api.goosialize_leads.read
```

Add write for inline Edit, Status, Active/Inactive and Restore. Add delete for
reversible Delete. See the [Admin Guide](ADMIN_GUIDE.md) and
[Permissions](PERMISSIONS.md).

## CSV export

```yaml
admin2_csv_export:
  enabled: true
  max_response_bytes: 131072
```

Grant both `api.goosialize_leads.read` and
`api.goosialize_leads.export`. See [CSV export](CSV_EXPORT.md).

## Notification delivery

After completing [Notification delivery](NOTIFICATION_DELIVERY.md), process a
bounded manual batch:

```bash
php bin/plugin goosialize-leads deliver-notifications --limit=10
```

Inspect state before retry or reconciliation:

```bash
php bin/plugin goosialize-leads notification-status --limit=10
php bin/plugin goosialize-leads notification-status --limit=10 --json
```

Advanced command and state references:

- [Scheduler](SCHEDULER.md)
- [Retry and dead-letter state](RETRY_DEAD_LETTER.md)
- [Reconciliation](RECONCILIATION.md)
- [CLI reference](CLI_REFERENCE.md)

## Public plugin capability

Compatible local Grav plugins discover service
`goosialize-leads.public-capture.v1`, request capability
`goosialize-leads.capture`, and require contract version `1`. They must use
only the published DTOs and outcomes. See the
[Public Integration Contract](PUBLIC_INTEGRATION_CONTRACT.md).

## Safety

Use only synthetic Lead data in development, documentation and support
material. Never include production Lead values, idempotency secrets, mail
credentials, recipient lists, production filesystem paths or raw exceptions.
