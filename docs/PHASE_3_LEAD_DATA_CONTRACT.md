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

Unknown request and record fields are rejected. Raw payloads, IP addresses, user agents, full URLs, query strings, arbitrary custom fields, product objects, consent prose, credentials, and secrets are never stored. `first_name` and `last_name` are accepted only as a complete paired input alternative, are combined into canonical `full_name`, and are never record keys. JSON member names must be unique.

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
| `consent.version` | string, required/non-null | Trusted config | NFC single-line configured slug using the same lowercase slug grammar as `source` and `form_name` | 64 code points/128 bytes | EVIDENCE | immutable |
| `consent.captured_at` | string, required/non-null | Server clock | equal to created time | 27 bytes | EVIDENCE | immutable |
| `idempotency` | object, required/non-null | Server | exact children below | object | INDIRECT | immutable |
| `idempotency.key_version` | integer, required/nullable | Trusted secret config | positive version identifying HMAC key; never secret material | integer | NONE | immutable; `null` without key |
| `idempotency.key_hash` | string, required/nullable | Server-derived | SHA-256 of valid header, lowercase hex | 64 bytes | INDIRECT | immutable; `null` without header |
| `idempotency.payload_fingerprint` | string, required/nullable | Server-derived | HMAC-SHA-256 of canonical normalized input, lowercase hex | 64 bytes | INDIRECT | immutable; `null` without header |
| `full_name` | string, required/nullable | Normalized input | NFC, trim/collapse horizontal whitespace, single-line, no markup; exact name grammar below | 200 code points/400 bytes | DIRECT | Phase 3 immutable; `null` |
| `email` | string, required/nullable | Normalized input | trim; exact conservative ASCII dot-atom grammar below; preserve local-part case, lowercase domain | 254 bytes | DIRECT | Phase 3 immutable; `null` |
| `phone` | string, required/nullable | Normalized input | remove space/`-`/parentheses; result `+` and 8–15 digits | 16 bytes | DIRECT | Phase 3 immutable; `null`; no country guessing |
| `company` | string, required/nullable | Normalized input | NFC, trim/collapse, single-line, no markup; exact company grammar below | 200 code points/400 bytes | DIRECT | Phase 3 immutable; `null` |
| `message` | string, required/nullable | Normalized input | NFC, normalized LF, multiline, no markup | 4,000 code points/8,000 bytes | DIRECT | Phase 3 immutable; `null` |
| `resource_id` | string, required/nullable | Normalized input | lowercase slug, max 128; arbitrary product objects rejected | 128 bytes | INDIRECT | immutable; `null` |
| `source_path` | string, required/nullable | Normalized input | internal absolute path; no host/scheme/query/fragment, `.`/`..`, encoded separators or duplicate slash | 512 bytes | INDIRECT | immutable; `null` |
| `campaign` | object, required/nullable | Normalized input | exact children; all-null becomes `null` | object | INDIRECT | immutable; `null` |
| `campaign.utm_source` | string, required/nullable | Normalized input | NFC, trim, single-line; exact campaign-token grammar below | 100 code points/200 bytes | INDIRECT | immutable; `null` |
| `campaign.utm_medium` | string, required/nullable | Normalized input | same | 100 code points/200 bytes | INDIRECT | immutable; `null` |
| `campaign.utm_campaign` | string, required/nullable | Normalized input | same | 100 code points/200 bytes | INDIRECT | immutable; `null` |
| `campaign.utm_term` | string, required/nullable | Normalized input | same | 100 code points/200 bytes | INDIRECT | immutable; `null` |
| `campaign.utm_content` | string, required/nullable | Normalized input | same | 100 code points/200 bytes | INDIRECT | immutable; `null` |

Every canonical key is present; omitted optional input becomes JSON `null`. `full_name` or the complete pair `first_name` plus `last_name` is required; both forms together or only one paired-name member is invalid. A valid pair is independently normalized under the full-name rules and joined with one ASCII space into canonical `full_name`; the pair is never serialized. At least one of `email` or `phone` must be non-null. At least one of `message` or `resource_id` must be non-null. Every violation rejects the whole request with the exhaustive stable primitive code below; no partial record is stored.

### Exact name grammar

