# Retry and Dead Letter

Goosialize Leads stores durable notification delivery state so transient failures
can be retried without rewriting the primary Lead record.

> **Audience:** Advanced operators interpreting Retry, Dead letter and
> duplicate-risk Notification states.

## Configuration

```yaml
notifications:
  delivery_retry:
    enabled: true
    maximum_attempts: 5
    delays_seconds:
      - 300
      - 1800
      - 7200
      - 28800
    processing_limit: 10
    state_max_bytes: 1024
```

The current public contract enforces:

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

Retry processing is explicit and bounded. Use the retries-only delivery mode;
the [CLI reference](CLI_REFERENCE.md#deliver-notifications) owns exact command
syntax and limit options.

## Dead-letter state

When an event reaches the maximum attempt count without a confirmed delivery,
the event enters Dead letter state.

Dead-lettered events are not automatically retried. They require explicit
operator review and, when appropriate, reconciliation.

## Duplicate-risk state

An interrupted or ambiguous delivery attempt can be marked as duplicate-risk.

Duplicate-risk events are not redelivered automatically. The operator must
determine whether delivery occurred before confirming delivery, retrying or
moving the event to Dead letter state.

## State safety

Durable retry state must not contain:

- SMTP or provider credentials;
- idempotency secrets;
- unbounded exception text;
- filesystem paths;
- unbounded Lead payloads.

## Operational inspection

Inspect bounded Notification state as described in
[Operational status](OPERATIONAL_STATUS.md). Use machine-readable output only
for controlled operational tooling.

---

## Navigation

[← Back to README](../README.md) · [Previous: Scheduler](SCHEDULER.md) ·
[Next: Reconciliation →](RECONCILIATION.md)

Related documentation: [Notification delivery](NOTIFICATION_DELIVERY.md) ·
[Operational status](OPERATIONAL_STATUS.md) · [CLI reference](CLI_REFERENCE.md)
