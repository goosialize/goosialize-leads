#!/usr/bin/env bash
set -euo pipefail

grep -Fq 'notifications:' "$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd -P)/goosialize-leads.yaml"

readonly EXPECTED_IMAGE_ID='sha256:702d936e25513805b57c9d009f7ff466217273415b2e55f539f3366e6377d351'
readonly SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
readonly REPOSITORY_ROOT="$(cd -- "${SCRIPT_DIR}/../.." && pwd -P)"
readonly FORMS_FIXTURE='/home/goosialize/projects/local-docker/grav/goosialize-v2-staging/www/user/plugins/form'
readonly GRAV_IMAGE="${GRAV_TEST_IMAGE:-}"
readonly TEMP_ROOT="$(mktemp -d /tmp/goosialize-phase3c1.XXXXXX)"

fail() { printf 'FAIL: %s\n' "$*" >&2; exit 1; }
cleanup() { rm -rf -- "${TEMP_ROOT}"; }
trap cleanup EXIT HUP INT TERM

[[ -n "${GRAV_IMAGE}" ]] || fail 'GRAV_TEST_IMAGE is required'
readonly ACTUAL_IMAGE_ID="$(docker image inspect --format '{{.Id}}' "${GRAV_IMAGE}")"
[[ "${ACTUAL_IMAGE_ID}" == "${EXPECTED_IMAGE_ID}" ]] || fail 'image ID mismatch'

for mode in present absent
do
    setup=':'
    mounts=(
        --mount "type=bind,src=${REPOSITORY_ROOT},dst=/app/www/public/user/plugins/goosialize-leads,readonly"
    )
    if [[ "${mode}" == present ]]; then
        mounts+=(--mount "type=bind,src=${FORMS_FIXTURE},dst=/app/www/public/user/plugins/form,readonly")
    else
        setup='mv /app/www/public/user/plugins/form /tmp/form-plugin'
    fi
    docker run --rm --network none "${mounts[@]}" --entrypoint /bin/sh "${EXPECTED_IMAGE_ID}" -c "
