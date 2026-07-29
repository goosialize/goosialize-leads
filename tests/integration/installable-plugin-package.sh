#!/usr/bin/env bash
set -euo pipefail

readonly EXPECTED_IMAGE_ID='sha256:702d936e25513805b57c9d009f7ff466217273415b2e55f539f3366e6377d351'
readonly SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
readonly REPOSITORY_ROOT="$(cd -- "${SCRIPT_DIR}/../.." && pwd -P)"
readonly CONTAINER_NAME="goosialize-leads-package-test-$$"
readonly TEMP_ROOT="$(mktemp -d /tmp/goosialize-leads-package-test.XXXXXX)"
readonly ZIP_NAME='goosialize-leads-0.1.0-dev.zip'
readonly ARCHIVE_ROOT='grav-plugin-goosialize-leads'

fail() { printf 'FAIL: %s\n' "$*" >&2; exit 1; }
cleanup() { docker rm -f "${CONTAINER_NAME}" >/dev/null 2>&1 || true; rm -rf -- "${TEMP_ROOT}"; }
trap cleanup EXIT HUP INT TERM

for tool in docker git python3 sha256sum cmp stat find; do command -v "${tool}" >/dev/null 2>&1 || fail "${tool} is not available"; done
[[ -n "${GRAV_TEST_IMAGE:-}" ]] || fail 'GRAV_TEST_IMAGE must name an already-local Docker image'
docker image inspect "${GRAV_TEST_IMAGE}" >/dev/null 2>&1 || fail "Docker image is not available locally: ${GRAV_TEST_IMAGE}"
readonly ACTUAL_IMAGE_ID="$(docker image inspect --format '{{.Id}}' "${GRAV_TEST_IMAGE}")"
[[ "${ACTUAL_IMAGE_ID}" == "${EXPECTED_IMAGE_ID}" ]] || fail "image ID mismatch: ${ACTUAL_IMAGE_ID}"
printf 'PASS_LOCAL_IMAGE image=%s id=%s\n' "${GRAV_TEST_IMAGE}" "${ACTUAL_IMAGE_ID}"

repository_digest() {
    ( cd "${REPOSITORY_ROOT}"; find . -path './.git' -prune -o -type f -printf '%P\0' | LC_ALL=C sort -z |
        while IFS= read -r -d '' path; do printf '%s\0%s\0%s\0' "${path}" "$(stat -c '%a' -- "${path}")" "$(sha256sum -- "${path}" | awk '{print $1}')"; done |
        sha256sum | awk '{print $1}' )
}
readonly CONTENT_BEFORE="$(repository_digest)"
readonly STATUS_BEFORE="$(git -C "${REPOSITORY_ROOT}" status --porcelain=v1 -z | sha256sum | awk '{print $1}')"
readonly HEAD_BEFORE="$(git -C "${REPOSITORY_ROOT}" rev-parse HEAD)"
readonly BRANCH_BEFORE="$(git -C "${REPOSITORY_ROOT}" branch --show-current)"
readonly VOLUMES_BEFORE="$(docker volume ls -q | LC_ALL=C sort | sha256sum | awk '{print $1}')"

mkdir -p "${TEMP_ROOT}/build-a" "${TEMP_ROOT}/build-b"
"${REPOSITORY_ROOT}/scripts/build-plugin-package.sh" "${TEMP_ROOT}/build-a"
"${REPOSITORY_ROOT}/scripts/build-plugin-package.sh" "${TEMP_ROOT}/build-b"
readonly ZIP_A="${TEMP_ROOT}/build-a/${ZIP_NAME}"
readonly ZIP_B="${TEMP_ROOT}/build-b/${ZIP_NAME}"
[[ -f "${ZIP_A}" && -f "${ZIP_B}" ]] || fail 'package build did not produce both ZIP files'
printf 'PASS_PACKAGE_BUILD\n'
cmp -s "${ZIP_A}" "${ZIP_B}" || fail 'independent package builds differ'
[[ "$(sha256sum "${ZIP_A}" | awk '{print $1}')" == "$(sha256sum "${ZIP_B}" | awk '{print $1}')" ]] || fail 'package hashes differ'
printf 'PASS_DETERMINISTIC_PACKAGE\n'

