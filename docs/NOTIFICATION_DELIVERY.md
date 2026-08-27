# Notification delivery

Notification delivery is an optional facility built on top of the durable notification
outbox.

> **Audience:** Administrators enabling basic Notification delivery. Advanced
> operators should use the linked status, Scheduler and Reconciliation guides.

Related operator guides are [Scheduler](SCHEDULER.md),
[Operational status](OPERATIONAL_STATUS.md),
[Retry and Dead Letter](RETRY_DEAD_LETTER.md),
[Reconciliation](RECONCILIATION.md) and [CLI reference](CLI_REFERENCE.md).

## Requirements

Delivery requires:

- Grav Email installed and enabled;
- notification outbox enabled;
- notification delivery enabled;
- between one and five unique valid recipients;
- a valid sender address.

## Configuration

```yaml
notifications:
  outbox:
    enabled: true
    max_event_bytes: 512
  delivery:
    enabled: true
    recipients:
      - leads@example.com
    sender_address: notifications@example.com
    sender_name: Goosialize Leads
    default_limit: 10
```

## Outbox behaviour

A successful Lead capture can publish a bounded notification event.

The outbox record is durable and separate from the primary Lead record.

The primary Lead capture does not become invalid solely because notification
delivery is disabled or unavailable.

## Manual delivery

Test a small bounded manual batch before enabling the Scheduler. The
[CLI reference](CLI_REFERENCE.md#deliver-notifications) owns the exact command
and options. Inspect the result through [Operational status](OPERATIONAL_STATUS.md).

## Delivery outcomes

Each attempt is recorded in durable notification state.

An event can become:

- delivered;
- pending;
- retry-eligible;
- in Dead letter state;
- duplicate-risk requiring operator reconciliation.

## Failure containment

Delivery failure must not:

- delete the primary Lead record;
- expose SMTP or provider credentials;
- expose recipient configuration;
- expose filesystem paths;
- suppress a required operator action.

For symptom-based checks, see
[Troubleshooting](TROUBLESHOOTING.md#notification-is-not-delivered).

---

## Navigation

[← Back to README](../README.md) ·
[Previous: Public JSON API](JSON_API_INTEGRATION.md) ·
[Next: Operational status →](OPERATIONAL_STATUS.md)

Related documentation: [Scheduler](SCHEDULER.md) ·
[Retry and Dead Letter](RETRY_DEAD_LETTER.md) · [CLI reference](CLI_REFERENCE.md)
