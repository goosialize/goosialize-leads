#!/usr/bin/env bash
set -Eeuo pipefail
export LC_ALL=C

readonly REPOSITORY_ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd -P)"
readonly MANIFEST="${REPOSITORY_ROOT}/packaging/package-files.txt"

fail() { printf 'FAIL: %s\n' "$*" >&2; exit 1; }

for document in \
    README.md \
    docs/QUICK_START.md \
    docs/INSTALLATION.md \
    docs/ADMIN_GUIDE.md \
    docs/FORMS_INTEGRATION.md \
    docs/CSV_EXPORT.md \
    docs/FAQ.md \
    docs/CONFIGURATION.md \
    docs/PERMISSIONS.md \
    docs/SECURITY.md \
    docs/JSON_API_INTEGRATION.md \
    docs/NOTIFICATION_DELIVERY.md \
    docs/OPERATIONAL_STATUS.md \
    docs/TROUBLESHOOTING.md; do
    test -f "${REPOSITORY_ROOT}/${document}" || fail "required document is missing: ${document}"
    grep -Fxq "${document}" "${MANIFEST}" || fail "required document is not packaged: ${document}"
done

test -f "${REPOSITORY_ROOT}/docs/TERMINOLOGY.md" \
    || fail 'repository terminology contract is missing'

for term in 'Goosialize Leads' 'Admin2' 'Form / Resource' 'CSV Export' \
    'Grav Forms' 'Public JSON API' 'Idempotency' 'Dead letter' 'Reconciliation'; do
    grep -Fq "${term}" "${REPOSITORY_ROOT}/docs/TERMINOLOGY.md" \
        || fail "terminology contract omits canonical term: ${term}"
done

for document in \
    docs/QUICK_START.md docs/INSTALLATION.md docs/ADMIN_GUIDE.md \
    docs/FORMS_INTEGRATION.md docs/CSV_EXPORT.md docs/FAQ.md \
    docs/CONFIGURATION.md docs/PERMISSIONS.md docs/SECURITY.md \
    docs/JSON_API_INTEGRATION.md docs/NOTIFICATION_DELIVERY.md \
    docs/OPERATIONAL_STATUS.md docs/TROUBLESHOOTING.md; do
    grep -Fq '## Navigation' "${REPOSITORY_ROOT}/${document}" \
        || fail "navigation block is missing: ${document}"
    grep -Fq 'Back to README' "${REPOSITORY_ROOT}/${document}" \
        || fail "README return link is missing: ${document}"
    grep -Fq 'Previous:' "${REPOSITORY_ROOT}/${document}" \
        || fail "previous navigation is missing: ${document}"
    grep -Fq 'Next:' "${REPOSITORY_ROOT}/${document}" \
        || fail "next navigation is missing: ${document}"
    grep -Fq 'Related documentation:' "${REPOSITORY_ROOT}/${document}" \
        || fail "related-documentation navigation is missing: ${document}"
done

if grep -Fq 'Version 1.0.2 is published as a stable' "${REPOSITORY_ROOT}/README.md"; then
    fail 'README contains the stale 1.0.2 current-release claim'
fi

if grep -R -n -E '"consent"[[:space:]]*:[[:space:]]*true' \
    "${REPOSITORY_ROOT}/README.md" "${REPOSITORY_ROOT}/docs"; then
    fail 'invalid scalar public consent example remains'
fi

if grep -n -E 'Lead status mutation|Delete unsupported|Restore unsupported|native Admin2 Lead detail' \
    "${REPOSITORY_ROOT}/docs/TROUBLESHOOTING.md"; then
    fail 'obsolete Admin2 mutation claim remains in Troubleshooting'
fi

grep -Fq 'goosialize_leads_capture: true' "${REPOSITORY_ROOT}/docs/QUICK_START.md" \
    || fail 'Quick Start lacks the exact Grav Forms process action'
grep -Fq 'consent.granted:' "${REPOSITORY_ROOT}/docs/QUICK_START.md" \
    || fail 'Quick Start lacks the nested Grav consent field'
grep -Fq 'consent.granted:' "${REPOSITORY_ROOT}/docs/FORMS_INTEGRATION.md" \
    || fail 'Forms guide lacks the nested Grav consent field'

