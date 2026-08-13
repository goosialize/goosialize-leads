#!/usr/bin/env bash
set -Eeuo pipefail
set +H

export LC_ALL=C
export GIT_PAGER=cat
export PAGER=cat
export NO_COLOR=1

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

fail() {
    printf 'FAIL: %s\n' "$*" >&2
    exit 1
}

pass() {
    printf 'PASS: %s\n' "$*"
}

EXPECTED_VERSION='1.0.2'
EXPECTED_MANUAL_ACCEPTANCE_STATUS='PASS'

echo "===== V1 RELEASE READINESS INTEGRATION TEST ====="

test -f blueprints.yaml || fail 'blueprints.yaml is missing'
test -f composer.json || fail 'composer.json is missing'
test -f packaging/package-files.txt || fail 'package manifest is missing'
test -x scripts/build-plugin-package.sh || fail 'package builder is missing or not executable'
test -f docs/MANUAL_BROWSER_ACCEPTANCE_CHECKLIST.md || fail 'manual browser acceptance checklist is missing'

grep -Fxq "version: ${EXPECTED_VERSION}" blueprints.yaml ||
    fail 'blueprints version is not 1.0.2'

grep -Fq "\"version\": \"${EXPECTED_VERSION}\"" composer.json ||
    fail 'composer version is not 1.0.2'

grep -Fq "readonly VERSION='${EXPECTED_VERSION}'" scripts/build-plugin-package.sh ||
    fail 'package builder version is not 1.0.2'

pass 'version metadata'

for path in \
    docs/INSTALLATION.md \
    docs/CONFIGURATION.md \
    docs/FORMS_INTEGRATION.md \
    docs/JSON_API_INTEGRATION.md \
    docs/ADMIN2_LEAD_INDEX.md \
    docs/CSV_EXPORT.md \
    docs/NOTIFICATION_DELIVERY.md \
    docs/SCHEDULER.md \
    docs/RETRY_DEAD_LETTER.md \
    docs/RECONCILIATION.md \
    docs/CLI_REFERENCE.md \
    docs/PERMISSIONS.md \
    docs/OPERATIONAL_STATUS.md \
    docs/UPGRADE.md \
    docs/UNINSTALL_DATA_RETENTION.md \
    docs/SECURITY.md \
    docs/TROUBLESHOOTING.md \
    docs/EXAMPLES.md \
    docs/RELEASE_NOTES_1.0.0.md \
    docs/RELEASE_NOTES_1.0.1.md \
    docs/RELEASE_NOTES_1.0.2.md \
    docs/PUBLIC_INTEGRATION_CONTRACT.md
do
    test -s "$path" || fail "packaged release document is missing or empty: $path"
done

pass 'packaged release documentation inventory'

grep -Fxq '# Manual browser acceptance checklist'     docs/MANUAL_BROWSER_ACCEPTANCE_CHECKLIST.md ||
    fail 'manual browser acceptance checklist heading is invalid'

grep -Fq 'Status: PASS' docs/MANUAL_BROWSER_ACCEPTANCE_CHECKLIST.md ||
    fail 'manual browser acceptance status is not PASS'

grep -Fq 'tag remains' docs/MANUAL_BROWSER_ACCEPTANCE_CHECKLIST.md ||
    fail 'manual acceptance tag gate is missing'

if grep -Fxq 'docs/MANUAL_BROWSER_ACCEPTANCE_CHECKLIST.md' packaging/package-files.txt; then
    fail 'manual browser acceptance checklist must remain development-only'
fi

if grep -Fxq 'tests/integration/v1-release-readiness.sh' packaging/package-files.txt; then
    fail 'release-readiness integration test must remain development-only'
fi

pass 'development-only release gates'

grep -Fq 'goosialize-leads.public-capture.v1' docs/RELEASE_NOTES_1.0.0.md ||
    fail 'public capture service identifier is missing from release notes'

grep -Fq 'goosialize-leads.capture' docs/RELEASE_NOTES_1.0.0.md ||
    fail 'public capture capability identifier is missing from release notes'

grep -Fq 'POST /api/v1/goosialize-leads/capture' docs/RELEASE_NOTES_1.0.0.md ||
    fail 'public JSON endpoint is missing from release notes'

grep -Fq 'api.goosialize_leads.read' docs/PERMISSIONS.md ||
    fail 'read permission is missing from permissions guide'

grep -Fq 'api.goosialize_leads.export' docs/PERMISSIONS.md ||
    fail 'export permission is missing from permissions guide'

grep -Fq 'api.goosialize_leads.operations' docs/PERMISSIONS.md ||
    fail 'operations permission is missing from permissions guide'

