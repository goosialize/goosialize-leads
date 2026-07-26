# Installable Plugin Package Test

## Purpose

This checkpoint proves that the non-functional plugin skeleton can be built reproducibly as a local ZIP and installed into a clean Grav CMS 2.0.12 runtime. It does not establish marketplace readiness or Lead functionality.

## Verified Grav 2.0.12 contract

The supported offline command is:

```bash
php bin/gpm direct-install -y /local/path/goosialize-leads-0.1.0-dev.zip
```

`DirectInstallCommand` accepts a local file, copies and extracts it, asks `GPM` to identify its type and name, and calls `Installer::install()`. The archive begins with `grav-plugin-goosialize-leads/`. This explicit plugin marker is required because Grav 2.0.12 `GPM::getPackageType()` recognizes “plugin” in the extracted directory name but its fallback class regex does not match this skeleton's `final class` declaration. `GPM::getPackageName()` derives `goosialize-leads` from `goosialize-leads.yaml`, and `GPM::getInstallPath()` resolves the destination to `user/plugins/goosialize-leads`.

The exact verified source is `/app/www/public/system/src/Grav/Console/Gpm/DirectInstallCommand.php` lines 62–284, `/app/www/public/system/src/Grav/Common/GPM/Installer.php` lines 73–275, and `/app/www/public/system/src/Grav/Common/GPM/GPM.php` lines 761–870 in image `lscr.io/linuxserver/grav:2.0.12` with ID `sha256:702d936e25513805b57c9d009f7ff466217273415b2e55f539f3366e6377d351`.

## Build and test

Prerequisites are Bash, Python 3, Git, SHA-256 tooling, Docker, and the already-local approved image. The builder uses the Symfony YAML component bootstrapped from `/app/www/public/vendor/autoload.php` in that exact image to validate blueprint slug and version structurally, with the repository mounted read-only and networking disabled. No dependency installation or network access is used.

ShellCheck is an optional additional static-analysis tool. It was unavailable during Phase 2C and was not installed because package installation was prohibited. Bash syntax validation passed, and both the Phase 2B and Phase 2C integration tests passed.

```bash
scripts/build-plugin-package.sh /tmp/goosialize-leads-package
GRAV_TEST_IMAGE=lscr.io/linuxserver/grav:2.0.12 tests/integration/installable-plugin-package.sh
```

The output is `goosialize-leads-0.1.0-dev.zip`. No release artifact is committed.

## Package manifest and deterministic guarantees

`packaging/package-files.txt` is the exact allowlist: `CHANGELOG.md`, `README.md`, `blueprints.yaml`, `composer.json`, `goosialize-leads.php`, `goosialize-leads.yaml`, and `languages/en.yaml`.

The builder rejects missing files, directories, symlinks, duplicates, blank entries, absolute paths, traversal, unsorted entries, and metadata mismatches. It writes entries in lexical order, normalizes timestamps to `1980-01-01 00:00:00`, normalizes directories to mode `0755` and regular files to `0644`, uses fixed ZIP settings, and atomically renames a temporary output. Identical content therefore produces identical bytes. Content changes alter the hash; source permission-only changes are normalized.

Before creating any directory or file, the builder resolves the requested output directory, including existing symlinked parent components, and rejects both the repository root and every path inside it. It then creates the temporary ZIP as a secure, unpredictable, exclusive file with restrictive permissions; this does not randomize the final package filename and cannot follow a pre-existing temporary-file symlink. Only a completed package is atomically renamed to the final ZIP path. Temporary or incomplete package files are removed after validation failure, shell failure, Python exception, or interruption.

## Isolation, ZIP safety, and repository integrity

The integration test builds twice under a disposable `/tmp` directory, compares bytes and hashes, and validates the exact entry set, root, timestamps, modes, duplicates, absolute paths, traversal, and symlinks. The package directory is mounted read-only into a `docker run --rm --network none` container. No ports, named volumes, Docker socket, reference repository, theme, API patch, or Admin2 replacement are mounted.

Before invoking `php bin/gpm direct-install -y <local ZIP>`, the disposable runtime verifies that `user/plugins/goosialize-leads` does not already exist as a file, directory, or symlink. The test fails before installation if that exact destination already exists.

After GPM installation, the test verifies the exact installed regular-file path set: every packaged file must exist, no unexpected regular file may exist, and each installed file's SHA-256 must match its corresponding ZIP entry. Mapping removes the `grav-plugin-goosialize-leads/` archive prefix and compares the remaining path beneath `user/plugins/goosialize-leads`. It also explicitly rejects both `user/plugins/goosialize-leads/goosialize-leads` and `user/plugins/goosialize-leads/grav-plugin-goosialize-leads` as incorrect nested destinations.

It validates YAML with Grav's bundled Symfony YAML parser, strict JSON metadata, PHP syntax, exact installation destination and file shape, Grav bootstrap, plugin discovery, enablement, and the absence of event subscriptions or functional Lead capability. Repository-relative path, numeric mode, and content hash are compared before and after; Git status, branch, HEAD, and named-volume inventory must also remain unchanged. Cleanup traps remove the container and temporary directory on success or failure.

Expected markers are `PASS_LOCAL_IMAGE`, `PASS_PACKAGE_BUILD`, `PASS_DETERMINISTIC_PACKAGE`, `PASS_PACKAGE_CONTENTS`, `PASS_ZIP_SAFETY`, `PASS_LOCAL_PACKAGE_INSTALL`, `PASS_INSTALLED_PLUGIN_LOAD`, `PASS_REPOSITORY_UNCHANGED`, and `PASS_INSTALLABLE_PLUGIN_PACKAGE`.

## Troubleshooting and limitations

A missing image, wrong immutable image ID, unsafe manifest entry, metadata mismatch, unexpected ZIP metadata, installer error, bootstrap failure, or repository mutation fails closed with `FAIL`. The test never pulls an image.

ShellCheck's absence is a documented validation limitation; ShellCheck must not be treated as having passed. The available Bash syntax and runtime integration validation passed without installing additional tooling.

This proves only deterministic package creation and local clean installation on the approved runtime. The plugin remains non-functional and provides no Lead capture, storage, API, delivery, or Admin2 management capability. Licensing, marketplace publication, update, distribution, and production readiness remain `OFFICIAL_VERIFICATION_REQUIRED`.
