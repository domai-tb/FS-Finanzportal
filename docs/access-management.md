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
  `fs_<slug>_finance`, and global AStA/auditor/admin roles.
- Creation and edit pages allow only `fs_<slug>_finance` and global roles.
- Global overview pages allow only AStA, auditor, and admin roles.

The portal menu intentionally contains only Dashboard and Logout. Dashboard
links are wrapped in Members shortcodes so each user sees only the areas they
are allowed to access.

## Redirects

Normal portal users:

- are redirected away from `wp-admin` to `/dashboard/`
- do not see the WordPress admin bar
- use frontend portal pages for viewing and creating workflow records

Finance/editor workflow roles receive the `fsfp_use_wp_admin` capability so the
“Bearbeiten” action can use WordPress' native scoped editor for the matching
post type. Readers, auditors, and unassigned users are redirected away from
`wp-admin`. Administrators and `portal_admin` retain backend access.

## Known Limit

Without custom runtime PHP or a paid/pro access-control plugin, one shared
workflow table filtered by Keycloak claims is not a reliable security boundary.
The implemented security boundary is therefore one scoped post type per
Fachschaft, strict role capabilities, and Members page permissions.
