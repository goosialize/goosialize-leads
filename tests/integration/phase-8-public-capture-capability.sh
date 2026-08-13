#!/usr/bin/env bash
set -Eeuo pipefail

export LC_ALL=C
export NO_COLOR=1

readonly EXPECTED_IMAGE_ID='sha256:702d936e25513805b57c9d009f7ff466217273415b2e55f539f3366e6377d351'
readonly SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
readonly ROOT="$(cd -- "${SCRIPT_DIR}/../.." && pwd -P)"
readonly IMAGE="${GRAV_TEST_IMAGE:?GRAV_TEST_IMAGE is required}"

fail() {
    printf 'FAIL: %s\n' "$*" >&2
    exit 1
}

docker image inspect "${IMAGE}" >/dev/null 2>&1 \
    || fail "Docker image unavailable: ${IMAGE}"

readonly ACTUAL_IMAGE_ID="$(
    docker image inspect \
        --format '{{.Id}}' \
        "${IMAGE}"
)"

test "${ACTUAL_IMAGE_ID}" = "${EXPECTED_IMAGE_ID}" \
    || fail "Unexpected Docker image ID: ${ACTUAL_IMAGE_ID}"

test -z "$(sort "${ROOT}/packaging/package-files.txt" | uniq -d)"

for path in \
    classes/Application/LeadCaptureRuntimeFactory.php \
    classes/Integration/GoosializeLeadsCaptureCapabilityV1.php \
    classes/Integration/LeadCaptureCapabilityV1.php \
    classes/Integration/LeadCaptureContextV1.php \
    classes/Integration/LeadCaptureRequestV1.php \
    classes/Integration/LeadCaptureResultV1.php
do
    grep -Fxq \
        "${path}" \
        "${ROOT}/packaging/package-files.txt"
done

grep -Fq \
    "\$serviceKey = 'goosialize-leads.public-capture.v1';" \
    "${ROOT}/goosialize-leads.php"

grep -Fq \
    'new GoosializeLeadsCaptureCapabilityV1($service)' \
    "${ROOT}/goosialize-leads.php"

grep -Fq \
    'public_capture_capability_service_collision' \
    "${ROOT}/goosialize-leads.php"

grep -Fq \
    'public_capture_capability_unavailable' \
    "${ROOT}/goosialize-leads.php"

docker run \
    --rm \
    --network none \
    --mount "type=bind,src=${ROOT},dst=/source,readonly" \
    --entrypoint php \
    "${IMAGE}" \
    /source/tests/unit/phase-8-public-capture-capability.php

docker run \
    --rm \
    --network none \
    --mount "type=bind,src=${ROOT},dst=/source,readonly" \
    --entrypoint php \
    "${IMAGE}" \
    -r '
require "/source/autoload.php";

use Grav\Plugin\GoosializeLeads\Application\LeadCaptureRuntimeFactory;
use Grav\Plugin\GoosializeLeads\Integration\GoosializeLeadsCaptureCapabilityV1;
use Grav\Plugin\GoosializeLeads\Integration\LeadCaptureCapabilityV1;
use Grav\Plugin\GoosializeLeads\Integration\LeadCaptureContextV1;
use Grav\Plugin\GoosializeLeads\Integration\LeadCaptureRequestV1;

$key = "goosialize-leads.public-capture.v1";
$root = sys_get_temp_dir()
    . "/goosialize-leads-external-consumer-"
    . bin2hex(random_bytes(6));

if (!mkdir($root, 0700, true)) {
    throw new RuntimeException("Unable to create consumer root.");
}

$remove = static function (string $path): void {
    if (!is_dir($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $path,
            FilesystemIterator::SKIP_DOTS
        ),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        $item->isDir()
            ? rmdir($item->getPathname())
            : unlink($item->getPathname());
    }

    rmdir($path);
};

try {
    $runtime = LeadCaptureRuntimeFactory::create(
        $root,
        [
            "active_key_version" => 1,
            "keys" => [
                1 => base64_encode(str_repeat("k", 32)),
            ],
        ],
        null
    );

    $container = new ArrayObject();

    if ($container->offsetExists($key)) {
        throw new RuntimeException("Synthetic service collision.");
    }

    $container[$key] =
        new GoosializeLeadsCaptureCapabilityV1(
            $runtime,
            static fn (int $length): string =>
                str_repeat("b", $length),
            static fn (): DateTimeInterface =>
                new DateTimeImmutable(
                    "2026-07-31T18:30:00+00:00"
                )
        );

    $consumer = static function (
        ArrayAccess $services
    ) use ($key): array {
        if (!$services->offsetExists($key)) {
            return [
                "available" => false,
                "outcome" => "unavailable",
            ];
        }

        $capability = $services[$key];

        if (
            !$capability instanceof LeadCaptureCapabilityV1
            || $capability->capabilityId()
                !== "goosialize-leads.capture"
            || $capability->contractVersion() !== 1
            || !$capability->available()
        ) {
            return [
                "available" => false,
                "outcome" => "unavailable",
            ];
        }

        $result = $capability->capture(
            LeadCaptureRequestV1::fromArray([
                "full_name" => "External Consumer",
                "email" => "consumer@example.test",
                "message" => "External consumer inquiry",
                "consent" => ["granted" => true],
            ]),
            LeadCaptureContextV1::create(
                "goosialize_links",
                "goosialize_links_contact",
                "en",
                "privacy_v1",
                "external-consumer-submission-0001"
            )
        );

        return [
            "available" => true,
            "outcome" => $result->outcome(),
            "errors" => $result->errors(),
        ];
    };

    $response = $consumer($container);

    if ($response !== [
        "available" => true,
        "outcome" => "created",
        "errors" => [],
    ]) {
        throw new RuntimeException(
            "External consumer result mismatch: "
            . json_encode($response, JSON_THROW_ON_ERROR)
        );
    }

    echo "PASS_PHASE_8_EXTERNAL_CONSUMER\n";
    echo "PASS_PHASE_8_SERVICE_KEY\n";
    echo "PASS_PHASE_8_EXACT_VERSION_DETECTION\n";
} finally {
    $remove($root);
}
'

printf '%s\n' \
    'PASS_PHASE_8_CONTAINER_REGISTRATION_CONTRACT' \
    'PASS_PHASE_8_PACKAGE' \
    'PASS_PHASE_8_INTEGRATION'
