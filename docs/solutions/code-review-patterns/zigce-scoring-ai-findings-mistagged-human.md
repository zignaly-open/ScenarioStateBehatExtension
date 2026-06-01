---
title: "ZIGCE review scores AI findings as human when committer == current user"
category: code-review-patterns
tags: [zigce, scoring, code-review, attribution, ai_snapshot, review-context, ci]
module: zigce-plugin (scoring / review-context)
symptom: "PR score file shows ai_snapshot grade A / 0 findings even though the AI review surfaced many; total uses the 1.2x human multiplier"
root_cause: "review-context.sh tags attribution source:human (with author_override = current git user) whenever the work was committed under the human's git identity and work_authors is empty; /zigce:review then stamps source:human on ALL findings, so none feed the source:ai-only ai_snapshot"
status: implemented
date: 2026-06-01
verified: true
---

# ZIGCE review scores AI-generated findings as `human`

## Problem / symptom

After `/zigce:review` on PR #3 (an AI-driven `/zigce:work` session), `docs/scores/PR-3.json` came out looking contradictory:

- `ai_snapshot`: **grade A, raw 0, 0 findings** — as if the AI review found nothing.
- `total`: **grade D, weighted 19.2, 11 findings** — all 11 findings tagged `source: human`, multiplied by 1.2.

In reality the six AI review agents produced all 11 findings. The snapshot meant for "AI review quality, frozen for cross-PR trend comparison" was empty and misleading.

## Root cause

The attribution is decided up front by `review-context.sh`, which writes `docs/scores/.review-context-PR-{N}.json`. On this PR it produced:

```json
"attribution": { "source": "human", "author_override": "MIGUEL ANGEL GARZON MALDONADO",
                 "current_user": "MIGUEL ANGEL GARZON MALDONADO", "work_authors": [] }
```

It classified the work as **human** because the commits were authored under the human's git identity and `work_authors` was empty — there was no signal that Claude did the work (commits aren't co-authored on a feature branch by default, and the "work authors" heuristic found nobody else).

`/zigce:review`'s synthesis step then applies the documented rule literally: *if `attribution.source == human` and `author_override` is set → stamp `source: human` + `author: {override}` on **ALL** findings.* The `scoring` skill builds `ai_snapshot` from `source: ai` findings only → zero of them → empty A snapshot, while `total` accrues every finding at the human 1.2× multiplier.

So: **AI work committed under a human git identity is scored as human review.** Not a bug in any one step — an emergent consequence of the committer-identity heuristic feeding a strict attribution rule.

## Why it matters

- `ai_snapshot` is designed to be the **immutable, normalized AI-quality signal** for `--trend` across PRs. An empty/A snapshot silently pollutes that trend — it reads as "AI shipped a flawless diff" when the AI review actually found 11 issues.
- `total` gets the human multiplier (1.2× vs 1.0×), inflating the weighted score and the grade (here D instead of what would be C under the AI multiplier).
- Anyone reading the score later cannot tell the findings were machine-generated.

## How to detect

- `ai_snapshot.findings_count == 0` **but** `total.findings_count > 0` on a PR you know was AI-reviewed.
- `jq '.attribution' docs/scores/.review-context-PR-{N}.json` shows `source: human` with `work_authors: []` on a branch you built with `/zigce:work`.

## Working resolution / options

1. **Accept and annotate (what we did):** leave the deterministic pipeline output as-is, but note in the review summary that findings are AI-generated despite the `human` tag. Don't hand-edit the score (single-writer rule: only the `scoring` skill writes it).
2. **Fix attribution at the source** for AI sessions: make `/zigce:work` commits carry the `Co-Authored-By: Claude ...` trailer (or otherwise populate `work_authors`) so `review-context.sh` resolves `source: ai`. This is the durable fix — attribution should reflect who *wrote the code*, not whose git identity committed it.
3. **Don't override in-flight:** resist the urge to retag findings `source: ai` by hand during synthesis — the rule is deliberately deterministic so attribution isn't a per-run judgment call. Fix the upstream signal instead.

## Prevention

- If you want AI work scored as AI, ensure the work commits include a Claude co-author trailer (or that `work_authors` is populated) **before** running `/zigce:review` — attribution is frozen from the commit metadata at review time.
- Treat `ai_snapshot` trend data with suspicion on any PR where `review-context` resolved `source: human` with empty `work_authors`.

## Cross-references

- Score file: `docs/scores/PR-3.json`; context: `docs/scores/.review-context-PR-3.json`
- Scoring skill (single-writer; ai_snapshot from `source: ai` only): `zigce-plugin/plugins/zigce/skills/scoring/`
- Related session learning: [[behat-extension-php84-symfony7-upgrade-playbook]] (the PR this surfaced on)
