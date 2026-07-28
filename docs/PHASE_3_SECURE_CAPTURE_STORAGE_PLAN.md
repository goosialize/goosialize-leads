# Phase 3 Secure Capture and Storage Plan

Planning status: normative through Phase 3B; Phase 3A runtime primitives exist and Phase 3B runtime behavior is not yet implemented.

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
| Forms lifecycle | `/app/www/public/user/plugins/form/blueprints.yaml:1-20` (Form 9.1.14); `/app/www/public/user/plugins/form/classes/Form.php:880-1024`, `post()`; `/app/www/public/user/plugins/form/form.php:276-282,798-806` | Nonce and blueprint validation run before `onFormProcessed`; honeypot is checked; named process actions receive form/action/params; redirects are honored after actions. | Exact-version source proves the Phase 3C.1 standard POST action model. XHR is deferred to Phase 3C.2. |
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

Current-request cleanup is step 10 and removes only a validated temporary created by the same call. Phase 3B never scans publication directories and never deletes a temporary left by another process. Stale/post-crash cleanup, authorization, active-write detection, bounded scans, maintenance results/events, and a CLI entry point are deferred together to Phase 7. Anonymous capture, Forms, API, Admin2, plugin startup, and Phase 3B invoke no cleanup operation beyond same-call `finally` cleanup.

Hard-link atomicity is limited to the verified local same-filesystem contract. Phase 3B guarantees process-visible atomic no-replace publication, not persistence of directory entries across power loss. Directory `fsync`, network/overlay filesystem crash semantics, scheduled cleanup, and post-crash temporary cleanup are explicit Phase 3B non-goals. Absence of directory `fsync` is not a runtime failure. File contents must still be completely written, flushed, and successfully `fsync()`ed before publication. Tests prove that readers see either no final entry or one complete final entry and document that power-loss durability remains an operator/filesystem responsibility.

## Capture/API contract

Proposed endpoint: `POST /api/v1/goosialize-leads/capture`; collector path `/goosialize-leads/capture`. The documentation identifier is `goosialize_leads.capture`; FastRoute/collector has no route-name API.

The exact anonymous route, mandatory idempotency header, transport checks, internal result codes, public response bodies, Origin policy, rate limiting, and safe failure behavior are locked by the normative Phase 3C.2 section below. No public read/update/delete/list route exists. A session or CSRF token is not proof of legitimate anonymous capture.

## Theme-independent Forms capture contract

Phase 3C is split. Phase 3C.1 implements only standard server-rendered Grav Forms POST processing through the source-proven custom process action defined below. Sites author forms with the installed Forms plugin and add process action `goosialize_leads_capture`; the plugin adds no form definition, renderer, template, or JavaScript. Phase 3C.2 owns the public JSON API, XHR/AJAX, raw JSON enforcement, Origin/proxy policy, abuse control, rate limiting, API status codes, and API response bodies.

Forms 9.1.14 validates its built-in `form-nonce`, blueprint data, and honeypot before firing `onFormProcessed`. The Phase 3C.1 adapter handles only action `goosialize_leads_capture`, calls the shared application service, and either supplies a validated same-site 303 redirect or sets one generic Forms error and stops further actions. Missing or invalid nonce never fires the action and therefore never invokes persistence. The exact API, field, configuration, idempotency, test, and package contracts follow in the normative Phase 3C.1 section.

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
| `Validation\ValidationError`, `ValidationResult` | Immutable stable primitive error and operation-result values; pure. |
| `Domain\LeadIdGenerator` | Injected random-byte source. |
| `Storage\LeadRepository` | Capture/idempotency port. |
| `Storage\PersistenceRequest`, `Storage\PersistenceResult` | Immutable Phase 3B repository boundary values. |
| `Storage\FilesystemLeadRepository` | Sole concrete filesystem-aware class; implements `LeadRepository` and exclusively owns storage-root/path/filename/`lstat`/symlink/lock/current-write/current-temp/mode handling, stored Lead/sidecar JSON, and filesystem exception translation. |
| `Application\LeadCaptureService` | Orchestrates validation, attribution, repository, fingerprint and notification port; no HTTP/filesystem. |
| `Application\LeadPersistenceCoordinator` | Sole Phase 3B orchestrator connecting Phase 3A validation/record creation to the repository. |
| `Security\IdempotencyKeyRing` | Validates required external key configuration and derives/verifies non-secret digests. |
| `Http\LeadCaptureController` | JSON/PSR-7 adapter; translates requests/results only. |
| `Http\FormsLeadCaptureAdapter` | Forms event adapter; maps native validated form data/results only. |
| `Application\CaptureCommand` | Adapter-neutral normalized submitted fields plus trusted attribution. |
| `Application\CaptureResult` and typed errors | Stable non-PII boundary values. |
| `Notification\LeadNotificationService` | Phase 3 adapter invoked after durable capture; failure never rolls back a Lead. It uses the verified Email 5.0.3 service boundary and Phase 3D probe. |

Dependencies point HTTP → application → domain/storage ports; concrete adapters implement ports. Only composition may access Grav services; only `FilesystemLeadRepository` resolves the storage locator or touches filesystem paths, locks, permissions, files, and stored Lead/sidecar JSON. Capture, Forms, API, notification, and application services never touch storage files directly.

### Normative Phase 3A public API

