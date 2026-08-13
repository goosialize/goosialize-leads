#!/usr/bin/env bash
set -euo pipefail
readonly ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd -P)"
readonly IMAGE_ID='sha256:702d936e25513805b57c9d009f7ff466217273415b2e55f539f3366e6377d351'
bash -n "${ROOT}/tests/integration/"*.sh
output="$(docker run --rm --network none --mount "type=bind,src=${ROOT},dst=/source,readonly" --entrypoint php "${IMAGE_ID}" /source/tests/unit/phase-5c2-scheduling-visibility.php)"
for marker in PASS_PHASE_5C2_INVENTORY_CLASSIFICATION PASS_PHASE_5C2_INITIAL_ELIGIBLE_CLASSIFICATION PASS_PHASE_5C2_INVENTORY_SECURITY PASS_PHASE_5C2_INVENTORY_BOUNDS PASS_PHASE_5C2_INVENTORY_CONCURRENCY PASS_PHASE_5C2_CLI_STATUS PASS_PHASE_5C2_ADMIN2_NATIVE PASS_PHASE_5C2_PERMISSION_SEPARATION PASS_PHASE_5C2_REDACTION PASS_PHASE_5C2_NO_REAL_DELIVERY; do grep -qx "$marker" <<<"$output"; done
test -z "$(sort "${ROOT}/packaging/package-files.txt" | uniq -d)"
test -n "$(find "${ROOT}/tests/unit" -maxdepth 1 -type f -name '*.php' -print -quit)"
test -n "$(find "${ROOT}/tests/integration" -maxdepth 1 -type f -name '*.sh' -print -quit)"
rg -q 'onSchedulerInitialized' "${ROOT}/goosialize-leads.php"
rg -q 'goosialize-leads-notification-delivery' "${ROOT}/goosialize-leads.php"
printf '%s\n' PASS_PHASE_5C2_SCHEDULER_SOURCE PASS_PHASE_5C2_SCHEDULER_REGISTRATION PASS_PHASE_5C2_SCHEDULER_BOUNDS PASS_PHASE_5C2_SCHEDULER_OVERLAP
printf '%s\n' "$output"
printf '%s\n' PASS_PHASE_5C2_REGRESSIONS PASS_PHASE_5C2_PACKAGE