grep -Fq 'default API configuration' "${REPOSITORY_ROOT}/docs/JSON_API_INTEGRATION.md" \
    || fail 'public API guide does not qualify the default path'
grep -Fq '/service/edge/goosialize-leads/capture' "${REPOSITORY_ROOT}/docs/JSON_API_INTEGRATION.md" \
    || fail 'public API guide lacks the custom-base example'
grep -Fq '## Tested curl request' "${REPOSITORY_ROOT}/docs/JSON_API_INTEGRATION.md" \
    || fail 'public API guide lacks its tested curl example'
grep -Fq 'curl --request POST' "${REPOSITORY_ROOT}/docs/JSON_API_INTEGRATION.md" \
    || fail 'public API guide lacks the POST curl request'

for delay in 300 1800 7200 28800; do
    grep -Eq "^[[:space:]]+- ${delay}$" "${REPOSITORY_ROOT}/goosialize-leads.yaml" \
        || fail "shipped retry delay is missing: ${delay}"
    grep -Eq "^[[:space:]]+- ${delay}$" "${REPOSITORY_ROOT}/docs/CONFIGURATION.md" \
        || fail "documented retry delay is missing: ${delay}"
    grep -Eq "^[[:space:]]+- ${delay}$" "${REPOSITORY_ROOT}/docs/RETRY_DEAD_LETTER.md" \
        || fail "Retry guide delay is missing: ${delay}"
done

python3 - "${REPOSITORY_ROOT}" "${MANIFEST}" <<'PY'
from pathlib import Path
import json
import re
import subprocess
import sys
import unicodedata
from urllib.parse import unquote, urlsplit

import yaml

root = Path(sys.argv[1])
manifest = Path(sys.argv[2]).read_text(encoding="utf-8").splitlines()
if manifest != sorted(manifest):
    raise SystemExit("FAIL: package manifest is not sorted")
if len(manifest) != len(set(manifest)):
    raise SystemExit("FAIL: package manifest contains duplicates")
packaged = set(manifest)
markdown = [Path(value) for value in manifest if value.endswith(".md")]

public_documents = [
    Path("README.md"),
    Path("docs/QUICK_START.md"),
    Path("docs/INSTALLATION.md"),
    Path("docs/ADMIN_GUIDE.md"),
    Path("docs/FORMS_INTEGRATION.md"),
    Path("docs/CSV_EXPORT.md"),
    Path("docs/FAQ.md"),
    Path("docs/CONFIGURATION.md"),
    Path("docs/PERMISSIONS.md"),
    Path("docs/SECURITY.md"),
    Path("docs/JSON_API_INTEGRATION.md"),
    Path("docs/NOTIFICATION_DELIVERY.md"),
    Path("docs/OPERATIONAL_STATUS.md"),
    Path("docs/SCHEDULER.md"),
    Path("docs/RETRY_DEAD_LETTER.md"),
    Path("docs/RECONCILIATION.md"),
    Path("docs/CLI_REFERENCE.md"),
    Path("docs/TROUBLESHOOTING.md"),
]

for relative in public_documents:
    if not (root / relative).is_file():
        raise SystemExit(f"FAIL: canonical public document is missing: {relative}")
    if relative.as_posix() not in packaged:
        raise SystemExit(f"FAIL: canonical public document is not packaged: {relative}")

def github_slug(value):
    value = re.sub(r"!??\[([^]]+)\]\([^)]+\)", r"\1", value)
    value = re.sub(r"<[^>]+>", "", value)
    value = value.replace("`", "").strip().lower()
    value = "".join(
        char for char in unicodedata.normalize("NFC", value)
        if char.isalnum() or char in " -_"
    )
    return re.sub(r"[ ]+", "-", value)

def anchors_for(text):
    anchors = set()
    counts = {}
    for heading in re.findall(r"^#{1,6}[ \t]+(.+?)[ \t]*#*[ \t]*$", text, re.M):
        base = github_slug(heading)
        count = counts.get(base, 0)
        counts[base] = count + 1
        anchors.add(base if count == 0 else f"{base}-{count}")
    return anchors

