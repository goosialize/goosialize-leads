# Goosialize Leads v1.0 — Admin2 Product UX

## Primary navigation

When the plugin is enabled and the authenticated Admin2 user has
`api.goosialize_leads.read`, the sidebar exposes:

- Leads
- Leads Settings

Users with `api.goosialize_leads.operations` may additionally access
Notification Operations.

The Leads workspace is not gated by a separate UI enable toggle.

## Leads workspace

The Leads page is capability-aware. Read-only users receive a read-only list;
write/delete controls are exposed only when the server-authoritative
capabilities permit them.

The native Filters fieldset is collapsed by default. Search is full width.
The desktop select row is Status | Source | State | Form / Resource, followed
by Created from | Created to. Source and Form / Resource vocabularies come
from the bounded loaded dataset. The workspace updates only the native select
options and uses one scoped lifecycle to recover both vocabularies after
Admin2 hydration.

Columns:

- Created
- Name
- Email
- Phone
- Source
- Form / Resource
- Status

CSV export is exposed only when both the feature and
`api.goosialize_leads.export` permission are available.

Sort defaults to A/Z and View defaults to 100 using the accepted compact
Pages-style toolbar, with 12px spacing before the table. The table includes
Pages-style Edit, Active/Inactive, Delete and Restore actions according to
capabilities. Edit is inline and uses exact-ID server endpoints with optimistic
revisions.

The card footer reports the current-page loaded count and filtered
active/inactive/deleted totals. Pagination is outside the card and is omitted
when the filtered result has one page.

## Settings

Plugin configuration is grouped into native Admin2 tabs:

1. General
2. Forms
3. Downloads
4. Public API
5. Notifications
6. Automation
7. Advanced

### Forms

The Forms tab discovers actual Grav Forms at blueprint-resolution time.
Administrators select eligible forms rather than entering arbitrary
form-name strings.

### Downloads

Goosialize Leads does not serve arbitrary files.

The Downloads tab configures lead-capture integration for compatible
resource/download providers. Rules identify a resource ID, a capture
form and a lead source.

Compatible providers call the stable public capability:

- service: `goosialize-leads.public-capture.v1`
- capability: `goosialize-leads.capture`
- contract version: 1

This keeps Goosialize Leads theme-independent and avoids implementing
an insecure generic file-serving layer.

## Demo data

Demo Leads are never shipped in the plugin package.

Synthetic Leads may be generated only by development / acceptance
tooling against a disposable test installation.
