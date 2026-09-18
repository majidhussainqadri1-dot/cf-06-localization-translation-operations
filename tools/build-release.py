#!/usr/bin/env python3
"""Build a deterministic, exact-commit-bound installable CF-06 WordPress ZIP."""
from __future__ import annotations

import hashlib
import json
import os
import pathlib
import re
import shutil
import sys
import zipfile

ROOT = pathlib.Path(__file__).resolve().parents[1]
DIST = ROOT / "dist"
PACKAGE_DIR = "sabri-localization-translation-operations"
VERSION = "1.0.0-rc.5"
ZIP_NAME = f"cf-06-sabri-localization-translation-operations-{VERSION}-SOURCE-CANDIDATE.zip"
FIXED_TIME = (2026, 9, 16, 0, 0, 0)
SOURCE_COMMIT = os.environ.get("SOURCE_COMMIT", "").strip()

EXCLUDE_PARTS = {".git", ".github", "tests", "tools", "dist", "vendor", ".idea", ".vscode"}
EXCLUDE_NAMES = {"composer.lock", "phpunit.xml", "phpunit.xml.dist", ".gitignore", ".gitattributes"}
INCLUDE_ROOT_DOCS = {"README.md", "CHANGELOG.md", "SECURITY.md", "LICENSE", "readme.txt", "composer.json"}


def sha256(path: pathlib.Path) -> str:
    h = hashlib.sha256()
    with path.open("rb") as fh:
        for chunk in iter(lambda: fh.read(1024 * 1024), b""):
            h.update(chunk)
    return h.hexdigest()


def sha256_bytes(payload: bytes) -> str:
    return hashlib.sha256(payload).hexdigest()


def source_files() -> list[pathlib.Path]:
    files: list[pathlib.Path] = []
    for path in ROOT.rglob("*"):
        if not path.is_file():
            continue
        rel = path.relative_to(ROOT)
        if any(part in EXCLUDE_PARTS for part in rel.parts) or path.name in EXCLUDE_NAMES:
            continue
        if len(rel.parts) == 1 and path.name not in INCLUDE_ROOT_DOCS and path.suffix != ".php":
            continue
        files.append(path)
    return sorted(files, key=lambda p: p.relative_to(ROOT).as_posix())


def write_json(path: pathlib.Path, payload: object) -> None:
    path.write_text(json.dumps(payload, ensure_ascii=False, sort_keys=True, indent=2) + "\n", encoding="utf-8")


def validated_source_commit() -> str:
    if re.fullmatch(r"[0-9a-f]{40}", SOURCE_COMMIT) is None:
        raise RuntimeError("SOURCE_COMMIT must bind the candidate to an exact lowercase 40-character Git SHA")
    return SOURCE_COMMIT


