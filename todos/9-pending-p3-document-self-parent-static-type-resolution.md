---
status: pending
priority: p3
issue_id: '9'
source: human
author: MIGUEL ANGEL GARZON MALDONADO
scope: in_scope
tags: [code-review, docs]
---

## Problem Statement

`src/Call/Handler/RuntimeCallHandler.php` — `parameterAcceptsScope()` should carry a docblock note explaining that `self`/`parent`/`static` type declarations resolve to those literal strings and therefore never match. This is harmless today (no real hook fixture uses them) but is non-obvious to future readers.

## Findings

- A parameter typed with `self`, `parent`, or `static` will surface as the literal string rather than a resolved class name.
- The current matching logic will therefore never match such declarations.
- No real hook fixture uses these keywords, so there is no live impact.
- Documenting the edge case prevents future confusion.

## Proposed Solutions

Add a docblock note in `parameterAcceptsScope()` clarifying that `self`/`parent`/`static` declarations resolve to literal strings and are intentionally not matched.

## Acceptance Criteria

- `parameterAcceptsScope()` has a docblock note describing the `self`/`parent`/`static` resolution behavior.
