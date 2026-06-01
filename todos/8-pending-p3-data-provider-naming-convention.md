---
status: pending
priority: p3
issue_id: '8'
source: human
author: MIGUEL ANGEL GARZON MALDONADO
scope: in_scope
tags: [code-review, pattern]
---

## Problem Statement

`tests/Call/Handler/RuntimeCallHandlerTest.php` — The data-provider method name `parameterMatchingProvider` drifts from the naming used in `ScenarioStateArgumentTest::getArguments`. There is no consistent data-provider naming convention across the test suite.

## Findings

- `RuntimeCallHandlerTest` uses a `*Provider` suffix (`parameterMatchingProvider`).
- `ScenarioStateArgumentTest` uses `getArguments` with no suffix.
- Optional standardization opportunity; no functional impact.

## Proposed Solutions

Standardize a single data-provider naming convention across the test suite (e.g. a `*Provider` suffix) and align existing provider methods to it.

## Acceptance Criteria

- A data-provider naming convention is agreed and documented (or applied consistently).
- Existing provider methods follow the chosen convention, or the team explicitly decides to leave as-is.
