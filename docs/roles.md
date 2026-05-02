# Roles – FS-Finanzportal

All roles are defined in the Keycloak realm **`fs-finance`**
(see `keycloak/realms/fs-finance-realm.json`) and mirrored as WordPress roles
during setup. The local prototype seeds matching WordPress users for the
Keycloak demo accounts so OpenID Connect can link by email.

The no-custom-code implementation uses WordPress roles and capabilities for
coarse authorization. It does not enforce per-Fachschaft row-level isolation or
strict status-transition guards at runtime; those rules require custom code or
a more specialized workflow plugin.

---

## Role Definitions

### `portal_admin`

| Attribute | Value |
|-----------|-------|
| **Assigned to** | System administrators |
| **Scope**       | All Fachschaften, all data |
| **Permissions** | Full CRUD on all CPTs; manage users, settings, plugins |
| **Typical user**| IT admin / AStA system admin |

---

### `asta_finance`

| Attribute | Value |
|-----------|-------|
| **Assigned to** | AStA finance officers |
| **Scope**       | All Fachschaften |
| **Permissions** | Read all Beschlüsse and Zahlungsanweisungen; trigger export to accounting; archive items |
| **Typical user**| AStA Finanzreferent/-in |

---

### `asta_reviewer`

| Attribute | Value |
|-----------|-------|
| **Assigned to** | AStA reviewers |
| **Scope**       | All Fachschaften |
| **Permissions** | Read and approve / request correction for Beschlüsse and Zahlungsanweisungen; cannot edit content |
| **Typical user**| AStA Referent/-in für Fachschaften |

---

### `fachschaft_finance`

| Attribute | Value |
|-----------|-------|
| **Assigned to** | Finance officers of a specific Fachschaft |
| **Scope**       | Intended own Fachschaft; not technically enforced per record in the no-code prototype |
| **Permissions** | Create, edit, and submit Beschlüsse and Zahlungsanweisungen; upload Belege (attachments); view own history |
| **Typical user**| Fachschafts-Finanzbeauftragte/-r |

> **Note**: Multi-Fachschaft membership should be modeled in Keycloak groups or
> claims if custom enforcement is reintroduced later.

---

### `fachschaft_reader`

| Attribute | Value |
|-----------|-------|
| **Assigned to** | Regular Fachschaft members |
| **Scope**       | Intended own Fachschaft; not technically enforced per record in the no-code prototype |
| **Permissions** | Read-only access to own Fachschaft's Beschlüsse and Zahlungsanweisungen |
| **Typical user**| Fachschaftsmitglied (general member) |

---

### `auditor`

| Attribute | Value |
|-----------|-------|
| **Assigned to** | Internal or external auditors |
| **Scope**       | All Fachschaften |
| **Permissions** | Read-only access to all data; cannot modify anything |
| **Typical user**| Rechnungsprüfer/-in, Datenschutzbeauftragte/-r |

---

## Permission Matrix

| Action                                   | portal_admin | asta_finance | asta_reviewer | fachschaft_finance | fachschaft_reader | auditor |
|------------------------------------------|:---:|:---:|:---:|:---:|:---:|:---:|
| Manage users & settings                  | ✅  | ❌  | ❌  | ❌  | ❌  | ❌  |
| Read all Fachschaften                    | ✅  | ✅  | ✅  | Via list visibility | Via list visibility | ✅  |
| Read own Fachschaft                      | ✅  | ✅  | ✅  | Operational convention | Operational convention | ✅  |
| Create / edit Beschluss                  | ✅  | ✅  | ❌  | ✅  | ❌  | ❌  |
| Submit Beschluss for review              | ✅  | ❌  | ❌  | Via status field | ❌  | ❌  |
| Approve / reject / request correction    | ✅  | Via status field | Via status field | ❌  | ❌  | ❌  |
| Create / edit Zahlungsanweisung          | ✅  | ❌  | ❌  | ✅  | ❌  | ❌  |
| Upload Belege                            | ✅  | ❌  | ❌  | ✅  | ❌  | ❌  |
| Export data for accounting               | ✅  | ✅  | ❌  | ❌  | ❌  | ❌  |
| Archive items                            | ✅  | ✅  | ❌  | ❌  | ❌  | ❌  |