grep -Fq 'goosialize-leads-notification-delivery' docs/SCHEDULER.md ||
    fail 'scheduler job identifier is missing'

grep -Fq 'confirm-delivered' docs/RECONCILIATION.md ||
    fail 'confirm-delivered reconciliation action is missing'

grep -Fq 'retry-duplicate-risk' docs/RECONCILIATION.md ||
    fail 'retry-duplicate-risk reconciliation action is missing'

grep -Fq 'dead-letter' docs/RECONCILIATION.md ||
    fail 'dead-letter reconciliation action is missing'

pass 'release contract documentation'

test -z "$(sort packaging/package-files.txt | uniq -d)" ||
    fail 'package manifest contains duplicate paths'

while IFS= read -r path; do
    test -n "$path" || fail 'package manifest contains an empty path'
    test -f "$path" || fail "package manifest source is missing: $path"
done < packaging/package-files.txt

for path in \
        docs/INSTALLATION.md \
        docs/CONFIGURATION.md \
        docs/FORMS_INTEGRATION.md \
        docs/JSON_API_INTEGRATION.md \
        docs/ADMIN2_LEAD_INDEX.md \
        docs/CSV_EXPORT.md \
        docs/NOTIFICATION_DELIVERY.md \
        docs/SCHEDULER.md \
        docs/RETRY_DEAD_LETTER.md \
        docs/RECONCILIATION.md \
        docs/CLI_REFERENCE.md \
        docs/PERMISSIONS.md \
        docs/OPERATIONAL_STATUS.md \
        docs/UPGRADE.md \
        docs/UNINSTALL_DATA_RETENTION.md \
        docs/SECURITY.md \
        docs/TROUBLESHOOTING.md \
        docs/EXAMPLES.md \
        docs/RELEASE_NOTES_1.0.0.md \
        docs/RELEASE_NOTES_1.0.1.md \
        docs/RELEASE_NOTES_1.0.2.md \
        docs/PUBLIC_INTEGRATION_CONTRACT.md
do
    grep -Fxq "$path" packaging/package-files.txt ||
        fail "release document is missing from final package manifest: $path"
done

pass 'deterministic package manifest'

mapfile -t UNIT_TESTS < <(
    find tests/unit -maxdepth 1 -type f -name '*.php' -printf '%f\n' |
    LC_ALL=C sort
)

mapfile -t INTEGRATION_TESTS < <(
    find tests/integration -maxdepth 1 -type f -name '*.sh' -printf '%f\n' |
    LC_ALL=C sort
)

test "${#UNIT_TESTS[@]}" -gt 0 || fail 'unit test inventory is empty'
test "${#INTEGRATION_TESTS[@]}" -gt 0 || fail 'integration test inventory is empty'
pass 'non-empty test inventory'

if test -f vendor/autoload.php; then
    REFLECTED_TYPE_COUNT="$(
        find classes -type f -name '*.php' -print0 |
        xargs -0 grep -hE '^[[:space:]]*(final[[:space:]]+|abstract[[:space:]]+)?(class|interface|trait|enum)[[:space:]]+' |
        wc -l |
        tr -d ' '
    )"

    printf 'REFLECTED_TYPE_COUNT=%s\n' "$REFLECTED_TYPE_COUNT"
else
    printf 'REFLECTED_TYPE_COUNT_CHECK=DEFERRED_NO_VENDOR_AUTOLOAD\n'
fi

git diff --check || fail 'git diff --check failed'

test -z "$(git diff --cached --name-only)" ||
    fail 'staged paths are not allowed during readiness preparation'

test -z "$(git tag --list '1.0.2')" ||
    fail '1.0.2 tag already exists before release preparation completes'

MANUAL_ACCEPTANCE_STATUS="$(
    awk '
        /^Status: / {
            print $2
            exit
        }
    ' docs/MANUAL_BROWSER_ACCEPTANCE_CHECKLIST.md
)"

test "$MANUAL_ACCEPTANCE_STATUS" = "$EXPECTED_MANUAL_ACCEPTANCE_STATUS" ||
    fail "unexpected manual browser acceptance status: $MANUAL_ACCEPTANCE_STATUS"

printf 'PRODUCT_ACCEPTANCE=%s\n' "$MANUAL_ACCEPTANCE_STATUS"
printf 'PUBLICATION_STATUS=NOT_PERFORMED\n'
printf 'RELEASE_TAG_ALLOWED=NO\n'

echo "V1_RELEASE_READINESS_INTEGRATION_TEST=PASS"
