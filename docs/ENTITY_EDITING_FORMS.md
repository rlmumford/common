# Entity editing for checklist items

Status: source investigation and revised design, 20 September 2026.
Runtime integration is not yet proven. This extends the
[workflow implementation plan](WORKFLOW_IMPLEMENTATION_PLAN.md) and
[checklist interaction contract](CHECKLIST_INTERACTION_CONTRACT.md).

## Recommendation

Build on the existing Drupal.org Flexiform `2.0.x` implementation for editing
multiple entities. Use standard entity form displays for the single-entity case.
Support a referenced display and an embedded display definition; embedded
configuration must not require saving a site-wide form display configuration entity.
Reuse suitable Webform elements through an optional Flexiform integration, using
Webform's existing element-processing API before considering service overrides.

The communication, document or other target entity is the saved result. Editing it
must not require creating a Webform submission, even temporarily in the database.
Checklist outcomes reference the resulting entities. Working edits belong to the
existing checklist workspace and attempt lifecycle.

This is a revision of the earlier assessment that Webform necessarily requires a
saved submission. There are two supported extension approaches in its source:

- A full Webform can disable submission persistence with `results_disabled` and
  run custom handlers. It still uses an in-memory Webform submission object and
  the submission lifecycle.
- A custom Form API form can use Webform's element manager without a Webform
  submission object. The shipped `webform_example_custom_form` module demonstrates
  this, including composites, enhanced inputs and direct configuration saving.

The second approach is the promising integration point for Flexiform. It does not
automatically provide the whole Webform builder, wizard, token, handler or file
lifecycle. Compatibility must be established for each supported element family.

## Required changes to Flexiform's approach

The existing modern implementation is a starting point. The required design goes
beyond its current bundle-specific form displays:

1. **Shared context selection.** The form entity manager must use TypedDataPlus's
   context handler for both configuration-time selection and runtime assignment.
   This includes related entities, local contexts and global provider contexts.
2. **Bundle-independent displays.** A reusable display may specify any bundle of
   its base entity type. Editing a related contact must not require duplicating
   the form for every bundle of the task or service providing that contact.
3. **Embedded configuration.** Other configuration entities and checklist item
   plugins can own the entire form definition, including entity mappings,
   components, layout and presentation settings. No separate saved form is needed.
4. **Custom elements.** Forms may mix field widgets and additional elements, with
   optional Webform integration where its element APIs fit.

### Context values and write-back locations

Prefer an optional write-back capability on context classes, retaining ordinary
typed-data definitions such as `entity:communication` and `string`. A context's
value type describes the data; its ability to write back describes how the value
is bound to a source. Do not invent separate saveable variants of every data type.
Exact interface and method names should be settled by the first implementation.

Distinguish these operations:

- Editing and saving an existing entity obtained from a context does not normally
  require changing the relationship through which it was found.
- Creating or replacing a related entity requires writing it back to the selected
  source reference, then persisting the affected owner as well as the target.
- Editing a scalar requires a writable source property and a known persistence
  owner; a copied or computed value alone is insufficient.

TypedDataPlus currently creates a fresh ordinary `Context` in
`ContextHandler::applyContextMapping()`, including for direct root assignments.
Adding a Flexiform context subclass alone would therefore lose its capability at
the assignment boundary. Extend assignment so an explicitly supported source
binding survives direct assignment and supported property traversal, alongside
the resulting definition and cache metadata. Keep ordinary read-only consumers
unchanged and preserve core's required/optional and type checks.

The narrow shared capability belongs in TypedDataPlus. Flexiform owns editability
configuration, entity access, validation and coordination of persistence. Writing
an edited value back into its source graph must be explicit; context selection,
form building and ordinary `setContextValue()` must not trigger database writes.
The form entity manager collects the affected entities and controls their saves.

A source binding must identify the root and the actual property/reference location,
including the list item and revision/translation where relevant. It must survive
the supported form-state/workspace round trip without a serialized closure or an
implicit reload by numeric ID. A replaced reference or reordered list must be
detected before a stale edit is applied to a different target. Two bindings that
resolve to the same entity share the working entity and do not independently save
conflicting copies. Missing required sources and getter cycles are visible errors.

Filters are not implicitly reversible. A computed string has no default write-back
operation. A filter that selects an existing entity can still yield an editable
entity, subject to access, without granting permission to replace the source
relationship. Only explicit source-preserving behavior can retain a write-back
binding. Global context providers likewise do not gain write capability merely
because their values are discoverable.

### Wildcard and embedded form definitions

