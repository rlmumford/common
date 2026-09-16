# Workflows

## `split.yml`

Pushes each package directory out to its own repository on every push to `2.x`.

**Adding a package**: add a row to the matrix in `split.yml`, create the target
repository, and give the module a `composer.json` naming it `rlmumford/<name>`.
Without that composer.json the split repo is not an installable package.

Add new repositories to the token's selected-repository scope before the first
split. The split action can report success even when GitHub rejects its push;
the verification step compares the public `2.x` contents with the package
directory and fails the job if publication did not happen.

**The split repos are outputs.** They are force-written on every push here, so a
commit made directly to one is lost the next time this runs. Work happens in
this repository.

### The token

The action pushes to *other* repositories, and the `GITHUB_TOKEN` GitHub
provides is scoped to this one — so it needs a personal access token in a secret
named `SPLIT_TOKEN`.

**Use a fine-grained token.** Grant it access to only the split repositories and
only one permission:

```
Repository access:  Only select repositories → document, field_tools, note,
                    commerce_invoice, task, checklist, service, plugin_reference
Permissions:        Contents → Read and write
```

That is genuinely all it does: write commits to those package repositories.

**If you use a classic token instead, `public_repo` is enough** — every split
repository is public. Do not reach for `repo`: there is no separate "write"
scope on classic tokens, `repo` *is* the write scope, and it carries write
access to every private repository you can see, plus issues, pull requests,
webhooks, deployments and settings. `public_repo` is the same power limited to
public repositories.

One thing that would change this: if a package directory ever contains its own
`.github/workflows`, pushing it needs the `workflow` scope as well. None of them
do today.

Typed Data Plus is published on Drupal.org and is no longer a GitHub split target.
See [its publication and retirement notes](../../docs/TYPED_DATA_PLUS_PUBLICATION.md).

## Pull request coding standards

`coder.yml` checks added and modified PHP files on every pull request, including
PRs targeting feature branches. It uses Drupal and DrupalPractice with a locked,
isolated Coder installation in `.github/coder`; it does not update module/runtime
dependencies. Unchanged legacy files are outside this check.

For same-repository PRs it first runs PHPCBF and commits automatic fixes back to
the source branch. A normal push refuses to overwrite concurrent changes. PHPCS
then fails on remaining errors or warnings. Fork PRs run PHPCS without writeback.
Because pushes using GITHUB_TOKEN do not start another PR workflow, the fixer
explicitly dispatches the Coder and task-package checks on its new commit. No
personal access token or privileged `pull_request_target` workflow is used.

Run the same check locally after installing the locked tool dependencies:

```sh
composer install --working-dir=.github/coder
CODER_BASE_SHA=origin/2.x CODER_HEAD_SHA=HEAD python3 .github/coder/check.py
```

Run PHP tools through the project's PHP environment (DDEV locally). The `--fix`
mode is for CI writeback and requires its branch/output environment variables.