set -eu
${setup}
cd /app/www/public
php -r '
define(\"GRAV_CLI\",true); define(\"GRAV_REQUEST_TIME\",microtime(true));
\$autoload=require \"/app/www/public/vendor/autoload.php\";
\$grav=Grav\\Common\\Grav::instance([\"loader\"=>\$autoload]); \$grav->initializeCli();
\$plugin=Grav\\Common\\Plugins::getPlugin(\"goosialize-leads\");
if (!\$plugin) throw new RuntimeException(\"plugin discovery failed\");
\$plugin->autoload();
if (!class_exists(\"Grav\\\\Plugin\\\\GoosializeLeads\\\\Http\\\\FormsLeadCaptureAdapter\")) throw new RuntimeException(\"adapter load failed\");
\$expected=[\"Grav\\\\Events\\\\PermissionsRegisterEvent\"=>[\"onRegisterPermissions\",1000],\"onApiRegisterRoutes\"=>[\"onApiRegisterRoutes\",0],\"onApiSidebarItems\"=>[\"onApiSidebarItems\",0],\"onApiPluginPageInfo\"=>[\"onApiPluginPageInfo\",0],\"onApiCollectPublicRoutes\"=>[\"onApiCollectPublicRoutes\",0],\"onRequestHandlerInit\"=>[\"onRequestHandlerInit\",98000],\"onTwigTemplatePaths\"=>[\"onTwigTemplatePaths\",0],\"onFormProcessed\"=>[\"onFormProcessed\",0]];
if (\$plugin::getSubscribedEvents()!==\$expected) throw new RuntimeException(\"subscription mismatch\");
'
"
    printf 'PASS_PHASE_3C1_FORMS_%s_LOAD\n' "${mode^^}"
done

cat > "${TEMP_ROOT}/adapter.php" <<'PHP'
<?php
declare(strict_types=1);

namespace Grav\Plugin\Form {
    final class Form {
        public string $status = '';
        public ?string $message = null;
        public bool $xhr_submit = false;
        public function __construct(
            private array $values,
            private string $name = 'contact',
            private string $id = '0123456789abcdefghij'
        ) {}
        public function value($name = null, $fallback = false): mixed { return $name === null ? $this->values : ($this->values[$name] ?? $fallback); }
        public function getFormName(): string { return $this->name; }
        public function getUniqueId(): string { return $this->id; }
        public function setMessage($message, $type = 'error'): void { $this->status = 'error'; $this->message = $message; }
    }
}
namespace {
    require '/app/www/public/vendor/autoload.php';
    require '/source/autoload.php';

    use Grav\Plugin\Form\Form;
    use Grav\Plugin\GoosializeLeads\Application\LeadCaptureService;
    use Grav\Plugin\GoosializeLeads\Application\LeadPersistenceCoordinator;
    use Grav\Plugin\GoosializeLeads\Http\FormsLeadCaptureAdapter;
    use Grav\Plugin\GoosializeLeads\Security\IdempotencyKeyRing;
    use Grav\Plugin\GoosializeLeads\Storage\LeadRepository;
    use Grav\Plugin\GoosializeLeads\Storage\PersistenceRequest;
    use Grav\Plugin\GoosializeLeads\Storage\PersistenceResult;
    use Grav\Plugin\GoosializeLeads\Validation\LeadInputValidator;
    use Grav\Plugin\GoosializeLeads\Validation\LeadNormalizer;
    use RocketTheme\Toolbox\Event\Event;

    function ok(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
    function values(): array {
        return [
            'full_name'=>'Synthetic Lead','email'=>'lead@example.test','phone'=>'+35722000000',
            'company'=>null,'message'=>'Phase 3C.1 Forms fixture','resource_id'=>null,
            'source_path'=>null,'campaign'=>null,'consent'=>['granted'=>true],
        ];
    }
    $repository = new class implements LeadRepository {
        public int $calls = 0;
        public string $mode = 'created';
        public function persist(PersistenceRequest $request): PersistenceResult {
            $this->calls++;
            return match($this->mode) {
                'created'=>PersistenceResult::created($request->record()),
                'replayed'=>PersistenceResult::replayed($request->record()->toArray()),
                'conflict'=>PersistenceResult::failure('idempotency_conflict'),
                'storage'=>PersistenceResult::failure('storage_unavailable'),
                default=>throw new RuntimeException('bad fixture mode'),
            };
        }
    };
    $ring = new IdempotencyKeyRing(1, [1=>base64_encode(str_repeat('K',32))]);
    $service = new LeadCaptureService(new LeadPersistenceCoordinator(
        new LeadInputValidator(new LeadNormalizer()), $repository, $ring
    ), $ring);
    $configuration = [
        'enabled'=>true,'forms'=>['contact'],'source'=>'website','locale'=>null,
        'consent_version'=>'privacy-v1','success_redirect'=>'/thanks',
    ];
    $adapter = new FormsLeadCaptureAdapter(
        $service, $configuration,
        static fn(int $n):string=>str_repeat("\x01",$n),
        static fn():DateTimeInterface=>new DateTimeImmutable('2026-07-27T10:20:30.123456Z')
    );
    $form = new Form(values());
    $event = new Event(['form'=>$form,'action'=>'goosialize_leads_capture','params'=>true]);
    $adapter->process($event);
    ok($repository->calls===1 && $form->status==='success', 'enabled capture failed');
    ok($form->message==='PLUGIN_GOOSIALIZE_LEADS.FORMS_CAPTURE_SUCCESS', 'success message failed');
    ok($event['redirect']==='/thanks' && $event['redirect_code']===303 && $event->isPropagationStopped(), 'redirect failed');

    $repository->mode='replayed';
    $replayForm=new Form(values());
    $replayEvent=new Event(['form'=>$replayForm,'action'=>'goosialize_leads_capture','params'=>[]]);
    $adapter->process($replayEvent);
    ok($replayForm->status==='success' && $repository->calls===2, 'replay failed');

    $repository->mode='conflict';
    $conflictForm=new Form(values());
    $conflictEvent=new Event(['form'=>$conflictForm,'action'=>'goosialize_leads_capture','params'=>true]);
    $adapter->process($conflictEvent);
    ok($conflictForm->message==='PLUGIN_GOOSIALIZE_LEADS.FORMS_CAPTURE_UNAVAILABLE' && !isset($conflictEvent['redirect']), 'conflict mapping failed');

    $badForm=new Form(values(),'contact','BAD');
    $badEvent=new Event(['form'=>$badForm,'action'=>'goosialize_leads_capture','params'=>true]);
    $adapter->process($badEvent);
    ok($badForm->message==='PLUGIN_GOOSIALIZE_LEADS.FORMS_CAPTURE_UNAVAILABLE' && $repository->calls===3, 'invalid ID failed');

    $invalidForm=new Form(array_replace(values(),['message'=>null,'resource_id'=>null]));
    $invalidEvent=new Event(['form'=>$invalidForm,'action'=>'goosialize_leads_capture','params'=>true]);
    $adapter->process($invalidEvent);
    ok($invalidForm->message==='PLUGIN_GOOSIALIZE_LEADS.FORMS_CAPTURE_INVALID' && $repository->calls===3, 'validation mapping failed');

    foreach ([['other','goosialize_leads_capture'],['contact','other_action']] as [$name,$action]) {
        $ignored=new Form(values(),$name);
        $ignoredEvent=new Event(['form'=>$ignored,'action'=>$action,'params'=>true]);
        $adapter->process($ignoredEvent);
        ok($ignored->status==='' && $repository->calls===3, 'ineligible event handled');
    }
    $xhr=new Form(values()); $xhr->xhr_submit=true;
    $xhrEvent=new Event(['form'=>$xhr,'action'=>'goosialize_leads_capture','params'=>true]);
    $adapter->process($xhrEvent);
    ok($xhr->message==='PLUGIN_GOOSIALIZE_LEADS.FORMS_CAPTURE_UNAVAILABLE' && $repository->calls===3, 'XHR was not rejected');

    $disabled = new FormsLeadCaptureAdapter($service, array_replace($configuration,['enabled'=>false]), static fn(int $n):string=>str_repeat('x',$n), static fn():DateTimeInterface=>new DateTimeImmutable());
    $disabledForm=new Form(values());
    $disabled->process(new Event(['form'=>$disabledForm,'action'=>'goosialize_leads_capture','params'=>true]));
    ok($disabledForm->status==='' && $repository->calls===3, 'disabled integration was active');

    foreach ([
        array_replace($configuration,['forms'=>['contact','contact']]),
        array_replace($configuration,['success_redirect'=>'https://example.test/']),
        array_replace($configuration,['success_redirect'=>'/a//b']),
    ] as $invalidConfiguration) {
        try {
            new FormsLeadCaptureAdapter($service,$invalidConfiguration,static fn(int $n):string=>str_repeat('x',$n),static fn():DateTimeInterface=>new DateTimeImmutable());
            throw new RuntimeException('invalid configuration accepted');
        } catch (InvalidArgumentException) {}
    }
    echo "PASS_PHASE_3C1_NATIVE_FORMS\n";
    echo "PASS_PHASE_3C1_DISABLED_INERT\n";
}
PHP

cat > "${TEMP_ROOT}/nonce.php" <<'PHP'
<?php
declare(strict_types=1);

chdir('/app/www/public');
require '/app/www/public/vendor/autoload.php';
require '/forms-fixture/classes/Form.php';
require '/source/autoload.php';

use Grav\Common\Data\Data;
use Grav\Common\Grav;
use Grav\Common\Utils;
use Grav\Plugin\Form\Form;
use Grav\Plugin\GoosializeLeads\Application\LeadCaptureService;
use Grav\Plugin\GoosializeLeads\Application\LeadPersistenceCoordinator;
use Grav\Plugin\GoosializeLeads\Http\FormsLeadCaptureAdapter;
use Grav\Plugin\GoosializeLeads\Security\IdempotencyKeyRing;
use Grav\Plugin\GoosializeLeads\Storage\LeadRepository;
use Grav\Plugin\GoosializeLeads\Storage\PersistenceRequest;
use Grav\Plugin\GoosializeLeads\Storage\PersistenceResult;
use Grav\Plugin\GoosializeLeads\Validation\LeadInputValidator;
use Grav\Plugin\GoosializeLeads\Validation\LeadNormalizer;
use RocketTheme\Toolbox\Event\Event;

function nonceCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

final class FixtureFlash
{
    public function exists(): bool { return false; }
    public function delete(): void {}
}

final class LifecycleForm extends Form
{
    private FixtureFlash $fixtureFlash;

    public static function create(): self
    {
        $reflection = new ReflectionClass(self::class);
        /** @var self $form */
        $form = $reflection->newInstanceWithoutConstructor();
        $form->fixtureFlash = new FixtureFlash();
        $values = [
            'items' => [
                'fields' => [],
                'process' => ['goosialize_leads_capture' => true],
            ],
            'data' => new Data(),
            'values' => new Data(),
            'name' => 'contact',
            'uniqueid' => '0123456789abcdefghij',
            'messages' => [],
            'files' => [],
        ];
        $parent = new ReflectionClass(Form::class);
        foreach ($values as $property => $value) {
            $parent->getProperty($property)->setValue($form, $value);
        }
        return $form;
    }

    public function getFlash(): FixtureFlash
    {
        return $this->fixtureFlash;
    }

    public function legacyUploads(): void {}
    public function copyFiles(): void {}
}

define('GRAV_CLI', true);
define('GRAV_REQUEST_TIME', microtime(true));
$loader = require '/app/www/public/vendor/autoload.php';
$grav = Grav::instance(['loader' => $loader]);
$grav->initializeCli();

$repository = new class implements LeadRepository {
    public int $calls = 0;
    public function persist(PersistenceRequest $request): PersistenceResult
    {
        $this->calls++;
        return PersistenceResult::created($request->record());
    }
};
$ring = new IdempotencyKeyRing(1, [1 => base64_encode(str_repeat('K', 32))]);
$adapter = new FormsLeadCaptureAdapter(
    new LeadCaptureService(
        new LeadPersistenceCoordinator(new LeadInputValidator(new LeadNormalizer()), $repository, $ring),
        $ring
    ),
    [
        'enabled' => true,
        'forms' => ['contact'],
        'source' => 'website',
        'locale' => null,
        'consent_version' => 'privacy-v1',
        'success_redirect' => '/thanks',
    ],
    static fn (int $length): string => str_repeat("\x01", $length),
    static fn (): DateTimeInterface => new DateTimeImmutable('2026-07-27T10:20:30.123456Z')
);
$processed = 0;
foreach ($grav['events']->getListeners('onFormProcessed') as $listener) {
    $grav['events']->removeListener('onFormProcessed', $listener);
}
$grav['events']->addListener('onFormProcessed', static function (Event $event) use (&$processed, $adapter): void {
    if (($event['action'] ?? null) === 'goosialize_leads_capture') {
        $processed++;
        $adapter->process($event);
    }
});

$submitted = [
    'full_name' => 'Synthetic Lead',
    'email' => 'lead@example.test',
    'phone' => '+35722000000',
    'company' => null,
    'message' => 'Phase 3C.1 Forms fixture',
    'resource_id' => null,
    'source_path' => null,
    'campaign' => null,
    'consent' => ['granted' => true],
];

$setPost = static function (array $post) use ($grav): void {
    $_POST = $post;
    (new ReflectionProperty($grav['uri'], 'post'))->setValue($grav['uri'], null);
};

$setPost(['data' => $submitted, '__unique_form_id__' => '0123456789abcdefghij']);
$missing = LifecycleForm::create();
$missing->post();
nonceCheck($missing->status === 'error', 'missing nonce was not rejected');
nonceCheck($processed === 0 && $repository->calls === 0, 'missing nonce reached persistence');

$setPost([
    'data' => $submitted,
    '__unique_form_id__' => '0123456789abcdefghij',
    'form-nonce' => 'invalid',
]);
$invalid = LifecycleForm::create();
$invalid->post();
nonceCheck($invalid->status === 'error', 'invalid nonce was not rejected');
nonceCheck($processed === 0 && $repository->calls === 0, 'invalid nonce reached persistence');

$setPost([
    'data' => $submitted,
    '__unique_form_id__' => '0123456789abcdefghij',
    'form-nonce' => Utils::getNonce('form'),
]);
$valid = LifecycleForm::create();
try {
    $valid->post();
} catch (RuntimeException $error) {
    nonceCheck(
        $error->getMessage() === 'Grav::close() called in CLI context (redirect to /thanks)',
        'unexpected valid-nonce redirect outcome'
    );
}
nonceCheck($processed === 1 && $repository->calls === 1, 'valid nonce did not capture exactly once');
nonceCheck($valid->status === 'success', 'valid nonce did not produce Forms success');
nonceCheck($valid->message === 'PLUGIN_GOOSIALIZE_LEADS.FORMS_CAPTURE_SUCCESS', 'valid nonce success message mismatch');

echo "PASS_PHASE_3C1_NONCE_POSTS\n";
PHP

docker run --rm --network none \
    --mount "type=bind,src=${REPOSITORY_ROOT},dst=/source,readonly" \
    --mount "type=bind,src=${FORMS_FIXTURE},dst=/forms-fixture,readonly" \
    --mount "type=bind,src=${TEMP_ROOT},dst=/fixtures,readonly" \
    --entrypoint /bin/sh "${EXPECTED_IMAGE_ID}" -c '
set -eu
test "$(php -r '\''require "/app/www/public/vendor/autoload.php";$b=Symfony\Component\Yaml\Yaml::parseFile("/forms-fixture/blueprints.yaml");echo $b["version"]??"";'\'' 2>/dev/null || true)" = "9.1.14"
grep -q "Utils::verifyNonce" /forms-fixture/classes/Form.php
grep -q "onFormProcessed" /forms-fixture/classes/Form.php
grep -q "getUniqueId" /forms-fixture/form.php
grep -q "getFormName" /forms-fixture/form.php
nonce_line="$(grep -n "Utils::verifyNonce" /forms-fixture/classes/Form.php | head -1 | cut -d: -f1)"
validation_line="$(grep -n "onFormPrepareValidation" /forms-fixture/classes/Form.php | tail -1 | cut -d: -f1)"
processed_line="$(grep -n "onFormProcessed" /forms-fixture/classes/Form.php | tail -1 | cut -d: -f1)"
test "$nonce_line" -lt "$validation_line"
test "$validation_line" -lt "$processed_line"
grep -q "return;" /forms-fixture/classes/Form.php
grep -q "honeypot" /forms-fixture/form.php
grep -Rq "form-nonce" /forms-fixture/templates
printf "PASS_PHASE_3C1_NONCE_LIFECYCLE\n"
php /fixtures/nonce.php
php /fixtures/adapter.php
'

unit_output="$(docker run --rm --network none \
    --mount "type=bind,src=${REPOSITORY_ROOT},dst=/source,readonly" \
    --entrypoint php "${EXPECTED_IMAGE_ID}" /source/tests/unit/phase-3c-forms-capture.php)"
printf '%s\n' "${unit_output}"
grep -q '^PASS_PHASE_3C1_SHARED_SERVICE$' <<<"${unit_output}" || fail 'shared-service marker missing'

phase3b_output="$(GRAV_TEST_IMAGE="${GRAV_IMAGE}" "${SCRIPT_DIR}/phase-3b-secure-storage.sh")"
printf '%s\n' "${phase3b_output}"
grep -q '^PASS_PHASE_3B_ATOMIC_STORAGE$' <<<"${phase3b_output}" || fail 'Phase 3B regression missing'
grep -q '^PASS_PHASE_2D_REGRESSION$' <<<"${phase3b_output}" || fail 'Phase 2D regression missing'
printf 'PASS_PHASE_3C1_PACKAGE\n'
