# Architecture Principles

## Plugin ownership boundaries

All Goosialize Leads configuration, Admin2 pages, fields, API routes, templates, assets, and runtime logic must live within plugin-owned boundaries. The plugin must not modify Grav core, patch the API plugin, replace compiled Admin2 files, or write into unrelated projects. Installation, upgrade, and removal must remain isolated and reversible.

## Theme independence

Capture and delivery behavior must not depend on the Goosialize theme or any specific frontend theme. The plugin owns safe default rendering and documents supported template override points. Theme integration may enhance presentation but must not be required for functionality, validation, security, or accessibility.

## Native Admin2 requirement

Administrative interfaces must use real native Grav Admin2 components and official Admin2 extension points. Custom controls must not imitate native controls with look-alike HTML or CSS. Shadow DOM must not be used when it isolates components from or prevents native Admin2 behavior. Lifecycle and API assumptions must be verified against official Grav 2.0 documentation and the official Grav 2.0.12 source.

## Storage and security principles

- Store lead records outside distributable plugin code so upgrades cannot overwrite them.
- Use restrictive filesystem permissions and non-public storage locations.
- Validate and normalize input on trusted server boundaries.
- Use atomic writes, collision-resistant identifiers, and duplicate protection.
- Preserve record integrity across interrupted writes and concurrent submissions.
- Apply output escaping and formula-injection protection where relevant, including CSV export.
- Capture consent evidence appropriate to the configured form and purpose.
- Prevent protected-resource paths from becoming public bypasses.
- Keep secrets and real lead data out of source control, fixtures, logs, and diagnostic output.
- Define retention and deletion behavior explicitly before handling production data.

## Permission model

Permissions must be deny-by-default and capability-based. Viewing, exporting, updating, deleting, configuring, and delivering protected resources should be independently enforceable where their risk differs. Server-side authorization is mandatory for every Admin2 action and API route; interface visibility is not an authorization boundary. Sensitive operations should be auditable without recording unnecessary personal data.

## Upgrade safety

Lead data must survive plugin upgrades. Code, configuration, schema or format metadata, and mutable lead data must have clear boundaries. Data-format changes require versioned, restartable, and testable migrations with failure recovery. Removal must not delete lead data implicitly, and no lifecycle operation may modify unrelated files.

## Testing strategy

Testing is layered:

- Syntax and static checks for PHP, templates, configuration, and frontend source.
- Unit tests for validation, normalization, identifiers, duplicate detection, permissions, and storage primitives.
- Integration tests for Grav lifecycle hooks, routes, email boundaries, Admin2 extensions, and filesystem behavior.
- Runtime tests against Grav CMS 2.0.12 for native Admin2 workflows and theme independence.
- Security tests for authorization failures, request forgery defenses, injection, path traversal, unsafe uploads or delivery, CSV formula injection, concurrency, and interrupted writes.
- Clean-install, upgrade, rollback or failure-recovery, and removal scenarios using both automation and documented manual verification.

Tests must run before commits at a level proportionate to the change, with full release checks required before packaging.
# v1.0.0 release invariants

## Version and compatibility

The target version is `1.0.0`. Semantic versioning is normative: patch releases
may contain compatible fixes, minor releases may add compatible features, and
breaking public-contract or stored-data changes require a major release.
`blueprints.yaml:version`, `composer.json:version` and
`scripts/build-plugin-package.sh` must contain the identical canonical version.
The ZIP name, changelog heading and release-notes heading must be derived from
that value and must agree exactly. No prerelease suffix is allowed for this
release.

The release metadata must state Grav `>=2.0.12 <2.1.0`, Admin2 2.0.15 as the
only verified Admin2 version, and PHP `^8.3`; PHP 8.5.8 is an observed test
runtime, not a broadened compatibility promise. The package is
`goosialize-leads-1.0.0.zip`, its root is
`grav-plugin-goosialize-leads/`, its changelog heading is
`## 1.0.0 — YYYY-MM-DD`, and release dates use ISO 8601 calendar form
`YYYY-MM-DD`. The future release commit subject is exactly
`release: prepare Goosialize Leads 1.0.0`.

## Install, upgrade and removal

A fresh install must be exercised with `docker run --rm --network none` and
local `php bin/gpm direct-install -y <local ZIP>`. Acceptance requires exact
extraction and discovery; dependency, enablement, default configuration,
permission, Admin2 page, CLI command and scheduler registration checks; no
runtime data before first capture; and lazy creation of data directories only
when needed. Missing Grav Email, disabled scheduling or disabled delivery must
fail closed for that optional capability while capture remains available.