All Phase 3A classes use prefix `Grav\Plugin\GoosializeLeads\`, are `final`, expose no public properties or setters, and are immutable after construction. Returned arrays are copy-on-write values and never references to internal properties. Expected submitted-data, generated-data, and serialization failures return `ValidationResult::failure()`; they do not throw. Programmer misuse and impossible invariant states throw `\InvalidArgumentException` with no submitted value, PII, path, credential, or unsafe exception detail. Entropy or clock callables that throw `\Throwable` are caught and mapped respectively to `invalid_generated_id` or `invalid_timestamp`; their original type, message, and trace are not exposed.

“Permitted callers” means the exact production class ownership and call-flow contract. It is an architectural restriction verified by tests and review; PHP public visibility does not enforce caller identity at runtime. Production creators construct an object or invoke its factory; production method consumers invoke operations or consume accessors/results. Test access is separate: only `tests/unit/phase-3a-lead-data-validation.php`, `tests/integration/clean-grav-plugin-load.sh`, `tests/integration/installable-plugin-package.sh`, and `tests/integration/phase-2d-entry-points.sh` may invoke public APIs directly for Phase 3A reflection and behavioral verification. Test code is not a production caller and never substitutes for absent production ownership.

| Class and path | Construction and exact public methods | Exact types, results and callers |
|---|---|---|
| `Application\CaptureCommand`; `classes/Application/CaptureCommand.php` | `private function __construct(array $submitted, array $trusted)`; `public static function fromValidated(array $submitted, array $trusted): self`; `public function submitted(): array`; `public function trusted(): array`; `public function toArray(): array` | Constructor and factory PHPDoc use the exact shapes below. Production creator: `Grav\Plugin\GoosializeLeads\Validation\LeadInputValidator::validate()` invokes `fromValidated()` after successful canonical validation. Production accessor consumer: `Grav\Plugin\GoosializeLeads\Domain\LeadRecord::fromCommand()` consumes `submitted()`, `trusted()`, and `toArray()` to build the canonical record. |
| `Validation\ValidationError`; `classes/Validation/ValidationError.php` | `public function __construct(string $code, ?string $field)`; `public function code(): string`; `public function field(): ?string`; `public function toArray(): array` | Constructor accepts only a code in the exhaustive Phase 3A table and a canonical field or `null`, otherwise throws `\InvalidArgumentException`. Production creators are exactly `Grav\Plugin\GoosializeLeads\Validation\LeadInputValidator::validate()`, `Grav\Plugin\GoosializeLeads\Domain\LeadIdGenerator::generate()`, `Grav\Plugin\GoosializeLeads\Domain\LeadRecord::fromCommand()`, and `Grav\Plugin\GoosializeLeads\Domain\LeadRecord::serialize()`. Production consumer: `Grav\Plugin\GoosializeLeads\Validation\ValidationResult::failure()` consumes the ordered objects; it originates no field predicate. |
| `Validation\ValidationResult`; `classes/Validation/ValidationResult.php` | <code>private function __construct(bool $valid, object&#124;string&#124;null $value, array $errors)</code>; <code>public static function success(object&#124;string $value): self</code>; `public static function failure(array $errors): self`; `public function isValid(): bool`; <code>public function value(): object&#124;string&#124;null</code>; `public function errors(): array`; `public function errorsAsArray(): array` | Constructor/failure PHPDoc uses `@param list<ValidationError> $errors`; accessors preserve exact order. Production creators are exactly `Grav\Plugin\GoosializeLeads\Validation\LeadInputValidator::validate()`, `Grav\Plugin\GoosializeLeads\Domain\LeadIdGenerator::generate()`, `Grav\Plugin\GoosializeLeads\Domain\LeadRecord::fromCommand()`, and `Grav\Plugin\GoosializeLeads\Domain\LeadRecord::serialize()`. Production consumer: `Grav\Plugin\GoosializeLeads\Domain\LeadRecord::fromCommand()` consumes the result from `LeadIdGenerator::generate()`. Validation, record-factory, and serialization results have no production consumer in Phase 3A. |
| `Validation\LeadNormalizer`; `classes/Validation/LeadNormalizer.php` | `public function __construct()`; `public function unicodeCapabilityAvailable(): bool`; `public function normalizeNfc(string $value): ?string`; `public function normalizeWhitespace(string $field, string $value): string`; `public function normalizeEmail(string $value): string` | Stateless. No production creator in Phase 3A. Production method consumer: `Grav\Plugin\GoosializeLeads\Validation\LeadInputValidator::validate()` invokes all four methods as required by field processing. Direct construction during this checkpoint is limited to the approved tests. |
| `Validation\LeadInputValidator`; `classes/Validation/LeadInputValidator.php` | `public function __construct(LeadNormalizer $normalizer)`; `public function validate(mixed $submitted, array $trusted): ValidationResult` | Stateless. No production caller in Phase 3A. Approved tests construct it with `Grav\Plugin\GoosializeLeads\Validation\LeadNormalizer` and invoke `validate()` directly. Production invocation is deferred; Phase 3B must explicitly amend this contract before assigning a caller, and no unnamed caller is implicitly authorized. |
| `Domain\LeadIdGenerator`; `classes/Domain/LeadIdGenerator.php` | `public function __construct()`; `public function generate(callable $entropy): ValidationResult` | Production creator and operation caller: `Grav\Plugin\GoosializeLeads\Domain\LeadRecord::fromCommand()` constructs it, supplies the received entropy callable, and invokes `generate()`. Production result consumer: the same `LeadRecord::fromCommand()` consumes its `ValidationResult`. No other production caller exists in Phase 3A. |
| `Domain\LeadRecord`; `classes/Domain/LeadRecord.php` | `private function __construct(array $data)`; `public static function fromCommand(CaptureCommand $command, callable $entropy, callable $clock): ValidationResult`; `public function id(): string`; `public function capturedAt(): string`; `public function status(): string`; `public function data(): array`; `public function toArray(): array`; `public function serialize(LeadRecord $record): ValidationResult` | No production caller in Phase 3A. Approved tests directly invoke `fromCommand()`, supply entropy/clock, invoke the accessors and `serialize()`, and consume their results. Production invocation is deferred; Phase 3B must explicitly amend this contract before assigning a caller, and no unnamed caller is implicitly authorized. Internally, `serialize()` consumes the receiver's `toArray()`. |

The exact Phase 3A production call graph is: no production orchestrator exists; therefore creation of `LeadNormalizer`, creation/invocation of `LeadInputValidator`, consumption of its returned `ValidationResult`, invocation of `LeadRecord::fromCommand()`, supply of entropy/clock, consumption of the record result, invocation of `LeadRecord::serialize()`, and consumption of the serialized-string result are all `none in Phase 3A`. Within the implemented primitives, `LeadInputValidator::validate()` consumes `LeadNormalizer`, creates `ValidationError`/`ValidationResult`, and alone invokes `CaptureCommand::fromValidated()`; `LeadRecord::fromCommand()` consumes `CaptureCommand` accessors, constructs/invokes `LeadIdGenerator`, consumes its `ValidationResult`, and may create record errors/results; `LeadRecord::serialize()` consumes its receiver and may create serialization errors/results; `ValidationResult::failure()` consumes but does not originate `ValidationError`. The approved tests separately supply the absent entry-point calls, entropy, and clock.

`ValidationResult` is the sole Phase 3A operation-result representation. `LeadInputValidator::validate()` succeeds only with `CaptureCommand`; `LeadIdGenerator::generate()` succeeds only with the ID string used internally by record creation; `LeadRecord::fromCommand()` succeeds only with `LeadRecord`; and `LeadRecord::serialize()` succeeds only with a canonical string. Public method reflection tests assert visibility, static status, parameters, declared types, return types and the PHPDoc shapes above.

`ValidationResult::success()` accepts only `CaptureCommand`, `LeadRecord`, or a string. Other object classes are programmer misuse and throw `\InvalidArgumentException`. Strings are accepted for the internal 32-lowercase-hex ID result and canonical JSON serialization result; the producing method fixes which string contract applies. `ValidationResult::failure()` requires a non-empty zero-based list containing only `ValidationError` objects and preserves duplicates and order exactly.

`CaptureCommand::fromValidated()` uses this exact submitted shape and order:

```php
array{
    consent: array{granted:true},
    full_name:?string,
    email:?string,
    phone:?string,
    company:?string,
    message:?string,
    resource_id:?string,
    source_path:?string,
    campaign:?array{
        utm_source:?string,
        utm_medium:?string,
        utm_campaign:?string,
        utm_term:?string,
        utm_content:?string
    }
}
```

The exact trusted shape and order is:

```php
array{
    source:string,
    form_name:string,
    locale:?string,
    consent_version:string
}
```

`CaptureCommand::toArray()` returns exact key order `source`, `form_name`, `locale`, `consent`, `full_name`, `email`, `phone`, `company`, `message`, `resource_id`, `source_path`, `campaign`; its consent object contains only `granted`, and campaign retains its five-key order when non-null. No raw body, unknown field, first/last-name input, trusted override, transport metadata, IP, user agent, full URL/query, credential, secret, or consent prose is retained.

`LeadInputValidator::validate()` requires `$trusted` to match the exact trusted shape above. An absent `consent_version` is the one expected configuration failure and returns `consent_version_missing`; a present non-string or grammar-invalid value returns `invalid_consent_version`. Other missing, extra, misordered, or incorrectly typed trusted keys are programmer misuse and throw `\InvalidArgumentException`; visitor attempts to submit trusted names remain ordinary `trusted_field_override` failures. A non-array `$submitted` returns only `object_required`. All other expected submitted-input failures use the exhaustive table, at most one primary error per field, canonical known-field order, normalized lexical unknown-field order, then fixed cross-field order.

`LeadNormalizer::normalizeWhitespace()` accepts only `full_name`, `first_name`, `last_name`, `company`, `message`, `campaign.utm_source`, `campaign.utm_medium`, `campaign.utm_campaign`, `campaign.utm_term`, or `campaign.utm_content`; any other field is programmer misuse and throws `\InvalidArgumentException`. It trims outer horizontal whitespace for every accepted field. It converts CRLF and CR to LF only for `message`. It collapses each internal horizontal-whitespace run to one U+0020 only for the three name inputs and `company`; it otherwise preserves internal characters. `normalizeEmail()` trims outer ASCII horizontal whitespace; when the result contains exactly one `@`, it preserves the local substring byte-for-byte and lowercases only ASCII `A`–`Z` in the domain substring, and otherwise returns the trimmed value unchanged for validator rejection.

`LeadRecord::fromCommand()` constructs a `LeadIdGenerator` and calls `generate()` once. It then invokes `$clock` exactly once. A thrown exception or value that is not `\DateTimeInterface` maps to `invalid_timestamp`. It copies mutable `\DateTime`, converts to `new \DateTimeZone('UTC')`, and formats exactly `Y-m-d\TH:i:s.u\Z`; no system clock or default timezone is consulted. The same timestamp populates `created_at`, `updated_at`, and `consent.captured_at`. The exact record and nested key order is the canonical example in the data contract. Initial schema version/status/revision are `1`/`new`/`1`.

Phase 3A has no idempotency header or persistence adapter, so record creation sets `idempotency.key_version`, `idempotency.key_hash`, and `idempotency.payload_fingerprint` to `null` in that exact order. `id()` returns `data()['id']`, `capturedAt()` returns `data()['created_at']`, `status()` returns `data()['status']`, and `data()` and `toArray()` each return the same complete canonical array copy.

`LeadRecord::serialize()` first requires its argument to be the receiver, then validates the server-generated canonical structure and invariants in the documented record-key order. It does not repeat submitted-field validation already completed before `CaptureCommand`; this separation makes encoder and final-size failures independently testable. It calls `json_encode()` with `JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`, appends exactly one LF, and rejects output greater than 32 KiB. JSON failure or an otherwise unmapped structural invariant failure returns `canonical_serialization_failed`; oversize encoded output returns `canonical_record_too_large`. It returns no partial string and exposes no caught exception detail.

The single Phase 3A unit test and both source-tree and installed-package reflection checks cover every signature in the table. Behavioral assertions cover: `ValidationError` code allowlist, accessors, exact array order, immutability and rejection; `ValidationResult` success/failure invariants, ordered errors, three external success types plus internal ID string, and invalid arguments; `CaptureCommand` exact shapes, private construction, returned-array isolation and no setters; validator success/failure, trusted misuse, stateless repetition and exhaustive oracles; all four normalizer methods and Unicode failure paths; ID callable type/count/length/exception behavior; record factory callable types/counts/exceptions, UTC microseconds and immutable arrays; and serialization identity argument, canonical bytes, JSON failure, size failure and no partial result. Reflection asserts class finality, constructor visibility, static factories, method visibility/static status, parameter names/order/types, return types and required PHPDoc shapes.

For server-record and serializer failure branches that cannot arise from a valid public `CaptureCommand`, the one unit test uses PHP reflection only as an explicit white-box fault oracle: `ReflectionClass::newInstanceWithoutConstructor()` plus one initialization of the private canonical-data property creates a synthetic malformed receiver, and `serialize($receiver)` executes the normal invariant checks. Exact malformed synthetic arrays independently reach `invalid_schema_version`, `invalid_generated_id`, `invalid_timestamp`, `invalid_updated_timestamp`, `capture_timestamps_mismatch`, `invalid_initial_status`, `invalid_initial_revision`, `invalid_source`, `invalid_form_name`, `invalid_locale`, `consent_version_missing`, `invalid_consent_version`, `consent_timestamp_missing`, `invalid_consent_timestamp`, `invalid_idempotency`, `invalid_idempotency_key_version`, `invalid_idempotency_key_hash`, `invalid_payload_fingerprint`, `canonical_serialization_failed`, and `canonical_record_too_large`. The reflection fixture is repository-only test code, never packaged or callable by production code, adds no public factory, and must not bypass the method under test.

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

## Normative Phase 3B secure-persistence implementation contract

This section is the sole normative Phase 3B implementation contract and supersedes earlier Phase 3B planning prose where they differ. Phase 3A public behavior remains stable. Phase 3B adds only the exact APIs, callers, files, and tests below.

### Implementation manifest

- Implementation branch: `feat/phase-3b-secure-lead-storage`.
- Future implementation commit subject: `feat: add secure Lead filesystem repository`.
- Exact nine new files:
  - `classes/Application/LeadPersistenceCoordinator.php`
  - `classes/Security/IdempotencyKeyRing.php`
  - `classes/Storage/LeadRepository.php`
  - `classes/Storage/PersistenceRequest.php`
  - `classes/Storage/PersistenceResult.php`
  - `classes/Storage/FilesystemLeadRepository.php`
  - `classes/Storage/StorageException.php`
  - `tests/unit/phase-3b-secure-persistence.php`
  - `tests/integration/phase-3b-secure-storage.sh`
- Exact ten modified files:
  - `classes/Domain/LeadRecord.php`: add only the idempotency-aware factory defined below; preserve `fromCommand()` and every Phase 3A invariant.
  - `goosialize-leads.yaml`: add only the disabled-by-default required-config idempotency keys defined below; never contain a secret value.
  - `packaging/package-files.txt`: add the seven new runtime-class paths in lexical order.
  - `tests/unit/phase-3a-lead-data-validation.php`: add regression/reflection coverage for the new `LeadRecord` factory while preserving every Phase 3A marker.
  - `tests/integration/clean-grav-plugin-load.sh`: reflect the fourteen packaged runtime classes and prove enabled/disabled loading remains inert.
  - `tests/integration/installable-plugin-package.sh`: assert the exact 24-file package/installed tree and fourteen-class reflection contract.
  - `tests/integration/phase-2d-entry-points.sh`: update only package/class assertions while preserving the Phase 2D behavior and regression markers.
  - `README.md`: describe completed Phase 3B primitives and explicitly retain the no-route/no-Forms/no-notification boundary.
  - `CHANGELOG.md`: record the Phase 3B secure-persistence checkpoint without claiming later capture behavior.
  - `docs/OFFICIAL_VERIFICATION_LOG.md`: record executed Phase 3B probes, two final package hashes, counts, markers, and accepted directory-durability limitation.
- Counts: nine new files, ten modified files, nineteen changed paths, seven new runtime classes, one new unit-test file, and one new integration-test file.
- Exact package-manifest additions: the seven new runtime-class paths above. The resulting package and installed tree each contain exactly 24 regular files. Development-only files excluded from the package are `tests/unit/phase-3b-secure-persistence.php` and `tests/integration/phase-3b-secure-storage.sh`.
- Exact package reflection list: `Application\CaptureCommand`, `Application\LeadPersistenceCoordinator`, `Domain\LeadIdGenerator`, `Domain\LeadRecord`, `Security\IdempotencyKeyRing`, `Storage\LeadRepository`, `Storage\PersistenceRequest`, `Storage\PersistenceResult`, `Storage\FilesystemLeadRepository`, `Storage\StorageException`, `Validation\LeadInputValidator`, `Validation\LeadNormalizer`, `Validation\ValidationError`, and `Validation\ValidationResult`.
- Changed package inputs are `classes/Domain/LeadRecord.php`, `goosialize-leads.yaml`, `packaging/package-files.txt`, and the seven new runtime classes. No other package input changes.

The exact final `packaging/package-files.txt` is this 24-line lexical list:

```text
CHANGELOG.md
README.md
admin-next/pages/goosialize-leads.js
autoload.php
blueprints.yaml
classes/Application/CaptureCommand.php
classes/Application/LeadPersistenceCoordinator.php
classes/Domain/LeadIdGenerator.php
classes/Domain/LeadRecord.php
classes/Security/IdempotencyKeyRing.php
classes/Storage/FilesystemLeadRepository.php
classes/Storage/LeadRepository.php
classes/Storage/PersistenceRequest.php
classes/Storage/PersistenceResult.php
classes/Storage/StorageException.php
classes/Validation/LeadInputValidator.php
classes/Validation/LeadNormalizer.php
classes/Validation/ValidationError.php
classes/Validation/ValidationResult.php
composer.json
goosialize-leads.php
goosialize-leads.yaml
languages/en.yaml
templates/phase-2d-skeleton.html.twig
```

Phase 3B adds no functional public HTTP route, API controller, Forms processor, notification or replay notification, Admin2 management/control, Shadow DOM, ACL/permission management, CSV export, theme integration, migration, lead-magnet delivery, or production UI. Phase 3C.1 owns standard Forms capture; Phase 3C.2 owns public JSON and XHR transport; Phase 3D owns notification; Phase 4 owns Admin2/ACL/CSV; Phase 5 owns delivery; Phase 7 owns migrations and scheduled/post-crash maintenance.

### Normative Phase 3B public API

All concrete Phase 3B value/service classes are `final`, expose no public properties or setters, and return copies rather than references. The two repository ports are interfaces. Literal union types below are encoded table-safely.

| FQCN and path | Declaration, construction, and exact public API | State, exceptions, creators, consumers, and direct tests |
|---|---|---|
| `Grav\Plugin\GoosializeLeads\Storage\LeadRepository`; `classes/Storage/LeadRepository.php` | `interface LeadRepository`; no constructor; `public function persist(PersistenceRequest $request): PersistenceResult` | Stateless port. It throws only `StorageException` for storage failure and `\InvalidArgumentException` for programmer misuse. Production implementation: `FilesystemLeadRepository`. Production caller: `LeadPersistenceCoordinator::persist()`. Direct access is limited to the two Phase 3B tests and a unit-test fake implementing this exact method. |
| `Grav\Plugin\GoosializeLeads\Storage\PersistenceRequest`; `classes/Storage/PersistenceRequest.php` | `final class`; private constructor `private function __construct(LeadRecord $record, string $recordBytes, ?string $keyDigest, ?string $payloadBytes)`; factory `public static function create(LeadRecord $record, string $recordBytes, ?string $keyDigest, ?string $payloadBytes): self`; accessors `public function record(): LeadRecord`, `public function recordBytes(): string`, `public function keyDigest(): ?string`, `public function payloadBytes(): ?string`, `public function hasIdempotency(): bool` | Immutable. `recordBytes` must equal successful canonical serialization of the same receiver. Key digest/payload bytes are either both null or a 64-character lowercase hexadecimal digest plus the exact canonical command JSON and LF used for HMAC. The record’s idempotency key hash must match the digest. Invalid direct use throws redacted `\InvalidArgumentException`. Creator: `LeadPersistenceCoordinator::persist()`. Consumer: `FilesystemLeadRepository::persist()`. Direct tests: both Phase 3B tests. |
| `Grav\Plugin\GoosializeLeads\Storage\PersistenceResult`; `classes/Storage/PersistenceResult.php` | `final class`; private constructor `private function __construct(string $status, ?array $record, ?string $code, array $errors)`; factories `public static function created(LeadRecord $record): self`, `public static function replayed(array $record): self`, `public static function idCollision(): self`, `public static function failure(string $code, array $errors = []): self`; accessors `public function status(): string`, `public function record(): ?array`, `public function code(): ?string`, `public function errors(): array`, `public function errorsAsArray(): array`, `public function isSuccess(): bool`, `public function toArray(): array`; PHPDoc is <code>@param array&lt;string,mixed&gt;&#124;null $record</code>, `@param list<ValidationError> $errors`, `@return list<ValidationError>` for `errors()`, and <code>@return array{status:string,record:array&lt;string,mixed&gt;&#124;null,code:?string,errors:list&lt;array{code:string,field:?string}&gt;}</code> for `toArray()` | Immutable statuses are exactly `created`, `replayed`, `id_collision`, `failure`. Created/replayed carry the complete canonical record and null code/empty errors; collision carries neither and empty errors; only `validation_failed` may carry the original ordered `ValidationError` list, while every other failure requires an empty list. Invalid factories throw `\InvalidArgumentException`. Producers: `FilesystemLeadRepository::persist()` and `LeadPersistenceCoordinator::persist()`. Consumers: `LeadPersistenceCoordinator::persist()` and Phase 3C `Grav\Plugin\GoosializeLeads\Application\LeadCaptureService::capture()`. Direct tests: both Phase 3B tests. |
| `Grav\Plugin\GoosializeLeads\Storage\FilesystemLeadRepository`; `classes/Storage/FilesystemLeadRepository.php` | `final class implements LeadRepository`; constructor `public function __construct(string $userDataRoot, callable $temporaryEntropy, IdempotencyKeyRing $keyRing)` with PHPDoc `@param callable(int):string $temporaryEntropy`; `public function persist(PersistenceRequest $request): PersistenceResult` | Mutable only during one locked call; retains validated canonical root, entropy callable, and key-ring object, but no Lead data. `$userDataRoot` is the trusted absolute `user-data://` resolution supplied by plugin composition, not a visitor value. It alone performs filesystem operations, record/sidecar serialization validation, locking, and exception translation; secret operations remain encapsulated by `IdempotencyKeyRing`. Constructor misuse throws `\InvalidArgumentException`; operational failures throw `StorageException`. Production creator: `Grav\Plugin\GoosializeLeadsPlugin::onFormProcessed()` beginning in Phase 3C.1. Caller: `LeadPersistenceCoordinator::persist()`. Direct access: integration test only. |
| `Grav\Plugin\GoosializeLeads\Storage\StorageException`; `classes/Storage/StorageException.php` | `final class extends \RuntimeException`; constructor `public function __construct(string $code, ?\Throwable $previous = null)`; `public function stableCode(): string` | Message is always the stable code and never incorporates the previous message. The previous exception is chained for internal debugging only and never serialized/logged by Phase 3B. Invalid code throws `\InvalidArgumentException`. Producers: only `FilesystemLeadRepository` and `IdempotencyKeyRing`. Consumer: only `LeadPersistenceCoordinator::persist()`, which maps it to `PersistenceResult::failure('storage_unavailable')`. Direct tests: both Phase 3B tests. |
| `Grav\Plugin\GoosializeLeads\Security\IdempotencyKeyRing`; `classes/Security/IdempotencyKeyRing.php` | `final class`; constructor `public function __construct(?int $activeVersion, array $encodedKeys)` with PHPDoc `@param array<int,string> $encodedKeys`; `public function enabled(): bool`, `public function activeVersion(): ?int`, `public function keyDigest(?string $idempotencyKey): ?string`, `public function canonicalPayload(CaptureCommand $command): string`, `public function payloadDigest(CaptureCommand $command): ?string`, `public function verify(int $version, string $payloadBytes, string $expectedDigest): bool` | Immutable decoded secrets are held only in memory. Both constructor arguments represent required external configuration. Empty/null disables idempotent capture and permits only null idempotency keys. Invalid/missing active configuration throws `StorageException('key_configuration_invalid')`; direct invalid method arguments throw `\InvalidArgumentException`. Creator: Phase 3C composition. Consumers: `LeadPersistenceCoordinator::persist()` derives new metadata; `FilesystemLeadRepository::persist()` invokes only `verify()` for historical sidecars. Direct access: unit test only. |
| `Grav\Plugin\GoosializeLeads\Application\LeadPersistenceCoordinator`; `classes/Application/LeadPersistenceCoordinator.php` | `final class`; constructor `public function __construct(LeadInputValidator $validator, LeadRepository $repository, IdempotencyKeyRing $keyRing)`; `public function persist(mixed $submitted, array $trusted, ?string $idempotencyKey, callable $entropy, callable $clock): PersistenceResult`; PHPDoc defines `$trusted` as the exact Phase 3A trusted shape, `$entropy` as `callable(int):string`, and `$clock` as `callable():\DateTimeInterface` | Stateless orchestrator and sole new production caller of Phase 3A validation/record APIs. It performs exactly the call graph below and catches only `StorageException`, mapping it to redacted failure. Invalid callable/trusted programmer misuse remains `\InvalidArgumentException`; expected validation/record failures map to `PersistenceResult::failure()` using the exact code policy below. Production creator/caller: Phase 3C `Grav\Plugin\GoosializeLeads\Application\LeadCaptureService::__construct()`/`capture()`. Direct access: unit and integration tests. |

