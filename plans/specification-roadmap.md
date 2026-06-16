# FS-Finanzportal Specification Roadmap

This specification maps the current implementation to a delivery roadmap. It is
based on the repository state on 2026-06-16 and should be read with
`docs/project-specification.md`, `docs/architecture.md`,
`docs/frontend-workflows.md`, `docs/access-management.md`, and
`docs/workflow-process.md`.

## Document Control

| Field | Value |
|---|---|
| Document type | Product and implementation specification roadmap |
| Primary audience | Project maintainers, implementation agents, reviewers, and future developers |
| Product scope | Local Docker-based WordPress finance workflow prototype for Fachschaften and AStA finance review |
| Delivery model | Setup-time WordPress generation through WP-CLI, JSON configuration, standard WordPress plugins, and Keycloak |
| Architecture constraint | No project-specific runtime PHP, no shared workflow table with runtime Keycloak-claim row filtering |
| Canonical setup gate | `./scripts/verify-setup.sh` |
| Requested output | Tabular Markdown project specification and roadmap in `plans/specification-roadmap.md` |

## Product Boundary

| Boundary area | In scope | Out of scope / non-goal | Current implementation evidence |
|---|---|---|---|
| Identity | Keycloak realm, groups, roles, demo users, OIDC login into WordPress | Runtime claim-based row filtering as the primary security boundary | `scripts/configure-keycloak.sh`, `wordpress/config/oidc/openid-connect-generic.settings.json`, `docs/roles.md` |
| Application shell | WordPress portal pages generated under `/dashboard/` | wp-admin-first workflow for normal users | `scripts/wp-eval/portal/pages.php`, `docs/frontend-workflows.md` |
| Content model | One scoped Beschluss CPT and one scoped Zahlungsanweisung CPT per Fachschaft | One shared Beschluss or Zahlungsanweisung table across all Fachschaften | `wordpress/config/fachschaften.json`, `wordpress/config/portal/pods.json`, `scripts/wp-eval/pods/schema.php` |
| Workflow behavior | Setup-generated forms, lists, detail pages, browser-side guards, role-gated pages, setup verification | Custom runtime state-machine plugin unless an explicit architecture change is approved | `scripts/wp-eval/portal/templates.php`, `wordpress/config/portal/forms.json`, `docs/workflow-process.md` |
| Reporting | AStA overview pages, budget summary, reporting page, source-row aggregation, CSV exports | External BI system or paid reporting plugin dependency for core prototype behavior | `scripts/wp-eval/portal/templates.php`, `scripts/tests/reporting-calculation.test.mjs` |
| Operations | Reproducible Docker setup, WP-CLI provisioning, setup verification, Betrieb page | Manual database edits as the normal operating model | `compose.yaml`, `scripts/setup.sh`, `scripts/verify-setup.sh`, `scripts/wp-eval/portal/templates.php` |

## Current Implementation Map

