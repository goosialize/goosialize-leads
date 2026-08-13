# Notification delivery

Notification delivery is an optional facility built on top of the durable notification
outbox.

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

Deliver pending notifications with:

```bash
php bin/plugin goosialize-leads deliver-notifications
```

Limit the batch size:

```bash
php bin/plugin goosialize-leads deliver-notifications --limit=10
```

Process only retry-eligible events:

```bash
php bin/plugin goosialize-leads deliver-notifications --retries-only
```

## Delivery outcomes

Each attempt is recorded in durable notification state.

An event can become:

- delivered;
- pending;
- retry-eligible;
- dead-lettered;
- duplicate-risk requiring operator reconciliation.

## Failure containment

Delivery failure must not:

- delete the primary Lead record;
- expose SMTP or provider credentials;
- expose recipient configuration;
- expose filesystem paths;
- suppress a required operator action.
