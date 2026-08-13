# Permissions

Goosialize Leads 1.0.0 uses explicit Grav ACL permissions for native Admin2 and operational access.

## Permission catalogue

Version 1.0.0 defines:

```text
api.goosialize_leads.read
api.goosialize_leads.write
api.goosialize_leads.delete
api.goosialize_leads.export
api.goosialize_leads.operations
```

## Read permission

```text
api.goosialize_leads.read
```

This permission allows access to the Admin2 Lead index and filters. The server
returns explicit authenticated capabilities so read-only users receive no Edit,
state, Delete or Restore controls.

It does not grant:

- CSV export;
- notification delivery;
- notification status inspection;
- notification reconciliation;
- configuration administration;
- Lead mutation, deletion or restoration.

## Write permission

`api.goosialize_leads.write` allows Edit, status changes, Active/Inactive and
Restore. It does not grant Delete. Restore uses the same server-authorized
mutation endpoint and is the only transition allowed from Deleted.

## Delete permission

`api.goosialize_leads.delete` allows reversible Delete and requires API access.
It does not independently grant index read or ordinary workflow mutation.

## Export permission

```text
api.goosialize_leads.export
```

CSV export requires both:

```text
api.goosialize_leads.read
api.goosialize_leads.export
```

Export permission alone does not grant access to the Lead index.

## Operations permission

```text
api.goosialize_leads.operations
```

This permission protects operational notification actions exposed through native Admin2 or another permission-aware integration surface.

Operational actions include:

- bounded notification-status inspection;
- notification delivery execution;
- retry-only delivery execution;
- explicit notification reconciliation.

CLI execution remains subject to host-level shell and deployment controls.

## Principle of least privilege

Recommended role separation:

- viewers receive `api.goosialize_leads.read`;
- editors receive read and write;
- deletion operators receive read, write where appropriate, and delete;
- export operators receive read and export;
- notification operators receive operations;
- administrators receive only the permissions required by their duties.

Do not grant all permissions solely because a user can access Grav Admin2.

## Failure behaviour

A missing permission must fail closed.

Permission failure must not expose:

- stored Lead values;
- CSV content;
- notification recipients;
- SMTP or provider credentials;
- filesystem paths;
- durable notification state;
- raw exceptions.

UI visibility is advisory. Every read, write, delete, export and operations
endpoint independently enforces its server-side ACL.