`LeadRecord` gains exactly:

```php
public static function fromCommandWithIdempotency(
    CaptureCommand $command,
    callable $entropy,
    callable $clock,
    ?int $keyVersion,
    ?string $keyHash,
    ?string $payloadFingerprint
): ValidationResult
```

`fromCommand()` remains unchanged externally and delegates to the new factory with three null idempotency arguments. The new factory requires either all three idempotency values null or a positive version plus two 64-character lowercase hexadecimal strings. It retains every existing timestamp, entropy, record-shape, validation, and exception rule. Production caller is only `LeadPersistenceCoordinator::persist()`; direct access is limited to the Phase 3A and Phase 3B unit tests.

### Exact production call graph and collision policy

The exact Phase 3B entry is `LeadPersistenceCoordinator::persist()`. Phase 3B registers no lifecycle, route, command, or automatic caller. Phase 3C’s exact `Grav\Plugin\GoosializeLeads\Application\LeadCaptureService::capture()` is the only authorized production caller.

1. `LeadPersistenceCoordinator::persist()` calls `LeadInputValidator::validate()`. Failure becomes `PersistenceResult::failure('validation_failed', $validationResult->errors())`, preserving the exact Phase 3A ordered non-PII errors.
2. It validates an optional idempotency key against `[A-Za-z0-9._~-]{16,128}`. Invalid input returns `invalid_idempotency_key`.
3. `IdempotencyKeyRing::keyDigest()` returns lowercase SHA-256 of exact ASCII key bytes; `canonicalPayload()` serializes `CaptureCommand::toArray()` with the Phase 3A JSON flags and one LF; `payloadDigest()` HMACs those bytes with the active decoded 32-byte key.
4. For attempts numbered 1 through 5 inclusive, the coordinator calls `LeadRecord::fromCommandWithIdempotency()` once with the same command, clock, derived metadata, and caller-supplied entropy. Each individual factory call invokes entropy exactly once and creates a fresh record. There is no retry inside `LeadRecord`.
5. It invokes `LeadRecord::serialize($record)`, then `PersistenceRequest::create()`, then `LeadRepository::persist()`.
6. `created`, `replayed`, or `failure` returns immediately. Only `id_collision` advances to the next attempt. After attempt five, collision maps deterministically to `PersistenceResult::failure('collision_exhausted')`.
7. `FilesystemLeadRepository::persist()` alone verifies sidecar-to-record consistency. For an existing sidecar it selects the sidecar’s historical version through `IdempotencyKeyRing::verify()` and the request’s canonical payload bytes; it does not compare an active-version digest to a historical-version digest. `StorageException` is caught only by the coordinator and becomes `storage_unavailable`; no raw detail crosses the coordinator.

Unit oracles inject five deterministic 16-byte entropy values, assert one call for each record attempt, assert early stop on attempts 1–4 success, assert exactly five calls on five collisions, preserve the original target bytes, and require exact terminal `collision_exhausted`.

### Required-config HMAC policy

Secrets are required, never generated. `goosialize-leads.yaml` adds exactly:

```yaml
idempotency:
  active_key_version: null
  keys: {}
```

Deployment supplies `plugins.goosialize-leads.idempotency.active_key_version` as a positive integer and `keys.<version>` as standard padded Base64 encoding of exactly 32 decoded bytes. No default, example, test, documentation, or package contains a secret. Null/empty configuration permits captures only when no idempotency key is supplied; a supplied key fails closed as `key_configuration_invalid`.

New writes use the active version. Historical positive integer keys remain accepted for verification. Active version must exist in the map; keys must be unique positive integer indices and valid canonical Base64 decoding to exactly 32 bytes. Missing, short, extra-padded, noncanonical, duplicate-decoded, or invalid keys fail with `key_configuration_invalid`. Rotation adds a higher version and selects it active; referenced historical keys must remain. Removal is allowed only after no retained record or sidecar references that version and is Phase 7 maintenance.

The key digest is lowercase `hash('sha256', $idempotencyKey)`. The payload bytes are canonical JSON of `CaptureCommand::toArray()` using `JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE` plus one LF. The fingerprint is lowercase `hash_hmac('sha256', $payloadBytes, $decodedKey)`. Verification selects the sidecar’s positive historical version and uses `hash_equals()` with the recomputed lowercase digest as first argument. Raw keys, decoded secrets, and HMAC input/output never appear in paths except the non-secret key digest, errors, logs, results, or documentation.

### Storage locator, containment, names, modes, and publication

`FilesystemLeadRepository::__construct()` accepts exactly one trusted absolute canonical `user-data://` filesystem root, already resolved by Grav composition, and appends only `goosialize-leads/v1`. It rejects empty, relative, NUL-containing, stream-wrapper, dot-segment, trailing-dot-segment, and noncanonical paths. It never accepts the plugin-relative root or a visitor-derived path. On Windows drive syntax is unsupported; Phase 3B supports the pinned Linux deployment only.

The constructor lexical-normalizes repeated separators and a single trailing separator, requires the supplied existing user-data root to have `realpath()`, requires it to be a non-symlink directory by `lstat()`, and stores its canonical path. Each existing descendant is checked with `lstat()` before `realpath()`, must not be a symlink, and must remain separator-aware within the stored root. Missing descendants are created one component at a time with `mkdir(..., 0700)`, immediately `chmod(..., 0700)`, then rechecked with `lstat()`, `realpath()`, containment, ownership type, and exact `0700 & 0777`. A missing component whose parent cannot be canonicalized fails `root_invalid`. Any root/component symlink fails `symlink_detected`. No arbitrary absolute child or `..` is accepted.

All directories are exactly `0700`; lock, temporary, record, and sidecar files are exactly `0600`. The repository temporarily sets `umask(0077)` only around creation, restores it in `finally`, calls `chmod()` explicitly, and verifies `fileperms() & 0777`. It assumes the PHP process owns newly created paths; ownership mismatch or inability to verify exact modes fails `permission_failed`. Errors expose no path.

The lock path is exactly `goosialize-leads/v1/.capture.lock`. It is opened with `fopen($path, 'c+b')`, immediately validated as a contained non-symlink regular file, explicitly chmodded and verified as `0600`, and acquired with blocking `flock($handle, LOCK_EX)`. One repository call owns it until all replay, collision, record, sidecar, recovery, and current-temp cleanup decisions finish. `flock($handle, LOCK_UN)` and `fclose()` run in nested `finally` blocks; acquisition, unlock, or lock-handle close failure maps to `lock_failed`. No lock file is deleted or treated as stale.

Final record path is `records/YYYY/MM/<32-lowercase-hex-id>.json`. Final sidecar path is `idempotency/<first-two-key-digest-characters>/<64-lowercase-hex-key-digest>.json`. UTC year/month come only from the canonical record timestamp. Maximum final basenames are 37 and 69 bytes respectively.

Temporary record basename is `.tmp-<32-lowercase-hex-id>-<16-lowercase-hex>.json` (59 bytes). Temporary sidecar basename is `.tmp-sidecar-<64-lowercase-hex-key-digest>-<16-lowercase-hex>.json` (99 bytes). The repository invokes `$temporaryEntropy(8)` exactly once per temporary creation; it must return exactly eight bytes, encoded with lowercase `bin2hex()`. Throwable or wrong-length output maps to `temporary_creation_failed`. Files are opened only with `fopen($path, 'x+b')`.

The complete byte string is prepared before opening. Writes loop until all bytes are accepted; `false` maps to `write_failed`, zero or incomplete termination maps to `short_write`. Then `fflush()` and `fsync()` must return true, mode/type/containment are revalidated, and `fclose()` must return true. Failure maps respectively to `flush_failed`, `file_fsync_failed`, or `close_failed`. The handle is closed in `finally`; only the validated current-attempt temporary is unlinked.

Publication uses only same-directory `link($temporaryPath, $finalPath)`. No `rename()`, copy, overwrite, or fallback exists. Once per repository instance, inside the acquired lock and before inspecting or publishing a request, the repository creates `v1/.probe-link-<16-lowercase-hex>` with `fopen(..., 'x+b')`, writes the exact bytes `phase-3b-link-probe\n`, flushes/fsyncs/closes it, hard-links it to `v1/.probe-link-<same-hex>.linked`, verifies equal nonzero inode and exact bytes, then unlinks the linked name followed by the source name. The same constructor temporary-entropy callable supplies exactly eight bytes for the suffix. Any pre-existing probe name, failed operation, unequal inode/bytes, or cleanup failure maps to `publication_unsupported`; both names are current-operation artifacts removed in `finally`. Probe names are never records, sidecars, or reader candidates.

An already existing canonical record final returns `PersistenceResult::idCollision()` after validating that it is a non-symlink regular contained file; its bytes are never changed. An existing sidecar is strictly parsed and verified against its referenced canonical Lead and historical key version. The candidate request record’s `capturedAt()` is the injected current request time used for expiry comparison; `capturedAt() >= expires_at` returns `idempotency_expired`. Before expiry, matching historical-version HMAC returns `replayed`, a different payload returns `idempotency_conflict`, and malformed state throws `idempotency_index_invalid`. Other record `link()` failure maps to `publication_failed`; sidecar `link()` failure maps to `sidecar_publication_failed`.

For every keyed request, after checking for an existing sidecar and before publishing the candidate record, the repository performs the missing-sidecar recovery scan. It walks only validated non-symlink `records/YYYY/MM` directories in ascending lexical `YYYY/MM/filename` order and examines at most 10,000 canonical final record names across the entire contained `records` tree. It validates each examined record’s exact schema, filename/ID, size, mode, containment, key hash, key version, and payload fingerprint, and calls `IdempotencyKeyRing::verify()` with that record’s historical version and the request payload bytes. Zero matches permits new record publication; exactly one matching record causes publication of its missing sidecar and returns `replayed`; multiple matches, an anomaly, or encountering a 10,001st canonical record fails `idempotency_index_invalid`. Thus a retry in a later UTC month cannot miss an earlier orphan and cannot create a second keyed Lead.

For keyed creation, the record publishes first and the sidecar second. If sidecar publication fails, the published record remains immutable and the operation returns `sidecar_publication_failed`; it is never rolled back because unlinking a visible Lead would lose accepted data. A later same-key request executes the pre-publication recovery scan above. Unkeyed creation ends after record publication. Readers never consume temporary names.

Process-visible atomic publication means concurrent readers observe no final file or the complete fsynced bytes. Directory-entry persistence across power loss is not guaranteed and absence of directory `fsync` is not failure. Integration acceptance documents this limitation and proves only process-visible atomicity.

### Storage errors and exception mapping

`StorageException` stable codes are exactly:

`root_invalid`, `unsafe_path`, `symlink_detected`, `directory_creation_failed`, `permission_failed`, `lock_failed`, `temporary_creation_failed`, `write_failed`, `short_write`, `flush_failed`, `file_fsync_failed`, `close_failed`, `publication_unsupported`, `publication_failed`, `sidecar_publication_failed`, `cleanup_failed`, `idempotency_index_invalid`, `key_configuration_invalid`, and `unexpected_storage_failure`.

| Condition | Storage behavior |
|---|---|
| Invalid/unresolved root | throw `root_invalid` |
| Containment/traversal failure | throw `unsafe_path` |
| Root/component/file symlink | throw `symlink_detected` |
| Directory creation failure | throw `directory_creation_failed` |
| Exact mode/ownership verification failure | throw `permission_failed` |
| Lock create/acquire/release failure | throw `lock_failed` |
| Temp entropy/open failure | throw `temporary_creation_failed` |
| Write false | throw `write_failed` |
| Zero/incomplete write | throw `short_write` |
| Flush failure | throw `flush_failed` |
| File fsync failure | throw `file_fsync_failed` |
| Close failure | throw `close_failed` |
| Hard-link probe unsupported | throw `publication_unsupported` |
| Existing record final | return `id_collision` |
| Other record link failure | throw `publication_failed` |
| Sidecar link/recovery publication failure | throw `sidecar_publication_failed` |
| Immediate temp unlink failure | throw `cleanup_failed` after preserving any published final |
| Existing malformed/inconsistent sidecar | throw `idempotency_index_invalid` |
| Missing/invalid HMAC configuration | throw `key_configuration_invalid` |
| Five record collisions | coordinator returns failure `collision_exhausted` |
| Unexpected filesystem throwable | throw `unexpected_storage_failure` with previous chained |

The coordinator result-code allowlist is exactly `validation_failed`, `invalid_idempotency_key`, `idempotency_conflict`, `idempotency_expired`, `collision_exhausted`, and `storage_unavailable`. Storage exceptions never cross it. Programmer misuse—invalid direct value-object construction, invalid trusted array, invalid callable declaration, or an unknown stable code—throws `\InvalidArgumentException`. No public result contains an exception, message, path, submitted value, raw key, digest input, secret, or trace.

### Immediate cleanup and deferred maintenance

Phase 3B chooses maintenance scope B. It performs only current-operation cleanup of the one validated temporary path in `finally`. It does not scan directories or remove crash leftovers. `TemporaryArtifactMaintenanceRepository`, `TemporaryArtifactMaintenanceResult`, `Application\StaleTemporaryCleanup`, and `Console\CleanupTemporariesCommand` are not Phase 3B runtime classes or files. The command `goosialize-leads:cleanup-temporaries`, scheduled cleanup, 24-hour/100-entry/two-second policy, active-write detection, event emission, and post-crash cleanup are deferred to Phase 7. Phase 3B registers no command or cleanup hook in `goosialize-leads.php`.

### Exact tests, fixtures, fault injection, and markers

`tests/unit/phase-3b-secure-persistence.php` runs in the pinned offline runtime exactly as:

```text
docker run --rm --network none --mount type=bind,src=/home/goosialize/projects/local-docker/grav/goosialize-leads-dev,dst=/source,readonly --entrypoint php sha256:702d936e25513805b57c9d009f7ff466217273415b2e55f539f3366e6377d351 /source/tests/unit/phase-3b-secure-persistence.php
```

