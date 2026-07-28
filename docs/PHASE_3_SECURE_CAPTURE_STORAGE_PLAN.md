# Phase 3 Secure Capture and Storage Plan

Planning status: review draft; no Phase 3 runtime behavior exists.

## Objective, scope, and baseline

Phase 3 must add theme-independent capture, deterministic server-side validation and normalization, consent capture, protected filesystem persistence, atomic writes, identifiers, duplicate protection, email notifications, and foundational tests. Phase 4 retains all functional Admin2 viewing, permission-gated management, search, combined filters, status changes, CSV export, updates, deletion, and accessibility.

At `ab919d4a9ee2e7d67547cc83ac648d3f6ce96784` the plugin has exactly the inert `onApiRegisterRoutes` and `onTwigTemplatePaths` subscriptions. It has zero routes, no controller or verified runtime autoloader, no Lead type, repository, persistence, schema, permissions, Forms/Email processing, or functional Admin2 management. This plan changes none of those facts and preserves the standalone, theme-independent Phase 2 package.

Non-goals are Admin2 management, delivery, final permission hardening, migrations, automated deletion, marketplace/release work, application-level encryption, distributed storage/locking, and any compliance claim.

## Exact Grav 2.0.12 and API findings

Evidence is local image `lscr.io/linuxserver/grav:2.0.12`, ID `sha256:702d936e25513805b57c9d009f7ff466217273415b2e55f539f3366e6377d351`, inspected with no network.

| Dependency | Exact source and method | Verified behavior | Status and reliance risk |
|---|---|---|---|
| Plugin autoload | `/app/www/public/system/src/Grav/Common/Plugins.php:125-171`, `Plugins::init()`; `/app/www/public/user/plugins/api/api.php:125-128`, `ApiPlugin::autoload()` | Enabled plugins call `autoload()` before subscriber registration; API returns its Composer loader. | Core lifecycle plus observed official plugin example; package loader still needs Phase 3A proof. |
| Data stream | `/app/www/public/system/src/Grav/Common/Config/Setup.php:154-159` | Forced `user-data://` stream maps to `user://data`. | Core exact-version source; effective modes/filesystem need Phase 3B tests. |
| API routes | `/app/www/public/user/plugins/api/classes/Api/ApiRouter.php:511-522,809-816`, `createDispatcher()`/`registerPluginRoutes()`; `/app/www/public/user/plugins/api/classes/Api/ApiRouteCollector.php:24-77` | `onApiRegisterRoutes` receives the collector; `post()` adds a relative handler. | Event is documented; collector/cache details are internal and pinned by Phase 3C tests. |
| Controller invocation | `/app/www/public/user/plugins/api/classes/Api/ApiRouter.php:466-509`, `dispatch()`/`handleRoute()` | Constructs controller with Grav and Config, decodes params, invokes with PSR-7 request. | Internal exact-version behavior; no signature beyond this source is assumed. |
| Public/auth | `/app/www/public/user/plugins/api/classes/Api/ApiRouter.php:158-241`, `process()`; `/app/www/public/user/plugins/api/classes/Api/Middleware/AuthMiddleware.php:28-59`, `processRequest()`/`processOptional()` | Method-scoped public entries use optional authentication. | Internal behavior; Phase 3C must prove anonymous-write registration. |
| JSON | `/app/www/public/user/plugins/api/classes/Api/ApiRouter.php:139-153`, `process()`; `/app/www/public/user/plugins/api/classes/Api/Middleware/JsonBodyParserMiddleware.php:10-31`, `processRequest()` | JSON parsing precedes dispatch and sets `json_body`. | Internal; lacks exact media, byte, depth, root, and duplicate-key enforcement. |
| CORS | `/app/www/public/user/plugins/api/classes/Api/Middleware/CorsMiddleware.php:24-116`, `addHeaders()`/`createPreflightResponse()`; `/app/www/public/user/plugins/api/api.yaml:16-46` | Default origins empty; configured exact origins can receive headers. | Internal and configuration-dependent; Phase 3C origin tests required. |
| Rate limit | `/app/www/public/user/plugins/api/classes/Api/ApiRouter.php:268-299`; `/app/www/public/user/plugins/api/classes/Api/Middleware/RateLimitMiddleware.php:20-153`, `check()`/`getIdentifier()`/`checkLimit()`; `/app/www/public/user/plugins/api/api.yaml:48-52` | Default 120/60-second bucket; guests use `REMOTE_ADDR`; storage failure allows request. | Internal; never the sole abuse boundary. |
| Responses/errors | `/app/www/public/user/plugins/api/classes/Api/ApiRouter.php:300-330`; `/app/www/public/user/plugins/api/classes/Api/Response/ApiResponse.php:10-30,92-113`, `create()`/`ok()`/`created()`/`noContent()` | Data envelope/no-store; debug mode can expose unhandled exception detail. | Internal; adapter must translate typed failures before router fallback. |
| Forms lifecycle | `/app/www/public/user/plugins/form/blueprints.yaml:1-20` (Form 9.1.13); `/app/www/public/user/plugins/form/classes/Form.php:875-1025`, `post()`; `/app/www/public/user/plugins/form/form.php:515-620,794-825,1176-1195` | Nonce and blueprint validation run before `onFormProcessed`; honeypot is checked; named processors receive form/action/params; redirects and XHR template selection exist. | Official plugin exact-version behavior; custom definition registration and XHR response mapping need Phase 3C proof. |
| Email service | `/app/www/public/user/plugins/email/blueprints.yaml:1-21` (Email 5.0.3); `/app/www/public/user/plugins/email/email.php:54-64`, `onPluginsInitialized()`; `/app/www/public/user/plugins/email/classes/Email.php:49-52,64-84,86-137,147-196`, `Email::enabled()`, `Email::message()`, `Email::send()`, `Email::buildMessage()` | Enabled service is `grav['Email']`; `message()` constructs/configures and returns a `Message`, while `send()` performs delivery and returns an integer status; recipient/from are required. | Internal exact-version plugin behavior, not a stable public API guarantee. Phase 3D must keep construction distinct from delivery-result interpretation, re-verify accepted `send()` statuses, and suppress potentially sensitive debug/exception output. |
| No-replace probe | Exact PHP 8.5.8 container, disposable same-filesystem directory | `link(temp, final)` published complete bytes, preserved `0600`, shared inode, and failed when final already existed; `fsync()` returned true. | Observed runtime contract; unsupported filesystems fail closed and are tested in Phase 3B. |

