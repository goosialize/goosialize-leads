# Retry and dead-letter state

Goosialize Leads stores durable notification delivery state so transient failures
can be retried without rewriting the primary Lead record.

## Configuration

```yaml
notifications:
  delivery_retry:
    enabled: true
    maximum_attempts: 5
    delays_seconds: []
    processing_limit: 10
    state_max_bytes: 1024
```

Version 1.0.0 enforces:

- a fixed maximum of five delivery attempts;
- a default processing limit of 10;
- a maximum durable state size of 1,024 bytes.

## Retry eligibility

A failed delivery becomes retry-eligible only when the recorded outcome and
durable state permit another attempt.

Retry processing does not bypass:

- the delivery-enabled gate;
- the maximum attempt bound;
- durable state validation;
- duplicate-risk protection;
- operator reconciliation requirements.

## Manual retry processing

Process retry-eligible events with:

```bash
php bin/plugin goosialize-leads deliver-notifications --retries-only
```

A bounded limit can be applied:

```bash
php bin/plugin goosialize-leads deliver-notifications --retries-only --limit=10
```

## Dead-letter state

When an event reaches the maximum attempt count without a confirmed delivery,
the event becomes dead-lettered.

Dead-lettered events are not automatically retried. They require explicit
operator review and, when appropriate, reconciliation.

## Duplicate-risk state

An interrupted or ambiguous delivery attempt can be marked as duplicate-risk.

Duplicate-risk events are not redelivered automatically. The operator must
determine whether delivery occurred before confirming delivery, retrying or
dead-lettering the event.

## State safety

Durable retry state must not contain:

- SMTP or provider credentials;
- idempotency secrets;
- unbounded exception text;
- filesystem paths;
- unbounded Lead payloads.

## Operational inspection

Inspect bounded notification state with:

```bash
php bin/plugin goosialize-leads notification-status --limit=10
```

Use `--json` only when machine-readable output is required.