python3 - "${ZIP_A}" "${REPOSITORY_ROOT}/packaging/package-files.txt" "${ARCHIVE_ROOT}" <<'PY'
from pathlib import Path, PurePosixPath
import stat, sys, zipfile
archive_path, manifest_path, root = Path(sys.argv[1]), Path(sys.argv[2]), sys.argv[3]
files = manifest_path.read_text(encoding="utf-8").splitlines()
required = [
    "CHANGELOG.md",
    "README.md",
    "admin-next/pages/goosialize-leads.js",
    "admin/blueprints/goosialize-leads-index.yaml",
    "admin/blueprints/goosialize-leads-index-export.yaml",
    "autoload.php",
    "blueprints.yaml",
    "classes/Admin/LeadIndexCollection.php",
    "classes/Admin/LeadCsvExporter.php",
    "classes/Admin/LeadIndexQuery.php",
    "classes/Admin/LeadSummary.php",
    "classes/Admin/LeadsIndexController.php",
    "classes/Admin/LeadsCsvExportController.php",
    "classes/Application/CaptureCommand.php",
    "classes/Application/CaptureResult.php",
    "classes/Application/LeadCaptureService.php",
    "classes/Application/LeadPersistenceCoordinator.php",
    "classes/Domain/LeadIdGenerator.php",
    "classes/Domain/LeadRecord.php",
    "classes/Http/ApiParseResult.php",
    "classes/Http/ApiRequestMapper.php",
    "classes/Http/ApiRequestResult.php",
    "classes/Http/ApiResponseMapper.php",
    "classes/Http/EndpointRateLimiter.php",
    "classes/Http/FormsLeadCaptureAdapter.php",
    "classes/Http/OriginPolicy.php",
    "classes/Http/PublicApiRawBodyMiddleware.php",
    "classes/Http/PublicLeadApiController.php",
    "classes/Http/RateLimitResult.php",
    "classes/Http/RawJsonParser.php",
    "classes/Notification/FilesystemNotificationOutbox.php",
    "classes/Notification/NotificationEnqueueResult.php",
    "classes/Notification/NotificationEvent.php",
    "classes/Notification/NotificationOutbox.php",
    "classes/Security/IdempotencyKeyRing.php",
    "classes/Storage/FilesystemLeadReadRepository.php",
    "classes/Storage/FilesystemLeadRepository.php",
    "classes/Storage/LeadReadRepository.php",
    "classes/Storage/LeadRepository.php",
    "classes/Storage/PersistenceRequest.php",
    "classes/Storage/PersistenceResult.php",
    "classes/Storage/StorageException.php",
    "classes/Validation/LeadInputValidator.php",
    "classes/Validation/LeadNormalizer.php",
    "classes/Validation/ValidationError.php",
    "classes/Validation/ValidationResult.php",
    "composer.json",
    "goosialize-leads.php",
    "goosialize-leads.yaml",
    "languages/en.yaml",
    "permissions.yaml",
    "templates/phase-2d-skeleton.html.twig",
]
if files != sorted(required): raise SystemExit("manifest does not match the independent 52-file allowlist")
expected = {root + "/", root + "/languages/"} | {f"{root}/{name}" for name in files}
expected |= {root + "/admin-next/", root + "/admin-next/pages/", root + "/templates/"}
expected |= {root + "/admin/", root + "/admin/blueprints/", root + "/classes/", root + "/classes/Admin/", root + "/classes/Application/", root + "/classes/Domain/", root + "/classes/Http/", root + "/classes/Notification/", root + "/classes/Security/", root + "/classes/Storage/", root + "/classes/Validation/"}
with zipfile.ZipFile(archive_path) as archive:
    infos = archive.infolist(); names = [item.filename for item in infos]
    if len(names) != len(set(names)): raise SystemExit("duplicate ZIP entry")
    if set(names) != expected: raise SystemExit(f"unexpected ZIP entries: {set(names) ^ expected}")
    if names != sorted(names): raise SystemExit("ZIP entries are not sorted")
    for item in infos:
        path = PurePosixPath(item.filename); mode = item.external_attr >> 16
        if path.is_absolute() or ".." in path.parts or "\\" in item.filename: raise SystemExit(f"unsafe ZIP entry: {item.filename}")
        if stat.S_ISLNK(mode): raise SystemExit(f"symlink ZIP entry: {item.filename}")
        if stat.S_IMODE(mode) != (0o755 if item.is_dir() else 0o644): raise SystemExit(f"bad mode: {item.filename}")
        if item.date_time != (1980, 1, 1, 0, 0, 0): raise SystemExit(f"bad timestamp: {item.filename}")
    if names[0] != root + "/": raise SystemExit("archive root is not the first entry")
print("PASS_PACKAGE_CONTENTS"); print("PASS_ZIP_SAFETY")
PY

