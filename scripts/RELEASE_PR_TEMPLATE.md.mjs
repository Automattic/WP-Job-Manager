export default ( { changelog, version }) => `

> [!IMPORTANT]
> Merging this PR will build and publish the new version automatically as a GitHub release, then deploy the new version on the WordPress.org plugin repository.
>

## WP Job Manager ${version}

> [!NOTE]
> These release notes between the two \`---\` lines will be the final changelog entry for the release. Edit them freely here before merging.
>

### Release Notes

---
${ changelog }
---

### Release

> [!NOTE]
> Click 'Ready for Review', ping the team, review the PR and merge. Upon merging, automation will:
> - Write the release notes above to the changelog
> - Create and tag a new GitHub release
> - Deploy the release to WordPress.org
`;
