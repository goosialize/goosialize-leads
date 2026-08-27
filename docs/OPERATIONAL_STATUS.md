# Operational status

Goosialize Leads provides bounded read-only operational visibility for
notification delivery state.

> **Audience:** Advanced operators inspecting bounded Notification state.

## Command

Use the read-only `notification-status` command. The
[CLI reference](CLI_REFERENCE.md#inspect-notification-status) owns its exact
syntax, result limit and machine-readable option.

## Permission

Native Admin2 operational visibility requires:

```text
api.goosialize_leads.operations
```

CLI execution remains subject to host-level shell and deployment controls.

## Report scope

The status report summarizes bounded notification state, including:

- pending events;
- retry-eligible events;
- delivered events;
- events in Dead letter state;
- duplicate-risk events requiring operator review.

The report does not mutate notification state.

## Result bounds

The `--limit` option bounds the number of detailed records returned.

The configured and command-line bounds must not be used to bypass:

- durable-state validation;
- filesystem containment;
- opened-file identity checks;
- revision safety;
- redaction requirements.

## JSON output

The `--json` option produces machine-readable output for controlled operational tooling.

Machine-readable output must remain bounded and must not expose:

- SMTP or provider credentials;
- configured recipient lists beyond operational need;
- idempotency secrets;
- filesystem paths;
- unbounded Lead payloads;
- raw exception traces.

## Interpreting states

### Pending

A pending event has not yet completed delivery and may be eligible for normal processing.

### Retry eligible

A retry-eligible event has a recorded failed attempt and may be processed again within the maximum-attempt bound.

### Delivered

A delivered event has confirmed durable delivery state and must not be sent again automatically.

### Dead letter

An event in Dead letter state reached a terminal condition and requires operator review before any further action.

### Duplicate risk

A duplicate-risk event represents an ambiguous or interrupted delivery attempt.

It must not be redelivered automatically. The operator must inspect the current revision and use explicit reconciliation when appropriate.

## Reconciliation workflow

Inspect current state first. Then use the exact event identifier and current
revision with one supported action. Exact command syntax belongs to the
[CLI reference](CLI_REFERENCE.md#reconcile-notification-state).

Supported actions are:

```text
confirm-delivered
retry-duplicate-risk
dead-letter
```

A stale revision fails closed and does not mutate durable state.

## Operational use

The status command is suitable for:

- manual operations review;
- deployment verification;
- scheduler health checks;
- bounded support diagnostics;
- controlled automation that consumes JSON output.

It is not a substitute for external mail-provider delivery logs or independent confirmation when duplicate-risk reconciliation is required.

---

## Navigation

[← Back to README](../README.md) ·
[Previous: Notification delivery](NOTIFICATION_DELIVERY.md) ·
[Next: Troubleshooting →](TROUBLESHOOTING.md)

Related documentation: [Scheduler](SCHEDULER.md) ·
[Retry and Dead Letter](RETRY_DEAD_LETTER.md) ·
[Reconciliation](RECONCILIATION.md) · [CLI reference](CLI_REFERENCE.md)