| Area | Status | Implemented behavior | Primary evidence | Verification evidence |
|---|---|---|---|---|
| Docker stack | Implemented | WordPress, MariaDB, Keycloak, PostgreSQL, and one-shot WP-CLI setup services | `compose.yaml`, `scripts/setup.sh` | `scripts/verify/env.sh`, Docker health checks used by setup and verification |
| WordPress setup | Implemented | Installs/configures plugins, imports Pods model, generates pages, configures access plugins, seeds demo data | `scripts/wp-install.sh`, `scripts/configure-wordpress.sh`, `scripts/wp-eval/ensure-portal-content.php` | `scripts/wp-eval/verify-wordpress-config.php` |
| Fachschaft model | Implemented | Generates `b_<slug>` and `za_<slug>` workflow post types per Fachschaft | `wordpress/config/fachschaften.json`, `scripts/wp-eval/pods/schema.php`, `scripts/wp-eval/pods/fields.php` | `scripts/wp-eval/verify/content-model.php` |
| Roles and capabilities | Implemented | Creates scoped reader/finance roles, global AStA/admin roles, hidden admin bar roles, and read/edit caps | `wordpress/config/portal/roles.json`, `scripts/wp-eval/lib/roles.php` | `scripts/wp-eval/verify/access.php`, `scripts/wp-eval/verify/pages.php` |
| Dashboard | Implemented | Role-gated dashboard sections for Fachschaften, AStA overview, reporting, and administration | `scripts/wp-eval/portal/pages.php`, `wordpress/config/portal/templates/dashboard-card.html` | `scripts/wp-eval/verify/pages.php`, `scripts/wp-eval/verify/frontend.php` |
| Scoped lists | Implemented | Search, status filtering, pagination, CSV export, detail links, edit links gated by role/status | `scripts/wp-eval/portal/templates.php`, `wordpress/config/portal/assets/table-controls.js` | `scripts/wp-eval/verify/frontend.php`, `scripts/wp-eval/verify/pages.php` |
| Global overview pages | Implemented | AStA/admin views for all Beschlüsse and Zahlungsanweisungen across scoped post types | `scripts/wp-eval/portal/templates.php` | `scripts/wp-eval/verify/frontend.php` |
| Create/edit forms | Implemented with prototype limits | Pods frontend forms, contextual redirects, browser-side sanity checks, role-gated workflow edit surfaces | `wordpress/config/portal/forms.json`, `scripts/wp-eval/portal/templates.php`, `wordpress/config/portal/assets/contextual-form-redirect.js` | `scripts/wp-eval/verify/frontend.php` |
| Beschluss workflow | Implemented with setup-time/UI enforcement | Draft, approved, rejected statuses; decision metadata; edit action only for draft records | `wordpress/config/portal/pods.json`, `docs/workflow-process.md`, `scripts/wp-eval/portal/templates.php` | `scripts/wp-eval/verify/frontend.php`, `scripts/wp-eval/verify/content-model.php` |
| Zahlungsanweisung workflow | Implemented with setup-time/UI enforcement | Draft, submitted, correction requested, cancelled, executed statuses; reviewer and finance edit paths | `wordpress/config/portal/pods.json`, `docs/workflow-process.md`, `scripts/wp-eval/portal/pages.php` | `scripts/wp-eval/verify/frontend.php` |
| Standard payment budget context | Implemented as portal guard | Standard payments reference approved Beschlüsse and use generated budget source data to show/open budget | `scripts/wp-eval/portal/templates.php`, `wordpress/config/portal/assets/payment-type-lock.js` | `scripts/wp-eval/verify/frontend.php` |
| Vorkasse branch | Implemented with prototype limits | Vorkasse uses the same payment CPT, skips Beschluss budget context, records method/justification/recipient details | `wordpress/config/demo/vorkasse.json`, `wordpress/config/portal/pods.json`, `scripts/wp-eval/portal/templates.php` | `scripts/wp-eval/verify/frontend.php`, `scripts/wp-eval/verify/demo-oidc.php` |
| Reporting | Implemented and still extensible | Budget overview, period table, open work table, executed totals, Fachschaft totals, record-count context | `scripts/wp-eval/portal/templates.php`, `scripts/tests/reporting-calculation.test.mjs` | `scripts/verify/reporting-calculation.sh`, `scripts/wp-eval/verify/frontend.php` |
| UX system | Implemented baseline | Dashboard cards, report context labels, recovery panels, table zebra/hover states, summary cards, responsive portal CSS | `wordpress/config/portal/assets/portal.css`, `plans/ux-ui-analysis-report.md` | `scripts/wp-eval/verify/frontend.php` |
| Access-denied recovery | Implemented baseline | Members error copy and dashboard recovery panel guide users back to allowed routes or admin support | `scripts/wp-eval/portal/plugin-settings.php`, `scripts/wp-eval/portal/pages.php` | `scripts/wp-eval/verify/plugin-settings.php`, `scripts/wp-eval/verify/pages.php` |
| Audit visibility | Partial | Workflow log tables and document context panels are visible; Meta Ledger remains background audit storage | `scripts/wp-eval/portal/templates.php`, `docs/workflow-process.md` | `scripts/wp-eval/verify/frontend.php` |
| Notifications | Partial | Payment detail pages contain structured mailto drafts and follow-up contact panels | `scripts/wp-eval/portal/templates.php` | `scripts/wp-eval/verify/frontend.php` |
| Operations page | Implemented baseline | Admin-only Betrieb page surfaces readiness checks, normalization summary, and recovery commands | `scripts/wp-eval/portal/templates.php` | `scripts/wp-eval/verify/frontend.php`, `scripts/wp-eval/verify/pages.php` |

