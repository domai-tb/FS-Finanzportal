# FS-Finanzportal Workspace Context for Agents

This document provides architectural context, structural guidelines, and established patterns for automated agents and developers working in this workspace.

## Core Architecture Philosophy

FS-Finanzportal is a low-code, Docker-based WordPress workflow prototype designed for Fachschaft (student council) finance management. 

- **No Custom Runtime PHP:** The project intentionally avoids shipping custom WordPress plugins or custom theme logic. (Previous `mu-plugins` approaches have been actively removed). 
- **Setup-Time Configuration:** WordPress behavior is driven by data. All project-specific initialization executes via one-shot WP-CLI scripts (`wp eval-file`) during setup to translate JSON configuration into database state.
- **Standard Tooling:** Application behavior relies on standard WordPress.org plugins (Pods, Members, OpenID Connect Generic, Remove Dashboard Access).

## Key Components

### 1. WordPress (Application layer)
- **Content Modeling (Pods):** Instead of one shared table utilizing dynamic row-level security (which isn't native to WP config), the portal uses a strict physical isolation pattern. The system generates separate scoped Custom Post Types (CPTs) for *every* Fachschaft (e.g., `b_informatik`, `za_maschinenbau`).
- **Configuration Source:** `wordpress/config/fachschaften.json` is the single source of truth for the available Fachschaften. The setup dynamically parses this to create the CPTs.
- **Frontend Workflows:** All normal workflow interaction (list, create, edit) occurs on frontend portal dashboard pages (e.g., `/dashboard/informatik/`). Native `wp-admin` access is blocked or hidden for most roles.

### 2. Keycloak (Identity Provider & SSO)
- **Role & Group Management:** Handles identities, roles (e.g., `fs_informatik_reader`, `asta_finance`), and group memberships.
- **OIDC Configuration:** WordPress uses `daggerhart-openid-connect-generic` to authenticate against Keycloak.
- **Mirroring:** The Keycloak roles are mirrored in WordPress via setup scripts rather than relying on complex runtime claim extraction logic mapping to rows.
- **Setup script:** `scripts/configure-keycloak.sh` securely and idempotently bootstraps the realm, roles, groups, clients, and test users.

### 3. Setup Automation (`scripts/`)
- **`setup.sh`:** Orchestrates the orchestration: spinning up Docker networks, waiting for health checks, executing Keycloak configurations, and bootstrapping WP configurations.
- **`wp-eval/*.php`:** These are PHP files, but they are *not* runtime codebase. The public entrypoints load setup-only modules from `scripts/wp-eval/lib/`, `scripts/wp-eval/portal/`, and `scripts/wp-eval/pods/`.
- **Human-editable portal config:** Adjust Pods fields, role defaults, form field lists, generated templates, CSS, and setup-injected JS in `wordpress/config/portal/` before changing PHP orchestration code.

## Strict Development Rules & Constraints

1. **Do Not Write Runtime PHP:** Do not create or edit plugins in `wp-content/plugins` or `mu-plugins` intending them to run on every page load.
2. **Setup scripts must be Idempotent:** If modifying `scripts/wp-eval/*.php`, `scripts/wp-eval/**`, or Bash scripts, ensure they can be run 10 times consecutively without duplicating content, roles, or settings. Use `update` logic instead of generic `insert` if something exists.
3. **Respect Scoped Boundaries:** Do not introduce global querying for `beschluss` if the user is a generic Fachschaft role. Security relies on capability checking (e.g., `edit_b_informatik`) against scoped Pods endpoints and Members plugin page gating.
4. **Testing Environments:** Validate UI or structural changes using the provided demo users combinations (`demo-fachschaft`, `demo-reviewer`, `demo-auditor`) defined in `docs/roles.md`.

## Relevant Documentation
Do not guess. Refer to the existing Markdown docs when adjusting workflow logic:
- `docs/architecture.md`: Full topology overview.
- `docs/roles.md`: Comprehensive breakdown of capabilities, default demo accounts, and OIDC claims.
- `docs/access-management.md`: How page level frontend security is layered on top of WP capabilities.
- `docs/frontend-workflows.md`: Detailed map of dashboard routes.
