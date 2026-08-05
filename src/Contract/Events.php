<?php

declare(strict_types=1);

namespace Sabri\Localization\Contract;

final class Events
{
    public const ALL = array(
        'LocaleEnabled', 'LocaleDegraded', 'LocaleDeprecated', 'LocaleDisabled',
        'TranslatableResourceChanged', 'TranslationMarkedStale', 'TranslationSubmitted',
        'TranslationApproved', 'TranslationRejected', 'TranslationReleased', 'TranslationRetired',
        'TerminologyEntryApproved', 'TerminologyEntryDeprecated',
        'LocaleBundleBuilt', 'LocaleBundleActivated', 'LocaleBundleRolledBack',
        'CriticalTranslationDefectDetected', 'TranslationCorrectionReleased',
        'MachineTranslationDraftReceived', 'MachineTranslationJobReviewed',
        'TranslationVendorJobPurged', 'LocalizationCoverageDegraded',
    );
}