## Personas And Workflows

| Persona | Role(s) | Primary needs | Implemented portal path | Current gaps |
|---|---|---|---|---|
| Fachschaft finance | `fs_<slug>_finance` | Create/edit Beschlüsse, prepare and submit Zahlungsanweisungen, answer Rückfragen | `/dashboard/<slug>/`, scoped list/create/edit/detail pages | Server-side transition and budget enforcement beyond browser checks |
| Fachschaft reader | `fs_<slug>_reader` | Read own Fachschaft records without edit access | `/dashboard/<slug>/beschluesse/`, `/dashboard/<slug>/zahlungsanweisungen/` | More reader-specific summaries and empty states could reduce table scanning |
| AStA finance/reviewer | `asta_finance`, `asta_reviewer` | Review payments across Fachschaften, request clarification, mark executed, use reporting | `/dashboard/beschluesse/`, `/dashboard/zahlungsanweisungen/`, `/dashboard/berichte/` | Automated notifications and richer reporting filters are still future work |
| Auditor | `auditor` | Read scoped records without AStA work surfaces | Fachschaft-scoped pages through read capabilities | Richer audit history view is not yet implemented |
| Portal admin | `administrator`, `portal_admin` | Operate setup, inspect readiness, recover configuration issues | `/dashboard/betrieb/`, wp-admin access | Backup/restore workflow remains mostly documented, not interactive |
| Unassigned user | `fs_portal_empty` or subscriber-like account | Understand why no data is available and recover through admin support | `/dashboard/` recovery panel | Self-service assignment is out of scope for the prototype |

## Requirements Matrix

| ID | Requirement | Priority | Status | Acceptance evidence | Notes |
|---|---|---:|---|---|---|
| R-001 | Preserve no-runtime-custom-PHP product behavior | P0 | Implemented | No project runtime plugin is mounted; setup-only PHP lives in `scripts/wp-eval/` | Future changes must document any architecture exception |
| R-002 | Keep Fachschaft data physically scoped by post type | P0 | Implemented | `b_<slug>` and `za_<slug>` generated from `wordpress/config/fachschaften.json` | Avoid shared table design unless a new security model is approved |
| R-003 | Enforce page access through WordPress roles/capabilities and Members permissions | P0 | Implemented | `_members_access_role` metadata generated and verified | Access-denied recovery is UX guidance, not a permissions bypass |
| R-004 | Keep setup idempotent | P0 | Implemented baseline | Upsert helpers and verification cover pages, Pods templates, roles, demo data | Any new setup mutation must use update/upsert logic |
| R-005 | Provide scoped list/detail/create/edit workflow pages | P0 | Implemented | Generated pages under `/dashboard/<slug>/...` | Direct public workflow permalinks remain disabled/avoided |
| R-006 | Provide AStA cross-Fachschaft overview and reporting | P1 | Implemented baseline | Global overview pages and `/dashboard/berichte/` generated and verified | Date range filtering and richer exports remain future work |
| R-007 | Support Vorkasse as a first-class payment branch | P1 | Implemented baseline | Vorkasse fields, demo records, detail rendering, and no-budget context verified | Server-side field requirements remain a future hardening item |
| R-008 | Expose visible workflow history | P1 | Partial | Workflow-log tables generated from domain fields | Rich Meta Ledger history is not yet exposed as scoped frontend UI |
| R-009 | Improve handoff visibility | P1 | Partial | Mailto drafts and notification panels exist on payment detail pages | Automated event notifications are not implemented |
| R-010 | Provide operational readiness and recovery surface | P1 | Implemented baseline | `/dashboard/betrieb/` generated and verified for admins | Backup/restore guidance can become more concrete |
| R-011 | Improve UX clarity and trust | P1 | Implemented baseline | Dashboard hierarchy, report context, recovery panels, and table styling added | Continued usability testing should refine microcopy and mobile behavior |
| R-012 | Add regression gates for critical calculations and generated UI | P1 | Partial | Node reporting test and WP verifier assertions run in setup verification | Browser-level or Playwright-style tests are still missing |
| R-013 | Harden workflow integrity if browser checks are bypassed | P0 for production, P2 for prototype | Missing | No server-side state-machine hook exists by design | Requires architecture decision under no-runtime-PHP constraint |
| R-014 | Protect direct media downloads by workflow permissions | P2 | Missing | Known limitation documented in `docs/access-management.md` | Requires protected media plugin or runtime download gate |

