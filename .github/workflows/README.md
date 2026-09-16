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
