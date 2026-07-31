#!/usr/bin/env bash
set -euo pipefail
# Phase 5C.1 preserves the disabled-retry Phase 5B delivery path.

readonly EXPECTED_IMAGE_ID='sha256:702d936e25513805b57c9d009f7ff466217273415b2e55f539f3366e6377d351'
readonly IMAGE="${GRAV_TEST_IMAGE:-lscr.io/linuxserver/grav:2.0.12}"
readonly ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd -P)"
fail() { printf 'FAIL: %s\n' "$*" >&2; exit 1; }

[[ "$(docker image inspect --format '{{.Id}}' "${IMAGE}")" == "${EXPECTED_IMAGE_ID}" ]] || fail 'image mismatch'
output="$(docker run --rm --network none \
    --mount "type=bind,src=${ROOT},dst=/source,readonly" \
    --entrypoint php "${EXPECTED_IMAGE_ID}" /source/tests/unit/phase-5b-notification-delivery.php)"
for marker in \
    PASS_PHASE_5B_COMMAND PASS_PHASE_5B_DISCOVERY PASS_PHASE_5B_LOCKING \
    PASS_PHASE_5B_LEAD_READ PASS_PHASE_5B_MESSAGE PASS_PHASE_5B_FAKE_TRANSPORT \
    PASS_PHASE_5B_ARCHIVE PASS_PHASE_5B_AT_LEAST_ONCE PASS_PHASE_5B_NO_REAL_DELIVERY; do
    grep -qx "${marker}" <<<"${output}" || fail "missing ${marker}"
done

command_output="$(docker run --rm --network none \
    --mount "type=bind,src=${ROOT},dst=/source,readonly" \
    --entrypoint sh "${EXPECTED_IMAGE_ID}" -c '
set -eu
cd /app/www/public
mkdir -p user/plugins/goosialize-leads
while IFS= read -r path; do
    mkdir -p "user/plugins/goosialize-leads/$(dirname "$path")"
    cp "/source/$path" "user/plugins/goosialize-leads/$path"
done < /source/packaging/package-files.txt
php bin/grav cache --all >/dev/null
php bin/plugin goosialize-leads deliver-notifications --help --no-ansi | grep -q "deliver-notifications"
php bin/plugin goosialize-leads deliver-notifications --help --no-ansi | grep -q -- "--limit"
set +e
actual="$(php bin/plugin goosialize-leads deliver-notifications --limit=10 --no-ansi)"
status=$?
set -e
test "$status" -eq 2
test "$actual" = "ERROR code=delivery_disabled count=1
RESULT discovered=0 delivered=0 dead_lettered=0 deferred=0 contended=0 failed=0 uncertain=0"
set +e
invalid="$(php bin/plugin goosialize-leads deliver-notifications --limit=0 --no-ansi)"
invalid_status=$?
set -e
test "$invalid_status" -eq 2
test "$invalid" = "ERROR code=invalid_limit count=1
RESULT discovered=0 delivered=0 dead_lettered=0 deferred=0 contended=0 failed=0 uncertain=0"
mkdir -p user/config/plugins
cat > user/config/plugins/goosialize-leads.yaml <<'\''YAML'\''
enabled: true
notifications:
  delivery:
    enabled: true
    recipients: []
    sender_address: sender@example.test
    sender_name: null
    default_limit: 10
YAML
php bin/grav cache --all >/dev/null
set +e
no_recipient="$(php bin/plugin goosialize-leads deliver-notifications --limit=10 --no-ansi)"
no_recipient_status=$?
set -e
test "$no_recipient_status" -eq 2
test "$no_recipient" = "ERROR code=routing_invalid count=1
RESULT discovered=0 delivered=0 dead_lettered=0 deferred=0 contended=0 failed=0 uncertain=0"
sed -i "s/recipients: \\[\\]/recipients: [INVALID@example.test]/" user/config/plugins/goosialize-leads.yaml
php bin/grav cache --all >/dev/null
set +e
invalid_recipient="$(php bin/plugin goosialize-leads deliver-notifications --limit=10 --no-ansi)"
invalid_recipient_status=$?
set -e
test "$invalid_recipient_status" -eq 2
test "$invalid_recipient" = "ERROR code=routing_invalid count=1
RESULT discovered=0 delivered=0 dead_lettered=0 deferred=0 contended=0 failed=0 uncertain=0"
cat > user/config/plugins/goosialize-leads.yaml <<'\''YAML'\''
enabled: true
notifications:
  delivery:
    enabled: true
    recipients:
      - notify@example.test
    sender_address: sender@example.test
    sender_name: Synthetic Sender
    default_limit: 10
YAML
mv user/plugins/email /tmp/email-plugin
php bin/grav cache --all >/dev/null
set +e
absent="$(php bin/plugin goosialize-leads deliver-notifications --limit=10 --no-ansi)"
absent_status=$?
set -e
test "$absent_status" -eq 2
test "$absent" = "ERROR code=email_unavailable count=1
RESULT discovered=0 delivered=0 dead_lettered=0 deferred=0 contended=0 failed=0 uncertain=0"
mv /tmp/email-plugin user/plugins/email
printf "enabled: false\n" > user/config/plugins/email.yaml
php bin/grav cache --all >/dev/null
set +e
disabled="$(php bin/plugin goosialize-leads deliver-notifications --limit=10 --no-ansi)"
disabled_status=$?
set -e
test "$disabled_status" -eq 2
test "$disabled" = "ERROR code=email_unavailable count=1
RESULT discovered=0 delivered=0 dead_lettered=0 deferred=0 contended=0 failed=0 uncertain=0"
printf "PASS_PHASE_5B_COMMAND_RUNTIME\n"
')"
grep -qx 'PASS_PHASE_5B_COMMAND_RUNTIME' <<<"${command_output}" || fail 'command runtime'

test "$(wc -l < "${ROOT}/packaging/package-files.txt")" -eq 88
grep -qx 'cli/DeliverNotificationsCommand.php' "${ROOT}/packaging/package-files.txt"
grep -q "setName('deliver-notifications')" "${ROOT}/cli/DeliverNotificationsCommand.php"
grep -q "InputOption::VALUE_REQUIRED" "${ROOT}/cli/DeliverNotificationsCommand.php"
! rg -n 'curl|socket_create|stream_socket_client|schedule' \
    "${ROOT}/classes/Notification" "${ROOT}/cli/DeliverNotificationsCommand.php"

printf 'PASS_PHASE_5B_REGRESSIONS\n'
printf 'PASS_PHASE_5B_PACKAGE\n'
# Phase 5C.2 preserves manual delivery behavior.