## Milestone Roadmap

| Milestone | Target outcome | Feature scope | Dependencies | Acceptance gate |
|---|---|---|---|---|
| M0 - Baseline Reproducibility | Current stack can be rebuilt and verified from repository state | Docker stack, WP install, Keycloak config, Pods import, role/page generation, demo data | `.env`, Docker, WordPress/Keycloak containers | `./scripts/setup.sh` completes and then `./scripts/verify-setup.sh` exits successfully |
| M1 - Scoped Workflow Prototype | Fachschaft finance workflows are usable without wp-admin | Scoped CPTs, role-gated pages, forms, detail pages, list controls, workflow logs | M0 | Demo users can view/create/edit allowed records; verifier proves scoped access and generated page structure |
| M2 - AStA Review And Reporting | Cross-Fachschaft review and reporting support finance operations | Global overviews, payment queues, reporting page, budget summary, CSV export, reporting regression test | M1 | AStA demo users can render global pages; report rows/totals are verified; Node reporting tests pass |
| M3 - UX And Recovery Hardening | Portal feels guided, trustworthy, and recoverable | Dashboard hierarchy, report context labels, recovery panels, table scan states, clearer empty states | M2 | Frontend verifier asserts dashboard/report/CSS/recovery markers; manual spot-check with demo roles confirms routes |
| M4 - Integrity Hardening | Invalid workflow state changes and budget overruns are blocked beyond browser controls | Transition rules, standard-payment budget guard, Vorkasse requirements, invalid-post handling | M1-M3 and architecture decision | A new verifier or runtime-safe enforcement mechanism proves invalid submissions cannot persist |
| M5 - Audit And Notifications | Handoffs and history are easier to inspect | Automated notifications or managed notification setup, scoped audit views, richer Betrieb diagnostics | M2-M4 | Reviewer/admin can trace workflow events without raw database inspection; notification behavior has deterministic tests |
| M6 - Operational Readiness | Non-developer recovery and maintenance are documented and tested | Backup/restore runbook, data export/import guidance, health checks, verifier diagnostics | M0-M5 | Betrieb page and docs cover recovery steps; setup verifier reports actionable failures |

## Feature Backlog