`full_name`, `first_name`, and `last_name` must already have passed UTF-8, NFC, control, noncharacter, markup, multiline, empty, and length checks. A name is one or more Unicode letter sequences separated only by exactly one of U+0020 SPACE, U+0027 APOSTROPHE, U+2019 RIGHT SINGLE QUOTATION MARK, or U+002D HYPHEN-MINUS. A letter sequence starts with a Unicode `\p{L}` character and continues with zero or more `\p{L}` or `\p{M}` characters; therefore combining marks occur only after a letter in the same sequence. Separators occur only between letter sequences. Leading, trailing, adjacent, and separator-only values are invalid. Digits and every other punctuation or symbol are invalid.

Exact accepted inputs are `Example Lead`, `Anne-Marie`, `O'Example`, and `D’Example`. Exact field-specific rejected inputs are `-`, `'`, `-Example`, `Example-`, `Example--Lead`, `Example2`, and `Example/Lead`. Each passes the higher-priority generic predicates but fails this grammar and returns `invalid_full_name`, `invalid_first_name`, or `invalid_last_name` according to the field under validation. In particular, `-` contains no Unicode letter and its separator is not between letter sequences.

### Exact company and campaign grammars

A non-null company must already have passed all generic text predicates. Its grammar is one or more sequences starting with a Unicode letter or decimal digit and continuing with Unicode letters, combining marks, or decimal digits. Sequences may be separated by exactly one U+0020 SPACE, U+0027 APOSTROPHE, U+2019 RIGHT SINGLE QUOTATION MARK, U+002D HYPHEN-MINUS, U+0026 AMPERSAND, or U+002E FULL STOP. Separators cannot lead, trail, or be adjacent. Thus `Example-Co` is accepted, while exact input `Example/Co` passes every generic predicate but returns `invalid_company`.

Each non-null campaign value uses ASCII token grammar `\A[A-Za-z0-9][A-Za-z0-9._~-]*\z`. It starts with an ASCII letter or digit; subsequent characters may additionally be `.`, `_`, `~`, or `-`. Spaces, Unicode, slashes, and leading punctuation fail the corresponding campaign-field code only after generic predicates pass. Exact reachable oracles are: `campaign.utm_source="spring sale"` → `invalid_campaign_source`; `campaign.utm_medium="-spring"` → `invalid_campaign_medium`; `campaign.utm_campaign="spring/campaign"` → `invalid_campaign_name`; `campaign.utm_term="spring sale"` → `invalid_campaign_term`; and `campaign.utm_content="-spring"` → `invalid_campaign_content`.

## Request shape and limits

