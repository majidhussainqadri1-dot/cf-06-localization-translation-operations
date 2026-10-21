# Operations Runbook

Named roles: Localization Owner, Urdu/English/Arabic leads, medical/homeopathic reviewer, Sharīʿah reviewer, legal/privacy/security/financial reviewers, provider/key custodian and release operator.

Monitor queue lag/dead letters, untranslated/stale/critical coverage, QA failures, provider health/retention/purge, bundle build/signature/activation, fallback use and privacy/security anomalies. Alerts must be bounded and actionable; missing telemetry is unknown, not green.

Incidents: disable affected provider or runtime, preserve evidence, prevent new release, reconcile jobs/bundles, assess privacy/domain impact, restore safely, notify where required, document lessons and re-run controls. Quarterly restore/rollback and provider-exit exercises are required after activation.


## Live-state truth discipline

Repository, CI, staging and live production are separate evidence domains. The health surface must not infer a live deployment from a green repository workflow or from generic production checkboxes. Production status requires an exact deployed-source commit (`SLTO_DEPLOYED_SOURCE_COMMIT`), installed schema/contract parity, production acceptance evidence, and an independent `slto_verify_live_deployment_parity` decision. Staging acceptance is separately attested through `slto_verify_staging_acceptance_evidence`. Migration state, deployed database state and runtime verification remain deployment evidence and are not created by a source build.
