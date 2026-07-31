#!/usr/bin/env bash
set -euo pipefail
readonly IMAGE_ID='sha256:702d936e25513805b57c9d009f7ff466217273415b2e55f539f3366e6377d351'
readonly ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd -P)"
fail(){ printf 'FAIL: %s\n' "$*" >&2; exit 1; }
output="$(docker run --rm --network none --mount "type=bind,src=${ROOT},dst=/source,readonly" --entrypoint php "${IMAGE_ID}" /source/tests/unit/phase-5c1-delivery-state-retry.php)"
for marker in PASS_PHASE_5C1_PENDING_INTERFACE_METHODS PASS_PHASE_5C1_PENDING_IMPLEMENTATION_METHODS PASS_PHASE_5C1_PENDING_SIGNATURES PASS_PHASE_5C1_PENDING_INTERFACE_PARITY PASS_PHASE_5C1_REFLECTION_EXACT PASS_PHASE_5C1_WORKER_USES_PENDING_INTERFACE PASS_PHASE_5C1_NO_CONCRETE_PENDING_BYPASS PASS_PHASE_5C1_STATE_SCHEMA PASS_PHASE_5C1_STATE_ATOMIC PASS_PHASE_5C1_CLASSIFICATION PASS_PHASE_5C1_BACKOFF PASS_PHASE_5C1_ELIGIBILITY PASS_PHASE_5C1_PERMANENT_FAILURE PASS_PHASE_5C1_UNCERTAIN PASS_PHASE_5C1_DUPLICATE_RISK_RETRY PASS_PHASE_5C1_RECONCILIATION PASS_PHASE_5C1_DEAD_LETTER PASS_PHASE_5C1_CONCURRENCY PASS_PHASE_5C1_NO_SCHEDULER PASS_PHASE_5C1_REGRESSIONS PASS_PHASE_5C1_PACKAGE; do
  grep -qx "${marker}" <<<"${output}" || fail "missing ${marker}"
done
test "$(wc -l < "${ROOT}/packaging/package-files.txt")" -eq 88
test "$(find "${ROOT}/tests/unit" -maxdepth 1 -type f | wc -l)" -eq 11
test "$(find "${ROOT}/tests/integration" -maxdepth 1 -type f | wc -l)" -eq 13
! rg -n 'method_exists|onSchedulerInitialized|addFunction|addCommand' "${ROOT}/classes/Notification" "${ROOT}/cli"
printf '%s\n' "${output}"
# Phase 5C.2 preserves retry-state and reconciliation behavior.
