# Typed Data Plus: ownership and publication

Typed Data Plus is maintained directly in the
[Drupal.org GitLab repository](https://git.drupalcode.org/project/typed_data_plus),
using branch `2.0.x` and Composer identity `drupal/typed_data_plus`.
The repository owns the root module, `typed_data_reference` and
`typed_data_context_assignment` submodules, tests and GitLab CI. Common does not
contain a source copy and does not split or mirror this package.

The initial `2.0.0-alpha1` release is published and resolvable from the standard
Drupal Composer repository. Entity Template's dependency migration is merged.
Common's task/checklist packages require `drupal/typed_data_plus:^2.0@alpha`.
Condition plugins and provider-aware context mapping are subsequent development
work; they are not part of alpha1. Their code is merged into
[`2.0.x`](https://git.drupalcode.org/project/typed_data_plus/-/tree/2.0.x) through
[MR !1](https://git.drupalcode.org/project/typed_data_plus/-/merge_requests/1).
Publish a new release to make these features available to Composer consumers;
never alter the alpha1 tag.

## Development and release process

1. Make code changes and commits in the Drupal.org repository. Run its Coder and
   module test suite through GitLab CI and review the feature branch before merge.
2. Tag reviewed code on `2.0.x` with the next appropriate release number and publish
   the release through the Drupal.org project page.
3. Verify the release is indexed by `packages.drupal.org/8` and installable in a
   fresh Composer consumer. Update Common/Entity Template requirements when a
   feature needs the new release; do not substitute unpublished local source in
   Common's package tests.
4. Keep Common's workflow architecture and implementation plan here. Link to
   Typed Data Plus implementation and tests in the Drupal.org repository.

## Existing consumers and GitHub retirement

Do not install `rlmumford/typed_data_plus` and `drupal/typed_data_plus` together.
Remove the old root requirement and custom GitHub repository entry when updating
consumer lockfiles with task/checklist. Enable the root `typed_data_plus` module
before deploying Entity Template's migrated code.

The GitHub split target and token-access instructions have been removed.
`rlmumford/typed_data_plus` can be deleted once known consumer lockfiles no longer
reference its source or distribution URLs and a fresh install succeeds.
Christian Jobs still has old package/repository references in its lockfile;
that consumer migration and repository deletion remain separate work.
