# Issue tracker: GitHub

Issues and PRDs for this repo live as GitHub issues. Use the `gh` CLI for all operations.

## Conventions

- **Create an issue**: `gh issue create --title "..." --body "..."`. Use a heredoc for multi-line bodies.
- **Read an issue**: `gh issue view <number> --comments`, filtering comments by `jq` and also fetching labels.
- **List issues**: `gh issue list --state open --json number,title,body,labels,comments --jq '[.[] | {number, title, body, labels: [.labels[].name], comments: [.comments[].body]}]'` with appropriate `--label` and `--state` filters.
- **Comment on an issue**: `gh issue comment <number> --body "..."`
- **Apply / remove labels**: `gh issue edit <number> --add-label "..."` / `--remove-label "..."`
- **Close**: `gh issue close <number> --comment "..."`

Infer the repo from `git remote -v` — `gh` does this automatically when run inside a clone.

## When a skill says "publish to the issue tracker"

Create a GitHub issue. Follow `.github/ISSUE_TEMPLATE.md` — that file is the canonical bug-report shape (Steps to Reproduce, What I Expected, What Happened Instead, PHP / WordPress / WP Job Manager Version, Browser / OS Version, Screenshot / Video, Context / Source). For non-bug issues (proposals, cleanup, questions) the template structure is a useful starting point; drop sections that don't apply.

**Don't file these as issues:**

- **End-user support questions** — direct to https://wordpress.org/support/plugin/wp-job-manager.
- **Security vulnerabilities** — direct to https://hackerone.com/automattic. Never paste exploit details into a public GitHub issue.

## When a skill says "fetch the relevant ticket"

Run `gh issue view <number> --comments`.