The command first requires that `docker image inspect --format '{{.Id}}' lscr.io/linuxserver/grav:2.0.12` equals the literal image ID used above. The test uses only `lead@example.test`, `+35722000000`, `Synthetic Lead`, message `Phase 3B persistence fixture`, consent version `privacy-v1`, source `api`, form `goosialize-leads-capture`, UTC `2026-07-27T10:20:30.123456Z`, entropy bytes `00` through `0f`, idempotency key `synthetic-key-01`, Base64 `S0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0s=` for version 1, and Base64 `UlJSUlJSUlJSUlJSUlJSUlJSUlJSUlJSUlJSUlJSUlI=` for version 2. The exact 287-byte payload-HMAC input is:

```json
{"source":"api","form_name":"goosialize-leads-capture","locale":null,"consent":{"granted":true},"full_name":"Synthetic Lead","email":"lead@example.test","phone":"+35722000000","company":null,"message":"Phase 3B persistence fixture","resource_id":null,"source_path":null,"campaign":null}
```

The JSON line plus exactly one LF is the HMAC input. Its key digest is `3a1190b048a946c5d50e77efb7361ab6768acbfa4e3a54fa8e0bf0b8ff927f59`; its version-1 payload digest is `838eb9525abf7512b0e1872249d52b5af67d662341185bed54dd14c93437896b`. With ID `00000000000000000000000000000000`, the exact persisted record bytes are this line plus one LF:

```json
{"schema_version":1,"id":"00000000000000000000000000000000","created_at":"2026-07-27T10:20:30.123456Z","updated_at":"2026-07-27T10:20:30.123456Z","status":"new","revision":1,"source":"api","form_name":"goosialize-leads-capture","locale":null,"consent":{"granted":true,"version":"privacy-v1","captured_at":"2026-07-27T10:20:30.123456Z"},"idempotency":{"key_version":1,"key_hash":"3a1190b048a946c5d50e77efb7361ab6768acbfa4e3a54fa8e0bf0b8ff927f59","payload_fingerprint":"838eb9525abf7512b0e1872249d52b5af67d662341185bed54dd14c93437896b"},"full_name":"Synthetic Lead","email":"lead@example.test","phone":"+35722000000","company":null,"message":"Phase 3B persistence fixture","resource_id":null,"source_path":null,"campaign":null}
```

The exact sidecar bytes are this line plus one LF:

```json
{"schema_version":1,"key_version":1,"key_digest":"3a1190b048a946c5d50e77efb7361ab6768acbfa4e3a54fa8e0bf0b8ff927f59","payload_digest":"838eb9525abf7512b0e1872249d52b5af67d662341185bed54dd14c93437896b","lead_id":"00000000000000000000000000000000","created_at":"2026-07-27T10:20:30.123456Z","expires_at":"2026-08-26T10:20:30.123456Z"}
```

The test defines an in-memory `LeadRepository` fake whose queued exact results are `created`, `replayed`, `id_collision`, or `failure`; it records immutable request snapshots and exposes no filesystem API.

The unit test asserts every API signature/finality/visibility/type/PHPDoc shape; exact key/payload digests; canonical Base64 rejection; active/historical selection and `hash_equals()` verification; null-key behavior; required-config failure; record idempotency fields; unchanged `fromCommand()` null behavior; one entropy call per record attempt; attempts 1–5; early success; five-collision exhaustion; exact result arrays; exception redaction; and the complete call graph. It emits:

- `PASS_PHASE_3B_IDEMPOTENCY` only after key selection, rotation, hashes, sidecar metadata, missing/invalid configuration, and redaction pass.
- `PASS_PHASE_3B_COLLISION_POLICY` only after all one-through-five attempt and no-overwrite fake-repository oracles pass.

`tests/integration/phase-3b-secure-storage.sh` runs exactly:

```text
GRAV_TEST_IMAGE=lscr.io/linuxserver/grav:2.0.12 tests/integration/phase-3b-secure-storage.sh
```

It first verifies immutable image ID `sha256:702d936e25513805b57c9d009f7ff466217273415b2e55f539f3366e6377d351`, then uses `mktemp -d` outside the repository as the trusted synthetic user-data root and mounts only the repository read-only plus that root read-write into `docker run --rm --network none`. A trap removes the root and named test containers. No volume is created.

The test-only fault fixture subclasses no production class. It supplies the constructor’s temporary-entropy callable and invokes the repository in disposable directories whose permissions and precreated entries deterministically cause each failure. `FilesystemLeadRepository` calls PHP filesystem functions unqualified within its namespace. For stages not reliably induced by permissions—write false, zero write, flush, fsync, close, hard-link unsupported, record link, sidecar link, and immediate unlink—the test runs the repository in a subprocess with a test-only same-namespace PHP function shim loaded before the class; the shim delegates to the corresponding global function by default and fails exactly the named call ordinal. Production files contain no fault hook. Each subprocess receives one stage name from the exact allowlist and asserts the mapped stable code, no final partial bytes, and no leftover current-operation temp.

Exact assertions cover: canonical record bytes; exact record/sidecar paths and bytes; 0700 directories; 0600 lock/temp/finals; umask restoration; traversal/root/symlink rejection; no write outside root; exact probe bytes/inode/cleanup and one-probe-per-instance behavior; same-file inode after publication; complete-byte visibility; duplicate record target byte identity; keyed replay/conflict/expiry; malformed and mismatched sidecars; zero/one/multiple/10,001-entry recovery including a prior-month orphan; two simultaneous unkeyed unique records; two simultaneous same-key calls producing one record/sidecar; every mapped fault stage; current-temp cleanup; no scheduled scan; and redacted exceptions. Expected sidecar bytes are the exact canonical schema in `docs/PHASE_3_LEAD_DATA_CONTRACT.md` with the deterministic fixture digests and one LF.

It then runs the Phase 3A unit test and the three existing integration scripts, builds two independent packages, requires byte-identical archives, installs one offline with GPM, asserts the exact 24-file package and installed trees, reflects all fourteen runtime classes, proves no `vendor/`, and preserves clean enabled/disabled load and `PASS_PHASE_2B_REGRESSION`, `PASS_PHASE_2C_REGRESSION`, and `PASS_PHASE_2D_REGRESSION`.

Markers map exactly:

- `PASS_PHASE_3B_NO_REPLACE`: link-only publication, inode identity, existing-target preservation, unsupported-link failure, and no overwrite fallback.
- `PASS_PHASE_3B_ATOMIC_STORAGE`: exact bytes/names/modes, full write/flush/fsync/close, process-visible completeness, publication ordering, rollback policy, and every stage fault.
- `PASS_PHASE_3B_IDEMPOTENCY`: unit marker plus sidecar schema, HMAC selection/rotation, replay/conflict/expiry, corruption, and recovery.
- `PASS_PHASE_3B_CONCURRENCY`: simultaneous unique and same-key process tests with exact resulting counts.
- `PASS_PHASE_3B_IMMEDIATE_CLEANUP`: current-attempt cleanup on every pre/post-publication failure and proof that no scan/scheduled cleanup exists.
- Existing Phase 3A and Phase 2 markers retain their exact current meanings.

Final implementation acceptance builds independently twice, requires byte-identical ZIP bytes and identical SHA-256 values, and records that SHA and both 24-file counts in `docs/OFFICIAL_VERIFICATION_LOG.md`. The final Phase 3B SHA is implementation evidence, not a planning-time constant. The Phase 3A SHA `6c6e5040baecc19ce89535ade4ab3d63fd68b51e8a1e881f4bb93ed6b83f2d7d` remains the pre-implementation baseline.

Use only synthetic `.test` data. Every PASS marker below maps to its named assertions; unavailable required coverage stops the checkpoint. Every checkpoint uses its named branch created from current `main`; the prior checkpoint must already be merged, and that main commit must be the direct parent of the checkpoint commit. Every listed documentation path is a required update, not merely permitted. Each checkpoint requires a clean pre-commit review, all listed tests, exactly one approved commit with the stated subject, then a separate read-only merge review and local `git merge --ff-only <branch>`. No remote, push, publish, or deploy is allowed. Post-merge, rerun earlier Phase 2 and Phase 3 markers and prove clean status, retained branch, unchanged reference, and package determinism.

### Normative Phase 3C.1 Grav Forms capture contract

Phase 3C.1 uses the installed Forms 9.1.14 custom-process-action model only. `GoosializeLeadsPlugin::onFormProcessed(\RocketTheme\Toolbox\Event\Event $event): void` is registered only when the plugin and `form` plugin are enabled. It ignores every action except the exact lowercase key `goosialize_leads_capture`. The event contains `form`, `action`, and `params`; the adapter requires `form` to be `\Grav\Plugin\Form\Form`, `action` to match exactly, and `params` to be `true` or an empty array. Alternative hooks and custom registration mechanisms are rejected.

Source evidence is exact: Forms `blueprints.yaml:1-20` reports 9.1.14; `classes/Form.php:880-908` loads POST data and rejects a missing/invalid built-in `form-nonce`; lines 938-980 validate/filter and fire validation events; lines 986-1024 fire one `onFormProcessed` event for each enabled process action, honor `redirect`/`redirect_code`, stop propagation, and redirect afterward. `form.php:276-282` enables the event for recognized POSTs, `form.php:798-806` rejects a populated honeypot, and `classes/Form.php:175-179,928-930` plus `FormTrait.php:123-150` provide the stable `getUniqueId(): string` and `getFormName(): string` accessors. Standard templates render `__unique_form_id__` and the nonce. `Form::value()` returns filtered form data; `Form::setMessage()` sets error status; the event supports `stopPropagation()`.

#### Implementation manifest and package

- Branch: `feat/phase-3c-forms-capture`.
- Future commit subject: `feat: add Grav Forms Lead capture`.
- Exact five new files: `classes/Application/CaptureResult.php`, `classes/Application/LeadCaptureService.php`, `classes/Http/FormsLeadCaptureAdapter.php`, `tests/unit/phase-3c-forms-capture.php`, and `tests/integration/phase-3c-grav-forms.sh`.
- Exact thirteen modified files: `classes/Security/IdempotencyKeyRing.php`, `goosialize-leads.php`, `goosialize-leads.yaml`, `blueprints.yaml`, `languages/en.yaml`, `packaging/package-files.txt`, `tests/integration/clean-grav-plugin-load.sh`, `tests/integration/installable-plugin-package.sh`, `tests/integration/phase-2d-entry-points.sh`, `tests/unit/phase-3b-secure-persistence.php`, `README.md`, `CHANGELOG.md`, and `docs/OFFICIAL_VERIFICATION_LOG.md`.
- Total changed paths: 18. New runtime classes: 3. Unit-test files: 2 (one new Phase 3C.1 oracle and one modified Phase 3B regression oracle). Integration-test files: 1.
- `tests/unit/phase-3b-secure-persistence.php` retains every existing Phase 3B assertion and exact public-API allowlist, preserves all existing Phase 3B method signatures, and adds an exact reflection and behavior oracle for `IdempotencyKeyRing::deriveFormsIdempotencyKey(string $formName, string $submissionId): string`, including public instance visibility, parameter order/types, return type, active-key deterministic HMAC behavior, key-version behavior, invalid-argument behavior, and redacted key-configuration failure. It remains development-only and does not change the package manifest or package/install counts.
- Package additions, in sorted manifest order, are exactly the three runtime-class paths. Tests remain repository-only. Package and installed-tree counts become exactly 27. Reflection covers the existing fourteen runtime types plus the three new final classes, for seventeen runtime types.
- The Phase 3B SHA-256 `943189d75a903c524fd1e1f7a5b0bfc471e75fad3d21714ea2c1e15202c907e1` is the baseline. Implementation builds independently twice, requires byte-identical ZIPs and equal SHA-256 values, and records the derived Phase 3C.1 SHA only after those bytes exist.

#### Exact public API

All three classes are `final`, expose no public properties or setters, and redact submitted values, secrets, paths, exception messages, and traces.

| FQCN and path | Exact construction and public API | Invariants, creators, and consumers |
|---|---|---|
| `Grav\Plugin\GoosializeLeads\Application\CaptureResult`; `classes/Application/CaptureResult.php` | `private function __construct(bool $success, ?array $record, bool $replayed, ?string $code, array $errors)`; `public static function success(array $record, bool $replayed): self`; `public static function failure(string $code, array $errors = []): self`; `public function isSuccess(): bool`; `public function record(): ?array`; `public function replayed(): bool`; `public function code(): ?string`; `public function errors(): array`; `public function errorsAsArray(): array`; `public function toArray(): array` | Record PHPDoc is `array{id:string,status:string,created_at:string}`. Errors are `list<ValidationError>`. Failure codes are exactly `validation_failed`, `invalid_submission_id`, `idempotency_conflict`, `storage_unavailable`, and `forms_configuration_invalid`. Success has a record, `code=null`, and no errors; failure has no record, `replayed=false`, and only `validation_failed` may carry a non-empty error list. `toArray()` is exactly `array{success:bool,record:?array,replayed:bool,code:?string,errors:list<array{code:string,field:?string}>}`. Creator: `LeadCaptureService::capture()`. Consumer: `FormsLeadCaptureAdapter::process()`. |
| `Grav\Plugin\GoosializeLeads\Application\LeadCaptureService`; `classes/Application/LeadCaptureService.php` | `public function __construct(LeadPersistenceCoordinator $coordinator, IdempotencyKeyRing $keyRing)`; `public function capture(mixed $submitted, array $trusted, string $formName, string $submissionId, callable $entropy, callable $clock): CaptureResult` | Trusted PHPDoc is `array{source:string,form_name:string,locale:?string,consent_version:string}`; entropy is `callable(int):string`; clock is `callable():\DateTimeInterface`. It requires `formName === trusted.form_name`, validates the exact submission ID, calls `IdempotencyKeyRing::deriveFormsIdempotencyKey()`, then calls `LeadPersistenceCoordinator::persist()` once. It maps created/replayed records to the three-field success shape and maps existing persistence codes without exposing detail. Creator: plugin composition. Sole production caller: `FormsLeadCaptureAdapter::process()`. |
| `Grav\Plugin\GoosializeLeads\Http\FormsLeadCaptureAdapter`; `classes/Http/FormsLeadCaptureAdapter.php` | `public function __construct(LeadCaptureService $service, array $configuration, callable $entropy, callable $clock)`; `public function process(\RocketTheme\Toolbox\Event\Event $event): void` | Configuration PHPDoc is `array{enabled:bool,forms:list<string>,source:string,locale:?string,consent_version:string,success_redirect:string}` with the callable shapes above. It handles only the exact action and eligible form, builds exact submitted/trusted arrays, invokes the service once, and maps the result to Forms state. Creator and caller: `GoosializeLeadsPlugin::onFormProcessed()`. Tests may construct all three classes directly; no other production caller exists. |

`IdempotencyKeyRing` adds exactly `public function deriveFormsIdempotencyKey(string $formName, string $submissionId): string`. It requires an enabled active key, canonical lowercase form slug, and a 20-character lowercase ASCII alphanumeric submission ID. Its HMAC-SHA-256 input bytes are exactly `"grav-forms-v1\n" . $formName . "\n" . $submissionId . "\n"`; it uses the active decoded 32-byte key and returns the 64-character lowercase hexadecimal HMAC. The result is the opaque key passed to the existing coordinator. Missing/invalid configuration throws only `StorageException('key_configuration_invalid')`; invalid direct arguments throw `\InvalidArgumentException` without values.

