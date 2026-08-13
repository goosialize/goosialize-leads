# Goosialize Leads 1.0.0 release notes

Goosialize Leads 1.0.0 is the first public release of the standalone Grav 2 Lead capture, storage, Admin2 and notification delivery plugin.

## Supported environment

- Grav CMS `>=2.0.12 <2.1.0`
- Admin2 `2.0.15`
- PHP `^8.3`

## Core capabilities

- Canonical Lead storage under `user-data://goosialize-leads/v1`.
- Durable idempotency with active key version and secret keys.
- Exact replay protection and conflicting payload detection.
- Configured Grav Forms capture.
- Bounded public JSON capture.
- Public capability discovery for integrations.
- Native Admin2 latest-100 workspace with Edit, status, Active/Inactive,
  reversible Delete and Restore.
- Native Search, Status, Source, State, Form / Resource and creation-date
  filters. Source and Form / Resource vocabularies are derived dynamically
  from the bounded loaded collection and restored after Admin2 hydration.
- Four-select desktop filter row, native datetime row, Pages-style Sort/View,
  bounded client-side pagination and loaded/state footer summaries.
- Inline exact-ID Edit with optimistic revisions; exact-ID edit and mutation
  remain addressable outside the visible latest-100 index.
- Bounded Admin2 CSV export.
- Durable notification outbox and delivery state.
- Bounded manual and scheduled notification delivery.
- Retry, dead-letter and duplicate-risk state.
- Revision-safe operator reconciliation.
- Bounded human-readable and JSON operational status.

## Public capture contract

The public service identifier is:

```text
goosialize-leads.public-capture.v1
```

The capability identifier is:

```text
goosialize-leads.capture
```

Integrations must request contract version `1` and must not bind to internal classes, Admin2 implementation details or runtime filesystem paths.

## Public JSON endpoint

```http
POST /api/v1/goosialize-leads/capture
```

The endpoint is disabled by default and supports:

- exact canonical Origin allowlisting;
- a 16,384-byte raw-body bound;
- a JSON depth of 4;
- 10 requests per 60-second window;
- explicit idempotency;
- stable, redacted error responses.

## Admin2 permissions

Version 1.0.0 defines:

```text
api.goosialize_leads.read
api.goosialize_leads.write
api.goosialize_leads.delete
api.goosialize_leads.export
api.goosialize_leads.operations
```

CSV export requires both read and export permissions.
Authenticated capabilities are returned by the Lead index endpoint. Read-only
users see no mutation controls; server-side ACL remains authoritative.
CSV downloads use the server-generated
`goosialize-leads-YYYY-MM-DD-HHmm.csv` filename in the effective application
timezone without changing export contents or ACLs.

## Operational CLI

Version 1.0.0 provides:

```bash
php bin/plugin goosialize-leads deliver-notifications
php bin/plugin goosialize-leads notification-status
php bin/plugin goosialize-leads reconcile-notification EVENT_ID REVISION ACTION --yes
```

Supported reconciliation actions:

```text
confirm-delivered
retry-duplicate-risk
dead-letter
```

## Scheduled delivery

When enabled, the plugin registers the job:

```text
goosialize-leads-notification-delivery
```

The registered command is:

```bash
bin/plugin goosialize-leads deliver-notifications --limit=10
```

## Security and safety

- Optional capture, Admin2, CSV, notification and scheduling facilities are disabled by default.
- Storage paths, file types, opened-file identity, record size and record format are validated.
- Classic CSV spreadsheet-formula prefixes are escaped.
- Secrets, recipients, filesystem paths, raw Lead payloads and raw exceptions are redacted from operational output.
- Retry and reconciliation fail closed when state or revision validation fails.

## Data retention

Uninstalling, package replacement or disabling the plugin does not automatically delete:

```text
user/data/goosialize-leads/
```

Permanent runtime-data deletion is a separate, explicit and irreversible administrative action.

## Admin2 record integrity

Primary records remain immutable in nested `YYYY/MM` storage. Workflow changes
use separate metadata sidecars and optimistic revisions. Deleted Leads require
Restore and cannot be edited or otherwise mutated. A dedicated bounded exact-ID
lookup keeps valid older Leads editable after they leave the latest-100 index.

## Release verification

The release must pass before tagging:

- the full unit test suite;
- the full integration test suite;
- deterministic package build and checksum verification;
- clean Grav 2.0.12 plugin load;
- all release-readiness inventory and boundary checks;
- manual browser acceptance.

Manual browser acceptance is complete. Tagging and publication remain separate
operations requiring final repository/package review, the milestone commit and
explicit authorization.
