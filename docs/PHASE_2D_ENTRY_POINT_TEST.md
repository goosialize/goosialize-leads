# Phase 2D Native Entry-Point Test

## Purpose and boundaries

Phase 2D establishes three plugin-owned native contracts while keeping the product inert. It adds no Lead capture, storage, metadata persistence, controller, functional API route, permission, Admin2 management UI, sidebar item, Forms or Email integration, session or delivery behavior, user-data access, theme dependency, core patch, API patch, or compiled Admin2 replacement.

Functional route/controller design, functional Admin2 page architecture, licensing, and marketplace publication remain `OFFICIAL_VERIFICATION_REQUIRED`.

## Entry-point contracts

`onApiRegisterRoutes` receives `RocketTheme\Toolbox\Event\Event` and intentionally registers zero routes. It creates no HTTP surface because authentication, permissions, controllers, persistence, and request contracts belong to later phases.

`onTwigTemplatePaths` appends the plugin-owned `templates/` directory. `phase-2d-skeleton.html.twig` renders exactly `Goosialize Leads Phase 2 skeleton.` with no variables, inheritance, controls, scripts, data, or theme dependency. Automated testing performs no public HTTP rendering.

Admin2 discovers `admin-next/pages/goosialize-leads.js` through component-mode filesystem discovery. The no-build script reads only `window.__GRAV_PAGE_TAG`, validates it, guards duplicate registration, extends `HTMLElement`, and mounts exact inert text in light DOM. It uses no Shadow DOM, custom imitation controls, CSS, PHP controller, API request, network, storage, cookie, navigation, or external mutation.

Route and Twig entry points are runtime subscriptions and are inactive when plugin configuration is disabled. Admin2 component-page discovery is installed-package filesystem behavior: the exact Grav 2.0.12 `GpmController` can still discover component metadata and serve the static asset while the plugin is disabled. Asset discovery does not prove runtime activation and is not a security or authorization boundary. The inert component makes no data request or state mutation. Future functional Admin2 data access must use authenticated API routes with server-side permissions; no Grav or API plugin patch is introduced.

## Automated verification

Run:

```bash
GRAV_TEST_IMAGE='lscr.io/linuxserver/grav:2.0.12' \
    tests/integration/phase-2d-entry-points.sh
```

The test enforces the immutable local image ID before starting a container, uses `docker run --rm --network none`, exposes no ports, mounts only this repository read-only, and uses disposable runtime storage. It validates PHP signatures and the exact subscription allowlist, zero route mutation, Twig resolution and rendering, disabled inactivity, official Admin2 filesystem-discovery source, JavaScript syntax and a deterministic synthetic DOM/VM contract, the exact nine-file manifest, deterministic package build, genuine offline GPM installation, installed hashes, and Phase 2B/2C regressions. Repository paths, modes, hashes, Git status, branch, and HEAD must remain unchanged.

The validated package SHA-256 is `87f4ffda0bb8ceaac09d79d696a7364dae8cb5fe7323b4f3fe16bfa6f823ae6b`.

Required markers are:

```text
PASS_LOCAL_IMAGE
PASS_ROUTE_PROVIDER_ENTRY_POINT
PASS_NO_FUNCTIONAL_ROUTE
PASS_TWIG_TEMPLATE_ENTRY_POINT
PASS_TEMPLATE_THEME_INDEPENDENCE
PASS_ADMIN2_COMPONENT_DISCOVERY
PASS_ADMIN2_COMPONENT_CONTRACT
PASS_DISABLED_RUNTIME_ENTRY_POINTS_INACTIVE
PASS_DISABLED_ADMIN2_FILESYSTEM_DISCOVERY_EXPECTED
PASS_UPDATED_PACKAGE_BUILD
PASS_UPDATED_PACKAGE_INSTALL
PASS_PHASE_2B_REGRESSION
PASS_PHASE_2C_REGRESSION
PASS_REPOSITORY_UNCHANGED
PASS_PHASE_2D_NATIVE_ENTRY_POINTS
```

ShellCheck is run only when already installed. If unavailable, Bash syntax and all integration suites remain mandatory and `SHELLCHECK=UNAVAILABLE` is reported.

## Browser status and manual scenario

When no genuine browser runtime is installed, the suite reports `ADMIN2_REAL_BROWSER_TEST=UNAVAILABLE`; the VM harness is not described as a real-browser pass.

The manual read-only Admin2 scenario uses a disposable clean Grav 2.0.12 installation with no real account or Lead data. Enable the plugin, open its discovered component page, and verify that the page script loads, the exact inert text appears, the Console has no error, and the component initiates no unexpected Network request. Inspect the element to confirm light DOM, no Shadow DOM, no form control, no Lead data, and no available mutation. Disable the plugin and verify that route and Twig runtime subscriptions are inactive while the installed Admin2 filesystem asset remains discoverable and byte-identically servable through `GpmController`.

## Limitations

This checkpoint proves inert entry-point discovery only. It does not approve a functional HTTP endpoint, controller/autoload strategy, permissions, production Admin2 architecture, browser compatibility, Lead behavior, licensing, distribution, marketplace publication, or production readiness.
