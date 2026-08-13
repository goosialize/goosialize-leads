# Uninstall and data retention

Goosialize Leads 1.0.0 separates plugin code, configuration and runtime data so an uninstall can be performed without silently destroying Lead or notification records.

## Relevant paths

Plugin code:

```text
user/plugins/goosialize-leads
```

Configuration:

```text
user/config/plugins/goosialize-leads.yaml
```

Runtime data:

```text
user/data/goosialize-leads/v1
```

The runtime directory can contain:

- primary Lead records;
- idempotency sidecars;
- notification outbox events;
- delivery state;
- retry state;
- dead-letter state;
- reconciliation revisions.

## Default retention policy

Removing or replacing the plugin package must not automatically delete:

```text
user/data/goosialize-leads/
```

This is the version 1.0.0 default data-retention contract.

Disabling the plugin also does not delete configuration or runtime data.

## Before uninstalling

Create verified backups of:

```text
user/config/plugins/goosialize-leads.yaml
user/data/goosialize-leads/
```

Also record:

- the installed plugin version;
- active Grav and Admin2 versions;
- enabled capture surfaces;
- notification delivery and scheduler configuration;
- unresolved retry, dead-letter or duplicate-risk events;
- integrations using the public capture capability.

Do not uninstall while a delivery or reconciliation command is running.

## Disable first

Disable the plugin before removing its package:

```yaml
enabled: false
```

Then verify that:

- public JSON capture no longer accepts requests;
- configured Forms capture is inactive;
- Admin2 Lead and export surfaces are unavailable;
- the scheduled delivery job is not registered;
- no operational command is currently running.

## Remove plugin code

Remove only the plugin package directory:

```text
user/plugins/goosialize-leads
```

Do not include the runtime-data directory in the same deletion operation.

## Configuration retention

Configuration may be retained for a planned reinstall.

Configuration can include sensitive values, including idempotency secrets and notification routing. Retained configuration must remain access-controlled and outside public web access.

Delete configuration only as a separate, explicit administrative decision.

## Runtime-data deletion

Permanent deletion of runtime data is irreversible and can remove:

- captured Lead records;
- replay protection;
- delivery audit state;
- retry and dead-letter evidence;
- reconciliation history.

Before deleting runtime data:

1. confirm the legal and business retention policy;
2. export or archive required records;
3. verify that no integration still depends on the data;
4. record the approved deletion scope;
5. perform the deletion as a separate operation.

The plugin does not provide an automatic purge command in version 1.0.0.

## Reinstall

A reinstall can reuse retained configuration and runtime data when the restored plugin version is compatible with the stored schema.

After reinstalling, verify:

```bash
php bin/plugin goosialize-leads notification-status --limit=10
```

Then verify plugin discovery, native Admin2 rendering, enabled capture surfaces and public capability discovery.

## Security

Uninstall and retention operations must not expose:

- stored Lead values;
- idempotency secrets;
- SMTP or provider credentials;
- configured recipient lists;
- filesystem paths outside controlled administrator output;
- raw exception traces.

## Manual acceptance

A production uninstall or reinstall procedure requires environment-specific manual verification. Package removal alone is not evidence that scheduled jobs, integrations or retained data have been handled correctly.
