# Roles – FS-Finanzportal

All roles are defined in the Keycloak realm **`fs-finance`**
(see `keycloak/realms/fs-finance-realm.json`) and mirrored as WordPress roles
during setup. The local prototype seeds matching WordPress users for the
Keycloak demo accounts so OpenID Connect can link by email.

Fachschaft ownership is currently stored in WordPress user meta
`fsfp_fachschaft`. This is a temporary prototype mechanism until Keycloak group
or claim based Fachschaft mapping is added.

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
| **Scope**       | Own Fachschaft only |
| **Permissions** | Create, edit, and submit Beschlüsse and Zahlungsanweisungen; upload Belege (attachments); view own history |
| **Typical user**| Fachschafts-Finanzbeauftragte/-r |

> **Note**: A user can hold this role for multiple Fachschaften simultaneously.
> The Keycloak role should then carry a `fachschaft_id` attribute.
> (TODO: design this claim once multi-tenancy is needed.)

---

### `fachschaft_reader`

| Attribute | Value |
|-----------|-------|
| **Assigned to** | Regular Fachschaft members |
| **Scope**       | Own Fachschaft only |
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
| Read all Fachschaften                    | ✅  | ✅  | ✅  | ❌  | ❌  | ✅  |
| Read own Fachschaft                      | ✅  | ✅  | ✅  | ✅  | ✅  | ✅  |
| Create / edit Beschluss                  | ✅  | ✅  | ❌  | ✅  | ❌  | ❌  |
| Submit Beschluss for review              | ✅  | ❌  | ❌  | ✅  | ❌  | ❌  |
| Approve / reject / request correction    | ✅  | ✅  | Rückfrage only | ❌  | ❌  | ❌  |
| Create / edit Zahlungsanweisung          | ✅  | ❌  | ❌  | ✅  | ❌  | ❌  |
| Upload Belege                            | ✅  | ❌  | ❌  | ✅  | ❌  | ❌  |
| Export data for accounting               | ✅  | ✅  | ❌  | ❌  | ❌  | ❌  |
| Archive items                            | ✅  | ✅  | ❌  | ❌  | ❌  | ❌  |
