#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

echo '== Composer metadata =='
php -r '$j=json_decode(file_get_contents("composer.json"),true,512,JSON_THROW_ON_ERROR); if(($j["require"]["php"]??"")!==">=8.1") exit(1); echo "composer.json OK\n";'

echo '== PHP syntax =='
find . -path './dist' -prune -o -path './vendor' -prune -o -name '*.php' -print0 | sort -z | xargs -0 -n1 php -l

echo '== Unit and adversarial suites =='
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

echo '== Requirement traceability =='
for i in $(seq 1 34); do
  id=$(printf 'CF06-FR-%03d' "$i")
  grep -Fq "$id" docs/REQUIREMENTS-TRACEABILITY.md || { echo "Missing $id" >&2; exit 1; }
done

echo '== Critical corrective guards =='
grep -Fq 'private_or_high_risk_external_mt' src/Contract/Manifest.php
grep -Fq 'slto_verify_integration_acceptance_evidence' src/Application/IntegrationService.php
grep -Fq 'slto_verify_extraction_evidence' src/Application/ExtractionService.php
grep -Fq 'environment_name' src/Application/QaEvidenceService.php
grep -Fq "'performance'" src/Application/BundleService.php
grep -Fq 'evidence_ref' src/Application/ReleaseApprovalService.php

echo 'QUALITY GATE PASS'
