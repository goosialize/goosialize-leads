# Public documentation terminology contract

> **Repository-only maintainer reference.** This file defines wording for the
> public documentation set. It is intentionally not part of the installable
> plugin package.

Use these terms with the capitalization shown:

| Canonical term | Usage |
| --- | --- |
| Goosialize Leads | Product name. Do not shorten it in first mention. |
| Lead / Leads | Capitalize when referring to a captured Goosialize Leads record. |
| Admin2 | Grav administration product and plugin. |
| Source | Trusted capture channel shown in the workspace. |
| Form / Resource | Native Grav Form name or integration resource context. |
| Status | Administrator workflow classification. |
| State | Lifecycle value: Active, Inactive or Deleted. |
| Active / Inactive / Deleted | State values shown in Admin2. |
| Delete / Restore | Reversible lifecycle actions. Delete never means primary-record destruction. |
| CSV Export | Admin2 export action and feature. Use “CSV file/format/output” generically. |
| Grav Forms | Native form integration. Use “Grav Form” only for one specific form. |
| Public JSON API | Optional anonymous HTTP capture surface. |
| Idempotency | Replay/conflict protection. |
| Notification | Durable delivery event or feature. |
| Scheduler | Grav scheduling integration. |
| Retry | Bounded delivery retry. |
| Dead letter | Terminal notification state requiring operator review. |
| Reconciliation | Explicit, revision-safe operator mutation. |

Required UI wording is **Form / Resource**, never “Form/Resource”,
“Resource/Form” or “Form Resource”. Use **Delete** for the UI action and state
clearly that it writes reversible metadata while retaining the immutable
primary Lead record.

The documentation contract test enforces selected unambiguous terms. Review
the full table during documentation changes because not every contextual
distinction is suitable for a mechanical check.
