#!/usr/bin/env bash
set -euo pipefail
# Phase 5C.1 runtime and command reflection is closed by the package oracle.

readonly PLUGIN_MOUNT='/app/www/public/user/plugins/goosialize-leads'
readonly EXPECTED_IMAGE_ID='sha256:702d936e25513805b57c9d009f7ff466217273415b2e55f539f3366e6377d351'
grep -qx 'cli/DeliverNotificationsCommand.php' "$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd -P)/packaging/package-files.txt"
readonly SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
readonly REPOSITORY_ROOT="$(cd -- "${SCRIPT_DIR}/../.." && pwd -P)"
readonly ENABLED_CONTAINER="goosialize-leads-enabled-$$"
readonly DISABLED_CONTAINER="goosialize-leads-disabled-$$"

fail() { printf 'FAIL: %s\n' "$*" >&2; exit 1; }
cleanup() { docker rm -f "${ENABLED_CONTAINER}" "${DISABLED_CONTAINER}" >/dev/null 2>&1 || true; }
trap cleanup EXIT HUP INT TERM

for tool in docker git sha256sum; do command -v "${tool}" >/dev/null 2>&1 || fail "${tool} is not available"; done
[[ -n "${GRAV_TEST_IMAGE:-}" ]] || fail 'GRAV_TEST_IMAGE must name an already-local Docker image'
docker image inspect "${GRAV_TEST_IMAGE}" >/dev/null 2>&1 || fail "Docker image is not available locally: ${GRAV_TEST_IMAGE}"
readonly ACTUAL_IMAGE_ID="$(docker image inspect --format '{{.Id}}' "${GRAV_TEST_IMAGE}")"
[[ "${ACTUAL_IMAGE_ID}" == "${EXPECTED_IMAGE_ID}" ]] \
    || fail "Docker image ID mismatch: expected ${EXPECTED_IMAGE_ID}, actual ${ACTUAL_IMAGE_ID}"

repository_digest() {
    (
        cd "${REPOSITORY_ROOT}"
        find . -path './.git' -prune -o -type f -printf '%P\0' \
            | LC_ALL=C sort -z \
            | while IFS= read -r -d '' path; do
                printf '%s\0%s\0%s\0' \
                    "${path}" \
                    "$(stat -c '%a' -- "${path}")" \
                    "$(sha256sum -- "${path}" | awk '{print $1}')"
            done \
            | sha256sum \
            | awk '{print $1}'
    )
}
readonly CONTENT_BEFORE="$(repository_digest)"
readonly GIT_BEFORE="$(git -C "${REPOSITORY_ROOT}" status --porcelain=v1 -z | sha256sum | awk '{print $1}')"
readonly HEAD_BEFORE="$(git -C "${REPOSITORY_ROOT}" rev-parse HEAD)"
readonly BRANCH_BEFORE="$(git -C "${REPOSITORY_ROOT}" branch --show-current)"

