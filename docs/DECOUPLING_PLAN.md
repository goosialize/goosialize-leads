# Decoupling Plan

## Guardrails

- Keep the existing Goosialize site implementation untouched throughout migration.
- Develop and test only in the standalone repository and isolated test instances.
- Do not copy real lead data, credentials, site-specific configuration, branding, contact details, or protected resources.
- Do not patch Grav core, the API plugin, or compiled Admin2 files.
- Treat every item marked `OFFICIAL_VERIFICATION_REQUIRED` as unresolved until verified.
- Preserve user lead data through every supported plugin upgrade.

## Ordered migration

### 1. Verify official extension contracts

Before implementing runtime code, verify against official Grav CMS 2.0.12 documentation and source:

- Plugin, page, form, session, permission, and API lifecycle behavior.
- Forms and Email plugin integration contracts.
- Persistent plugin-data location and locator behavior.
- Admin2 sidebar, page, blueprint, native component, and resource-management extension points.
- Installation, upgrade, migration, disable, removal, and data-preservation behavior.

Record every result and replace each relevant `OFFICIAL_VERIFICATION_REQUIRED` marker only with cited evidence.

### 2. Define plugin-owned boundaries

Specify:

- A versioned configuration schema.
- A versioned capture-record and mutable-metadata schema.
- A persistent data root outside distributable plugin code.
- Capability-based permissions for view, export, update, delete, configure, and protected delivery.
- Retention, deletion, audit, migration, and uninstall policies.
- Generic form and resource definitions without site-specific defaults.

### 3. Build an isolated standalone skeleton

Create the minimum plugin metadata, configuration, dependency declarations, services, and verified lifecycle subscribers. Confirm clean installation, enable/disable behavior, and removal without touching unrelated files. Do not connect it to the Goosialize site.

### 4. Move capture ownership into the plugin

Implement plugin-owned form registration or a verified plugin-owned submission endpoint. Port only validated design concepts:

- Server-side validation and single-line normalization.
- Explicit consent evidence.
- Safe source/form identifiers.
- Exclusive locking and duplicate policy.
- Atomic persistence and restrictive permissions.

Add theme-independent default rendering and documented template overrides. Do not require the `digital-product` template.

### 5. Establish a versioned storage layer

Implement repositories behind interfaces so runtime code does not depend on legacy filenames. Add:

- Schema/version metadata.
- Atomic record and metadata writes.
- Collision and corruption handling.
- Bounded queries or indexes suitable for expected scale.
- Recovery and reconciliation.
- Restartable, idempotent migrations.

Lead data must remain outside plugin code and survive upgrades.

### 6. Add a legacy import boundary

Design an explicit, opt-in importer for the existing text-file layout without changing the reference site. The importer must:

- Operate on a copy in an isolated test environment first.
- Validate source paths and reject links or escapes.
- Preserve originals.
- Record progress and permit safe restart.
- Detect duplicates deterministically.
- Report counts and errors without printing personal data.

Import tooling must not contain the site's form names, paths, or configuration as product defaults.

### 7. Move notifications into the plugin

Add a plugin-owned notification adapter using the verified Email plugin contract. Use configurable templates, validated recipients, privacy-safe errors, and best-effort or queued behavior documented explicitly. Never ship the site's recipient or message copy.

### 8. Replace URL exposure with protected delivery

Implement a plugin-owned resource-delivery controller:

- Resolve only configured files within an approved resource root.
- Require a valid, short-lived authorization token or session grant.
- Prevent direct-path bypass and traversal.
- Apply safe response and caching headers.
- Define replay, expiry, duplicate, and failure behavior.

The resource may have a theme override for presentation, but authorization and delivery remain plugin-owned.

### 9. Implement official plugin-owned API routes

Using only verified extension points, add routes for listing, filtering, exporting, updating, and deleting. Enforce the canonical permission model server-side for every operation. Do not modify `BlueprintController.php` or any other API plugin file.

### 10. Build the native Admin2 management page

Use real native Admin2 components and official plugin extension points for:

- Leads navigation and page registration.
- Resource table or officially supported equivalent.
- Search and combined filters.
- Status editing.
- CSV export.
- Permission-aware destructive confirmation and deletion.

Do not use the reference Shadow DOM custom element, imitate Admin2 controls, or replace compiled Admin2 assets. If the required native extension is unavailable, stop and document the limitation rather than patching Admin2.

### 11. Add upgrade and removal safety

Implement versioned, restartable migrations with preflight checks and recovery. Test:

- Clean installation.
- Upgrade from each supported data version.
- Interrupted migration and retry.
- Plugin disable/re-enable.
- Removal that leaves lead data intact by default.
- Explicit, separately approved data deletion.
- No changes to unrelated files.

### 12. Validate decoupling

Run the plugin against:

- Grav CMS 2.0.12.
- At least two unrelated frontend themes.
- A site with no Goosialize theme or digital-product page.
- Stock API and Admin2 plugins with no patches or replaced compiled assets.
- Empty, populated, corrupted, and migrated test datasets containing only synthetic data.

The phase is complete only when no runtime path depends on the reference theme, page, API patch, or custom Admin2 build.

## Reference-site coexistence

During development, the standalone plugin must not be enabled against the reference site's live capture directories. Any comparison uses tracked implementation files or synthetic copies only. The existing site remains authoritative and unchanged until a separate, explicitly approved migration project is planned.

## Clean installation and removal outcome

A clean installation adds only plugin-owned code, configuration, routes, Admin2 extensions, and persistent-data directories created through supported Grav mechanisms. An upgrade changes plugin-owned code and versioned data only. Removal removes plugin code and registrations but preserves lead data by default; deleting user data is a separate explicit action requiring approval.
