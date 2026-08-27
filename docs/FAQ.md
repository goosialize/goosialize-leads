# FAQ

## What is the difference between Status and State?

Status is the administrator workflow classification, such as the initial
`new` status. State is the lifecycle condition: Active, Inactive or Deleted.
They can be filtered and managed independently, subject to permissions.

## What do Source and Form / Resource mean?

Source identifies the trusted capture channel. Grav Forms use the configured
`forms.source`; the Public JSON API uses `public_api`. Form / Resource identifies
the native Grav form name or an integration-supplied resource identifier.

## Why does "All" show at most 100 Leads?

The Admin2 backend intentionally loads only the latest 100 validated primary
Lead records. View: All displays all filtered rows within that bounded loaded
collection, not every historical record in storage.

## Why is Source not in the dropdown?

Source choices come only from non-empty values in the latest 100 validated
Leads loaded by Admin2. Reset filters and reload. A Source found only in older
history is outside the current bounded workspace.

## Why is Form / Resource not in the dropdown?

The dropdown uses distinct non-empty Form / Resource values in the same
latest-100 collection. Blank context and values present only in older history
are not offered.

## Does Delete erase Lead data?

No. Delete records a reversible `deleted` metadata state. The immutable
primary Lead record remains stored. Permanent runtime-data removal is a
separate, explicit administrative operation.

## Why can I Restore but not edit a deleted Lead?

Deleted Leads are locked against ordinary edits, Status changes,
Active/Inactive changes and repeated Delete. Restore is the single allowed
transition and returns the Lead to an editable state while preserving Status.

## Do Grav Forms require an idempotency secret?

No. Trusted native Grav Forms capture works with the shipped empty key ring.
In that mode, the Lead is stored without a keyed idempotency digest. You may
configure a key ring when Forms replay protection is required.

## Can I use Goosialize Leads without the Public JSON API?

Yes. Grav Forms capture, Admin2 management and CSV Export do not require the
Public JSON API. Keep it disabled unless an external HTTP client needs it.

## Can Goosialize Leads replace my newsletter provider?

No. It can capture a newsletter registration as a Lead and optionally send an
administrator Notification. List management, confirmed opt-in, campaigns,
suppression and unsubscribe processing remain the newsletter provider's job.

## Why does Public JSON API capture require key configuration?

The anonymous public endpoint must fail closed unless an active key version
and matching secret are configured. The key ring protects its idempotency
records. Origin allowlisting and rate limiting are additional protections, not
replacements for the key ring.

## What happens if the same submission is sent twice?

With keyed capture, an exact retry using the same idempotency key and canonical
payload returns the existing Lead without rewriting it. Reusing that key for a
different payload returns a conflict. Keyless trusted Forms capture does not
store a keyed replay digest.

## Why is a saved secret hidden or redacted?

Idempotency secrets use nested password configuration and are removed from API
configuration responses. A blank or masked Admin2 password field does not prove
that the stored secret is absent. Do not replace it unless you intend to rotate
the key and have planned the effect on replay protection.

## Does uninstalling delete Lead data?

No. Ordinary plugin disablement, package replacement or removal must not
automatically delete `user/data/goosialize-leads/`. Follow
[Uninstall and data retention](UNINSTALL_DATA_RETENTION.md) before any
irreversible deletion.

## Does successful capture guarantee notification delivery?

No. Capture persistence and notification delivery are separate. A Lead can be
successfully stored while notifications are disabled, pending, retrying,
in Dead letter state or unavailable. Use [Operational status](OPERATIONAL_STATUS.md)
and, when necessary, your mail provider's delivery logs.

See the [Admin Guide](ADMIN_GUIDE.md), [Configuration](CONFIGURATION.md) and
[Troubleshooting](TROUBLESHOOTING.md) for step-by-step guidance.

---

## Navigation

[← Back to README](../README.md) · [Previous: CSV Export](CSV_EXPORT.md) ·
[Next: Configuration →](CONFIGURATION.md)

Related documentation: [Admin Guide](ADMIN_GUIDE.md) ·
[Grav Forms integration](FORMS_INTEGRATION.md) ·
[Uninstall and data retention](UNINSTALL_DATA_RETENTION.md)
