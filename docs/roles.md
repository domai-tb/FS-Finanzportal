# Roles and Permissions

Roles exist in both Keycloak and WordPress.

Keycloak is the login source. WordPress roles control what users can do after
login. The setup script creates matching demo users in both systems so OIDC can
link them by username and email.

## Prototype Boundary

The current implementation uses WordPress capabilities for coarse access
control. It does not enforce:

- Per-Fachschaft row-level isolation.
- Strict status transition rules.
- Multi-Fachschaft membership logic.

Those rules need custom code or a dedicated workflow/access-control plugin.

## Roles

| Role | Intended user | Scope | Current behavior |
|------|---------------|-------|------------------|
| `portal_admin` | System administrator | All data and settings | Full WordPress administration |
| `asta_finance` | AStA finance officer | All Fachschaften | Can read, edit, publish, upload, archive operationally |
| `asta_reviewer` | AStA reviewer | All Fachschaften | Can read and edit existing workflow records |
| `fachschaft_finance` | Fachschaft finance officer | Intended own Fachschaft | Can create, edit, publish, upload, and delete own posts |
| `fachschaft_reader` | Fachschaft member | Intended own Fachschaft | Read-only WordPress access |
| `auditor` | Auditor | All data | Read-only WordPress access |

## Capability Summary

| Action | portal_admin | asta_finance | asta_reviewer | fachschaft_finance | fachschaft_reader | auditor |
|--------|:------------:|:------------:|:-------------:|:------------------:|:------------------:|:-------:|
| Manage WordPress settings | Yes | No | No | No | No | No |
| Read admin content | Yes | Yes | Yes | Yes | Yes | Yes |
| Create Beschluss records | Yes | Yes | No | Yes | No | No |
| Edit Beschluss records | Yes | Yes | Yes | Yes | No | No |
| Upload Belege | Yes | Yes | No | Yes | No | No |
| Change workflow status field | Yes | Yes | Yes | Yes | No | No |
| Delete posts | Yes | Yes | No | Yes | No | No |

Status changes are field edits. The UI does not currently prevent a user with
edit access from choosing any configured status value.

## Demo Users

| Login | Email | Role | Password |
|-------|-------|------|----------|
| `demo-fachschaft` | `demo-fachschaft@example.com` | `fachschaft_finance` | `demo_secret` |
| `demo-philosophie` | `demo-philosophie@example.com` | `fachschaft_reader` | `demo_secret` |
| `demo-asta` | `demo-asta@example.com` | `asta_finance` | `demo_secret` |
| `demo-reviewer` | `demo-reviewer@example.com` | `asta_reviewer` | `demo_secret` |
| `demo-auditor` | `demo-auditor@example.com` | `auditor` | `demo_secret` |
