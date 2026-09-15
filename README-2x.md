# 2.x

A branch that can be required by a modern Drupal 10 site.

## What changed from 1.x

**The dependency list is down to one.** 1.x required eleven packages in
`require`, several pinned to dev branches, and any site taking the package
inherited all of them. In practice a consumer wants one or two modules out of
twenty, so the rest are now **suggestions**: `aws/aws-sdk-php`,
`drupal/block_class`, `drupal/ctools`, `drupal/mini_layouts`, `drupal/name`,
`drupal/pdf_tools`, `drupal/range`, `drupal/views_block_filter_block`. Only
`drupal/entity` remains required, because the access handlers use it and every
module here has one.

`drupal/block_class: 1.x-dev` was the pin that made 1.x uninstallable alongside
anything modern — a site on `^3` could not take the package at all.

**The composer-patches fork is gone.** 1.x required
`cweagans/composer-patches: dev-relative-patches-1.x` from a separate VCS repo,
which meant every consumer had to add that repository too. The patches it
carried are all remote URLs with no relative paths, so nothing needed the fork;
the patch block has been removed, and a consuming site declares whichever of
those patches it still wants.

**`webmozart/path-util` is gone**, along with two other unused imports in
`ScriptHandler`. The package is abandoned upstream and was never called.

## What it means for a consumer

```json
"repositories": [
  { "type": "vcs", "url": "https://github.com/rlmumford/common.git" }
],
"require": {
  "rlmumford/common": "2.x-dev"
}
```

and then enable the one or two modules you want, adding their suggested
dependencies as you go.
