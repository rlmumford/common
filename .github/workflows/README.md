# Workflows

## `split.yml`

Pushes each package directory out to its own repository on every push to `2.x`.

**Adding a package**: add a row to the matrix in `split.yml`, create the target
repository, and give the module a `composer.json` naming it `rlmumford/<name>`.
Without that composer.json the split repo is not an installable package.

**The token**: the action needs a `SPLIT_TOKEN` secret — a personal access token
with `repo` scope on the target repositories. The default `GITHUB_TOKEN` cannot
push to another repository.

**The split repos are outputs.** They are force-written on every push here, so a
commit made directly to one is lost the next time this runs.
