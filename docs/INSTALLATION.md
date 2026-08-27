# Installation

> **Audience:** Grav administrators installing Goosialize Leads or verifying a
> clean installation.

For a guided first Lead after installation, continue with the
[Quick Start](QUICK_START.md).

## Requirements

- Grav CMS `>=2.0.12`
- PHP `>=8.3` with `ext-intl`
- Grav API plugin
- Grav Admin2 plugin
- Grav Email plugin

The package metadata declares API, Admin2 and Email as dependencies. The native
Grav Form plugin is additionally required only when using Grav Forms capture.

## Install from GPM

When Goosialize Leads is available in your configured GPM catalogue, run this
from the Grav root:

```bash
php bin/gpm install goosialize-leads
```

GPM resolves the package and its declared dependencies. Review any dependency
or compatibility prompt before accepting it.

## Install a GitHub release ZIP

If the catalogue update is not yet available, download the latest stable
package from
[GitHub Releases](https://github.com/goosialize/goosialize-leads/releases).
Release archives follow this naming contract:

```text
goosialize-leads-<version>.zip
```

Install the complete archive rather than copying selected files:

```bash
php bin/gpm direct-install -y /absolute/path/goosialize-leads-<version>.zip
```

Both installation routes resolve the plugin to:

```text
user/plugins/goosialize-leads
```

Do not rename the folder or merge the archive into an older plugin tree.

## Initial state

The shipped configuration has:

- the plugin enabled;
- the Admin2 Lead index enabled;
- Forms capture disabled;
- public JSON capture disabled;
- Downloads integration disabled;
- CSV Export disabled;
- notification outbox and delivery disabled;
- retry processing and scheduling disabled.

The **Leads** sidebar item still requires a signed-in user with
`api.goosialize_leads.read`. Other controls require their corresponding
permissions. See [Permissions](PERMISSIONS.md).

Runtime data is created lazily under `user/data/goosialize-leads/v1` after an
enabled operation needs it. No Lead data or idempotency secret is shipped in
the package.

## Clean-install checks

1. Confirm `user/plugins/goosialize-leads` exists.
2. Confirm API, Admin2 and Email are installed and enabled.
3. Sign in to Admin2 and open **Plugins > Goosialize Leads**.
4. Confirm the native configuration tabs render.
5. Grant a test administrator `api.goosialize_leads.read` and confirm **Leads**
   appears in the sidebar.
6. If using Forms capture, install/enable Form and follow the
   [Quick Start](QUICK_START.md).

For operational command syntax, use the [CLI reference](CLI_REFERENCE.md).
For failures, see [Troubleshooting](TROUBLESHOOTING.md).

---

## Navigation

[← Back to README](../README.md) · [Previous: Quick Start](QUICK_START.md) ·
[Next: Admin Guide →](ADMIN_GUIDE.md)

Related documentation: [Configuration](CONFIGURATION.md) ·
[Upgrade](UPGRADE.md) · [Troubleshooting](TROUBLESHOOTING.md)
