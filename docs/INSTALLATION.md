# Installation

## Supported releases

Download the latest stable package from
[GitHub Releases](https://github.com/goosialize/goosialize-leads/releases).
The release ZIP naming contract is:

```text
goosialize-leads-<version>.zip
```

Verified compatibility:

- Grav CMS `>=2.0.12`;
- required Grav plugins: API, Admin2 and Email;
- PHP `^8.3`;
- observed verification runtime PHP `8.5.8`.

## Package installation

The archive root is:

```text
grav-plugin-goosialize-leads/
```

Install through Grav GPM using a local archive:

```bash
php bin/gpm direct-install -y /absolute/path/goosialize-leads-<version>.zip
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

## Required dependencies

The GPM metadata declares API, Admin2 and Email as required dependencies. GPM
can therefore discover them before the plugin is loaded. Notification delivery
still requires:

- Grav Email installed and enabled;
- valid recipients;
- a valid sender address;
- delivery explicitly enabled.

On a clean installation, Forms capture works without an idempotency key. Public
JSON and public capability capture require a per-installation idempotency key;
see `docs/CONFIGURATION.md`. No key is generated into or shipped with the
package.

## Verification

After installation, run:

```bash
php bin/plugin goosialize-leads notification-status
```

Then verify plugin discovery and native configuration rendering in Admin2.

Manual browser acceptance remains a mandatory release gate and is recorded in
the development-only manual browser checklist.