fixture="${TEMP_ROOT}/fixture"
mkdir -p "${fixture}/packaging" "${fixture}/scripts" "${fixture}/languages" \
    "${fixture}/admin-next/pages" "${fixture}/admin/blueprints" "${fixture}/templates" \
    "${fixture}/classes/Admin" "${fixture}/classes/Application" "${fixture}/classes/Domain" "${fixture}/classes/Http" "${fixture}/classes/Notification" "${fixture}/classes/Security" \
    "${fixture}/classes/Storage" "${fixture}/classes/Validation"
cp "${REPOSITORY_ROOT}/packaging/package-files.txt" "${fixture}/packaging/"
cp "${REPOSITORY_ROOT}/scripts/build-plugin-package.sh" "${fixture}/scripts/"
while IFS= read -r path; do cp "${REPOSITORY_ROOT}/${path}" "${fixture}/${path}"; done < "${REPOSITORY_ROOT}/packaging/package-files.txt"
mkdir -p "${TEMP_ROOT}/mutation-original" "${TEMP_ROOT}/mutation-content" "${TEMP_ROOT}/mutation-mode"
"${fixture}/scripts/build-plugin-package.sh" "${TEMP_ROOT}/mutation-original" >/dev/null
printf '\nmutation-proof\n' >> "${fixture}/README.md"
"${fixture}/scripts/build-plugin-package.sh" "${TEMP_ROOT}/mutation-content" >/dev/null
[[ "$(sha256sum "${TEMP_ROOT}/mutation-original/${ZIP_NAME}" | awk '{print $1}')" != "$(sha256sum "${TEMP_ROOT}/mutation-content/${ZIP_NAME}" | awk '{print $1}')" ]] || fail 'content mutation did not alter ZIP hash'
cp "${REPOSITORY_ROOT}/README.md" "${fixture}/README.md"; chmod 755 "${fixture}/README.md"
"${fixture}/scripts/build-plugin-package.sh" "${TEMP_ROOT}/mutation-mode" >/dev/null
cmp -s "${TEMP_ROOT}/mutation-original/${ZIP_NAME}" "${TEMP_ROOT}/mutation-mode/${ZIP_NAME}" || fail 'input permission was not normalized'

docker run --rm --name "${CONTAINER_NAME}" --network none --mount "type=bind,src=${TEMP_ROOT}/build-a,dst=/packages,readonly" --entrypoint /bin/sh "${GRAV_TEST_IMAGE}" -c '
set -eu
cd /app/www/public
test "$(php bin/grav --version)" = "Grav CLI Application 2.0.12"
root=user/plugins/goosialize-leads
if [ -e "$root" ] || [ -L "$root" ]; then
    echo "FAIL: clean runtime already contains $root" >&2
    exit 1
fi
php -r '\''
require "/app/www/public/vendor/autoload.php";
use Symfony\Component\Yaml\Yaml;
$zip=new ZipArchive(); if ($zip->open("/packages/goosialize-leads-0.1.0-dev.zip") !== true) throw new RuntimeException("ZIP open failed");
$blueprint=Yaml::parse($zip->getFromName("grav-plugin-goosialize-leads/blueprints.yaml"));
$config=Yaml::parse($zip->getFromName("grav-plugin-goosialize-leads/goosialize-leads.yaml"));
if (($blueprint["slug"]??null)!=="goosialize-leads" || ($blueprint["version"]??null)!=="0.1.0-dev" || ($config["enabled"]??null)!==true) throw new RuntimeException("invalid YAML metadata");
$composer=json_decode($zip->getFromName("grav-plugin-goosialize-leads/composer.json"),true,512,JSON_THROW_ON_ERROR);
if (($composer["type"]??null)!=="grav-plugin") throw new RuntimeException("invalid composer metadata");
'\''
php bin/gpm direct-install -y /packages/goosialize-leads-0.1.0-dev.zip
test -d "$root"
for nested in "$root/goosialize-leads" "$root/grav-plugin-goosialize-leads"; do
    if [ -e "$nested" ] || [ -L "$nested" ]; then echo "FAIL: unexpected nested package path: $nested" >&2; exit 1; fi