| Feature | Milestone | Priority | Current status | Implementation direction | Verification strategy |
|---|---|---:|---|---|---|
| Setup idempotence guard expansion | M0 | P0 | Partial | Add verifier checks for repeated setup runs and unchanged page/template counts | Run setup twice and compare generated object counts |
| Role/access matrix coverage | M1 | P0 | Implemented baseline | Keep role definitions in `roles.json` and generate access metadata from helpers | Extend `scripts/wp-eval/verify/access.php` when adding roles |
| Stronger workflow transition validation | M4 | P0 | Missing | Evaluate setup-compatible enforcement first; document any required runtime hook explicitly | Add negative tests for illegal status transitions |
| Server-side standard-payment budget enforcement | M4 | P0 | Missing | Decide whether a runtime gate, plugin configuration, or import-time reconciliation is acceptable | Attempt over-budget submission and verify rejection or repair |
| Vorkasse server-side completeness checks | M4 | P1 | Missing | Enforce or reconcile method, justification, and recipient fields for bank transfers | Add seeded invalid Vorkasse records and verify normalization/reporting |
| Rich report filters | M2 | P1 | Missing | Add date range and Fachschaft controls to generated report page without shared runtime query model | Add report fixture tests and WP rendered page assertions |
| Report export improvements | M2 | P1 | Partial | Extend CSV export or add report-specific export for summary tables | Verify exported visible rows and totals match rendered state |
| Scoped audit view | M5 | P1 | Missing | Investigate Meta Ledger frontend feasibility or generated admin/auditor pages | Verify auditors cannot see unrelated records and admins can trace changes |
| Workflow notifications | M5 | P2 | Partial | Keep mailto drafts as fallback; evaluate standard notification plugin setup if deterministic | Verify notification config or generated drafts per workflow event |
| Protected media access | M6 | P2 | Missing | Evaluate protected-media plugin or explicit architecture exception for download gate | Direct file URL should respect parent workflow permissions |
| Backup and restore runbook | M6 | P1 | Missing | Add documented commands and Betrieb page references for DB/volume backup | Verify commands in a clean local environment |
| Browser-level UX regression checks | M3 | P2 | Missing | Add Playwright or equivalent only for high-value portal screens | Snapshot dashboard, lists, report, access-denied states for demo users |
| Accessibility pass | M3 | P2 | Missing | Review generated forms, tables, focus states, labels, and contrast | Add axe or manual checklist results to verification docs |
| Role-specific dashboard summaries | M3 | P2 | Partial | Add compact status summaries per role without introducing runtime queries | Verify summaries derive from already-rendered scoped source rows |

## Acceptance Criteria

| Area | Acceptance criteria | Evidence source | Owner lane |
|---|---|---|---|
| Architecture | Feature uses setup-time generation, existing plugin configuration, or an explicitly approved architecture change | Design note in docs plus implementation under `scripts/wp-eval/` or `wordpress/config/` | WordPress setup |
| Access | Users can only view pages and records allowed by their WordPress roles and scoped capabilities | `scripts/wp-eval/verify/access.php`, `scripts/wp-eval/verify/pages.php` | WordPress access |
| Content model | Every Fachschaft has scoped Beschluss and Zahlungsanweisung post types with expected fields | `scripts/wp-eval/verify/content-model.php` | Pods setup |
| Workflow UI | Lists, forms, details, workflow logs, and recovery paths render for intended roles | `scripts/wp-eval/verify/frontend.php` | Portal generation |
| Reporting | Report totals and date buckets handle executed, open, cancelled, and no-data cases predictably | `scripts/tests/reporting-calculation.test.mjs`, rendered report verifier | Portal/reporting |
| Operations | Setup can be regenerated and verified from a clean Docker stack | `scripts/setup.sh`, `scripts/verify-setup.sh` | Docker/setup |
| Documentation | Product behavior, limits, and roadmap remain aligned with implementation | `docs/*.md`, `plans/specification-roadmap.md` | Documentation |

## Risks And Decisions

| Risk / decision | Impact | Current position | Next decision point |
|---|---|---|---|
| No runtime custom PHP limits hard enforcement | Browser checks can be bypassed in a prototype | Acceptable for current prototype; production needs stronger enforcement | Before M4 starts |
| Public WordPress uploads are not permission-guarded | Attachment URLs can be accessed directly if known | Documented known limit; form picker is scoped to current record | Before handling real private documents |
| Reporting is client/generated-source based | Large datasets may make page rendering heavy | Acceptable for scoped prototype/demo size | Before production data volume grows |
| Members/Pods shortcode behavior is a product dependency | Plugin behavior changes can affect rendering and access | Versioned Docker image and setup verification reduce drift | During plugin/image upgrades |
| Verification is markup-heavy | It can miss browser-only regressions and overfit strings | Useful setup gate today; add scenario/browser tests for high-risk areas | Before M3/M4 completion |
| Automated notifications may require runtime triggers | Could conflict with setup-only philosophy | Keep generated mailto drafts until deterministic setup-only option is selected | Before M5 starts |

