# Access Management

FS-Finanzportal uses WordPress capabilities and Members page permissions as the
enforcement layer. Keycloak is the identity source.

## Keycloak

Fachschaft membership is stored in Keycloak as:

- group `fachschaft-<slug>`
- user attribute `fachschaften`
- OIDC claims `groups` and `fachschaften`

The local demo realm also defines scoped roles such as `fs_informatik_reader`
and `fs_informatik_finance`. These names match the WordPress roles created by
setup.

## WordPress Role Mirroring

The setup script creates demo WordPress users with roles that mirror their
Keycloak assignment. This is intentionally setup-time mirroring, not runtime
claim-based authorization.

Unassigned or auto-created users are safe by default because they receive no
capabilities for any workflow post type and no Members access role for workflow
pages. They can log in, but cannot read, create, or edit Beschlüsse or
Zahlungsanweisungen.

## Frontend Page Permissions

Members content permissions are enabled during setup. Every generated workflow
page receives `_members_access_role` metadata:

- Fachschaft pages allow only the matching `fs_<slug>_reader`,
  `fs_<slug>_finance`, auditors, and admin roles. AStA users use the unified
  overview pages instead of Fachschaft-specific list pages.
- Beschluss creation and edit pages allow only `fs_<slug>_finance`,
  administrators, and `portal_admin`.
- Zahlungsanweisung creation pages allow only `fs_<slug>_finance`,
  administrators, and `portal_admin`; Zahlungsanweisung workflow edit pages also
  allow AStA finance/reviewer roles.
- Global overview pages and the generated reporting page allow only AStA
  finance/reviewer and admin roles. Auditors keep cross-Fachschaft read
  capabilities, but they do not see the unified AStA overview or reporting
  pages.
- The generated `Betrieb` page allows only `administrator` and `portal_admin`.

The portal menu intentionally contains only Dashboard and Logout. Dashboard
links are wrapped in Members shortcodes so each user sees only the areas they
are allowed to access. The generated block header navigation also adds plain
theme-styled `Beschlüsse` and `Zahlungsanweisungen` links. They are resolved in
the browser from the current dashboard context: AStA pages point to the two
unified tables, while Fachschaft pages point inside the current Fachschaft area.
The dashboard also exposes an AStA-only `Berichte` card that links to the
generated reporting page, and administrator-only views get a `Betrieb` card.
The classic menu fallback stays minimal because it cannot hide individual links
by role without custom runtime PHP.

## Redirects

Normal portal users:

- are redirected away from `wp-admin` to `/dashboard/`
- do not see the WordPress admin bar
- use frontend portal pages for viewing and creating workflow records

Finance/editor workflow roles receive the `fsfp_use_wp_admin` capability only
where needed for plugin-admin compatibility, but the generated portal workflow
uses frontend Pods forms rather than WordPress' native editor. Readers,
auditors, and unassigned users are redirected away from `wp-admin`.
Administrators and `portal_admin` retain backend access.

## Known Limit

Without custom runtime PHP or a paid/pro access-control plugin, one shared
workflow table filtered by Keycloak claims is not a reliable security boundary.
The implemented security boundary is therefore one scoped post type per
Fachschaft, strict role capabilities, and Members page permissions.
