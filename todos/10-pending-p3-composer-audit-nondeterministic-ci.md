---
status: pending
priority: p3
issue_id: '10'
source: human
author: MIGUEL ANGEL GARZON MALDONADO
scope: in_scope
tags: [code-review, ci]
---

## Problem Statement

`.github/workflows/ci.yml:53` — `composer audit` on a lock-free library is non-deterministic over time. A green run today can turn red later with no code change, because the advisory database evolves. Informational; acceptable for a dev/test tool.

## Findings

- The project is a library with no committed lock file, so `composer audit` resolves against advisories that change over time.
- CI results are therefore not reproducible across time for an unchanged codebase.
- Acceptable trade-off for a dev/test tool; flagged as informational.

## Proposed Solutions

Optional: Document the expectation that `composer audit` may go red over time without code changes, so future maintainers are not surprised. Optionally add `--format=plain` to produce more readable CI logs.

## Acceptance Criteria

- The non-deterministic nature of `composer audit` is documented (in CI comments or contributor docs), and/or `--format=plain` is added for readability — or the team explicitly accepts the current behavior.
