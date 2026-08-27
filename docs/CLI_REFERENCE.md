# CLI reference

Goosialize Leads provides bounded operational commands through the Grav plugin
CLI.

> **Audience:** Advanced operators who need the exact supported commands and
> options. Run them under the deployment's normal shell controls.

## Deliver notifications

Process eligible notification events:

```bash
php bin/plugin goosialize-leads deliver-notifications
```

Apply an explicit batch limit:

```bash
php bin/plugin goosialize-leads deliver-notifications --limit=10
```

Process only retry-eligible events:

```bash
php bin/plugin goosialize-leads deliver-notifications --retries-only
```

Combine both options:

```bash
php bin/plugin goosialize-leads deliver-notifications --retries-only --limit=10
```

The command respects delivery enablement, retry eligibility, maximum attempts,
Dead letter state and duplicate-risk Reconciliation requirements.

## Inspect notification status

Display bounded operational state:

```bash
php bin/plugin goosialize-leads notification-status
```

Apply an explicit result limit:

```bash
php bin/plugin goosialize-leads notification-status --limit=10
```

Request machine-readable output:

```bash
php bin/plugin goosialize-leads notification-status --limit=10 --json
```

The status command is read-only.

## Reconcile notification state

The reconciliation command requires an event identifier, exact current revision,
supported action and explicit confirmation:

```bash
php bin/plugin goosialize-leads reconcile-notification EVENT_ID REVISION ACTION --yes
```

Supported actions:

```text
confirm-delivered
retry-duplicate-risk
dead-letter
```

Examples:

```bash
php bin/plugin goosialize-leads reconcile-notification event-123 4 confirm-delivered --yes
php bin/plugin goosialize-leads reconcile-notification event-123 4 retry-duplicate-risk --yes
php bin/plugin goosialize-leads reconcile-notification event-123 4 dead-letter --yes
```

A stale or mismatched revision fails closed without mutating durable state.

## Exit behavior

Operational commands return a non-zero exit status when configuration,
validation, storage, delivery or reconciliation requirements are not satisfied.

Command output must not expose:

- SMTP or provider credentials;
- configured recipient details beyond bounded operational need;
- idempotency secrets;
- filesystem paths;
- unbounded Lead payloads;
- raw exception traces.

---

## Navigation

[← Back to README](../README.md) · [Previous: Reconciliation](RECONCILIATION.md) ·
[Next: Troubleshooting →](TROUBLESHOOTING.md)

Related documentation: [Operational status](OPERATIONAL_STATUS.md) ·
[Scheduler](SCHEDULER.md) · [Retry and Dead Letter](RETRY_DEAD_LETTER.md)
