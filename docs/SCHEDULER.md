# Scheduler

The notification scheduler registers a bounded Grav scheduler job for delivering
pending notifications.

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

Version 1.0.0 enforces:

- a frequency of 5 minutes;
- a batch limit between 1 and 50;
- a fixed timeout of 300 seconds.

## Registered job

The job name is:

```text
goosialize-leads-notification-delivery
```

The registered command is:

```bash
bin/plugin goosialize-leads deliver-notifications --limit=10
```

The configured `batch_limit` is projected into the `--limit` argument.

## Execution behaviour

Each scheduled run attempts a bounded batch of eligible notification events.

The job does not bypass:

- delivery enablement;
- retry eligibility;
- maximum attempt bounds;
- dead-letter state;
- duplicate-risk reconciliation requirements.

## Disablement

When scheduling is disabled, the plugin must not register the delivery job.

Disabling the scheduler does not delete:

- primary Lead records;
- notification outbox events;
- delivery state;
- retry state;
- dead-letter state.

## Operational check

Inspect notification state with:

```bash
php bin/plugin goosialize-leads notification-status
```

Then confirm that the Grav scheduler runner is active in the host environment.