`LeadCaptureService::capture()` maps `PersistenceResult` exactly: `created` becomes `CaptureResult::success()` with `replayed=false`; `replayed` becomes success with `replayed=true`; `validation_failed` preserves the ordered `ValidationError` list; `invalid_idempotency_key` becomes `invalid_submission_id`; `idempotency_conflict` and `idempotency_expired` become `idempotency_conflict`; `collision_exhausted`, `storage_unavailable`, any `StorageException`, and any impossible result invariant become `storage_unavailable`. Direct programmer misuse remains `\InvalidArgumentException`; no raw throwable crosses the service boundary.

`GoosializeLeadsPlugin::getSubscribedEvents()` adds exactly `'onFormProcessed' => ['onFormProcessed', 0]` to the existing two entries. `public function onFormProcessed(Event $event): void` first proves the exact action and `\Grav\Plugin\Form\Form` object, then reads only plugin configuration. It resolves the storage root exactly with `$this->grav['locator']->findResource('user-data://', true)` and requires a non-empty string. It creates one shared `static fn (int $length): string => random_bytes($length)` entropy callable and `static fn (): \DateTimeInterface => new \DateTimeImmutable('now', new \DateTimeZone('UTC'))` clock; constructs `IdempotencyKeyRing`, `FilesystemLeadRepository`, `LeadNormalizer`, `LeadInputValidator`, `LeadPersistenceCoordinator`, `LeadCaptureService`, and `FormsLeadCaptureAdapter` in that order; then invokes `process()` once. Configuration/root/construction failure sets only the unavailable Forms message and stops propagation. The plugin logs only stable code `forms_configuration_invalid`.

The production call graph is exactly Forms nonce/blueprint/honeypot validation → Forms `onFormProcessed` event → `GoosializeLeadsPlugin::onFormProcessed()` composition → `FormsLeadCaptureAdapter::process()` → `LeadCaptureService::capture()` → `IdempotencyKeyRing::deriveFormsIdempotencyKey()` → existing `LeadPersistenceCoordinator::persist()` → unchanged Phase 3A validation/record creation and Phase 3B persistence. Neither adapter nor service touches filesystem paths or stored JSON; only plugin composition resolves `user-data://`, and only `FilesystemLeadRepository` performs filesystem operations.

#### Configuration, eligibility, and fields

Configuration below `plugins.goosialize-leads.forms` is exact:

| YAML path | Type, default, and validation | Failure behavior |
|---|---|---|
| `enabled` | Boolean, default `false`; no coercion | Invalid disables Forms capture only. |
| `forms` | Zero-based list of unique case-sensitive lowercase slugs, default `[]`; each uses `^[a-z0-9](?:[a-z0-9_-]{0,62}[a-z0-9])?$` | Empty disables capture; invalid or duplicate entry disables Forms capture. |
| `source` | Lowercase slug, default `website` | Invalid disables Forms capture. |
| `locale` | String matching the existing locale grammar or `null`, default `null` | Invalid disables Forms capture. |
| `consent_version` | Lowercase slug, default `privacy-v1` | Invalid disables Forms capture. |
| `success_redirect` | Absolute internal route beginning `/`, default `/`; no scheme, authority, query, fragment, backslash, control, `.` or `..` segment; repeated slash rejected | Invalid disables Forms capture. |

`blueprints.yaml` exposes those exact keys and constraints. The adapter compares `Form::getFormName()` byte-for-byte against the configured unique list. Missing/disabled Forms, disabled integration, invalid configuration, non-listed forms, wrong action, or malformed event are inert and perform no write. Multiple configured names are independently eligible; duplicate names invalidate the Forms integration only. The standalone plugin continues loading without Forms.

The adapter calls `Form::value()` and accepts exactly these submitted names: `full_name`, `email`, `phone`, `company`, `message`, `resource_id`, `source_path`, `campaign`, and `consent`. Each is missing or a scalar accepted by Phase 3A; repeated/list values remain arrays and therefore fail Phase 3A type validation. `campaign` may only contain the five canonical campaign keys. `consent` must be exactly `['granted' => true]`. Unknown filtered data names cause form-level `validation_failed`; Forms infrastructure names are not returned by `Form::value()`. Normalization remains exclusively Phase 3A.

Trusted values are `source` from configuration, `form_name` from `getFormName()`, `locale` from configuration, and `consent_version` from configuration. No submitted value may override them. The submission identifier comes only from `Form::getUniqueId()`, must match `^[a-z0-9]{20}$`, and is never included in submitted fields, records, logs, messages, or paths. Phase 3A continues to require a valid name, email or phone, affirmative consent, and at least one of `message` or `resource_id`.

#### Exact Forms outcomes

- Created and replayed results both set public `$form->status = 'success'`, public `$form->message = 'PLUGIN_GOOSIALIZE_LEADS.FORMS_CAPTURE_SUCCESS'`, event `redirect` to the configured route, event `redirect_code` to integer `303`, then stop propagation. No Lead ID or submitted value enters the redirect or query. Forms performs the redirect and clears its normal flash data. Replay is indistinguishable to the visitor and causes no second write.
- Validation failure calls `Form::setMessage('PLUGIN_GOOSIALIZE_LEADS.FORMS_CAPTURE_INVALID')`, stops propagation, sets no redirect, and uses standard server-rendered redisplay. It exposes no primitive text or value; tests separately assert exact primitive mapping in `CaptureResult`.
- Invalid/missing submission ID, idempotency conflict, invalid configuration, and storage failure call `Form::setMessage('PLUGIN_GOOSIALIZE_LEADS.FORMS_CAPTURE_UNAVAILABLE')`, stop propagation, and set no redirect. Logging is one warning with only the stable scalar code and contains no form values, path, key, HMAC, exception, or trace.
- Missing/invalid nonce and honeypot failure are handled before the custom action by Forms; the adapter/service/repository invocation count is zero.
- XHR/AJAX is unavailable. Eligible forms must have `xhr_submit` absent or false; otherwise the adapter uses the unavailable error above, stops propagation, and produces no JSON response. Phase 3C.1 adds no renderer, template, definition, JavaScript, API route, or notification.

The exact English translations added during implementation are `Forms Lead captured.` for `FORMS_CAPTURE_SUCCESS`, `Please correct the form and try again.` for `FORMS_CAPTURE_INVALID`, and `We could not submit this form. Please try again later.` for `FORMS_CAPTURE_UNAVAILABLE`.

#### Exact tests and markers

- `php tests/unit/phase-3c-forms-capture.php` uses synthetic values `Synthetic Lead`, `lead@example.test`, `+35722000000`, message `Phase 3C.1 Forms fixture`, form `contact`, source `website`, consent `privacy-v1`, submission ID `0123456789abcdefghij`, UTC `2026-07-27T10:20:30.123456Z`, deterministic entropy, and the Phase 3B synthetic key. It reflects every new/changed public API; asserts result invariants, exact HMAC input/output against an independently computed `hash_hmac`, all persistence-result mappings, single service call, redaction, configuration validation, exact field/trusted arrays, eligibility, and no direct filesystem access. The corrected Phase 3B regression command `php tests/unit/phase-3b-secure-persistence.php` additionally owns the exact expanded `IdempotencyKeyRing` public-API allowlist and signature/behavior oracle without weakening any older assertion. The Phase 3C.1 unit test prints `PASS_PHASE_3C1_SHARED_SERVICE`.
- `GRAV_TEST_IMAGE=lscr.io/linuxserver/grav:2.0.12 tests/integration/phase-3c-grav-forms.sh` first requires the immutable image ID. It mounts the repository read-only and disposable synthetic storage, and uses the locally installed Forms 9.1.14 source fixture. A counting fake service plus real standard Forms POSTs assert plugin load with/without Forms, enabled/disabled integration, listed/unlisted form, action order, built-in nonce success/missing/invalid, honeypot, exact mapping, missing inquiry, malformed ID, created/replay/conflict, storage/configuration failure, 303 success redirect, failure redisplay, XHR rejection, no API route, notification, Admin2, theme, or direct adapter storage. Fault injection queues exact `CaptureResult` values and counts calls; it introduces no production hook. It prints `PASS_PHASE_3C1_NATIVE_FORMS` and `PASS_PHASE_3C1_DISABLED_INERT`.
- The integration script then runs the Phase 3C.1 unit test, `tests/integration/phase-3b-secure-storage.sh`, and the three existing package/Phase 2 scripts. Updated package tests assert the exact 27-file manifest/tree, seventeen runtime types, no `vendor/`, offline GPM install, two byte-identical builds, the newly derived SHA, and all Phase 3A, Phase 3B, and Phase 2B/2C/2D markers. It prints `PASS_PHASE_3C1_PACKAGE`.

Implementation requires all four Phase 3C.1 markers and every existing regression marker. Development fixtures use `.test` values only. Phase 3C.1 forbids public JSON/API hooks, raw JSON parsing, duplicate-member/depth/byte handling, Origin/proxy logic, endpoint rate limits, API statuses/bodies, XHR responses, custom renderer/template/JavaScript/form definition, Email/notification, Admin2/ACL/CSV, theme integration, real data, core/API/Forms plugin modification, and filesystem access outside `FilesystemLeadRepository`. All public JSON and XHR transport work is Phase 3C.2.

### Phase 3A — Lead data and validation primitives

- Branch: `feat/phase-3a-lead-data-validation`, created from `main` at the future post-correction HEAD; purpose: handwritten package autoload plus pure command, record, ID/clock, normalization and validation. Subject: `feat: add Phase 3 lead validation primitives`.
- Exact nine new files: `autoload.php`, `classes/Application/CaptureCommand.php`, `classes/Domain/LeadRecord.php`, `classes/Domain/LeadIdGenerator.php`, `classes/Validation/LeadInputValidator.php`, `classes/Validation/LeadNormalizer.php`, `classes/Validation/ValidationError.php`, `classes/Validation/ValidationResult.php`, `tests/unit/phase-3a-lead-data-validation.php`.
- Exact nine modified files: `goosialize-leads.php`, `composer.json`, `packaging/package-files.txt`, `tests/integration/clean-grav-plugin-load.sh`, `tests/integration/installable-plugin-package.sh`, `tests/integration/phase-2d-entry-points.sh`, `README.md`, `CHANGELOG.md`, `docs/OFFICIAL_VERIFICATION_LOG.md`.
- Package manifest source of truth: the existing nine paths plus root `autoload.php` and the seven runtime-class paths, for exactly 17 packaged regular files. The one unit test remains repository-only. The old SHA-256 `87f4ffda0bb8ceaac09d79d696a7364dae8cb5fe7323b4f3fe16bfa6f823ae6b` must change; two consecutive builds must be byte-identical and Phase 3A records the new SHA-256. No stale nine-file or 15-file assertion or `vendor/` path may remain.
- `tests/integration/installable-plugin-package.sh` derives and asserts the exact 17-file manifest, count and installed tree; verifies the loader and all seven runtime classes are installed; invokes the installed loader offline; reflects every class and public API signature; proves no `vendor/` directory; and preserves deterministic ZIP equality, ZIP safety, immutable input, offline GPM direct install, installed hashes, enabled/disabled load, and cleanup.
- `tests/integration/phase-2d-entry-points.sh` asserts the updated manifest/count while preserving exactly two inert subscriptions, zero functional routes, Twig behavior, the inert Admin2 component, disabled behavior, Phase 2B/2C regressions, and proof that packaged Phase 3A classes activate no Lead behavior.
- `tests/integration/clean-grav-plugin-load.sh` preserves clean Grav 2.0.12 enabled/disabled loading, invokes the packaged plugin autoload method, loads and reflects all seven Phase 3A classes and every exact public method, proves no missing loader/class and no functional route, filesystem storage, Forms capture, notification, or Admin2 management.
- Marker assertions: `PASS_PHASE_3A_AUTOLOAD` covers method signature, idempotence, prefix/name/containment rejection and every source-tree class; `PASS_PHASE_3A_SCHEMA` covers every public API reflection assertion, immutable value/result invariants, exact command/record shape, entropy/clock invocation and failure mapping, and canonical serialization; `PASS_PHASE_3A_VALIDATION` covers `ValidationError`/`ValidationResult`, the exhaustive table, per-field priority, accumulation, reachability and byte-identical ordering; `PASS_PHASE_3A_UNICODE` covers the pinned `ext-intl`/`Normalizer` probe and NFC/failure cases; `PASS_PHASE_3A_PACKAGE` covers the exact 17-file manifest/tree, seven-class reflection, no vendor, two identical builds, new hash and offline install; `PASS_PHASE_2B_REGRESSION`, `PASS_PHASE_2C_REGRESSION`, and `PASS_PHASE_2D_REGRESSION` require their complete existing suites and final markers.
- Forbidden: filesystem Lead persistence, locks/publication, idempotency-sidecar persistence, stale cleanup, functional HTTP route/controller, Forms processing, Email/notification, Admin2 management, permissions, real-data migration, and theme/core/API plugin modification.
- The checkpoint retains one commit directly parented by `main`, clean pre-commit review, a separate read-only merge review, local `git merge --ff-only`, the retained branch, no remote/push/publish/deploy, and all post-merge regressions.

### Phase 3B — secure filesystem repository and idempotency index

- Branch, subject, exact nineteen-path implementation manifest, APIs, call graph, tests, package contract, and forbidden scope are defined exclusively by the normative Phase 3B section above.
- Phase 3B retains immediate current-operation temporary cleanup only. Scheduled/post-crash cleanup and the previously proposed CLI/maintenance classes are deferred to Phase 7.
- Required markers are `PASS_PHASE_3B_NO_REPLACE`, `PASS_PHASE_3B_ATOMIC_STORAGE`, `PASS_PHASE_3B_IDEMPOTENCY`, `PASS_PHASE_3B_COLLISION_POLICY`, `PASS_PHASE_3B_CONCURRENCY`, and `PASS_PHASE_3B_IMMEDIATE_CLEANUP`, followed by all existing Phase 3A and Phase 2 regression markers.

### Phase 3C.1 — standard Grav Forms capture

- Branch, subject, exact seventeen-path manifest, three-class API, call graph, configuration, fields, outcomes, tests, package counts, and forbidden scope are defined exclusively by the normative Phase 3C.1 section above.
- Required markers are `PASS_PHASE_3C1_SHARED_SERVICE`, `PASS_PHASE_3C1_NATIVE_FORMS`, `PASS_PHASE_3C1_DISABLED_INERT`, and `PASS_PHASE_3C1_PACKAGE`, followed by all Phase 3A, Phase 3B, and Phase 2 regression markers.

### Phase 3C.2 — public JSON and XHR transport