Use one form-definition shape for reusable configuration and inline configuration.
It describes the base entity type, an exact bundle or wildcard, named contexts,
components and layout. A saved reusable definition has its own machine name;
an inline definition is stored inside the owning configuration/plugin and has no
independent configuration identity.

Core's display storage is not a transparent home for a literal wildcard:
`EntityDisplayBase::id()` includes the bundle, configuration names forbid `*`, and
display dependency/field discovery assumes a real bundle. The recommended approach
is a Flexiform-owned reusable configuration entity whose definition can contain
`bundle: '*'`, plus the identical definition embedded in consumer configuration.
Continue accepting existing core form-display references. Render using the existing
display/component machinery with the supplied entities' concrete runtime definitions.
Do not save per-bundle copies or alter the shared definition while binding it.

Selection must be deterministic. An explicitly chosen definition or core display
is authoritative; a missing or incompatible explicit selection is an error. If a
consumer uses form-mode lookup, prefer exact bundle/requested mode, then
wildcard/requested mode, then exact bundle/default and wildcard/default. Cover this
order in tests rather than relying on incidental configuration load order.

The wildcard relaxes the base bundle constraint, not the base entity type or the
constraints of related contexts. Configuration-time discovery must not pretend all
bundle-specific fields exist on every bundle. Expose known/common definitions and
explicitly constrained related entities; validate components against the actual
entity definitions when binding. Missing required components fail before writes.
Runtime widget caches must be scoped to the concrete definitions, so rendering a
second bundle in the same request cannot reuse the first bundle's widgets.

Reuse the embedded definition's configuration schema and editing form in the
owning checklist plugin. Propagate component/plugin/configuration dependencies
and translatable labels to the owning config entity. Preserve the embedded form
in the checklist item's existing configuration snapshot; changing job defaults
must not silently replace an in-progress item's form. Runtime entity values and
write-back bindings belong to working state, not exported configuration.

Custom components must declare where entered values go: an explicit writable
binding, declared transient working data, or an outcome produced on successful
completion. They must not quietly invent Webform submissions to store those values.

### Architectural review

- `[GOOD]` One context selection system covers related entities and global providers.
- `[GOOD]` Wildcard and embedded definitions remove configuration duplication.
- `[FLAG]` Source bindings must survive context assignment; a subclass alone is insufficient.
- `[FLAG]` Core display identity and dependency handling require explicit wildcard adaptation.
- `[FLAG]` Write-back capability does not confer entity or field access.
- `[FLAG]` Keep definition storage separate from per-attempt entities and source locations.

## What D7 Flexiform contributes

The important model is a set of named entities, with elements bound to their
fields, rather than a separate answer record copied into those entities afterward.

| Capability | D7 implementation | Requirement to preserve |
| --- | --- | --- |
| Named entity graph | `FlexiformFormEntityManagerDefault` prepares getter dependencies and caches entities by namespace | Supply the template result directly; resolve other entities from typed contexts |
| Load or create related entities | Getter plugins, including entity references and Entity Template | Explicit creation policy; distinguish missing optional entities from invalid configuration |
| Mix fields from several entities | `FlexiformBuilderFlexiform` delegates elements to their named entity | Reuse Drupal field widgets, with unambiguous form parents |
| Local field presentation | `FlexiformElementField` overlays form-specific instance/widget settings | Custom labels, widgets and arrangement without changing global field definitions |
| Controlled persistence | `skipOnSave()`, per-entity save handlers and callbacks | Validate first; explicitly select what is saved and preserve references |
| Rebuilds and embedding | Builder keeps entity manager in form state, scoped by form parents | Retain unsaved entities and user edits across validation and AJAX rebuilds |
| Checklist integration | `manual.item_plugin.inc` delegates build/validate/submit and records entity outcomes | Saving progress is distinct from completing an item |
| Template editing | Template CI and Entity Template UI build an entity, then hand it to a form | Apply defaults once, allow edits, save the edited result |

The 11.5 manual handler also exposes Save & Continue Editing, which saves entity
data without completing the item. That is different from retaining unsaved edits
in working state. Preserve the distinction in action operations and audit history.

Do not reproduce D7's implementation weaknesses as requirements: unchecked getter
cycles, repeated parent saves, or silently hiding a configured field because its
required entity could not be resolved.

## Existing modern Flexiform

The Credit checkout contains Flexiform `2.0.x` at
`2d2e28b9b53e3e57496de6f7c684528446c20663`. A read-only remote lookup confirmed that
this is also the Drupal.org branch head at investigation time.

It already supplies:

- `FlexiformEntityFormDisplay`, extending Drupal's `EntityFormDisplay`, with
  component plugins and a `buildAdvancedForm()` entry point accepting named entities.
- `FlexiformFormEntityManager`, lazy entity contexts and provided/load/current-user/
  referenced-entity plugins.
- Native field widget components, additional form elements and custom text.
- A multiple-entities configuration UI stored in display third-party settings.
- Per-entity `save_on_submit` and deferred parent saves for reference updates.

This is a substantially closer starting point than implementing another entity
manager inside Webform. It is not yet a proven checklist editor. Concrete gaps to
exercise and fix before integration are:

1. Its Composer constraint still requires `drupal/ctools:~3.0-beta1`; declared
   Drupal 10/11 compatibility alone does not prove a fresh dependency solve.
   Existing tests also contain older Drupal test APIs and need a runnable baseline.
2. The Inline Entity Form bridge explicitly leaves deferred related-entity saving
   as a TODO. Checklist completion cannot rely on an entity-form `::save` button
   being present; the caller must control the final save boundary.
3. Field widgets check field access, but tests must establish create/update access
   and constraint validation for every participating entity, including related
   entities, with errors mapped back to the correct widget.
4. Deferred saves are keyed by entity type and numeric ID, which does not distinguish
   multiple unsaved entities of the same type. Preserve UUID-based identity and
   deduplicate aliases referring to the same entity.
5. Entity plugins currently wire context mappings directly from the manager.
   Integrate the standard context handler and TypedDataPlus so supplied contexts,
   filtered fetches and global provider contexts have the same meaning as elsewhere.
6. The referenced-entity plugin writes delta zero. Arbitrary deltas and repeated
   related entities require explicit support and tests rather than implied support.
7. Flexiform changes the entity-form-display class site-wide. Preserve ordinary
   form behavior and check coexistence with other display integrations.

An embedded or wildcard definition should instantiate a runtime display using the
same component model. Existing UI routes assume a saved, concrete-bundle display;
reusable definition storage, embedded configuration editing and dependency/schema
reporting remain implementation work as specified above.

## Where Webform fits

| Approach | What can be reused | Work still required | Direction |
| --- | --- | --- | --- |
| Core form display | Field widgets and their entity data model | Embedded configuration and caller-controlled integration | First single-entity proof |
| Existing modern Flexiform | Multi-entity contexts, widgets and display UI | Compatibility, validation, save boundaries and embedded editing | Foundation for richer forms |
| Full Webform with storage disabled | Builder, elements, validation and handlers | Bidirectional field mapping, entity access/validation, multi-entity persistence | Useful optional frontend if a concrete form needs it |
| Webform elements in Flexiform | Element processing, composites and presentation | Explicit field/property bindings and supported-element coverage | Preferred optional element integration |
| Replace Webform services to change its data model | Some existing Webform infrastructure | Submission assumptions across forms, plugins, handlers, tokens and files | No evidence this is necessary for the first implementation |

`WebformElementManager::processElements()` processes ordinary form arrays, and
`buildElement()` explicitly allows a non-Webform form object. Use those APIs and
component/element plugins before changing global services.

`WebformElementBase` still has submission-typed formatting and lifecycle methods.
Managed-file processing includes submission-specific ownership, paths and access.
Initially use native file field widgets for entity attachments. A general Webform
file adapter must prove file usage, private download access and cancellation cleanup.

Existing community integrations also deserve credit: Webform Entity Handler
advertises entity creation and updates, and Webform Content Creator maps answers
to content fields. Their project descriptions establish mapping capabilities, not
equivalence to Flexiform's entity graph or direct field-widget editing. Neither
was runtime-tested in this investigation.

## Shared execution boundaries

- TypedDataPlus resolves named input contexts and expected definitions. An entity
  fetched through a filter is not automatically a writable property path. Writes
  must target explicit entity/field/property bindings with access and type checks.
- Resolve/create the editable graph once per working attempt. Rebuilding a form
  must not rerun templates, recreate child entities or overwrite user edits.
- Extract submitted values into that graph, validate every entity to be persisted,
  then save through a common handler path callable from action operations too.
  Browser Form API validation must not become the only route to business validation.
- Reject stale ownership/version submissions using the existing checklist storage
  contracts. Form-state snapshots must not bypass the shared workspace.
- Hidden, inaccessible and omitted fields retain their existing values unless an
  explicit operation clears them. Presentation conditions are not access checks.
- Save related entities in a defined order and preserve reference updates. Database
  rollback cannot undo external effects; keep communication sending as an explicit
  operation and retain the existing durable attempt/audit boundary.
