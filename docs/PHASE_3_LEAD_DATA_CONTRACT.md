# Phase 3 Lead Data Contract

Status: strict schema-v1 planning contract; not implemented.

## Canonical synthetic record

```json
{
  "schema_version": 1,
  "id": "0123456789abcdef0123456789abcdef",
  "created_at": "2026-07-27T10:20:30.123456Z",
  "updated_at": "2026-07-27T10:20:30.123456Z",
  "status": "new",
  "revision": 1,
  "source": "website",
  "form_name": "contact",
  "locale": "en",
  "consent": {
    "granted": true,
    "version": "privacy-2026-01",
    "captured_at": "2026-07-27T10:20:30.123456Z"
  },
  "idempotency": {
    "key_version": 1,
    "key_hash": "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa",
    "payload_fingerprint": "bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb"
  },
  "full_name": "Example Lead",
  "email": "lead@example.test",
  "phone": "+35700000000",
  "company": null,
  "message": "Please contact me.",
  "resource_id": "example-resource",
  "source_path": "/example",
  "campaign": {
    "utm_source": "example",
    "utm_medium": "example",
    "utm_campaign": "example",
    "utm_term": null,
    "utm_content": null
  }
}
```

Canonical serialization is UTF-8 JSON with exception-on-error, unescaped Unicode/slashes, the shown stable key order, and exactly one LF.

## Universal rules

Unknown request and record fields are rejected. Raw payloads, IP addresses, user agents, full URLs, query strings, arbitrary custom fields, first/last-name variants, product objects, consent prose, credentials, and secrets are never stored. JSON member names must be unique.

Submitted strings must be valid UTF-8, normalized to NFC, and measured after normalization. Outer horizontal whitespace is trimmed. Optional empty strings become `null`; required empty strings fail. C0/C1 controls and Unicode noncharacters are rejected except LF in `message`; CRLF/CR becomes LF. HTML is unsupported: tag-like markup is rejected and all future output must still be contextually escaped.

PII classes below are `DIRECT`, `INDIRECT`, `EVIDENCE`, and `NONE`.

## Complete field contract

| Field | Type/presence | Origin | Normalization and validation | Limit | PII | Mutability/default |
|---|---|---|---|---|---|---|
| `schema_version` | integer, required/non-null | Server | exactly `1` | integer | NONE | immutable, `1` |
| `id` | string, required/non-null | Server | 16 CSPRNG bytes as `^[0-9a-f]{32}$` | 32 bytes | NONE | immutable |
| `created_at` | string, required/non-null | Server clock | UTC RFC 3339, six fractional digits, `Z` | 27 bytes | NONE | immutable |
| `updated_at` | string, required/non-null | Server clock | same format/equal to created at capture | 27 bytes | NONE | Phase 3 immutable |
| `status` | string, required/non-null | Server | exactly `new` | 3 bytes | NONE | Phase 4 mutable; default `new` |
| `revision` | integer, required/non-null | Server | exactly `1` at capture | positive integer | NONE | Phase 4 mutable; `1` |
| `source` | string, required/non-null | Trusted config | lowercase slug `^[a-z0-9](?:[a-z0-9_-]{0,62}[a-z0-9])?$` | 64 bytes | INDIRECT | immutable; request override rejected |
| `form_name` | string, required/non-null | Trusted config | same slug rule | 64 bytes | INDIRECT | immutable; request override rejected |
| `locale` | string, required/nullable | Trusted context/config | configured allowlisted locale; request override rejected | 35 bytes | INDIRECT | immutable; configured value or `null` |
| `consent` | object, required/non-null | Mixed | exact children below | object | EVIDENCE | immutable |
| `consent.granted` | boolean, required/non-null | Input | literal `true`; no coercion | boolean | EVIDENCE | immutable; false/missing rejected |
| `consent.version` | string, required/non-null | Trusted config | NFC single-line configured slug | 64 code points/128 bytes | EVIDENCE | immutable |
| `consent.captured_at` | string, required/non-null | Server clock | equal to created time | 27 bytes | EVIDENCE | immutable |
| `idempotency` | object, required/non-null | Server | exact children below | object | INDIRECT | immutable |
| `idempotency.key_version` | integer, required/nullable | Trusted secret config | positive version identifying HMAC key; never secret material | integer | NONE | immutable; `null` without key |
| `idempotency.key_hash` | string, required/nullable | Server-derived | SHA-256 of valid header, lowercase hex | 64 bytes | INDIRECT | immutable; `null` without header |
| `idempotency.payload_fingerprint` | string, required/nullable | Server-derived | HMAC-SHA-256 of canonical normalized input, lowercase hex | 64 bytes | INDIRECT | immutable; `null` without header |
| `full_name` | string, required/nullable | Normalized input | NFC, trim/collapse horizontal whitespace, single-line, no markup | 200 code points/400 bytes | DIRECT | Phase 3 immutable; `null` |
| `email` | string, required/nullable | Normalized input | trim; approved ASCII email validator; preserve local-part case, lowercase domain | 254 bytes | DIRECT | Phase 3 immutable; `null` |
| `phone` | string, required/nullable | Normalized input | remove space/`-`/parentheses; result `+` and 8–15 digits | 16 bytes | DIRECT | Phase 3 immutable; `null`; no country guessing |
| `company` | string, required/nullable | Normalized input | NFC, trim/collapse, single-line, no markup | 200 code points/400 bytes | DIRECT | Phase 3 immutable; `null` |
| `message` | string, required/nullable | Normalized input | NFC, normalized LF, multiline, no markup | 4,000 code points/8,000 bytes | DIRECT | Phase 3 immutable; `null` |
| `resource_id` | string, required/nullable | Normalized input | lowercase slug, max 128; arbitrary product objects rejected | 128 bytes | INDIRECT | immutable; `null` |
| `source_path` | string, required/nullable | Normalized input | internal absolute path; no host/scheme/query/fragment, `.`/`..`, encoded separators or duplicate slash | 512 bytes | INDIRECT | immutable; `null` |
| `campaign` | object, required/nullable | Normalized input | exact children; all-null becomes `null` | object | INDIRECT | immutable; `null` |
| `campaign.utm_source` | string, required/nullable | Normalized input | NFC, trim, single-line | 100 code points/200 bytes | INDIRECT | immutable; `null` |
| `campaign.utm_medium` | string, required/nullable | Normalized input | same | 100 code points/200 bytes | INDIRECT | immutable; `null` |
| `campaign.utm_campaign` | string, required/nullable | Normalized input | same | 100 code points/200 bytes | INDIRECT | immutable; `null` |
| `campaign.utm_term` | string, required/nullable | Normalized input | same | 100 code points/200 bytes | INDIRECT | immutable; `null` |
| `campaign.utm_content` | string, required/nullable | Normalized input | same | 100 code points/200 bytes | INDIRECT | immutable; `null` |

