# Manual browser acceptance checklist

This checklist records the mandatory human browser verification for Goosialize Leads 1.0.0.

## Release gate

Current status:

```text
PASS
```

Final human browser acceptance has passed. The annotated `v1.0.0` tag remains
unauthorized until repository alignment, deterministic package verification,
staged-diff review, the milestone commit and explicit release authorization.

Automated tests, package checksums and clean-container verification do not replace this browser acceptance.

## Test environment

Record the environment used for acceptance:

```text
Date:
Tester:
Grav version:
Admin2 version:
PHP version:
Browser:
Plugin package SHA-256:
Site URL:
```

Release target:

- Grav CMS `>=2.0.12 <2.1.0`
- Admin2 `2.0.15`
- PHP `^8.3`
- packaged Goosialize Leads `1.0.0`

Use synthetic Lead data only.

## Installation and discovery

- [ ] Install the deterministic `goosialize-leads-1.0.0.zip` package in a clean supported Grav environment.
- [ ] Confirm the plugin is discovered as `goosialize-leads`.
- [ ] Confirm the native Admin2 configuration page opens without PHP or browser-console errors.
- [ ] Confirm the displayed plugin version is `1.0.0`.

## Native Admin2 configuration

- [ ] Confirm configuration uses native Admin2 form components.
- [ ] Confirm dependent fields appear only when their parent facility is enabled.
- [ ] Confirm disabled facilities hide irrelevant dependent controls.
- [ ] Save valid configuration and confirm it persists after reload.
- [ ] Submit invalid bounded values and confirm validation fails closed.
- [ ] Confirm secrets are not exposed in page source, console output or validation messages.

## Grav Forms capture

- [ ] Confirm an unconfigured form cannot capture a Lead.
- [ ] Enable capture for one exact synthetic form name.
- [ ] Submit a valid synthetic Lead and confirm one canonical record is created.
- [ ] Repeat the idempotent submission and confirm the existing Lead is returned without rewrite.
- [ ] Reuse the key with a conflicting payload and confirm the request fails closed.
- [ ] Confirm the success redirect occurs only after successful capture.

## Public JSON capture

- [ ] Keep the API disabled and confirm capture is rejected.
- [ ] Enable it for one exact canonical Origin.
- [ ] Submit a valid request to `POST /api/v1/goosialize-leads/capture`.
- [ ] Confirm stable JSON and one stored Lead.
- [ ] Confirm exact replay returns the existing Lead.
- [ ] Confirm conflicting replay returns the idempotency-conflict response.
- [ ] Confirm an unapproved Origin returns `403`.
- [ ] Confirm invalid JSON or required fields return `400`.
- [ ] Confirm the 16,384-byte body bound and JSON depth 4 are enforced.
- [ ] Confirm 10 requests per 60 seconds are enforced.
- [ ] Confirm an unapproved Origin is not reflected in `Access-Control-Allow-Origin`.
- [ ] Confirm failures expose no secrets, filesystem paths or raw exceptions.

## Public capability discovery

- [ ] Confirm discovery exposes `goosialize-leads.public-capture.v1`.
- [ ] Confirm the capability identifier is `goosialize-leads.capture`.
- [ ] Confirm contract version `1` can be requested.
- [ ] Confirm discovery does not expose internal classes, Admin2 implementation details or runtime paths.

## Admin2 Lead index

Final accepted Admin2 checks:

- [x] Source options repopulate after native Admin2 hydration.
- [x] Form / Resource options and exact filtering work.
- [x] Status | Source | State | Form / Resource render in one desktop row.
- [x] Sort/View match the accepted Pages toolbar contract.
- [x] View 10 renders working multi-page navigation.
- [x] View 100 hides unnecessary single-page navigation.
- [x] Inline Edit and capability-aware state actions work.

- [ ] Enable the index and grant `api.goosialize_leads.read`.
- [ ] Confirm the latest-100 Lead workspace opens and synthetic values render correctly.
- [ ] Confirm configured timezone handling.
- [ ] Confirm a user without read permission is denied.
- [ ] Confirm a read-only user sees no Edit, state, Delete or Restore controls.
- [ ] Confirm a write-capable user sees Edit and Active/Inactive controls.
- [ ] Confirm only a delete-capable user sees Delete.
- [ ] Confirm a write-capable user can Restore a deleted Lead and status is preserved.
- [ ] Confirm a deleted Lead cannot be edited or mutated outside Restore.
- [ ] Confirm stale optimistic revisions fail and reload preserves the winning state.
- [ ] Confirm a valid Lead outside the latest-100 index opens by exact ID.
- [ ] Confirm the fully authorized action header and rows remain `Edit | Status | Delete`.