## Verification Roadmap

| Verification layer | Current checks | Missing checks | Recommended next step |
|---|---|---|---|
| Static/source hygiene | `git diff --check`, shell scripts with `set -euo pipefail` | PHP lint depends on available PHP runtime outside containers | Add containerized PHP lint helper if local PHP remains unavailable |
| Node regression | Reporting date/status aggregation tests | More report fixtures for Fachschaft totals and zero-value trust states | Extend `scripts/tests/reporting-calculation.test.mjs` |
| WordPress setup verifier | Plugins, content model, roles, pages, frontend rendering, demo data | Negative POST/submission behavior and repeated setup count comparison | Add targeted verifier modules rather than broad ad hoc checks |
| Keycloak verifier | OIDC discovery, theme, roles/groups | Host PHP dependency warning in keycloak verifier path | Remove or replace host PHP dependency in `scripts/verify/keycloak.sh` |
| Browser checks | None dedicated | Layout, responsive behavior, focus, and actual JavaScript interaction | Add narrow browser smoke tests for dashboard, lists, reports, and access denial |
| Operational checks | Docker health, Betrieb page readiness table | Backup/restore proof | Add documented backup smoke test once runbook exists |

## Delivery Sequence

| Sequence | Work package | Exit condition | Suggested files |
|---:|---|---|---|
| 1 | Freeze current roadmap baseline | `plans/specification-roadmap.md` exists and maps current implementation to milestones | `plans/specification-roadmap.md` |
| 2 | Fix verifier host dependency | Full setup verification has no misleading `php: command not found` warning | `scripts/verify/keycloak.sh`, `scripts/verify/env.sh` |
| 3 | Add repeated setup/idempotence proof | Running setup repeatedly does not duplicate generated pages, templates, roles, or demo records | `scripts/wp-eval/verify/pages.php`, `scripts/wp-eval/verify/demo-oidc.php` |
| 4 | Expand reporting tests | Reporting fixtures cover record count, zero money totals, Fachschaft summary, and cancelled payments | `scripts/tests/reporting-calculation.test.mjs` |
| 5 | Decide integrity enforcement approach | M4 architecture decision is documented before implementation starts | `docs/project-specification.md`, `docs/workflow-process.md` |
| 6 | Implement integrity hardening | Invalid transitions and over-budget standard payments are rejected or repaired by a verified mechanism | `scripts/wp-eval/portal/templates.php`, future enforcement module if approved |
| 7 | Add audit/notification milestone work | Admins/reviewers can trace handoffs and changes without raw DB inspection | `scripts/wp-eval/portal/templates.php`, `docs/workflow-process.md` |
| 8 | Add operational runbook | Backup/restore and failure recovery have tested commands and dashboard references | `docs/architecture.md`, `docs/agent-harness.md`, `scripts/verify/*` |

## Change Control

| Rule | Rationale | Required evidence |
|---|---|---|
| Any workflow behavior change must preserve scoped post type isolation | Fachschaft separation is the primary security model | Updated verifier for every affected Fachschaft role |
| Any setup script change must be idempotent | Setup is the product delivery mechanism | Re-run setup and verification, or add a verifier proving no duplication |
| Any new plugin dependency must be standard, reproducible, and documented | The project avoids hidden/manual runtime behavior | Updated docs, setup install step, and plugin verifier |
| Any architecture exception must be explicit | Runtime PHP or shared tables would change the project philosophy | Architecture note, migration plan, and acceptance tests |
| Any reporting change must include calculation coverage | Finance users need trustworthy totals | Node fixture test plus rendered report verifier update |
| Any UX change on generated pages must be reflected in setup inputs | Pages are regenerated from config and WP-CLI scripts | Source change under `wordpress/config/portal/` or `scripts/wp-eval/portal/` |
