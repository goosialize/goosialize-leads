#!/usr/bin/env bash
set -euo pipefail

readonly EXPECTED_IMAGE_ID='sha256:702d936e25513805b57c9d009f7ff466217273415b2e55f539f3366e6377d351'
readonly SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
readonly REPOSITORY_ROOT="$(cd -- "${SCRIPT_DIR}/../.." && pwd -P)"
readonly PLUGIN_MOUNT='/app/www/public/user/plugins/goosialize-leads'
readonly ENABLED_CONTAINER="goosialize-leads-phase-2d-enabled-$$"
readonly DISABLED_CONTAINER="goosialize-leads-phase-2d-disabled-$$"

fail() { printf 'FAIL: %s\n' "$*" >&2; exit 1; }
cleanup() { docker rm -f "${ENABLED_CONTAINER}" "${DISABLED_CONTAINER}" >/dev/null 2>&1 || true; }
trap cleanup EXIT HUP INT TERM

for tool in docker git node sha256sum stat find; do
    command -v "${tool}" >/dev/null 2>&1 || fail "${tool} is not available"
done
[[ -n "${GRAV_TEST_IMAGE:-}" ]] || fail 'GRAV_TEST_IMAGE must name an already-local Docker image'
docker image inspect "${GRAV_TEST_IMAGE}" >/dev/null 2>&1 || fail "Docker image is not available locally: ${GRAV_TEST_IMAGE}"
readonly ACTUAL_IMAGE_ID="$(docker image inspect --format '{{.Id}}' "${GRAV_TEST_IMAGE}")"
[[ "${ACTUAL_IMAGE_ID}" == "${EXPECTED_IMAGE_ID}" ]] || fail "image ID mismatch: ${ACTUAL_IMAGE_ID}"

repository_digest() {
    (
        cd "${REPOSITORY_ROOT}"
        find . -path './.git' -prune -o -type f -printf '%P\0' \
            | LC_ALL=C sort -z \
            | while IFS= read -r -d '' path; do
                printf '%s\0%s\0%s\0' "${path}" "$(stat -c '%a' -- "${path}")" \
                    "$(sha256sum -- "${path}" | awk '{print $1}')"
            done \
            | sha256sum | awk '{print $1}'
    )
}

readonly CONTENT_BEFORE="$(repository_digest)"
readonly STATUS_BEFORE="$(git -C "${REPOSITORY_ROOT}" status --porcelain=v1 -z | sha256sum | awk '{print $1}')"
readonly HEAD_BEFORE="$(git -C "${REPOSITORY_ROOT}" rev-parse HEAD)"
readonly BRANCH_BEFORE="$(git -C "${REPOSITORY_ROOT}" branch --show-current)"

[[ "$(wc -l < "${REPOSITORY_ROOT}/packaging/package-files.txt")" -eq 45 ]] || fail 'package manifest count mismatch'
[[ "$(<"${REPOSITORY_ROOT}/templates/phase-2d-skeleton.html.twig")" == 'Goosialize Leads Phase 2 skeleton.' ]] || fail 'template content mismatch'
node --check "${REPOSITORY_ROOT}/admin-next/pages/goosialize-leads.js"
if grep -Eiq 'attachShadow|fetch|XMLHttpRequest|WebSocket|sendBeacon|localStorage|sessionStorage|document\.cookie|location\.|<form|<input|<button|addEventListener|import[ (]|export ' "${REPOSITORY_ROOT}/admin-next/pages/goosialize-leads.js"; then
    fail 'Admin2 component contains a forbidden capability'
fi

