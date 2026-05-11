# Triage Labels

The skills speak in terms of five canonical triage roles. This file maps those roles to the actual label strings used in this repo's issue tracker (https://github.com/Automattic/WP-Job-Manager/labels).

| Canonical role    | Label in this repo                       | Notes                                                                                        |
| ----------------- | ---------------------------------------- | -------------------------------------------------------------------------------------------- |
| `needs-triage`    | *(no label — unlabeled)*                 | This repo doesn't carry an explicit "needs triage" label. Treat any issue with no `[Status] *` label as needing triage. |
| `needs-info`      | `[Status] Needs Author Reply`            | Exact fit.                                                                                   |
| `ready-for-agent` | `ready-for-agent` + `[Status] Accepted`  | Both labels required (see below).                                                            |
| `ready-for-human` | `ready-for-human` + `[Status] Accepted`  | Both labels required (see below).                                                            |
| `wontfix`         | `[Status] Won't Fix`                     | Exact fit.                                                                                   |

When a skill mentions a role (e.g. "apply the AFK-ready triage label"), use the corresponding label string(s) from this table.

## Two-axis readiness

`[Status] Accepted` and the `ready-for-*` labels are **orthogonal**:

- **`[Status] Accepted`** means a maintainer agreed the issue is real and a fix would be merged.
- **`ready-for-agent` / `ready-for-human`** mean someone has *recently* re-read the issue and confirmed it is:
  - still relevant (some accepted issues are years old and stale),
  - self-contained in the issue body (context not spread across long comment threads),
  - actionable as written.

An accepted issue is **not** ready to pick up until one of the `ready-for-*` labels is added.

| Apply when                                                                       | Use                |
| -------------------------------------------------------------------------------- | ------------------ |
| Issue body fully summarises scope; minimal code-archaeology needed                | `ready-for-agent`  |
| Implementation needs human judgement (UX, edge cases, customer-facing decisions)  | `ready-for-human`  |

If an accepted issue can't be brought up to either bar (stale, vague, scope drifted), leave it without a `ready-for-*` label and add a comment explaining why — or close it.

## Other labels worth knowing

The triage skill may also want to apply or read these orthogonal labels:

- **Priority**: `[Pri] Critical`, `[Pri] High`, `[Pri] Normal`, `[Pri] Low`
- **Type**: `Bug`, `Enhancement`, `[Type] Proposal`, `[Type] Question`, `[Type] Cleanup and Documentation`, `[Type] Maintenance`, `[Type] Good First Bug`
- **Source**: `Customer Report` (issues reported via Happiness)
- **Status (additional)**: `[Status] In Progress`, `[Status] Needs Documentation`, `[Status] Needs Design`
- **Area**: `Hooks`, `Templates`, `Addon`, `Third-party Conflict`, `Promoted Jobs`, `Stats`

## Listing untriaged issues

To find issues that need triage (no `[Status] *` label):

```sh
gh issue list --state open --search 'no:label OR -label:"[Status] Accepted" -label:"[Status] Needs Author Reply" -label:"[Status] In Progress" -label:"[Status] Won't Fix"'
```
