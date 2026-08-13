# Installation

## Supported release

This guide applies to Goosialize Leads 1.0.0, released on 2026-07-31.

Verified compatibility:

- Grav CMS `>=2.0.12 <2.1.0`;
- Admin2 `2.0.15`;
- PHP `^8.3`;
- observed verification runtime PHP `8.5.8`.

## Package installation

The distributable archive is:

```text
goosialize-leads-1.0.0.zip
```

Its archive root is:

```text
grav-plugin-goosialize-leads/
```

Install through Grav GPM using a local archive:

```bash
php bin/gpm direct-install -y /absolute/path/goosialize-leads-1.0.0.zip
```

The resolved plugin destination is:

```text
user/plugins/goosialize-leads
```

## Initial state

The plugin is enabled by default, but optional facilities remain disabled until
configured:

- Grav Forms capture;
- public JSON capture;
- Admin2 Lead index;
- Admin2 CSV export;
- notification outbox;
- manual notification delivery;
- durable retries;
- scheduled delivery.

Runtime data is created lazily after an enabled operation requires it.

## Optional dependencies

Notification delivery requires:

- Grav Email installed and enabled;
- valid recipients;
- a valid sender address;
- delivery explicitly enabled.

A missing optional dependency disables only the affected optional facility.
Lead capture remains available when notification delivery or scheduling is
unavailable.

## Verification

After installation, run:

```bash
php bin/plugin goosialize-leads notification-status
```

Then verify plugin discovery and native configuration rendering in Admin2.

Manual browser acceptance remains a mandatory release gate and is recorded in
the development-only manual browser checklist.
