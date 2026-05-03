# Roles and Permissions

Roles exist in both Keycloak and WordPress.

Keycloak is the login source. WordPress roles control what users can do after
login. The setup script creates matching demo users in both systems so OIDC can
link them by username and email.

## Fachschaft Membership

Each Fachschaft user belongs to exactly one Fachschaft.

Membership is stored in Keycloak as a `fachschaft-<slug>` group and a
`fachschaften` user attribute. The WordPress OIDC client receives this
membership as `groups` and `fachschaften` claims.

No custom WordPress plugin is used for this. WordPress therefore does not
enforce per-record Fachschaft isolation by itself. Strict status transition
rules are also not enforced; users with edit access can choose any configured
status value.

## Roles

| Role | Intended user | Scope | Current behavior |
|------|---------------|-------|------------------|
| `portal_admin` | System administrator | All data and settings | Full WordPress administration |
| `asta_finance` | AStA finance officer | All Fachschaften | Can read, edit, publish, upload, archive operationally |
| `asta_reviewer` | AStA reviewer | All Fachschaften | Can read and edit existing workflow records |
| `fachschaft_finance` | Fachschaft finance officer | Keycloak Fachschaft group | Can create, edit, publish, upload, and delete own Beschluss/Zahlungsanweisung records |
| `fachschaft_reader` | Fachschaft member | Keycloak Fachschaft group | Basic read/login access only |
| `auditor` | Auditor | All data | Read-only WordPress access |
| `fsr_member` | Frontend FSR member | Frontend portal | Basic read/login access only |
| `fsr_treasurer` | Frontend treasurer | Frontend portal | Can create and edit own workflow records |
| `fsr_board` | Frontend board member | Frontend portal | Can read and edit workflow records |
| `asta_finance_admin` | Frontend AStA finance admin | Frontend portal | Can manage workflow records |

## Capability Summary

| Action | portal_admin | asta_finance | asta_reviewer | fachschaft_finance | fachschaft_reader | auditor |
|--------|:------------:|:------------:|:-------------:|:------------------:|:------------------:|:-------:|
| Manage WordPress settings | Yes | No | No | No | No | No |
| Manage Fachschaften | Yes | No | No | No | No | No |
| Read admin workflow lists | Yes | Yes | Yes | Yes | No | No |
| Create Beschluss records | Yes | Yes | No | Yes | No | No |
| Edit Beschluss records | Yes | Yes | Yes | Yes | No | No |
| Create Zahlungsanweisung records | Yes | Yes | No | Yes | No | No |
| Edit Zahlungsanweisung records | Yes | Yes | Yes | Yes | No | No |
| Upload Belege | Yes | Yes | No | Yes | No | No |
| Change workflow status field | Yes | Yes | Yes | Yes | No | No |
| Delete posts | Yes | Yes | No | Yes | No | No |

Fachschaft scope is represented in Keycloak groups and claims. WordPress
capabilities remain role-based unless a claim-aware access-control plugin is
added. The WordPress capability model prevents Fachschaft users from managing
the `fachschaft` master data post type.

Normal portal roles are blocked from `wp-admin` and profile/settings pages by
the Remove Dashboard Access plugin and are redirected to `/dashboard/`. The
admin bar is hidden for those roles.

## Demo Users

All demo passwords are `demo_secret`.

| Login | Role | Fachschaft |
|-------|------|------------|
| `demo-fachschaft` | `fachschaft_finance` | `informatik` |
| `demo-informatik-reader` | `fachschaft_reader` | `informatik` |
| `demo-informatik-reader2` | `fachschaft_reader` | `informatik` |
| `demo-maschinenbau-finance` | `fachschaft_finance` | `maschinenbau` |
| `demo-maschinenbau-reader` | `fachschaft_reader` | `maschinenbau` |
| `demo-maschinenbau-reader2` | `fachschaft_reader` | `maschinenbau` |
| `demo-philosophie-finance` | `fachschaft_finance` | `philosophie` |
| `demo-philosophie` | `fachschaft_reader` | `philosophie` |
| `demo-philosophie-reader2` | `fachschaft_reader` | `philosophie` |
| `demo-asta` | `asta_finance` | all |
| `demo-reviewer` | `asta_reviewer` | all |
| `demo-auditor` | `auditor` | all |
