# Notification reconciliation

Notification reconciliation is an explicit operator action for delivery states that
cannot be resolved safely through automatic retry processing.

## Command

Inspect a specific event revision with:

```bash
php bin/plugin goosialize-leads reconcile-notification EVENT_ID REVISION ACTION --yes
```

Replace:

- `EVENT_ID` with the notification event identifier;
- `REVISION` with the exact durable-state revision currently stored;
- `ACTION` with one supported reconciliation action.

The `--yes` option is mandatory for a state-changing reconciliation.

## Supported actions

Version 1.0.0 supports:

```text
confirm-delivered
retry-duplicate-risk
dead-letter
```

### Confirm delivered

Use `confirm-delivered` only when the operator has independently confirmed that
the external delivery completed.

This action records the event as delivered without sending it again.

### Retry duplicate risk

Use `retry-duplicate-risk` only after reviewing an ambiguous delivery attempt and
deciding that another delivery attempt is acceptable.

This action returns an eligible duplicate-risk event to retry processing. It does
not bypass delivery enablement, attempt limits or durable-state validation.

### Dead-letter

Use `dead-letter` when the event must not be delivered or retried automatically.

This action preserves the durable event and records a terminal operational state.

## Revision safety

Reconciliation requires the exact current revision.

A stale or mismatched revision fails closed and does not mutate the durable state.
The operator must inspect the current state again before issuing another action.

This prevents an operator command from silently overwriting a concurrent state
transition.

## Inspection before mutation

Inspect the bounded operational state first:

```bash
php bin/plugin goosialize-leads notification-status --limit=10
```

For machine-readable inspection:

```bash
php bin/plugin goosialize-leads notification-status --limit=10 --json
```

Record the event identifier, revision and current state before choosing an action.

## Examples

Confirm an independently verified delivery:

```bash
php bin/plugin goosialize-leads reconcile-notification event-123 4 confirm-delivered --yes
```

Return a reviewed duplicate-risk event to retry processing:

```bash
php bin/plugin goosialize-leads reconcile-notification event-123 4 retry-duplicate-risk --yes
```

Move an event to terminal dead-letter state:

```bash
php bin/plugin goosialize-leads reconcile-notification event-123 4 dead-letter --yes
```

## Operational safety

Reconciliation must not:

- rewrite the primary Lead record;
- expose SMTP or provider credentials;
- expose configured recipients;
- expose filesystem paths;
- suppress revision conflicts;
- redeliver a duplicate-risk event without explicit operator intent.

Every state-changing action must remain explicit, bounded and auditable.
