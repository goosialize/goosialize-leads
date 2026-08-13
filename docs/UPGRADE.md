# Upgrade

This guide covers upgrades to Goosialize Leads 1.0.0 from development snapshots or earlier internal builds.

## Before upgrading

Create verified backups of:

```text
user/config/plugins/goosialize-leads.yaml
user/data/goosialize-leads/
```

Also record:

- the installed plugin version;
- the active Grav and Admin2 versions;
- enabled optional facilities;
- current notification delivery and retry state;
- scheduled-job configuration;
- custom integration code using the public capture capability.

Do not upgrade while a notification delivery or reconciliation command is running.

## Supported target

Version 1.0.0 is verified against:

- Grav CMS `>=2.0.12 <2.1.0`;
- Admin2 `2.0.15`;
- PHP `^8.3`.

## Package replacement

Install the release package through the same controlled method used for initial installation:

```bash
php bin/gpm direct-install -y /absolute/path/goosialize-leads-1.0.0.zip
```

The plugin directory remains:

```text
user/plugins/goosialize-leads
```

Do not copy selected files from the archive into an older plugin tree. Replace the plugin package as one verified unit.

## Configuration review

Version 1.0.0 keeps optional facilities disabled unless explicitly configured.

Review these sections after replacement:

```text
idempotency
forms
public_api
admin2_index
admin2_csv_export
notifications.outbox
notifications.delivery
notifications.delivery_retry
notifications.scheduling
```

Confirm that secret idempotency keys remain outside version control and were not replaced by package defaults.

## Data retention

Runtime data remains under:

```text
user/data/goosialize-leads/v1
```

Package replacement must not delete this directory.

The retained data can include:

- primary Lead records;
- idempotency sidecars;
- notification outbox events;
- delivery state;
- retry state;
- dead-letter state;
- reconciliation revisions.

## Public integration contract

The version 1 public capture service is:

```text
goosialize-leads.public-capture.v1
```

The capability identifier is:

```text
goosialize-leads.capture
```

Integrations must continue to request contract version `1`.

Do not bind integration code to internal classes, filesystem paths or Admin2 implementation details.

## Post-upgrade verification

Run the bounded operational status command:

```bash
php bin/plugin goosialize-leads notification-status --limit=10
```

Then verify:

- plugin discovery;
- native Admin2 configuration rendering;
- enabled capture entry points;
- Admin2 Lead index permissions;
- CSV export permissions;
- notification delivery configuration;
- scheduler registration when enabled;
- retry and dead-letter state;
- public capability discovery;
- public JSON capture when enabled.

## Rollback

Rollback requires both:

- the previous verified plugin package;
- the matching configuration and runtime-data backups.

Restore the plugin, configuration and runtime data as one compatible set.

Do not restore only primary Lead records while leaving newer notification or idempotency state in place.

## Manual acceptance

Every production upgrade requires manual browser acceptance for the enabled Admin2 and capture surfaces.

A release tag or package checksum does not replace environment-specific acceptance.