No API-layer CSRF verifier or request-size limiter was found in those audited paths. Proxy-aware origin derivation, missing-Origin policy, CSRF position, and endpoint-specific abuse limits are assigned to the Phase 3C verification table below.

### Autoload prerequisite

Before Phase 3 classes exist, Phase 3A packages one handwritten root `autoload.php`; no `vendor/` or `vendor/composer/` tree is generated or required. `GoosializeLeadsPlugin::autoload(): void` performs exactly one guarded `require_once __DIR__ . '/autoload.php'`. The root artifact idempotently registers exactly one loader for prefix `Grav\Plugin\GoosializeLeads\` and root `__DIR__ . '/classes/'`. It accepts only that prefix and valid PHP namespace segments, converts namespace separators to directory separators, appends `.php`, proves separator-aware containment below the canonical classes root, and requires only an existing regular file there. Unknown prefixes and invalid or traversal-like names are ignored; it never searches arbitrary directories, registers aliases, accesses the network, or fabricates classes. Repeated plugin `autoload()` calls and repeated artifact inclusion are safe.

The existing Composer PSR-4 metadata remains the package declaration and Phase 3A adds `"ext-intl": "*"` to `require`, but Composer-generated runtime artifacts are forbidden. Unit and integration tests must prove the `void` method contract, single registration, repeated-call safety, ignored foreign/invalid names, containment, every packaged Phase 3A class, disabled-plugin inertness, and offline installed-package loading with no `vendor/` directory.

## Reference implementation assessment

Only tracked source at commit `585b4a76c6bacbbb2efcac4a6499a5b11eb247c5` was audited; user-data paths and submissions were excluded.

| Finding | Classification and decision |
|---|---|
| Repository/service/controller separation and PII-free exception logging | PRESERVE, with narrower boundaries. |
| Slug validation, regular-file/containment/symlink checks, locks and `0600` records | ADAPT to the strict schema. |
| Storage below Grav user data | ADAPT to the locator-backed stream. |
| Manual `require_once` class list | REJECT; `GoosializeLeadsPlugin::autoload()` guardedly requires the plugin-owned root `autoload.php`, which registers only `Grav\Plugin\GoosializeLeads\` → `classes/`. Composer PSR-4 metadata documents that namespace but is not a runtime loader; no package `vendor/autoload.php` or generated `vendor/composer/` tree exists, and offline installation/reflection must pass without `vendor/`. |
| Timestamp-oriented/predictable filenames and prior data shape | REJECT. |
| Management routes, sidebar, permissions, search/export/update/delete | DEFER_TO_LATER_PHASE (Phase 4). |
| Theme-owned Forms capture and direct notification | REJECT; replace with plugin-owned adapters and shared service after Phase 3C/3D probes. |

## Storage architecture

### Candidates and selection

One JSON file per Lead is selected: it gives a small corruption radius, same-directory atomic publication, deterministic recovery, independent migration, and test isolation. A consolidated JSON/YAML collection is rejected because every capture rewrites and locks all records. SQLite is deferred because it adds extension/operational dependency and a single-artifact blast radius. Flex Objects are deferred pending a later exact query/migration audit. Future Phase 4 scans records until an independently rebuildable index is justified.

Canonical root and layout:

```text
user-data://goosialize-leads/
  v1/
    .capture.lock
    records/YYYY/MM/<32-lowercase-hex-id>.json
    idempotency/<first-two-digest-characters>/<64-lowercase-hex-key-digest>.json
