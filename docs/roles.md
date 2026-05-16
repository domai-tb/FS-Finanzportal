# Roles and Permissions

Roles exist in Keycloak and WordPress. Keycloak is the login source. WordPress
roles and capabilities enforce access after login.

## Fachschaft Membership

Each Fachschaft user belongs to exactly one Keycloak group named
`fachschaft-<slug>`, for example `fachschaft-informatik`. The OIDC client still
receives `groups` and `fachschaften` claims for traceability.

Runtime claim-based row filtering is not used. Instead, setup mirrors demo users
to scoped WordPress roles such as `fs_informatik_reader` and
`fs_informatik_finance`. Unknown users receive only `fs_portal_empty` or the
normal low-privilege subscriber behavior and cannot read workflow records.

## Roles

| Role | Intended user | Scope | Behavior |
|------|---------------|-------|----------|
| `portal_admin` | System administrator | All data and settings | Full WordPress administration and all workflow records |
| `asta_finance` | AStA finance officer | All Fachschaften | Read, create, edit, publish, upload, and archive by status |
| `asta_reviewer` | AStA reviewer | All Fachschaften | Read and edit workflow records |
| `auditor` | Auditor | All Fachschaften | Read-only workflow access |
| `fs_<slug>_finance` | Fachschaft finance officer | One Fachschaft | Read, create, edit, publish, upload, and archive by status for one Fachschaft |
| `fs_<slug>_reader` | Fachschaft member | One Fachschaft | Read-only access for one Fachschaft |
| `fs_portal_empty` | Authenticated user without assignment | None | Login only, no workflow record access |

## Capability Pattern

For each Fachschaft in `wordpress/config/fachschaften.json`, setup creates:

| Fachschaft | Beschluss type | Zahlungsanweisung type | Reader role | Finance role |
|------------|----------------|-------------------------|-------------|--------------|
| `informatik` | `b_informatik` | `za_informatik` | `fs_informatik_reader` | `fs_informatik_finance` |
| `maschinenbau` | `b_maschinenbau` | `za_maschinenbau` | `fs_maschinenbau_reader` | `fs_maschinenbau_finance` |
| `philosophie` | `b_philosophie` | `za_philosophie` | `fs_philosophie_reader` | `fs_philosophie_finance` |

Reader roles receive only:

- `read`
- `read_<scoped_record>`
- `read_private_<scoped_records>`

Finance roles receive the reader capabilities plus:

- `edit_<scoped_record>`
- `edit_<scoped_records>`
- `edit_others_<scoped_records>`
- `edit_private_<scoped_records>`
- `edit_published_<scoped_records>`
- `publish_<scoped_records>`
- `upload_files`

Normal workflow users do not receive delete capabilities. Archiving is modeled
through the workflow status value `archived`, preserving audit history.

## Admin Boundary

Readers, auditors, and unassigned users are blocked from `wp-admin` and
redirected to `/dashboard/`. Finance/editor workflow roles receive
`fsfp_use_wp_admin` so the role-gated edit action can open WordPress' native
editor for their scoped post types. The admin bar is hidden for portal roles.
WordPress administrators and `portal_admin` retain backend access.

## Demo Users

All demo passwords are `demo_secret`.

| Login | WordPress role | Fachschaft |
|-------|----------------|-------------|
| `demo-fachschaft` | `fs_informatik_finance` | `informatik` |
| `demo-informatik-reader` | `fs_informatik_reader` | `informatik` |
| `demo-informatik-reader2` | `fs_informatik_reader` | `informatik` |
| `demo-maschinenbau-finance` | `fs_maschinenbau_finance` | `maschinenbau` |
| `demo-maschinenbau-reader` | `fs_maschinenbau_reader` | `maschinenbau` |
| `demo-maschinenbau-reader2` | `fs_maschinenbau_reader` | `maschinenbau` |
| `demo-philosophie-finance` | `fs_philosophie_finance` | `philosophie` |
| `demo-philosophie` | `fs_philosophie_reader` | `philosophie` |
| `demo-philosophie-reader2` | `fs_philosophie_reader` | `philosophie` |
| `demo-asta` | `asta_finance` | all |
| `demo-reviewer` | `asta_reviewer` | all |
| `demo-auditor` | `auditor` | all |
| `demo-unassigned` | `fs_portal_empty` | none |
