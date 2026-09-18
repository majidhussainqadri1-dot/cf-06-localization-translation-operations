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
php tests/future40-privacy-provider.php
php tests/future40-semantic-integrity.php
php tests/future40-locale-accessibility.php
php tests/review-round-9-traceability.php
php tests/review-round-10-final.php
php tests/review-fresh-round-01-environment.php
php tests/review-fresh-round-02-boot-parity.php
php tests/review-fresh-round-03-idempotency.php
php tests/review-fresh-round-04-provider-governance.php
php tests/review-fresh-round-05-workflow-provenance.php
php tests/review-fresh-round-06-bundle-coverage.php
php tests/review-fresh-round-07-privacy-retention.php
php tests/review-fresh-round-08-future40-hotfix.php
php tests/review-cycle2-round-01-provider-parity.php
php tests/review-cycle2-round-02-memory-governance.php
for test in tests/review-cycle3-round-*.php tests/review-cycle4-round-*.php tests/review-cycle5-round-*.php; do
  [ -e "$test" ] || continue
  php "$test"
done

echo '== Secret-pattern guard =='
if grep -RInE --exclude-dir=.git --exclude-dir=dist --exclude-dir=tests --exclude='quality-check.sh' '(BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY|AKIA[0-9A-Z]{16}|ghp_[A-Za-z0-9]{20,}|sk_live_[A-Za-z0-9]{12,}|xox[baprs]-[A-Za-z0-9-]{10,})' .; then
  echo 'Secret-like material found.' >&2
  exit 1
fi

echo '== Requirement traceability =='
for i in $(seq 1 34); do id=$(printf 'CF06-FR-%03d' "$i"); grep -Fq "$id" docs/REQUIREMENTS-TRACEABILITY.md || { echo "Missing $id" >&2; exit 1; }; done
for i in $(seq 1 10); do id=$(printf 'CF06-CEN-%02d' "$i"); grep -Fq "$id" docs/REQUIREMENTS-TRACEABILITY.md || { echo "Missing $id" >&2; exit 1; }; done
for i in $(seq 1 6); do id=$(printf 'CF06-NJ-%02d' "$i"); grep -Fq "$id" docs/REQUIREMENTS-TRACEABILITY.md || { echo "Missing $id" >&2; exit 1; }; done
for i in $(seq 1 40); do
  id=$(printf 'CF06-FUT-%03d' "$i")
  grep -Fq "$id" docs/REQUIREMENTS-TRACEABILITY.md || { echo "Missing $id" >&2; exit 1; }
  grep -Fq "$id" docs/FUTURE40.md || { echo "Missing Future40 specification $id" >&2; exit 1; }
  grep -Fq "$id" docs/FUTURE40-TRACEABILITY-EVIDENCE.md || { echo "Missing Future40 evidence row $id" >&2; exit 1; }