- Owns all public JSON routes and controllers, API lifecycle/public-route hooks, raw JSON byte/depth/duplicate enforcement, Origin and proxy-aware policy, endpoint abuse control and rate limiting, API status/error bodies, CORS/cache behavior, and any XHR/AJAX transport.
- Requires a separate normative source-proven API, manifest, security, and test contract before branch creation. Phase 3C.1 implements none of this scope.

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
| 3A | Implement the specified handwritten root `autoload.php`; require it once from `GoosializeLeadsPlugin::autoload(): void`; implement the seven-class normative API table; update the exact 17-file manifest and three integration scripts; install offline through GPM and reflect every class and method with no `vendor/`. | Pass: exact constructors/factories/types/results/callables/exceptions, idempotent contained loader, exact manifest/tree, byte-identical builds, new recorded hash, installed reflection and Phase 2 regressions; fail: any API mismatch, escape, foreign lookup, missing/extra file, generated dependency, nondeterminism, or activated functional behavior; fallback: no Phase 3A runtime classes and checkpoint failure. |
| 3A | Probe `extension_loaded('intl')`, `class_exists('Normalizer')`, `Normalizer::FORM_C`, `normalize()` and `isNormalized()` in the pinned image using decomposed `e` plus `U+0301`, expecting `U+00E9`; declare `"ext-intl": "*"` and test capability before validation. | Pass: all capabilities exist and exact NFC output verifies; fail: absence, false return, mismatch, or unverifiable result; fallback: fail closed with `unicode_normalization_unavailable` or `unicode_normalization_failed`, including ASCII input, without runtime detail or submitted values. |
| 3A | Execute every row of the exhaustive primitive error table, including multi-error permutations, and the exact conservative ASCII email examples/boundaries. | Pass: one primary error per field, fixed canonical/unknown/cross-field ordering and byte-identical results; fail: unmapped rejection, unstable order, value/PII leakage, or grammar mismatch; fallback: checkpoint failure and no capture adapters. |
| 3B | Probe the exact trusted user-data locator, `0700/0600`, file `fsync`, `flock`, hard-link no-replace publication, process-visible atomicity, containment/symlink rejection, current-operation cleanup, and every namespaced fault shim stage in `tests/integration/phase-3b-secure-storage.sh`. | Pass: all six exact Phase 3B markers plus no overwrite, no escape, no partial final, no current temp, and documented absence of directory-fsync/power-loss guarantee; fail: any marker or invariant fails; fallback: no Phase 3B merge and capture remains unavailable. |
| 3B | Validate required externally configured canonical-Base64 32-byte HMAC keys and active/historical versions in `tests/unit/phase-3b-secure-persistence.php`; run sidecar rotation/replay integration assertions. | Pass: version 2 signs new payloads, versions 1 and 2 verify their own historical digests with `hash_equals()`, invalid/missing keyed configuration fails redacted, and no secret enters source/package/output; fail: loss, ambiguity, leakage, or unkeyed digest; fallback: keyed capture fails closed and no implementation merge. |
| 3C.1 | Prove Forms 9.1.14 `onFormProcessed`, built-in nonce/validation/honeypot ordering, exact unique ID, configured eligibility, standard POST/303, failure redisplay, and absence of XHR/custom rendering. | Pass: all four Phase 3C.1 markers and theme-free standard flow; fail: bypass, ambiguity, duplicate invocation, or later-scope behavior; fallback: disable Forms capture while the plugin remains loaded. |
| 3C.2 | Define and prove the public-route hook, cache invalidation, proxy-aware same-origin/missing-Origin/CSRF policy, pre-buffer byte/duplicate detector, endpoint abuse control, API schemas, and XHR behavior. | Pass: a separate exact anonymous security/status contract and tests; fail: incomplete or fail-open endpoint; fallback: no public API or XHR implementation. |
| 3D | Probe Email 5.0.3 service availability, `message(): Message`, exact accepted `send(): int` statuses, construction/delivery separation, and redaction without using plugin debug output. | Pass: source signatures plus one minimized post-durable attempt with accepted status interpretation and redacted failure; fail: signature/status mismatch, leakage, duplicate, or ordering defect; fallback: notification disabled while stored Lead remains valid. |
| 3E | Re-probe retention, backup, update/removal and supported filesystem in clean-install regression. | Pass: data boundary and package invariants; fail: loss/exposure; fallback: block Phase 3 completion. |

Licensing and marketplace publication remain Phase 8 decisions and are not hidden Phase 3 core contracts.

## Phase 3C.2 normative public JSON API contract

Phase 3C.2 is implemented on branch `feat/phase-3c2-public-json-api` in one commit with subject `feat: add public JSON Lead capture API`. It adds one anonymous JSON transport and reuses `LeadCaptureService`, `LeadInputValidator`, `LeadPersistenceCoordinator`, `IdempotencyKeyRing`, `FilesystemLeadRepository`, and `CaptureResult` without a second validation or persistence pipeline. Forms, rendering, JavaScript, notifications, Admin2, ACL UI, CSV/export, themes, migration, delivery, OAuth, accounts, CAPTCHA providers, Redis, and databases remain forbidden.

### Exact implementation manifest

The ten new packaged runtime files are:

- `classes/Http/ApiParseResult.php`
- `classes/Http/ApiRequestMapper.php`
- `classes/Http/ApiRequestResult.php`
- `classes/Http/ApiResponseMapper.php`
- `classes/Http/EndpointRateLimiter.php`
- `classes/Http/OriginPolicy.php`
- `classes/Http/PublicApiRawBodyMiddleware.php`
- `classes/Http/PublicLeadApiController.php`
- `classes/Http/RateLimitResult.php`
- `classes/Http/RawJsonParser.php`

The two new development-only tests are `tests/unit/phase-3c2-public-json-api.php` and `tests/integration/phase-3c2-public-json-api.sh`. The fourteen modified non-documentation files are `classes/Application/LeadCaptureService.php`, `classes/Security/IdempotencyKeyRing.php`, `goosialize-leads.php`, `goosialize-leads.yaml`, `blueprints.yaml`, `packaging/package-files.txt`, `tests/integration/clean-grav-plugin-load.sh`, `tests/integration/installable-plugin-package.sh`, `tests/integration/phase-2d-entry-points.sh`, `tests/integration/phase-3c-grav-forms.sh`, `tests/unit/phase-3b-secure-persistence.php`, `tests/unit/phase-3c-forms-capture.php`, `README.md`, and `CHANGELOG.md`. The sole implementation-evidence documentation file is `docs/OFFICIAL_VERIFICATION_LOG.md`. This plan remains an immutable committed contract during implementation.

The implementation therefore changes exactly 27 paths: 12 new runtime/test paths, 14 modified runtime/configuration/package/regression paths, and the one verification-log evidence path. Exactly ten new runtime classes enter `packaging/package-files.txt`; the two tests do not. The exact final package manifest is the current 27 package paths unchanged and in their current order, followed by these ten paths in lexical order: `classes/Http/ApiParseResult.php`, `classes/Http/ApiRequestMapper.php`, `classes/Http/ApiRequestResult.php`, `classes/Http/ApiResponseMapper.php`, `classes/Http/EndpointRateLimiter.php`, `classes/Http/OriginPolicy.php`, `classes/Http/PublicApiRawBodyMiddleware.php`, `classes/Http/PublicLeadApiController.php`, `classes/Http/RateLimitResult.php`, and `classes/Http/RawJsonParser.php`. The current 27 package paths are exactly `CHANGELOG.md`, `README.md`, `admin-next/pages/goosialize-leads.js`, `autoload.php`, `blueprints.yaml`, `classes/Application/CaptureCommand.php`, `classes/Application/CaptureResult.php`, `classes/Application/LeadCaptureService.php`, `classes/Application/LeadPersistenceCoordinator.php`, `classes/Domain/LeadIdGenerator.php`, `classes/Domain/LeadRecord.php`, `classes/Http/FormsLeadCaptureAdapter.php`, `classes/Security/IdempotencyKeyRing.php`, `classes/Storage/FilesystemLeadRepository.php`, `classes/Storage/LeadRepository.php`, `classes/Storage/PersistenceRequest.php`, `classes/Storage/PersistenceResult.php`, `classes/Storage/StorageException.php`, `classes/Validation/LeadInputValidator.php`, `classes/Validation/LeadNormalizer.php`, `classes/Validation/ValidationError.php`, `classes/Validation/ValidationResult.php`, `composer.json`, `goosialize-leads.php`, `goosialize-leads.yaml`, `languages/en.yaml`, and `templates/phase-2d-skeleton.html.twig`.

The resulting package and installed tree each contain 37 regular files. The reflection list is the existing seventeen runtime types plus, in manifest order: `Grav\Plugin\GoosializeLeads\Http\ApiParseResult`, `ApiRequestMapper`, `ApiRequestResult`, `ApiResponseMapper`, `EndpointRateLimiter`, `OriginPolicy`, `PublicApiRawBodyMiddleware`, `PublicLeadApiController`, `RateLimitResult`, and `RawJsonParser`, for 27 runtime types. No `tests/`, docs other than packaged files already present, ZIP, VCS metadata, fixture, cache, rate-limit state, data record, sidecar, log, `vendor/`, or development tool enters the package. The Phase 3C.1 SHA `21e5ed92b950706955512c344ce3d5b7227cf929d5f6287b57e4a8c08219f9e9` remains the baseline; the Phase 3C.2 SHA is recorded only after two byte-identical implementation builds.

### Exact runtime APIs

All classes below are final and throw no public exception for ordinary request faults. Result objects are immutable.

| FQCN and path | Exact public API | Creator, consumer, and state |
|---|---|---|
| `Grav\Plugin\GoosializeLeads\Http\ApiParseResult`; `classes/Http/ApiParseResult.php` | Private `__construct(bool $valid, ?array $object, ?string $code)`; `valid(array $object): self`; `failure(string $code): self`; `isValid(): bool`; `object(): ?array`; `code(): ?string`. Object shape is `array<string,mixed>`. | `RawJsonParser::parse()` creates; middleware consumes. Internal failure codes are exactly `EMPTY_BODY`, `PAYLOAD_TOO_LARGE`, `INVALID_UTF8`, `MALFORMED_JSON`, `TOO_DEEP`, `DUPLICATE_JSON_KEY`, `OBJECT_REQUIRED`. Direct construction is forbidden; factories are directly testable. |
| `Grav\Plugin\GoosializeLeads\Http\RawJsonParser`; `classes/Http/RawJsonParser.php` | Public parameterless constructor; `parse(string $bytes, int $maximumBytes, int $maximumDepth): ApiParseResult`. | Middleware creates and calls it. It returns results and does not leak decoder messages. Direct tests are required. |
| `Grav\Plugin\GoosializeLeads\Http\ApiRequestResult`; `classes/Http/ApiRequestResult.php` | Private `__construct(bool $valid, ?array $submitted, ?array $trusted, ?string $idempotencyKey, ?string $code)`; `valid(array $submitted, array $trusted, string $idempotencyKey): self`; `failure(string $code): self`; accessors `isValid(): bool`, `submitted(): ?array`, `trusted(): ?array`, `idempotencyKey(): ?string`, `code(): ?string`. Both arrays are `array<string,mixed>`. | Mapper creates; controller consumes. Internal codes are exactly `UNKNOWN_MEMBER`, `REQUEST_SCHEMA_INVALID`, `MISSING_IDEMPOTENCY_KEY`, `INVALID_IDEMPOTENCY_KEY`. |
| `Grav\Plugin\GoosializeLeads\Http\ApiRequestMapper`; `classes/Http/ApiRequestMapper.php` | Public parameterless constructor; `map(array $object, string $idempotencyKey, array $config): ApiRequestResult`, with `array<string,mixed>` input and configuration. | Controller creates and calls it. Direct deterministic tests are required. |
| `Grav\Plugin\GoosializeLeads\Http\OriginPolicy`; `classes/Http/OriginPolicy.php` | Public parameterless constructor; `evaluate(Psr\Http\Message\ServerRequestInterface $request, array $allowedOrigins): ?string`, where allowlist is `list<string>`. | Middleware creates and calls it. Null means allowed; internal codes are `ORIGIN_MISSING`, `ORIGIN_INVALID`, `ORIGIN_FORBIDDEN`. |
| `Grav\Plugin\GoosializeLeads\Http\RateLimitResult`; `classes/Http/RateLimitResult.php` | Private `__construct(bool $allowed, int $limit, int $remaining, int $retryAfter, ?string $code)`; `allowed(int $limit, int $remaining): self`; `limited(int $limit, int $retryAfter): self`; `unavailable(int $limit): self`; accessors `isAllowed(): bool`, `limit(): int`, `remaining(): int`, `retryAfter(): int`, `code(): ?string`. | Limiter creates; middleware and response mapper consume. Codes are null, `RATE_LIMITED`, or `RATE_LIMIT_UNAVAILABLE`. |
| `Grav\Plugin\GoosializeLeads\Http\EndpointRateLimiter`; `classes/Http/EndpointRateLimiter.php` | `__construct(string $userDataRoot, callable $clock)` where clock is `callable():int`; `check(string $clientAddress, int $limit, int $windowSeconds): RateLimitResult`. | Plugin creates; middleware calls. It catches storage faults and returns unavailable. Direct clock and filesystem fault tests are required. |
| `Grav\Plugin\GoosializeLeads\Http\ApiResponseMapper`; `classes/Http/ApiResponseMapper.php` | Public parameterless constructor; `success(Grav\Plugin\GoosializeLeads\Application\CaptureResult $result): Psr\Http\Message\ResponseInterface`; `failure(string $code, array $errors = [], ?int $retryAfter = null): Psr\Http\Message\ResponseInterface`, with `list<array{field:string,code:string}>`. | Controller and middleware create/call it; Grav emits the response. No submitted values enter output. |
| `Grav\Plugin\GoosializeLeads\Http\PublicApiRawBodyMiddleware`; `classes/Http/PublicApiRawBodyMiddleware.php` | `__construct(RawJsonParser $parser, OriginPolicy $originPolicy, EndpointRateLimiter $rateLimiter, ApiResponseMapper $responses, array $config)`; `process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface`. | `GoosializeLeadsPlugin::onRequestHandlerInit()` creates it and adds it before API routing; it calls the next handler only after attaching the validated parse result as request attribute `goosialize_leads.api_parse_result`. |
| `Grav\Plugin\GoosializeLeads\Http\PublicLeadApiController`; `classes/Http/PublicLeadApiController.php` | `__construct(Grav\Common\Grav $grav, Grav\Common\Config\Config $config)`; `capture(ServerRequestInterface $request): ResponseInterface`. | API router constructs it; route calls `capture()`. It consumes the middleware attribute, maps schema, calls `LeadCaptureService::captureApi()`, and maps `CaptureResult`. |
| `Grav\Plugin\GoosializeLeads\Security\IdempotencyKeyRing`; existing path | Add `deriveApiIdempotencyKey(string $idempotencyKey): string`. | Controller calls it through the capture service. The exact active-key HMAC input is ASCII `public-api-v1`, LF, the case-preserved header bytes, LF. It returns lowercase hexadecimal HMAC-SHA-256. |
| `Grav\Plugin\GoosializeLeads\Application\LeadCaptureService`; existing path | Add `captureApi(mixed $submitted, array $trusted, string $idempotencyKey, callable $entropy, callable $clock): CaptureResult`, with `array<string,mixed>` trusted data, `callable(int):string` entropy, and `callable():DateTimeInterface` clock. | Controller calls; service invokes the existing validator and coordinator exactly as Forms capture does. |
| `Grav\Plugin\GoosializeLeadsPlugin`; `goosialize-leads.php` | Existing `getSubscribedEvents(): array` adds `onApiCollectPublicRoutes` and `onRequestHandlerInit` at priority 98000; existing `onApiRegisterRoutes(Event $event): void` performs route registration; add `onApiCollectPublicRoutes(Event $event): void` and `onRequestHandlerInit(RequestHandlerEvent $event): void`. | Grav creates and calls the plugin. The three event handlers validate config, register/classify only the exact route, and compose the runtime classes; direct test access is limited to the source fixture and integration test. |