readonly PHP_PROBE='
use Grav\Common\Grav;
use Grav\Common\Plugins;
use Grav\Plugin\GoosializeLeadsPlugin;
use Symfony\Component\Yaml\Yaml;
define("GRAV_CLI", true);
define("GRAV_REQUEST_TIME", microtime(true));
$expectedEnabled = getenv("EXPECT_ENABLED") === "1";
$root = "/app/www/public/user/plugins/goosialize-leads";
$autoload = require "/app/www/public/vendor/autoload.php";
$grav = Grav::instance(["loader" => $autoload]);
$grav->initializeCli();
$plugin = Plugins::getPlugin("goosialize-leads");
if (!$plugin) throw new RuntimeException("goosialize-leads.php was not discovered");
if (get_class($plugin) !== "Grav\\Plugin\\GoosializeLeadsPlugin") throw new RuntimeException("Unexpected plugin class");
if (!class_exists(GoosializeLeadsPlugin::class, false)) throw new RuntimeException("Plugin class was not loaded");
foreach (["blueprints.yaml", "goosialize-leads.yaml", "languages/en.yaml"] as $file) {
    if (!is_array(Yaml::parseFile($root . "/" . $file))) throw new RuntimeException("Invalid YAML: " . $file);
}
$metadata = Yaml::parseFile($root . "/blueprints.yaml");
if (($metadata["slug"] ?? null) !== "goosialize-leads") throw new RuntimeException("Invalid metadata slug");
if (($metadata["dependencies"][0]["version"] ?? null) !== ">=2.0.12 <2.1.0") throw new RuntimeException("Invalid Grav dependency");
$defaults = Yaml::parseFile($root . "/goosialize-leads.yaml");
if (($defaults["enabled"] ?? null) !== true) throw new RuntimeException("Default configuration is not enabled");
$composer = json_decode(file_get_contents($root . "/composer.json"), true, 512, JSON_THROW_ON_ERROR);
if (($composer["type"] ?? null) !== "grav-plugin") throw new RuntimeException("Invalid package type");
if (($composer["require"]["php"] ?? null) !== "^8.3") throw new RuntimeException("Invalid PHP requirement");
if (($composer["require"]["ext-intl"] ?? null) !== "*") throw new RuntimeException("Invalid ext-intl requirement");
$enabled = (bool) $grav["config"]->get("plugins.goosialize-leads.enabled");
if ($enabled !== $expectedEnabled) throw new RuntimeException("Merged enabled state mismatch");
$expectedSubscriptions = [
    "onPluginsInitialized" => ["onPluginsInitialized", 0],
    "Grav\\Events\\PermissionsRegisterEvent" => ["onRegisterPermissions", 1000],
    "onApiRegisterRoutes" => ["onApiRegisterRoutes", 0],
    "onApiSidebarItems" => ["onApiSidebarItems", 0],
    "onApiPluginPageInfo" => ["onApiPluginPageInfo", 0],
    "onApiCollectPublicRoutes" => ["onApiCollectPublicRoutes", 0],
    "onRequestHandlerInit" => ["onRequestHandlerInit", 98000],
    "onTwigTemplatePaths" => ["onTwigTemplatePaths", 0],
    "onFormProcessed" => ["onFormProcessed", 0],
    "onSchedulerInitialized" => ["onSchedulerInitialized", 0],
];
if (GoosializeLeadsPlugin::getSubscribedEvents() !== $expectedSubscriptions) throw new RuntimeException("Unexpected event subscriptions");
foreach ($expectedSubscriptions as [$method]) {
    if (!method_exists($plugin, $method)) throw new RuntimeException("Missing inert listener: " . $method);
}
$grav["plugins"]->init();
if ($expectedEnabled) {
    if (($plugin->config()["enabled"] ?? null) !== true) throw new RuntimeException("Enabled plugin was not initialized");
    $before = $grav["twig"]->twig_paths;
    $plugin->onTwigTemplatePaths();
    if (array_slice($grav["twig"]->twig_paths, 0, count($before)) !== $before) throw new RuntimeException("Existing Twig paths changed");
    if (end($grav["twig"]->twig_paths) !== $root . "/templates") throw new RuntimeException("Plugin Twig path was not appended");
    $event = new RocketTheme\Toolbox\Event\Event(["routes" => new stdClass()]);
    $routes = $event["routes"];
    $plugin->onApiRegisterRoutes($event);
    if ($event["routes"] !== $routes) throw new RuntimeException("Route entry point modified its event");
    $autoloadMethod = new ReflectionMethod($plugin, "autoload");
    if (!$autoloadMethod->isPublic() || $autoloadMethod->isStatic() || (string) $autoloadMethod->getReturnType() !== "void") throw new RuntimeException("autoload signature mismatch");
    $plugin->autoload();
    $plugin->autoload();
    $classes = [
        "Grav\\Plugin\\GoosializeLeads\\Admin\\LeadIndexCollection",
        "Grav\\Plugin\\GoosializeLeads\\Admin\\LeadIndexQuery",
        "Grav\\Plugin\\GoosializeLeads\\Admin\\LeadSummary",
        "Grav\\Plugin\\GoosializeLeads\\Admin\\LeadsIndexController",
        "Grav\\Plugin\\GoosializeLeads\\Application\\CaptureCommand",
        "Grav\\Plugin\\GoosializeLeads\\Application\\CaptureResult",
        "Grav\\Plugin\\GoosializeLeads\\Application\\LeadCaptureRuntimeFactory",
        "Grav\\Plugin\\GoosializeLeads\\Application\\LeadCaptureService",
        "Grav\\Plugin\\GoosializeLeads\\Application\\LeadPersistenceCoordinator",
        "Grav\\Plugin\\GoosializeLeads\\Domain\\LeadIdGenerator",
        "Grav\\Plugin\\GoosializeLeads\\Domain\\LeadRecord",
        "Grav\\Plugin\\GoosializeLeads\\Http\\FormsLeadCaptureAdapter",
        "Grav\\Plugin\\GoosializeLeads\\Http\\ApiParseResult",
        "Grav\\Plugin\\GoosializeLeads\\Http\\ApiRequestMapper",
        "Grav\\Plugin\\GoosializeLeads\\Http\\ApiRequestResult",
        "Grav\\Plugin\\GoosializeLeads\\Http\\ApiResponseMapper",
        "Grav\\Plugin\\GoosializeLeads\\Http\\EndpointRateLimiter",
        "Grav\\Plugin\\GoosializeLeads\\Http\\OriginPolicy",
        "Grav\\Plugin\\GoosializeLeads\\Http\\PublicApiRawBodyMiddleware",
        "Grav\\Plugin\\GoosializeLeads\\Http\\PublicLeadApiController",
        "Grav\\Plugin\\GoosializeLeads\\Http\\RateLimitResult",
        "Grav\\Plugin\\GoosializeLeads\\Http\\RawJsonParser",
        "Grav\\Plugin\\GoosializeLeads\\Integration\\GoosializeLeadsCaptureCapabilityV1",
        "Grav\\Plugin\\GoosializeLeads\\Integration\\LeadCaptureContextV1",
        "Grav\\Plugin\\GoosializeLeads\\Integration\\LeadCaptureRequestV1",
        "Grav\\Plugin\\GoosializeLeads\\Integration\\LeadCaptureResultV1",
        "Grav\\Plugin\\GoosializeLeads\\Notification\\FilesystemNotificationOutbox",
        "Grav\\Plugin\\GoosializeLeads\\Notification\\NotificationEnqueueResult",
        "Grav\\Plugin\\GoosializeLeads\\Notification\\NotificationEvent",
        "Grav\\Plugin\\GoosializeLeads\\Security\\IdempotencyKeyRing",
        "Grav\\Plugin\\GoosializeLeads\\Storage\\FilesystemLeadRepository",
        "Grav\\Plugin\\GoosializeLeads\\Storage\\FilesystemLeadReadRepository",
        "Grav\\Plugin\\GoosializeLeads\\Storage\\PersistenceRequest",
        "Grav\\Plugin\\GoosializeLeads\\Storage\\PersistenceResult",
        "Grav\\Plugin\\GoosializeLeads\\Storage\\StorageException",
        "Grav\\Plugin\\GoosializeLeads\\Validation\\LeadInputValidator",
        "Grav\\Plugin\\GoosializeLeads\\Validation\\LeadNormalizer",
        "Grav\\Plugin\\GoosializeLeads\\Validation\\ValidationError",
        "Grav\\Plugin\\GoosializeLeads\\Validation\\ValidationResult",
    ];
    foreach ($classes as $class) {
        if (!class_exists($class) || !(new ReflectionClass($class))->isFinal()) throw new RuntimeException("Phase 3A class mismatch: " . $class);
    }
    if (!interface_exists("Grav\\Plugin\\GoosializeLeads\\Integration\\LeadCaptureCapabilityV1")) throw new RuntimeException("Phase 8 public capability interface missing");
    if (!interface_exists("Grav\\Plugin\\GoosializeLeads\\Storage\\LeadRepository")) throw new RuntimeException("Phase 3B repository interface missing");
    if (!interface_exists("Grav\\Plugin\\GoosializeLeads\\Storage\\LeadReadRepository")) throw new RuntimeException("Phase 4A.1 read repository interface missing");
    if (!interface_exists("Grav\\Plugin\\GoosializeLeads\\Notification\\NotificationOutbox")) throw new RuntimeException("Phase 5A outbox interface missing");
    if (is_dir($root . "/vendor")) throw new RuntimeException("Packaged vendor directory exists");
    echo "PASS_PHASE_3A_AUTOLOAD\n";
    echo "PASS_ENABLED_DISCOVERY_LOAD\n";
} else {
    if ($plugin->config() !== []) throw new RuntimeException("Disabled plugin became active");
    if (in_array($root . "/templates", $grav["twig"]->twig_paths, true)) throw new RuntimeException("Disabled plugin contributed a Twig path");
    echo "PASS_DISABLED_INACTIVE\n";
}
'