texts = {relative: (root / relative).read_text(encoding="utf-8") for relative in markdown}
anchors = {relative: anchors_for(text) for relative, text in texts.items()}
pattern = re.compile(r"(!?)\[([^]]*)\]\(([^)]+)\)")

for relative in markdown:
    source = root / relative
    text = texts[relative]
    if text.count("```") % 2:
        raise SystemExit(f"FAIL: unbalanced Markdown fence: {relative}")

    for language, body in re.findall(r"^```([A-Za-z0-9_-]*)[ \t]*\n(.*?)^```[ \t]*$", text, re.M | re.S):
        if language in {"yaml", "yml"}:
            try:
                list(yaml.safe_load_all(body))
            except yaml.YAMLError as error:
                raise SystemExit(f"FAIL: invalid YAML example in {relative}: {error}")
        elif language == "json":
            try:
                json.loads(body)
            except json.JSONDecodeError as error:
                raise SystemExit(f"FAIL: invalid JSON example in {relative}: {error}")
        elif language in {"bash", "sh"}:
            result = subprocess.run(
                ["bash", "-n"], input=body, text=True, capture_output=True, check=False
            )
            if result.returncode:
                raise SystemExit(f"FAIL: invalid shell example in {relative}: {result.stderr.strip()}")

    for image_marker, label, raw_target in pattern.findall(text):
        # Grav's changelog format uses empty self-links as list markers; they
        # are not rendered documentation navigation.
        if not image_marker and not label:
            continue
        raw_target = raw_target.strip()
        if raw_target.startswith("<") and ">" in raw_target:
            raw_target = raw_target[1:raw_target.index(">")]
        elif " " in raw_target:
            raw_target = raw_target.split(" ", 1)[0]
        parsed = urlsplit(raw_target)
        if parsed.scheme:
            if parsed.scheme not in {"http", "https", "mailto"}:
                raise SystemExit(f"FAIL: unsupported link scheme: {relative} -> {raw_target}")
            if parsed.scheme in {"http", "https"} and not parsed.netloc:
                raise SystemExit(f"FAIL: malformed external URL: {relative} -> {raw_target}")
            continue

        target_path = unquote(parsed.path)
        fragment = unquote(parsed.fragment)
        resolved = source.resolve() if not target_path else (source.parent / target_path).resolve()
        try:
            packaged_target = resolved.relative_to(root).as_posix()
        except ValueError:
            raise SystemExit(f"FAIL: link escapes repository: {relative} -> {raw_target}")
        if not resolved.exists():
            raise SystemExit(f"FAIL: unresolved link: {relative} -> {raw_target}")
        if packaged_target not in packaged:
            raise SystemExit(f"FAIL: packaged doc links to unpackaged file: {relative} -> {raw_target}")
        if fragment:
            target_relative = Path(packaged_target)
            if target_relative.suffix.lower() != ".md":
                raise SystemExit(f"FAIL: anchor points to non-Markdown file: {relative} -> {raw_target}")
            if fragment not in anchors[target_relative]:
                raise SystemExit(f"FAIL: unresolved anchor: {relative} -> {raw_target}")

canonical_text = "\n".join((root / item).read_text(encoding="utf-8") for item in public_documents)
for forbidden in (r"Form/Resource", r"Resource/Form", r"Form Resource", r"Admin 2", r"CSV export"):
    if re.search(forbidden, canonical_text):
        raise SystemExit(f"FAIL: non-canonical public terminology remains: {forbidden}")

screenshots = {
    "docs/images/admin2-leads-workspace.png",
    "docs/images/admin2-leads-filters.png",
    "docs/images/admin2-lead-edit.png",
    "docs/images/admin2-delete-restore.png",
    "docs/images/admin2-csv-export.png",
    "docs/images/plugin-configuration.png",
}
for screenshot in screenshots:
    if screenshot not in packaged:
        raise SystemExit(f"FAIL: screenshot is not packaged: {screenshot}")
    if not (root / screenshot).is_file():
        raise SystemExit(f"FAIL: screenshot file is missing: {screenshot}")
    reference = screenshot.removeprefix("docs/")
    if not any(reference in texts[item] or screenshot in texts[item] for item in public_documents):
        raise SystemExit(f"FAIL: screenshot is not referenced: {screenshot}")

print("DOCUMENTATION_CONTRACT=PASS")
PY
