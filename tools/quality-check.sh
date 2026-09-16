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
php tests/review-rounds-40.php
php tests/future40.php
php tests/future40-validation.php

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
for i in $(seq 1 10); do
  id=$(printf 'CF06-CEN-%02d' "$i")
  grep -Fq "$id" docs/REQUIREMENTS-TRACEABILITY.md || { echo "Missing $id" >&2; exit 1; }
done
for i in $(seq 1 6); do
  id=$(printf 'CF06-NJ-%02d' "$i")
  grep -Fq "$id" docs/REQUIREMENTS-TRACEABILITY.md || { echo "Missing $id" >&2; exit 1; }
done
for i in $(seq 1 40); do
  id=$(printf 'CF06-FUT-%03d' "$i")
  grep -Fq "$id" docs/REQUIREMENTS-TRACEABILITY.md || { echo "Missing $id" >&2; exit 1; }
  grep -Fq "$id" docs/FUTURE40.md || { echo "Missing Future40 specification $id" >&2; exit 1; }
done

echo '== Repository hygiene and version coherence =='
test ! -e .cf06-payload
test ! -e .cf06-fixed
test ! -e .github/workflows/export-source-for-review.yml
grep -Fq 'Version:           1.0.0-rc.5' sabri-localization-translation-operations.php
grep -Fq "SABRI_SLTO_SCHEMA_VERSION', '1.0.1" sabri-localization-translation-operations.php
grep -Fq "SABRI_SLTO_CONTRACT_VERSION', '1.3.0" sabri-localization-translation-operations.php
grep -Fq 'VERSION = "1.0.0-rc.5"' tools/build-release.py
grep -Fq '"contract_version": "1.3.0"' tools/build-release.py
grep -Fq 'cf-06-complete-source-candidate-rc5' .github/workflows/ci.yml

echo '== Critical corrective guards =='
grep -Fq "'external_mt' => 'low-risk-c1-draft-only'" src/Contract/Manifest.php
grep -Fq "'C1' !== strtoupper(\$dataClass)" src/Domain/Translation/RiskPolicy.php
grep -Fq 'MessageFormatValidator::assertEquivalent' src/Domain/Translation/PlaceholderValidator.php
grep -Fq "status='invalidated'" src/Infrastructure/DependencyInvalidator.php
grep -Fq 'slto_verify_integration_acceptance_evidence' src/Application/IntegrationService.php
grep -Fq 'slto_verify_extraction_evidence' src/Application/ExtractionService.php
grep -Fq 'environment_name' src/Application/QaEvidenceService.php
grep -Fq "'performance'" src/Application/BundleService.php
grep -Fq 'evidence_ref' src/Application/ReleaseApprovalService.php
grep -Fq "'future40' => array_values(FutureCapabilities::all())" src/Contract/Manifest.php
grep -Fq "'default_state' => 'disabled'" src/Contract/FutureCapabilities.php
grep -Fq "'mode' => 'evidence-preview'" src/Application/FutureCapabilitiesService.php
grep -Fq "'direct_publish' => false" src/Application/FutureCapabilitiesService.php
grep -Fq "'approval_authority' => false" src/Application/FutureCapabilitiesService.php
grep -Fq 'FutureCapabilitiesFacade' src/Plugin.php
grep -Fq 'FutureCapabilityGuard::normalize' src/Application/FutureCapabilitiesFacade.php
grep -Fq 'live_deployment_verification' src/Contract/FutureCapabilities.php
grep -Fq 'MAX_EVALUATION_BYTES' src/Rest/FutureRoutes.php
grep -Fq 'if (! isset(self::MAP[$action]))' src/Security/Authorization.php

echo 'QUALITY GATE PASS'
