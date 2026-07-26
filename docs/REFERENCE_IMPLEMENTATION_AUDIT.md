# Reference Implementation Audit

## Audit identity and safety

The standalone repository was audited from:

- Repository: `/home/goosialize/projects/local-docker/grav/goosialize-leads-dev`
- Base branch: `main`
- Base commit: `d23803a6912f032c2be047ac8ba4e6ac04c36a40`
- Audit branch: `docs/reference-implementation-audit`

The reference implementation was inspected read-only from:

- Repository: `/home/goosialize/projects/local-docker/grav/goosialize-v2-staging`
- Branch at audit time: `main`
- Commit: `585b4a76c6bacbbb2efcac4a6499a5b11eb247c5`

The reference worktree contained untracked runtime-data paths. Their contents were not opened, enumerated, or otherwise inspected. No real lead data, captured form values, credentials, API keys, contact data, sessions, logs, cache, backups, or other runtime user data was inspected.

## Audited paths

Tracked content was inspected only at these relevant paths:

- `www/user/plugins/goosialize-leads/`
- `www/user/plugins/api/classes/Api/Controllers/BlueprintController.php`
- `www/user/themes/goosialize/goosialize.php`
- `www/user/themes/goosialize/templates/digital-product.html.twig`
- `www/user/pages/11.resources/04.free/01.website-project-brief-checklist/digital-product.en.md`
- Tracked Admin2 compiled integration evidence under `www/user/plugins/admin2/`
- Relevant Git history for those paths

The audit did not copy implementation code into the standalone repository.

## Current end-to-end flow

1. A specific digital-product page declares acquisition, form, consent, notification, and delivery settings in page frontmatter.
2. The Goosialize theme listens during page processing, detects the digital-product template and lead-capture acquisition mode, and dynamically registers a form definition for the Forms plugin.
3. The theme template renders that form in a site-specific dialog and controls the capture/success presentation.
4. Theme-owned form-process handlers normalize the submitted name and email, interpret consent, harden the storage directory, acquire a per-source lock, detect duplicate normalized email addresses, and atomically publish a new lead file.
5. A local Post/Redirect/Get destination and short-lived session token identify a successful unlock request. Duplicate submissions unlock the resource but do not create another record or notification.
6. When enabled, the theme sends a best-effort notification through the Email plugin. Notification failure does not prevent resource access.
7. The protected-resource link is taken from page frontmatter and exposed by the template after successful token validation. The current link is still a site URL, not a plugin-owned delivery controller.
8. The Goosialize Leads plugin reads configured source directories from Grav user data, parses narrowly named lead files, derives stable identifiers, and joins separate mutable status metadata.
9. Plugin-owned API routes list leads, export CSV, update status, and delete leads after permission checks.
10. The plugin registers an Admin2 sidebar item and blueprint-backed page. A patched API blueprint serializer transports custom `resource-table` properties, while a custom compiled Admin2 build supplies the corresponding table behavior.

Several lifecycle, API, Admin2, session, and permission details in this flow are `OFFICIAL_VERIFICATION_REQUIRED`.

## Capability findings

### Lead capture registration

**Classification:** Reusable after refactoring.

The dynamic form definition is a useful reference, but registration is owned by the theme, restricted to one template, and driven by site frontmatter. Move registration into the standalone plugin and replace template identity checks with plugin-owned configuration and documented rendering hooks.

`OFFICIAL_VERIFICATION_REQUIRED`: Confirm the supported Grav CMS 2.0.12 event, priority, page-form mutation API, and Forms plugin integration contract.

### Form processing

**Classification:** Reusable after refactoring.

The existing handler separates custom process actions and validates the expected form name. Its runtime ownership must move from the theme into the plugin, with explicit behavior for partial failure and action ordering.

`OFFICIAL_VERIFICATION_REQUIRED`: Confirm Grav CMS 2.0.12 form-process event ordering, mutable event fields, redirect behavior, and error propagation.

### Validation and sanitization

**Classification:** Reusable after refactoring.

The implementation requires name and email fields, uses email validation, constrains lengths, and converts stored values to single lines. Consent interpretation and field-level policy need a plugin-owned schema. Validation must cover encoding, required consent, unexpected fields, request size, and configurable forms.

### Duplicate prevention

**Classification:** Reusable after refactoring.

Duplicate detection occurs under an exclusive source lock and compares normalized email addresses without timing-sensitive plain equality. The approach is sound for a small filesystem store but performs a full scan and defines uniqueness only as email within one source. The commercial plugin needs an explicit uniqueness policy and scale limits.

