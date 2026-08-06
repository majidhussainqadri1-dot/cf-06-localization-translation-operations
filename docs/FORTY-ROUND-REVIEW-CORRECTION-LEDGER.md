# CF-06 — Forty-Round Review, Correction and Retest Ledger

**Candidate:** 1.0.0-rc.3
**Review law:** each round was executed as review → correction → fresh retest; no round is counted merely because the same test was repeated.

| Round | Review surface | Defect found | Correction applied | Retest evidence |
|---:|---|---|---|---|
| 1 | Transaction depth/commit failure | Double decrement could poison later requests | Single-scope depth reset; checked rollback | `Executable static guard + PHP lint` |
| 2 | Nested rollback | Rollback result ignored | Rollback failure now becomes integrity error | `Guard 02` |
| 3 | Repository reads | DB errors looked like empty results | All canonical reads check database error state | `Guard 03` |
| 4 | Rate limiting | DB outage could fail open | Write/read failure now denies request | `Guard 04` |
| 5 | Job deduplication | Different job types could collide | Compound job_type + dedupe key | `Guard 05` |
| 6 | Job payloads | Unchecked/oversized JSON | Bounded JSON with exception-safe decode | `Guard 06` |
| 7 | Outbox integrity | Malformed payload could dispatch | Hash and JSON validation before hooks | `Guard 07` |
| 8 | Event hook mapping | sanitize_key could collapse event names | Deterministic snake-case hook mapping | `Guard 08` |
| 9 | Audit chain head | Read failure could silently restart chain | Read errors block audit write | `Guard 09` |
| 10 | Audit lock lifecycle | Release failure was ignored | Advisory lock release is verified | `Guard 10` |
| 11 | Authorization dependency | WordPress role could bypass File 00 | Current File 00 assertion is mandatory | `Guard 11` |
| 12 | Authorization filter | Filter could grant alternate authority | Extension filter is deny-only | `Guard 12` |
| 13 | Integration acceptance | Bare booleans had no durable evidence | Versioned evidence table/service | `Guard 13` |
| 14 | Integration verification | No independent evidence hook | Explicit independent verification contract | `Guard 14` |
| 15 | Integration expiry | Stale acceptance could remain active | Expiry checked at write and read | `Guard 15` |
| 16 | Extraction inventory | No durable extraction evidence | Owner/commit/inventory/extraction evidence table | `Guard 16` |
| 17 | Extraction verification | Inventory could be self-asserted | Independent staging verifier required | `Guard 17` |
| 18 | QA environment | QA rows lacked environment identity | Environment/plugin/build/test evidence added | `Guard 18` |
| 19 | QA artifact binding | QA could be detached from artifact | Artifact reference and SHA-256 required | `Guard 19` |
| 20 | Release approvals | Single approved_by field was insufficient | Dedicated approval ledger | `Guard 20` |
| 21 | Separation of duties | One approver could activate release | Two distinct roles and actors required | `Guard 21` |
| 22 | Step-up proof | Old authentication could approve release | 15-minute step-up evidence window | `Guard 22` |
| 23 | Activation integrations | Filter booleans controlled activation | Verified IntegrationService gate | `Guard 23` |
| 24 | Activation approval | Bundle activation lacked dual approval | Dual release approval asserted | `Guard 24` |
| 25 | Release exactness | All approved locale units could be swept | Only manifest-bound units release | `Guard 25` |
| 26 | Rollback reconciliation | Unit-to-bundle relations remained stale | Old and target units reconciled exactly | `Guard 26` |
| 27 | Transactional side effects | Cache flush occurred inside transaction | Cache invalidation moved after commit | `Guard 27` |
| 28 | Public delivery integrity | Active row served without re-verification | Hash and signature verified at read | `Guard 28` |
| 29 | Project scale | Unbounded target locale fan-out | 1–25 target locale bound | `Guard 29` |
| 30 | Project source truth | Mixed source locales could enter snapshot | Resource source locale must match project | `Guard 30` |
| 31 | Risk classification | Arbitrary risk ceiling accepted | Enumerated risk ceiling | `Guard 31` |
| 32 | Assignment time | Expired assignment could be created | Future expiry and due-order validation | `Guard 32` |
| 33 | Conflicts | Conflict status defaulted without declaration | Explicit cleared declaration required | `Guard 33` |
| 34 | Assignee legitimacy | Any WordPress user could be assigned | File 00 approval/suspension recheck | `Guard 34` |
| 35 | Review accountability | Reject/change could omit reason | Reason required for negative decisions | `Guard 35` |
| 36 | Independent domain review | Same reviewer could cover both stages | Domain reviewer cannot equal linguistic reviewer | `Guard 36` |
| 37 | Vendor contextual queries | Sensitive comments used keyword-only check | Protected data/risk units deny vendor audience | `Guard 37` |
| 38 | Translation memory privacy | High-risk approved text could enter TM | TM limited to non-high-risk C1/C2 | `Guard 38` |
| 39 | Provider retirement | Failed/rejected jobs could evade purge | Every non-purged job blocks retirement | `Guard 39` |
| 40 | Privacy lifecycle | Erasure omitted holds and operational identities | Hold hook + bounded pseudonymization coverage | `Guard 40` |

## Post-round closure correction

A final version-coherence check found that a contract-only upgrade could be skipped when the database schema version was already current. `Activator::maybeUpgrade()` now compares both schema and public-contract versions, and activation persists both versions with checked `update_option()` semantics. This additional correction was retested by the exact-source quality gate and does not replace or renumber the forty distinct review rounds above.

## Closure judgment

The forty distinct executable guards are run by `tests/review-rounds-40.php` from the exact-source quality gate on PHP 8.1 and PHP 8.3. They supplement—not replace—the behavioral, architecture, ownership, security/privacy, lifecycle and release suites, the real WordPress/MySQL integration test, deterministic package parity, ZIP integrity, secret-pattern and requirements-traceability guards.

This ledger establishes closure only for the repository/source-candidate scope. Hostinger-equivalent staging, real companion modules/providers, human linguistic acceptance, browser/device/accessibility evidence, restore/rollback rehearsal, independent security/privacy acceptance, Founder sign-off, live deployment and operational monitoring remain separate gates.
