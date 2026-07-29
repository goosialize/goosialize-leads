# Phase 3 Lead Data Contract

Status: strict schema-v1 contract; Phase 3A record primitives are implemented and Phase 3B persistence is not yet implemented.

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

### Phase 3A API binding

The normative signatures, visibility, callable contracts, exception policy and permitted callers are the single API table in `docs/PHASE_3_SECURE_CAPTURE_STORAGE_PLAN.md`. This data contract fixes the values consumed and produced by those APIs:

- `LeadInputValidator::validate(mixed $submitted, array $trusted): ValidationResult` returns expected submitted-data failures rather than throwing. Success contains only a canonical `CaptureCommand`; failure contains one or more ordered `ValidationError` values and no submitted values.
- `ValidationError` accepts only a code appearing in this exhaustive table. Its field is the table's canonical field or `null`, and its exact array form is `array{code:string,field:?string}` in that key order.
- `ValidationResult` preserves the table's deterministic error order without sorting or deduplication. Its valid success value types are `CaptureCommand`, `LeadRecord`, or canonical serialization string, plus the internal generated-ID string returned by `LeadIdGenerator`.
- Invalid trusted-array shape and invalid direct factory use are programmer misuse and throw `\InvalidArgumentException`. Visitor attempts to override trusted names remain `trusted_field_override` results.
- Entropy and clock exceptions or invalid returns are caught and mapped without detail to `invalid_generated_id` and `invalid_timestamp`, respectively. Generated-data and serialization failures are ordinary `ValidationResult` failures.

The submitted portion of a successful command has exact key order `consent`, `full_name`, `email`, `phone`, `company`, `message`, `resource_id`, `source_path`, `campaign`. `consent` contains only `granted`; campaign is either `null` or contains exact key order `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`. The trusted portion has exact key order `source`, `form_name`, `locale`, `consent_version`. `CaptureCommand::toArray()` combines them in exact order `source`, `form_name`, `locale`, `consent`, `full_name`, `email`, `phone`, `company`, `message`, `resource_id`, `source_path`, `campaign`. The first/last-name alternative exists only at submitted validation input; success combines it into `full_name` and retains neither original member.

`LeadRecord::fromCommand()` invokes entropy exactly once as `callable(int): string` with `16` and invokes the clock exactly once as `callable(): \DateTimeInterface`. It copies mutable time objects, converts to UTC independently of the system timezone, and formats `Y-m-d\TH:i:s.u\Z`. One value supplies `created_at`, `updated_at`, and `consent.captured_at`. No retry, collision lookup, direct randomness, or direct current-time call belongs to Phase 3A.

The record factory emits the canonical top-level order shown at the start of this document and exact nested order `granted`, `version`, `captured_at` for consent; `key_version`, `key_hash`, `payload_fingerprint` for idempotency; and the five documented campaign keys. `LeadRecord::serialize()` accepts only its receiver as its `LeadRecord` argument, uses `JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`, adds exactly one LF, and returns `canonical_serialization_failed` or `canonical_record_too_large` without a partial value. Passing a distinct record is programmer misuse and throws `\InvalidArgumentException`.

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

`Idempotency-Key` is an opaque 16–128 character token matching `[A-Za-z0-9._~-]+`. It is never stored raw. With a key, store its SHA-256 and an HMAC-SHA-256 payload fingerprint. Without it both values are `null` and a valid request is new. Email and phone are not separate hashes. Secrets are required external configuration and are never generated: `plugins.goosialize-leads.idempotency.active_key_version` is a positive integer and each `plugins.goosialize-leads.idempotency.keys.<positive-version>` value is canonical padded Base64 decoding to exactly 32 bytes. The active version must exist; historical referenced versions remain configured. Null/empty configuration permits only submissions without an idempotency key; keyed submission fails closed. Secret values never enter documentation, logs, records, sidecars, packages, or responses. An unkeyed fingerprint or committed secret is forbidden. The exact key-ring API, rotation tests, digest bytes, error mapping, and Phase 3A idempotency-aware record factory are normative in `docs/PHASE_3_SECURE_CAPTURE_STORAGE_PLAN.md`.

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