done
test ! -d user/themes/goosialize
test -f user/plugins/api/api.php; test -f user/plugins/admin2/admin2.php
expected="CHANGELOG.md README.md admin-next/pages/goosialize-leads.js admin/blueprints/goosialize-leads-index-export.yaml admin/blueprints/goosialize-leads-index.yaml autoload.php blueprints.yaml classes/Admin/LeadCsvExporter.php classes/Admin/LeadIndexCollection.php classes/Admin/LeadIndexQuery.php classes/Admin/LeadSummary.php classes/Admin/LeadsCsvExportController.php classes/Admin/LeadsIndexController.php classes/Application/CaptureCommand.php classes/Application/CaptureResult.php classes/Application/LeadCaptureService.php classes/Application/LeadPersistenceCoordinator.php classes/Domain/LeadIdGenerator.php classes/Domain/LeadRecord.php classes/Http/ApiParseResult.php classes/Http/ApiRequestMapper.php classes/Http/ApiRequestResult.php classes/Http/ApiResponseMapper.php classes/Http/EndpointRateLimiter.php classes/Http/FormsLeadCaptureAdapter.php classes/Http/OriginPolicy.php classes/Http/PublicApiRawBodyMiddleware.php classes/Http/PublicLeadApiController.php classes/Http/RateLimitResult.php classes/Http/RawJsonParser.php classes/Notification/FilesystemNotificationOutbox.php classes/Notification/NotificationEnqueueResult.php classes/Notification/NotificationEvent.php classes/Notification/NotificationOutbox.php classes/Security/IdempotencyKeyRing.php classes/Storage/FilesystemLeadReadRepository.php classes/Storage/FilesystemLeadRepository.php classes/Storage/LeadReadRepository.php classes/Storage/LeadRepository.php classes/Storage/PersistenceRequest.php classes/Storage/PersistenceResult.php classes/Storage/StorageException.php classes/Validation/LeadInputValidator.php classes/Validation/LeadNormalizer.php classes/Validation/ValidationError.php classes/Validation/ValidationResult.php composer.json goosialize-leads.php goosialize-leads.yaml languages/en.yaml permissions.yaml templates/phase-2d-skeleton.html.twig"
actual="$(find "$root" -type f -printf "%P\n" | LC_ALL=C sort | tr "\n" " " | sed "s/ $//")"
test "$actual" = "$expected"
test -f "$root/templates/phase-2d-skeleton.html.twig"
test -f "$root/admin-next/pages/goosialize-leads.js"
php -r '\''
$archivePrefix = "grav-plugin-goosialize-leads/";
$installRoot = "/app/www/public/user/plugins/goosialize-leads";
$zip = new ZipArchive();
if ($zip->open("/packages/goosialize-leads-0.1.0-dev.zip") !== true) throw new RuntimeException("ZIP open failed for content comparison");
$expected = [];
for ($index = 0; $index < $zip->numFiles; $index++) {
    $name = $zip->getNameIndex($index);
    if (!is_string($name) || !str_starts_with($name, $archivePrefix)) throw new RuntimeException("Unexpected archive path: " . var_export($name, true));
    if (str_ends_with($name, "/")) continue;
    $relative = substr($name, strlen($archivePrefix));
    if ($relative === "" || array_key_exists($relative, $expected)) throw new RuntimeException("Duplicate or empty archive file path: " . $relative);
    $contents = $zip->getFromIndex($index);
    if (!is_string($contents)) throw new RuntimeException("Cannot read archive file: " . $relative);
    $expected[$relative] = hash("sha256", $contents);
}
$zip->close();
$actual = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($installRoot, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    $path = $file->getPathname();
    $relative = substr($path, strlen($installRoot) + 1);
    if ($file->isLink()) throw new RuntimeException("Unexpected installed symlink: " . $relative);
    if (!$file->isFile()) continue;
    $actual[$relative] = hash_file("sha256", $path);
}
ksort($expected, SORT_STRING); ksort($actual, SORT_STRING);
foreach ($expected as $path => $hash) {
    if (!array_key_exists($path, $actual)) throw new RuntimeException("Installed file is missing: " . $path);
    if (!hash_equals($hash, $actual[$path])) throw new RuntimeException("Installed file content mismatch: " . $path);
}
foreach ($actual as $path => $hash) {
    if (!array_key_exists($path, $expected)) throw new RuntimeException("Unexpected installed regular file: " . $path);
}
'\''
find "$root" -type f -name "*.php" -exec php -l {} \; | grep -q "No syntax errors detected"
php -r '\''
define("GRAV_CLI",true); define("GRAV_REQUEST_TIME",microtime(true));
$autoload=require "/app/www/public/vendor/autoload.php"; $grav=Grav\Common\Grav::instance(["loader"=>$autoload]); $grav->initializeCli();
$plugin=Grav\Common\Plugins::getPlugin("goosialize-leads");
if (!$plugin || get_class($plugin)!=="Grav\\Plugin\\GoosializeLeadsPlugin") throw new RuntimeException("plugin discovery failed");
if ($grav["config"]->get("plugins.goosialize-leads.enabled")!==true) throw new RuntimeException("plugin disabled");
if (Grav\Plugin\GoosializeLeadsPlugin::getSubscribedEvents()!==[
    "Grav\\Events\\PermissionsRegisterEvent"=>["onRegisterPermissions",1000],
    "onApiRegisterRoutes"=>["onApiRegisterRoutes",0],
    "onApiSidebarItems"=>["onApiSidebarItems",0],
    "onApiPluginPageInfo"=>["onApiPluginPageInfo",0],
    "onApiCollectPublicRoutes"=>["onApiCollectPublicRoutes",0],
    "onRequestHandlerInit"=>["onRequestHandlerInit",98000],
    "onTwigTemplatePaths"=>["onTwigTemplatePaths",0],
    "onFormProcessed"=>["onFormProcessed",0],
]) throw new RuntimeException("unexpected plugin subscriptions");
$grav["plugins"]->init(); if ($plugin->config()===[]) throw new RuntimeException("plugin did not initialize");
$plugin->autoload();
$classes=[
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
foreach($classes as $class){if(!class_exists($class)||!(new ReflectionClass($class))->isFinal())throw new RuntimeException("Phase 3A reflection failed: ".$class);}
if(!interface_exists("Grav\\Plugin\\GoosializeLeads\\Storage\\LeadRepository"))throw new RuntimeException("Phase 3B repository interface missing");
if(!interface_exists("Grav\\Plugin\\GoosializeLeads\\Storage\\LeadReadRepository"))throw new RuntimeException("Phase 4A.1 read repository interface missing");
if(!interface_exists("Grav\\Plugin\\GoosializeLeads\\Notification\\NotificationOutbox"))throw new RuntimeException("Phase 5A outbox interface missing");
if(is_dir("/app/www/public/user/plugins/goosialize-leads/vendor"))throw new RuntimeException("vendor directory must not exist");
$plugin->onTwigTemplatePaths();
if (end($grav["twig"]->twig_paths)!=="/app/www/public/user/plugins/goosialize-leads/templates") throw new RuntimeException("Twig entry point inactive");
$event=new RocketTheme\Toolbox\Event\Event(["routes"=>new stdClass()]); $routes=$event["routes"];
$plugin->onApiRegisterRoutes($event); if ($event["routes"]!==$routes) throw new RuntimeException("route entry point mutated event");
'\''
mkdir -p user/config/plugins
printf "enabled: false\n" > user/config/plugins/goosialize-leads.yaml
php bin/grav cache --all >/dev/null
php -r '\''
define("GRAV_CLI",true); define("GRAV_REQUEST_TIME",microtime(true));
$autoload=require "/app/www/public/vendor/autoload.php"; $grav=Grav\Common\Grav::instance(["loader"=>$autoload]); $grav->initializeCli();
$plugin=Grav\Common\Plugins::getPlugin("goosialize-leads"); $grav["plugins"]->init();
if (!$plugin || $grav["config"]->get("plugins.goosialize-leads.enabled") !== false) throw new RuntimeException("disabled installed plugin state mismatch");
if (in_array("/app/www/public/user/plugins/goosialize-leads/templates",$grav["twig"]->twig_paths,true)) throw new RuntimeException("disabled installed plugin contributed Twig path");
'\''
printf "PASS_LOCAL_PACKAGE_INSTALL\nPASS_INSTALLED_PLUGIN_LOAD\n"
'

[[ "$(repository_digest)" == "${CONTENT_BEFORE}" ]] || fail 'repository content or modes changed'
[[ "$(git -C "${REPOSITORY_ROOT}" status --porcelain=v1 -z | sha256sum | awk '{print $1}')" == "${STATUS_BEFORE}" ]] || fail 'Git status changed'
[[ "$(git -C "${REPOSITORY_ROOT}" rev-parse HEAD)" == "${HEAD_BEFORE}" ]] || fail 'HEAD changed'
[[ "$(git -C "${REPOSITORY_ROOT}" branch --show-current)" == "${BRANCH_BEFORE}" ]] || fail 'branch changed'
[[ "$(docker volume ls -q | LC_ALL=C sort | sha256sum | awk '{print $1}')" == "${VOLUMES_BEFORE}" ]] || fail 'named volume set changed'
printf 'PASS_REPOSITORY_UNCHANGED digest=%s\n' "${CONTENT_BEFORE}"
printf 'PASS_PHASE_3A_PACKAGE\n'
printf 'PASS_INSTALLABLE_PLUGIN_PACKAGE\n'
