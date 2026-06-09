---
status: pending
priority: p3
issue_id: '11'
source: human
author: MIGUEL ANGEL GARZON MALDONADO
scope: in_scope
tags: [code-review, ci]
---

## Problem Statement

`composer.json:26` — `roave/security-advisories: dev-latest` is a moving dev constraint with no `minimum-stability` / `prefer-stable` configured. The CI conflict set it enforces is therefore non-reproducible across time. Informational; this is the intended behavior for the metapackage.

## Findings

- `roave/security-advisories` is a metapackage whose `dev-latest` constraint intentionally tracks the latest advisory conflict set.
- Without `minimum-stability` / `prefer-stable`, the resolved conflict set changes over time, so installs/CI are not reproducible across time.
- This is the intended design of the metapackage, not a misconfiguration.

## Proposed Solutions

Optional: Document the expectation that the security-advisories conflict set is intentionally a moving target (CI may flag newly disclosed advisories without code changes), or otherwise record the team's pinning/behavior expectations.

## Acceptance Criteria

- The intended moving-target behavior of `roave/security-advisories: dev-latest` is documented (contributor docs or composer.json comment), or the team explicitly accepts it as-is.
