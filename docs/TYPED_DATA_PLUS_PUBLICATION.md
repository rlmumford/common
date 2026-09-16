# Typed Data Plus: Drupal.org publication

Status, 16 September 2026: project created; code pushed to Drupal.org branch
`2.0.x` at `aa33954`; tag `2.0.0-alpha1` pushed. Contents match Common `d316fb4`.
The independent consumer CI passed with the new Composer identity. The Drupal.org release page is published. A clean Composer consumer installed
`drupal/typed_data_plus:2.0.0-alpha1` from the standard Drupal repository with
Drupal 10.6.16 and Typed Data 2.1.1. The migrated Entity Template branch also
installed against that published package; only the unreleased Entity Template
code used a local path repository. No custom Typed Data Plus repository was used.
Composer reported no security advisories. This verifies package installation,
not an existing-site deployment or Drupal 11 runtime support.

Publication is required before releasing Entity Template's shared-fetcher
migration. The current GitHub-only dependency works only when a consuming site's
root Composer configuration adds its repository. That is not the intended public
installation experience.

## Project page draft

Name: **Typed Data Plus**

Machine name: **typed_data_plus** (subject to Drupal.org availability).

Description:

> Typed Data Plus provides filtered typed-data fetching and placeholder resolution,
> typed-data reference fields, and plugin context assignment. It builds on the
> Typed Data API Enhancements module. Its shared services let templates and workflow
> modules use consistent data selection and filter behavior.
>
> This project is under active development. The initial release is an alpha;
> public APIs may change. It does not yet provide the planned condition-string
> evaluator. It is maintained as part of RLMumford Common; the Drupal.org repository
> receives the package extracted from that source.

Use a full project so a named Composer package and releases can be provided.
Do not claim security advisory coverage or a stable API merely by creating it.
Only list supported Drupal/PHP versions verified by the release tests.

## Publication sequence

1. Create the Drupal.org project under the maintainer's account. Project-page
   creation needs an authenticated Drupal.org session; existing SSH Git access is
   sufficient to push code once the repository exists, not to create that page.
2. Publish the current Common work through `2.x`, including the shared resolver.
   Preserve all three module machine names and current directory layout.
3. Change the package's Composer name to `drupal/typed_data_plus`. Use Drupal.org
   branch `2.0.x` and an initial `2.0.0-alpha1` release, preserving the major version
   associated with Common's existing package. Do not set a hardcoded Composer
   version; release metadata comes from the tag.
4. Update task and checklist to require `drupal/typed_data_plus:^2.0@alpha`, and
   update their independent-consumer path-repository version mapping. Update
   Entity Template to the same dependency. These coordinated metadata changes must
   not land while Drupal.org cannot resolve the package.
5. Avoid installing both package names over the same module directory. Consumers
   should remove their root `rlmumford/typed_data_plus` requirement, if any, and
   update dependent packages together. Add a conflict with the obsolete package
   identity rather than an unbounded `replace` claiming compatibility with all
   past/future releases. Keep the GitHub repository as a mirror, not a separately
   versioned implementation.
6. Publish the extracted package to Drupal.org and create the alpha release.
   Check its metadata through `packages.drupal.org/8` and prove that a fresh Drupal
   consumer can require Entity Template without a custom Typed Data Plus repository.
7. Test an existing installation's Composer transition and module enablement,
   including reference/context-assignment fields and template configuration.
   Enable `typed_data_plus` before deploying Entity Template's migrated code.
8. Release Entity Template only after those checks pass. Wire subsequent Common
   package publication to Drupal.org with repository-scoped credentials; do not
   assume the GitHub SPLIT_TOKEN authenticates to Drupal.org.

The repository remains authored in Common. The publication mechanism must preserve
Drupal.org history/tags and verify the published content, just as the GitHub split
is verified. The initial alpha publication and normal Composer installation proof are complete.
Existing-site deployment and automatic Drupal.org mirroring remain separate work.

References: [creating a project](https://www.drupal.org/docs/develop/managing-a-drupalorg-theme-module-or-distribution-project/creating-a-new-project),
[project types](https://www.drupal.org/docs/develop/managing-a-drupalorg-theme-module-or-distribution-project/sandbox-projects),
[Composer naming](https://project.pages.drupalcode.org/coding_standards/composer/package-name/).

## Initial alpha release notes

Initial alpha providing shared filtered typed-data fetching, placeholder
resolution, typed-data reference fields and plugin context assignment. Includes
quoted filter argument parsing, wrapped-value compatibility and definition-only
fetching. Public APIs remain under development; the planned condition-string
evaluator is not included. Requires Drupal 10/11, PHP 8.1+ and Typed Data 2.1+.

Common remains the source repository. Until Drupal.org publication credentials
are configured in CI, subsequent Drupal.org updates must be explicitly extracted,
content-verified and pushed; the GitHub split does not update Drupal.org.
