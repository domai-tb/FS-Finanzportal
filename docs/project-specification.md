# FS-Finanzportal Specification

This document defines the current state of the project and the feature gaps that
remain. It is the baseline for future implementation work.

## 1. Current State

FS-Finanzportal is a local, Docker-based WordPress prototype for Fachschaft
finance workflows. The system is intentionally setup-driven:

- WordPress behavior is produced from imported configuration and WP-CLI setup
  scripts.
- Keycloak is the identity source for authentication and role assignment.
- Runtime custom PHP is intentionally avoided.
- Scoped WordPress post types are generated per Fachschaft to preserve physical
  separation between finance data sets.
- Frontend portal pages are the primary workflow surface; `wp-admin` is blocked
  or hidden for normal portal roles.
- Setup verification is part of the product contract, not an afterthought.

At this stage, the project already provides:

- Keycloak login and role mirroring
- Fachschaft-scoped Beschluss and Zahlungsanweisung content types
- Frontend list, detail, create, and edit pages
- Role-gated access using WordPress capabilities and Members permissions
- Seed/demo content and reproducible setup
- Generated workflow logs from domain fields
- Browser-side list controls for search, filtering, pagination, and CSV export
- AStA budget and payment queue summary strips with empty-state reporting
- AStA reporting page with period totals, open-workload summaries, executed
  payment totals, and Fachschaft totals
- Admin-only Betrieb page with setup-readiness checks and recovery notes
- Structured follow-up contact panels on payment detail pages
- Document-context panels on detail pages for audit visibility
- Background audit history via Meta Ledger

## 2. Product Definition

The project is not a generic CMS. It is a workflow prototype for finance
operations in student councils.

The intended system boundary is:

- WordPress stores workflow objects and portal pages.
- Keycloak stores identity, membership, and realm-level access data.
- Docker Compose defines local services and startup order.
- Setup scripts translate JSON configuration into the running system.

The product should continue to avoid:

- one shared workflow table with runtime Keycloak-claim filtering
- runtime WordPress plugins that implement project-specific business logic
- manual administration as the primary operational path

## 3. Missing Features

The project is functional, but not complete. The remaining gaps are below.

### 3.0 Status Snapshot

The table below summarizes the current implementation surface from the
perspective of future work:

| Area | Status | Implemented | Partial / missing | Unclear |
|------|--------|-------------|-------------------|---------|
| Server-side enforcement | Partial | Generated role-gated pages/forms, setup-time normalization, browser-side budget checks | No runtime state-machine hook; invalid POSTs still need stronger verification if the architecture stays setup-time only | Whether a stronger non-runtime integrity gate can cover all transition and budget cases |
| Notifications | Partial | Follow-up mailto links and structured notification drafts on payment detail pages | No automated workflow notifications | Whether automation belongs in setup-time generation or should remain manual |
| Audit visibility | Partial | Workflow logs and document-context panels on detail pages | Background Meta Ledger history is still not the visible audit UI | Which additional scoped history views are worth the extra surface area |
| Reporting | Partial | Unified reporting page with budget, workload, executed-payment, and Fachschaft totals; Node regression coverage for aggregation is wired into setup verification | No live WordPress/browser execution of the reporting calculation in this workspace | Whether broader date-range and browser-rendered coverage should join the setup gate |
| Operational hardening | Partial | Betrieb page, readiness checks, and recovery guidance | Recovery proof is still mostly verification-backed rather than runtime-monitored | How much of the ops surface should remain informational versus actionable |
| UX refinement | Partial | Scoped dashboards, queues, filters, CSV export, and mobile-conscious styling | Empty states and validation feedback still need iteration in more scenarios | Which portal interactions need further polish first |
| Regression coverage | Partial | Setup verification proves the generated structure and access model; Node regression coverage exists for reporting aggregation | No standalone unit/e2e suite; most assertions are still markup-based | Which behavior slices justify a dedicated scenario harness next |

### 3.1 Server-Side Enforcement

Current portal forms and list pages rely heavily on generated UI controls and
verification. That is acceptable for a prototype, but not a full integrity
boundary.

Missing feature:

- hard server-side enforcement for workflow transitions and budget rules, if the
  architecture remains within WordPress setup-time constraints

Goal:

- prevent invalid status changes, invalid Beschluss references, and budget
  overruns even when browser-side checks are bypassed

Current partial progress:

- setup-time normalization now clears `beschluss_ref` from Vorkasse records
  and normalizes invalid payment types back to `standard`
- the setup now generates an admin-only `/dashboard/betrieb/` readiness page
  with option/page checks and restore guidance, which partially covers the
  operational-hardening gap

### 3.2 Notifications and Follow-Up

