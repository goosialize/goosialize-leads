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
$fields=$blueprint["form"]["fields"]??null;

if(!is_array($fields))throw new RuntimeException("index fields mismatch");

$workspace=$fields["leads_workspace"]??null;

if(
    !is_array($workspace)
    || ($workspace["type"]??null)!=="leads-workspace"
)throw new RuntimeException("workspace field mismatch");

$filters=$fields["filters_panel"]["fields"]??null;
if(
    !is_array($filters)
    || array_keys($fields)!==["filters_panel","leads_workspace"]
    || ($blueprint["form"]["validation"]??null)!=="loose"
)throw new RuntimeException("native filter index form mismatch");

if(isset($fields["toolbar"]))throw new RuntimeException("presentation toolbar must not be a blueprint field");

$findField=function(array $node,string $name)use(&$findField):?array{
    foreach($node as $key=>$value){
        if($key===$name && is_array($value))return $value;
        if(is_array($value)){
            $found=$findField($value,$name);
            if($found!==null)return $found;
        }
    }
    return null;
};
foreach([
    "filters.search"=>"text",
    "filters.status"=>"select",
    "filters.source"=>"select",
    "filters.state"=>"select",
    "filters.date_from"=>"datetime",
    "filters.date_to"=>"datetime",
] as $name=>$type){
    $field=$findField($filters,$name);
    if(($field["type"]??null)!==$type)throw new RuntimeException("native filter mismatch: ".$name);
}

if(
    isset($fields["leads"])
)throw new RuntimeException("legacy resource-table declaration present");

$permissions=Yaml::parseFile("/source/permissions.yaml");

if(
    array_keys(
        $permissions["actions"]["api.goosialize_leads"]["actions"]??[]
    )!==["read","write","delete","export","operations"]
)throw new RuntimeException("permission mismatch");

$defaults=Yaml::parseFile("/source/goosialize-leads.yaml");
if(($defaults["admin2_index"]??null)!==["enabled"=>true,"timezone"=>"UTC"])throw new RuntimeException("default mismatch");
$source=file_get_contents("/source/goosialize-leads.php");
foreach([
    "onRegisterPermissions",
    "onApiRegisterRoutes",
    "onApiSidebarItems",
    "onApiPluginPageInfo",
] as $needle){
    if(
        !str_contains(
            $source,
            "function ".$needle
        )
    ){
        throw new RuntimeException(
            "registrar method missing: ".$needle
        );
    }
}
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
echo "PASS_PHASE_4A1_ADMIN2_WORKSPACE\n";
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
$sidebar=new RocketTheme\Toolbox\Event\Event(
    [
        "items"=>[],
        "user"=>$allowed,
    ]
);

$plugin->onApiSidebarItems(
    $sidebar
);

$sidebarItems=$sidebar["items"]??null;

if(
    !is_array($sidebarItems)
){
    throw new RuntimeException(
        "sidebar items mismatch"
    );
}

$leadItems=array_values(
    array_filter(
        $sidebarItems,
        static fn($item)=>
            is_array($item)
            && ($item["id"]??null)==="goosialize-leads"
    )
);

if(
    count($leadItems)!==1
){
    throw new RuntimeException(
        "Leads sidebar item count mismatch"
    );
}

$leadItem=$leadItems[0];

if(
    ($leadItem["plugin"]??null)!=="goosialize-leads"
    || ($leadItem["label"]??null)!=="Leads"
    || ($leadItem["route"]??null)!=="/plugin/goosialize-leads"
    || ($leadItem["authorize"]??null)!=="api.goosialize_leads.read"
    || !array_key_exists(
        "badge",
        $leadItem
    )
    || $leadItem["badge"]!==null
){
    throw new RuntimeException(
        "Leads sidebar mismatch"
    );
}

$page=new RocketTheme\Toolbox\Event\Event(["plugin"=>"goosialize-leads","user"=>$allowed]); $plugin->onApiPluginPageInfo($page);
$definition=$page["definition"]??null;

if(
    !is_array($definition)
    || ($definition["id"]??null)!=="goosialize-leads"
    || ($definition["plugin"]??null)!=="goosialize-leads"
    || ($definition["blueprint"]??null)!=="goosialize-leads-index"
    || ($definition["data_endpoint"]??null)!=="/goosialize-leads/filter-form-data"
    || ($definition["save_endpoint"]??null)!=="/goosialize-leads/filter-form-data"
    || array_column($definition["actions"]??[],"id")!==["refresh","reset_filters"]
){
    throw new RuntimeException(
        "page definition mismatch"
    );
}

