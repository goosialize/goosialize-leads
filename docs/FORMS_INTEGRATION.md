# Grav Forms integration

Goosialize Leads can capture an explicitly approved native Grav Form through
the `goosialize_leads_capture` process action. For the shortest setup path,
start with [Quick Start](QUICK_START.md).

> **Audience:** Site builders integrating native Grav Forms.

## Complete native Form example

Create a page such as `user/pages/contact/form.md` with this frontmatter:

```yaml
---
title: Contact
form:
  name: contact
  xhr_submit: false
  fields:
    full_name:
      type: text
      label: Name
      validate:
        required: true
    email:
      type: email
      label: Email
      validate:
        required: true
    message:
      type: textarea
      label: Message
      validate:
        required: true
    consent.granted:
      type: checkbox
      label: I agree to the privacy notice
      value: true
      validate:
        type: bool
        required: true
  buttons:
    submit:
      type: submit
      value: Send
  process:
    goosialize_leads_capture: true
---
```

`consent.granted` is an exact dotted Grav field name. Grav renders dotted
names as nested submitted arrays, producing the required affirmative consent
shape:

```yaml
consent:
  granted: true
```

The capture contract requires a name. `full_name` is used above; alternatively,
provide both `first_name` and `last_name`. It also requires at least one contact
method (`email` or `phone`) and at least one inquiry value (`message` or
`resource_id`).

## Approve the form

Enable Forms capture and allowlist the exact native form name:

```yaml
forms:
  enabled: true
  forms:
    - contact
  source: website
  locale: null
  consent_version: privacy-v1
  success_redirect: /thank-you
```

In Admin2, these settings are on **Plugins > Goosialize Leads > Forms**. The
form name returned by Grav must exactly match an allowlisted value.

## Stored context

- **Source** is the configured `forms.source`; this example stores `website`.
- **Form / Resource** is the native form name unless a submitted `resource_id`
  is present; this example displays `contact`.
- **Status** starts as `new`.
- **State** starts as `active`.
- **Consent version** is trusted plugin configuration, not a visitor-supplied
  override.

These values appear in the [Admin Guide](ADMIN_GUIDE.md) workspace.

## Success behavior

On successful capture, the adapter:

- marks the native Form result successful;
- uses the translated Goosialize Leads success message;
- applies a `303` redirect to the configured local `success_redirect`;
- stops later Form process actions from running.

Put `goosialize_leads_capture` at the intended point in the Form process list
and do not rely on later actions running after success.

## Empty and configured key rings

Trusted Grav Forms capture supports the shipped empty idempotency key ring. In
that mode, capture stores no keyed idempotency digest. This is the clean-install
default and does not make the public JSON endpoint available.

When a valid key ring is configured, Forms capture derives replay protection
from the native form name and submission identifier. An exact replay returns
the existing Lead without rewriting its primary record; conflicting reuse
fails closed.

Public JSON and public plugin capability capture are separate keyed surfaces
and require an active key version and matching secret. See
[Configuration](CONFIGURATION.md#idempotency-key-ring) and
[Security](SECURITY.md).

## XHR behavior

XHR submission is unsupported by the committed Forms adapter. A Form with
`xhr_submit: true` fails the Goosialize Leads capture action. Keep it false or
omit it.

## Newsletter registration example

A newsletter form uses the same contract. Configure:

```yaml
forms:
  enabled: true
  forms:
    - newsletter
  source: newsletter
  locale: null
  consent_version: newsletter-v1
  success_redirect: /newsletter/thanks
```

Name the native form `newsletter`. Reuse the complete example above and, for a
registration form without a message field, submit a hidden bounded
`resource_id` such as `newsletter`. The resulting Admin2 values are Source
`newsletter`, Form / Resource `newsletter`, initial Status `new`, and initial
State `active`.

## Failure behavior

An ineligible form is ignored. Invalid submitted data, invalid configuration,
an idempotency conflict, unsupported XHR submission or storage failure stops
the action without exposing stored values, filesystem paths, secrets or raw
exceptions.

See [Troubleshooting](TROUBLESHOOTING.md#form-submits-but-no-lead-appears) for a
symptom-based checklist.

---

## Navigation

[← Back to README](../README.md) · [Previous: Admin Guide](ADMIN_GUIDE.md) ·
[Next: CSV Export →](CSV_EXPORT.md)

Related documentation: [Quick Start](QUICK_START.md) ·
[Configuration](CONFIGURATION.md#grav-forms) ·
[Troubleshooting](TROUBLESHOOTING.md#form-submits-but-no-lead-appears)
