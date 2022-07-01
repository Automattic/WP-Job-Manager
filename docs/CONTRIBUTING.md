# Contributing to WP Job Manager ✨

There are many ways to contribute to the WP Job Manager project!

- [Translating strings into your language](https://translate.wordpress.org/projects/wp-plugins/wp-job-manager/).
- Answering questions on the WP Job Manager forum on WordPress.org [WP.org support forums](https://wordpress.org/support/plugin/wp-job-manager/).
- Testing open [issues](https://github.com/Automattic/WP-Job-Manager/issues) or [pull requests](https://github.com/Automattic/WP-Job-Manager/pulls) and sharing your findings in a comment.
- Testing WP Job Manager beta versions and release candidates.
- Submitting fixes, improvements, and enhancements.
- To disclose a security issue to our team, [please submit a report via HackerOne](https://hackerone.com/automattic/).

If you wish to contribute code, please read the information in the sections below. Then [fork](https://help.github.com/articles/fork-a-repo/) WP Job Manager, commit your changes, and [submit a pull request](https://help.github.com/articles/using-pull-requests/) 🎉

We use the `[Type] Good First Bug` label to mark issues that are suitable for new contributors. You can find all the issues with this label [here](https://github.com/automattic/wp-job-manager/issues?q=is%3Aopen+is%3Aissue+label%3A%22%5BType%5D+Good+First+Bug%22).

WP Job Manager is licensed under the GPLv3+, and all contributions to the project will be released under the same license. You maintain copyright over any contribution you make, and by submitting a pull request, you are agreeing to release that contribution under the GPLv3+ license.

If you have questions about the process to contribute code or want to discuss details of your contribution, you can contact WP Job Manager core developers in the [WordPress.org Slack](https://make.wordpress.org/chat/).

## Getting started

- [How to set up WP Job Manager development environment](https://github.com/Automattic/WP-Job-Manager/wiki/Setting-Up-Development-Environment)
- [Git Flow and PR Review](https://github.com/Automattic/WP-Job-Manager/wiki/Our-Git-Flow-and-PR-Review)
- [String localisation guidelines](https://codex.wordpress.org/I18n_for_WordPress_Developers)
- [Running unit tests](https://github.com/Automattic/WP-Job-Manager/blob/trunk/tests/README.md)

## Coding Guidelines and Development 🛠

- Ensure you stick to the [WordPress Coding Standards](https://make.wordpress.org/core/handbook/best-practices/coding-standards/php/)
- Run our build process described in the document on [how to set up WP Job Manager development environment](https://github.com/Automattic/WP-Job-Manager/wiki/Setting-Up-Development-Environment), it will install our pre-commit hook, code sniffs, dependencies, and more.
- Whenever possible please fix pre-existing code standards errors in the files that you change. It is ok to skip that for larger files or complex fixes.
- Ensure you use LF line endings in your code editor. Use [EditorConfig](http://editorconfig.org/) if your editor supports it so that indentation, line endings and other settings are auto configured.
- When committing, reference your issue number (#1234) and include a note about the fix.
- Ensure that your code supports the minimum supported versions of PHP and WordPress; this is shown at the top of the `readme.txt` file.
- Push the changes to your fork and submit a pull request on the master branch of the WP Job Manager repository.
- Make sure to write good and detailed commit messages (see [this post](https://chris.beams.io/posts/git-commit/) for more on this) and follow all the applicable sections of the pull request template.
- Please avoid modifying the changelog directly or updating the .pot files. These will be updated by the WP Job Manager team.

## Feature Requests 🚀

Feature requests can be [submitted to our issue tracker](https://github.com/Automattic/WP-Job-Manager/issues/new?template=Feature_request.md). Be sure to include a description of the expected behavior and use case, and before submitting a request, please search for similar ones in the closed issues.
