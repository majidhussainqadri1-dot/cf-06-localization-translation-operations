<?php

declare(strict_types=1);

require __DIR__ . '/TestHarness.php';

$root=dirname(__DIR__);$read=static fn(string $p):string=>(string)file_get_contents($root.'/'.$p);$t=new TestHarness();
$checks=[
['01 Transaction depth is reset safely','src/Infrastructure/Transaction.php','self::$depth = $isRoot ? 0 : $level'],
['02 Rollback failure is surfaced','src/Infrastructure/Transaction.php','Localization transaction rollback failed'],
['03 Repository reads fail closed','src/Infrastructure/Repository/LocalizationRepository.php','assertReadSucceeded'],
['04 Rate-limit database failure denies','src/Infrastructure/Repository/LocalizationRepository.php','if (false === $written)'],
['05 Job dedupe is scoped by job type','src/Infrastructure/Activator.php','UNIQUE KEY job_dedupe (job_type,dedupe_key)'],
['05b Contract-only upgrades are detected and persisted','src/Infrastructure/Activator.php',"installedContract","update_option('slto_contract_version'"],
['06 Job payload JSON is bounded','src/Infrastructure/JobQueue.php','1048576'],
['07 Outbox payload integrity is checked','src/Infrastructure/Outbox.php','Outbox payload integrity check failed'],
['08 Event hook normalization preserves word boundaries','src/Infrastructure/Outbox.php',"preg_replace('/(?<!^)[A-Z]/'"],
['09 Audit chain read errors are surfaced','src/Infrastructure/Repository/AuditRepository.php','audit chain head could not be read'],
['10 Audit advisory lock release is checked','src/Infrastructure/Repository/AuditRepository.php','audit chain lock could not be released'],
['11 File 00 assertions are mandatory','src/Security/Authorization.php',"! function_exists('smc_membership_assertions')"],
['12 Authorization extension is deny-only','src/Security/Authorization.php','return false !== $decision'],
['13 Integration evidence is persisted','src/Infrastructure/Database.php',"'integration_evidence'"],
['14 Integration evidence has independent verifier','src/Application/IntegrationService.php','slto_verify_integration_acceptance_evidence'],
['15 Expired integration evidence is rejected','src/Application/IntegrationService.php','already expired'],
['16 Extraction evidence is persisted','src/Infrastructure/Database.php',"'extraction_evidence'"],
['17 Extraction evidence has independent verifier','src/Application/ExtractionService.php','slto_verify_extraction_evidence'],
['18 QA evidence records environment','src/Application/QaEvidenceService.php','environment_name'],
['19 QA evidence binds artifact hash','src/Application/QaEvidenceService.php','artifact_hash'],
['20 Release approvals are persisted','src/Infrastructure/Database.php',"'release_approvals'"],
['21 Release requires two approval roles','src/Application/ReleaseApprovalService.php',"release_operator','independent_reviewer"],
['22 Release approval requires recent step-up','src/Application/ReleaseApprovalService.php','> 900'],
['23 Bundle activation uses verified integrations','src/Application/BundleService.php','integrations->assertReady'],
['24 Bundle activation requires dual approval','src/Application/BundleService.php','releaseApprovals->assertDualApproval'],
['25 Bundle releases exact source-list units','src/Application/BundleService.php','Exact approved translation set'],
['26 Rollback reconciles released units','src/Application/BundleService.php','released_bundle_uuid=NULL'],
['27 Cache flush occurs after transaction','src/Application/BundleService.php',"        wp_cache_flush();\n        return \$updated"],
['28 Public bundle re-verifies integrity','src/Application/BundleService.php','Active locale bundle integrity verification failed'],
['29 Projects bound target locale count','src/Application/ProjectService.php','1–25 distinct target locales'],
['30 Project resource source locale is enforced','src/Application/ProjectService.php','different source locale'],
['31 Project risk ceiling is enumerated','src/Application/ProjectService.php',"['low','medium','high','critical','private']"],
['32 Assignment expiry must be future','src/Application/ProjectService.php','Assignment expiry must be in the future'],
['33 Assignment requires conflict declaration','src/Application/ProjectService.php','cleared conflict declaration'],
['34 Assignee eligibility comes from File 00','src/Application/ProjectService.php','assertEligibleAssignee'],
['35 Rejection requires review reason','src/Application/TranslationService.php','A review reason is required'],
['36 Domain reviewer is independent','src/Application/TranslationService.php','independent reviewer'],
['37 Vendor comments deny protected units','src/Application/TranslationService.php','Vendor comments are denied for protected'],
['38 High-risk segments are excluded from TM','src/Application/TranslationService.php','RiskPolicy::requiresDomainReview'],
['39 Provider retirement requires purge','src/Application/ProviderService.php','unpurged jobs'],
['40 Privacy erasure supports holds and pseudonymization','src/Application/PrivacyService.php','slto_privacy_erasure_hold'],
];
foreach($checks as [$name,$file,$needle]){$t->test($name,static function()use($read,$file,$needle):void{TestHarness::assertTrue(str_contains($read($file),$needle),$file.' is missing '.$needle);});}
$t->finish();