Records and sidecars use the same verified hard-link no-replace publication boundary. Partial temporary sidecars are ignored by readers. If record publication succeeds but sidecar publication is interrupted, the request returns 503; before any later same-key record publication, the repository scans at most 10,000 canonical records across validated `records/YYYY/MM` directories in lexical order for matching `key_hash`, key version, and `payload_fingerprint`, validates exactly one record, and publishes the missing sidecar. Zero matches permits the new publication path; exactly one returns replay after sidecar recovery; multiple matches, a 10,001st record, or inconsistent state fails closed.

Malformed sidecars are never overwritten, renamed, quarantined, or repaired during anonymous capture. A future authorized Phase 7 maintenance command may copy them into a contained `quarantine/` directory only after explicit review; it must preserve the source, redact logs, and pass corruption, symlink, containment, and recovery tests.

## Phase 3C.1 Grav Forms mapping

Phase 3C.1 accepts only the filtered values returned by Forms 9.1.14 `Form::value()` after its built-in nonce, blueprint, and honeypot validation. The exact field mapping is:

| Forms field | Phase 3A destination | Presence and type |
|---|---|---|
| `full_name` | submitted `full_name` | Required string for Phase 3C.1 Forms; missing, null, or non-string reaches unchanged Phase 3A validation. |
| `email` | submitted `email` | Optional string or null; at least one of email/phone remains required. |
| `phone` | submitted `phone` | Optional string or null. |
| `company` | submitted `company` | Optional string or null. |
| `message` | submitted `message` | Optional string or null; at least one of message/resource remains required. |
| `resource_id` | submitted `resource_id` | Optional string or null. |
| `source_path` | submitted `source_path` | Optional string or null. |
| `campaign` | submitted `campaign` | Optional null or exact five-key campaign object. |
| `consent` | submitted `consent` | Required exact object `{"granted":true}`; no Boolean/string coercion. |
| `Form::getFormName()` | trusted `form_name` | Exact configured case-sensitive lowercase slug. |
| `plugins.goosialize-leads.forms.source` | trusted `source` | Validated lowercase slug. |
| `plugins.goosialize-leads.forms.locale` | trusted `locale` | Validated locale or null. |
| `plugins.goosialize-leads.forms.consent_version` | trusted `consent_version` | Validated lowercase slug. |
| `Form::getUniqueId()` | idempotency derivation only | Exactly 20 lowercase ASCII alphanumeric characters; never a command or record field. |

Missing fields become null only where Phase 3A permits null. Repeated/multiple values remain arrays and fail `invalid_type`; no adapter coercion or first-value selection occurs. Unknown filtered field names produce the stable form-level validation failure and are not silently discarded. Trusted names in submitted data remain forbidden. All trimming, NFC, telephone normalization, email-domain lowercasing, control/noncharacter checks, field grammar, consent, contact, name, and inquiry rules remain exclusively Phase 3A behavior.

Forms idempotency uses `IdempotencyKeyRing::deriveFormsIdempotencyKey()` with exact bytes `grav-forms-v1`, LF, form name, LF, submission ID, LF and the active Phase 3B HMAC key. The lowercase hexadecimal HMAC becomes the existing coordinator’s opaque idempotency key. Identical form/name/ID and normalized payload replays; a changed payload conflicts; absent/malformed ID or missing key configuration fails before persistence. Neither the Forms ID nor derived key is stored raw or exposed.

Phase 3C.1 user-visible outcomes contain no Lead data. Success is the exact translated message `Forms Lead captured.` followed by a configured validated internal 303 redirect with no query. Validation uses `Please correct the form and try again.` and standard redisplay. Configuration, identifier, conflict, or storage failure uses `We could not submit this form. Please try again later.` and standard redisplay. Primitive errors remain available only inside `CaptureResult` for deterministic tests and are not rendered with submitted values.

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