done
for field in 'Security/privacy/safety enforcement' 'Automated evidence' 'Canonical owner boundary' 'Package / staging / live evidence'; do grep -Fq "$field" docs/FUTURE40-TRACEABILITY-EVIDENCE.md || { echo "Missing Future40 evidence dimension: $field" >&2; exit 1; }; done

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
grep -Fq 'deploymentEnvironment' src/Application/IntegrationService.php
grep -Fq 'Integration evidence must match the explicitly configured deployment environment.' src/Application/IntegrationService.php
grep -Fq 'evidenceStorageKey' src/Application/IntegrationService.php
grep -Fq 'slto_verify_production_activation_evidence' src/Application/HealthService.php
grep -Fq 'slto_verify_live_deployment_parity' src/Application/HealthService.php
grep -Fq 'SLTO_DEPLOYED_SOURCE_COMMIT' src/Application/HealthService.php
grep -Fq 'expected and actual hashes differ' src/Application/QaEvidenceService.php
grep -Fq "status='running' AND lease_until IS NOT NULL" src/Infrastructure/JobQueue.php
grep -Fq 'retained_audit_metadata' src/Application/PrivacyService.php
grep -Fq 'assignedActorIsCurrent' src/Application/TranslationService.php
grep -Fq 'translation_memory_reuse_allowed' src/Application/TranslationService.php
grep -Fq 'context-mismatch-human-review-required' src/Application/TerminologyService.php
grep -Fq "'auto_accept'=>false" src/Application/TerminologyService.php
grep -Fq 'Project resource exceeds the declared risk ceiling' src/Application/ProjectService.php
grep -Fq "unitChanges['status']='new'" src/Application/ProjectService.php
grep -Fq 'Terminology activation requires preserved approval provenance.' src/Application/TerminologyService.php
grep -Fq 'Style guide activation requires preserved approval provenance.' src/Application/TerminologyService.php
grep -Fq 'QA evidence is frozen once a bundle is approved for release' src/Application/BundleService.php
grep -Fq 'assertActiveProvider' src/Application/MachineTranslationService.php
grep -Fq 'assertGovernedProviderForPurge' src/Application/MachineTranslationService.php
grep -Fq 'slto_verify_provider_purge_evidence' src/Application/MachineTranslationService.php
grep -Fq 'slto_verify_assignment_qualification' src/Application/ProjectService.php
grep -Fq 'assertAdapterGovernance' src/Application/MachineTranslationService.php
grep -Fq 'response must attest the approved provider region' src/Application/MachineTranslationService.php
grep -Fq 'response must include bounded reference and model-version provenance' src/Application/MachineTranslationService.php
grep -Fq 'An active provider must be disabled before governance-relevant configuration is changed' src/Application/ProviderService.php
grep -Fq 'strictUtcTimestamp' src/Application/ReleaseApprovalService.php
grep -Fq 'APPROVAL_TTL_SECONDS' src/Application/ReleaseApprovalService.php
grep -Fq 'Rollback target must be the exact prior signed bundle.' src/Application/BundleService.php
grep -Fq 'Expired idempotency state could not be retired' src/Infrastructure/Repository/LocalizationRepository.php
grep -Fq "'status'=>'runtime_disabled'" src/functions.php
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
grep -Fq 'ProviderEligibilityGuard::normalize' src/Application/FutureCapabilitiesFacade.php
grep -Fq 'SemanticIntegrityGuard::apply' src/Application/FutureCapabilitiesFacade.php
grep -Fq 'LocaleAccessibilityGuard::normalize' src/Application/FutureCapabilitiesFacade.php
grep -Fq 'LocaleAccessibilityGuard::apply' src/Application/FutureCapabilitiesFacade.php
grep -Fq 'ReleaseLifecycleGuard::normalize' src/Application/FutureCapabilitiesFacade.php
grep -Fq 'ReleaseLifecycleGuard::apply' src/Application/FutureCapabilitiesFacade.php
grep -Fq 'live_deployment_verification' src/Contract/FutureCapabilities.php
grep -Fq 'MAX_EVALUATION_BYTES' src/Rest/FutureRoutes.php
grep -Fq 'if (! isset(self::MAP[$action]))' src/Security/Authorization.php
grep -Fq 'approved public low-risk C1' src/Domain/Future/FutureCapabilityGuard.php
grep -Fq 'Data residency is uncertain' src/Domain/Future/FutureCapabilityGuard.php
grep -Fq 'BidiValidator::assertSafe' src/Domain/Future/LocaleAccessibilityGuard.php
grep -Fq 'canonical_numeric_value_mutated' src/Domain/Future/LocaleAccessibilityGuard.php
grep -Fq "'file19'" src/Contract/PlanCompliance.php
grep -Fq "'file22'" src/Contract/PlanCompliance.php
grep -Fq "'file23'" src/Contract/PlanCompliance.php
grep -Fq "'file24'" src/Contract/PlanCompliance.php
grep -Fq 'slto_verify_assignment_qualification' docs/CONTRACTS.md
grep -Fq 'slto_verify_provider_purge_evidence' docs/CONTRACTS.md
grep -Fq 'slto_verify_staging_acceptance_evidence' docs/CONTRACTS.md
grep -Fq 'slto_verify_live_deployment_parity' docs/CONTRACTS.md

echo 'QUALITY GATE PASS'
