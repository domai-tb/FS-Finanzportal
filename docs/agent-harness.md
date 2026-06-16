# Agent Harness

This document defines how agents should interact with FS-Finanzportal. The
goal is to keep the action space small, the observations deterministic, and the
recovery path explicit.

## Operating Principles

1. Treat WordPress, Keycloak, and Docker as separate lanes.
2. Prefer setup-time changes over runtime hacks.
3. Use the repo's verification scripts before broad manual inspection.
4. Keep every edit idempotent unless it is purely documentation.
5. Stop and report when a change would require custom runtime PHP, a shared
   row-level security model, or direct third-party mutation outside the setup
   scripts.

## Recommended Agent Loop

1. Orient on the docs:
   - `docs/architecture.md`
   - `docs/roles.md`
   - `docs/access-management.md`
   - `docs/frontend-workflows.md`
   - `docs/workflow-process.md`
2. Identify the lane:
   - WordPress setup or portal config
   - Keycloak realm/client/theme
   - Docker compose and service health
   - Verification and diagnosis
3. Make the smallest edit that fits the lane.
4. Run the narrowest relevant verification first.
5. Finish with `./scripts/verify-setup.sh` when the change touches setup,
   access, or workflow behavior.

## Lane Map

| Lane | Primary entrypoints | Notes |
|------|---------------------|-------|
| Docker / health | `compose.yaml`, `./scripts/setup.sh`, `docker compose ps`, `docker compose logs` | Use for service lifecycle, startup failures, and container drift. |
| Keycloak | `scripts/configure-keycloak.sh`, `keycloak/realms/fs-finance-realm.json`, `keycloak/themes/asta-finance/` | Realm, role, group, mapper, and theme changes belong here. |
| WordPress install | `scripts/wp-install.sh`, `scripts/configure-wordpress.sh` | Use for plugin activation, site options, and generated portal setup. |
| WP-CLI setup logic | `scripts/wp-eval/**` | Setup-only PHP. Must remain idempotent. |
| Portal config | `wordpress/config/portal/**`, `wordpress/config/demo/**` | Use for generated pages, forms, templates, styles, and seeded records. |
| Verification | `scripts/verify-setup.sh`, `scripts/wp-eval/verify-*.php` | Use to confirm behavior after edits. |

## Tooling Contract

Use narrow commands first:

- `./scripts/verify-setup.sh` for a full local sanity check.
- `docker compose ps` to confirm service state.
- `docker compose logs <service>` to inspect one container.
- `docker compose exec -T <service> ...` for bounded, read-first inspection.
- `wp eval-file ...` only through the existing setup and verification entrypoints.

Avoid ad hoc database edits, manual admin UI changes, and direct content changes
inside the running WordPress instance unless the repo already models them as
setup-time operations.

## Observation Shape

When reporting a tool result, keep the response structure stable:

```text
status: success|warning|error
summary: one-line result
next_actions: 1-3 concrete follow-ups
artifacts: files, URLs, or commands that matter
```

If a command fails, report:

1. the likely root cause
2. the safest retry
3. the point where you should stop and ask for a different input or a state
   change

## Recovery Rules

- If setup fails, rerun the narrow failing step before rerunning the full stack.
- If verification fails, inspect the matching verifier file before changing
  multiple layers at once.
- If access control looks wrong, check `docs/access-management.md` and
  `scripts/wp-eval/portal/ensure.php` before changing page content.
- If workflow behavior looks wrong, check `docs/workflow-process.md` before
  changing statuses or form fields.

## Boundaries

- Do not add runtime WordPress plugins for project behavior.
- Do not replace scoped post types with one shared post type and runtime
  Keycloak claim filtering.
- Do not bypass the generated portal pages by editing data manually in wp-admin.
- Do not change Keycloak or Docker resources outside the repo's setup scripts
  unless the user explicitly asked for an external mutation.

## Completion Gate

A change is only done when the narrow verifier passes and the relevant setup
verification is clean. For behavior, access, or provisioning changes, that means
the full setup verification should pass before handoff.