## Phase 3C.2 public JSON transport projection

The public endpoint accepts only these JSON members and projects them into the existing Phase 3A contract. Transport parsing never normalizes a submitted value.

| JSON member | Required and JSON type | Ownership and exact projection |
|---|---|---|
| `full_name` | Optional string or null | Submitted `full_name`; Phase 3A owns empty, length, NFC, and grammar. |
| `first_name` | Optional string or null | Submitted alternative name half; valid only with `last_name` and without `full_name`; Phase 3A combines the pair. |
| `last_name` | Optional string or null | Submitted alternative name half; valid only with `first_name` and without `full_name`; Phase 3A combines the pair. |
| `email` | Optional string or null | Submitted `email`; Phase 3A owns normalization and validation. |
| `phone` | Optional string or null | Submitted `phone`; Phase 3A owns normalization and validation. |
| `company` | Optional string or null | Submitted `company`; Phase 3A owns empty and length rules. |
| `message` | Optional string or null | Submitted `message`; Phase 3A owns content and length; at least one of this and `resource_id` must survive validation. |
| `resource_id` | Optional string or null | Submitted `resource_id`; Phase 3A owns grammar; at least one inquiry field is required. |
| `source_path` | Optional string or null | Submitted `source_path`; Phase 3A owns its safe relative-path contract. |
| `campaign` | Optional object or null | Only `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, and `utm_content` are allowed, each string or null; Phase 3A owns normalization, grammar, and length. |
| `consent` | Required object | Must contain exactly `{"granted":true}`; Phase 3A owns the consent invariant. |

Every other member, including client-supplied `source`, `form_name`, `locale`, `consent_version`, `status`, `schema_version`, `revision`, `id`, `created_at`, `updated_at`, and idempotency data, is `unknown_member`. Trusted values are `source = public_api`, `form_name = public_api`, configured `locale`, and configured `consent_version`. Missing optional members are represented as null only where the existing validator permits null. Arrays, numbers, Booleans outside `consent.granted`, and objects outside `campaign` and `consent` fail `request_schema_invalid`; repeated JSON names at any object depth fail earlier as `duplicate_json_key`.

API idempotency comes only from one case-sensitive `Idempotency-Key` header matching `[A-Za-z0-9._~-]{16,128}`. `IdempotencyKeyRing::deriveApiIdempotencyKey()` applies the active configured key to exact bytes `"public-api-v1\n" + header + "\n"` and returns lowercase hexadecimal HMAC-SHA-256. The header and digest remain transport/coordinator data and are absent from the Lead record. Same derived key plus the same canonical payload returns the existing Lead ID as replay; a changed payload returns `idempotency_conflict`. Missing and malformed headers never reach validation or persistence.

## Phase 4A.1 bounded Lead summary projection

Phase 4A.1 is read-only and does not change canonical records. The provider returns at most 100 summaries ordered by canonical `created_at` descending, then `id` ascending. It is a bounded inbox, not a complete history.

| Response key | Canonical source | Projection |
|---|---|---|
| `id` | `id` | Required opaque 32-character lowercase hexadecimal string. |
| `created_at` | `created_at` | Required canonical UTC timestamp. |
| `name` | `full_name` | String or null; native text. |
| `email` | `email` | String or null; native text. |
| `source` | `source` | Required string; native text and source filter. |
| `form_name` | `form_name` | Required string; native text. |
| `status` | `status` | Required stored string; native text and status filter; never mutated. |

Exact key order is `id`, `created_at`, `name`, `email`, `source`, `form_name`, `status`. HMAC/idempotency, revision, consent, company, message, phone, resource, source path, campaign, locale, paths, and sidecars are not projected. The success envelope is `data: list<LeadSummary>` followed by `meta` with exact keys `read_only:true`, `count:0..100`, `limit:100`, `truncated:boolean`, `total_scanned:0..10000`, and `allowed_statuses:[new,contacted,qualified,closed]`. No Phase 4A.1 endpoint exposes older records.

## Phase 4B mutation boundary

Phase 4B.1 status mutation and Phase 4B.2 reversible delete/restore are `BLOCKED_BY_ADMIN2_2_0_15`. The installed native resource-table editor transports only the selected value and has no source-proven revision/version field, explicit save action, general plugin nonce/CSRF lifecycle or deterministic conflict lifecycle. API authentication alone must never authorize these mutations.

While blocked, the canonical Lead record remains immutable, `status` and `revision` retain their captured values, no separate mutable status metadata exists, and the Phase 4A.1 projection remains read-only. Phase 3B security/idempotency sidecars remain separate and unopened by Admin2 reads. No hypothetical Phase 4B schema, runtime API or migration is approved.

Unblocking requires one exact installed-platform workflow proving together: native edit form and select, explicit submit/save, Lead ID, revision/version, authentication, dedicated ACL, general plugin nonce/CSRF creation and transport, server verification before persistence, stale-conflict behavior, native success/error feedback, and operation without plugin JavaScript, custom components, Shadow DOM, legacy Admin or compiled Admin2 changes.

## Phase 4C.1 bounded CSV projection

Phase 4C.1 exports only the existing immutable Phase 4A.1 summary projection. It calls `FilesystemLeadReadRepository::latest(LeadIndexQuery::newest())`; therefore it scans at most 10,000 exact primary-record candidates, refuses candidate 10,001, returns at most the newest 100 in canonical `created_at` descending then `id` ascending order, rejects malformed/oversized/symlinked/non-contained primary records, performs no write and never opens Phase 3B sidecars.

Columns are fixed and exhaustive:

| Order | Header | Source | Null | Maximum CSV cell | Disclosure |
|---:|---|---|---|---:|---|
| 1 | `Lead ID` | `LeadSummary::id()` | empty quoted cell | 128 UTF-8 code points | Synthetic opaque identifier only |
| 2 | `Created (UTC)` | `LeadSummary::createdAt()` | empty quoted cell | 32 code points | Canonical UTC timestamp unchanged |
| 3 | `Name` | `LeadSummary::name()` | empty quoted cell | 256 code points | Existing summary field |
| 4 | `Email` | `LeadSummary::email()` | empty quoted cell | 320 code points | Existing summary field |
| 5 | `Source` | `LeadSummary::source()` | empty quoted cell | 128 code points | Existing summary field |
| 6 | `Form` | `LeadSummary::formName()` | empty quoted cell | 128 code points | Existing summary field |
| 7 | `Status` | `LeadSummary::status()` | empty quoted cell | 32 code points | Immutable captured status only |

No message, telephone, consent, campaign, locale, company, resource, revision, HMAC, key version, fingerprint, storage path, sidecar or future mutable metadata is exported. A source value exceeding its column maximum fails the entire export before output with `export_record_invalid`; it is never silently truncated.

CSV is UTF-8 without BOM, comma-delimited, CRLF-terminated, and contains exactly one header row plus zero to 100 rows. Every field is enclosed in ASCII double quotes; embedded double quotes are doubled; CRLF and bare CR inside values normalize to LF and embedded LF remains inside the quoted cell. Null is an empty quoted cell. There are no Boolean columns. Timestamps remain canonical UTC strings. The final record also ends in CRLF.

Formula injection is neutralized without changing disk data. After line-break normalization and length validation, any non-empty cell whose first byte is tab, CR or LF, or whose first non-ASCII-whitespace character after leading ASCII space/tab is `=`, `+`, `-` or `@`, receives one leading ASCII apostrophe before CSV quoting. This includes leading-whitespace cases. Neutralization precedes response-byte accounting; no truncation occurs. Tests cover each dangerous prefix, leading spaces/tabs, safe apostrophes and ordinary Unicode.

## Phase 5A notification-event contract

Phase 5A defines exactly one immutable event type, `lead.accepted`, schema version integer `1`. Its event ID is lowercase SHA-256 of the exact bytes `goosialize-leads` + NUL + `lead.accepted` + NUL + the canonical 32-hex-character Lead ID. The 64-hex-character digest is deterministic and does not directly reveal the Lead ID.

Canonical JSON key order is `schema_version`, `event_id`, `event_type`, `lead_id`, `created_at`. Values are respectively integer `1`, the event ID, exact string `lead.accepted`, canonical Lead ID, and the Lead record's canonical UTC `created_at`. Encoding is UTF-8 JSON with `JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE` plus exactly one LF, at most 512 bytes. Nulls, unknown keys and alternate ordering are invalid. The event contains no contact/message/consent data, HMAC, fingerprint, key version, path, address, Origin, proxy header, exception, credential or raw request. Future delivery may rely only on the event type, Lead reference and creation time, and must reread the primary Lead under a later contract.

| Order/field | Exact source and type | Maximum/null policy | Disclosure and future reliance |
|---:|---|---|---|
| 1 `schema_version` | Contract constant; integer exactly `1` | One decimal digit; never null | Non-sensitive routing metadata; future readers may select schema with it. |
| 2 `event_id` | Deterministic digest defined above; lowercase hexadecimal string | Exactly 64 ASCII characters; never null | Opaque internal identifier; the digest does not reveal the Lead ID; future delivery may use it for event idempotency. |
| 3 `event_type` | Contract constant; string exactly `lead.accepted` | Exactly 13 ASCII characters; never null | Non-sensitive routing metadata; future delivery may route on it. |
| 4 `lead_id` | Canonical immutable primary-record `id`; lowercase hexadecimal string | Exactly 32 ASCII characters; never null | Internal Lead reference that does reveal the stored Lead ID; future delivery may use it only to reread that Lead. |
| 5 `created_at` | Canonical immutable primary-record `created_at`; UTC string `Y-m-d\TH:i:s.u\Z` | Exactly 27 ASCII characters; never null | Internal event time; future delivery may order or audit with it. |

The primary Lead and Phase 3B sidecar schemas remain byte-identical. Capture responses remain exactly unchanged and never expose notification state.

## Phase 5B notification-delivery projection

Phase 5B never changes the immutable `lead.accepted` event or primary Lead schema. A delivery message is a transient UTF-8 plain-text projection of the referenced canonical Lead. Its subject is exactly `New Lead ` followed by the canonical 32-character lowercase hexadecimal Lead ID: 41 ASCII bytes, no CR/LF, null or alternate form.

The body uses LF line endings, ends in exactly one LF, and contains these labels in this order: `Lead ID`, `Created (UTC)`, `Name`, `Email`, `Phone`, `Company`, `Source`, `Form`, `Message`. Each scalar line is `<label>: <value>\n`; null or empty is `-`. Message CRLF/CR normalizes to LF and every continuation line is prefixed by two ASCII spaces. Tab becomes one space; remaining C0/C1 controls and DEL are rejected. Values remain NFC UTF-8 and are never interpreted as Twig or headers.

Maximum normalized values are: Lead ID 32 bytes, timestamp 27, name 256 UTF-8 bytes, email 320 ASCII bytes, phone 64 UTF-8 bytes, company 256 UTF-8 bytes, source 64 ASCII bytes, form 64 ASCII bytes and message 10,000 UTF-8 bytes. Subject maximum is 41 bytes and body maximum is 12,288 bytes; equality is accepted and one byte over fails with `message_invalid`. The message excludes consent, locale, campaign, resource/source paths, idempotency/HMAC data, request metadata, credentials and filesystem paths.

Successful archival copies the original event bytes unchanged. Sent archives are not delivery metadata and contain no new field; Phase 5B creates no retry count, delivery timestamp, provider identifier or mutable status.

## Phase 5C.1 delivery-state contract

Phase 5C.1 keeps every pending, sent and dead-letter event byte-identical to its Phase 5A canonical bytes. Mutable operational metadata exists only at `user-data://goosialize-leads/v1/notification-outbox/delivery-state/<first-two-event-ID-hex>/<64-lower-hex-event-ID>.json`; directory/file modes are 0700/0600 and the canonical UTF-8 JSON is at most 1,024 bytes with exactly one final LF.

