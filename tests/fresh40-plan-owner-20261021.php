<?php
declare(strict_types=1);
/** Static source ownership / requirements parity only, NOT runtime acceptance. */
$root=dirname(__DIR__);
$checks=array(
    array("CF06-FR-001", "Locale registry", "src/Application/LocaleService.php", "class LocaleService"),
    array("CF06-FR-002", "Resource catalog", "src/Application/ResourceService.php", "class ResourceService"),
    array("CF06-FR-003", "Canonical source locale and version", "src/Application/ResourceService.php", "source_locale"),
    array("CF06-FR-004", "Translatability and extraction", "src/Application/ExtractionService.php", "class ExtractionService"),
    array("CF06-FR-005", "Risk classification", "src/Domain/Translation/RiskPolicy.php", "class RiskPolicy"),
    array("CF06-FR-006", "Translation projects", "src/Application/ProjectService.php", "class ProjectService"),
    array("CF06-FR-007", "Assignments and qualifications", "src/Application/ProjectService.php", "slto_verify_assignment_qualification"),
    array("CF06-FR-008", "Translation-unit lifecycle", "src/Domain/Workflow/StateMachine.php", "class StateMachine"),
    array("CF06-FR-009", "Versioned comments/queries", "src/Application/TranslationService.php", "class TranslationService"),
    array("CF06-FR-010", "Translation memory constraints", "src/Application/TranslationService.php", "translation_memory_reuse_allowed"),
    array("CF06-FR-011", "External vendor boundary", "src/Application/ProviderService.php", "class ProviderService"),
    array("CF06-FR-012", "Terminology registry", "src/Application/TerminologyService.php", "class TerminologyService"),
    array("CF06-FR-013", "Medical qualified review", "src/Domain/Translation/RiskPolicy.php", "requiresDomainReview"),
    array("CF06-FR-014", "Sharīʿah qualified review", "src/Application/TranslationService.php", "domain_review"),
    array("CF06-FR-015", "Legal/financial/privacy qualified review", "src/Domain/Translation/RiskPolicy.php", "requiresDomainReview"),
    array("CF06-FR-016", "Transliteration owner separation", "src/Contract/Manifest.php", "search_transliteration"),
    array("CF06-FR-017", "Protected numbers/units/placeholders", "src/Domain/Translation/NumberUnitGuard.php", "class NumberUnitGuard"),
    array("CF06-FR-018", "External MT eligibility", "src/Application/MachineTranslationService.php", "class MachineTranslationService"),
    array("CF06-FR-019", "MT preprocessing/redaction", "src/Domain/Translation/Redactor.php", "class Redactor"),
    array("CF06-FR-020", "Machine translation draft-only", "src/Application/MachineTranslationService.php", "machine_draft"),
    array("CF06-FR-021", "Governed automation suggestions", "src/Application/TerminologyService.php", "class TerminologyService"),
    array("CF06-FR-022", "Provider feedback/training policy", "src/Application/ProviderService.php", "training_allowed"),
    array("CF06-FR-023", "Automated linguistic QA", "src/Application/QaService.php", "class QaService"),
    array("CF06-FR-024", "In-context accessibility/RTL QA", "src/Application/BundleService.php", "slto_verify_bundle_qa_evidence"),
    array("CF06-FR-025", "Deterministic locale bundle", "src/Domain/Bundle/DeterministicBundle.php", "class DeterministicBundle"),
    array("CF06-FR-026", "Critical language coverage", "src/Infrastructure/Repository/LocalizationRepository.php", "coverage"),
    array("CF06-FR-027", "Staged release and rollback", "src/Application/BundleService.php", "function rollback"),
    array("CF06-FR-028", "Translated content owner-publication relation", "src/Application/ContentLinkService.php", "class ContentLinkService"),
    array("CF06-FR-029", "Versioned style guides", "src/Application/TerminologyService.php", "style"),
    array("CF06-FR-030", "Translation feedback", "src/Application/FeedbackService.php", "class FeedbackService"),
    array("CF06-FR-031", "Staleness propagation", "src/Infrastructure/DependencyInvalidator.php", "class DependencyInvalidator"),
    array("CF06-FR-032", "Coverage and quality metrics", "src/Application/MetricsService.php", "class MetricsService"),
    array("CF06-FR-033", "Retention/deletion/provider propagation", "src/Application/PrivacyService.php", "class PrivacyService"),
    array("CF06-FR-034", "Locale/provider deprecation", "src/Application/LocaleService.php", "function"),
    array("CEN-01", "File00 independent auth", "src/Contract/Manifest.php", "identity_membership_capability_authority"),
    array("CEN-02", "File20 language/shell ownership", "src/Contract/Manifest.php", "user_language_preference"),
    array("CEN-03", "File25 RTL visual owner", "src/Contract/Manifest.php", "visual_rtl_components"),
    array("CEN-04", "File26 search/transliteration owner", "src/Contract/Manifest.php", "search_transliteration"),
    array("CEN-05", "Future40 gated activation", "src/Contract/PlanCompliance.php", "'default_state' => 'disabled'"),
    array("CEN-06", "Live-deployed must demand independent proof", "src/Application/HealthService.php", "slto_verify_live_deployment_parity"),
);
if(count($checks)!==40){throw new RuntimeException('40 check plan changed.');}
$bad=array();
foreach($checks as $i=>$row){
 [$id,$title,$file,$anchor]=$row;
 $full=$root.'/'.$file;
 $source=is_file($full)?file_get_contents($full):false;
 $pass=is_string($source)&&str_contains($source,$anchor);
 echo ($pass?'PASS ':'FAIL ').sprintf('%02d',$i+1)."/40 $id: $title -> $file\n";
 if(!$pass){$bad[]=$id;}
}
echo 'CF-06 FRESH 40 plan/source anchors: '.(40-count($bad)).'/40 passing (source only, NOT staging or live).'."\n";
exit($bad===array()?0:1);