The only submitted top-level keys are `consent`, `full_name`, `first_name`, `last_name`, `email`, `phone`, `company`, `message`, `resource_id`, `source_path`, and `campaign`. `consent` accepts only `{"granted":true}`. `campaign` accepts only `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, and `utm_content`.

Maximum raw request is 16,384 bytes; maximum canonical record is 32 KiB; depth is four including root. Duplicate JSON members fail transport code `DUPLICATE_JSON_KEY`, not last-key-wins. Canonical server-controlled fields fail primitive code `trusted_field_override`; other non-allowlisted fields fail `unknown_field`.

## Phase 3A validation and normalization contract

Phase 3A requires `ext-intl`, `Normalizer`, `Normalizer::FORM_C`, successful `Normalizer::normalize($value, Normalizer::FORM_C)`, and positive `Normalizer::isNormalized()` verification. Absence fails closed with `unicode_normalization_unavailable`; a call failure or unverifiable result fails with `unicode_normalization_failed`. There is no ASCII bypass or lossy fallback.

Each primitive error object has exactly `code` (lower-snake-case string) and `field` (canonical dotted field name, normalized submitted unknown name, or `null`). It contains no submitted value, PII, translated prose, path, trace, or internal exception. Root-shape failure stops immediately. For a valid associative map, validation collects every independently detectable error, retains at most one primary error per field, and selects the lowest numbered applicable priority below. Known fields are ordered by canonical record order, with `first_name` and `last_name` immediately after `full_name`; unknown names follow in normalized ASCII lexicographic order; cross-field errors follow in their fixed numbered order. Ordering never uses insertion order, locale, filesystem state, or exception order. Repeated validation produces byte-identical ordered errors.

This table is exhaustive for Phase 3A. “Collect” continues with independently checkable fields; “stop” returns only that error. Examples are synthetic.

| Rejection condition | Stable code | Field | Priority | Behavior | Synthetic oracle |
|---|---|---|---:|---|---|
| Root is not an associative map | `object_required` | `null` | 1 | stop | `[]` |
| More than 11 top-level members | `too_many_fields` | `null` | 2 | stop | 12 distinct keys |
| Unknown member | `unknown_field` | submitted name | 1 | collect | `nickname` |
| Server/trusted member override | `trusted_field_override` | submitted name | 1 | collect | `status` |
| Wrong scalar/object type | `invalid_type` | field | 2 | collect | `email: 7` |
| Null where forbidden | `null_forbidden` | field | 3 | collect | `consent: null` |
| Required child absent after its parent passes shape and type validation | `required` | `consent.granted` | 4 | collect | `{"consent":{},"full_name":"Example Lead","email":"lead@example.test","message":"Example"}` |
| Required text empty after normalization | `empty_required` | field | 5 | collect | three spaces |
| Invalid UTF-8 | `invalid_utf8` | field | 6 | collect | bytes `C3 28` |
| Unicode capability absent | `unicode_normalization_unavailable` | `null` | 1 | stop | `ext-intl` false |
| NFC call or verification fails | `unicode_normalization_failed` | field | 7 | collect | failing normalizer |
| Forbidden C0/C1 control | `forbidden_control` | field | 8 | collect | `U+0007` |
| Unicode noncharacter | `forbidden_noncharacter` | field | 9 | collect | `U+FDD0` |
| Tag-like markup | `html_not_allowed` | field | 10 | collect | `<b>Example</b>` |
| Line break outside `message` | `multiline_not_allowed` | field | 11 | collect | `consent.version="privacy\nversion"` |
| Text exceeds its code-point or byte limit | `too_long` | field | 13 | collect | 201 ASCII name characters |
| Exact name grammar fails after generic checks | `invalid_full_name` | `full_name` | 14 | collect | `-` |
| Exact name grammar fails after generic checks | `invalid_first_name` | `first_name` | 14 | collect | `'` |
| Exact name grammar fails after generic checks | `invalid_last_name` | `last_name` | 14 | collect | `Example2` |
| Exact email grammar fails after generic checks | `invalid_email` | `email` | 14 | collect | `lead@@example.test` |
| Normalized telephone is not `+` plus 8–15 digits | `invalid_phone` | `phone` | 14 | collect | `000` |
| Exact company grammar fails after generic checks | `invalid_company` | `company` | 14 | collect | `Example/Co` |
| Locale is absent from the configured allowlist after generic checks | `invalid_locale` | `locale` | 14 | collect | `xx-invalid` |
| Lowercase resource slug grammar fails after generic checks | `invalid_resource_id` | `resource_id` | 14 | collect | `Example` |
| Absolute internal-path grammar fails after generic checks | `invalid_source_path` | `source_path` | 14 | collect | `/example/../other` |
| Campaign-token grammar fails after generic checks | `invalid_campaign_source` | `campaign.utm_source` | 14 | collect | `spring sale` |
| Campaign-token grammar fails after generic checks | `invalid_campaign_medium` | `campaign.utm_medium` | 14 | collect | `-spring` |
| Campaign-token grammar fails after generic checks | `invalid_campaign_name` | `campaign.utm_campaign` | 14 | collect | `spring/campaign` |
| Campaign-token grammar fails after generic checks | `invalid_campaign_term` | `campaign.utm_term` | 14 | collect | `spring sale` |
| Campaign-token grammar fails after generic checks | `invalid_campaign_content` | `campaign.utm_content` | 14 | collect | `-spring` |
| Consent object absent | `consent_missing` | `consent` | 1 | collect | omitted `consent` |
| Consent is not Boolean `true` | `invalid_consent_granted` | `consent.granted` | 14 | collect | `false` |
| Trusted consent version absent | `consent_version_missing` | `consent.version` | 4 | collect | missing configuration |
| Consent version invalid | `invalid_consent_version` | `consent.version` | 14 | collect | `privacy version` |
| Server consent timestamp absent | `consent_timestamp_missing` | `consent.captured_at` | 4 | collect | clock has no value |
| Consent timestamp invalid | `invalid_consent_timestamp` | `consent.captured_at` | 14 | collect | timestamp without `Z` |
| No full name or complete pair | `name_required` | `full_name` | 1 | cross-field 1 | all name inputs null |
| Only one paired-name member | `incomplete_name_pair` | `full_name` | 2 | cross-field 2 | only `first_name` |
| Full name and pair both supplied | `conflicting_name_forms` | `full_name` | 3 | cross-field 3 | both forms |
| Neither email nor phone | `contact_required` | `email` | 4 | cross-field 4 | both null |
| Neither message nor resource ID | `inquiry_required` | `message` | 5 | cross-field 5 | both null |
| Generated ID is not 32 lowercase hex | `invalid_generated_id` | `id` | 1 | stop record | 31 hex characters |
| Schema version is not integer `1` | `invalid_schema_version` | `schema_version` | 1 | stop record | integer `2` |
| Injected time is not exact UTC microseconds | `invalid_timestamp` | `created_at` | 1 | stop record | no six-digit fraction |
| Updated timestamp is invalid | `invalid_updated_timestamp` | `updated_at` | 1 | stop record | timestamp without `Z` |
| Capture timestamps are not identical | `capture_timestamps_mismatch` | `updated_at` | 2 | stop record | one-microsecond difference |
| Initial status is not `new` | `invalid_initial_status` | `status` | 1 | stop record | `open` |
| Initial revision is not integer `1` | `invalid_initial_revision` | `revision` | 1 | stop record | integer `0` |
| Trusted source violates its slug rule | `invalid_source` | `source` | 1 | stop record | `Website!` |
| Trusted form name violates its slug rule | `invalid_form_name` | `form_name` | 1 | stop record | `Contact Form` |
| Idempotency value is not the exact object | `invalid_idempotency` | `idempotency` | 1 | stop record | unknown child |
| Non-null key version is not positive integer | `invalid_idempotency_key_version` | `idempotency.key_version` | 1 | stop record | integer `0` |
| Non-null key hash is not 64 lowercase hex | `invalid_idempotency_key_hash` | `idempotency.key_hash` | 1 | stop record | 63 hex characters |
| Non-null fingerprint is not 64 lowercase hex | `invalid_payload_fingerprint` | `idempotency.payload_fingerprint` | 1 | stop record | uppercase hex |
| Canonical JSON encoding fails | `canonical_serialization_failed` | `null` | 1 | stop record | injected encoder failure |
| Canonical record exceeds 32 KiB | `canonical_record_too_large` | `null` | 2 | stop record | 32,769 encoded bytes |