run_case() {
    local name="$1" expected="$2" setup="$3"
    docker run --rm --name "${name}" --network none \
        --mount "type=bind,src=${REPOSITORY_ROOT},dst=${PLUGIN_MOUNT},readonly" \
        --entrypoint /bin/sh --env "EXPECT_ENABLED=${expected}" "${GRAV_TEST_IMAGE}" \
        -c '
            set -eu
            cd /app/www/public
            test "$(php bin/grav --version)" = "Grav CLI Application 2.0.12"
            php -r "exit(PHP_VERSION_ID >= 80300 && PHP_VERSION_ID < 90000 ? 0 : 1);"
            awk '\''
                $5 == "/app/www/public/user/plugins/goosialize-leads" && $6 ~ /(^|,)ro(,|$)/ { ro = 1 }
                $5 ~ "^/app/www/public/" && $5 != "/app/www/public/user/plugins/goosialize-leads" { extra = 1 }
                END { exit ro && !extra ? 0 : 1 }
            '\'' /proc/self/mountinfo
            test ! -d user/themes/goosialize
            test -f user/plugins/api/api.php
            test -f user/plugins/admin2/admin2.php
            test -f user/plugins/goosialize-leads/goosialize-leads.php
            '"${setup}"'
            output="$(find user/plugins/goosialize-leads -type f -name "*.php" -exec php -l {} \;)"
            printf "%s\n" "$output" | grep -qv "No syntax errors detected" && exit 1 || true
            php -d display_errors=1 -d error_reporting=E_ALL -r "$1"
        ' sh "${PHP_PROBE}"
}