### Atomic persistence

**Classification:** Reusable with minimal adaptation.

Temporary-file creation in the destination directory followed by rename is a strong same-filesystem atomic-publication pattern. Restrictive permissions and cleanup are present. Durability guarantees, disk-full behavior, and platform-specific rename semantics need tests.

### Filesystem permissions and path safety

**Classification:** Reusable after refactoring.

The code rejects symbolic links in sensitive locations, resolves real paths, constrains source identifiers, uses owner-only directory/file modes, and checks containment. The standalone plugin must own the data-root policy and handle hosts where `chmod` semantics differ.

`OFFICIAL_VERIFICATION_REQUIRED`: Confirm the supported Grav CMS 2.0.12 writable data location and locator contract for persistent plugin data.

### Consent capture

**Classification:** Must be redesigned.

The current checkbox and stored yes/no value establish a basic signal but do not preserve consent text/version, purpose, policy version, locale, or sufficient provenance. The standalone schema must record explicit consent evidence without collecting unnecessary data.

### Notification delivery

**Classification:** Reusable after refactoring.

The notification is best-effort, validates the configured recipient, checks Email plugin availability, and avoids blocking delivery. Message ownership, templates, retry policy, redaction, and plugin dependency declarations must move into the plugin. Site-specific recipient and copy must not migrate.

`OFFICIAL_VERIFICATION_REQUIRED`: Confirm the Grav CMS 2.0.12 Email plugin service and message-building contract.

### Success redirect and session token

**Classification:** Reusable after refactoring.

The local-only redirect validation, short-lived random token, route/form binding, constant-time comparison, expiry, and one-time state removal are good security references. The token lifecycle and session interaction require official verification and dedicated replay, expiry, multi-tab, and disabled-session tests.

`OFFICIAL_VERIFICATION_REQUIRED`: Confirm session startup, persistence, request timing, and redirect integration in Grav CMS 2.0.12.

### Resource delivery

**Classification:** Must be redesigned.

The current success state reveals a page-configured URL to a resource that may be directly addressable. A standalone plugin needs a plugin-owned authorization and delivery route, safe file resolution, response headers, expiry/replay policy, and explicit behavior for duplicate submissions.

### Lead parsing and repository access

**Classification:** Reusable after refactoring.

The reader restricts source identifiers and filenames, ignores links and unexpected files, sanitizes parsed values, derives stable IDs, and detects ID collisions during destructive resolution. It is coupled to a legacy text format and scans all files on each request. Introduce a versioned plugin-owned record format and migration/import boundary.

### Mutable metadata

**Classification:** Reusable after refactoring.

Separate mutable metadata preserves immutable capture files and uses atomic writes with owner-only permissions. The hard-coded directory and YAML format need versioning, concurrency tests, recovery rules, and migration ownership.

### Statuses

**Classification:** Reusable after refactoring.

The fixed status set and validated updates provide a clear MVP baseline. Status policy, labels, transitions, and metadata schema must be plugin-owned and tested without assuming the site-specific list will remain final.

### Search and filters

**Classification:** Reusable after refactoring.

The API export implements bounded text search, while the Admin2 blueprint describes search, status, source, and date filters. Much filtering currently appears client-side or dependent on the custom table component. Define a consistent server-side query contract, pagination, combined-filter semantics, and scale limits.

### CSV export

**Classification:** Reusable with minimal adaptation.

The exporter normalizes controls and whitespace, quotes cells, emits UTF-8, and prefixes formula-like values to mitigate spreadsheet injection. Add permission, audit, filter-equivalence, large-export, encoding, and privacy tests.

### Delete flow

**Classification:** Reusable after refactoring.

Deletion uses a dedicated permission, re-resolves under a source lock, rejects link/path changes, and deletes metadata after the capture record. Partial failure can leave orphaned metadata or a deleted lead with failed metadata cleanup. The standalone plugin needs an explicit recoverable deletion transaction or tombstone strategy and retention safeguards.

### Permission checks

**Classification:** Requires official Grav 2.0.12 lifecycle or API verification.

The implementation is deny-by-default and separates read, export, write, synchronization, and delete capabilities. It mixes direct access-path lookup, API super/access checks, and `authorize()` fallbacks. The canonical Grav CMS 2.0.12 authorization model and Admin2 exposure rules must be verified.

`OFFICIAL_VERIFICATION_REQUIRED`