def build() -> pathlib.Path:
    source_commit = validated_source_commit()
    DIST.mkdir(exist_ok=True)
    for old in DIST.iterdir():
        if old.is_file():
            old.unlink()
        elif old.is_dir():
            shutil.rmtree(old)

    files = source_files()
    if not files:
        raise RuntimeError("No package source files were discovered")
    manifest_files = []
    for path in files:
        rel = path.relative_to(ROOT).as_posix()
        manifest_files.append({"path": rel, "bytes": path.stat().st_size, "sha256": sha256(path)})

    manifest = {
        "module": "CF-06 — Localization and Translation Operations",
        "plugin_version": VERSION,
        "schema_version": "1.0.1",
        "contract_version": "1.3.0",
        "runtime_default": "disabled",
        "reproducible_archive_epoch": "2026-09-16T00:00:00Z",
        "archive_epoch_policy": "deterministic-reproducibility-only-not-build-time",
        "source_commit": source_commit,
        "requirements": {
            "functional_first": "CF06-FR-001", "functional_last": "CF06-FR-034", "functional_count": 34,
            "completion_first": "CF06-CEN-01", "completion_last": "CF06-CEN-10", "completion_count": 10,
            "native_journey_first": "CF06-NJ-01", "native_journey_last": "CF06-NJ-06", "native_journey_count": 6,
            "future_first": "CF06-FUT-001", "future_last": "CF06-FUT-040", "future_count": 40,
        },
        "files": manifest_files,
    }
    manifest_path = DIST / "MANIFEST.json"
    write_json(manifest_path, manifest)

    sbom = {
        "bomFormat": "CycloneDX", "specVersion": "1.5",
        "serialNumber": "urn:uuid:cf060000-0000-4000-8000-000000000001", "version": 1,
        "metadata": {
            "component": {
                "type": "application", "name": "sabri-localization-translation-operations", "version": VERSION,
                "licenses": [{"license": {"id": "GPL-2.0-or-later"}}],
                "properties": [
                    {"name": "sabri:runtime-default", "value": "disabled"},
                    {"name": "sabri:contract-version", "value": "1.3.0"},
                    {"name": "sabri:plan-reconciliation", "value": "central+cf06-latest+future40"},
                    {"name": "sabri:future40-default", "value": "disabled"},
                    {"name": "sabri:source-commit", "value": source_commit},
                ],
            },
        },
        "components": [
            {"type": "framework", "name": "WordPress", "version": ">=6.0"},
            {"type": "platform", "name": "PHP", "version": ">=8.1"},
        ],
    }
    sbom_path = DIST / "SBOM.cdx.json"
    write_json(sbom_path, sbom)

    zip_path = DIST / ZIP_NAME
    with zipfile.ZipFile(zip_path, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as zf:
        for path in files:
            rel = path.relative_to(ROOT).as_posix()
            info = zipfile.ZipInfo(f"{PACKAGE_DIR}/{rel}", FIXED_TIME)
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = 0o644 << 16
            zf.writestr(info, path.read_bytes(), compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)
        for evidence in (manifest_path, sbom_path):
            info = zipfile.ZipInfo(f"{PACKAGE_DIR}/{evidence.name}", FIXED_TIME)
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = 0o644 << 16
            zf.writestr(info, evidence.read_bytes(), compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)

    manifest_by_path = {row["path"]: row for row in manifest_files}
    with zipfile.ZipFile(zip_path, "r") as zf:
        bad = zf.testzip()
        if bad:
            raise RuntimeError(f"ZIP CRC failed at {bad}")
        names = zf.namelist()
        if not names or any(not name.startswith(PACKAGE_DIR + "/") for name in names):
            raise RuntimeError("ZIP canonical top-level folder check failed")
        expected = {f"{PACKAGE_DIR}/{p.relative_to(ROOT).as_posix()}" for p in files}
        actual = {n for n in names if pathlib.PurePosixPath(n).name not in {"MANIFEST.json", "SBOM.cdx.json"}}
        if expected != actual:
            raise RuntimeError("Source/package path parity failed")
        for rel, row in manifest_by_path.items():
            payload = zf.read(f"{PACKAGE_DIR}/{rel}")
            if len(payload) != row["bytes"] or sha256_bytes(payload) != row["sha256"]:
                raise RuntimeError(f"Source/package byte parity failed for {rel}")
        if zf.read(f"{PACKAGE_DIR}/MANIFEST.json") != manifest_path.read_bytes():
            raise RuntimeError("Embedded manifest parity failed")
        if zf.read(f"{PACKAGE_DIR}/SBOM.cdx.json") != sbom_path.read_bytes():
            raise RuntimeError("Embedded SBOM parity failed")

    sums = DIST / "SHA256SUMS"
    sums.write_text(
        f"{sha256(zip_path)}  {zip_path.name}\n"
        f"{sha256(manifest_path)}  {manifest_path.name}\n"
        f"{sha256(sbom_path)}  {sbom_path.name}\n",
        encoding="utf-8",
    )
    print(zip_path)
    print(f"sha256={sha256(zip_path)}")
    return zip_path


if __name__ == "__main__":
    try:
        build()
    except Exception as exc:
        print(f"BUILD FAILED: {exc}", file=sys.stderr)
        raise
