---
status: pending
priority: p2
issue_id: '6'
source: human
author: MIGUEL ANGEL GARZON MALDONADO
scope: in_scope
tags: [code-review, pattern]
---

## Problem Statement

`tests/Call/Handler/RuntimeCallHandlerTest.php:16` — The test file declares two top-level fixture types inline (`RuntimeCallHandlerScopeFixtureInterface`, `RuntimeCallHandlerScopeFixture`, lines 16-22). No other test in the repo declares top-level helper types in the test file, a soft PSR-4 / one-class-per-file smell.

## Findings

- Two top-level types are defined alongside the test class in the same file.
- This is the only test in the repo doing so; other tests keep one class per file.
- `tests/` is not PSR-4 autoloaded, so inlining is functionally fine — autoload will not break.
- This is a convention/hygiene call, not a functional defect.

## Proposed Solutions

Option A: Move the two fixture types to a dedicated fixtures file (e.g. under `tests/Call/Handler/Fixtures/`) so the test file holds only the test class.

Option B: Reuse real Behat scope classes plus an interface they implement, removing the need for bespoke inline fixtures entirely.

## Acceptance Criteria

- `tests/Call/Handler/RuntimeCallHandlerTest.php` no longer declares top-level helper types inline.
- Fixtures live in a dedicated file or are replaced by real Behat scope classes.
- The test suite still passes.