### Route, lifecycle, and transport

The sole route-registration hook is `onApiRegisterRoutes`. `GoosializeLeadsPlugin::onApiRegisterRoutes()` obtains the event’s `ApiRouteCollector` and calls `post('/goosialize-leads/capture', [PublicLeadApiController::class, 'capture'])`. Separately, `onApiCollectPublicRoutes` does not register a route: it appends the exact method-scoped string `POST /api/v1/goosialize-leads/capture` to the event’s `exact` list so only this POST bypasses API authentication. The sole full path is `/api/v1/goosialize-leads/capture`; it has no route name, locale prefix, configurability, or trailing-slash alias. API-plugin absence means its events never fire and the plugin remains loadable with no route. Disabled or invalid public-API configuration likewise registers no route or public classification while Forms remains available. A conflicting route is a configuration failure: this plugin registers no alternative.

The plugin also subscribes to Grav `onRequestHandlerInit` at priority 98000. The API plugin registers its `ApiRouter` at priority 99000; this plugin then prepends `PublicApiRawBodyMiddleware`, so its raw stream check executes before API `JsonBodyParserMiddleware`. The middleware handles only the exact path. It requires a seekable stream, rewinds it, reads at most 16,385 raw bytes, and rewinds again; non-seekable, rewind, or read failure returns `request_unavailable`. It never trusts Grav/API parsed-body values.

Only POST is allowed. Other methods, including OPTIONS, return 405 and `Allow: POST`; no preflight or credentialed CORS facility exists. `Content-Type` must be `application/json` with either no parameter or exactly one case-insensitive `charset=utf-8`; every other type, charset, duplicate parameter, or malformed value is 415. `Accept` may be absent, `*/*`, `application/json`, or a comma-separated list containing either media range; otherwise 406. All responses are UTF-8 JSON with `Content-Type: application/json; charset=utf-8`, `Cache-Control: no-store`, `X-Content-Type-Options: nosniff`, and `Vary: Origin`. No `Access-Control-Allow-Credentials` is emitted. An allowed cross-origin response echoes its canonical Origin in `Access-Control-Allow-Origin`; same-origin emits none.

### Raw JSON, Origin, and abuse controls

The raw-body limit is exactly 16,384 bytes inclusive. Empty means zero bytes. UTF-8 is checked with `preg_match('//u', $bytes) === 1`; a UTF-8 BOM is invalid JSON. Root depth is 1 and maximum nesting depth is exactly 4. Before `json_decode()`, a single-pass byte scanner tracks strings, escapes, arrays, objects, and object-member positions, decodes JSON string escapes including surrogate pairs solely for key comparison, and rejects a decoded duplicate name within the same object; nested objects have independent sets. It rejects malformed escapes and invalid surrogate pairs. Then `json_decode($bytes, true, 4, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING)` must consume the entire input and produce an object. Any JSON number anywhere is rejected as `request_schema_invalid`; no accepted field uses a number. Trailing non-whitespace bytes, excessive depth, arrays/scalars at root, and duplicates have their named failures. Strings retain JSON escape semantics and enter Phase 3A unchanged. Unknown members are rejected; no first-value selection or coercion exists.

`Origin` is mandatory and exactly one header value. `null`, comma-joined/multiple, malformed, credential-bearing, path/query/fragment-bearing, non-HTTP(S), Unicode/IDNA, or trailing-dot origins are rejected. Hosts are canonical ASCII lowercase; IPv4 and bracketed IPv6 must parse canonically. Default ports 80/443 are removed and every other explicit port is preserved. An Origin is allowed only if it exactly equals the canonical direct request URI scheme/host/port or an exact canonical entry in `plugins.goosialize-leads.public_api.allowed_origins`. Wildcards are forbidden. No source-proven proxy-address allowlist exists in Grav 2.0.12, so `Forwarded`, `X-Forwarded-For`, `X-Forwarded-Host`, `X-Forwarded-Port`, and `X-Forwarded-Proto` never influence origin or client identity, regardless of sender.

Rate limiting occurs after path, method, negotiation, raw-size, and Origin checks but before JSON parsing. The key is the canonical direct server `REMOTE_ADDR` only: canonical IPv4 or lowercase compressed IPv6; missing or invalid address fails closed. It is a fixed window of 10 accepted checks per 60 seconds with no extra burst. State survives process restart under `<user-data>/goosialize-leads/v1/api-rate-limit/`, directory mode 0700 and regular lock/state files 0600. The filename is lowercase SHA-256 of canonical address; file content is exact JSON `{"window_start":<integer>,"count":<integer>}` plus LF. One exclusive `flock()` covers read, validation, increment, truncate/write/flush, and unlock; symlinks, non-regular files, wrong containment, malformed state, permission failure, lock failure, or I/O failure return 503. The injected integer Unix-seconds clock defines windows. Each allowed check removes at most 100 expired regular state files while holding a separate cleanup lock; symlinks are ignored. At 10,000 state files, unknown keys fail closed until cleanup reduces the count. Limited requests return 429 with `Retry-After` equal to `window_start + 60 - now`, minimum 1.

### Request mapping, idempotency, and configuration

