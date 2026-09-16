# Typed Data Plus

A Composer package containing two independently enabled Drupal modules:

- `typed_data_reference` provides fields for storing typed values and entity
  references, including task contexts and checklist outcomes.
- `typed_data_context_assignment` maps available typed data into plugin inputs,
  with assignment forms, autocomplete, context handling and condition support.

Both extend Drupal typed data. Install `rlmumford/typed_data_plus`, then enable
only the modules your application requires. Existing module names, field types,
configuration and PHP namespaces are preserved; there is no umbrella module
that enables both automatically.

Source lives in `rlmumford/common` on `2.x`, under `modules/util/typed_data_plus`.
The `rlmumford/typed_data_plus` repository is an automated split output.
