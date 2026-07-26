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
