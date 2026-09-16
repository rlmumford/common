# Typed Data Plus

A Composer package containing independently enabled Drupal modules:

- `typed_data_plus` provides shared filtered typed-data fetching.
- `typed_data_reference` provides fields for storing typed values and entity
  references, including task contexts and checklist outcomes.
- `typed_data_context_assignment` maps available typed data into plugin inputs,
  with assignment forms, autocomplete, context handling and condition support.

Install `rlmumford/typed_data_plus`, then enable only the modules your application
requires. Enabling the new `typed_data_plus` module does not enable either existing
submodule. Existing module names, field types, configuration and PHP namespaces
are preserved. No database update is required for this additive module.

## Filtered fetching

Inject `typed_data_plus.data_fetcher` using
`Drupal\typed_data_plus\DataFetcherInterface`. The service extends the normal
Typed Data fetcher API and adds:

- `fetchFilteredData($data, $expression, $metadata, $langcode)` for typed results.
- `fetchFilteredDefinition($definition, $expression, $langcode)` for configuration
  contexts without executing filters or loading runtime values.
- `parsePropertyPathAndFilters()`, `applyFilters()` and `applyFiltersToValue()` for
  callers migrating from Entity Template's extended fetcher. The raw-value API
  preserves trusted Markup as the final result; consumers must escape other values.

For example, `field_date.value|date_add('P1D')` selects a property and applies a
registered filter. An expression starting with `|` filters the supplied value.
The shared Typed Data filter manager supplies plugins. Entity Template currently
provides `date_add`, `date_sub`, `option_label` and `format_field`; enable it to use
those filters until their coordinated extraction is released.

Arguments are literal strings, not executable expressions. Single or double
quotes protect commas, dots, pipes and parentheses: `|default('a.b,c|d(e)')`.
Backslash escapes the enclosing quote or another backslash; other backslashes
are preserved. Empty parentheses or no parentheses mean zero arguments; `('')`
means one empty argument. Invalid syntax, unsupported data types and invalid
arguments are rejected. Unlike the old permissive parser, empty pipe segments
and malformed quoted arguments are not silently accepted. Validate existing
configuration before moving callers to this API.

New filters needing the current typed object implement
`WrappedValueFilterInterface`. Existing Entity Template `usesWrappedValue()`
methods also work during migration. After a scalar transformation, the next
wrapped filter receives a wrapper for the new value and definition; the original
entity/field wrapper is retained only while no transformation has replaced it.
Filters must accurately declare their result through `filtersTo()`.

Pass a `BubbleableMetadata` object to collect traversal/filter cache metadata and
attachments. Fetching is not an authorization boundary: consumers must enforce
entity/field access and propagate collected metadata when rendering results.

## Entity Template migration status

This first P1 slice introduces a separately named service. It does not replace
`typed_data.data_fetcher` or `typed_data.placeholder_resolver`, and does not alter
existing Entity Template callers or duplicate its filter plugin IDs. Kernel tests
exercise standalone use and coexistence with Entity Template alpha17, including
its legacy wrapped-field filter.

`typed_data_plus.placeholder_resolver` now applies the same parser and filters,
including wrapped values and output escaping. It is available without Entity
Template. The coordinated Entity Template branch delegates its legacy service IDs,
classes, Twig and selectors to these shared services. Existing alpha17 remains
supported until that migration is released. Filter ownership transfer, the
condition evaluator and Views predicates remain subsequent P1 work. This package does not yet provide a condition-string evaluator.

Source lives in `rlmumford/common` on `2.x`, under `modules/util/typed_data_plus`.
The `rlmumford/typed_data_plus` repository is an automated split output.
