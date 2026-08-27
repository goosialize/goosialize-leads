# Admin2 Lead index

> This is a technical behavior and storage reference. For normal administrator
> usage, see the [Admin Guide](ADMIN_GUIDE.md).

Goosialize Leads provides a native Admin2 Lead workspace over a bounded
latest-100 index.

## Enablement

```yaml
admin2_index:
  enabled: true
  timezone: UTC
```

## Permission

Access requires:

```text
api.goosialize_leads.read
```

## Capability-aware behaviour

The index:

- reads canonical primary Lead records;
- uses bounded collection and filtering;
- validates file containment and opened-file identity;
- does not treat idempotency sidecars as Lead records;
- exposes no mutation controls to read-only users;
- exposes Edit and Active/Inactive controls with `api.goosialize_leads.write`;
- exposes Delete with `api.goosialize_leads.delete`;
- exposes Restore for deleted Leads with `api.goosialize_leads.write`;
- uses native Admin2 page definitions;
- does not use Shadow DOM;
- does not use iframes;
- does not use legacy Admin v1 UI.

## Filters

The Admin2 blueprint supplies native Search, Status, Source, State,
Form / Resource, Created from and Created to fields. The workspace listens for
the native field events and consumes their values, but does not render or
persist duplicate controls. Native Admin2 dirty-state behavior is retained.

Source and Form / Resource options are derived from every distinct non-empty
value in the bounded collection currently loaded by Admin2. The blueprint
retains ownership of both native selects; the workspace synchronizes only
their option children. A single scoped filter lifecycle restores the dynamic
options if Admin2 hydrates or replaces those native controls later.

Created from and Created to use native Admin2 datetime fields. Both comparisons
are inclusive at the selected datetime. Invalid or unsupported values fail
closed.

The desktop layout is Search, Status | Source | State | Form / Resource, then
Created from | Created to. Columns stack naturally on smaller viewports.

The workspace Sort control orders the filtered loaded collection by creation
time or Lead name before pagination. View supports 10, 20, 30 or All. “All”
means all filtered rows currently loaded from the bounded latest-100 index; it
does not mean all historical Leads in storage. True all-history browsing needs
a later server-side pagination/index milestone.

Changing View or navigating client-side pages never widens the backend
latest-100 read boundary.

## Storage safety

The index reads from:

```text
user/data/goosialize-leads/v1/records
```

Records are validated before projection. Malformed, unbounded or unsafe
filesystem content fails closed.

## Mutation and storage model

Primary records under `records/YYYY/MM` are immutable. Admin2 writes only
validated metadata sidecars containing workflow status, state, revision and
update timestamp. Every change supplies the expected revision; stale changes
fail with a conflict.

Delete is reversible and retains the primary record. A deleted Lead cannot be
edited, re-deleted, set inactive or have its status changed. Restore is the
single allowed deleted-state transition and preserves status.

The visible index remains latest-100. Edit and mutation resolve a strictly
validated immutable ID through a separate bounded exact-ID lookup, so a valid
Lead remains addressable after it falls outside that index.

Edit is an inline state of the same plugin-owned workspace. It loads and saves
through the exact-ID endpoints without navigation, hash state, a document
reload or filesystem editor-selection context. Back prompts through the public
Admin2 confirmation dialog only when Status or State differs from the editor's
initial snapshot.
