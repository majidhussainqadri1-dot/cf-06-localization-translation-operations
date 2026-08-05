#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

echo '== Composer metadata =='
php -r '$j=json_decode(file_get_contents("composer.json"),true,512,JSON_THROW_ON_ERROR); if(($j["require"]["php"]??"")!==">=8.1") exit(1); echo "composer.json OK\n";'

echo '== PHP syntax =='
find . -path './dist' -prune -o -path './vendor' -prune -o -name '*.php' -print0 | sort -z | xargs -0 -n1 php -l

echo '== Unit, contract, integration-contract and adversarial tests =='
php tests/run-unit.php
php tests/contracts.php
php tests/review-round-1-ownership.php
php tests/review-round-2-security.php
php tests/review-round-3-lifecycle.php
php tests/review-round-4-acceptance.php

echo '== Secret-pattern guard =='
if grep -RInE --exclude-dir=.git --exclude-dir=dist --exclude-dir=tests --exclude='quality-check.sh' '(BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY|AKIA[0-9A-Z]{16}|ghp_[A-Za-z0-9]{20,}|sk_live_[A-Za-z0-9]{12,}|xox[baprs]-[A-Za-z0-9-]{10,})' .; then
  echo 'Secret-like material found.' >&2
  exit 1
fi

echo '== Requirement traceability and stale-evidence guard =='
python3 - <<'PY'
from pathlib import Path

root = Path('.')
rtm = (root / 'docs/REQUIREMENTS-TRACEABILITY.md').read_text(encoding='utf-8')
missing = [f'CF06-FR-{i:03d}' for i in range(1, 35) if f'CF06-FR-{i:03d}' not in rtm]
if missing:
    raise SystemExit('Missing requirements: ' + ', '.join(missing))

stale = (
    '1.0.0-rc.1',
    '5d994a98dd951a63fb86452655fd18c6895a45e4',
    '45aca0b7881214756db301e836be6446dc98fd8e',
)
stale_hits = []
for path in root.rglob('*'):
    if not path.is_file() or '.git' in path.parts or 'dist' in path.parts or path.name == 'quality-check.sh':
        continue
    text = path.read_text(encoding='utf-8', errors='ignore')
    for needle in stale:
        if needle in text:
            stale_hits.append(f'{path}:{needle}')
if stale_hits:
    raise SystemExit('Stale completion evidence: ' + '; '.join(stale_hits))

required = {
    'src/Contract/Manifest.php': ['private_or_high_risk_external_mt'],
    'src/Application/IntegrationService.php': ['slto_verify_integration_acceptance_evidence'],
    'src/Application/ExtractionService.php': ['slto_verify_extraction_evidence'],
    'src/Application/QaEvidenceService.php': ['environment_name'],
    'src/Application/BundleService.php': ["'performance'"],
    'src/Application/ReleaseApprovalService.php': ['evidence_ref'],
}
for filename, needles in required.items():
    text = (root / filename).read_text(encoding='utf-8')
    for needle in needles:
        if needle not in text:
            raise SystemExit(f'Missing guard {needle!r} in {filename}')
print('Traceability and evidence guard OK')
PY

echo 'QUALITY GATE PASS'
