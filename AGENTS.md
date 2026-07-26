# Development Instructions

These instructions apply to all work in this repository.

## Before changing anything

- Inspect the repository, relevant files, Git state, and available runtime before modifying.
- Stop and report if the requested scope, repository state, or runtime state is unexpected.
- Preserve compatibility with Grav CMS 2.0.12.
- Before relying on lifecycle, API, Admin2, or extension behavior, consult the official Grav 2.0 documentation and the official Grav 2.0.12 source.

## Development workflow

- Use small feature branches and clear checkpoints.
- Keep changes narrowly scoped and reviewable.
- Before every commit, run all available syntax, static-analysis, and runtime tests relevant to the changed scope. If any required test category is unavailable, cannot run, or is intentionally omitted, stop and report the exact reason. Do not commit until the exception has been explicitly reviewed and approved.
- Record automated coverage and manual test scenarios for behavior that cannot be fully automated.

## Architecture

- Never modify Grav core, patch the API plugin, or replace or alter compiled Admin2 files.
- Use real native Grav Admin2 components and official Admin2 extension mechanisms.
- Do not imitate Admin2 controls with custom HTML or CSS.
- Do not use Shadow DOM where it isolates or prevents native Admin2 behavior.
- Keep all configuration, Admin2 pages, fields, API routes, templates, and runtime logic owned by this plugin.
- Keep the plugin independent of the Goosialize theme and every other specific frontend theme.
- Preserve clean installation, upgrade, and removal without modifying unrelated files.
- Ensure lead data survives plugin upgrades.
- Design security, permissions, and data integrity before convenience features.

## Safety and approval

- Never push, merge, publish, deploy, or delete user data without explicit approval.
- Never expose credentials, secrets, or real lead data in source, fixtures, logs, screenshots, or test output.
- Stop and report rather than guessing when an action could violate these instructions.
