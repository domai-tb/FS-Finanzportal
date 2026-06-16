# Project Specification Roadmap

This plan is the execution-oriented companion to `docs/project-specification.md`.
It breaks the specification work into cold-startable steps for future sessions.

## Objective

Define the current product state, identify missing capabilities, and lock a
prioritized roadmap that matches the setup-driven WordPress/Keycloak/Docker
architecture.

## Dependency Graph

```text
Step 1 -> Step 2 -> Step 3 -> Step 5
                    -> Step 4 -> Step 5
```

Step 2 and Step 4 can be refined in parallel once Step 1 is stable.

## Step 1 - Baseline Definition

Context brief:

- Read `docs/architecture.md`, `docs/access-management.md`,
  `docs/frontend-workflows.md`, and `docs/workflow-process.md`.
- Confirm the current product boundary: setup-driven WordPress, Keycloak
  identity, scoped post types, frontend workflow pages, and verification.

Tasks:

1. Summarize the current state in product language.
2. Distinguish current behavior from intended behavior.
3. Capture the architectural constraints that must not be violated.

Verification:

- Cross-check terminology against the existing docs.
- Ensure the summary does not claim runtime custom PHP or shared row-level
  filtering.

Exit criteria:

- The baseline description is precise enough to support roadmap decisions
  without rereading the source docs.

## Step 2 - Gap Analysis

Context brief:

- Use the baseline from Step 1 and the current verification scripts.
- Focus on capabilities that are missing, weak, or only partially enforced.

Tasks:

1. Group gaps into enforcement, notifications, audit, reporting, UX, and ops.
2. Separate prototype-complete items from future goals.
3. Identify which gaps are blocked by the no-runtime-custom-PHP rule.

Verification:

- Every gap must map to a visible limitation in the current docs or scripts.
- No item may silently assume a shared workflow table or runtime business logic.

Exit criteria:

- The gap list is complete enough to drive prioritization.

## Step 3 - Prioritization And Goals

Context brief:

- Build on the gap list and turn it into a prioritised goal set.
- Keep the scope aligned with the project's low-code architecture.

Tasks:

1. Assign P0, P1, and P2 priority bands.
2. Write concrete goal statements for each band.
3. Define non-goals that protect architecture and maintainability.

Verification:

- Each goal must be testable or clearly marked as a policy goal.
- P0 items must preserve the current setup model.

Exit criteria:

- The project has a stable goal hierarchy.

## Step 4 - Acceptance Criteria And Verification

Context brief:

- Translate goals into acceptance criteria and proof points.
- Use the existing verification scripts as the starting point.

Tasks:

1. Define what success looks like for each priority band.
2. Identify the minimal checks needed to prove success.
3. Note any future tests or verifiers that should be added later.

Verification:

- Acceptance criteria must be specific enough that another agent can test them
  cold.
- A criterion cannot require manual memory of previous discussion.

Exit criteria:

- The roadmap has measurable completion conditions.

## Step 5 - Publish The Spec

Context brief:

- Finalize `docs/project-specification.md`.
- Update any doc index or agent references that should point at the new spec.

Tasks:

1. Check wording for consistency with the rest of the repo.
2. Remove duplicate or contradictory statements.
3. Confirm the spec is self-contained and readable on its own.

Verification:

- Review the file as a fresh reader.
- Confirm the spec does not imply unsupported runtime behavior.

Exit criteria:

- The specification is ready to use as the canonical baseline for future work.
