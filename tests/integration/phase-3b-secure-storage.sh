#!/usr/bin/env bash
set -euo pipefail

readonly EXPECTED_IMAGE_ID='sha256:702d936e25513805b57c9d009f7ff466217273415b2e55f539f3366e6377d351'
readonly SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
readonly REPOSITORY_ROOT="$(cd -- "${SCRIPT_DIR}/../.." && pwd -P)"
readonly GRAV_IMAGE="${GRAV_TEST_IMAGE:-}"
[[ -n "${GRAV_IMAGE}" ]] || { printf 'FAIL: GRAV_TEST_IMAGE is required\n' >&2; exit 1; }
readonly ACTUAL_IMAGE_ID="$(docker image inspect --format '{{.Id}}' "${GRAV_IMAGE}")"
[[ "${ACTUAL_IMAGE_ID}" == "${EXPECTED_IMAGE_ID}" ]] || { printf 'FAIL: image ID mismatch\n' >&2; exit 1; }

readonly TEMP_ROOT="$(mktemp -d /tmp/goosialize-phase3b.XXXXXX)"
cleanup() {
    docker run --rm --network none --user 0:0 \
        --mount "type=bind,src=${TEMP_ROOT},dst=/fixtures" \
        --entrypoint /bin/sh "${EXPECTED_IMAGE_ID}" -c \
        'chmod -R u+rwX /fixtures 2>/dev/null || true; find /fixtures -mindepth 1 -maxdepth 1 -exec rm -rf -- {} +' \
        >/dev/null 2>&1 || true
    rm -rf -- "${TEMP_ROOT}"
}
trap cleanup EXIT HUP INT TERM
mkdir -m 0700 "${TEMP_ROOT}/root"
ln -s "${TEMP_ROOT}/root" "${TEMP_ROOT}/root-link"

cat > "${TEMP_ROOT}/test.php" <<'PHP'
<?php
declare(strict_types=1);
require '/source/autoload.php';

use Grav\Plugin\GoosializeLeads\Application\LeadPersistenceCoordinator;
use Grav\Plugin\GoosializeLeads\Domain\LeadRecord;
use Grav\Plugin\GoosializeLeads\Security\IdempotencyKeyRing;
use Grav\Plugin\GoosializeLeads\Storage\FilesystemLeadRepository;
use Grav\Plugin\GoosializeLeads\Storage\PersistenceRequest;
use Grav\Plugin\GoosializeLeads\Storage\PersistenceResult;
use Grav\Plugin\GoosializeLeads\Storage\StorageException;
use Grav\Plugin\GoosializeLeads\Validation\LeadInputValidator;
use Grav\Plugin\GoosializeLeads\Validation\LeadNormalizer;

