# Notification reconciliation

Notification reconciliation is an explicit operator action for delivery states that
cannot be resolved safely through automatic retry processing.

> **Audience:** Advanced operators. **High risk:** Reconciliation mutates
> durable Notification state and can permit another delivery attempt.

## Command

The [CLI reference](CLI_REFERENCE.md#reconcile-notification-state) owns the
exact command syntax. Every invocation supplies:

- `EVENT_ID` with the notification event identifier;
- `REVISION` with the exact durable-state revision currently stored;
- `ACTION` with one supported reconciliation action.

Explicit confirmation is mandatory for a state-changing Reconciliation.

## Supported actions

The public contract supports:

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

Inspect bounded state through [Operational status](OPERATIONAL_STATUS.md).
Record the event identifier, revision and current State before choosing an
action. Independently confirm external delivery when duplicate risk exists;
do not infer delivery from local state alone.

## Operational safety

Reconciliation must not:

- rewrite the primary Lead record;
- expose SMTP or provider credentials;
- expose configured recipients;
- expose filesystem paths;
- suppress revision conflicts;
- redeliver a duplicate-risk event without explicit operator intent.

Every state-changing action must remain explicit, bounded and auditable.

---

## Navigation

[← Back to README](../README.md) ·
[Previous: Retry and Dead Letter](RETRY_DEAD_LETTER.md) ·
[Next: CLI reference →](CLI_REFERENCE.md)

Related documentation: [Operational status](OPERATIONAL_STATUS.md) ·
[Notification delivery](NOTIFICATION_DELIVERY.md) · [Security](SECURITY.md)
