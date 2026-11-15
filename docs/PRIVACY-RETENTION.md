# Privacy, Retention and Provider Exit

- Collect only resource context, assignment and operational metadata required for declared localization purposes.
- C4/C5/private source/target text is encrypted with AES-256-GCM and purpose/owner-bound AAD.
- External MT excludes C4/C5/private data by policy; approved lower-risk jobs are redacted and hash-audited.
- Provider training is hard-disabled; credential values are never stored in plugin tables.
- Privacy export exposes only the requesting user’s assignments/comments/feedback and excludes other actors’ private data.
- Erasure anonymizes eligible operational identity while preserving required security/release/audit evidence; encrypted payload and provider deletion require explicit evidence.
- Vendor jobs have purge dates and deletion evidence. A purge is not accepted merely because a provider adapter returns data: non-empty evidence must pass the independent `slto_verify_provider_purge_evidence` contract for the exact job/provider/reference. Provider deprecation requires outstanding-job reconciliation and exit proof.
- Retention schedules and lawful/clinical/financial exceptions are finalized before activation per jurisdiction and native owner.


## V12.3 immutable language policy retention and minimization

The `slto_v123_edition_*` revision options hold only the versioned locale/alias/reserve policy, an opaque non-secret Founder approval reference, a policy hash, and nonpersonal registration timestamp/state. They **do not** repeat the registering WordPress user's ID; the restricted, tamper-evident security audit retains actor identity under the existing governance exception. Public policy examples must not include private manuscript contents or patient/vendor data. The conservative uninstall intentionally retains governed policy revisions; any purge requires an owner-approved retention/deletion assessment.

## Operational actor pseudonymization

Mutable operational actor references are pseudonymized during erasure into a deterministic high-ID namespace. Allocation explicitly refuses collision with a real WordPress user account; immutable audit-chain actor metadata remains retained only under the stated security/governance integrity exception.


## V12.3 owner verifier projection

Public-version review passes only allowlisted public source/version/link/owner metadata to `slto_verify_current_edition_publication`, never arbitrary caller-supplied clinical, patient or credential fields. The 100-record maximum is enforced before projection. Synthetic negative tests in `tests/wp-v123-edition-policy-registry.php` cover this data-minimization boundary.