node - "${REPOSITORY_ROOT}/admin-next/pages/goosialize-leads.js" <<'NODE'
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(process.argv[2], 'utf8');
const definitions = new Map();
let mutations = 0;
class HTMLElement {
    set textContent(value) { this.value = value; mutations += 1; }
}
const context = {
    window: {__GRAV_PAGE_TAG: 'grav-goosialize-leads--page'},
    HTMLElement,
    customElements: {
        get: tag => definitions.get(tag),
        define: (tag, constructor) => {
            if (definitions.has(tag)) throw new Error('duplicate definition');
            definitions.set(tag, constructor);
        }
    }
};
vm.runInNewContext(source, context, {filename: 'goosialize-leads.js'});
if (definitions.size !== 1 || !definitions.has(context.window.__GRAV_PAGE_TAG)) throw new Error('supplied tag was not defined exactly once');
const Element = definitions.get(context.window.__GRAV_PAGE_TAG);
const element = new Element();
element.connectedCallback();
if (element.value !== 'Goosialize Leads Phase 2 skeleton. No lead functionality is enabled.') throw new Error('inert text mismatch');
if (mutations !== 1) throw new Error('unexpected DOM mutation count');
vm.runInNewContext(source, context, {filename: 'goosialize-leads.js'});
if (definitions.size !== 1) throw new Error('duplicate evaluation changed registration');
const invalid = {...context, window: {__GRAV_PAGE_TAG: 'invalid'}};
vm.runInNewContext(source, invalid, {filename: 'goosialize-leads.js'});
if (definitions.size !== 1) throw new Error('invalid tag registered');
NODE

printf 'PASS_LOCAL_IMAGE image=%s id=%s\n' "${GRAV_TEST_IMAGE}" "${ACTUAL_IMAGE_ID}"

docker run --rm --name "${ENABLED_CONTAINER}" --network none \
    --mount "type=bind,src=${REPOSITORY_ROOT},dst=${PLUGIN_MOUNT},readonly" \
    --entrypoint /bin/sh "${GRAV_TEST_IMAGE}" -c '
