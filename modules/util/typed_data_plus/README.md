# Typed Data Plus

A Composer package containing independently enabled Drupal modules:

- `typed_data_plus` provides shared filtered typed-data fetching.
- `typed_data_reference` provides fields for storing typed values and entity
  references, including task contexts and checklist outcomes.
- `typed_data_context_assignment` maps available typed data into plugin inputs,
  with assignment forms, autocomplete, context handling and condition support.

Install `drupal/typed_data_plus:^2.0@alpha`, then enable only the modules your application
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
remaining condition grammar and Views predicates remain subsequent P1 work. The
development branch now includes the data-predicate subset described below.

Source lives in `rlmumford/common` on `2.x`, under `modules/util/typed_data_plus`.
The Drupal.org `typed_data_plus` project publishes this package with Composer
identity `drupal/typed_data_plus`. The old GitHub repository is being retired;
it is no longer a split target. Remove any old root requirement for
`rlmumford/typed_data_plus` when updating task/checklist together; the two package
identities must not be installed alongside each other.

## Condition strings (development branch)

`typed_data_plus.condition_evaluator` now supports the data-predicate subset of
CounselKit condition strings. This is unreleased work after `2.0.0-alpha1`.
Inject `ConditionEvaluator` and call `evaluate($expression, $contexts)` with a map
of context names to `TypedDataInterface` objects. Represent a known missing value
as typed data containing NULL, so its expected definition remains available.

```text
count >= {{minimum}} and (name|upper == 'READY' or override notempty)
choices contains 'approved'
missing|default('a.b,c|d(e)') == 'a.b,c|d(e)'
not (name empty or NEVER)
```

Supported predicates: `exists`, `empty`, `notempty`, `==`, `!=`, `<>`, `>`, `>=`,
`<`, `<=`, `contains`, `notcontains`, `in`, `notin`. Use parentheses when mixing
AND and OR at one level; keywords are case-insensitive except the `NEVER` literal.
Both prefix `not (...)` and `subject not predicate` are supported. An empty top-level
expression means no restriction; empty nested groups are invalid.

`{{selector}}` on the right resolves a typed value through the data fetcher, not
through HTML placeholder substitution. Filtered selectors work on either side.
Arguments are literals; quote strings containing whitespace or boolean words.
Numeric/boolean literals retain their types. Scalar comparisons follow PHP's
comparison semantics; list membership is strict, and string membership checks
substrings. Missing values never satisfy binary comparisons: use `empty` or
`exists` to test availability. `empty` retains CounselKit/PHP empty-value semantics,
including zero, false and the string `"0"`.

`validate($expression, $definitions)` accepts expected data definitions and checks
all paths/filter definitions without fetching values or executing filters. Runtime
evaluation also validates every branch before evaluating; invalid configuration
cannot hide in an unselected OR branch. Unknown contexts, unsupported predicates,
invalid paths and malformed expressions throw `ConditionException`, distinct from
a valid false result. This deliberately rejects malformed strings that CounselKit
11.5's permissive parser could truncate.

The result exposes `isMet()`, `getReasons()` and bubbleable metadata. Reasons use
configured expressions rather than resolved values. Every branch is evaluated to
collect metadata; nothing is cached across calls. Callers must still enforce
context access and carry appropriate access/cache metadata on supplied values.

Views, regex `matches`, Rules `passes`, `with`, checklist-specific predicates and
bare item-completion shorthand are not implemented yet. They raise configuration
errors. Their adapters/extension registry, semantic reference descriptions and
Entity Template component conditions remain P1 work; do not migrate complete
CounselKit job configurations to this subset yet.
