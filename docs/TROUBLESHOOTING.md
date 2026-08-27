# Troubleshooting

> **Audience:** Administrators resolving visible symptoms. Advanced operators
> should follow the linked operational guides and exact [CLI reference](CLI_REFERENCE.md).

This guide owns symptom-to-action resolution. The [FAQ](FAQ.md) owns conceptual
answers; feature guides own normative configuration and command details.

## Plugin is not discovered

### Likely cause

The folder name, package contents, effective YAML or platform requirements are
incorrect.

### Check

Confirm the directory is exactly `user/plugins/goosialize-leads`, the three
root plugin files exist, `enabled: true` is effective, and Grav/PHP meet the
[Installation](INSTALLATION.md) requirements.

### Fix

Install the complete stable package through a supported installation method.
Do not rename the directory or combine files from different releases.

## Leads menu is missing

### Likely cause

The Lead index or a dependency is disabled, or the account lacks read access.

### Check

Confirm `admin2_index.enabled` is true, API and Admin2 are enabled, and the
signed-in account has API access plus `api.goosialize_leads.read`.

### Fix

Correct configuration or the role, then start a fresh Admin2 session. See
[Admin Guide](ADMIN_GUIDE.md) and [Permissions](PERMISSIONS.md).

## API dependency is missing

### Likely cause

The declared API plugin dependency was not installed or enabled.

### Check

Review the installed plugin list and dependency status.

### Fix

Install or enable API through the supported GPM workflow, then reload Admin2.
Do not patch API plugin files.

## Admin2 dependency is missing

### Likely cause

The declared Admin2 dependency is unavailable or disabled.

### Check

Verify compatible Admin2 is installed and enabled.

### Fix

Install or enable Admin2 and sign in again. The Public JSON API is not a
replacement for Admin2 administration.

## Email dependency is missing

### Likely cause

Grav Email is unavailable or not provider-configured.

### Check

Review the Email plugin state and its provider configuration.

### Fix

Install and configure Email before enabling Notification delivery. Lead
capture remains independent of delivery; see [Notification delivery](NOTIFICATION_DELIVERY.md).

## Form submits but no Lead appears

### Likely cause

Forms capture is disabled, the form name is not allowlisted, the exact process
action is absent, the submission uses XHR, or required data is invalid.

### Check

Compare the form and plugin settings with the copy/paste-safe
[Grav Forms integration](FORMS_INTEGRATION.md). Confirm the exact action is
`goosialize_leads_capture`, consent is nested, and `xhr_submit` is false or
omitted.

### Fix

Correct the eligible form or submitted fields, use synthetic data, submit
again, and inspect the latest bounded Admin2 collection.

## Permission denied

### Likely cause

The account lacks the permission for the requested control.

### Check

Compare the action with the [permission catalogue](PERMISSIONS.md): read for
the workspace, write for Edit/Status/State/Restore, delete for Delete, read plus
export for CSV Export, and operations for Notification operations.

### Fix

Grant only the required permission and refresh the Admin2 session. Every API
route rechecks the ACL server-side.

## CSV Export is unavailable

### Likely cause

CSV Export is disabled, permissions are incomplete, no validated Lead is in the
latest-100 boundary, or the response-size bound was reached.

### Check

Confirm the [CSV Export](CSV_EXPORT.md) enablement and both read/export
permissions.

### Fix

Enable the feature, correct the role, then retry with an appropriate bounded
collection. Changing View does not widen the backend boundary.

## Source or Form / Resource is blank

### Likely cause

The validated projection contains no value for that context field.

### Check

Identify the capture entry point and its trusted Source/form/resource mapping.

### Fix

Correct future capture configuration. Do not edit immutable primary Lead
records directly.

## Source or Form / Resource options seem incomplete

### Likely cause

Dropdown choices come from distinct non-empty values in the latest 100 valid
primary Leads, not all historical storage.

### Check

Reset filters and reload the workspace.

### Fix

Use available bounded values. Older-only values cannot appear until a future
server-side history/index feature exists; see the [FAQ](FAQ.md).

## Public JSON API key configuration is missing

### Likely cause

The anonymous endpoint has no valid active key version and matching nested
32-byte Base64 secret.

