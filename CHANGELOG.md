# Changelog

## Unreleased

- Added a bounded normalized diff builder with unified, split, and fields presentations.
- Added a bounded Structured JSON viewer with Tree/JSON modes for Safe Metadata and authorized Developer Details.
- Added package-owned Filament CSS assets for reliable styled diff rendering without host Tailwind scanning.
- Added global diff limits, panel-level style configuration, and optional validated runtime style switching.
- Added structured diff documentation and regression coverage for nested paths, null/missing values, list indexes, limits, and style tampering.
- Separated consumer installation documentation from contributor-only sibling-checkout and path-repository setup.
- Added Filament Directory-compatible hidden image markup to prevent README artwork duplication on the listing page.
- Added Larastan/PHPStan level-5 analysis and package quality workflow.
- Hardened static typing around Filament resource callbacks.
- Retained panel-scoped authorization, safe timelines, diff rendering, and original-actor privacy controls.
- Polished the Audit Explorer defaults with authorized event-name navigation, toggleable secondary columns, concise actor/subject labels, relative UTC-safe timestamps, and clearer empty/search states.
- Refactored Audit Detail into independent desktop information and changes stacks to remove row-coupled whitespace while preserving responsive single-column rendering.

## 0.1.0

- Initial public release for the Filament 5 explorer and record-history integration.