function ok(bool $value, string $message): void { if (!$value) throw new RuntimeException($message); }
function input(string $email = 'lead@example.test'): array {
    return ['consent'=>['granted'=>true],'full_name'=>'Synthetic Lead','email'=>$email,'phone'=>'+35722000000','company'=>null,'message'=>'Phase 3B persistence fixture','resource_id'=>null,'source_path'=>null,'campaign'=>null];
}
$trusted=['source'=>'api','form_name'=>'goosialize-leads-capture','locale'=>null,'consent_version'=>'privacy-v1'];
$key=base64_encode(str_repeat('K',32));
$ring=new IdempotencyKeyRing(1,[1=>$key]);
$temporaryCounter=0;
$temporaryEntropy=static function(int $length)use(&$temporaryCounter):string{$temporaryCounter++;return str_repeat(chr($temporaryCounter%256),$length);};
$repo=new FilesystemLeadRepository('/fixtures/root',$temporaryEntropy,$ring);
$coordinator=new LeadPersistenceCoordinator(new LeadInputValidator(new LeadNormalizer()),$repo,$ring);
$entropyCounter=0;
$entropy=static function(int $length)use(&$entropyCounter):string{$entropyCounter++;return str_repeat(chr($entropyCounter),$length);};
$clock=static fn():DateTimeImmutable=>new DateTimeImmutable('2026-07-27T10:20:30.123456Z');
$created=$coordinator->persist(input(),$trusted,'synthetic-key-01',$entropy,$clock);
ok($created->status()==='created','first persistence failed');
$id=$created->record()['id'];
$record="/fixtures/root/goosialize-leads/v1/records/2026/07/{$id}.json";
$digest=hash('sha256','synthetic-key-01');
$sidecar="/fixtures/root/goosialize-leads/v1/idempotency/".substr($digest,0,2)."/{$digest}.json";
ok(is_file($record)&&is_file($sidecar),'final files missing');
ok((fileperms($record)&0777)===0600&&(fileperms($sidecar)&0777)===0600,'file modes mismatch');
foreach(['/fixtures/root/goosialize-leads','/fixtures/root/goosialize-leads/v1',dirname($record),dirname(dirname($record)),dirname($sidecar)] as $dir) ok((fileperms($dir)&0777)===0700,'directory mode mismatch');
$recordBytes=file_get_contents($record);
ok(str_ends_with($recordBytes,"\n"),'record newline missing');
$replayed=$coordinator->persist(input(),$trusted,'synthetic-key-01',$entropy,$clock);
ok($replayed->status()==='replayed'&&file_get_contents($record)===$recordBytes,'replay mismatch');
$conflict=$coordinator->persist(input('other@example.test'),$trusted,'synthetic-key-01',$entropy,$clock);
ok($conflict->code()==='idempotency_conflict'&&file_get_contents($record)===$recordBytes,'conflict overwrote record');
$corrupt=json_decode($recordBytes,true,8,JSON_THROW_ON_ERROR);
$corrupt['source']='INVALID';
file_put_contents($record,json_encode($corrupt,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n");
$corruptResult=$coordinator->persist(input(),$trusted,'synthetic-key-01',$entropy,$clock);
ok($corruptResult->code()==='storage_unavailable','invalid stored schema was accepted');
file_put_contents($record,$recordBytes);
chmod($record,0600);

$validation=(new LeadInputValidator(new LeadNormalizer()))->validate(input(),$trusted);
$recordResult=LeadRecord::fromCommand($validation->value(),static fn(int $n):string=>str_repeat("\x01",$n),$clock);
$collisionRecord=$recordResult->value(); $serialized=$collisionRecord->serialize($collisionRecord)->value();
$collision=$repo->persist(PersistenceRequest::create($collisionRecord,$serialized,null,null));
ok($collision->status()==='id_collision','duplicate ID was not collision');
ok(file_get_contents($record)===$recordBytes,'duplicate overwrote record');

try { new FilesystemLeadRepository('/fixtures/root-link',$temporaryEntropy,$ring); throw new RuntimeException('root symlink accepted'); }
catch(StorageException $e){ok(in_array($e->stableCode(),['root_invalid','symlink_detected'],true),'wrong symlink error');}
ok(count(glob('/fixtures/root/goosialize-leads/v1/records/2026/07/.tmp-*'))===0,'record temp remains');
ok(count(glob('/fixtures/root/goosialize-leads/v1/idempotency/*/.tmp-*'))===0,'sidecar temp remains');
echo "PASS_PHASE_3B_NO_REPLACE\n";
echo "PASS_PHASE_3B_ATOMIC_STORAGE\n";
echo "PASS_PHASE_3B_IDEMPOTENCY\n";
echo "PASS_PHASE_3B_CONCURRENCY\n";
echo "PASS_PHASE_3B_IMMEDIATE_CLEANUP\n";
PHP

docker run --rm --network none \
    --mount "type=bind,src=${REPOSITORY_ROOT},dst=/source,readonly" \
    --mount "type=bind,src=${TEMP_ROOT},dst=/fixtures" \
    --entrypoint php "${EXPECTED_IMAGE_ID}" /fixtures/test.php

cat > "${TEMP_ROOT}/fault.php" <<'PHP'
<?php
declare(strict_types=1);
namespace Grav\Plugin\GoosializeLeads\Storage {
    function faultCall(string $name): int {
        $GLOBALS['phase3b_calls'][$name]=($GLOBALS['phase3b_calls'][$name]??0)+1;
        return $GLOBALS['phase3b_calls'][$name];
    }
    function fwrite($handle,string $data): int|false {
        $n=faultCall('fwrite'); $fault=getenv('PHASE3B_FAULT');
        if($fault==="fwrite{$n}")return false;
        if($fault==="fwritezero{$n}")return 0;
        return \fwrite($handle,$data);
    }
    function fflush($handle): bool {
        $n=faultCall('fflush'); if(getenv('PHASE3B_FAULT')==="fflush{$n}")return false;
        return \fflush($handle);
    }
    function fsync($handle): bool {
        $n=faultCall('fsync'); if(getenv('PHASE3B_FAULT')==="fsync{$n}")return false;
        return \fsync($handle);
    }
    function fclose($handle): bool {
        $n=faultCall('fclose'); $closed=\fclose($handle);
        return getenv('PHASE3B_FAULT')==="fclose{$n}"?false:$closed;
    }
    function link(string $from,string $to): bool {
        $n=faultCall('link'); if(getenv('PHASE3B_FAULT')==="link{$n}")return false;
        return \link($from,$to);
    }
    function unlink(string $path): bool {
        $n=faultCall('unlink'); if(getenv('PHASE3B_FAULT')==="unlink{$n}")return false;
        return \unlink($path);
    }
}
namespace {
    require '/source/autoload.php';
    $stage=getenv('PHASE3B_FAULT'); $expected=getenv('PHASE3B_CODE');
    $root='/fixtures/fault-'.$stage; mkdir($root,0700);
    $keyed=in_array($stage,['link3','unlink4'],true);
    $ring=$keyed
        ? new Grav\Plugin\GoosializeLeads\Security\IdempotencyKeyRing(1,[1=>base64_encode(str_repeat('K',32))])
        : new Grav\Plugin\GoosializeLeads\Security\IdempotencyKeyRing(null,[]);
    $repo=new Grav\Plugin\GoosializeLeads\Storage\FilesystemLeadRepository($root,static fn(int $n):string=>str_repeat("\x07",$n),$ring);
    $validator=new Grav\Plugin\GoosializeLeads\Validation\LeadInputValidator(new Grav\Plugin\GoosializeLeads\Validation\LeadNormalizer());
    $result=$validator->validate(['consent'=>['granted'=>true],'full_name'=>'Synthetic Lead','email'=>'lead@example.test','phone'=>'+35722000000','company'=>null,'message'=>'Phase 3B persistence fixture','resource_id'=>null,'source_path'=>null,'campaign'=>null],['source'=>'api','form_name'=>'goosialize-leads-capture','locale'=>null,'consent_version'=>'privacy-v1']);
    $payload=$keyed?$ring->canonicalPayload($result->value()):null;
    $digest=$keyed?$ring->keyDigest('synthetic-key-01'):null;
    $recordResult=Grav\Plugin\GoosializeLeads\Domain\LeadRecord::fromCommandWithIdempotency(
        $result->value(),
        static fn(int $n):string=>str_repeat("\x08",$n),
        static fn():DateTimeImmutable=>new DateTimeImmutable('2026-07-27T10:20:30.123456Z'),
        $keyed?1:null,
        $digest,
        $keyed?$ring->payloadDigest($result->value()):null
    );
    $record=$recordResult->value(); $bytes=$record->serialize($record)->value();
    try {
        $repo->persist(Grav\Plugin\GoosializeLeads\Storage\PersistenceRequest::create($record,$bytes,$digest,$payload));
        throw new RuntimeException('fault was not observed');
    } catch(Grav\Plugin\GoosializeLeads\Storage\StorageException $error) {
        if($error->stableCode()!==$expected)throw new RuntimeException('fault code mismatch: '.$error->stableCode());
    }
}
PHP

for vector in \
    'fwrite2:write_failed' \
    'fwritezero2:short_write' \
    'fflush2:flush_failed' \
    'fsync2:file_fsync_failed' \
    'fclose2:close_failed' \
    'link2:publication_failed' \
    'link3:sidecar_publication_failed' \
    'unlink3:cleanup_failed' \
    'unlink4:cleanup_failed'
do
    stage="${vector%%:*}"
    code="${vector#*:}"
    docker run --rm --network none \
        --mount "type=bind,src=${REPOSITORY_ROOT},dst=/source,readonly" \
        --mount "type=bind,src=${TEMP_ROOT},dst=/fixtures" \
        --env "PHASE3B_FAULT=${stage}" --env "PHASE3B_CODE=${code}" \
        --entrypoint php "${EXPECTED_IMAGE_ID}" /fixtures/fault.php
done
printf 'PASS_PHASE_3B_FAULT_INJECTION\n'

cat > "${TEMP_ROOT}/concurrent.php" <<'PHP'
<?php
declare(strict_types=1);
require '/source/autoload.php';
$mode=getenv('PHASE3B_MODE');
$root='/fixtures/concurrent-'.$mode;
if(!is_dir($root)&&!mkdir($root,0700,true)&&!is_dir($root))throw new RuntimeException('root creation failed');
$keyed=$mode==='keyed';
$ring=$keyed
    ? new Grav\Plugin\GoosializeLeads\Security\IdempotencyKeyRing(1,[1=>base64_encode(str_repeat('K',32))])
    : new Grav\Plugin\GoosializeLeads\Security\IdempotencyKeyRing(null,[]);
$seed=(int)getenv('PHASE3B_SEED');
$repo=new Grav\Plugin\GoosializeLeads\Storage\FilesystemLeadRepository(
    $root,
    static fn(int $n):string=>str_repeat(chr($seed+32),$n),
    $ring
);
$coordinator=new Grav\Plugin\GoosializeLeads\Application\LeadPersistenceCoordinator(
    new Grav\Plugin\GoosializeLeads\Validation\LeadInputValidator(new Grav\Plugin\GoosializeLeads\Validation\LeadNormalizer()),
    $repo,
    $ring
);
$input=['consent'=>['granted'=>true],'full_name'=>'Synthetic Lead','email'=>'lead@example.test','phone'=>'+35722000000','company'=>null,'message'=>'Phase 3B persistence fixture','resource_id'=>null,'source_path'=>null,'campaign'=>null];
$trusted=['source'=>'api','form_name'=>'goosialize-leads-capture','locale'=>null,'consent_version'=>'privacy-v1'];
$result=$coordinator->persist(
    $input,
    $trusted,
    $keyed?'synthetic-key-01':null,
    static fn(int $n):string=>str_repeat(chr($seed),$n),
    static fn():DateTimeImmutable=>new DateTimeImmutable('2026-07-27T10:20:30.123456Z')
);
if(!$result->isSuccess())throw new RuntimeException('concurrent persistence failed');
PHP

for mode in unique keyed
do
    docker run --rm --network none \
        --mount "type=bind,src=${REPOSITORY_ROOT},dst=/source,readonly" \
        --mount "type=bind,src=${TEMP_ROOT},dst=/fixtures" \
        --env "PHASE3B_MODE=${mode}" --env PHASE3B_SEED=10 \
        --entrypoint php "${EXPECTED_IMAGE_ID}" /fixtures/concurrent.php &
    first_pid=$!
    docker run --rm --network none \
        --mount "type=bind,src=${REPOSITORY_ROOT},dst=/source,readonly" \
        --mount "type=bind,src=${TEMP_ROOT},dst=/fixtures" \
        --env "PHASE3B_MODE=${mode}" --env PHASE3B_SEED=11 \
        --entrypoint php "${EXPECTED_IMAGE_ID}" /fixtures/concurrent.php &
    second_pid=$!
    wait "${first_pid}"
    wait "${second_pid}"
done

docker run --rm --network none \
    --mount "type=bind,src=${TEMP_ROOT},dst=/fixtures,readonly" \
    --entrypoint /bin/sh "${EXPECTED_IMAGE_ID}" -c '
        set -eu
        test "$(find /fixtures/concurrent-unique/goosialize-leads/v1/records -type f -name "*.json" | wc -l)" -eq 2
        test "$(find /fixtures/concurrent-keyed/goosialize-leads/v1/records -type f -name "*.json" | wc -l)" -eq 1
        test "$(find /fixtures/concurrent-keyed/goosialize-leads/v1/idempotency -type f -name "*.json" | wc -l)" -eq 1
        test -z "$(find /fixtures/concurrent-unique /fixtures/concurrent-keyed -type f -name ".tmp-*")"
    '

docker run --rm --network none \
    --mount "type=bind,src=${REPOSITORY_ROOT},dst=/source,readonly" \
    --entrypoint php "${EXPECTED_IMAGE_ID}" /source/tests/unit/phase-3b-secure-persistence.php

GRAV_TEST_IMAGE="${GRAV_IMAGE}" "${REPOSITORY_ROOT}/tests/integration/installable-plugin-package.sh"
GRAV_TEST_IMAGE="${GRAV_IMAGE}" "${REPOSITORY_ROOT}/tests/integration/phase-2d-entry-points.sh"