The priority columns implement this exact validation-layer order: root structure; allowlist/trusted override; PHP type/nullability; UTF-8/capability; NFC; controls/noncharacters; HTML; multiline; empty/maximum length; field grammar/semantics; fixed-order cross-field validation; then server-generated canonical validation. No non-empty field has a separate generic minimum: required normalized empty text uses `empty_required`, optional empty text becomes `null`, and non-empty lower-bound semantics belong to the exact field grammar, so no unreachable `too_short` code exists. A field-specific predicate runs only after every generic predicate for that field passes, so each field-specific oracle above is reachable and cannot be shadowed by a generic code. `message` has no distinct semantic grammar: its failures use `invalid_utf8`, `unicode_normalization_failed`, `forbidden_control`, `forbidden_noncharacter`, `html_not_allowed`, `empty_required`, or `too_long` as applicable; no `invalid_message` code exists.

The exact generic `required` oracle is the root object shown in its table row. Its `consent` parent is present with the correct object type, all other required input obligations are valid, and only required child `consent.granted` is absent. No specialized missing code applies: `consent_missing` applies only when the `consent` object itself is absent, while `invalid_consent_granted` applies only to a present value that is not Boolean `true`. The independently executable result is therefore exactly `{"code":"required","field":"consent.granted"}`. By contrast, omitting the top-level `consent` member produces only `{"code":"consent_missing","field":"consent"}` at priority 1.

The exact single-line consent-version semantic oracle is `privacy version`: it is a 15-byte string, valid UTF-8 and NFC, with no controls, noncharacters, markup, or newline, and is within both consent-version limits. It passes every earlier generic predicate and fails only the configured lowercase slug grammar because U+0020 SPACE is not allowed, so its exact result is `{"code":"invalid_consent_version","field":"consent.version"}`. The separate exact value `privacy\nversion`, where `\n` denotes one LF byte, is 15 bytes and returns `{"code":"multiline_not_allowed","field":"consent.version"}` before semantic validation.

Cross-field errors are appended in the exact order numbered 1–5 above. Server-generated canonical validation follows afterward in canonical record-key order—`schema_version`, `id`, `created_at`, `updated_at`, `status`, `revision`, `source`, `form_name`, `locale`, `consent`, and `idempotency`—then serialization and record size; it stops at its first terminal error. This ordering is independent of submitted-key order, PHP hash order, locale, exception order, and runtime iteration order.

Exact multi-error oracle input:

```json
{"zeta":"synthetic","resource_id":null,"company":"<b>Example</b>","phone":"+35700000000","email":7,"message":null,"full_name":"Example Lead","alpha":"synthetic","consent":{"granted":true}}
```

The complete ordered primitive error array is exactly:

