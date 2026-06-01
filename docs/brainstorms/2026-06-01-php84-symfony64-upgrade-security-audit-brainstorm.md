# Brainstorm: PHP 8.4 / Symfony 6.4+ Upgrade & Supply-Chain Security Audit

**Date:** 2026-06-01
**Topic:** Modernize `gorghoa/scenariostate-behat-extension` to a PHP 8.4 / Symfony 6.4+ baseline as a clean **2.0** release, plus a pragmatic supply-chain security audit.

---

## What We're Building

A clean **major-version (2.0)** modernization of this Behat extension:

1. **Raise the platform floor** to PHP `>=8.4` and Symfony `^6.4 || ^7.0`, dropping all legacy PHP (5.5–8.3) and Symfony (2–5) support. Existing users on old stacks stay on the 1.x line.
2. **Upgrade `doctrine/annotations` to `^2.0`** while keeping the annotation-based API (`@ScenarioStateArgument`) — no user-facing API change. This requires removing the now-deleted `AnnotationRegistry::registerFile()` call (2.x relies on the normal Composer autoloader).
3. **Modernize the dev toolchain**: replace `phpunit ~4.5` with a current PHPUnit, drop the unused `phpspec` dependency (no `spec/` directory exists), and update `phpunit.xml.dist` to the current schema.
4. **Supply-chain / hygiene security audit**: run `composer audit` for CVEs, narrow and pin dependency constraints, replace the dead **Travis CI** with **GitHub Actions** (testing the 8.4 matrix), and verify the existing hardening (CODEOWNERS, workflow permissions) plus add **Dependabot**.

This is **not** an app — it's a library — so "pinning" means narrow Composer constraints, not a committed `composer.lock` (none is committed today, which is correct). A lock file may be committed *only* for reproducible CI.

---

## Why This Approach

- **Clean break over a widened range.** Supporting 5.5→8.4 and Symfony 2→7 in one codebase means conditional shims and a huge CI matrix. A 2.0 with a single modern baseline is far simpler to maintain and reason about. Legacy users are served by the frozen 1.x line.
- **Keep Doctrine annotations (on 2.x) rather than migrating to native PHP attributes.** Migrating `@ScenarioStateArgument` → `#[ScenarioStateArgument]` would break every consumer's step definitions. Staying on annotations preserves the public API; the only required change is internal (drop the removed `AnnotationRegistry` call). Native-attribute support can be a *future, additive* enhancement.
- **Hygiene + supply-chain depth (not full SAST).** This is a test-only dev dependency with a tiny, reflection-based surface and no network/IO/untrusted-input handling. CVE scanning, dependency pinning, and CI hardening deliver almost all the real risk reduction; a line-by-line threat model would be overkill.

---

## Key Decisions

| Decision | Choice | Rationale |
|---|---|---|
| Version strategy | **Clean break → 2.0** | Single modern baseline; 1.x serves legacy users |
| PHP floor | **`>=8.4` (literal)** | User intent: "PHP 8.4 at least." Most modern; smallest legacy-code burden |
| Symfony constraint | **`^6.4 \|\| ^7.0`** | Supports the 6.4 LTS line *and* current 7.x for adoption |
| Annotation engine | **Keep Doctrine, upgrade to `^2.0`** | Preserves public `@ScenarioStateArgument` API; remove deleted `AnnotationRegistry::registerFile()` |
| Dev tooling | **Modern PHPUnit; drop phpspec** | phpspec dep is unused (no `spec/`); PHPUnit 4.5 can't run on PHP 8.4 |
| CI | **Travis → GitHub Actions** | Travis is dead; GHA tests the real target matrix |
| Audit depth | **Hygiene + supply chain** | `composer audit`, pin constraints, Dependabot, verify CODEOWNERS/workflow perms |
| "Pinning" semantics | **Narrow constraints (library)** | Libraries pin via constraints, not a committed lock |

### Affected files (for the planning phase)
- `composer.json` — `require` (php, symfony/*, doctrine/annotations, behat), `require-dev` (phpunit, drop phpspec)
- `src/ServiceContainer/ScenarioStateExtension.php` — remove `AnnotationRegistry::registerFile()` in `initialize()`
- `phpunit.xml.dist` — migrate to current schema
- `.travis.yml` → delete; add `.github/workflows/ci.yml`
- `.github/dependabot.yml` — new
- `README.md` — update version/support matrix

---

## Resolved Questions

1. **PHP floor — literal 8.4 vs. lower for LTS reach?** → **`>=8.4` literal.** (Accepted that this excludes SF 6.4 LTS users still on 8.1–8.3.)
2. **Symfony constraint — `^6.4` only vs. include 7.x?** → **`^6.4 || ^7.0`.**
3. **Annotations vs. attributes?** → **Keep Doctrine annotations**, upgrade to `^2.0`.
4. **Audit depth?** → **Hygiene + supply chain**, no full SAST/threat model.

## Open Questions

_None — all resolved above. Ready for planning._

---

## Out of Scope (YAGNI)

- Migrating to native PHP 8 attributes (possible future additive feature)
- Full static analysis (PHPStan/Psalm) — deferred; not blocking the upgrade
- Line-by-line SAST / formal threat model — disproportionate for a test-only library
- Supporting Symfony 8 (not yet released / relevant)

---

**Next:** Run `/zigce:plan` to turn this into an implementation plan.
