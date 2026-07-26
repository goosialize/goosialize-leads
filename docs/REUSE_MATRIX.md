# Reuse Matrix

Classification values are the required Phase 1 categories. “Official verification” refers specifically to Grav CMS 2.0.12 and locally relevant Admin2 behavior.

| Capability | Current location | Classification | Security sensitivity | Proposed standalone destination | Required tests | Official verification required |
|---|---|---|---|---|---|---:|
| Lead capture registration | Goosialize theme `onPageProcessed` | Reusable after refactoring | High | Plugin capture/form registrar | Lifecycle ordering, multiple forms, theme independence, disabled plugin | Yes |
| Form processing | Goosialize theme `onFormProcessed` | Reusable after refactoring | High | Plugin submission service and event adapter | Action ordering, invalid events, failures, redirect behavior | Yes |
| Validation and sanitization | Theme capture helpers and Forms field definitions | Reusable after refactoring | High | Plugin validation and normalization layer | Encoding, lengths, required fields, malformed email, unexpected fields | No |
| Duplicate prevention | Theme filesystem capture routine | Reusable after refactoring | High | Plugin storage repository | Same/different case email, concurrency, source policy, large sets | No |
| Atomic persistence | Theme capture routine and metadata repository | Reusable with minimal adaptation | Critical | Plugin filesystem storage adapter | Concurrent writes, disk full, interrupted write, rename failure, cleanup | No |
| Filesystem permissions and path safety | Theme storage hardening; lead and metadata repositories | Reusable after refactoring | Critical | Plugin data-root and safe-path services | Symlinks, traversal, mode failure, non-POSIX host, containment | Yes |
| Consent capture | Theme form definition and legacy text record | Must be redesigned | Critical | Versioned consent-evidence model | Required/optional policy, text version, locale, provenance, minimization | No |
| Notification delivery | Theme Email service integration | Reusable after refactoring | High | Plugin notification adapter and templates | Disabled/misconfigured Email plugin, redaction, send failure, duplicate submission | Yes |
| Success redirect and session token | Theme redirect/session helpers | Reusable after refactoring | Critical | Plugin unlock-token service | Replay, expiry, route/form binding, multi-tab, missing session, open redirect | Yes |
| Resource delivery | Theme template and page delivery URL | Must be redesigned | Critical | Plugin protected-resource controller | Authorization, traversal, direct bypass, headers, expiry, range/error behavior | Yes |
| Lead parsing and repository access | `LeadRepository.php` | Reusable after refactoring | Critical | Versioned record repository plus legacy importer | Malformed files, collisions, symlinks, scale, corrupted records | No |
| Mutable metadata | `LeadMetadataRepository.php` | Reusable after refactoring | High | Plugin metadata repository | Atomicity, concurrent updates, invalid YAML, orphan recovery | No |
| Statuses | Metadata repository and Admin2 blueprint | Reusable after refactoring | Medium | Plugin status policy/service | Allowed values, transitions, concurrency, authorization | No |
| Search and filters | CSV exporter, Admin2 blueprint, custom client table | Reusable after refactoring | High | Plugin query service and native Admin2 page | Combined filters, dates, pagination, Unicode, authorization, scale | Yes |
| CSV export | `LeadCsvExporter.php` and API controller | Reusable with minimal adaptation | Critical | Plugin export service and route | Formula injection, quoting, encoding, filters, permissions, large exports | No |
| Delete flow | Lead repository, metadata repository, API controller, Admin2 action | Reusable after refactoring | Critical | Plugin retention/deletion service | Permission denial, confirmation, concurrent delete, partial failure, recovery | Yes |
| Permission checks | Permissions YAML, plugin UI gates, API controller | Requires official Grav 2.0.12 lifecycle or API verification | Critical | Plugin capability policy and route middleware | Every capability, superuser, missing user, UI/API parity | Yes |
| API routes | Plugin route subscriber and API controller | Requires official Grav 2.0.12 lifecycle or API verification | Critical | Official plugin-owned routes/controllers | Registration, authentication, CSRF where applicable, methods, errors | Yes |
| Admin2 page and resource table | Plugin blueprint, sidebar/page events, plugin-local custom element, compiled Admin2 | Must be redesigned | Critical | Official plugin-owned Admin2 extension using native components | Native behavior, accessibility, permissions, filters, edits, deletion | Yes |
| Blueprint transport | Patched API `BlueprintController.php` | Must be redesigned | High | Official extension or plugin-owned supported definition endpoint | Schema preservation, authorization, unsupported properties, upgrades | Yes |
| Theme and page coupling | Goosialize theme, digital-product template, page frontmatter | Site-specific and must not enter the standalone plugin | High | Generic plugin configuration and documented override boundary | Multiple themes, no theme override, arbitrary pages, branding isolation | No |
| Upgrade and data-migration risks | Unversioned capture files and separate metadata directory | Must be redesigned | Critical | Versioned plugin data schema and migration coordinator | Upgrade, retry, rollback/failure recovery, orphan reconciliation, uninstall | Yes |

## Reuse constraints

- “Reusable” means reuse of a design or carefully adapted behavior, not immediate code copying.
- No site-specific configuration, branding, addresses, routes, resource paths, or captured values may migrate.
- No API plugin patch or compiled Admin2 replacement may become a dependency.
- Any row marked “Yes” remains blocked by `OFFICIAL_VERIFICATION_REQUIRED` until supported behavior is confirmed from official Grav CMS 2.0.12 documentation and source.