The exact key order is `schema_version`, `event_id`, `revision`, `state`, `attempt_count`, `first_attempt_at`, `last_attempt_at`, `next_eligible_at`, `last_result_code`, `terminal_at`, `created_at`, `updated_at`. Schema version is integer `1`; event ID is the canonical 64-lower-hex ID; revision is integer 1–2147483647; state is exactly `eligible`, `retry_wait`, `attempting`, `uncertain`, `delivered`, or `dead_lettered`; attempt count is integer 0–5. Every timestamp is null or canonical UTC `Y-m-d\TH:i:s.u\Z`. Result code is null or exactly one of `already_archived`, `archive_conflict`, `archive_failed`, `attempt_interrupted`, `dead_letter_conflict`, `dead_letter_failed`, `delivered`, `delivery_uncertain`, `duplicate_risk_retry_authorized`, `lead_invalid`, `lead_missing`, `message_invalid`, `state_unavailable`, `transport_exception`, `transport_failed`, or `transport_uncertain`. Unknown/missing/reordered keys, unknown states/codes, noncanonical JSON, overflow and inconsistent state invariants are invalid.

Initial state is revision 1, `eligible`, attempt 0, all attempt/result/terminal timestamps null, and equal nonnull `created_at`/`updated_at`. `attempting` has attempt count at least 1, first/last attempt timestamps, null next eligibility and terminal time. `retry_wait` has a retryable result and nonnull next eligibility. `uncertain` has null next eligibility and an uncertain result. `delivered` and `dead_lettered` are terminal, have null next eligibility and nonnull terminal time. Every transition increments revision exactly once and changes `updated_at`; first-attempt time never changes after its first value. The record contains no Lead/event/message content, address, path, credential, provider identifier or raw exception.

