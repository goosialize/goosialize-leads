#!/usr/bin/env bash
set -euo pipefail
# Phase 5C.1 preserves the bounded read-only Admin2 index.

readonly EXPECTED_IMAGE_ID='sha256:702d936e25513805b57c9d009f7ff466217273415b2e55f539f3366e6377d351'
readonly SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
readonly REPOSITORY_ROOT="$(cd -- "${SCRIPT_DIR}/../.." && pwd -P)"
readonly GRAV_IMAGE="${GRAV_TEST_IMAGE:-}"

[[ -n "${GRAV_IMAGE}" ]] || { printf 'FAIL: GRAV_TEST_IMAGE is required\n' >&2; exit 1; }
readonly ACTUAL_IMAGE_ID="$(docker image inspect --format '{{.Id}}' "${GRAV_IMAGE}")"
[[ "${ACTUAL_IMAGE_ID}" == "${EXPECTED_IMAGE_ID}" ]] || { printf 'FAIL: image ID mismatch\n' >&2; exit 1; }

docker run --rm --network none \
    --mount "type=bind,src=${REPOSITORY_ROOT},dst=/source,readonly" \
    --entrypoint /bin/sh "${EXPECTED_IMAGE_ID}" -c '
set -eu
find /source -type f -name "*.php" -exec php -l {} \; | grep -q "No syntax errors detected"
php /source/tests/unit/phase-4a1-bounded-admin2-lead-index.php
php -r '\''
require "/app/www/public/vendor/autoload.php";
use Symfony\Component\Yaml\Yaml;
$blueprint=Yaml::parseFile("/source/admin/blueprints/goosialize-leads-index.yaml");
$field=$blueprint["form"]["fields"]["leads"]??null;
if(!is_array($field)||($field["type"]??null)!=="resource-table"||($field["endpoint"]??null)!=="/goosialize-leads"||($field["id_key"]??null)!=="id")throw new RuntimeException("resource table mismatch");
if(($field["actions"]??null)!==["clear"=>true,"refresh"=>true,"export"=>false])throw new RuntimeException("actions mismatch");
$filters=array_column($field["filters"],null,"name");
foreach(["search","status","source","date_from","date_to"] as $name)if(!isset($filters[$name]))throw new RuntimeException("filter missing");
if(isset($field["export_endpoint"],$field["page"],$field["sort"],$field["editor"]))throw new RuntimeException("forbidden declaration");
$permissions=Yaml::parseFile("/source/permissions.yaml");
if(array_keys($permissions["actions"]["api.goosialize_leads"]["actions"]??[])!==["read","export","operations"])throw new RuntimeException("permission mismatch");
$defaults=Yaml::parseFile("/source/goosialize-leads.yaml");
if(($defaults["admin2_index"]??null)!==["enabled"=>false,"timezone"=>"UTC"])throw new RuntimeException("default mismatch");
$source=file_get_contents("/source/goosialize-leads.php");
foreach(["onRegisterPermissions","onApiSidebarItems","onApiPluginPageInfo","Leads — latest 100"] as $needle)if(!str_contains($source,$needle))throw new RuntimeException("registrar mismatch");
foreach(["customElements.define","attachShadow","ShadowRoot"] as $needle)if(str_contains($source,$needle))throw new RuntimeException("custom Admin2 code");
if(glob("/source/admin-next/pages/*phase-4a1*")!==[])throw new RuntimeException("Phase 4A.1 page script exists");
$gravAutoload=require "/app/www/public/vendor/autoload.php";
define("GRAV_CLI",true); define("GRAV_REQUEST_TIME",microtime(true));
$grav=Grav\Common\Grav::instance(["loader"=>$gravAutoload]);
require "/source/autoload.php";
$config=new Grav\Common\Config\Config();
$config->set("plugins.goosialize-leads.admin2_index",["enabled"=>false,"timezone"=>"UTC"]);
$controller=new Grav\Plugin\GoosializeLeads\Admin\LeadsIndexController($grav,$config);
$request=new Nyholm\Psr7\ServerRequest("GET","/api/v1/goosialize-leads");
if($controller->index($request)->getStatusCode()!==401)throw new RuntimeException("authentication gate mismatch");
$denied=new class{public function get(string $key):bool{return false;}public function authorize(string $key):bool{return false;}};
if($controller->index($request->withAttribute("api_user",$denied))->getStatusCode()!==403)throw new RuntimeException("permission gate mismatch");
$allowed=new class{public function get(string $key):bool{return in_array($key,["access.api.access","access.api.goosialize_leads.read"],true);}public function authorize(string $key):bool{return $key==="api.goosialize_leads.read";}};
if($controller->index($request->withAttribute("api_user",$allowed))->getStatusCode()!==503)throw new RuntimeException("disabled gate mismatch");
echo "PASS_PHASE_4A1_NATIVE_RESOURCE_TABLE\n";
echo "PASS_PHASE_4A1_READ_ACL\n";
'\''
'

