---
status: pending
priority: p2
issue_id: '5'
source: human
author: MIGUEL ANGEL GARZON MALDONADO
scope: in_scope
tags: [code-review, pattern]
---

## Problem Statement

`src/Call/Handler/RuntimeCallHandler.php:108` — `parameterAcceptsScope(\ReflectionParameter $parameter, object $scope): bool` is the only typed method in an otherwise phpdoc-only, untyped file. This mixes a modern typed signature into a legacy phpdoc-style class, creating intra-file inconsistency.

## Findings

- The file uses phpdoc annotations rather than native PHP type declarations for its public methods (`supportsCall`, `handleCall`).
- The newly added/edited helper `parameterAcceptsScope()` carries native param and return types, standing out as the lone typed method.
- This PR is a modernization effort, so the inconsistency is a style decision that should be resolved one way or the other.

## Proposed Solutions

Option A: Add native return/param types to the edited public methods for intra-file consistency (`supportsCall(): bool`, `handleCall(): CallResult`). Before doing so, verify the `CallHandler` interface permits the added return types (covariance / interface signature compatibility) so the implementation does not break the contract.

Option B: Revert the helper to phpdoc-only typing to match the surrounding legacy style, deferring full modernization.

## Acceptance Criteria

- The file is internally consistent: either all edited public methods + the helper are natively typed, or the helper matches the legacy phpdoc-only style.
- If Option A is chosen, the `CallHandler` interface compatibility is confirmed (no fatal signature mismatch) and tests pass.