### API routes

**Classification:** Requires official Grav 2.0.12 lifecycle or API verification.

Route registration and request-user attributes depend on API plugin events and conventions. The plugin must use supported plugin-owned routes without patching the API plugin.

`OFFICIAL_VERIFICATION_REQUIRED`

### Admin2 page and resource table

**Classification:** Must be redesigned.

The current plugin registers sidebar and page definitions, but the resource table depends on custom blueprint transport and custom compiled Admin2 artifacts. An earlier plugin-local custom element also uses Shadow DOM and hand-built controls that imitate Admin2; it is incompatible with the foundation rules. The standalone implementation must use real native components and official extension points only.

`OFFICIAL_VERIFICATION_REQUIRED`: Identify and prove the supported Grav CMS 2.0.12/Admin2 extension contract for plugin pages, native tables, filters, editing, confirmation, and export.

### Blueprint transport

**Classification:** Must be redesigned.

The API plugin controller was patched to serialize custom resource-table properties. A commercial standalone plugin cannot require or ship this patch. Replace it with an official extension mechanism or plugin-owned supported endpoint/definition transport.

`OFFICIAL_VERIFICATION_REQUIRED`

### Theme and page coupling

**Classification:** Site-specific and must not enter the standalone plugin.

Template names, frontmatter hierarchy, product wording, brand presentation, form name, source slug, notification destination, resource URL, and page route belong to the current site. Preserve the reference site untouched and design generic plugin configuration plus documented theme override points.

### Upgrade and data-migration risks

**Classification:** Must be redesigned.

Capture data and metadata live under Grav user data, which is directionally upgrade-safe, but formats are unversioned and split across source directories. There is no documented migration journal, schema version, rollback/retry behavior, orphan reconciliation, retention policy, or uninstall policy. The standalone plugin needs durable data ownership independent of its code directory and explicit upgrade/removal contracts.

## Security strengths

- Exclusive locking covers duplicate detection and capture publication.
- Capture and metadata writes use temporary files followed by rename.
- Sensitive directories and files are hardened to owner-only modes.
- Symbolic links and path escapes are rejected in core storage operations.
- Source names, lead identifiers, and filenames use restrictive patterns.
- Redirects are constrained to local paths.
- Success tokens are random, short-lived, bound to route and form, compared safely, and removed after use.
- API operations are deny-by-default and use distinct capabilities.
- CSV generation mitigates spreadsheet formula injection.
- API responses and exports use private/no-store behavior.
- Logged failures record exception classes rather than lead values.

## Technical debt and risk

- Capture, consent, redirect, notification, and storage logic is embedded in the Goosialize theme.
- Configuration is embedded in a specific page's frontmatter.
- Resource delivery is URL exposure rather than plugin-owned protected delivery.
- The management plugin reads a legacy, unversioned text format.
- Full filesystem scans are used for listing, lookup, duplicate detection, and sidebar counts.
- API blueprint transport requires a patch to another plugin.
- Native resource-table behavior requires a custom Admin2 build.
- The plugin-local fallback page uses Shadow DOM and custom controls instead of native Admin2 components.
- Search/filter responsibilities are split between API, blueprint, and client behavior.
- Delete is not transactional across capture and metadata files.
- Permission implementation uses compatibility fallbacks whose canonical behavior is unverified.
- Plugin metadata includes site-oriented positioning and a deferred third-party synchronization concept.
- No migration, retention, uninstall, or recovery protocol is documented.

## Assumptions requiring official Grav verification

Each item below is `OFFICIAL_VERIFICATION_REQUIRED`:

- Grav CMS 2.0.12 plugin lifecycle events and subscriber priorities used for page/form registration.
- Forms plugin dynamic registration and custom process-action contracts.
- Form processing redirect and exception behavior.
- Supported persistent plugin-data location and stream-locator behavior.
- Grav session startup and persistence during Post/Redirect/Get.
- Email plugin service lookup, message construction, and send semantics.
- API plugin route-registration event and route ownership contract.
- API-authenticated user request attribute and canonical permission checks.
- Permission registration, hierarchy, superuser behavior, and Admin2 visibility.
- Admin2 sidebar and plugin-page registration events.
- Official Admin2 component and extension support for resource tables, filters, inline status editing, destructive confirmation, and CSV export.
- Blueprint transport customization without patching the API plugin.
- Plugin installation, upgrade, migration, disable, uninstall, and persistent-data preservation behavior.

No assumption above is presented as verified behavior.
