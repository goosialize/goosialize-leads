# Upgrade

This guide covers upgrades from an older Goosialize Leads 1.x installation to
a newer compatible release. Read the target release notes before replacing the
package.

## Before upgrading

Create and verify backups of:

```text
user/config/plugins/goosialize-leads.yaml
user/data/goosialize-leads/
```

Include existing environment-specific plugin configuration under
`user/env/*/config/plugins/` when used. Record:

- installed and target plugin versions;
- Grav, Admin2, API, Email and PHP versions;
- enabled capture, export and notification facilities;
- notification retry, dead-letter and duplicate-risk state;
- scheduler configuration;
- integrations using the public capture capability.

Do not upgrade while notification delivery or reconciliation is running.

## Compatibility

Current packages target Grav CMS `>=2.0.12` and PHP `>=8.3` with `ext-intl`.
Review `blueprints.yaml`, the target release notes and GPM dependency output
for the exact package contract.

## Replace the complete package

Use the supported GPM update/install workflow, or direct-install a complete
stable release archive:

```bash
php bin/gpm direct-install -y /absolute/path/goosialize-leads-<version>.zip
```

The destination remains `user/plugins/goosialize-leads`. Do not merge selected
files from different versions into the existing tree. Package replacement must
not delete `user/data/goosialize-leads/`.

## Upgrade from older 1.x configuration

Review these sections after replacement:

```text
idempotency
forms
downloads
public_api
admin2_index
admin2_csv_export
notifications.outbox
notifications.delivery
notifications.delivery_retry
notifications.scheduling
```

Preserve environment-specific overrides and confirm the effective configuration
uses the target release's fixed security bounds.

## Legacy flat idempotency secrets

Older 1.x configuration may use a scalar key:

```yaml
idempotency:
  active_key_version: 1
  keys:
    1: <secret>
```

Current startup migrates base and existing environment-specific configuration
to:

```yaml
idempotency:
  active_key_version: 1
  keys:
    1:
      secret: <secret>
```

The migration preserves the secret bytes and active version exactly. It does
not generate, re-enter or rotate a key, and it is safe to repeat. Ensure the
PHP/web user can write every affected configuration file. A persistence
failure blocks this plugin's configuration API response rather than risking
secret disclosure.

See [Configuration](CONFIGURATION.md#legacy-secret-migration) and
[Troubleshooting](TROUBLESHOOTING.md#legacy-flat-secret-upgrade-or-migration-fails).

## Retained runtime data

Runtime data remains under:

```text
user/data/goosialize-leads/v1
```

It can include immutable primary Leads, idempotency sidecars, notification
events, delivery/retry/dead-letter state and reconciliation revisions. Do not
restore only one portion of this state without proving compatibility with the
rest.

## Public integrations

The version 1 service key remains
`goosialize-leads.public-capture.v1`, with capability identifier
`goosialize-leads.capture` and contract version `1`. Consumers must detect the
published capability and must not bind to internal classes, storage paths or
Admin2 implementation details.

Public JSON clients must derive the endpoint from the effective API route and
version prefix rather than assuming `/api/v1`.

## Post-upgrade verification

1. Confirm plugin discovery and native configuration rendering.
2. Confirm Admin2 sidebar and control visibility for representative roles.
3. Submit a synthetic Lead through each enabled capture entry point.
4. Confirm CSV permissions and bounded output when enabled.
5. Inspect notification state:

   ```bash
   php bin/plugin goosialize-leads notification-status --limit=10
   ```

6. Confirm delivery, retries and scheduler registration when enabled.
7. Confirm public capability detection and configured public API route.
8. Confirm legacy secrets are nested and redacted without exposing their
   values.

## Rollback

Rollback requires the previous verified plugin package plus the matching
configuration and runtime-data backups. Restore them as one compatible set.

Rolling back only plugin code can leave newer configuration or durable state
that the older version does not understand. Likewise, restoring only primary
Leads while leaving newer idempotency or notification state can break replay
and delivery guarantees.

Every production upgrade or rollback requires environment-specific Admin2 and
capture verification. See [Security](SECURITY.md) and
[Uninstall and data retention](UNINSTALL_DATA_RETENTION.md).
