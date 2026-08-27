# Scheduler

The notification scheduler registers a bounded Grav scheduler job for delivering
pending notifications.

> **Audience:** Advanced operators automating bounded delivery after manual
> delivery has been tested.

## Requirements

Scheduled delivery requires:

- the plugin enabled;
- notification outbox enabled;
- notification delivery enabled;
- scheduling enabled;
- Grav Email installed and enabled;
- valid recipient and sender configuration.

## Configuration

```yaml
notifications:
  scheduling:
    enabled: true
    frequency_minutes: 5
    batch_limit: 10
    timeout_seconds: 300
```

The current public contract enforces:

- a frequency of 5 minutes;
- a batch limit between 1 and 50;
- a fixed timeout of 300 seconds.

## Registered job

The job name is:

```text
goosialize-leads-notification-delivery
```

The job invokes bounded Notification delivery and projects `batch_limit` into
the delivery limit. Exact manual command syntax belongs to the
[CLI reference](CLI_REFERENCE.md#deliver-notifications).

## Execution behaviour

Each scheduled run attempts a bounded batch of eligible notification events.

The job does not bypass:

- delivery enablement;
- retry eligibility;
- maximum attempt bounds;
- Dead letter state;
- duplicate-risk reconciliation requirements.

## Disablement

When scheduling is disabled, the plugin must not register the delivery job.

Disabling the scheduler does not delete:

- primary Lead records;
- notification outbox events;
- delivery state;
- retry state;
- Dead letter state.

## Operational check

Inspect Notification state through [Operational status](OPERATIONAL_STATUS.md),
then confirm that the Grav scheduler runner is active in the host environment.

---

## Navigation

[← Back to README](../README.md) ·
[Previous: Operational status](OPERATIONAL_STATUS.md) ·
[Next: Retry and Dead Letter →](RETRY_DEAD_LETTER.md)

Related documentation: [Notification delivery](NOTIFICATION_DELIVERY.md) ·
[CLI reference](CLI_REFERENCE.md) · [Troubleshooting](TROUBLESHOOTING.md)