Every canonical key is present; omitted optional input becomes JSON `null`. At least one of `email` or `phone` must be non-null. At least one of `message` or `resource_id` must be non-null. Every type, format, limit, unknown field, or server-field override violation rejects the whole request with a stable field code; no partial record is stored.

## Request shape and limits

The only submitted top-level keys are `consent`, `full_name`, `email`, `phone`, `company`, `message`, `resource_id`, `source_path`, and `campaign`. `consent` accepts only `{"granted":true}`. `campaign` accepts only `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, and `utm_content`.

Maximum raw request is 16,384 bytes; maximum canonical record is 32 KiB; depth is four including root. Duplicate JSON members fail `DUPLICATE_JSON_KEY`, not last-key-wins. Server fields, `first_name`, `last_name`, `product`, `page_url`, `custom_fields`, `ip`, and `user_agent` fail `UNKNOWN_FIELD`.

## ID, timestamp, and lifecycle

IDs have 128 random bits, lowercase filename-safe encoding, are not time-sortable, and are practically non-enumerable. Exclusive publication detects collision; five attempts precede `STORAGE_UNAVAILABLE`. No external ID package is required.

Timestamps use an injected server wall clock, UTC, and exactly six fractional digits. The clock is not monotonic and values can repeat; ordering uses timestamp plus ID. At capture, `created_at`, `updated_at`, and consent time are identical.

Phase 3 permits only creation as `new`. Status transitions, revision increments and `updated_at` changes are Phase 4+. Unknown schema versions fail closed and are never rewritten on read. Future migration must atomically preserve ID/created time and is Phase 7 work.

## Consent, attribution, and idempotency

The server supplies `source`, `form_name`, `locale`, consent version/time, status, revision, and timestamps from allowlisted configuration/context. Visitor `source_path` never selects configuration. Consent stores affirmative evidence/version/time, not policy prose; it is not proof of identity or a compliance claim.

`Idempotency-Key` is an opaque 16–128 character token matching `[A-Za-z0-9._~-]+`. It is never stored raw. With a key, store its SHA-256 and an HMAC-SHA-256 payload fingerprint. Without it both values are `null` and a valid request is new. Email and phone are not separate hashes. The HMAC key ring is plugin-owned configuration outside the repository at `plugins.goosialize-leads.idempotency.active_key_version` and `plugins.goosialize-leads.idempotency.keys.<positive-version>`; Phase 3B must generate or require a 32-byte secret, reject missing/short values, prove backup and rotation behavior, and fail closed. Secret values never enter documentation, logs, records, sidecars, packages, or responses. An unkeyed fingerprint or committed secret is forbidden.

## Canonical idempotency sidecar

The sidecar is a separate immutable schema, not a Lead record. Its path is `user-data://goosialize-leads/v1/idempotency/<first-two-key-digest-characters>/<64-lowercase-hex-key-digest>.json`; raw header text never enters a path. Canonical JSON key order is exactly the order shown, encoded as UTF-8 with `JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE` and one final LF:

```json
{
  "schema_version": 1,
  "key_version": 1,
  "key_digest": "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa",
  "payload_digest": "bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb",
  "lead_id": "0123456789abcdef0123456789abcdef",
  "created_at": "2026-07-27T10:20:30.123456Z",
  "expires_at": "2026-08-26T10:20:30.123456Z"
}
```