printf 'PASS_LOCAL_IMAGE image=%s id=%s\n' "${GRAV_TEST_IMAGE}" "${ACTUAL_IMAGE_ID}"
run_case "${ENABLED_CONTAINER}" 1 ':'
run_case "${DISABLED_CONTAINER}" 0 'mkdir -p user/config/plugins; printf "enabled: false\n" > user/config/plugins/goosialize-leads.yaml'
unit_output="$(docker run --rm --network none \
    --mount "type=bind,src=${REPOSITORY_ROOT},dst=/plugin,readonly" \
    --entrypoint php "${GRAV_TEST_IMAGE}" /plugin/tests/unit/phase-3a-lead-data-validation.php)"
printf '%s\n' "${unit_output}"
for marker in PASS_PHASE_3A_AUTOLOAD PASS_PHASE_3A_SCHEMA PASS_PHASE_3A_VALIDATION PASS_PHASE_3A_UNICODE; do
    grep -q "^${marker}$" <<<"${unit_output}" || fail "missing unit marker: ${marker}"
done

[[ "$(repository_digest)" == "${CONTENT_BEFORE}" ]] || fail 'repository content changed during testing'
[[ "$(git -C "${REPOSITORY_ROOT}" status --porcelain=v1 -z | sha256sum | awk '{print $1}')" == "${GIT_BEFORE}" ]] || fail 'Git status changed during testing'
[[ "$(git -C "${REPOSITORY_ROOT}" rev-parse HEAD)" == "${HEAD_BEFORE}" ]] || fail 'HEAD changed during testing'
[[ "$(git -C "${REPOSITORY_ROOT}" branch --show-current)" == "${BRANCH_BEFORE}" ]] || fail 'branch changed during testing'
printf 'PASS_REPOSITORY_UNCHANGED digest=%s\n' "${CONTENT_BEFORE}"
printf 'PASS_CLEAN_GRAV_PLUGIN_LOAD\n'
# Phase 5C.2 regression coverage is owned by phase-5c2-scheduling-visibility.sh.