```

There is no mutable search index initially; idempotency sidecars are transactional mappings. Directories request `0700`; records, sidecars, lock and temporary files request `0600`, with effective modes verified or capture fails closed. Records are canonical UTF-8 JSON using exception-on-error, unescaped Unicode/slashes, stable key order, and one LF. Maximum serialized size is 32 KiB. Initial capacity assumption is 10,000 records/month directory; beyond that requires sharding/index review.

The full root, including sidecars, is the backup boundary. It lies outside the plugin package, survives upgrades, and is retained on uninstall by default. Retention duration and authorized erasure require later policy.

Reject a symlink at the root or any component. Walk existing components with `lstat`, require directories/regular files as appropriate, canonicalize parents, and enforce separator-aware containment. Externally supplied values never construct filenames.

### Atomicity and concurrency

The sole publication primitive is same-filesystem hard-link creation after a complete temporary write. Exact PHP 8.5.8 probing proved that `link($temporaryPath, $finalPath)` exposes the complete pre-written inode, preserves `0600`, and returns failure when the final path exists. Plain `rename()` is not the no-replace boundary, there is no overwrite fallback, and unsupported hard-link publication fails closed.

1. Resolve the absolute root; safely create and revalidate each directory, symlink status, containment, and effective `0700` mode.
2. Validate/open `v1/.capture.lock` at `0600`; acquire blocking `flock(LOCK_EX)`. It is the only Phase 3 lock.
3. Validate idempotency under the lock using the canonical sidecar contract.
4. Generate 16 random bytes and retry at most five ID collisions.
5. Exclusively create a random same-directory record `.tmp-<32-lowercase-hex-id>-<16-lowercase-hex>.json` or sidecar `.tmp-sidecar-<64-lowercase-hex-key-digest>-<16-lowercase-hex>.json` with `fopen(..., 'x+b')`, mode `0600`.
6. Serialize before writing; loop through all bytes, verify length, call/check `fflush` and available `fsync`, recheck regular-file/symlink/containment/mode state, then close the handle.
7. Publish only with `link($temporaryPath, $finalPath)`. Existing destination means collision: records regenerate within the bounded limit; sidecars validate the existing mapping. Never replace it.
8. Verify final regular file, inode, size, mode and containment, then unlink only the validated temporary name. Readers consume only canonical final filenames; temporary names are never records.
9. Publish the canonical sidecar by the same algorithm. If interrupted after record publication, return 503 and use the documented bounded recovery scan on retry.
10. Release in `finally`; remove only validated contained temporary files from the current attempt.

Unique submissions serialize and succeed. Same-key submissions create at most one record/sidecar; exact replay does no write or notification. Termination before link leaves a temp; after link but before temp unlink leaves a valid final plus temp; after record link but before sidecar link invokes recovery. Full disk, short write, permission/mode failure, malformed state, invalid filename, unsupported links, or collision exhaustion fails closed without replacing data. Process exit releases `flock`; no stale-lock deletion exists.

Current-request cleanup is step 10. Stale files left by terminated processes are handled only by the explicitly authorized Phase 3B CLI operation `bin/grav goosialize-leads:cleanup-temporaries`; anonymous capture, Forms, API, Admin2, and automatic startup never invoke it. `Application\StaleTemporaryCleanup` confirms authorization, supplies the fixed cleanup policy (24-hour minimum age, 100-entry maximum and two-second maximum), invokes only `Storage\TemporaryArtifactMaintenanceRepository`, handles its redacted result and emits stable redacted event/result codes. It never accesses the filesystem, paths, locks, symlinks, stored JSON or file contents directly.

The `FilesystemLeadRepository` implementation of that maintenance interface exclusively resolves and validates the storage root; acquires the same `v1/.capture.lock` and releases it in `finally`; scans only validated plugin-owned record year/month and idempotency-shard publication directories; and enforces the 100-entry/two-second bounds. Under that single lock it performs directory iteration, exact temporary-filename matching, `lstat`-style inspection, containment/mode/symlink validation, active-write detection, 24-hour stale eligibility, deletion and filesystem-exception translation. It never follows symlinks or scans arbitrary paths, never deletes/replaces/treats the lock as stale, skips recent or unverifiable entries, and fails closed on anomalies. This one repository-owned lock order cannot deadlock with publication and protects active writes. Canonical records and sidecars are never cleanup candidates.

Only `FilesystemLeadRepository` may unlink a verified stale temporary artifact. It returns `Storage\TemporaryArtifactMaintenanceResult` containing only `scanned_count`, `removed_count`, `skipped_recent_count`, `skipped_active_count`, `rejected_count`, `limit_reached`, `elapsed_limit_reached`, and stable result/error codes. It never returns absolute paths, filenames, contents, Lead data, raw keys, submitted PII, or unsafe exceptions. `StaleTemporaryCleanup` emits only `LEAD_TEMP_CLEANUP_COMPLETED` or `LEAD_TEMP_CLEANUP_FAILED` plus those redacted aggregates; capture clients receive no cleanup detail. Phase 3B tests preserve recent, wrong-name, symlinked, escaped, active-write, malformed, published-record and published-sidecar entries; remove a valid stale temp; enforce entry/time limits; verify result redaction and safe failure; and prove no unauthenticated request can invoke cleanup.

Hard-link atomicity is limited to the verified local same-filesystem contract. Directory-entry durability, directory `fsync`, network/overlay filesystems, and crash semantics require the Phase 3B probe; failure means installation is unsupported for capture and remains inert.

## Capture/API contract

Proposed endpoint: `POST /api/v1/goosialize-leads/capture`; collector path `/goosialize-leads/capture`. The documentation identifier is `goosialize_leads.capture`; FastRoute/collector has no route-name API.

It is intended to be anonymous via exact method-scoped public entry `POST {api_base}/goosialize-leads/capture`. A public surface is appropriate because visitors cannot require Admin2 credentials, but implementation is blocked until public-event stability, origin/CSRF and abuse controls pass review. No public read/update/delete/list route exists.

- Require `Content-Type: application/json` with optional UTF-8 charset; raw body maximum 16,384 bytes.
- Optional `Idempotency-Key`: 16–128 characters matching `[A-Za-z0-9._~-]+`; body keys are forbidden.
- Statuses: `201` created, `200` exact replay, `400` malformed/duplicate-key/non-object, `415` media, `413` size/depth, `422` validation/consent, `409` key conflict, `429` rate limited, `503` storage unavailable.
- HTTP `201` creation is `{"data":{"id":"<id>","status":"new","created_at":"<UTC>","replayed":false}}`: it returns the new Lead ID after one record and any sidecar are durable, and that original creation result may request one eligible notification attempt.
- HTTP `200` exact replay is `{"data":{"id":"0123456789abcdef0123456789abcdef","status":"new","created_at":"2026-07-27T10:20:30.123456Z","replayed":true}}`: the same envelope returns the original Lead ID, creates no second record, mutates no storage, attempts no second notification, and returns no submitted PII. The HTTP status plus Boolean `replayed` field are the stable success-result identifiers.
- Error is `{"error":{"code":"VALIDATION_FAILED","message":"Request validation failed.","errors":[{"code":"invalid_email","field":"email"}]}}`. Primitive errors retain their deterministic order. Only stable codes are returned; values, paths, traces and exception messages are hidden.
- All responses use `Content-Type: application/json`, `Cache-Control: no-store, max-age=0`, and `X-Content-Type-Options: nosniff`. Same key/different payload returns `409`/`IDEMPOTENCY_CONFLICT`; a malformed or inconsistent sidecar fails closed with redacted `503`/`STORAGE_UNAVAILABLE`, creates no Lead, performs no repair, and attempts no notification.
- Same-origin is default and the plugin adds no CORS origin. Origin must match the canonical site origin; proxy-aware calculation and missing-Origin behavior are assigned to the Phase 3C verification table. A session or CSRF token is not proof of legitimate anonymous capture.

Transport/adaptor codes are exactly `MALFORMED_JSON`, `DUPLICATE_JSON_KEY`, `OBJECT_REQUIRED`, `UNSUPPORTED_MEDIA_TYPE`, `PAYLOAD_TOO_LARGE`, `TOO_DEEP`, `VALIDATION_FAILED`, `INVALID_IDEMPOTENCY_KEY`, `IDEMPOTENCY_CONFLICT`, `RATE_LIMITED`, and `STORAGE_UNAVAILABLE`. `VALIDATION_FAILED` carries only the ordered primitive errors exhaustively defined in `docs/PHASE_3_LEAD_DATA_CONTRACT.md`; adapters map those codes without values, PII, translated primitive text, paths, traces, or exceptions.

## Theme-independent Forms capture contract

The API is not the only Phase 3 capture surface. The plugin will own `blueprints/forms/goosialize-leads-capture.yaml` and optional renderer `templates/forms/goosialize-leads-capture.html.twig`; the Forms plugin renders native fields/templates. The plugin explicitly loads/registers that named definition—never a theme page or template—and the definition uses processor `goosialize_leads_capture`. Exact Forms registration service calls and plugin-blueprint discovery are confined to the Phase 3C source probe; failure falls back to disabled Forms capture, not theme ownership.

A future `Http\FormsLeadCaptureAdapter` subscribes to the verified `onFormProcessed` event only for that exact processor and exact form name. Forms 9.1.13 performs nonce and blueprint validation before processors and checks honeypot in `onFormValidationProcessed`. The adapter re-allowlists submitted values, rejects server-field overrides, obtains trusted `source`, `form_name`, locale and consent version from `plugins.goosialize-leads.forms.capture`, and creates the same normalized `Application\CaptureCommand` as JSON. Neither adapter touches the repository; both call `LeadCaptureService`.

The definition contains only canonical submitted fields, a native nonce, honeypot, submit control, and no file field. Forms errors remain native field/form errors without echoed values. Capture errors map to a stable generic form error. Non-AJAX success uses a configured same-site 303 redirect with a non-PII success flag; no-JavaScript POST works fully. XHR is optional only where the exact `form-xhr` behavior is proven, returns a stable success/error shape, and never changes semantics. No theme-specific template, JavaScript, or Goosialize theme is required.

Forms derives an internal idempotency token from the Forms-generated unique submission ID plus trusted form name using HMAC-SHA-256; raw hidden values are never stored or used as paths. Same submission maps the shared replay result to the documented successful form outcome without another Lead, storage mutation, or notification; different submissions remain eligible Leads. Disabled plugin or missing/disabled Forms dependency registers no definition/processor and performs no write. Forms rendering and submission do not yet exist.

Phase 3C tests use only synthetic values and prove definition ownership, native rendering with no Goosialize theme, nonce rejection, honeypot rejection, allowlist, trusted attribution, validation errors, successful standard POST/303, no-JavaScript operation, supported XHR success/error or explicit unavailability, replay/concurrency behavior, shared service invocation, disabled behavior, and absence of direct repository access.

## Validation pipeline and idempotency

Order is: method/length transport checks; media type; byte-counted read; UTF-8 JSON parse at depth four; duplicate-member detection; root/field/depth shape; allowlist; types; NFC/whitespace/line normalization; semantic lengths and formats; consent; idempotency; record construction; atomic storage; response; PII-free log.

Maximum request fields are 11 allowlisted top-level members plus the consent child and five campaign children. PHP JSON overwrites duplicate keys, so Phase 3C must prove the raw duplicate detector defined in the verification table before decoding. Phase 3A owns and verifies `ext-intl`/NFC availability; later checkpoints rerun it only as regression coverage and always fail closed rather than silently skip. Controls/noncharacters are rejected except message LF. HTML is unsupported: tag-like markup is rejected, and later output must still escape. Only message is multiline, at 4,000 code points/8,000 bytes.

The opaque client key is not identity. Store only `SHA-256(key)` and an `HMAC-SHA-256` fingerprint of canonical normalized submitted fields. Do not store separate email/phone hashes. Canonical sidecars expire after 30 days but are removed only by authorized maintenance; capture never overwrites or deletes them. Exact reuse returns 200; changed payload returns 409; no key always creates a new legitimate Lead. Concurrent matches serialize. Replays consume rate limit and authorize no reads. The server HMAC secret uses the versioned Phase 3B contract and must pass the generation, rotation, backup and uninstall probe in the verification table.

## Class and dependency boundaries

All classes use `Grav\Plugin\GoosializeLeads`.

| Future class | Responsibility |
|---|---|
| `Domain\LeadRecord` | Immutable schema value; pure. |
| `Validation\LeadInputValidator`, `LeadNormalizer` | Pure deterministic validation/normalization. |
| `Domain\LeadIdGenerator` | Injected random-byte source. |
| `Storage\LeadRepository` | Capture/idempotency port. |
| `Storage\TemporaryArtifactMaintenanceRepository` | Narrow maintenance port accepting authorized policy bounds and returning only `TemporaryArtifactMaintenanceResult`; exposes no paths or file primitives. |
| `Storage\FilesystemLeadRepository` | Sole concrete filesystem-aware class; implements both `LeadRepository` and `TemporaryArtifactMaintenanceRepository`, exclusively owning storage-root/path/iteration/filename/`lstat`/symlink/lock/active-write/stale/deletion/mode handling, stored Lead/sidecar JSON, and filesystem exception translation. |
| `Application\LeadCaptureService` | Orchestrates validation, attribution, repository, fingerprint and notification port; no HTTP/filesystem. |
| `Application\StaleTemporaryCleanup` | Operator-only orchestration: confirms authorization, supplies age/entry/time policy, invokes only `TemporaryArtifactMaintenanceRepository`, and handles/emits its redacted result; no direct filesystem, lock, path, symlink, deletion, or JSON access. |
| `Storage\TemporaryArtifactMaintenanceResult` | Immutable redacted counts, limit flags and stable result/error codes; never paths, filenames, contents, Lead data, raw keys, PII, or unsafe exceptions. |
| `Console\CleanupTemporariesCommand` | Explicit CLI entry point that invokes `StaleTemporaryCleanup`; no filesystem access and no HTTP, Forms, or Admin2 exposure. |
| `Http\LeadCaptureController` | JSON/PSR-7 adapter; translates requests/results only. |
| `Http\FormsLeadCaptureAdapter` | Forms event adapter; maps native validated form data/results only. |
| `Application\CaptureCommand` | Adapter-neutral normalized submitted fields plus trusted attribution. |
| `Application\CaptureResult` and typed errors | Stable non-PII boundary values. |
| `Notification\LeadNotificationService` | Phase 3 adapter invoked after durable capture; failure never rolls back a Lead. It uses the verified Email 5.0.3 service boundary and Phase 3D probe. |

Dependencies point HTTP/Console → application → domain/storage ports; concrete adapters implement ports. `StaleTemporaryCleanup` depends only on `TemporaryArtifactMaintenanceRepository`. Only composition and the verified controller constructor may access Grav services; only `FilesystemLeadRepository` resolves the storage locator or touches filesystem paths, locks, permissions, files, and stored Lead/sidecar JSON. Capture, Forms, API, notification, console and maintenance orchestration services never touch storage files directly.

## Notification contract

Phase 3 attempts one notification only after a newly created Lead and sidecar are durable. Plugin-owned keys are `plugins.goosialize-leads.notifications.enabled`, `plugins.goosialize-leads.notifications.to`, and `plugins.goosialize-leads.notifications.from`; startup/runtime validation requires syntactically valid configured addresses when enabled. No address is hardcoded. The adapter uses Email 5.0.3's verified `grav['Email']` service: `Email::message()` constructs and returns a `Message`, and that object is passed separately to `Email::send()`, whose integer result represents the delivery attempt. The adapter never treats `message()` as a delivery result. Missing/disabled service or invalid configuration returns a typed notification failure after storage.

The fixed non-PII subject is “New Goosialize Lead”. Plain-text body allowlist is exactly Lead ID, trusted source, trusted form name, created timestamp, and resource ID when non-null. It excludes names, email, telephone, company, message, campaign/query data, consent text, raw payload, IP, user agent, paths, traces, exceptions, recipients, and attachments.

Only the request whose repository result is `created` may attempt once. Exact replay never sends; concurrent duplicates produce at most one attempt; a distinct valid submission creates a new Lead and one attempt. Failure never rolls back storage or creates another Lead. Phase 3 performs zero automatic retries; replay is not retry. Manual retry and an outbox are deferred to Phase 7.

Observability emits stable `LEAD_NOTIFICATION_FAILED`, Lead ID, trusted form name/source, and UTC attempt timestamp only. It never logs recipient, submitted PII, raw Email-plugin messages/debug, or transport exceptions. Tests cover disabled, invalid config, success, service failure, replay, concurrency, legitimate later submission, no second Lead, one-attempt ordering, log redaction, and exact minimized content.

## Threat model and privacy

| Threat | Prevention | Detection/test | Residual |
|---|---|---|---|
| Traversal/symlink/overwrite | server paths, containment, `lstat`, exclusive temp plus hard-link no-replace publication | adversarial existing-target/link/symlink tests | unsupported filesystems fail closed |
| Predictable IDs/enumeration | 128 random bits; no read route | entropy/route inventory | returned ID authorizes nothing |
| Oversize/deep/malformed/DoS | byte/depth/field caps, strict UTF-8, rate controls | fuzz/full-disk/limit tests | distributed quotas later |
| Log/stored script/PII leakage | stable codes, no values, plain text plus output escaping | log/debug/render tests | infrastructure access logs |
| Replay/concurrency/partial writes | HMAC, global lock, exclusive temp/hard-link publication | process/fault tests | directory crash guarantee unresolved |
| Duplicate notification delivery | persistence/idempotency resolve first; only original `created` result may request one attempt; replay never requests one; global lock makes concurrent duplicates produce at most one attempt; capture retry is not notification retry; zero Phase 3 automatic retries | simultaneous identical-key and sequential-replay tests; Email-adapter invocation count; legitimate-new-submission test; failure creates neither second Lead nor implicit retry | uncertain transport failure can leave provider-delivery ambiguity; exactly-once external delivery is not claimed without provider idempotency/outbox, owned by Phase 7 |
| Malformed sidecar recovery | strict schema/size/digest/Lead-reference/containment/symlink validation; no automatic overwrite, silent replacement, or second Lead | malformed JSON, unsupported schema, invalid key/payload digest, invalid Lead ID, missing Lead, digest mismatch, oversize, symlink, partial temp, and authorized non-overwrite recovery tests | capture fails closed for that key until authorized Phase 7 maintenance; unauthenticated repair is prohibited, availability may be reduced, and the malformed source is preserved for PII-safe diagnosis |
| Forged source/consent | trusted config/server fields; literal consent | spoofing tests | assent is not identity/compliance proof |
| Backup/uninstall exposure/loss | restrictive modes, full-root backup, retain by default | mode/lifecycle tests | operator backup protection |

Never store raw payload, IP, user agent, full URL/query, arbitrary custom data, consent prose, credentials, or secrets. Encryption at rest is the environment's Phase 3 responsibility; application encryption is deferred until a key lifecycle is approved. No legal or compliance claim is made.

## Tests and implementation checkpoints

Use only synthetic `.test` data. Every PASS marker below maps to its named assertions; unavailable required coverage stops the checkpoint. Every checkpoint uses its named branch created from current `main`; the prior checkpoint must already be merged, and that main commit must be the direct parent of the checkpoint commit. Every listed documentation path is a required update, not merely permitted. Each checkpoint requires a clean pre-commit review, all listed tests, exactly one approved commit with the stated subject, then a separate read-only merge review and local `git merge --ff-only <branch>`. No remote, push, publish, or deploy is allowed. Post-merge, rerun earlier Phase 2 and Phase 3 markers and prove clean status, retained branch, unchanged reference, and package determinism.

### Phase 3A — Lead data and validation primitives

- Branch: `feat/phase-3a-lead-data-validation`, created from `main` at the future post-correction HEAD; purpose: handwritten package autoload plus pure command, record, ID/clock, normalization and validation. Subject: `feat: add Phase 3 lead validation primitives`.
- Exact seven new files: `autoload.php`, `classes/Application/CaptureCommand.php`, `classes/Domain/LeadRecord.php`, `classes/Domain/LeadIdGenerator.php`, `classes/Validation/LeadInputValidator.php`, `classes/Validation/LeadNormalizer.php`, `tests/unit/phase-3a-lead-data-validation.php`.
- Exact nine modified files: `goosialize-leads.php`, `composer.json`, `packaging/package-files.txt`, `tests/integration/clean-grav-plugin-load.sh`, `tests/integration/installable-plugin-package.sh`, `tests/integration/phase-2d-entry-points.sh`, `README.md`, `CHANGELOG.md`, `docs/OFFICIAL_VERIFICATION_LOG.md`.
- Package manifest source of truth: the existing nine paths plus root `autoload.php` and the five runtime-class paths, for exactly 15 packaged regular files. The unit test remains repository-only. The old SHA-256 `87f4ffda0bb8ceaac09d79d696a7364dae8cb5fe7323b4f3fe16bfa6f823ae6b` must change; two consecutive builds must be byte-identical and Phase 3A records the new SHA-256. No stale nine-file assertion or `vendor/` path may remain.
- `tests/integration/installable-plugin-package.sh` derives and asserts the exact 15-file manifest, count and installed tree; verifies the loader and every runtime class are installed; invokes the installed loader offline; reflects every class; proves no `vendor/` directory; and preserves deterministic ZIP equality, ZIP safety, immutable input, offline GPM direct install, installed hashes, enabled/disabled load, and cleanup.
- `tests/integration/phase-2d-entry-points.sh` asserts the updated manifest/count while preserving exactly two inert subscriptions, zero functional routes, Twig behavior, the inert Admin2 component, disabled behavior, Phase 2B/2C regressions, and proof that packaged Phase 3A classes activate no Lead behavior.
- `tests/integration/clean-grav-plugin-load.sh` preserves clean Grav 2.0.12 enabled/disabled loading, invokes the packaged plugin autoload method, loads and reflects every Phase 3A class, proves no missing loader/class and no functional route, filesystem storage, Forms capture, notification, or Admin2 management.
- Marker assertions: `PASS_PHASE_3A_AUTOLOAD` covers method signature, idempotence, prefix/name/containment rejection and every source-tree class; `PASS_PHASE_3A_SCHEMA` covers exact command/record shape, ID/clock and canonical serialization; `PASS_PHASE_3A_VALIDATION` covers the exhaustive table, per-field priority, accumulation and byte-identical ordering; `PASS_PHASE_3A_UNICODE` covers the pinned `ext-intl`/`Normalizer` probe and NFC/failure cases; `PASS_PHASE_3A_PACKAGE` covers exact manifest/tree, no vendor, two identical builds, new hash, offline install and installed-class reflection; `PASS_PHASE_2B_REGRESSION`, `PASS_PHASE_2C_REGRESSION`, and `PASS_PHASE_2D_REGRESSION` require their complete existing suites and final markers.
- Forbidden: filesystem Lead persistence, locks/publication, idempotency-sidecar persistence, stale cleanup, functional HTTP route/controller, Forms processing, Email/notification, Admin2 management, permissions, real-data migration, and theme/core/API plugin modification.
- The checkpoint retains one commit directly parented by `main`, clean pre-commit review, a separate read-only merge review, local `git merge --ff-only`, the retained branch, no remote/push/publish/deploy, and all post-merge regressions.

### Phase 3B — secure filesystem repository and idempotency index

- Branch: `feat/phase-3b-secure-lead-storage`; purpose: sole concrete filesystem repository, hard-link publication, lock, record/sidecar recovery and authorized stale-artifact maintenance.
- New files: `classes/Storage/LeadRepository.php`, `classes/Storage/TemporaryArtifactMaintenanceRepository.php`, `classes/Storage/FilesystemLeadRepository.php`, `classes/Storage/StorageException.php`, `classes/Storage/TemporaryArtifactMaintenanceResult.php`, `classes/Application/StaleTemporaryCleanup.php`, `classes/Console/CleanupTemporariesCommand.php`, `tests/unit/phase-3b-sidecar-schema.php`, `tests/unit/phase-3b-stale-temporary-cleanup.php`, `tests/integration/phase-3b-secure-storage.sh`.
- Modified files: `goosialize-leads.php`, `composer.json`, `packaging/package-files.txt`, `README.md`, `CHANGELOG.md`, `docs/OFFICIAL_VERIFICATION_LOG.md`.
- Forbidden: HTTP, Forms, Email, Admin2, theme/core/API plugin. Tests: interface-only cleanup orchestration with no filesystem calls; redacted result shape; repository-exclusive containment, iteration, filename, `lstat`, symlink, lock, active-write, stale-age, bounds, deletion, permissions and exception translation; `0700/0600`, exclusive temp, fsync, hard-link no-replace, existing target, unsupported link, fault/crash/full-disk/collision cases; sidecar schema/replay/expiry/corruption/recovery; concurrent unique/same-key; and recent/stale/wrong-name/escape/malformed/published cleanup cases. Markers: `PASS_PHASE_3B_NO_REPLACE`, `PASS_PHASE_3B_ATOMIC_STORAGE`, `PASS_PHASE_3B_IDEMPOTENCY`, `PASS_PHASE_3B_CONCURRENCY`, `PASS_PHASE_3B_STALE_TEMP_CLEANUP`. Subject: `feat: add secure Lead filesystem repository`.

### Phase 3C — theme-independent Forms and JSON API capture adapters

- Branch: `feat/phase-3c-capture-adapters`; purpose: shared capture service plus both adapters.
- New files: `classes/Application/LeadCaptureService.php`, `classes/Application/CaptureResult.php`, `classes/Http/LeadCaptureController.php`, `classes/Http/FormsLeadCaptureAdapter.php`, `blueprints/forms/goosialize-leads-capture.yaml`, `templates/forms/goosialize-leads-capture.html.twig`, `tests/integration/phase-3c-json-api.sh`, `tests/integration/phase-3c-grav-forms.sh`.
- Modified files: `goosialize-leads.php`, `goosialize-leads.yaml`, `blueprints.yaml`, `composer.json`, `packaging/package-files.txt`, `README.md`, `CHANGELOG.md`, `docs/OFFICIAL_VERIFICATION_LOG.md`.
- Forbidden: Email delivery, Admin2, public read/update/delete, theme/core/API/Forms plugin. Tests: exact route/public/cache/origin/CSRF/rate/error contracts; Forms registration/rendering/nonce/honeypot/standard POST/303/no-JS/XHR decision/errors; common command/service; disabled dependency/plugin; no direct adapter storage. Markers: `PASS_PHASE_3C_SHARED_SERVICE`, `PASS_PHASE_3C_JSON_API`, `PASS_PHASE_3C_NATIVE_FORMS`, `PASS_PHASE_3C_DISABLED_INERT`. Subject: `feat: add Phase 3 capture adapters`.

### Phase 3D — notification adapter and capture orchestration

- Branch: `feat/phase-3d-lead-notification`; purpose: one minimized post-persistence notification attempt.
- New files: `classes/Notification/LeadNotificationService.php`, `classes/Notification/NotificationResult.php`, `tests/integration/phase-3d-notification.php`.
- Modified files: `classes/Application/LeadCaptureService.php`, `goosialize-leads.yaml`, `blueprints.yaml`, `composer.json`, `packaging/package-files.txt`, `README.md`, `CHANGELOG.md`, `docs/OFFICIAL_VERIFICATION_LOG.md`.
- Forbidden: retries/outbox, attachments, Admin2, theme/core/API/Forms/Email plugin modification. Tests: disabled/invalid config, success/failure ordering, content allowlist, redacted log, replay/concurrent suppression, legitimate new submission. Markers: `PASS_PHASE_3D_NOTIFICATION_ORDER`, `PASS_PHASE_3D_NOTIFICATION_MINIMIZATION`, `PASS_PHASE_3D_NOTIFICATION_REPLAY_SUPPRESSION`. Subject: `feat: add minimized Lead notifications`.

### Phase 3E — complete Phase 3 security and integration acceptance

- Branch: `test/phase-3e-security-acceptance`; purpose: acceptance only.
- New files: `tests/integration/phase-3e-security-acceptance.sh`, `docs/PHASE_3_ACCEPTANCE_TEST.md`.
- Modified files: `README.md`, `CHANGELOG.md`, `docs/OFFICIAL_VERIFICATION_LOG.md`.
- Forbidden: runtime classes, configs, routes, Forms definitions, Email, Admin2, theme/core/plugin changes. Tests: complete Forms/API/repository/sidecar/notification matrix; sole concrete filesystem ownership and no direct adapter/service filesystem access; authorized bounded stale cleanup, active-write/published-artifact preservation and redacted results; adversarial/fault/concurrency, disabled runtime, PII scans, no public data route, clean no-network install, package hash/regressions. Markers: `PASS_PHASE_3E_SECURITY`, `PASS_PHASE_3E_FORMS_API`, `PASS_PHASE_3E_STORAGE_IDEMPOTENCY`, `PASS_PHASE_3E_NOTIFICATION`, `PASS_PHASE_3E_PACKAGE`, `PASS_PHASE_3_ACCEPTANCE`. Subject: `test: verify Phase 3 secure capture and storage`.

## Acceptance and deferred work

Completion requires all checkpoints, exact-version capture and storage assertions, duplicate/concurrency/mode/containment/symlink/redaction/origin/CSRF/rate/notification/disabled/clean-install/upgrade-survival tests, no public management route, and closure of every blocker.

Phase 4 owns reads, permissions, search/filter/index/pagination, status transitions, edits, deletion/retention execution, CSV, Admin2 and accessibility. Phase 5 owns protected delivery; Phase 6 final hardening; Phase 7 migrations/upgrade/removal/recovery; Phase 8–9 marketplace/release.

## OFFICIAL_VERIFICATION_REQUIRED

| Checkpoint | Exact question and probe | Pass / fail / fallback |
|---|---|---|
| 3A | Implement the specified handwritten root `autoload.php`; require it once from `GoosializeLeadsPlugin::autoload(): void`; update the exact 15-file manifest and three integration scripts; install offline through GPM and reflect every class with no `vendor/`. | Pass: idempotent contained loader, exact manifest/tree, byte-identical builds, new recorded hash, installed reflection and Phase 2 regressions; fail: escape, foreign lookup, missing/extra file, generated dependency, nondeterminism, or activated functional behavior; fallback: no Phase 3A runtime classes and checkpoint failure. |
| 3A | Probe `extension_loaded('intl')`, `class_exists('Normalizer')`, `Normalizer::FORM_C`, `normalize()` and `isNormalized()` in the pinned image using decomposed `e` plus `U+0301`, expecting `U+00E9`; declare `"ext-intl": "*"` and test capability before validation. | Pass: all capabilities exist and exact NFC output verifies; fail: absence, false return, mismatch, or unverifiable result; fallback: fail closed with `unicode_normalization_unavailable` or `unicode_normalization_failed`, including ASCII input, without runtime detail or submitted values. |
| 3A | Execute every row of the exhaustive primitive error table, including multi-error permutations, and the exact conservative ASCII email examples/boundaries. | Pass: one primary error per field, fixed canonical/unknown/cross-field ordering and byte-identical results; fail: unmapped rejection, unstable order, value/PII leakage, or grammar mismatch; fallback: checkpoint failure and no capture adapters. |
| 3B | Probe locator path, effective modes, `fsync`, `flock`, hard links, crash points, and the authorized 24-hour/100-entry/two-second stale-temp cleanup bounds on the supported deployment filesystem. | Pass: exact containment/mode/no-replace/fault plus recent/stale/active/symlink/limit/authorization tests; fail: any overwrite, active-write removal, unauthenticated invocation, path escape, or unsupported guarantee; fallback: capture disabled and cleanup unavailable. |
| 3B | Generate/validate 32-byte HMAC secret; simulate rotation/backup against sidecars. | Pass: old sidecars remain verifiable through approved key-version handling; fail: loss/ambiguity; fallback: refuse capture. |
| 3C | Prove plugin-owned Forms blueprint registration, native templates, `onFormProcessed`, standard POST/303 and exact XHR mapping in Form 9.1.13. | Pass: theme-free standard flow plus asserted XHR or explicit unsupported result; fail: theme ownership/bypass; fallback: disable Forms adapter, never replace architecture. |
| 3C | Prove `onApiCollectPublicRoutes`, cache invalidation, proxy-aware same-origin/missing-Origin/CSRF policy, pre-buffer byte/duplicate detector, and endpoint abuse control. | Pass: exact anonymous security/status tests; fail: bypass/fail-open endpoint; fallback: API adapter disabled. |
| 3D | Probe Email 5.0.3 service availability, `message(): Message`, exact accepted `send(): int` statuses, construction/delivery separation, and redaction without using plugin debug output. | Pass: source signatures plus one minimized post-durable attempt with accepted status interpretation and redacted failure; fail: signature/status mismatch, leakage, duplicate, or ordering defect; fallback: notification disabled while stored Lead remains valid. |
| 3E | Re-probe retention, backup, update/removal and supported filesystem in clean-install regression. | Pass: data boundary and package invariants; fail: loss/exposure; fallback: block Phase 3 completion. |

Licensing and marketplace publication remain Phase 8 decisions and are not hidden Phase 3 core contracts.
