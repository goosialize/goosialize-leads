#!/usr/bin/env bash
set -euo pipefail
# Phase 5C.1 preserves immutable Phase 5A outbox events.

readonly EXPECTED_IMAGE_ID='sha256:702d936e25513805b57c9d009f7ff466217273415b2e55f539f3366e6377d351'
readonly REPOSITORY_ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd -P)"
readonly IMAGE="${GRAV_TEST_IMAGE:-}"

[[ -n "${IMAGE}" ]]
[[ "$(docker image inspect --format '{{.Id}}' "${IMAGE}")" == "${EXPECTED_IMAGE_ID}" ]]

output="$(docker run --rm --network none \
  --mount "type=bind,src=${REPOSITORY_ROOT},dst=/source,readonly" \
  --entrypoint php "${EXPECTED_IMAGE_ID}" /source/tests/unit/phase-5a-notification-outbox.php)"
printf '%s\n' "${output}"
for marker in \
  PASS_PHASE_5A_EVENT_SCHEMA \
  PASS_PHASE_5A_OUTBOX_ATOMIC \
  PASS_PHASE_5A_OUTBOX_IDEMPOTENCY \
  PASS_PHASE_5A_REPLAY_REPAIR \
  PASS_PHASE_5A_FAILURE_POLICY \
  PASS_PHASE_5A_NO_DELIVERY; do
  grep -qx "${marker}" <<<"${output}"
done

docker run --rm --network none \
  --mount "type=bind,src=${REPOSITORY_ROOT},dst=/source,readonly" \
  --entrypoint /bin/sh "${EXPECTED_IMAGE_ID}" -c '
set -eu
for file in /source/classes/*/*.php /source/goosialize-leads.php; do php -l "$file" >/dev/null; done
test "$(wc -l < /source/packaging/package-files.txt)" -eq 88
php -r '\''
require "/source/autoload.php";
$classes=[
"Grav\\Plugin\\GoosializeLeads\\Notification\\FilesystemNotificationOutbox",
"Grav\\Plugin\\GoosializeLeads\\Notification\\FilesystemPendingNotificationRepository",
"Grav\\Plugin\\GoosializeLeads\\Notification\\GravEmailNotificationTransport",
"Grav\\Plugin\\GoosializeLeads\\Notification\\LeadDeliveryRecordReader",
"Grav\\Plugin\\GoosializeLeads\\Notification\\NotificationDeliveryResult",
"Grav\\Plugin\\GoosializeLeads\\Notification\\NotificationDeliveryWorker",
"Grav\\Plugin\\GoosializeLeads\\Notification\\NotificationEnqueueResult",
"Grav\\Plugin\\GoosializeLeads\\Notification\\NotificationEvent",
"Grav\\Plugin\\GoosializeLeads\\Notification\\NotificationMessage",
"Grav\\Plugin\\GoosializeLeads\\Notification\\NotificationMessageFactory"];
foreach($classes as $class)if(!class_exists($class)||!(new ReflectionClass($class))->isFinal())throw new RuntimeException("class");
if(!interface_exists("Grav\\Plugin\\GoosializeLeads\\Notification\\NotificationOutbox"))throw new RuntimeException("interface");
if(!interface_exists("Grav\\Plugin\\GoosializeLeads\\Notification\\NotificationTransport"))throw new RuntimeException("transport interface");
if(!interface_exists("Grav\\Plugin\\GoosializeLeads\\Notification\\PendingNotificationRepository"))throw new RuntimeException("pending interface");
'\'''

docker run --rm --network none \
  --mount "type=bind,src=${REPOSITORY_ROOT},dst=/source,readonly" \
  --entrypoint /bin/sh "${EXPECTED_IMAGE_ID}" -c '
set -eu
root="$(mktemp -d /tmp/phase-5a-concurrent.XXXXXX)"
trap '\''rm -rf -- "$root"'\'' EXIT
i=0
while [ "$i" -lt 20 ]; do
  php /source/tests/unit/phase-5a-notification-outbox.php --concurrent "$root" > "$root/result-$i" &
  i=$((i+1))
done
wait
test "$(grep -lx created "$root"/result-* | wc -l)" -eq 1
test "$(grep -lx existing "$root"/result-* | wc -l)" -eq 19
test "$(find "$root/goosialize-leads/v1/notification-outbox/events" -type f -name "*.json" | wc -l)" -eq 1
test -z "$(find "$root/goosialize-leads/v1/notification-outbox/events" -type f \( -name "*.lock" -o -name "*.tmp" \) -print)"
'

printf 'PASS_PHASE_5A_CAPTURE_PARITY\n'
printf 'PASS_PHASE_5A_REGRESSIONS\n'
printf 'PASS_PHASE_5A_PACKAGE\n'
# Phase 5C.2 preserves immutable outbox behavior.