if(($definition["title"]??null)!=="Leads"){
    throw new RuntimeException(
        "page title mismatch"
    );
}
$routes=new class{
    public array $gets=[];
    public array $posts=[];
    public array $patches=[];

    public function get(
        string $path,
        array $handler
    ):void{
        $this->gets[]=[$path,$handler];
    }

    public function post(
        string $path,
        array $handler
    ):void{
        $this->posts[]=[$path,$handler];
    }

    public function patch(
        string $path,
        array $handler
    ):void{
        $this->patches[]=[$path,$handler];
    }
};

$routeEvent=
    new RocketTheme\Toolbox\Event\Event(
        ["routes"=>$routes]
    );

$plugin->onApiRegisterRoutes(
    $routeEvent
);

$getPaths=array_column(
    $routes->gets,
    0
);

$postPaths=array_column(
    $routes->posts,
    0
);

$patchPaths=array_column(
    $routes->patches,
    0
);

foreach(
    [
        "/goosialize-leads",
        "/goosialize-leads/filter-form-data",
        "/goosialize-leads/edit/{id}/form-data",
        "/goosialize-leads/notification-operations",
    ]
    as $path
){
    if(
        !in_array(
            $path,
            $getPaths,
            true
        )
    ){
        throw new RuntimeException(
            "GET route missing: ".$path
        );
    }
}

foreach(
    [
        "/goosialize-leads/apply",
    ]
    as $path
){
    if(
        !in_array(
            $path,
            $postPaths,
            true
        )
    ){
        throw new RuntimeException(
            "POST route missing: ".$path
        );
    }
}

if(
    !in_array(
        "/goosialize-leads/edit/{id}",
        $patchPaths,
        true
    )
){
    throw new RuntimeException(
        "PATCH edit route missing"
    );
}

