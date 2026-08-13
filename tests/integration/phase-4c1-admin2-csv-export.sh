#!/usr/bin/env bash
set -euo pipefail
# Phase 5C.1 preserves authenticated CSV export behavior.

readonly REPOSITORY_ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd -P)"
readonly IMAGE='lscr.io/linuxserver/grav:2.0.12'
readonly IMAGE_ID='sha256:702d936e25513805b57c9d009f7ff466217273415b2e55f539f3366e6377d351'

[[ "$(docker image inspect --format '{{.Id}}' "${IMAGE}")" == "${IMAGE_ID}" ]]
docker run --rm --network none \
  --mount "type=bind,src=${REPOSITORY_ROOT},dst=/source,readonly" \
  --entrypoint php "${IMAGE_ID}" /source/tests/unit/phase-4c1-admin2-csv-export.php
[[ -z "$(sort "${REPOSITORY_ROOT}/packaging/package-files.txt" | uniq -d)" ]]
grep -qx 'classes/Notification/FilesystemNotificationOutbox.php' "${REPOSITORY_ROOT}/packaging/package-files.txt"
grep -Fq "api.goosialize_leads.export" "${REPOSITORY_ROOT}/goosialize-leads.php"
grep -Fq "routes->get('/goosialize-leads/export'" "${REPOSITORY_ROOT}/goosialize-leads.php"
grep -Fq "'id' => 'export'" "${REPOSITORY_ROOT}/goosialize-leads.php"
grep -Fq "api.goosialize_leads.export" "${REPOSITORY_ROOT}/goosialize-leads.php"
! find "${REPOSITORY_ROOT}" -path '*/.git' -prune -o -type f \( -name '*.svelte' -o -name '*.css' \) -print | grep -q .

docker run --rm --network none \
  --mount "type=bind,src=${REPOSITORY_ROOT},dst=/app/www/public/user/plugins/goosialize-leads,readonly" \
  --entrypoint php "${IMAGE_ID}" -r '
chdir("/app/www/public");
define("GRAV_CLI",true); define("GRAV_REQUEST_TIME",microtime(true));
$autoload=require "vendor/autoload.php";
$grav=Grav\Common\Grav::instance(["loader"=>$autoload]); $grav->initializeCli();
$plugin=Grav\Common\Plugins::getPlugin("goosialize-leads"); $grav["plugins"]->init();
if(!$plugin)throw new RuntimeException("plugin missing");
$grav["config"]->set("plugins.goosialize-leads.admin2_index",["enabled"=>true,"timezone"=>"UTC"]);
$grav["config"]->set("plugins.goosialize-leads.admin2_csv_export",["enabled"=>true,"max_response_bytes"=>131072]);
$readOnly=new class{public function get(string $key):bool{return in_array($key,["access.api.access","access.api.goosialize_leads.read"],true);}public function authorize(string $key):bool{return $key==="api.goosialize_leads.read";}};
$exporter=new class{public function get(string $key):bool{return in_array($key,["access.api.access","access.api.goosialize_leads.read","access.api.goosialize_leads.export"],true);}public function authorize(string $key):bool{return in_array($key,["api.goosialize_leads.read","api.goosialize_leads.export"],true);}};
$noApiAccess=new class{public function get(string $key):bool{return in_array($key,["access.api.goosialize_leads.read","access.api.goosialize_leads.export"],true);}public function authorize(string $key):bool{return in_array($key,["api.goosialize_leads.read","api.goosialize_leads.export"],true);}};
$page=new RocketTheme\Toolbox\Event\Event(["plugin"=>"goosialize-leads","user"=>$readOnly]);$plugin->onApiPluginPageInfo($page);
if(in_array("export",array_column($page["definition"]["actions"]??[],"id"),true))throw new RuntimeException("read-only export action visible");
$page=new RocketTheme\Toolbox\Event\Event(["plugin"=>"goosialize-leads","user"=>$noApiAccess]);$plugin->onApiPluginPageInfo($page);
if(isset($page["definition"]))throw new RuntimeException("API access bypass");
$page=new RocketTheme\Toolbox\Event\Event(["plugin"=>"goosialize-leads","user"=>$exporter]);$plugin->onApiPluginPageInfo($page);
if(!in_array("export",array_column($page["definition"]["actions"]??[],"id"),true))throw new RuntimeException("export action hidden");
$routes=new class{public array $gets=[];public function get(string $path,array $handler):void{$this->gets[$path]=$handler;}public function post(string $path,array $handler):void{}public function patch(string $path,array $handler):void{}};
$plugin->onApiRegisterRoutes(new RocketTheme\Toolbox\Event\Event(["routes"=>$routes]));
foreach(["/goosialize-leads","/goosialize-leads/export","/goosialize-leads/notification-operations"] as $path)if(!array_key_exists($path,$routes->gets))throw new RuntimeException("route missing: ".$path);
$controller=new Grav\Plugin\GoosializeLeads\Admin\LeadsCsvExportController($grav,$grav["config"]);
$request=new Nyholm\Psr7\ServerRequest("GET","/api/v1/goosialize-leads/export");
if($controller->export($request)->getStatusCode()!==401)throw new RuntimeException("authentication gate");
$denied=$controller->export($request->withAttribute("api_user",$readOnly));
if($denied->getStatusCode()!==403||str_starts_with((string)$denied->getBody(),"\"Lead ID\""))throw new RuntimeException("ACL gate");
$response=$controller->export($request->withAttribute("api_user",$exporter));
if($response->getStatusCode()!==200
 ||$response->getHeaderLine("Content-Type")!=="text/csv; charset=utf-8"
 ||preg_match("/\\Aattachment; filename=\\\"goosialize-leads-\\d{4}-\\d{2}-\\d{2}-\\d{4}\\.csv\\\"\\z/D",$response->getHeaderLine("Content-Disposition"))!==1
 ||str_contains($response->getHeaderLine("Content-Disposition"),"latest-100")
 ||$response->getHeaderLine("Cache-Control")!=="private, no-store, max-age=0"
 ||$response->getHeaderLine("X-Content-Type-Options")!=="nosniff"
 ||(string)$response->getBody()!=="\"Lead ID\",\"Created (UTC)\",\"Name\",\"Email\",\"Phone\",\"Source\",\"Form / Resource\",\"Status\"\r\n")throw new RuntimeException("CSV response");
$grav["config"]->set("plugins.goosialize-leads.admin2_csv_export",["enabled"=>false,"max_response_bytes"=>131072]);
if($controller->export($request->withAttribute("api_user",$exporter))->getStatusCode()!==503)throw new RuntimeException("disabled gate");
echo "PASS_PHASE_4C1_AUTHENTICATED_ENDPOINT\n";
'

printf 'PASS_PHASE_4C1_NATIVE_EXPORT\n'
printf 'PASS_PHASE_4C1_EXPORT_ACL\n'
printf 'PASS_PHASE_4C1_NO_CUSTOM_COMPONENT\n'
printf 'PASS_PHASE_4C1_REGRESSIONS\n'
printf 'PASS_PHASE_4C1_PACKAGE\n'
# Phase 5C.2 preserves CSV export behavior.