## CSV export

- [x] Browser download uses `goosialize-leads-YYYY-MM-DD-HHmm.csv`.

- [ ] Enable export and grant both read and export permissions.
- [ ] Export synthetic records and confirm a valid CSV download.
- [ ] Confirm spreadsheet-formula prefixes are escaped.
- [ ] Confirm the configured maximum response size is enforced.
- [ ] Confirm a user missing either permission is denied.
- [ ] Confirm sidecars and notification state are not exported as Leads.

## Notification delivery

- [ ] Keep outbox and delivery disabled and confirm no delivery occurs.
- [ ] Enable outbox, delivery and valid Grav Email routing with synthetic recipients.
- [ ] Capture a synthetic Lead and confirm one bounded outbox event.
- [ ] Run bounded manual delivery and confirm durable delivered state.
- [ ] Confirm a delivered event is not sent again automatically.
- [ ] Confirm output redacts recipients, credentials, Lead payloads and filesystem paths.
- [ ] Confirm invalid routing or unavailable Grav Email fails closed.

## Retry and dead-letter handling

- [ ] Enable retry processing with the documented v1 bounds.
- [ ] Produce a controlled synthetic delivery failure.
- [ ] Confirm the event becomes retry-eligible.
- [ ] Run `deliver-notifications --retries-only --limit=10`.
- [ ] Confirm attempts remain bounded by five.
- [ ] Confirm terminal failure becomes dead-lettered.
- [ ] Confirm dead-lettered events are not retried automatically.
- [ ] Confirm ambiguous delivery becomes duplicate-risk and is not redelivered automatically.

## Scheduler registration

- [ ] Keep scheduling disabled and confirm the job is absent.
- [ ] Enable frequency 5, batch limit 10 and timeout 300.
- [ ] Confirm `goosialize-leads-notification-delivery` is registered.
- [ ] Confirm its command includes `bin/plugin goosialize-leads deliver-notifications --limit=10`.
- [ ] Run the host scheduler and confirm bounded processing.
- [ ] Disable scheduling and confirm the job is removed without deleting Lead or delivery state.

## Operational status and reconciliation

- [ ] Run `notification-status --limit=10`.
- [ ] Run `notification-status --limit=10 --json`.
- [ ] Confirm both outputs are bounded and redacted.
- [ ] Confirm pending, retry-eligible, delivered, dead-letter and duplicate-risk states render correctly.
- [ ] Reconcile a synthetic event with `confirm-delivered --yes`.
- [ ] Reconcile a duplicate-risk event with `retry-duplicate-risk --yes`.
- [ ] Reconcile a synthetic event with `dead-letter --yes`.
- [ ] Confirm a stale revision fails closed without mutating durable state.
- [ ] Confirm reconciliation requires explicit `--yes`.

## ACL separation

- [ ] Confirm `api.goosialize_leads.read` grants read-only index access.
- [ ] Confirm `api.goosialize_leads.write` grants Edit, status, state and Restore.
- [ ] Confirm `api.goosialize_leads.delete` independently gates Delete.
- [ ] Confirm CSV export additionally requires `api.goosialize_leads.export`.
- [ ] Confirm operational Admin2 actions require `api.goosialize_leads.operations`.
- [ ] Confirm least-privilege users cannot access unrelated facilities.
- [ ] Confirm UI-hidden controls remain forbidden at their server endpoints.

## Data retention and reinstall

- [ ] Disable the plugin and confirm runtime data remains under `user/data/goosialize-leads/`.
- [ ] Remove only the plugin package and confirm runtime data is retained.
- [ ] Reinstall the same compatible package.
- [ ] Confirm retained synthetic Lead and notification state remain readable.
- [ ] Confirm no automatic purge occurs.
- [ ] Confirm permanent runtime-data deletion remains a separate explicit administrative action.

## Acceptance record

Complete only after every required item passes:

```text
Status: PASS
Tester: Human acceptance completed; identity retained outside this repository
Completed at: 2026-08-13
Package SHA-256: Pending final deterministic package build
Notes: Product acceptance passed. Tagging/publication remain separately gated and unauthorized.
```

Allowed final status values are:

```text
PENDING
PASS
FAIL
```

When any required item fails, record `FAIL`, preserve the evidence and do not create the release tag.

Product acceptance is `PASS`. Record the final deterministic package SHA-256
after repository alignment, then complete staged-diff review and obtain
explicit authorization before tagging or publication.
