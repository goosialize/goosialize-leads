# Security

Goosialize Leads 1.0.0 uses fail-closed, bounded and secret-aware contracts across capture, storage, Admin2, CSV export and notification delivery.

## Security boundary

The plugin is responsible for:

- validating and normalizing capture input;
- enforcing idempotency;
- persisting Lead and notification state under contained runtime paths;
- enforcing bounded Admin2 read and export operations;
- enforcing origin, body-size, JSON-depth and rate-limit bounds;
- redacting secrets and sensitive operational details;

- requiring explicit operator intent for reconciliation.

The host environment remains responsible for:

- TlS and reverse-proxy configuration;
- Grav, Admin2 and PHP patching;
- filesystem ownership and permissions;
- backups and disaster recovery;
- mail-provider and SMTP security;
- shell, CLI and scheduler-runner access controls.

## Secret material

Sensitive configuration can include:

- idempotency keys;
- mail-provider or SMTP credentials;
- notification recipients;
- sender addresses;
- environment-specific integration values.

Do not:

- commit secrets to version control;
- include secrets in packages or support archives;
- log secrets or raw request bodies;
- expose configuration files through public web routes.

## Idempotency

Keyed public API and public capability capture require:

- an active positive key version;
- a matching secret key;
- a valid bounded idempotency key.

An exact replay returns the existing Lead without rewriting it.

Reusing the same idempotency key with a conflicting payload fails closed.

## Public JSON API

The public API is disabled by default and exposes only:

```http
POST <configured API route>/<configured version prefix>/goosialize-leads/capture
```

When enabled, it enforces:

- exact canonical Origin matching;
- no wildcard Origins;
- a 16,384-byte raw-body bound;
- a JSON depth of 4;
- 10 requests per 60-second window;
- explicit idempotency;
- stable error responses;
- redacted failure output.

The API must not reflect an unapproved Origin in `Access-Control-Allow-Origin`.

## Grav Forms

Only explicitly configured forms can use the `goosialize_leads_capture` process action.

A form name not present in the allowlist must fail closed.

## Filesystem containment

Runtime data is contained under:

```text
user/data/goosialize-leads/v1
```

The plugin validates:

- resolved path containment;
- file type;
- opened-file identity;
- bounded record size;
- record format and version.

Symlinks, unsafe paths, unexpected file types and identity mismatches must fail closed.

## Admin2 and ACL

The version 1.0.0 permissions are:

```text
api.goosialize_leads.read
api.goosialize_leads.write
api.goosialize_leads.delete
api.goosialize_leads.export
api.goosialize_leads.operations
```

Permissions must be granted according to least privilege.

CSV export requires both read and export.

Write permits Edit, status, Active/Inactive and Restore. Delete is separately
gated. The operations permission implies none of those capabilities.

Primary Lead records are immutable. Workflow metadata is bounded, canonical,
permission-restricted and revision-checked. Exact-ID lookup validates the ID,
directory topology, containment and opened-file identity; it never accepts a
caller-supplied path.

## CSV safety

CSV values beginning with spreadsheet formula prefixes are escaped before serialization.

CSV responses are bounded by the configured maximum response size.

## Notification delivery

Notification delivery requires Grav Email, valid recipients, a valid sender and explicit enablement.

Delivery state must not expose:

- SMTP or provider credentials;
- configured recipient lists;
- raw exceptions;
- filesystem paths;
- unbounded Lead payloads.

## Retry and reconciliation

Retry processing respects:

- maximum attempts;
- durable state validation;
- duplicate-risk protection;
- dead-letter state;
- explicit reconciliation requirements.

Reconciliation requires:

- an event identifier;
- the exact current revision;
- a supported action;
- explicit `--yes` confirmation.

A stale revision fails closed and does not mutate durable state.

## Logging and error handling

Production logs and error responses must not include:

- raw Lead payloads;
- secret keys;
- SMTP or provider credentials;
- configured recipient lists;
- filesystem paths;
- raw exception traces.

Use stable error categories and bounded operational details.

## Deployment checklist

Before enabling a capture or delivery surface:

1. verify the Grav, Admin2 and PHP versions;
2. verify filesystem ownership and permissions;
3. verify that secrets are not in version control;
4. verify Origin allowlists and rate limits;
5. verify Admin2 ACL roles;
6. verify email delivery and scheduler configuration;
7. run integration and package tests;
8. complete manual browser acceptance.

## Security reporting

Security reports should include:

- the affected plugin version;
- Grav, Admin2 and PHP versions;
- a redacted reproduction procedure;
- the affected entry point;
- the observed failure category.

Do not include real Lead data, secrets, credentials, recipient lists or filesystem paths in a security report.
