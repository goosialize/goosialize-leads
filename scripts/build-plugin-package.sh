#!/usr/bin/env bash
set -euo pipefail

readonly SLUG='goosialize-leads'
readonly VERSION='0.1.0-dev'
readonly ARCHIVE_ROOT='grav-plugin-goosialize-leads'
readonly VALIDATION_IMAGE='lscr.io/linuxserver/grav:2.0.12'
readonly VALIDATION_IMAGE_ID='sha256:702d936e25513805b57c9d009f7ff466217273415b2e55f539f3366e6377d351'
readonly SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
readonly REPOSITORY_ROOT="$(cd -- "${SCRIPT_DIR}/.." && pwd -P)"
readonly MANIFEST="${REPOSITORY_ROOT}/packaging/package-files.txt"

fail() { printf 'FAIL: %s\n' "$*" >&2; exit 1; }
[[ $# -eq 1 ]] || fail 'usage: scripts/build-plugin-package.sh OUTPUT_DIRECTORY'
command -v python3 >/dev/null 2>&1 || fail 'python3 is not available'
command -v docker >/dev/null 2>&1 || fail 'docker is not available for structured blueprint validation'
[[ -f "${MANIFEST}" && ! -L "${MANIFEST}" ]] || fail 'package manifest is missing or is a symlink'

output_input="$1"
readonly OUTPUT_DIRECTORY="$(python3 - "${REPOSITORY_ROOT}" "${output_input}" <<'PY'
from pathlib import Path
import sys

repository = Path(sys.argv[1]).resolve(strict=True)
requested = Path(sys.argv[2]).resolve(strict=False)
if requested == repository or repository in requested.parents:
    raise SystemExit(f"FAIL: output directory is inside the source repository: {requested}")
print(requested)
PY
)" || exit $?
mkdir -p -- "${OUTPUT_DIRECTORY}"
[[ -d "${OUTPUT_DIRECTORY}" && ! -L "${OUTPUT_DIRECTORY}" ]] || fail "output path is not a regular directory: ${OUTPUT_DIRECTORY}"

readonly OUTPUT_PATH="${OUTPUT_DIRECTORY}/${SLUG}-${VERSION}.zip"
docker image inspect "${VALIDATION_IMAGE}" >/dev/null 2>&1 || fail "Docker image is not available locally: ${VALIDATION_IMAGE}"
readonly ACTUAL_IMAGE_ID="$(docker image inspect --format '{{.Id}}' "${VALIDATION_IMAGE}")"
[[ "${ACTUAL_IMAGE_ID}" == "${VALIDATION_IMAGE_ID}" ]] || fail "Docker image ID mismatch: expected ${VALIDATION_IMAGE_ID}, actual ${ACTUAL_IMAGE_ID}"
readonly BLUEPRINT_METADATA="$(docker run --rm --network none \
    --mount "type=bind,src=${REPOSITORY_ROOT},dst=/source,readonly" \
    --entrypoint php "${VALIDATION_IMAGE_ID}" -r '
require "/app/www/public/vendor/autoload.php";
$metadata = Symfony\Component\Yaml\Yaml::parseFile("/source/blueprints.yaml");
if (!is_array($metadata)) { fwrite(STDERR, "FAIL: blueprints.yaml did not parse to a mapping\n"); exit(1); }
echo json_encode(["slug" => $metadata["slug"] ?? null, "version" => $metadata["version"] ?? null], JSON_THROW_ON_ERROR);
')" || fail 'structured blueprint validation failed'
python3 - "${BLUEPRINT_METADATA}" "${SLUG}" "${VERSION}" <<'PY'
import json, sys
metadata = json.loads(sys.argv[1])
if metadata.get("slug") != sys.argv[2]:
    raise SystemExit(f"FAIL: blueprint slug mismatch: {metadata.get('slug')!r}")
if metadata.get("version") != sys.argv[3]:
    raise SystemExit(f"FAIL: blueprint version mismatch: {metadata.get('version')!r}")
PY

python3 - "${REPOSITORY_ROOT}" "${MANIFEST}" "${OUTPUT_PATH}" "${ARCHIVE_ROOT}" <<'PY'
import json
import os
from pathlib import Path, PurePosixPath
import signal
import stat
import sys
import tempfile
import zipfile

root, manifest, output = map(Path, sys.argv[1:4])
archive_root = sys.argv[4]
lines = manifest.read_text(encoding="utf-8").splitlines()
if not lines or any(not line for line in lines):
    raise SystemExit("FAIL: manifest contains a blank entry")
if lines != sorted(lines):
    raise SystemExit("FAIL: manifest is not in deterministic lexical order")
if len(lines) != len(set(lines)):
    raise SystemExit("FAIL: manifest contains duplicate entries")
for value in lines:
    path = PurePosixPath(value)
    if path.is_absolute() or ".." in path.parts or value.startswith(("/", "\\")):
        raise SystemExit(f"FAIL: unsafe manifest path: {value}")
    source = root / value
    if source.is_symlink() or not source.is_file():
        raise SystemExit(f"FAIL: manifest path is not a regular non-symlink file: {value}")

composer = json.loads((root / "composer.json").read_text(encoding="utf-8"))
if composer.get("type") != "grav-plugin":
    raise SystemExit("FAIL: composer package type is not grav-plugin")

timestamp = (1980, 1, 1, 0, 0, 0)
directories = {archive_root + "/"}
for value in lines:
    parent = PurePosixPath(value).parent
    while str(parent) != ".":
        directories.add(f"{archive_root}/{parent.as_posix()}/")
        parent = parent.parent
file_entries = {f"{archive_root}/{value}": value for value in lines}
temporary = None
def interrupted(signum, frame):
    raise KeyboardInterrupt(f"signal {signum}")
for signum in (signal.SIGHUP, signal.SIGINT, signal.SIGTERM):
    signal.signal(signum, interrupted)
try:
    with tempfile.NamedTemporaryFile(prefix=".goosialize-leads-", suffix=".zip.tmp", dir=output.parent, delete=False) as handle:
        temporary = Path(handle.name)
        os.fchmod(handle.fileno(), 0o600)
        with zipfile.ZipFile(handle, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
            for name in sorted(directories | set(file_entries)):
                info = zipfile.ZipInfo(name, timestamp)
                info.create_system = 3
                if name in directories:
                    info.external_attr = (stat.S_IFDIR | 0o755) << 16 | 0x10
                    archive.writestr(info, b"")
                else:
                    info.external_attr = (stat.S_IFREG | 0o644) << 16
                    info.compress_type = zipfile.ZIP_DEFLATED
                    archive.writestr(info, (root / file_entries[name]).read_bytes(), compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)
        handle.flush()
        os.fsync(handle.fileno())
    os.replace(temporary, output)
    temporary = None
finally:
    if temporary is not None:
        temporary.unlink(missing_ok=True)
PY

readonly SHA256="$(sha256sum -- "${OUTPUT_PATH}" | awk '{print $1}')"
printf 'PACKAGE_PATH=%s\nSHA256=%s\n' "${OUTPUT_PATH}" "${SHA256}"