```json
[{"code":"invalid_type","field":"email"},{"code":"html_not_allowed","field":"company"},{"code":"unknown_field","field":"alpha"},{"code":"unknown_field","field":"zeta"},{"code":"inquiry_required","field":"message"}]
```

The two known-field errors follow canonical field order; normalized unknown names follow afterward in lexical order despite reverse submission; the fixed cross-field error is last. The valid phone prevents `contact_required`. Errors contain no submitted value or PII. Repeating validation must produce these exact bytes.

### Exact Phase 3A email grammar

Email input must already be valid UTF-8 and NFC, must be entirely ASCII, and must not exceed 254 bytes. It contains exactly one `@`. The local part is 1–64 bytes of dot-separated non-empty atoms; atom characters are ASCII letters, digits, and the ASCII characters `!`, `#`, `$`, `%`, `&`, `'`, `*`, `+`, `/`, `=`, `?`, `^`, `_`, backtick, `{`, `|`, `}`, `~`, and `-`. Leading, trailing, and consecutive dots are forbidden. Quoted local parts, comments, escapes, display names, and domain literals are forbidden. Local-part case is preserved.

The domain is 1–253 bytes and is normalized to lowercase. It has at least two dot-separated labels, each 1–63 bytes, containing only ASCII letters, digits, and interior hyphens. Labels cannot begin or end with a hyphen. Empty labels, underscores, non-ASCII characters, and IDN conversion are forbidden.

Accepted email oracles:

| Exact input or deterministic construction | Bytes | Result | Exact normalized value/reason |
|---|---:|---|---|
| `lead@example.test` | 17 | accepted | `lead@example.test` |
| `first.last+tag@example.test` | 27 | accepted | `first.last+tag@example.test` |
| `str_repeat('a', 64) . '@example.test'` | 77; local 64 | accepted | same 77-byte value; local-part maximum |
| `'lead@' . str_repeat('a', 63) . '.test'` | 73; label 63 | accepted | same 73-byte value; label maximum |
| `str_repeat('a', 64) . '@' . str_repeat('b', 63) . '.' . str_repeat('c', 63) . '.' . str_repeat('d', 61)` | 254 | accepted | same value; assert `strlen($email) === 254`, all labels valid |

Rejected email oracles:

| Exact input or deterministic construction | Bytes | Exact code | Exact reason |
|---|---:|---|---|
| `lead.example.test` | 17 | `invalid_email` | no `@` |
| `lead@@example.test` | 18 | `invalid_email` | two `@` characters |
| `.lead@example.test` | 18 | `invalid_email` | leading local dot |
| `lead.@example.test` | 18 | `invalid_email` | trailing local dot |
| `le..ad@example.test` | 19 | `invalid_email` | adjacent local dots |
| `"lead"@example.test` | 19 | `invalid_email` | quoted local part forbidden |
| `léad@example.test` | 18 | `invalid_email` | valid NFC UTF-8 but local part is not ASCII |
| `lead@exämple.test` | 18 | `invalid_email` | valid NFC UTF-8 but domain is not ASCII; no IDN conversion |
| `lead@example_test` | 17 | `invalid_email` | underscore in domain label |
| `lead@-example.test` | 18 | `invalid_email` | label begins with hyphen |
| `lead@example-.test` | 18 | `invalid_email` | label ends with hyphen |
| `lead@example..test` | 18 | `invalid_email` | empty domain label |
| `str_repeat('a', 65) . '@example.test'` | 78; local 65 | `invalid_email` | local part exceeds 64 bytes after all generic checks pass |
| `'lead@' . str_repeat('a', 64) . '.test'` | 74; label 64 | `invalid_email` | domain label exceeds 63 bytes after all generic checks pass |
| `str_repeat('a', 64) . '@' . str_repeat('b', 63) . '.' . str_repeat('c', 63) . '.' . str_repeat('d', 62)` | 255 | `too_long` | assert `strlen($email) === 255`; generic maximum-length priority precedes email grammar |

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
| `{"email":"lead@example.test","nickname":"Example"}` | `unknown_field` |
| `{"email":"lead@example.test","consent":{"granted":false}}` | `invalid_consent_granted` |
| `{"email":"lead@example.test","consent":{"granted":true},"status":"new"}` | `trusted_field_override` |
| `{"email":"lead@example.test","consent":{"granted":true},"page_url":"https://example.test/example"}` | `unknown_field` |
| `{"phone":"000","consent":{"granted":true},"message":"Example"}` | `invalid_phone` |
| An object with two `email` members | `DUPLICATE_JSON_KEY` |