A permanent pre-transport failure is exactly `lead_missing`, `lead_invalid`, `message_invalid`, or `archive_conflict`, classified while the exclusive delivery lease is held and before any transport invocation in that processing pass. It proves provider acceptance is impossible for that pass and consumes no attempt. `eligible` and an eligible `retry_wait` may transition directly to `dead_lettered` only for one of those four codes. The transition preserves `attempt_count`, `first_attempt_at`, and `last_attempt_at`, clears `next_eligible_at`, records the exact code, sets `terminal_at`, increments revision once, and changes `updated_at`. It never passes through `attempting`; no other direct transition to `dead_lettered` is allowed.

Permanent dead-lettering persists that terminal state before immutable dead-letter publication. Until publication and pending-source removal complete, the terminal state prohibits transport and authorizes deterministic recovery under the same exclusive delivery-lock architecture. A matching existing dead-letter is idempotent only with the matching terminal state; a conflicting destination fails closed and leaves the pending bytes untouched. Event, Lead, sidecar, sent-archive, and dead-letter bytes are never rewritten.

The sole `uncertain`→`eligible` transition is explicit operator duplicate-risk retry authorization when `attempt_count` is 1–4; attempt 5 is exhausted and may only be confirmed delivered or dead-lettered. Authorization does not invoke transport or process the event. The resulting state increments revision once; preserves `attempt_count`, `first_attempt_at`, `last_attempt_at`, and `created_at`; sets `next_eligible_at` and `terminal_at` to null; sets `last_result_code` to exact audit code `duplicate_risk_retry_authorized`; and sets `updated_at` to the canonical reconciliation clock time. This code is neither a transport result nor a retryable, permanent, uncertain, or successful delivery classification. A later bounded batch calls the ordinary `beginAttempt()`, which increments the count and clears `last_result_code` before transport. Generic hydration, raw-array editing, and constructor reconstruction are prohibited as transition mechanisms.