docker run --rm --network none \
    --mount "type=bind,src=${REPOSITORY_ROOT},dst=/app/www/public/user/plugins/goosialize-leads,readonly" \
    --entrypoint php "${EXPECTED_IMAGE_ID}" -r '
chdir("/app/www/public");
define("GRAV_CLI",true); define("GRAV_REQUEST_TIME",microtime(true));
$autoload=require "vendor/autoload.php";
$grav=Grav\Common\Grav::instance(["loader"=>$autoload]); $grav->initializeCli();
$plugin=Grav\Common\Plugins::getPlugin("goosialize-leads"); $grav["plugins"]->init();
if(!$plugin)throw new RuntimeException("plugin missing");
$grav["config"]->set("plugins.goosialize-leads.admin2_index",["enabled"=>true,"timezone"=>"UTC"]);
$allowed=new class{public function get(string $key):bool{return in_array($key,["access.api.access","access.api.goosialize_leads.read"],true);}public function authorize(string $key):bool{return $key==="api.goosialize_leads.read";}};
$sidebar=new RocketTheme\Toolbox\Event\Event(["items"=>[],"user"=>$allowed]); $plugin->onApiSidebarItems($sidebar);
if(count($sidebar["items"])!==1||($sidebar["items"][0]["authorize"]??null)!=="api.goosialize_leads.read"||!array_key_exists("badge",$sidebar["items"][0])||$sidebar["items"][0]["badge"]!==null)throw new RuntimeException("sidebar mismatch");
$page=new RocketTheme\Toolbox\Event\Event(["plugin"=>"goosialize-leads","user"=>$allowed]); $plugin->onApiPluginPageInfo($page);
if(($page["definition"]["title"]??null)!=="Leads — latest 100"||($page["definition"]["blueprint"]??null)!=="goosialize-leads-index"||($page["definition"]["actions"]??null)!==[])throw new RuntimeException("page mismatch");
$routes=new class{public array $gets=[];public function get(string $path,array $handler):void{$this->gets[]=[$path,$handler];}};
$routeEvent=new RocketTheme\Toolbox\Event\Event(["routes"=>$routes]); $plugin->onApiRegisterRoutes($routeEvent);
if(count($routes->gets)!==2||$routes->gets[0][0]!=="/goosialize-leads"||$routes->gets[1][0]!=="/goosialize-leads/notification-operations")throw new RuntimeException("provider route mismatch");
$request=(new Nyholm\Psr7\ServerRequest("GET","/api/v1/goosialize-leads"))->withAttribute("api_user",$allowed);
$controller=new Grav\Plugin\GoosializeLeads\Admin\LeadsIndexController($grav,$grav["config"]);
$response=$controller->index($request); $body=json_decode((string)$response->getBody(),true,8,JSON_THROW_ON_ERROR);
if($response->getStatusCode()!==200||($body["data"]??null)!==[]||($body["meta"]["limit"]??null)!==100)throw new RuntimeException("provider success mismatch");
$grav["config"]->set("plugins.goosialize-leads.admin2_index",["enabled"=>false,"timezone"=>"UTC"]);
$hidden=new RocketTheme\Toolbox\Event\Event(["items"=>[],"user"=>$allowed]); $plugin->onApiSidebarItems($hidden);
if($hidden["items"]!==[])throw new RuntimeException("disabled navigation visible");
$grav["config"]->set("plugins.goosialize-leads.admin2_index",["enabled"=>true,"timezone"=>"UTC"]);
$denied=new class{public function get(string $key):bool{return false;}public function authorize(string $key):bool{return false;}};
$hidden=new RocketTheme\Toolbox\Event\Event(["items"=>[],"user"=>$denied]); $plugin->onApiSidebarItems($hidden);
if($hidden["items"]!==[])throw new RuntimeException("unauthorized navigation visible");
echo "PASS_PHASE_4A1_PLUGIN_REGISTRATION\n";
'

[[ "$(wc -l < "${REPOSITORY_ROOT}/packaging/package-files.txt")" -eq 88 ]]
grep -qx 'classes/Notification/NotificationEvent.php' "${REPOSITORY_ROOT}/packaging/package-files.txt"
[[ -z "$(find "${REPOSITORY_ROOT}" -type f \( -name '*.js' -o -name '*.css' -o -name '*.svelte' \) -newer "${REPOSITORY_ROOT}/docs/PHASE_3_SECURE_CAPTURE_STORAGE_PLAN.md" -print)" ]]

printf 'PASS_PHASE_4A1_REGRESSIONS\n'
printf 'PASS_PHASE_4A1_PACKAGE\n'
# Phase 5C.2 preserves bounded Lead Index behavior.
