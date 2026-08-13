# Grav Forms integration

## Process action

Add the following action to an approved Grav Form:

```yaml
process:
  goosialize_leads_capture: true
```

The plugin listens to the native `onFormProcessed` event and accepts only the
exact action:

```text
goosialize_leads_capture
```

## Required configuration

Forms capture must be enabled and the form name must be explicitly listed:

```yaml
plugins:
  goosialize-leads:
    forms:
      enabled: true
      forms:
        - contact
```

## Capture pipeline

The Forms adapter passes data through the same shared capture service used by
the public JSON API and the plugin integration capability.

The service performs:

- validation;
- normalization;
- consent validation;
- idempotency processing;
- contained filesystem persistence;
- optional notification-outbox publication.

## Idempotency

Exact replay returns the existing Lead without rewriting its primary record.

A conflicting payload for the same idempotency key fails closed.

## Success redirect

Successful capture applies the configured local redirect:

```yaml
forms:
  success_redirect: /
```

## Failure behaviour

Invalid configuration, validation failure, idempotency conflict or storage
failure must not expose:

- filesystem paths;
- raw exceptions;
- idempotency secrets;
- stored Lead values.