The pending-event repository boundary is normative. `PendingNotificationRepository` declares exactly the additive methods `eligible(int $limit, DeliveryStateRepository $states, DeliveryClock $clock, bool $retriesOnly): array` and `withLockedEvent(string $eventId, callable $consumer): string`. The first returns the exact documented retry-discovery shape; the second owns the continuous exclusive event lock and callback-scoped `DeliveryEventLease`, validates the callback's closed operational result, maps callback throwables to redacted `event_invalid`, invalidates the lease, and releases the lock in `finally`. `FilesystemPendingNotificationRepository` implements both with identical signatures. Worker and reconciliation consumers use the interface declarations directly; no default interface implementation, dynamic detection, `method_exists()` fallback, concrete-class bypass, or parameter/return-type variance is permitted. Direct unit and reflection tests must prove both interface methods, both implementation methods, exact signatures, interface/implementation parity, exact reflection inventory, worker interface use, and no direct concrete-class bypass.

## Phase 5C.2 operational-data projection

Phase 5C.2 adds no Lead, capture, event, sent, dead-letter or delivery-state field. It adds only a derived ephemeral operational projection. The projection reads canonical Phase 5A event bytes and Phase 5C.1 state bytes without changing them and never reads the Lead record, Phase 3B sidecars, Email configuration or notification message.