There is no earlier public release. The only supported upgrade evidence for
v1.0.0 is the validated Phase 5C.2 package baseline at commit
`db34a6008da54ed013ab02950830572e04239018`. Prior internal development
packages are not claimed as supported upgrade origins. Before upgrade,
operators must back up configuration and `user-data://goosialize-leads`.
Upgrade must preserve existing Leads, Phase 3B sidecars, pending events, sent
archives, delivery states, dead letters, configuration and ACL assignments.
It performs no destructive migration and rewrites none of those records.
Malformed historical material continues to fail closed under redacted error
codes.

Removal deletes plugin code only. All Lead and operational data is preserved by
default. Data removal requires a separate, explicit operator action after a
verified backup; the plugin must not automate or silently perform it. The
uninstall guide must warn about every retained record class before code removal
and provide a separate confirmation checklist for any later manual deletion.

## Credential and availability isolation

Functional, notification, scheduler, permission and optional-dependency
settings remain separate. Goosialize Leads stores no SMTP password, provider
API credential or SendPulse credential, has no hardcoded recipient, and copies
no credential into Leads or notification records. Sender and recipient routing
are configuration values, never documentation examples from a real system.
Invalid optional settings disable only the affected optional facility; capture
continues when notification or scheduling is unavailable.

## Release security and artifact gates

The final security review covers every packaged runtime file and proves path
containment, symlink rejection, pre/post `lstat` and `fstat` identity, path
replacement resistance, no-overwrite atomic publication, immutable records,
CAS, lock ownership, concurrency, rate limiting, Origin and nonce validation,
ACL separation, CSV formula protection, command argument bounds, scheduler
bounds, output redaction, credential isolation, and absence of Lead or
recipient leakage. Tests use synthetic roots, clocks, transports and scheduler
objects and perform no network or real transport.

Compatibility gates cover only Grav 2.0.12, Admin2 2.0.15, PHP `^8.3`
(observed 8.5.8), Email absent, Email present with fake transport, scheduling
disabled, scheduling enabled with a synthetic scheduler, and offline install.
Two independent builds must be byte-identical. The package must contain exactly
the manifest, no tests, fixtures, credentials, real data, temporary files or
Git metadata. Inventories of installed files, PHP runtime/command types,
commands, scheduler jobs, configuration and permissions must match the release
contract.

An annotated tag is mandatory. Only after automated validation, manual browser
acceptance, exact staging, the release commit, fast-forward merge, post-merge
rebuild/smoke tests, final ZIP generation and final SHA-256 recording may the
operator run `git tag -a v1.0.0 -m "Goosialize Leads 1.0.0"`. No tag or final
checksum is predeclared by this contract.

## Manual browser checklist lifecycle

The sole browser acceptance record is
`docs/MANUAL_BROWSER_ACCEPTANCE_CHECKLIST.md`, classified
`DEVELOPMENT_ONLY_RELEASE_GATE`. Before testing it exists with every visual
item unchecked or `PENDING`; candidate commit and package fields may remain
pending until available, and automated tests cannot mark an item `PASS`.

During testing an operator records the candidate version, commit, package
SHA-256, Grav and Admin2 versions, browser/version, operating system, date and
operator identifier. Each item receives an individual result and failures have
notes. Source and package content must not be changed merely to mark a test
passing. The record ends with overall `PASS` or `FAIL` and explicit release/tag
approval or rejection.

Only a `PASS` record containing the exact candidate commit and package SHA-256
permits final release review. Any later source correction invalidates the
previous acceptance and requires affected checks to be repeated. Tag creation
and publication remain prohibited while the result is `PENDING` or `FAIL`.

## Phase 8 cumulative release baseline

The authoritative source baseline for the v1.0.0 release candidate is main
commit `247a0ecf3b55b0d1c3cdf3dbfb0f590dd8a7435c`, after the public
plugin-to-plugin Lead capture capability was merged and post-merge verified.

The immutable pre-release package evidence for that baseline is:

- archive name `goosialize-leads-0.1.0-dev.zip`;
- SHA-256
  `e00c6bf0557f051baf5830c104132f35567f08bb6f47050560ac4730fc6fe883`;
- 88 package and installed files;
- 74 reflected runtime/command types;
- 11 unit-test files; and
- 13 integration-test files.

The release-readiness implementation adds nineteen packaged documentation
files and one development-only integration test. It adds no runtime or command
type and no unit test. Its exact final inventories are therefore:

- 107 package and installed files;
- 74 reflected runtime/command types;
- 11 unit-test files;
- 14 integration-test files; and
- 39 changed paths, composed of 21 new and 18 modified paths.

The additional modified path beyond the earlier contract is
`tests/integration/phase-8-public-capture-capability.sh`, whose package-count
oracle must follow the 107-file release inventory.

All existing version, compatibility, security, deterministic-build,
manual-browser, annotated-tag and publication invariants remain unchanged.
The manual-browser result remains `PENDING`.
