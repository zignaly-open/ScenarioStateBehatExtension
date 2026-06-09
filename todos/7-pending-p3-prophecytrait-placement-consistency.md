---
status: pending
priority: p3
issue_id: '7'
source: human
author: MIGUEL ANGEL GARZON MALDONADO
scope: in_scope
tags: [code-review, pattern]
---

## Problem Statement

`tests/Resolver/ArgumentsResolverTest.php:27` — The `use ProphecyTrait;` placement differs from the other two prophecy-based tests. In `ScenarioStateArgumentOrganiserTest` and `ScenarioStateInitializerTest`, the trait is the first class member followed by a blank line; here it appears in a different position.

## Findings

- Three tests use `ProphecyTrait`; two place `use ProphecyTrait;` as the first class member followed by a blank line.
- `ArgumentsResolverTest` deviates from that pattern.
- Purely a stylistic consistency issue, no functional impact.

## Proposed Solutions

Move `use ProphecyTrait;` in `ArgumentsResolverTest.php` to be the first class member followed by a blank line, matching `ScenarioStateArgumentOrganiserTest` and `ScenarioStateInitializerTest`.

## Acceptance Criteria

- `use ProphecyTrait;` placement in `ArgumentsResolverTest.php` matches the convention used by the other two prophecy tests.
- Tests still pass.
