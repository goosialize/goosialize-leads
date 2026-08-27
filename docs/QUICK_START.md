# Quick Start

This guide captures a first Lead with a native Grav Form and opens it in
Admin2.

> **Audience:** First-time Grav administrators and site builders. Allow about
> 5–8 minutes.

## Requirements

- Grav CMS `>=2.0.12`
- PHP `>=8.3` with `ext-intl`
- Grav API plugin
- Grav Admin2 plugin
- Grav Email plugin
- Grav Form plugin for this Forms workflow

API, Admin2 and Email are required package dependencies. Email delivery is
optional until notifications are enabled.

## Install

When the plugin is available in your configured GPM catalogue, run from the
Grav root:

```bash
php bin/gpm install goosialize-leads
```

As a fallback, download `goosialize-leads-<version>.zip` from
[GitHub Releases](https://github.com/goosialize/goosialize-leads/releases) and
run:

```bash
php bin/gpm direct-install -y /absolute/path/goosialize-leads-<version>.zip
```

The resulting folder must be `user/plugins/goosialize-leads`. See
[Installation](INSTALLATION.md) for clean-install verification.

## Enable

The plugin itself is enabled by default. In Admin2, open
**Plugins > Goosialize Leads** and confirm **Enabled** is on.

For this first Lead:

1. Confirm the Form plugin is installed and enabled.
2. Open the **Forms** tab in Goosialize Leads settings.
3. Enable Forms capture.
4. Select the native form named `contact`.
5. Keep Source as `website` and set the success redirect to `/thank-you` or
   another existing local path.

The Admin2 Lead index is enabled in the shipped default configuration. Access
still requires the `api.goosialize_leads.read` permission.

## Capture your first Lead

Create a page such as `user/pages/contact/form.md` with this frontmatter. The
field name `consent.granted` is intentional: Grav converts the dotted name to
nested submitted data required by the capture contract.

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

The form name must exactly match the form selected in plugin configuration.
Goosialize Leads currently rejects XHR Form submissions, so keep
`xhr_submit: false` or omit the option.

Trusted Grav Forms capture works with the shipped empty idempotency key ring.
Do not create a secret merely to complete this workflow.

## Submit

Open the form page as a visitor, enter synthetic test details, accept the
privacy notice and submit. On success, Goosialize Leads marks the Form result
successful and sends a `303` redirect to the configured local success path.

If no Lead appears, use [Forms integration](FORMS_INTEGRATION.md) and
[Troubleshooting](TROUBLESHOOTING.md).

## Open Leads in Admin2

Sign in to Admin2 and select **Leads** in the main sidebar. The menu appears
only when the Lead index is enabled and your account has
`api.goosialize_leads.read` (or equivalent superuser access).

![First Leads in the Admin2 workspace](images/admin2-leads-workspace.png)

*Synthetic test Leads in the native workspace. Your first submission appears
with its trusted Source, Form / Resource, initial Status and Active State.*

## Understand the row

- **Source** identifies the configured capture channel. This example stores
  `website` from `forms.source`.
- **Form / Resource** identifies the native form or integration resource. This
  example stores the native form name `contact`.
- **Status** is the administrator workflow classification. A captured Lead
  starts as `new`.
- **State** controls whether the Lead is Active, Inactive or Deleted. A
  captured Lead starts as `active`.

## Manage

Use Search and the Status, Source, State, Form / Resource and creation-date
filters to narrow the loaded Leads. Depending on your permissions, you can
open inline Edit, change Status, switch Active/Inactive, Delete a Lead
reversibly, or Restore a deleted Lead.

See the [Admin Guide](ADMIN_GUIDE.md) for the complete workspace behavior.

## Export

Enable `admin2_csv_export.enabled`, then grant both
`api.goosialize_leads.read` and `api.goosialize_leads.export`. The **Export
CSV** action then downloads the same bounded Lead collection used by the
workspace. See [CSV Export](CSV_EXPORT.md).

## Next steps

- [Admin Guide](ADMIN_GUIDE.md)
- [Forms integration](FORMS_INTEGRATION.md)
- [Configuration](CONFIGURATION.md)
- [Public JSON API](JSON_API_INTEGRATION.md)
- [Notification delivery](NOTIFICATION_DELIVERY.md)
- [Troubleshooting](TROUBLESHOOTING.md)
- [FAQ](FAQ.md)

---

## Navigation

[← Back to README](../README.md) · [Previous: README](../README.md) ·
[Next: Installation →](INSTALLATION.md)

Related documentation: [Admin Guide](ADMIN_GUIDE.md) ·
[Grav Forms integration](FORMS_INTEGRATION.md) · [FAQ](FAQ.md)