set -eu
cd /app/www/public
test "$(php bin/grav --version)" = "Grav CLI Application 2.0.12"
php -l user/plugins/goosialize-leads/goosialize-leads.php
php -r '\''
define("GRAV_CLI", true); define("GRAV_REQUEST_TIME", microtime(true));
$autoload = require "/app/www/public/vendor/autoload.php";
$grav = Grav\Common\Grav::instance(["loader" => $autoload]); $grav->initializeCli();
$plugin = Grav\Common\Plugins::getPlugin("goosialize-leads");
if (!$plugin || !$grav["config"]->get("plugins.goosialize-leads.enabled")) throw new RuntimeException("enabled plugin unavailable");
$expected = ["Grav\\Events\\PermissionsRegisterEvent" => ["onRegisterPermissions", 1000], "onApiRegisterRoutes" => ["onApiRegisterRoutes", 0], "onApiSidebarItems" => ["onApiSidebarItems", 0], "onApiPluginPageInfo" => ["onApiPluginPageInfo", 0], "onApiCollectPublicRoutes" => ["onApiCollectPublicRoutes", 0], "onRequestHandlerInit" => ["onRequestHandlerInit", 98000], "onTwigTemplatePaths" => ["onTwigTemplatePaths", 0], "onFormProcessed" => ["onFormProcessed", 0]];
if ($plugin::getSubscribedEvents() !== $expected) throw new RuntimeException("subscription allowlist mismatch");
$plugin->autoload();
foreach ([
    "Grav\\Plugin\\GoosializeLeads\\Admin\\LeadIndexCollection",
    "Grav\\Plugin\\GoosializeLeads\\Admin\\LeadIndexQuery",
    "Grav\\Plugin\\GoosializeLeads\\Admin\\LeadSummary",
    "Grav\\Plugin\\GoosializeLeads\\Admin\\LeadsIndexController",
    "Grav\\Plugin\\GoosializeLeads\\Application\\CaptureCommand",
    "Grav\\Plugin\\GoosializeLeads\\Application\\CaptureResult",
    "Grav\\Plugin\\GoosializeLeads\\Application\\LeadCaptureService",
    "Grav\\Plugin\\GoosializeLeads\\Application\\LeadPersistenceCoordinator",
    "Grav\\Plugin\\GoosializeLeads\\Domain\\LeadIdGenerator",
    "Grav\\Plugin\\GoosializeLeads\\Domain\\LeadRecord",
    "Grav\\Plugin\\GoosializeLeads\\Http\\ApiParseResult",
    "Grav\\Plugin\\GoosializeLeads\\Http\\ApiRequestMapper",
    "Grav\\Plugin\\GoosializeLeads\\Http\\ApiRequestResult",
    "Grav\\Plugin\\GoosializeLeads\\Http\\ApiResponseMapper",
    "Grav\\Plugin\\GoosializeLeads\\Http\\EndpointRateLimiter",
    "Grav\\Plugin\\GoosializeLeads\\Http\\FormsLeadCaptureAdapter",
    "Grav\\Plugin\\GoosializeLeads\\Http\\OriginPolicy",
    "Grav\\Plugin\\GoosializeLeads\\Http\\PublicApiRawBodyMiddleware",
    "Grav\\Plugin\\GoosializeLeads\\Http\\PublicLeadApiController",
    "Grav\\Plugin\\GoosializeLeads\\Http\\RateLimitResult",
    "Grav\\Plugin\\GoosializeLeads\\Http\\RawJsonParser",
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
] as $class) {
    if (!class_exists($class) || !(new ReflectionClass($class))->isFinal()) throw new RuntimeException("Phase 3A class mismatch: " . $class);
}
if (!interface_exists("Grav\\Plugin\\GoosializeLeads\\Storage\\LeadRepository")) throw new RuntimeException("Phase 3B repository interface missing");
if (!interface_exists("Grav\\Plugin\\GoosializeLeads\\Storage\\LeadReadRepository")) throw new RuntimeException("Phase 4A.1 read repository interface missing");
$routeMethod = new ReflectionMethod($plugin, "onApiRegisterRoutes");
$parameters = $routeMethod->getParameters();
if (count($parameters) !== 1 || (string) $parameters[0]->getType() !== "RocketTheme\\Toolbox\\Event\\Event" || (string) $routeMethod->getReturnType() !== "void") throw new RuntimeException("route listener signature mismatch");
$sentinel = new class { public function __call(string $name, array $arguments): never { throw new RuntimeException("route method called: " . $name); } };
$event = new RocketTheme\Toolbox\Event\Event(["routes" => $sentinel]);
$plugin->onApiRegisterRoutes($event);
if ($event["routes"] !== $sentinel) throw new RuntimeException("route event changed");
$grav["twig"]->twig_paths = [];
$plugin->onTwigTemplatePaths();
$path = "/app/www/public/user/plugins/goosialize-leads/templates";
if ($grav["twig"]->twig_paths !== [$path]) throw new RuntimeException("Twig path mismatch");
$loader = new Twig\Loader\FilesystemLoader($grav["twig"]->twig_paths);
$twig = new Twig\Environment($loader);
if ($twig->render("phase-2d-skeleton.html.twig") !== "Goosialize Leads Phase 2 skeleton.\n") throw new RuntimeException("Twig render mismatch");
$user = new Grav\Common\User\User(["access" => ["api" => ["super" => true]]]);
$pageRequest = (new Nyholm\Psr7\ServerRequest("GET", "/gpm/plugins/goosialize-leads/page"))->withAttribute("api_user", $user)->withAttribute("route_params", ["slug" => "goosialize-leads"]);
$controller = new Grav\Plugin\Api\Controllers\GpmController($grav, $grav["config"]);
$pageResponse = $controller->pluginPage($pageRequest);
if ($pageResponse->getStatusCode() !== 200 || $pageResponse->getHeaderLine("Content-Type") !== "application/json") throw new RuntimeException("Admin2 page metadata response mismatch");
$pageBody = json_decode((string) $pageResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
$page = $pageBody["data"] ?? null;
if (($page["id"] ?? null) !== "goosialize-leads" || ($page["plugin"] ?? null) !== "goosialize-leads" || ($page["page_type"] ?? null) !== "component" || ($page["has_custom_component"] ?? null) !== true) throw new RuntimeException("Admin2 component metadata mismatch");
$scriptRequest = (new Nyholm\Psr7\ServerRequest("GET", "/gpm/plugins/goosialize-leads/page-script"))->withAttribute("api_user", $user)->withAttribute("route_params", ["slug" => "goosialize-leads"]);
$scriptResponse = $controller->customPageScript($scriptRequest);
$scriptFile = "/app/www/public/user/plugins/goosialize-leads/admin-next/pages/goosialize-leads.js";
if ($scriptResponse->getStatusCode() !== 200 || $scriptResponse->getHeaderLine("Content-Type") !== "application/javascript; charset=utf-8") throw new RuntimeException("Admin2 page script response mismatch");
if (!hash_equals(hash_file("sha256", $scriptFile), hash("sha256", (string) $scriptResponse->getBody()))) throw new RuntimeException("Admin2 page script body mismatch");
if (is_dir("/app/www/public/user/themes/goosialize")) throw new RuntimeException("Goosialize theme unexpectedly present");
'\''
'
printf 'PASS_ROUTE_PROVIDER_ENTRY_POINT\nPASS_NO_FUNCTIONAL_ROUTE\n'
printf 'PASS_TWIG_TEMPLATE_ENTRY_POINT\nPASS_TEMPLATE_THEME_INDEPENDENCE\n'
printf 'PASS_ADMIN2_COMPONENT_DISCOVERY\nPASS_ADMIN2_COMPONENT_CONTRACT\n'

docker run --rm --name "${DISABLED_CONTAINER}" --network none \
    --mount "type=bind,src=${REPOSITORY_ROOT},dst=${PLUGIN_MOUNT},readonly" \
    --entrypoint /bin/sh "${GRAV_TEST_IMAGE}" -c '
set -eu
cd /app/www/public
mkdir -p user/config/plugins
printf "enabled: false\n" > user/config/plugins/goosialize-leads.yaml
php -r '\''
define("GRAV_CLI", true); define("GRAV_REQUEST_TIME", microtime(true));
$autoload = require "/app/www/public/vendor/autoload.php";
$grav = Grav\Common\Grav::instance(["loader" => $autoload]); $grav->initializeCli();
$plugin = Grav\Common\Plugins::getPlugin("goosialize-leads"); $grav["plugins"]->init();
if (!$plugin || $plugin->config() !== []) throw new RuntimeException("disabled plugin became active");
if (in_array("/app/www/public/user/plugins/goosialize-leads/templates", $grav["twig"]->twig_paths, true)) throw new RuntimeException("disabled Twig contribution active");
$dispatcher = FastRoute\simpleDispatcher(function (FastRoute\RouteCollector $routes) use ($grav): void {
    $routes->addRoute("GET", "/phase-2d-baseline", static fn(): null => null);
    $event = new RocketTheme\Toolbox\Event\Event(["routes" => new Grav\Plugin\Api\ApiRouteCollector($routes)]);
    $grav->fireEvent("onApiRegisterRoutes", $event);
});
if (($dispatcher->dispatch("GET", "/phase-2d-baseline")[0] ?? null) !== FastRoute\Dispatcher::FOUND) throw new RuntimeException("baseline route collector changed");
foreach (["/goosialize", "/goosialize-leads", "/goosialize/leads"] as $route) {
    if (($dispatcher->dispatch("GET", $route)[0] ?? null) !== FastRoute\Dispatcher::NOT_FOUND) throw new RuntimeException("disabled Goosialize route became dispatchable: " . $route);
}
$loader = new Twig\Loader\FilesystemLoader($grav["twig"]->twig_paths);
try {
    $loader->getSourceContext("phase-2d-skeleton.html.twig");
    throw new RuntimeException("disabled plugin template unexpectedly resolved");
} catch (Twig\Error\LoaderError) {
}
$user = new Grav\Common\User\User(["access" => ["api" => ["super" => true]]]);
$pageRequest = (new Nyholm\Psr7\ServerRequest("GET", "/gpm/plugins/goosialize-leads/page"))->withAttribute("api_user", $user)->withAttribute("route_params", ["slug" => "goosialize-leads"]);
$controller = new Grav\Plugin\Api\Controllers\GpmController($grav, $grav["config"]);
$pageResponse = $controller->pluginPage($pageRequest);
$pageBody = json_decode((string) $pageResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
$page = $pageBody["data"] ?? null;
if ($pageResponse->getStatusCode() !== 200 || ($page["page_type"] ?? null) !== "component" || ($page["has_custom_component"] ?? null) !== true) throw new RuntimeException("disabled installed Admin2 metadata mismatch");
$scriptRequest = (new Nyholm\Psr7\ServerRequest("GET", "/gpm/plugins/goosialize-leads/page-script"))->withAttribute("api_user", $user)->withAttribute("route_params", ["slug" => "goosialize-leads"]);
$scriptResponse = $controller->customPageScript($scriptRequest);
$scriptFile = "/app/www/public/user/plugins/goosialize-leads/admin-next/pages/goosialize-leads.js";
if ($scriptResponse->getStatusCode() !== 200 || $scriptResponse->getHeaderLine("Content-Type") !== "application/javascript; charset=utf-8") throw new RuntimeException("disabled installed Admin2 script response mismatch");
if (!hash_equals(hash_file("sha256", $scriptFile), hash("sha256", (string) $scriptResponse->getBody()))) throw new RuntimeException("disabled installed Admin2 script body mismatch");
'\''
'
printf 'PASS_DISABLED_RUNTIME_ENTRY_POINTS_INACTIVE
PASS_DISABLED_ADMIN2_FILESYSTEM_DISCOVERY_EXPECTED\n'

phase_2b_output="$(GRAV_TEST_IMAGE="${GRAV_TEST_IMAGE}" "${SCRIPT_DIR}/clean-grav-plugin-load.sh")"
printf '%s\n' "${phase_2b_output}"
grep -q '^PASS_CLEAN_GRAV_PLUGIN_LOAD$' <<<"${phase_2b_output}" || fail 'Phase 2B regression marker missing'
printf 'PASS_PHASE_2B_REGRESSION\n'

phase_2c_output="$(GRAV_TEST_IMAGE="${GRAV_TEST_IMAGE}" "${SCRIPT_DIR}/installable-plugin-package.sh")"
printf '%s\n' "${phase_2c_output}"
grep -q '^PASS_PACKAGE_BUILD$' <<<"${phase_2c_output}" || fail 'updated package build marker missing'
grep -q '^PASS_LOCAL_PACKAGE_INSTALL$' <<<"${phase_2c_output}" || fail 'updated package install marker missing'
grep -q '^PASS_INSTALLABLE_PLUGIN_PACKAGE$' <<<"${phase_2c_output}" || fail 'Phase 2C regression marker missing'
grep -q '^PASS_PHASE_3A_PACKAGE$' <<<"${phase_2c_output}" || fail 'Phase 3A package marker missing'
printf 'PASS_UPDATED_PACKAGE_BUILD\nPASS_UPDATED_PACKAGE_INSTALL\nPASS_PHASE_2C_REGRESSION\n'

if command -v chromium >/dev/null 2>&1 || command -v chromium-browser >/dev/null 2>&1 \
    || command -v google-chrome >/dev/null 2>&1 || command -v firefox >/dev/null 2>&1; then
    fail 'browser runtime exists but no genuine browser test was executed'
else
    printf 'ADMIN2_REAL_BROWSER_TEST=UNAVAILABLE\n'
fi

[[ "$(repository_digest)" == "${CONTENT_BEFORE}" ]] || fail 'repository content or modes changed during testing'
[[ "$(git -C "${REPOSITORY_ROOT}" status --porcelain=v1 -z | sha256sum | awk '{print $1}')" == "${STATUS_BEFORE}" ]] || fail 'Git status changed during testing'
[[ "$(git -C "${REPOSITORY_ROOT}" rev-parse HEAD)" == "${HEAD_BEFORE}" ]] || fail 'HEAD changed during testing'
[[ "$(git -C "${REPOSITORY_ROOT}" branch --show-current)" == "${BRANCH_BEFORE}" ]] || fail 'branch changed during testing'
printf 'PASS_REPOSITORY_UNCHANGED digest=%s\n' "${CONTENT_BEFORE}"
printf 'PASS_PHASE_2D_REGRESSION\n'
printf 'PASS_PHASE_2D_NATIVE_ENTRY_POINTS\n'
