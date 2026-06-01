---
status: pending
priority: p2
issue_id: '4'
source: human
author: MIGUEL ANGEL GARZON MALDONADO
scope: in_scope
tags: [code-review, architecture]
---

## Problem Statement

`src/Call/Handler/RuntimeCallHandler.php` — The widened `instanceof` check combined with the first-match `break` in the hook scope-injection loop could bind the scope to the WRONG parameter. When a hook declares two object-typed parameters where the first is typed to a parent/interface that the scope satisfies (e.g. `HookScope`/`ScenarioScope`) and the second is the intended concrete scope, the loop will bind to the first assignable parameter and stop. This is a behavior change versus the old exact-class-name string comparison.

## Findings

- The old implementation compared the parameter type against the exact class name string, so only an exact match would bind.
- The new `instanceof`-based check is more permissive: any parameter whose declared type the scope is assignable to will match.
- Combined with the first-match `break`, the first assignable parameter wins, even if a later parameter is the intended concrete scope target.
- The scenario is contrived: no current fixture declares two object-typed params in this configuration, so there is no live bug today.
- Still a latent correctness/architecture concern because it silently diverges from the prior exact-match semantics.

## Proposed Solutions

Option A (preferred): Prefer an exact type-name match first; fall back to `instanceof` only when no exact match is found. This preserves legacy behavior while adding the interface/parent flexibility.

Option B: Explicitly document "first assignable parameter wins" as intended behavior, both in code comments and in the hook contract docs, so the semantics are deliberate rather than accidental.

## Acceptance Criteria

- The chosen behavior (exact-first-then-instanceof, or documented first-assignable-wins) is implemented and clearly commented.
- A test fixture/case covers a hook with two object-typed parameters where the first is a parent/interface and the second is the concrete scope, asserting the correct parameter is bound.
- No regression in existing hook scope-injection tests.