| Field | Type/presence | Derivation and validation | Limit | Mutability |
|---|---|---|---|---|
| `schema_version` | integer, required/non-null | exactly `1` | integer | immutable |
| `key_version` | integer, required/non-null | positive configured HMAC-key version; must match the Lead | 1–2,147,483,647 | immutable |
| `key_digest` | string, required/non-null | lowercase SHA-256 hex of exact ASCII key bytes after syntax validation; must equal filename | 64 bytes | immutable |
| `payload_digest` | string, required/non-null | lowercase HMAC-SHA-256 hex over canonical normalized command JSON | 64 bytes | immutable |
| `lead_id` | string, required/non-null | `^[0-9a-f]{32}$`; filename and stored Lead ID must match | 32 bytes | immutable |
| `created_at` | string, required/non-null | Lead timestamp format and equal to Lead `created_at` | 27 bytes | immutable |
| `expires_at` | string, required/non-null | exactly 30 UTC days after `created_at` | 27 bytes | immutable |

Every key is required; unknown keys are rejected. Maximum serialized size is 1,024 bytes. Mode is `0600`; directories are `0700`. Sidecars remain for at least 30 days. Expiry ends replay eligibility but authorizes neither overwrite nor automatic deletion: an expired key returns internal `IDEMPOTENCY_EXPIRED`/HTTP 409 until an authorized, tested Phase 7 maintenance operation removes it under the approved retention policy.

`key_version` selects a secret from the plugin-owned key ring; the active version is used for new captures and retained versions verify older records/sidecars. Rotation may add a version but never silently remove one still referenced. The payload HMAC input is canonical JSON plus one LF with exact key order: `source`, `form_name`, `locale`, `consent`, `full_name`, `email`, `phone`, `company`, `message`, `resource_id`, `source_path`, `campaign`. Consent contains only `granted`; campaign uses its canonical five-key order. Values are the normalized capture command before server IDs/timestamps. Trusted adapter attribution is included. Raw payload/key, ID, timestamps, status, revision, IP, user agent, email hashes, and phone hashes are excluded.

Replay validation under the global capture lock rejects symlinks, non-regular or oversized files, malformed JSON, unknown/duplicate keys, unsupported schema, invalid key versions/digests/timestamps/Lead references, missing or corrupt referenced Leads, filename/digest mismatch, or reconstructed payload-digest mismatch with internal `IDEMPOTENCY_INDEX_INVALID` and redacted 503. It verifies that the referenced Lead exists, its ID and key version match, and its stored canonical command reproduces `payload_digest`. Before expiry, same key/same digest returns the original result without storage or notification; same key/different digest returns `IDEMPOTENCY_CONFLICT`/409. Simultaneous matches serialize and produce at most one Lead and sidecar. Sidecar collisions are inspected, never overwritten.

Records and sidecars use the same verified hard-link no-replace publication boundary. Partial temporary sidecars are ignored by readers. If record publication succeeds but sidecar publication is interrupted, the request returns 503; a subsequent same-key request performs a bounded record scan under the lock for matching `key_hash` and `payload_fingerprint`, validates exactly one record, and publishes the missing sidecar. Zero, multiple, or inconsistent matches fail closed.

Malformed sidecars are never overwritten, renamed, quarantined, or repaired during anonymous capture. A future authorized Phase 7 maintenance command may copy them into a contained `quarantine/` directory only after explicit review; it must preserve the source, redact logs, and pass corruption, symlink, containment, and recovery tests.

## Compatibility and security invariants

- Schema 1 is the only Phase 3 write format; even additive keys require an explicit schema decision.
- Filename ID equals record ID; directory year/month equals UTC `created_at`.
- Numeric contract fields are base-10 JSON integers: Lead `schema_version`, `revision`, and non-null `idempotency.key_version`, plus sidecar `schema_version` and `key_version`. Fractional, negative, exponent-form, stringified, Boolean, or null representations are rejected unless the individual field contract explicitly permits null; floats are never valid. The canonical Lead and sidecar examples use these exact field names and satisfy this rule.
- Records contain no raw transport metadata, internal path, auth material, secret, or exception.
- Stored text remains untrusted at every output.
- Corrupt/unsupported records are never silently normalized, overwritten, exposed, or skipped without a safe diagnostic.
- Sidecars are schema-validated before replay, never expose raw keys or PII, and never authorize record reads.
- Records live outside the plugin package, survive upgrade, and remain on uninstall unless later explicitly authorized erasure exists.

## Rejected synthetic examples

| Input | Error |
|---|---|
| `{"email":"lead@example.test","nickname":"Example"}` | `UNKNOWN_FIELD` |
| `{"email":"lead@example.test","consent":{"granted":false}}` | `INVALID_CONSENT` |
| `{"email":"lead@example.test","consent":{"granted":true},"status":"new"}` | `UNKNOWN_FIELD` |
| `{"email":"lead@example.test","consent":{"granted":true},"page_url":"https://example.test/example"}` | `UNKNOWN_FIELD` |
| `{"phone":"000","consent":{"granted":true},"message":"Example"}` | `INVALID_PHONE` |
| An object with two `email` members | `DUPLICATE_JSON_KEY` |
