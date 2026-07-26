# Dependency Map

## Summary

The reference implementation is not standalone. Capture is owned by the Goosialize theme and a specific page, while management depends on the API plugin and a custom Admin2 build. The target plugin must own runtime behavior and use supported Grav CMS 2.0.12 extension mechanisms.

| Dependency | Why it exists | Acceptable in a standalone plugin? | Removal or replacement |
|---|---|---:|---|
| Goosialize theme | The theme registers forms, processes submissions, stores leads, creates success tokens, sends notifications, and exposes template variables. | No | Move all runtime behavior into the plugin. Leave only optional documented template overrides in themes. |
| `digital-product` template | The template renders the site-specific modal, form, success state, and resource link. Theme logic also checks this exact template name. | No | Provide plugin-owned default rendering and a documented override contract that works with any theme. |
| Specific page frontmatter | A page supplies acquisition mode, form identity, consent copy, notification settings, source identity, success copy, and delivery URL. | No | Define validated plugin-owned configuration and a generic per-form/resource schema. Do not copy the site's values or branding. |
| API plugin patch | `BlueprintController.php` was changed to transport custom `resource-table` blueprint properties to Admin2. | No | Use an official extension point or plugin-owned supported transport. Restore independence from API plugin internals. `OFFICIAL_VERIFICATION_REQUIRED` |
| Custom Admin2 build | Compiled Admin2 artifacts were replaced to provide native resource-table behavior used by the Leads blueprint. | No | Use official plugin-owned Admin2 extensions and native components without replacing compiled Admin2 files. `OFFICIAL_VERIFICATION_REQUIRED` |
| Forms plugin | The theme dynamically creates a Grav form and relies on Forms processing and rendering. | Potentially, if declared and officially supported | Declare a compatible dependency or implement a plugin-owned capture endpoint. Verify the supported Grav CMS 2.0.12 Forms contract. `OFFICIAL_VERIFICATION_REQUIRED` |
| Email plugin | New-lead notifications use the Email service. | Yes, as an optional or declared dependency | Keep delivery behind a plugin-owned notification adapter, validate configuration, document failure behavior, and verify the official service contract. `OFFICIAL_VERIFICATION_REQUIRED` |
| Filesystem layout | Capture files use per-form directories under Grav user data; metadata uses a separate hard-coded namespace. | The concept is acceptable; the current layout is not a final contract | Define one plugin-owned, versioned data root outside distributable code, with safe import/migration from legacy source directories. `OFFICIAL_VERIFICATION_REQUIRED` |
| Permission definitions | API permissions separate read, export, write, sync, and delete, while UI checks combine access paths and compatibility fallbacks. | Yes, after verification and cleanup | Keep least-privilege capabilities, remove deferred sync from MVP, enforce server-side checks, and use the canonical Grav CMS 2.0.12 model. `OFFICIAL_VERIFICATION_REQUIRED` |
| Grav session | A short-lived session record binds the success token to route and form across a redirect. | Yes, if officially supported | Encapsulate it in plugin-owned delivery authorization with expiry, one-time use, replay tests, and a documented no-session failure mode. `OFFICIAL_VERIFICATION_REQUIRED` |
| Grav lifecycle events | Theme and plugin behavior depends on page, form, API route, sidebar, page-definition, and permission events. | Yes, only through official contracts | Map each behavior to official Grav CMS 2.0.12 events and test actual priority/order before implementation. `OFFICIAL_VERIFICATION_REQUIRED` |

## Site-specific configuration boundary

The following reference values must not enter product defaults:

- Brand-specific copy and presentation.
- A particular form name or source slug.
- A specific page route or template name.
- A specific notification recipient.
- A specific downloadable resource path.
- Product-specific consent or success wording.
- Site-specific commerce and catalogue frontmatter.

Generic examples may be created later using fictional, non-operational values.

## Acceptable dependency policy

An external Grav plugin dependency is acceptable only when:

1. It is necessary to the declared MVP.
2. Its required version and failure behavior are documented.
3. The integration uses an official Grav CMS 2.0.12 contract.
4. Goosialize Leads does not patch or overwrite it.
5. Installation, upgrade, disable, and removal remain clean.
6. Lead data remains owned and preserved by Goosialize Leads.
