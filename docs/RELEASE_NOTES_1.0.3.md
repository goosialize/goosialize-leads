# Goosialize Leads 1.0.3 release notes

Goosialize Leads 1.0.3 is a Grav GPM maintainer-remediation release. It includes
narrow runtime fixes for clean-install Forms capture and configured API routes.

## Maintenance changes

- Corrects README wording for the published 1.0.2 release and points to
  GitHub Releases as the authoritative publication record.
- Replaces patch-specific installation instructions with a durable release ZIP
  naming and installation contract.
- Aligns packaged public documentation for the existing Grav GPM submission.
- Declares API, Admin2 and Email dependencies and broadens Grav compatibility
  to `>=2.0.12`.
- Uses native password/redaction semantics for nested idempotency secrets.
- Allows default-keyless Grav Forms capture while keeping public keyed capture
  fail-closed.
- Derives the public endpoint from API `route` and `version_prefix` settings.

## Compatibility and upgrades

- Grav CMS compatibility is `>=2.0.12`.
- The MIT license is unchanged.
- There is no storage schema change.
- No migration is required from 1.0.2.

The immutable `1.0.0`, `1.0.1` and `1.0.2` tags and their published release
assets are not modified by this release. Grav GPM acceptance or submission
completion is not claimed.