### Check

Review the [Idempotency key-ring configuration](CONFIGURATION.md#idempotency-key-ring),
Public JSON API enablement and exact Origin allowlist. A masked password field
may represent a stored redacted secret.

### Fix

Supply the security-sensitive key configuration through the deployment's
secret process. Do not overwrite a masked value without a rotation plan.

## Custom API route mismatch

### Likely cause

The client assumes `/api/v1` while the API plugin uses a custom route or
version prefix.

### Check

Read the effective API plugin `route` and `version_prefix`.

### Fix

Build the URL as documented in [endpoint construction](JSON_API_INTEGRATION.md#endpoint-construction),
then append `/goosialize-leads/capture`. Preserve the exact allowed Origin.

## Public JSON request is rejected

### Likely cause

Headers, Origin, JSON, Idempotency, validation, size or rate limits failed.

### Check

Use the [status code table](JSON_API_INTEGRATION.md#status-code-reference) and
stable response code. Do not expect an Admin2 token to fix an anonymous
endpoint request.

### Fix

Correct only the indicated client contract: JSON headers/body, exact Origin,
one valid Idempotency key, accepted fields, body size, or Retry-After delay.

## Duplicate and Idempotency behavior

### Likely cause

An Idempotency key was replayed with the same or a conflicting canonical
payload.

### Check

An exact replay returns the existing Lead; conflicting reuse returns `409`.

### Fix

Reuse a key only for the same logical submission. Generate a new opaque key for
a new submission; see [Public JSON API Idempotency](JSON_API_INTEGRATION.md#idempotency).

## Legacy flat secret upgrade or migration fails

### Likely cause

The PHP/web user cannot persist the legacy scalar key in the secure nested
password form.

### Check

Verify write access to the base and any existing environment-specific plugin
configuration. Review only the non-sensitive migration error code.

### Fix

Repair ownership/permissions and let the idempotent migration retry. Do not
rotate or re-enter the key unless an explicit rotation is planned; see
[Upgrade](UPGRADE.md).

## Notification is not delivered

### Likely cause

Email, outbox, delivery, recipient/sender configuration or eligible state is
incomplete.

### Check

Follow [Notification delivery](NOTIFICATION_DELIVERY.md), inspect bounded
[Operational status](OPERATIONAL_STATUS.md), and consult provider logs.

### Fix

Correct setup, then run a small bounded manual delivery using the exact
[CLI reference](CLI_REFERENCE.md#deliver-notifications). Successful capture
does not itself guarantee delivery.

## Retries or scheduled delivery do not run

### Likely cause

Retry eligibility, attempt bounds, Scheduler gates or the host runner prevent
execution.

### Check

Inspect [Retry and Dead Letter](RETRY_DEAD_LETTER.md) state. For automation,
verify the job is registered and the host Grav scheduler runner is active.

### Fix

Correct the relevant enablement/state; do not bypass Dead letter or
duplicate-risk protection. Follow [Scheduler](SCHEDULER.md).

## Revision conflict or duplicate-risk state

### Likely cause

Another Lead update won, or a Notification attempt ended ambiguously.

### Check

For a Lead, reload and review current Status/State. For a Notification, inspect
the exact event and revision through [Operational status](OPERATIONAL_STATUS.md).

### Fix

Retry a still-appropriate Lead edit. Treat duplicate risk as advanced/high-risk
and follow [Reconciliation](RECONCILIATION.md); stale revisions correctly fail
closed.

## Safe support information

### Likely cause

Support cannot reproduce the symptom from a safe, bounded report.

### Check

Collect plugin, Grav, Admin2 and PHP versions, a redacted reproduction, the
affected entry point, and the stable error category.

### Fix

Share only that bounded material. Never include real Lead data, secrets, mail
credentials, recipients, production paths or raw exception traces.

---

## Navigation

[← Back to README](../README.md) ·
[Previous: Operational status](OPERATIONAL_STATUS.md) · [Next: README →](../README.md)

Related documentation: [FAQ](FAQ.md) · [Configuration](CONFIGURATION.md) ·
[Permissions](PERMISSIONS.md) · [CLI reference](CLI_REFERENCE.md)