- Store final entities as typed outcomes. Keep unfinished edits as working state;
  retain failed-attempt state and separate fresh attempts as already agreed.

## Reviewable implementation sequence

1. **Establish compatibility and context bindings.** In the Flexiform repository,
   establish Composer and Drupal test compatibility. In TypedDataPlus, prove
   source-bound context assignment and supported write-back without adding implicit
   saving. In Flexiform, replace direct context wiring with the shared handler.
   Test local/global selection, direct and nested bindings, missing related entities,
   new-reference attachment, scalar updates, non-reversible filters and stale targets.
2. **Prove wildcard and embedded editing.** Add a common definition schema and
   reusable Flexiform definition storage, retaining core display references.
   Use one wildcard definition from two different base bundles to edit a related
   entity. Prove the equivalent definition works inside a checklist configuration
   without creating a standalone config entity, including export/import dependencies.
   Verify a supplied unsaved entity can be built, edited, rebuilt and extracted
   without saving. Add a two-entity case and fix validation/access and persistence
   gaps revealed by it. A rejected/cancelled form saves neither target nor
   submission; successful submission saves only the declared targets.
3. **Integrate the real template handlers.** In Entity Template, support inline
   definitions, conditional components and applying to an existing target. In
   Common, add `entity_template__create` and `entity_template__apply_to`, with
   automatic execution and optional editing using the proven display path.
   Prove later checklist items can use their entity outcomes as contexts.
4. **Add selected Webform elements.** Implement an optional `flexiform_webform`
   integration in the Flexiform project, starting with a simple value and a
   composite. Prove defaults, extraction, validation, access and AJAX behavior
   without constructing or saving a Webform submission. Extend the supported set
   based on actual form requirements. This is not a dependency of step 3.
5. **Exercise job and task authoring.** Test saved job configuration, including
   triggers, assignment rules and checklist handlers, through the task UI and
   resource pane. Keep this generic rather than tied to Christian Jobs roles.

Use one test-suite step per affected submodule in CI, following the agreed workflow
convention. Keep tests inside those suites; do not add a workflow step per scenario.
Flexiform and Entity Template changes belong in their Drupal.org repositories;
Common owns checklist integrations and this cross-project plan.

## Evidence and verification limits

This investigation reviewed source; it did not execute browser or kernel tests.
No host PHP/Composer executable was available for a local compatibility probe.

- CounselKit `origin/11.5.x` at `6b33115a4ce51521df7a691a44cd549822ca419b`:
  `ck_task/includes/checklist_item/manual.item_plugin.inc` and
  `ck_template/includes/checklist_item/entity_from_template.item_plugin.inc`.
- D7 Flexiform source inspected in the local Platform build under
  `profiles/counselkit/modules/contrib/flexiform`, especially
  `includes/flexiform.form_entity_manager.inc`,
  `includes/builder/flexiform.builder.inc` and reference/field plugins.
  This installed copy is separate from the 11.5 make-file pin; it is behavioral
  source evidence, not a claim of byte-for-byte 11.5 dependency verification.
- [Modern Flexiform source](https://git.drupalcode.org/project/flexiform/-/tree/2.0.x),
  at the revision above, inspected from the Credit Composer checkout.
- TypedDataPlus checkout at `e10ae7701dd58a7840c45729b7576de909e93bca`:
  `src/Plugin/Context/ContextHandler.php`, `DataContextDefinition.php`,
  `src/DataFetcher.php`, and the context-assignment submodule's selector UI.
  These establish the current read/selection behavior; write-back is proposed work.
- Local Drupal core `EntityDisplayBase.php` and `ConfigBase.php`: bundle-specific
  display identity/dependencies and the restriction on `*` in configuration names.
- Webform `6.3.0-beta6`, inspected from the CMS checkout:
  `src/Plugin/WebformElementManager.php`, `src/Plugin/WebformElementBase.php`,
  `src/WebformSubmissionStorage.php`, `src/Plugin/WebformElement/WebformManagedFileBase.php`
  and [the custom-form example](https://git.drupalcode.org/project/webform/-/blob/6.3.0-beta6/modules/webform_example_custom_form/src/Form/WebformExampleCustomFormSettingsForm.php).
- [Webform storage-disabled discussion](https://www.drupal.org/project/webform/issues/2908315),
  corroborated by the inspected storage code rather than treated as a current
  compatibility guarantee for all elements.
- [Webform Entity Handler](https://www.drupal.org/project/webform_entity_handler) and
  [Webform Content Creator](https://www.drupal.org/project/webform_content_creator).