An item has exact ordered keys `event_id`, `state`, `attempt_count`, `next_eligible_at`, `created_at`, `updated_at`, `last_result_code`, `revision`, `terminal`, `processable`, `operator_actionable`. State is exactly `conflict`, `recovery_required`, `dead_lettered`, `sent`, `uncertain`, `attempting`, `eligible_retry`, `retry_wait`, or `pending_first_attempt`. Event ID is 64 lowercase hex; attempt count is 0–5; timestamps are null or canonical UTC `Y-m-d\TH:i:s.u\Z`; result is null or the Phase 5C.1 allowlist; revision is 0 only without state and otherwise 1–2147483647. Boolean flags must match the state matrix in the secure-capture/storage plan.

`pending_first_attempt` has exactly two valid representations: a canonical pending event with no state, or that event with the exact durable initial Phase 5C.1 state—schema version 1, revision 1, state `eligible`, attempt count 0, null `first_attempt_at`, `last_attempt_at`, `next_eligible_at`, `last_result_code`, and `terminal_at`, and equal nonnull canonical `created_at` and `updated_at`. Both are processable by the existing bounded worker and scheduled invocation; neither proves retry history. `eligible_retry` requires either an eligible state with attempt count 1–4, nonnull valid attempt timestamps, and exact `duplicate_risk_retry_authorized` result history, or a valid `retry_wait` state that is due at the one inventory clock instant. Impossible eligible histories fail closed. Inventory never creates, deletes, normalizes, or rewrites state to reconcile the two first-attempt representations.

Response order is `items`, then `meta`. Meta order is `read_only:true`, `total_scanned`, `returned`, `limit`, `truncated`, `invalid`, `conflicts`, `counters`, `codes`. Limit is 1–100 and scan count 0–10,000. Counter and code maps are lexically ordered positive integers from the state list or `event_invalid`, `state_invalid`, `inventory_changed`, `inventory_conflict`, `inventory_capacity_exceeded`. Unknown/malformed values are counted, never echoed. One scan clock determines every retry boundary.

No operational output may contain Lead content/status, recipient/sender, subject/body, raw event/state/archive/dead-letter, provider response, exception/stack, path, IP/header, credential/token, SMTP detail, scheduler command/trigger URL/user. The projection is not durable state or an audit log, grants no mutation/reconciliation authority and makes no exactly-once claim. Concurrent movement can make it stale immediately.