$request=(new Nyholm\Psr7\ServerRequest("GET","/api/v1/goosialize-leads"))->withAttribute("api_user",$allowed);
$controller=new Grav\Plugin\GoosializeLeads\Admin\LeadsIndexController($grav,$grav["config"]);
$response=$controller->index($request); $body=json_decode((string)$response->getBody(),true,8,JSON_THROW_ON_ERROR);
if($response->getStatusCode()!==200||($body["data"]??null)!==[]||($body["meta"]["limit"]??null)!==100)throw new RuntimeException("provider success mismatch");
if(($body["meta"]["capabilities"]??null)!==["write"=>false,"delete"=>false,"restore"=>false])throw new RuntimeException("read-only capabilities mismatch");
$readOnlyFilterResponse=$controller->filterFormData($request);
$readOnlyFilterBody=json_decode((string)$readOnlyFilterResponse->getBody(),true,8,JSON_THROW_ON_ERROR);
if(($readOnlyFilterBody["data"]["capabilities"]??null)!==["write"=>false,"delete"=>false,"restore"=>false])throw new RuntimeException("read-only filter capabilities mismatch");
$super=new class{public function get(string $key):bool{return false;}public function authorize(string $key):bool{return $key==="api.super";}};
$superResponse=$controller->index($request->withAttribute("api_user",$super));
$superBody=json_decode((string)$superResponse->getBody(),true,8,JSON_THROW_ON_ERROR);
if(($superBody["meta"]["capabilities"]??null)!==["write"=>true,"delete"=>true,"restore"=>true])throw new RuntimeException("super capabilities mismatch");
$superFilterResponse=$controller->filterFormData($request->withAttribute("api_user",$super));
$superFilterBody=json_decode((string)$superFilterResponse->getBody(),true,8,JSON_THROW_ON_ERROR);
if(($superFilterBody["data"]["capabilities"]??null)!==["write"=>true,"delete"=>true,"restore"=>true])throw new RuntimeException("super filter capabilities mismatch");
$writeOnly=new class{public function get(string $key):bool{return in_array($key,["access.api.access","access.api.goosialize_leads.read","access.api.goosialize_leads.write"],true);}public function authorize(string $key):bool{return in_array($key,["api.access","api.goosialize_leads.read","api.goosialize_leads.write"],true);}};
$writeResponse=$controller->index($request->withAttribute("api_user",$writeOnly));
$writeBody=json_decode((string)$writeResponse->getBody(),true,8,JSON_THROW_ON_ERROR);
if(($writeBody["meta"]["capabilities"]??null)!==["write"=>true,"delete"=>false,"restore"=>true])throw new RuntimeException("write-only capabilities mismatch");
$writeFilterResponse=$controller->filterFormData($request->withAttribute("api_user",$writeOnly));
$writeFilterBody=json_decode((string)$writeFilterResponse->getBody(),true,8,JSON_THROW_ON_ERROR);
if(($writeFilterBody["data"]["capabilities"]??null)!==["write"=>true,"delete"=>false,"restore"=>true])throw new RuntimeException("write-only filter capabilities mismatch");
$full=new class{public function get(string $key):bool{return in_array($key,["access.api.access","access.api.goosialize_leads.read","access.api.goosialize_leads.write","access.api.goosialize_leads.delete"],true);}public function authorize(string $key):bool{return in_array($key,["api.goosialize_leads.read","api.goosialize_leads.write","api.goosialize_leads.delete"],true);}};
$fullResponse=$controller->index($request->withAttribute("api_user",$full));
$fullBody=json_decode((string)$fullResponse->getBody(),true,8,JSON_THROW_ON_ERROR);
if(($fullBody["meta"]["capabilities"]??null)!==["write"=>true,"delete"=>true,"restore"=>true])throw new RuntimeException("full capabilities mismatch");
$filterResponse=$controller->filterFormData($request->withAttribute("api_user",$full));
$filterBody=json_decode((string)$filterResponse->getBody(),true,8,JSON_THROW_ON_ERROR);
if(($filterBody["data"]["capabilities"]??null)!==["write"=>true,"delete"=>true,"restore"=>true])throw new RuntimeException("filter capabilities mismatch");
$workspace=file_get_contents("/app/www/public/user/plugins/goosialize-leads/admin-next/fields/leads-workspace.js");
foreach(["capabilities?.write === true","capabilities?.delete === true","capabilities?.restore === true","circle-check","circle-dashed","trash-2"] as $needle)if(!str_contains($workspace,$needle))throw new RuntimeException("workspace capability contract missing: ".$needle);
if(str_contains($workspace,"Edit | Status | Delete")||str_contains($workspace,"fa-solid fa-pencil")||str_contains($workspace,"aria-checked"))throw new RuntimeException("obsolete workspace action contract present");
$grav["config"]->set("plugins.goosialize-leads.admin2_index",["enabled"=>false,"timezone"=>"UTC"]);
$hidden=new RocketTheme\Toolbox\Event\Event(["items"=>[],"user"=>$allowed]); $plugin->onApiSidebarItems($hidden);
if($hidden["items"]!==[])throw new RuntimeException("disabled navigation visible");
$grav["config"]->set("plugins.goosialize-leads.admin2_index",["enabled"=>true,"timezone"=>"UTC"]);
$denied=new class{public function get(string $key):bool{return false;}public function authorize(string $key):bool{return false;}};
$hidden=new RocketTheme\Toolbox\Event\Event(["items"=>[],"user"=>$denied]); $plugin->onApiSidebarItems($hidden);
if($hidden["items"]!==[])throw new RuntimeException("unauthorized navigation visible");
echo "PASS_PHASE_4A1_PLUGIN_REGISTRATION\n";
'

PACKAGE_MANIFEST="${REPOSITORY_ROOT}/packaging/package-files.txt"

[[ -f "${PACKAGE_MANIFEST}" ]]

while IFS= read -r package_file; do
    [[ -n "${package_file}" ]] || continue
    [[ -f "${REPOSITORY_ROOT}/${package_file}" ]] || {
        printf 'FAIL: package source missing: %s\n' "${package_file}" >&2
        exit 1
    }
done < "${PACKAGE_MANIFEST}"

[[ -z "$(sort "${PACKAGE_MANIFEST}" | uniq -d)" ]]

for required in \
    'admin-next/fields/leads-workspace.js' \
    'classes/Admin/LeadEditController.php' \
    'classes/Admin/LeadMutationController.php' \
    'classes/Storage/FilesystemLeadMetadataRepository.php' \
    'classes/Notification/NotificationEvent.php'
do
    grep -qx "${required}" "${PACKAGE_MANIFEST}"
done

[[ -z "$(find "${REPOSITORY_ROOT}/admin-next/pages" -type f -name '*phase-4a1*' -print 2>/dev/null || true)" ]]

printf 'PASS_PHASE_4A1_REGRESSIONS\n'
printf 'PASS_PHASE_4A1_PACKAGE\n'
# Phase 5C.2 preserves bounded Lead Index behavior.
