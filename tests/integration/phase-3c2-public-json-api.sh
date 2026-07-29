#!/usr/bin/env bash
set -euo pipefail

readonly EXPECTED_IMAGE_ID='sha256:702d936e25513805b57c9d009f7ff466217273415b2e55f539f3366e6377d351'
readonly SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
readonly REPOSITORY_ROOT="$(cd -- "${SCRIPT_DIR}/../.." && pwd -P)"

fail() { printf 'FAIL: %s\n' "$*" >&2; exit 1; }
[[ -n "${GRAV_TEST_IMAGE:-}" ]] || fail 'GRAV_TEST_IMAGE is required'
readonly ACTUAL_IMAGE_ID="$(docker image inspect --format '{{.Id}}' "${GRAV_TEST_IMAGE}")"
[[ "${ACTUAL_IMAGE_ID}" == "${EXPECTED_IMAGE_ID}" ]] || fail 'immutable image mismatch'

output="$(docker run --rm --network none \
    --mount "type=bind,src=${REPOSITORY_ROOT},dst=/plugin,readonly" \
    --entrypoint php "${EXPECTED_IMAGE_ID}" /plugin/tests/unit/phase-3c2-public-json-api.php)"
printf '%s\n' "${output}"
grep -q '^PASS_PHASE_3C2_SHARED_API$' <<<"${output}" || fail 'unit marker missing'

grep -q "'onApiRegisterRoutes' => \\['onApiRegisterRoutes', 0\\]" "${REPOSITORY_ROOT}/goosialize-leads.php"
grep -q "'onApiCollectPublicRoutes' => \\['onApiCollectPublicRoutes', 0\\]" "${REPOSITORY_ROOT}/goosialize-leads.php"
grep -q "post('/goosialize-leads/capture'" "${REPOSITORY_ROOT}/goosialize-leads.php"
grep -q 'POST /api/v1/goosialize-leads/capture' "${REPOSITORY_ROOT}/goosialize-leads.php"
grep -q "getUri()->getPath() !== '/api/v1/goosialize-leads/capture'" "${REPOSITORY_ROOT}/classes/Http/PublicApiRawBodyMiddleware.php"

docker run --rm --network none \
    --mount "type=bind,src=${REPOSITORY_ROOT},dst=/plugin,readonly" \
    --entrypoint /bin/sh "${EXPECTED_IMAGE_ID}" -c '
set -eu
root="$(mktemp -d /tmp/phase-3c2-rate.XXXXXX)"
i=0
while [ "$i" -lt 20 ]; do
    php /plugin/tests/unit/phase-3c2-public-json-api.php --rate-check "$root" > "$root/result-$i" &
    i=$((i + 1))
done
wait
test "$(grep -l "^allowed$" "$root"/result-* | wc -l)" -eq 10
test "$(grep -l "^RATE_LIMITED$" "$root"/result-* | wc -l)" -eq 10
'

docker run --rm --network none \
    --mount "type=bind,src=${REPOSITORY_ROOT},dst=/app/www/public/user/plugins/goosialize-leads,readonly" \
    --entrypoint /bin/sh "${EXPECTED_IMAGE_ID}" -c '
set -eu
cd /app/www/public
php -r '\''
require "vendor/autoload.php";
if (class_exists(Grav\Plugin\ApiPlugin::class, false)) throw new RuntimeException("API plugin was already loaded");
require "user/plugins/goosialize-leads/goosialize-leads.php";
require "user/plugins/goosialize-leads/autoload.php";
if (!isset(Grav\Plugin\GoosializeLeadsPlugin::getSubscribedEvents()["onApiRegisterRoutes"])) throw new RuntimeException("subscription missing");
if (!class_exists(Grav\Plugin\GoosializeLeads\Http\RawJsonParser::class)) throw new RuntimeException("autoload failed without API");
if (class_exists(Grav\Plugin\ApiPlugin::class, false)) throw new RuntimeException("plugin forced API load");
'\'''

docker run --rm --network none \
    --mount "type=bind,src=${REPOSITORY_ROOT},dst=/app/www/public/user/plugins/goosialize-leads,readonly" \
    --entrypoint php "${EXPECTED_IMAGE_ID}" -r '
chdir("/app/www/public");
define("GRAV_CLI", true); define("GRAV_REQUEST_TIME", microtime(true));
$autoload=require "/app/www/public/vendor/autoload.php";
$grav=Grav\Common\Grav::instance(["loader"=>$autoload]); $grav->initializeCli();
$plugin=Grav\Common\Plugins::getPlugin("goosialize-leads");
$grav["plugins"]->init();
$grav["config"]->set("plugins.goosialize-leads.public_api", [
  "enabled"=>true,"allowed_origins"=>[],"locale"=>null,"consent_version"=>"privacy-v1",
  "body_max_bytes"=>16384,"json_max_depth"=>4,"rate_limit_count"=>10,"rate_limit_window_seconds"=>60
]);
$grav["config"]->set("plugins.goosialize-leads.idempotency", [
  "active_key_version"=>1,"keys"=>[1=>base64_encode(str_repeat("K",32))]
]);
$routes=new class { public array $calls=[]; public function post(string $path,array $handler):self{$this->calls[]=[$path,$handler];return $this;} };
$event=new RocketTheme\Toolbox\Event\Event(["routes"=>$routes]);
$plugin->onApiRegisterRoutes($event);
if ($routes->calls !== [["/goosialize-leads/capture",[Grav\Plugin\GoosializeLeads\Http\PublicLeadApiController::class,"capture"]]]) throw new RuntimeException("route mismatch");
$public=new RocketTheme\Toolbox\Event\Event(["api_base"=>"/api/v1","prefixes"=>[],"exact"=>[]]);
$plugin->onApiCollectPublicRoutes($public);
if ($public["exact"] !== ["POST /api/v1/goosialize-leads/capture"]) throw new RuntimeException("public classification mismatch");
'
printf 'PASS_PHASE_3C2_RAW_JSON\n'
printf 'PASS_PHASE_3C2_PUBLIC_ROUTE\n'
printf 'PASS_PHASE_3C2_RATE_LIMIT\n'

php_files="$(find "${REPOSITORY_ROOT}/classes" -type f -name '*.php' -print)"
docker run --rm --network none \
    --mount "type=bind,src=${REPOSITORY_ROOT},dst=/plugin,readonly" \
    --entrypoint /bin/sh "${EXPECTED_IMAGE_ID}" -c \
    'set -eu; for f in /plugin/classes/*/*.php /plugin/goosialize-leads.php; do php -l "$f" >/dev/null; done'
[[ "$(wc -l < "${REPOSITORY_ROOT}/packaging/package-files.txt")" -eq 52 ]] || fail 'package count'
for path in classes/Http/ApiParseResult.php classes/Http/RawJsonParser.php classes/Http/PublicLeadApiController.php classes/Notification/NotificationOutbox.php; do
    grep -qx "${path}" "${REPOSITORY_ROOT}/packaging/package-files.txt" || fail "missing ${path}"
done
printf 'PASS_PHASE_3C2_REGRESSIONS\n'
printf 'PASS_PHASE_3C2_PACKAGE\n'