The current baseline intentionally does not ship automated workflow
notifications.

Missing feature:

- structured notifications for submission, clarification, approval, execution,
  and rejection/cancellation events

Goal:

- make workflow handoffs visible without requiring manual portal polling or ad
  hoc email handling

Current partial progress:

- payment detail pages now expose structured notification drafts and follow-up
  mailto links for contacting both the portal administration and the AStA

### 3.3 Audit Visibility

Meta Ledger stores background post-meta history, but the visible user-facing
audit model is still lightweight and combines generated workflow logs with
document-context panels.

Missing feature:

- a richer audit and traceability view for admins and auditors

Goal:

- provide scoped history and change context without exposing unrelated records
  or requiring raw database inspection

Current partial progress:

- detail pages now expose a document-context panel with creation and
  last-modified metadata alongside the workflow log

### 3.4 Reporting

The current portal has overview tables, generated summary strips, and a
dedicated AStA reporting page, but reporting is still narrow.

Missing feature:

- richer reporting views, exports, and date-range filtering for period totals,
  pending work, executed payments, and Fachschaft-level summaries

Goal:

- answer finance-team questions without exporting data manually

Current partial progress:

- the generated report page now aggregates setup-time source rows into period,
  open-workload, executed-payment, and Fachschaft summary tables
- executed payments are grouped by execution date in the generated report
  sources, while open payments still fall back to submission/review dates when
  execution is not present
- reporting aggregation has a small Node regression test that checks the
  cancelled-payment and executed-date cases without needing Docker

### 3.5 Operational Hardening

The setup is reproducible, but operational guarantees are still basic.

Missing feature:

- backup and restore guidance
- explicit health and readiness dashboards
- clearer failure-mode reporting for setup and verification

Goal:

- make stack recovery and support easier for non-developers

Current partial progress:

- the generated Betrieb page now surfaces setup-readiness checks and recovery
  guidance for portal administrators and a setup-time data-integrity summary
- verification now renders the admin dashboard and Betriebs page to prove the
  discoverability and role-gated checklist behavior

### 3.6 User Experience Refinement

The portal is usable, but the workflow surface still needs iteration.

Missing feature:

- clearer empty states
- better validation feedback
- tighter mobile behavior
- role-specific guidance inside the portal

Goal:

- reduce friction for readers, finance users, reviewers, and auditors

### 3.7 Test Coverage and Regression Gates

The repo already has verification scripts, but the spec is ahead of exhaustive
behavioral coverage.

Missing feature:

- scenario-based tests for workflow transitions, access rules, and generated UI
  states

Goal:

- catch regressions before setup changes are merged

Current partial progress:

- setup verification already proves the main dashboard, reporting, and
  operations surfaces exist and render for the expected roles, but broader
  scenario coverage is still missing
- Vorkasse detail pages are now rendered and checked separately so the branchy
  payment workflow is not only seeded but also exercised in the frontend
- the reporting aggregation now has a dedicated Node regression test wired into
  the setup verification path to catch cancelled-payment and execution-date
  regressions before Docker-based verification runs

## 4. Goals

The project goals are prioritized as follows.

### P0

- Preserve the current no-runtime-custom-PHP model.
- Keep each Fachschaft physically isolated at the content-model level.
- Keep setup idempotent and repeatable.
- Keep access control enforceable through roles, capabilities, and page
  permissions.

### P1

- Enforce workflow integrity more strongly.
- Add useful reporting for finance operations.
- Improve audit visibility for admins and auditors.
- Improve setup and verification diagnostics.

### P2

- Add notifications and optional workflow automation.
- Refine portal UX and mobile behavior.
- Expand regression coverage for the generated portal.

## 5. Non-Goals

This project should not drift toward:

- a shared runtime database model for all Fachschaften
- bespoke runtime WordPress business logic
- a direct wp-admin-first operating model for portal users
- dependency on paid plugins for core workflow integrity
- hidden behavior that cannot be reproduced from setup inputs

## 6. Acceptance Criteria For Future Work

A future feature is acceptable only if:

1. It fits the setup-time architecture or explicitly documents an architecture
   change.
2. It preserves scoped access boundaries.
3. It has an idempotent setup path if it changes provisioning.
4. It has a verification step or test that proves the intended behavior.
5. It does not make the current workflow harder to inspect or recover.

## 7. Source Documents

This spec aligns with:

- [docs/architecture.md](architecture.md)
- [docs/access-management.md](access-management.md)
- [docs/roles.md](roles.md)
- [docs/frontend-workflows.md](frontend-workflows.md)
- [docs/workflow-process.md](workflow-process.md)
- [docs/agent-harness.md](agent-harness.md)