The accepted client members are exactly `full_name`, `first_name`, `last_name`, `email`, `phone`, `company`, `message`, `resource_id`, `source_path`, `campaign`, and `consent`. Each is optional except consent and is passed to Phase 3A without normalization. The nine scalar fields must be JSON string or null. `campaign` must be null or an object containing only `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, and `utm_content`, each string or null. `consent` must be exactly the object `{"granted":true}`. Empty strings, lengths, full-name versus paired-name rules, at least one email/phone, and at least one message/resource are unchanged Phase 3A validation. `source`, `form_name`, `locale`, and `consent_version` are server-owned: respectively `public_api`, `public_api`, configured locale or null, and configured consent version; any client occurrence is an unknown member. API idempotency is solely the HTTP header `Idempotency-Key`: exactly one value, 16–128 case-sensitive ASCII characters matching `[A-Za-z0-9._~-]+`; absence, commas, multiple values, whitespace, or other bytes fail. The raw header is never stored or logged. Identical normalized payload replays with success; a changed payload using the same derived key returns conflict.

| YAML path | Type, default, and exact validation |
|---|---|
| `plugins.goosialize-leads.public_api.enabled` | Boolean, default `false`; any non-Boolean disables only public API capture. |
| `plugins.goosialize-leads.public_api.allowed_origins` | `list<string>`, default `[]`; every entry must satisfy the exact canonical Origin grammar, be unique, and contain no wildcard; otherwise public API is disabled. |
| `plugins.goosialize-leads.public_api.locale` | String or null, default null; non-null must match `[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*`, canonicalized with lowercase language and uppercase two-letter region; otherwise public API is disabled. |
| `plugins.goosialize-leads.public_api.consent_version` | Required lowercase slug matching `[a-z0-9](?:[a-z0-9._-]{0,62}[a-z0-9])?`; missing/invalid disables public API. |
| `plugins.goosialize-leads.public_api.body_max_bytes` | Integer, default and only allowed value `16384`; mismatch disables public API. |
| `plugins.goosialize-leads.public_api.json_max_depth` | Integer, default and only allowed value `4`; mismatch disables public API. |
| `plugins.goosialize-leads.public_api.rate_limit_count` | Integer, default and only allowed value `10`; mismatch disables public API. |
| `plugins.goosialize-leads.public_api.rate_limit_window_seconds` | Integer, default and only allowed value `60`; mismatch disables public API. |

`blueprints.yaml` exposes enabled, exact-origin list, locale, and consent version. The fixed security limits are visible read-only descriptions, not editable alternatives. There is no endpoint, proxy, storage-root, or public-message setting. Any invalid Phase 3C.2 setting is redacted, prevents route registration, and never disables the plugin or Forms capture.

### Deterministic responses and call graph

JSON is encoded without pretty printing or escaped slashes. Success key order is `ok`, `code`, `message`, `lead_id`: created is 201/`created`/`Lead captured.` and replay is 200/`replayed`/`Lead already captured.`; both expose the opaque Lead ID. Error key order is `ok`, `code`, `message`, then `errors` only for validation and `retry_after` only for rate limiting. `errors` preserves Phase 3A deterministic order and contains only `field` then `code`.

| Outcome codes | HTTP | Exact safe message and additions |
|---|---:|---|
| `unsupported_method` | 405 | `Method not allowed.`; `Allow: POST` |
| `not_acceptable` | 406 | `JSON response required.` |
| `unsupported_media_type` | 415 | `JSON request required.` |
| `empty_body`, `invalid_utf8`, `invalid_json`, `too_deep`, `duplicate_json_key`, `object_required`, `unknown_member`, `request_schema_invalid`, `missing_idempotency_key`, `invalid_idempotency_key` | 400 | `Invalid request.` |
| `payload_too_large` | 413 | `Request body too large.` |
| `origin_missing`, `origin_invalid`, `origin_forbidden` | 403 | `Origin not allowed.` |
| `validation_failed` | 422 | `Please correct the request and try again.`; includes redacted primitive `errors` |
| `idempotency_conflict` | 409 | `Request conflicts with an earlier submission.` |
| `rate_limited` | 429 | `Too many requests.`; integer `retry_after` and matching `Retry-After` |
| `request_unavailable`, `rate_limit_unavailable`, `storage_unavailable`, `configuration_unavailable`, `api_dependency_unavailable` | 503 | `Service unavailable.` |
| `internal_error` | 500 | `Unexpected error.` |

Except for the two success bodies, the exact base body is `{"ok":false,"code":"<code>","message":"<message>"}`. No exception, stack, path, submitted value, Origin, address, header, key, HMAC, or filesystem detail enters body or headers. Logs contain only stable code, request correlation token, and outcome; never Lead data or transport identifiers.

Production order is exact: `GoosializeLeadsPlugin::onApiRegisterRoutes()` registers the POST route and `onApiCollectPublicRoutes()` marks only that method/path public; `onRequestHandlerInit()` installs `PublicApiRawBodyMiddleware::process()`; middleware checks method/negotiation, raw bytes, Origin, and rate limit, then `RawJsonParser::parse()` and attaches `ApiParseResult`; API dispatch constructs `PublicLeadApiController`, whose `capture()` calls `ApiRequestMapper::map()`, then `LeadCaptureService::captureApi()`; the service calls `IdempotencyKeyRing::deriveApiIdempotencyKey()`, unchanged Phase 3A validation, and unchanged Phase 3B coordinator/repository; `ApiResponseMapper` maps `CaptureResult`; the controller logs only the redacted boundary outcome.

### Exact verification

`php tests/unit/phase-3c2-public-json-api.php` uses synthetic `example.test` values, deterministic clocks/entropy, raw-byte scanner fixtures, duplicate decoded-key aliases, depth 4/5, every schema member/type, header grammar, origin grammar, response body/header oracle, Phase 3A inquiry failures, replay/conflict, rate boundary/concurrency, symlink/malformed/I/O fault shims, and reflection of every API above; it prints exactly `PASS_PHASE_3C2_SHARED_API`.

`GRAV_TEST_IMAGE=lscr.io/linuxserver/grav:2.0.12 tests/integration/phase-3c2-public-json-api.sh` uses `docker run --rm --network none`, a synthetic temporary Grav tree and API source fixture, never the reference data. It proves absent/present API load, disabled/invalid inertness, exact hook/path/controller, middleware-before-decoder ordering, POST/405/OPTIONS, negotiation, 16,384/16,385 bytes, UTF-8, syntax/depth/duplicates, every Origin and direct-address rule, 10/11 boundary, concurrent rate state, unavailable storage, all response oracles and redaction; it prints `PASS_PHASE_3C2_RAW_JSON`, `PASS_PHASE_3C2_PUBLIC_ROUTE`, and `PASS_PHASE_3C2_RATE_LIMIT`.

The same integration script runs existing Phase 3A, Phase 3B, Phase 3C.1, and Phase 2B/2C/2D commands, proves no notification/Admin2/theme behavior, and prints `PASS_PHASE_3C2_REGRESSIONS`. Updated package scripts build twice, compare bytes and SHA, offline GPM-install, assert exactly 37 manifest/install files and all 27 reflected runtime types, retain the Phase 3C.1 baseline, reject development/state/data files, and print `PASS_PHASE_3C2_PACKAGE`. Any missing marker, unsupported lifecycle, extra path, unsafe output, nondeterminism, or regression blocks implementation commit and leaves the public API disabled.

## Phase 4A.1 bounded native Admin2 Lead Index

Phase 4A.1 is implemented on `feat/phase-4a1-bounded-admin2-lead-index` in one commit with subject `feat: add bounded Admin2 Lead Index`. It provides one read-only API/ACL-backed Admin2 page through Admin2 2.0.15's declarative `resource-table`. It adds no plugin-owned JavaScript, web component, Shadow DOM, CSS, compiled asset, detail view, row action, mutation, export, notification, or capture change.

The installed resource-table calls one endpoint without page, page-size, filter, or sort parameters, filters the returned `data` array client-side, and renders every filtered row. Phase 4A.1 therefore has no pagination or interactive sorting. The provider returns at most 100 records in one fixed order. The page title is exactly `Leads — latest 100`, permanently disclosing that older records can be outside the bounded index. Phase 4A.2 owns detail only after a native architecture is separately proven; Phase 4B owns status and reversible mutations; Phase 4C owns export; notifications remain later.

### Phase 4A.1 exact implementation manifest

The future implementation changes exactly 24 paths: ten new and fourteen modified.

New paths are `admin/blueprints/goosialize-leads-index.yaml`, `classes/Admin/LeadIndexCollection.php`, `classes/Admin/LeadIndexQuery.php`, `classes/Admin/LeadSummary.php`, `classes/Admin/LeadsIndexController.php`, `classes/Storage/FilesystemLeadReadRepository.php`, `classes/Storage/LeadReadRepository.php`, `permissions.yaml`, `tests/integration/phase-4a1-bounded-admin2-lead-index.sh`, and `tests/unit/phase-4a1-bounded-admin2-lead-index.php`.

Modified paths are `CHANGELOG.md`, `README.md`, `blueprints.yaml`, `classes/Storage/StorageException.php`, `docs/OFFICIAL_VERIFICATION_LOG.md`, `goosialize-leads.php`, `goosialize-leads.yaml`, `packaging/package-files.txt`, `tests/integration/clean-grav-plugin-load.sh`, `tests/integration/installable-plugin-package.sh`, `tests/integration/phase-2d-entry-points.sh`, `tests/integration/phase-3c-grav-forms.sh`, `tests/integration/phase-3c2-public-json-api.sh`, and `tests/unit/phase-3b-secure-persistence.php`.

Exactly six runtime types, one declarative page, one permissions definition, one unit test, and one integration test are new. The six runtime types plus the page and permissions enter the package; tests do not. The package/installed tree grows from 37 to 45 files and reflection from 27 to 33 types. Append these manifest entries in exact order: `admin/blueprints/goosialize-leads-index.yaml`, `classes/Admin/LeadIndexCollection.php`, `classes/Admin/LeadIndexQuery.php`, `classes/Admin/LeadSummary.php`, `classes/Admin/LeadsIndexController.php`, `classes/Storage/FilesystemLeadReadRepository.php`, `classes/Storage/LeadReadRepository.php`, `permissions.yaml`. No tests, fixtures, data, state, logs, ZIP, VCS metadata, `vendor/`, JavaScript, CSS, Svelte, or compiled Admin2 asset enters the package.

### Bounded filesystem read policy

`FilesystemLeadReadRepository` receives the real `user-data://` root and owns reads below `goosialize-leads/v1/records`. Discovery considers only exact primary Lead record filenames. It never opens, reads, parses, decodes, or content-validates a sidecar and never stats a sidecar for content validation. Missing, malformed, and oversized synthetic sidecars have no effect on the collection; sidecar validity remains a Phase 3B capture/replay concern and no sidecar value or path enters a result or log. A source-compatible test-only same-namespace `fopen()` shim rejects any path containing `/idempotency/` and records the attempt, while delegating every primary-record open to global `fopen()`; collection equality across absent, malformed, and oversized synthetic sidecars plus an attempt count of zero proves read isolation without opening those sidecars. Every concatenated primary-record segment is grammar-checked; `lstat()`, `realpath()`, containment, non-symlink directory, regular-file, and `0600` checks are mandatory. Only `YYYY/MM/<32 lowercase hex>.json` is accepted. Temporary, unknown, malformed, non-regular, or incorrectly permissioned entries in the records tree fail the request.

Iteration is bounded to 256 year/month directory entries and 10,001 record candidates. Candidate 10,001 returns `lead_index_capacity_exceeded` with no partial data. Each accepted record is at most 32,768 bytes, valid UTF-8 JSON, an associative object, and the exact Phase 3B canonical schema. At most 10,000 decoded records are resident. A corrupt, malformed, or incomplete record returns `lead_index_record_invalid`; none is repaired or skipped. Reads perform no write, chmod, rename, unlink, lock creation, sidecar access, or value/path logging.

For 0–10,000 valid records, sort by canonical UTC `created_at` descending, then `id` ascending using bytewise comparison. Missing/malformed timestamps fail rather than sort as null. Return the first 100. `truncated` is true exactly when valid count exceeds 100; `total_scanned` is the valid count. There is no configurable limit, pagination emulation, or older-record endpoint.

### Exact public APIs

| Type/path | Exact API | Creator, consumer, and invariant |
|---|---|---|
| `final Admin\LeadSummary`; `classes/Admin/LeadSummary.php` | Private constructor; `fromRecord(array $record): self`; getters `id(): string`, `createdAt(): string`, `name(): ?string`, `email(): ?string`, `source(): string`, `formName(): string`, `status(): string`; `toArray(): array{id:string,created_at:string,name:?string,email:?string,source:string,form_name:string,status:string}`. | Repository creates; collection/controller consume. Bad input throws `InvalidArgumentException('Invalid Lead summary record.')`. |
| `final Admin\LeadIndexQuery`; `classes/Admin/LeadIndexQuery.php` | Private constructor; `newest(): self`; `limit(): int` always 100. | Controller creates; repository consumes. Immutable, with no page, filter, cursor, or sort input. |
| `final Admin\LeadIndexCollection`; `classes/Admin/LeadIndexCollection.php` | Private constructor; `create(array $summaries, bool $truncated, int $totalScanned): self`; `summaries(): array`; `truncated(): bool`; `totalScanned(): int`; `toResponse(): array{data:list<array<string,mixed>>,meta:array{read_only:true,count:int,limit:100,truncated:bool,total_scanned:int,allowed_statuses:list<string>}}`. | Repository creates; controller consumes. Requires a list of at most 100 summaries and internally consistent totals/truncation; otherwise `InvalidArgumentException('Invalid Lead index collection.')`. |
| `Storage\LeadReadRepository`; `classes/Storage/LeadReadRepository.php` | Interface containing only `latest(LeadIndexQuery $query): LeadIndexCollection`. | Controller depends on it; filesystem reader and unit fakes implement it. |
| `final Storage\FilesystemLeadReadRepository`; `classes/Storage/FilesystemLeadReadRepository.php` | `__construct(string $userDataRoot)`; `latest(LeadIndexQuery $query): LeadIndexCollection`. | Plugin composes; controller calls. Only stable codes `lead_index_storage_invalid`, `lead_index_capacity_exceeded`, `lead_index_record_invalid`, `unexpected_storage_failure`. |
| `final Admin\LeadsIndexController`; `classes/Admin/LeadsIndexController.php` | `__construct(Grav $grav, Config $config)`; `index(ServerRequestInterface $request): ResponseInterface`. | API router creates/calls. It enforces authentication, config, and `api.goosialize_leads.read`, then creates query/repository. |

All names above use prefix `Grav\Plugin\GoosializeLeads\`. Direct tests may call public APIs only. `GoosializeLeadsPlugin` is the registrar: add `PermissionsRegisterEvent`, `onApiSidebarItems`, and `onApiPluginPageInfo`; register only GET `/goosialize-leads` to `LeadsIndexController::index`. Exact new handlers are `onRegisterPermissions(PermissionsRegisterEvent $event): void`, `onApiSidebarItems(Event $event): void`, and `onApiPluginPageInfo(Event $event): void`. They are inert when disabled or API/Admin2 events are absent.

`StorageException` keeps its existing constructor, `stableCode()` method, message behavior, and closed exact allowlist. Preserve every existing Phase 3B/3C code verbatim and add only these Phase 4A.1 codes:

| Exact code | Producer and condition | Public mapping and redaction | Exact oracle |
|---|---|---|---|
| `lead_index_storage_invalid` | `FilesystemLeadReadRepository::latest()`; invalid root/tree/entry containment, type, symlink, mode, or primary-record I/O state not classified below. | Controller returns 503 `lead_index_storage_invalid` / `Lead index storage is unavailable.`; no path, value, exception detail, or stack. | Construction succeeds, `stableCode()` and message equal the exact scalar, and the controller emits the exact redacted response. |
| `lead_index_capacity_exceeded` | `FilesystemLeadReadRepository::latest()`; a 257th year/month directory entry or 10,001st exact primary-record candidate is encountered. | Controller returns 503 `lead_index_capacity_exceeded` / `Lead index capacity was exceeded.`; no partial collection or internal detail. | Both exact boundaries accept their maximum and reject the next candidate with this exact scalar and response. |
| `lead_index_record_invalid` | `FilesystemLeadReadRepository::latest()`; an exact primary-record candidate is oversized, unreadable, noncanonical, malformed, incomplete, or violates the Phase 3B record schema. | Controller returns 503 `lead_index_record_invalid` / `Lead index data is invalid.`; no record value, path, exception detail, or stack. | Each primary-record fault produces this exact scalar and exact redacted response. |

`unexpected_storage_failure` remains the existing catch-all for an otherwise unclassified throwable and maps to 500 `internal_error` / `Lead index could not be loaded.`. `tests/unit/phase-3b-secure-persistence.php` must assert the complete exact allowlist: all nineteen existing codes remain accepted, the three codes above are accepted, and an unknown, prefix-only, wildcard-like, or arbitrary code is rejected with `InvalidArgumentException`. Wildcard, prefix-based, and arbitrary-code acceptance are forbidden.

### Declarative resource-table contract

`admin/blueprints/goosialize-leads-index.yaml` contains one `form.fields.leads`: `type: resource-table`, `endpoint: /goosialize-leads`, `id_key: id`; clear and refresh true, export false; and no export endpoint, editor, action, link, detail, page, cursor, size, or sort key. Messages are exactly `No leads have been captured yet.`, `No leads match the active filters.`, and `Lead data could not be loaded.`.

| Filter | Exact declaration and client behavior |
|---|---|
| Search | `name: search`, `section: search`, `type: text`, label `Search`, placeholder `Search name, email, source or Lead ID`, fields `[id, name, email, source]`, `operator: contains`; lowercase containment; empty inactive. |
| Status | `name: status`, `type: select`, label `Status`, field `status`, `operator: equals`, `options_from_meta: allowed_statuses`; lowercase equality; options `new`, `contacted`, `qualified`, `closed`. |
| Source | `name: source`, `type: select`, label `Source`, field `source`, `operator: equals`, `options_from_field: source`; lowercase equality over bounded rows. |
| From | `name: date_from`, `type: datetime`, label `Created from`, field `created_at`, `operator: date-from`; inclusive leading `YYYY-MM-DD`; invalid/empty inactive. |
| To | `name: date_to`, `type: datetime`, label `Created to`, field `created_at`, `operator: date-to`; inclusive leading `YYYY-MM-DD`; invalid/empty inactive. |

Filters are exclusively client-side. Labels are native accessibility labels. Columns in order are `created_at` (`Created`, datetime, 18%), `name` (`Name`, 18%), `email` (`Email`, 24%), `source` (`Source`, 14%), `form_name` (`Form`, 12%), and `status` (`Status`, 14%). Native text/datetime rendering escapes values and renders null/empty as an em dash. No badge/custom renderer exists.

Page definition is id/plugin/blueprint `goosialize-leads`, title `Leads — latest 100`, icon `fa-address-book`, `page_type: blueprint`, actions `[]`. Sidebar is id/plugin `goosialize-leads`, label `Leads`, same icon, route `/plugin/goosialize-leads`, priority 20, badge null, authorize `api.goosialize_leads.read`. No page script exists.

### ACL, configuration, and responses

`permissions.yaml` registers only `api.goosialize_leads.read` through `PermissionsRegisterEvent` and `PermissionsReader::fromYaml()`. No role receives it by default. Navigation/page are omitted without it; the provider rejects unauthenticated with 401 `authentication_required` and unauthorized with 403 `forbidden`. Bodies contain only `ok:false`, `code`, `message`; logs contain stable codes only.

Configuration contains only `plugins.goosialize-leads.admin2_index.enabled` (Boolean, default false; only literal true enables) and `plugins.goosialize-leads.admin2_index.timezone` (string, default/only value `UTC`). Missing/invalid values disable Admin2 browsing only, never Forms or public API capture. The 100 limit is fixed.

Success/empty is 200 with exact top-level `data`, then `meta`; meta is `read_only`, `count`, `limit`, `truncated`, `total_scanned`, `allowed_statuses`. Truncated success is ordinary 200; resource-table ignores unused metadata while the title discloses the bound. Error mappings are exact: 401 `authentication_required` / `Authentication required.`, 403 `forbidden` / `Lead access is forbidden.`, 503 `admin2_index_unavailable` / `Lead index is unavailable.`, 503 `lead_index_record_invalid` / `Lead index data is invalid.`, 503 `lead_index_storage_invalid` / `Lead index storage is unavailable.`, 503 `lead_index_capacity_exceeded` / `Lead index capacity was exceeded.`, and 500 `internal_error` / `Lead index could not be loaded.`. Errors contain keys `ok`, `code`, `message` in that order. Every response has JSON UTF-8, `Cache-Control: no-store`, and `X-Content-Type-Options: nosniff`; no path, record value, sidecar, key, HMAC, exception, or stack leaks.

### Tests and acceptance

`php tests/unit/phase-4a1-bounded-admin2-lead-index.php` uses synthetic temporary roots; reflects all six APIs; covers empty/one/many, 100/101 and 10,000/10,001 boundaries, ordering/tie, projection, corrupt/incomplete/oversize/malformed/symlink/containment/mode faults, zero writes, responses, redaction, ACL/config, and the exact source-compatible no-sidecar-open oracle. Absent, malformed, and oversized synthetic sidecars must yield the identical collection without their contents being opened and without a sidecar-specific size or error marker. It prints `PASS_PHASE_4A1_BOUNDED_INDEX` and `PASS_PHASE_4A1_NO_SIDECAR_READ`.

`GRAV_TEST_IMAGE=lscr.io/linuxserver/grav:2.0.12 tests/integration/phase-4a1-bounded-admin2-lead-index.sh` uses `docker run --rm --network none` with source-compatible synthetic Admin2/API fixtures; proves absent/present load, disabled behavior, permission/navigation/page/provider ACL, exact blueprint/filter/column declarations, no JS/web component/Shadow DOM/CSS/pagination/sort/detail/action/export, native states, all regressions; prints `PASS_PHASE_4A1_NATIVE_RESOURCE_TABLE`, `PASS_PHASE_4A1_READ_ACL`, `PASS_PHASE_4A1_REGRESSIONS`, `PASS_PHASE_4A1_PACKAGE`.

The five modified integration scripts preserve every older marker while updating package/install count 45, reflection count 33, plugin loading, and entry points. The authorized Phase 3B unit-test modification preserves every previous assertion and adds only the complete exact `StorageException` allowlist regression. Acceptance requires PHP syntax, Composer/YAML/Markdown/manifest integrity, shell syntax, two byte-identical builds and SHA values, offline GPM install, no browser claim, clean resources, and exact 24-path commit. The final Phase 4A.1 SHA is implementation evidence, not a planning constant; Phase 3C.2 SHA remains baseline.

## Phase 4B native mutation checkpoint

Phase 4A.1 is complete and remains read-only. Phase 4A.2 remains blocked pending a source-proven native Lead-detail composition API. Phase 4B.1 status mutation and Phase 4B.2 reversible delete/restore are `BLOCKED_BY_ADMIN2_2_0_15`.

Admin2 2.0.15's native resource-table editor automatically posts or patches only the edited scalar payload (`{status: value}` for the proven declaration). It exposes neither revision/version transport nor an explicit submit/save lifecycle. The API router provides authentication but no source-proven general plugin nonce/CSRF lifecycle; its located `admin-nonce` verification is private to API-key generate/revoke tasks. Authentication alone is insufficient, and no mutation endpoint may be registered on that basis.

The blocker preserves the immutable primary record, introduces no mutable status metadata, and authorizes no runtime classes, configuration, tests, package changes or implementation branch. It must not be bypassed with custom JavaScript, web components, Shadow DOM, custom controls, legacy Admin pages or compiled Admin2 changes.

Phase 4B may be replanned only after the supported installed platform source-proves a single complete native workflow with: native edit form/select; explicit submit/save; Lead-ID and revision transport; authenticated and dedicated-ACL callback; general plugin nonce/CSRF creation, transport and verification; persistence unreachable after verification failure; deterministic stale/conflict handling; native success/error feedback; and no custom component or plugin JavaScript.
