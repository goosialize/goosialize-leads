# Clean Grav Plugin Load Test

## Purpose

This test verifies that the current standalone skeleton loads in isolated Grav CMS 2.0.12. It does not test Lead functionality.

## Isolation model

Two disposable containers use `--rm`, `--network none`, an explicit shell entrypoint, and one read-only bind at `user/plugins/goosialize-leads`. Runtime writes stay in temporary container layers. Reference repository, configuration, user data, and mounts are never used.

## Prerequisites

Docker and Git must be available. The selected image must already exist locally, contain Grav 2.0.12 at `/app/www/public`, and provide PHP satisfying `^8.3`. The script never pulls.

ShellCheck is an optional additional static-analysis check. It was unavailable in the Phase 2B environment and was not installed because package installation was prohibited; Bash syntax validation and the complete integration test still passed.

## Command

```bash
GRAV_TEST_IMAGE='lscr.io/linuxserver/grav:2.0.12' \
    tests/integration/clean-grav-plugin-load.sh
```

`GRAV_TEST_IMAGE` is mandatory. Use an immutable digest for cross-machine repeatability.

## Tested contracts

The harness fails closed unless:

- the local image reports Grav exactly `2.0.12` and PHP satisfies `^8.3`;
- the plugin is the only mount below the Grav root and is read-only;
- PHP, Symfony YAML, and strict JSON parsing pass;
- metadata, the Grav dependency, and enabled default match;
- `goosialize-leads.php` resolves as `Grav\Plugin\GoosializeLeadsPlugin`;
- exactly the inert `onApiRegisterRoutes` and `onTwigTemplatePaths` subscriptions are declared, with no others;
- route registration adds no route or HTTP surface, Twig adds only the plugin template directory, and disabled configuration leaves both contributions inactive;
- no Goosialize theme is present or required;
- API and Admin2 remain unmounted image content, without reference patches or compiled replacements; and
- the deterministic repository digest covers each regular file outside `.git` using its repository-relative path, numeric permission mode, and content hash; stable ordering detects both content-only and permission-only changes without hashing timestamps, ownership, inode numbers, or other unstable metadata; and
- repository content, Git status, branch, and HEAD remain unchanged.

## Enabled test

The enabled container uses default `enabled: true`. CLI initialization discovers the class, and plugin initialization supplies merged configuration without Leads behavior.

## Disabled test

The disabled container writes `enabled: false` only inside its disposable runtime. Grav discovers the installed class, but `Plugins::init()` does not activate it or inject runtime configuration.

## Security boundaries

The test performs no network access, image pull, package installation, port publication, named-volume creation, or checkout write. It does not mount or inspect reference files, credentials, lead data, sessions, logs, caches, backups, or secrets. Traps remove named test containers on success, failure, or interruption.

## Expected PASS markers

```text
PASS_LOCAL_IMAGE
PASS_ENABLED_DISCOVERY_LOAD
PASS_DISABLED_INACTIVE
PASS_REPOSITORY_UNCHANGED
PASS_CLEAN_GRAV_PLUGIN_LOAD
```

## Troubleshooting

- A missing-image failure means `GRAV_TEST_IMAGE` is not local.
- A Grav or PHP failure means the image is not the required runtime.
- A mount failure means the isolation model differs.
- A discovery or parsing failure identifies the plugin contract to inspect.
- Cleanup targets containers named `goosialize-leads-enabled-*` and `goosialize-leads-disabled-*`.

## Limitations

This is a CLI bootstrap and lifecycle test, not an HTTP, installation, upgrade, removal, theme-rendering, API, permissions, or Admin2 UI test. Those remain subject to official verification and dedicated testing.

No functional Lead capability exists: there is no capture, storage, delivery, functional API route, or Admin2 Leads management behavior.
