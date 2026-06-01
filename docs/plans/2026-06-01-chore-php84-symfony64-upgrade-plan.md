---
title: "Upgrade to PHP 8.4 / Symfony 6.4+ (clean-break 2.0) + supply-chain audit"
type: chore
status: active
date: 2026-06-01
origin: docs/brainstorms/2026-06-01-php84-symfony64-upgrade-security-audit-brainstorm.md
---

# ♻️ Upgrade to PHP 8.4 / Symfony 6.4+ (clean-break 2.0) + supply-chain audit

## Enhancement Summary

**Deepened on:** 2026-06-01
**Sections enhanced:** Constraint table, all 6 phases, risks, security, acceptance criteria
**Research inputs:** 2× Explore (source reads), framework-docs-researcher (PHPUnit 13 / Prophecy / Doctrine 2.0), best-practices-researcher (GHA/Dependabot), 3× plan reviewers (simplicity, security, architecture). All findings verified against a real `behat/behat:v3.31.0` install on PHP 8.4.

### Key improvements folded in
1. **Concrete, copy-paste code** for every breaker: `getType()` rewrite (using `instanceof`, handling union/intersection/builtin), `Process::fromShellCommandline()`, full PHPUnit 13 `phpunit.xml.dist`, `ci.yml`, `dependabot.yml`.
2. **Phase reorder (architecture finding):** the original "each phase independently green-able" claim was **false** — `tests/` and `features/`/`testapp/` share an autoload classpath, so symbol migration must happen in one sweep. Phases 3–4 restructured accordingly.
3. **PHPUnit constraint simplified** `^12.5 || ^13.0` → **`^13.0`** (PHP-8.4-only lib; the dual created a schema/runner mismatch on the `lowest` leg).
4. **Security hardening punch-list** added (SHA-pinned actions, least-privilege `permissions`, branch protection, signed/protected `v2.0.0` tag, `roave/security-advisories`, `config.allow-plugins: false`).

### New considerations discovered
- **Missed migration:** `tests/Annotation/ScenarioStateArgumentTest.php:20` uses `@dataProvider` — PHPUnit 13 removed docblock metadata, so it must become `#[DataProvider]` with a **`static`** provider method.
- **Prophecy risk is Medium, not Low:** the suite prophesizes internal `\ReflectionMethod`/`\ReflectionParameter`; doubling internal reflection classes is fragile on modern PHP and is the load-bearing assumption of the "keep Prophecy" strategy. Validate it first.
- **`prophecy-phpunit ^2.5`** is the version that supports PHPUnit 13 (plan previously said `^2.3`).
- **`setup-php` has no v3** — pin `@v2` (rolling major).
- **3 "verify-only" contracts promoted to acceptance assertions:** annotation resolution under Doctrine 2.0 (silent-null failure mode), `EventSubscriber`/`ScenarioTested` store-clear, and `ArgumentOrganiser` decoration actually wrapping under SF7 DI.

---

## Overview

Modernize `gorghoa/scenariostate-behat-extension` from a PHP 5.5 / Symfony 2–6 baseline to a clean **major-version 2.0** targeting **PHP `>=8.4`** and **Symfony `^6.4 || ^7.0`**, keeping the existing Doctrine-annotation public API (`@ScenarioStateArgument`) on `doctrine/annotations ^2.0`. Replace the dead Travis CI with GitHub Actions, modernize the test toolchain (PHPUnit 4 → 13), and run a pragmatic supply-chain/hygiene security pass. Legacy users stay on the frozen 1.x line.

All strategic decisions originate from the brainstorm (see brainstorm: `docs/brainstorms/2026-06-01-php84-symfony64-upgrade-security-audit-brainstorm.md`). Research confirmed the approach is viable (Behat 3.31 supports `symfony/* ^7.0` + PHP 8.4) and surfaced **three mandatory runtime breakers** the brainstorm did not list — now first-class scope.

## Problem Statement / Motivation

- The library declares `php >=5.5` and Symfony `^2.7|...|^6.0`, but **the code physically cannot run on PHP 8.4**: `RuntimeCallHandler.php:68` uses `ReflectionParameter::getClass()` (removed in PHP 8.0) and the acceptance bootstrap uses `Process` APIs removed in Symfony 5.
- `doctrine/annotations` is pinned to `^1.2` and the extension calls `AnnotationRegistry::registerFile()` — **removed in doctrine/annotations 2.0**.
- Dev tooling is a decade stale: `phpunit ~4.5` (cannot run on PHP 8.4), an unused `phpspec ~2.0`, CI on **Travis** (defunct) testing PHP 5.3–7.1.
- Security posture: no automated CVE scanning, no Dependabot, mutable-tag actions risk. (CODEOWNERS + workflow hardening landed in PR #1.)

## Proposed Solution

A single clean-break 2.0 release delivered in **ordered phases**. ⚠️ **Correction from review:** phases are *dependency-ordered*, **not** all independently green-able — `tests/`, `features/`, and `testapp/` share one autoload classpath, so symbol migration is a single sweep (see Phase 3).

1. **Dependencies** — narrow `composer.json` constraints to the modern baseline.
2. **Source breakers** — fix the PHP 8.0 / Symfony 5 / Doctrine 2.0 hard-fatals in `src/`.
3. **Test toolchain + symbol sweep** — PHPUnit 4 → 13, Prophecy bridge, config schema, and **all** `PHPUnit_Framework_*` references across `tests/` + `features/` + `testapp/` in one pass. Gate: `phpunit` green.
4. **Acceptance behavior** — `Process` rewrite + `SnippetAcceptingContext` removal + behavioral assertions. Gate: `behat` green.
5. **CI** — Travis → GitHub Actions matrix (lowest/highest) + `composer audit`.
6. **Security & docs** — Dependabot, hardening punch-list, README/CHANGELOG.

**Out of scope (YAGNI, per brainstorm):** native PHP 8 `#[Attribute]` migration (keep Doctrine annotations — explicit decision), PHPStan/Psalm, full SAST/threat model, Symfony 8 / Behat 4, and a broad `declare(strict_types=1)` + return-type sweep (architecture review confirmed the decorated Behat interfaces are untyped → no sweep needed).

---

## Technical Approach

### Pinned constraint table (the contract for Phase 1)

| Package | New constraint | Notes |
|---|---|---|
| `php` | `>=8.4` | Forced anyway by PHPUnit 13. Behat 3.31 caps `<8.6`; composer intersects. |
| `behat/behat` | `^3.31` | First line verified for `symfony/* ^7.0` + PHP 8.4. **Not** 4.x (Symfony 8). |
| `symfony/dependency-injection` | `^6.4 \|\| ^7.0` | Mirrors Behat's own constraint pattern. |
| `symfony/process` | `^6.4 \|\| ^7.0` | Same. |
| `symfony/config` | `^6.4 \|\| ^7.0` | Add explicitly — used by `ScenarioStateExtension::configure()`, currently only transitive. |
| `doctrine/annotations` | `^2.0` | 2.0.2 latest. `@Annotation`+`array $options` ctor parses unchanged; `AnnotationReader`/`Reader` retained; `AnnotationRegistry::register*` removed. |
| `phpunit/phpunit` (dev) | **`^13.0`** | 13 = native PHP 8.4. Single major, single schema (simplicity review). Replaces `~4.5`. |
| `phpspec/prophecy-phpunit` (dev) | `^2.5` | **New** — Prophecy unbundled from PHPUnit 10; v2.5 supports PHPUnit 12/13. Pulls `phpspec/prophecy` transitively. |
| `roave/security-advisories` (dev) | `dev-latest` | **New (security)** — metapackage, no code; refuses to resolve known-vuln versions. Scope out of `--prefer-lowest` job if it fights the `^6.4` floor. |
| `phpspec/phpspec` (dev) | **remove** | Unused — no `spec/` directory. |

Also add to `composer.json`:
```json
"config": {
    "allow-plugins": false,
    "sort-packages": true
}
```
(`allow-plugins: false` blocks install-time plugin execution from a compromised transitive dep — security review.)

### Phase 1 — Dependencies (`composer.json`)

- Update `require` / `require-dev` per the table; add the `config` block above.
- **Remove the autoload hack** `"Symfony\\Component\\Process\\": "vendor/symfony/process/"` (`composer.json:18`) — it shadows the real Symfony package (also a dependency-confusion smell — security review).
- Keep **no committed `composer.lock`** (correct for a library; Dependabot reads `composer.json`).
- Pre-flight: `rm -rf vendor composer.lock` before first install (stale-vendor is the #1 false-error source — institutional learning).
- **Gate:** `composer validate --strict` clean; `composer update` resolves on PHP 8.4 at both `--prefer-lowest` and highest.

### Phase 2 — Source breakers (`src/`)

**2a. `src/ServiceContainer/ScenarioStateExtension.php`**
- Delete `use Doctrine\Common\Annotations\AnnotationRegistry;` (`:21`) and the `AnnotationRegistry::registerFile(...)` call in `initialize()` (`:61`). 2.x autoloads annotation classes via Composer; `initialize()` body becomes empty.
- Delete dead imports `Hook\Dispatcher\ScenarioStateHookDispatcher` (`:25`) and `Hook\Tester\ScenarioStateHookableScenarioTester` (`:26`) — no `src/Hook/` exists. Remove unused constants `SCENARIO_STATE_DISPATCHER_ID` (`:41`), `SCENARIO_STATE_TESTER_ID` (`:42`).
- `AnnotationReader` + `addGlobalIgnoredName()` (`:82,93-94`) retained in 2.0 — leave as-is.

**2b. `src/Call/Handler/RuntimeCallHandler.php:67-72` — the PHP 8.0 hard fatal.**
Current:
```php
foreach ($function->getParameters() as $parameter) {
    if (null !== $parameter->getClass() && get_class($scope) === $parameter->getClass()->getName()) {
        $arguments[$parameter->getName()] = $scope;
        break;
    }
}
```
Replacement (handles null/builtin/union/intersection/self; uses `instanceof`, which also fixes a latent bug where the original missed parent/interface types — architecture review):
```php
foreach ($function->getParameters() as $parameter) {
    $type = $parameter->getType();

    // Only a single, non-builtin class type can match a hook scope object.
    // Union/intersection types (ReflectionUnionType/ReflectionIntersectionType)
    // and builtins (int, string, …) are not matchable here.
    if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
        continue;
    }

    if ($scope instanceof ($type->getName())) {
        $arguments[$parameter->getName()] = $scope;
        break;
    }
}
```
> Note: `$scope instanceof ($type->getName())` uses the PHP 8 dynamic-class-name `instanceof`. If targeting clarity, resolve to a `$className = $type->getName();` local first. Do **not** instantiate `new \ReflectionClass(...)` to "verify" — unnecessary and can throw (architecture review).

**2c.** Confirm `Reader` interface usage in `ArgumentsResolver.php` / `ScenarioStateArgumentOrganiser.php` is unchanged (it is — 2.0 keeps `Reader` + `getMethodAnnotation(s)`).

**2d.** `src/Annotation/ScenarioStateArgument.php` — `@Annotation`/`@Target("METHOD")` + `array $options` ctor still supported by 2.0 (verified). No change.

### Phase 3 — Test toolchain + symbol sweep (`tests/`, `features/`, `testapp/`, `phpunit.xml.dist`)

⚠️ **Do all `PHPUnit_Framework_*` symbol fixes across `tests/` + `features/bootstrap/` + `testapp/features/bootstrap/` in this one phase** — they share the `autoload`/`autoload-dev` classpath; you cannot get a clean runner with broken symbols still resolvable (architecture review).

**3a. Replace `phpunit.xml.dist` wholesale** with the PHPUnit 13 schema:
```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/13.x/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         cacheDirectory=".phpunit.cache"
         colors="true"
         beStrictAboutOutputDuringTests="true"
         beStrictAboutTestsThatDoNotTestAnything="true"
         displayDetailsOnTestsThatTriggerDeprecations="true"
         displayDetailsOnTestsThatTriggerWarnings="true">
    <testsuites>
        <testsuite name="ScenarioStateBehatExtension Test Suite">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory suffix=".php">src</directory>
        </include>
    </source>
</phpunit>
```
Add `.phpunit.cache` to `.gitignore`. Hold `failOnDeprecation`/`failOnWarning` off until green, then turn on. (`bootstrap` was absent before — required so annotation classes autoload.)

**3b. Per-file migration map** (4 unit files + 2 fixture files):

| File | Changes |
|---|---|
| `tests/ScenarioStateTest.php` | `\PHPUnit_Framework_TestCase` → `use PHPUnit\Framework\TestCase`; `setExpectedException()` (`:23`) → `$this->expectException()`; add `: void` to tests. |
| `tests/Annotation/ScenarioStateArgumentTest.php` | TestCase namespace; **`@dataProvider getArguments` (`:20`) → `#[DataProvider('getArguments')]`** + make `getArguments()` **`static`**; `: void`. |
| `tests/Resolver/ArgumentsResolverTest.php` | TestCase namespace; add `use Prophecy\PhpUnit\ProphecyTrait;` + `use ProphecyTrait;`; `: void`. |
| `tests/Argument/ScenarioStateArgumentOrganiserTest.php` | TestCase namespace; `ProphecyTrait`; `setUp(): void` (`:61`). |
| `tests/Context/Initializer/ScenarioStateInitializerTest.php` | TestCase namespace; `ProphecyTrait`; `setUp(): void` (`:29`). |
| `features/bootstrap/FeatureContext.php` | `\PHPUnit_Framework_Assert` (`:113,167,173`) → `\PHPUnit\Framework\Assert`; **string `assertContains` (`:113`) → `assertStringContainsString`**. |
| `testapp/features/bootstrap/FeatureContext.php` | All `\PHPUnit_Framework_Assert` (`:63,76,77,87,99,112,113,123,163,179,180,193`) → `\PHPUnit\Framework\Assert`. |

`ProphecyTrait` usage example:
```php
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class ArgumentsResolverTest extends TestCase
{
    use ProphecyTrait;
    // $this->prophesize(...) now works exactly as before
}
```

**3c. ⚠️ Validate the Prophecy-on-reflection assumption FIRST (Medium risk).** `ArgumentsResolverTest` and `ScenarioStateArgumentOrganiserTest` prophesize internal `\ReflectionMethod`/`\ReflectionParameter`. Doubling internal reflection classes is fragile on modern PHP. Run these two files first; **if doubling fails, fall back to real fixture methods + a real `ReflectionMethod`** rather than mocks. The new `RuntimeCallHandler` `getType()` test (below) should use a **real** fixture method, not a prophesized `ReflectionParameter`.

**3d. Add `tests/Call/Handler/RuntimeCallHandlerTest.php`** covering typed-parameter scope injection: (1) a class-typed param matching the scope → injected; (2) a builtin-typed param → skipped; (3) a parent/interface-typed param → matched via `instanceof` (new behavior). Use real fixture methods.

**Gate:** `vendor/bin/phpunit` green on PHP 8.4 (PHPUnit 13).

### Phase 4 — Acceptance behavior (`features/bootstrap/FeatureContext.php`)

**4a. Symfony 5+ `Process` rewrite.** Current `new Process(null)` (`:63`) + `setCommandLine(...)` (`:77-87`) are removed. Replace with `fromShellCommandline` (keeps the existing `escapeshellarg`/shell-string build intact):
```php
$commandLine = sprintf(
    '%s %s %s %s',
    $this->phpBin,
    escapeshellarg(BEHAT_BIN_PATH),
    $argumentsString,
    strtr('--lang=en --format-settings=\'{"timer": false}\'', ['\'' => '"', '"' => '\"'])
);
$this->process = Process::fromShellCommandline($commandLine);
$this->process->setWorkingDirectory(__DIR__.'/../../testapp');
$this->process->run();
```
Output retrieval at `:184` (`getErrorOutput().getOutput()`) is unchanged.

**4b.** Remove `use Behat\Behat\Context\SnippetAcceptingContext;` (`:31`) and drop it from the `implements` clause — removed in modern Behat; snippet generation no longer needs the marker.

**4c. Promote these to explicit acceptance assertions** (architecture review — silent-failure modes):
- **Annotation resolution under Doctrine 2.0:** a stored fragment is injected into a `@ScenarioStateArgument` step (failure mode: reader returns null → arg silently not injected).
- **`EventSubscriber`/`ScenarioTested`:** the store is cleared between scenarios.
- **`ArgumentOrganiser` decoration actually wraps** under SF7 DI (`setDecoratedService(...)->setPublic(false)` + `.inner`) — confirm it didn't silently no-op.
- **`@Transform` interplay** (regression from issue #27 / commit `5970a00`) — exercises `TransformationCall`/`EnvironmentCall` re-instantiation (their constructor signatures are the real cross-version drift surface — verify against Behat 3.31).
- Lowercase annotation aliases still ignored by the reader.

**Gate:** `vendor/bin/behat --strict` green (run from repo root — see Phase 5 gotcha).

### Phase 5 — CI (`.github/workflows/ci.yml`, delete `.travis.yml`)

Delete `.travis.yml`. Add `ci.yml` (actions **SHA-pinned** with version comments so Dependabot can still bump — security review):
```yaml
name: CI
on:
  push: { branches: [master] }
  pull_request:
  workflow_dispatch:
permissions:
  contents: read
concurrency:
  group: ci-${{ github.workflow }}-${{ github.ref }}
  cancel-in-progress: true
jobs:
  tests:
    name: "PHP ${{ matrix.php }} / ${{ matrix.dependencies }}"
    runs-on: ubuntu-latest
    strategy:
      fail-fast: false
      matrix:
        php: ["8.4"]
        dependencies: ["lowest", "highest"]
    steps:
      - uses: actions/checkout@<sha>            # v5.x
        with: { persist-credentials: false }
      - uses: shivammathur/setup-php@<sha>      # v2.x  (no v3 exists)
        with:
          php-version: "${{ matrix.php }}"
          extensions: mbstring, intl, dom, json
          coverage: none
          tools: composer:v2
      - run: composer validate --strict
      - uses: ramsey/composer-install@<sha>     # v3.x
        with: { dependency-versions: "${{ matrix.dependencies }}" }
      - run: composer audit --abandoned=report  # failing gate; do NOT `|| true`
      - run: vendor/bin/phpunit
      - run: vendor/bin/behat --strict --no-interaction
```
- **Why `lowest` matters:** it resolves `behat ^3.31` and `symfony ^6.4` at their floors — the only thing that proves the new constraints aren't lying to consumers. (Its sole purpose; drop it only if floors are otherwise proven.)
- **Behat-in-Actions gotcha:** the suite shells out via `Process` to run behat against `testapp/`. **Run from repo root — do not add `working-directory: testapp`** (the fixture builds its own cwd via `__DIR__`). `vendor/bin` is not on `PATH` by default; the fixture sidesteps this via `PhpExecutableFinder`, so keep that pattern and ensure `setup-php` runs before the behat step.
- **CODEOWNERS gate:** `.github/**` requires `@zignaly-open/admins` review — this PR (and weekly Dependabot action-bump PRs) will trigger it. Sequence for review lead time.

### Phase 6 — Security & docs

**6a. `.github/dependabot.yml`:**
```yaml
version: 2
updates:
  - package-ecosystem: composer
    directory: "/"
    schedule: { interval: weekly }
    open-pull-requests-limit: 5
    cooldown: { default-days: 7 }   # don't auto-PR releases < 7 days old (compromised-release mitigation)
    commit-message: { prefix: "build", prefix-development: "build", include: "scope" }
    groups:
      symfony: { patterns: ["symfony/*"] }
      dev-dependencies: { dependency-type: "development", patterns: ["*"] }
  - package-ecosystem: github-actions
    directory: "/"
    schedule: { interval: weekly }
    commit-message: { prefix: "ci" }
    groups:
      github-actions: { patterns: ["*"] }
```

**6b. Hardening punch-list** (security review — prioritized):
- [ ] **(HIGH)** All actions SHA-pinned (acceptance criterion).
- [ ] **(HIGH)** `permissions: contents: read` + `persist-credentials: false` in `ci.yml`.
- [ ] **(HIGH, repo setting — runbook)** Branch protection on `master`: require PR + ≥1 approval, **require Code Owner review** (this activates the existing CODEOWNERS), required status checks = CI matrix, no force-push. `gh api` command in the runbook.
- [ ] **(HIGH, release — runbook)** Signed annotated `v2.0.0` tag (`git tag -s`) + GitHub tag-protection rule for `v*`; verify Packagist maintainers limited to `@zignaly-open/admins` and the GitHub↔Packagist webhook uses the App integration. (SLSA attestation is **N/A** — source-only Composer package, no build artifact; the signed protected tag is the integrity anchor.)
- [ ] **(MED)** `roave/security-advisories: dev-latest` in `require-dev` (Phase 1).
- [ ] **(MED)** `config.allow-plugins: false` (Phase 1).
- [ ] **(MED)** `composer audit` is a failing CI gate and surfaces abandoned packages.

**6c. Docs:**
- `README.md` — support matrix (PHP `>=8.4`, Symfony `^6.4||^7.0`, **Behat `^3.31` floor**); note 1.x frozen for legacy stacks; fix `PHPUnit_Framework_Assert` examples (`:122,149,164`).
- `CHANGELOG.md` — single 2.0 entry documenting BC breaks: PHP/Symfony floor, **Behat 3.31 floor**, Doctrine 2.x, dropped phpspec. (One section — not a backfilled history.)

---

## System-Wide Impact

- **Interaction graph:** `ScenarioStateExtension::load()` decorates Behat's `argument.preg_match_organiser` and `call.call_handler.runtime` (both verified present + interface-compatible in 3.31) and registers the `ScenarioStateInitializer` (ContextInitializer + EventSubscriber) and Doctrine reader. The `getType()` fix sits on the per-step argument-resolution path → exercised by every `@ScenarioStateArgument` step.
- **Error propagation:** `MissingStateException` flow unchanged. New failure surface is install/boot-time (composer resolution, annotation autoloading) — caught by the lowest+highest matrix — plus the **silent-null annotation-resolution** mode now covered by an explicit acceptance assertion.
- **State lifecycle:** in-memory store only; no persistence, no migration risk. Store-clear-between-scenarios now asserted.
- **API surface parity:** public contract = `@ScenarioStateArgument` + `ScenarioStateAwareContext`/`Trait`. **Preserved.** Only consumer-visible change is the raised PHP/Symfony/Behat floor (→ 2.0).
- **Cross-version drift to verify (architecture review):** `TransformationCall`/`EnvironmentCall` positional constructors in 3.31; `getCallee()->getReflection()` returning `\ReflectionMethod` (resolver assumes method, not closure — pre-existing).

---

## Acceptance Criteria

### Functional
- [ ] `composer update` resolves on PHP 8.4 at `--prefer-lowest` (behat 3.31.x, symfony 6.4.0) **and** highest (symfony 7.x).
- [ ] `vendor/bin/phpunit` passes on PHP 8.4 (PHPUnit 13), including the new `RuntimeCallHandlerTest`.
- [ ] `vendor/bin/behat --strict` passes, with assertions for: annotation injection, store-clear between scenarios, organiser decoration wrapping, `@Transform` interplay, lowercase-alias ignore.
- [ ] No reference to `AnnotationRegistry`, `ReflectionParameter::getClass()`, `Process::setCommandLine()`, `new Process(null)`, `SnippetAcceptingContext`, `PHPUnit_Framework_*`, `setExpectedException`, or docblock `@dataProvider` remains.

### Non-functional / quality gates
- [ ] `composer validate --strict` clean; `composer audit` reports no actionable advisories; abandoned packages surfaced.
- [ ] GitHub Actions green across the full matrix; Travis removed; **all actions SHA-pinned**; `permissions: contents: read`.
- [ ] Dependabot configured (composer + github-actions, cooldown, groups); branch protection + code-owner review active on `master`; `v2.0.0` signed + tag-protected.
- [ ] README support matrix + CHANGELOG 2.0 entry updated; `phpspec` removed; `Symfony\Process` autoload hack removed; `config.allow-plugins: false` set.

## Dependencies & Risks

| Risk | Likelihood | Mitigation |
|---|---|---|
| **Prophecy can't double internal `\ReflectionMethod`/`\ReflectionParameter`** on modern PHP | **Medium** | Validate the two reflection-mocking test files FIRST (Phase 3c). Fallback: real fixture methods + real `ReflectionMethod`. Don't mock reflection in the new `getType()` test. |
| `behat 3.31` Testwork value-object constructors (`TransformationCall`/`EnvironmentCall`) drifted vs. our positional calls | Medium | Verified interfaces are compatible; verify the *constructors* via the `@Transform` acceptance scenario early in Phase 4. |
| `getType()` rewrite mishandles union/intersection/self or changes match semantics | Medium | `instanceof`-based rewrite (above) + dedicated unit tests (3d). `instanceof` intentionally widens to parent/interface matches (fixes latent bug). |
| Symfony 5+ `Process` rewrite alters how the acceptance suite spawns behat | Medium | `fromShellCommandline` preserves the existing shell-string build; keep cwd/env identical; assert on captured output. |
| SF7 service decoration silently no-ops | Low | Explicit acceptance assertion that the organiser wraps (4c). |
| `roave/security-advisories` blocks `--prefer-lowest` resolution | Low | Scope it out of the lowest-deps job if it fights the `^6.4` floor. |
| Stale `vendor/` masking real errors | Low | `rm -rf vendor composer.lock` before diagnosing (institutional learning). |
| `.github/**` changes blocked pending admin review (CODEOWNERS) | Low | Sequence CI/Dependabot PRs for review lead time. |

## Success Metrics

- CI green on PHP 8.4 × {lowest, highest}, both `phpunit` and `behat`.
- Zero deprecation/fatal output during the acceptance run.
- `composer audit` clean; no abandoned-package surprises beyond the known doctrine/annotations maintenance-mode note.

## Sources & References

### Origin
- **Brainstorm:** `docs/brainstorms/2026-06-01-php84-symfony64-upgrade-security-audit-brainstorm.md`. Carried-forward decisions: clean-break 2.0; PHP `>=8.4`; Symfony `^6.4||^7.0`; **keep Doctrine annotations on `^2.0`**; hygiene+supply-chain depth; library stays lock-free.

### Internal references (files to change)
- `composer.json:7-9,15,18,23-24` — constraints, autoload hack, `config` block
- `src/ServiceContainer/ScenarioStateExtension.php:21,25-26,41-42,61` — Doctrine registry + dead Hook code
- `src/Call/Handler/RuntimeCallHandler.php:67-72` — `getClass()` → `getType()`; also verify `:78-80` value-object constructors
- `src/Resolver/ArgumentsResolver.php`, `src/Argument/ScenarioStateArgumentOrganiser.php` — `Reader` usage (verify)
- `phpunit.xml.dist` — full replace (v13 schema)
- `tests/**` (4 files + new `RuntimeCallHandlerTest`) — see Phase 3 map
- `features/bootstrap/FeatureContext.php:31,63,77-87,113,167,173,184` — Behat/Process/Assert
- `testapp/features/bootstrap/FeatureContext.php` — `PHPUnit_Framework_Assert` refs
- `.travis.yml` (delete), `.github/workflows/ci.yml` (new), `.github/dependabot.yml` (new), `.github/CODEOWNERS` (verify), `README.md`, `CHANGELOG.md`

### External references
- behat/behat 3.31 constraints — https://packagist.org/packages/behat/behat ; https://docs.behat.org/en/latest/releases.html
- doctrine/annotations 2.0 — https://github.com/doctrine/annotations/blob/2.0.x/UPGRADE.md ; https://www.doctrine-project.org/projects/doctrine-annotations/en/2.0/custom.html
- PHPUnit 13 — https://phpunit.de/announcements/phpunit-13.html ; config: https://docs.phpunit.de/en/13.0/configuration.html ; attributes: https://docs.phpunit.de/en/12.5/attributes.html
- prophecy-phpunit — https://packagist.org/packages/phpspec/prophecy-phpunit
- Symfony EOL (6.4 LTS, 7.x) — https://endoflife.date/symfony
- CI actions — https://github.com/shivammathur/setup-php (v2 rolling) ; https://github.com/ramsey/composer-install (v3)
- GitHub Actions permissions / SHA-pinning — https://docs.github.com/en/actions/reference/workflow-syntax-for-github-actions#permissions
- Dependabot options — https://docs.github.com/en/code-security/dependabot/working-with-dependabot/dependabot-options-reference
- Composer CLI (validate/audit) — https://getcomposer.org/doc/03-cli.md

### Related work
- Commits: `135d4b8` Support symfony 6, `f1b4a68` SF5 compat, `b5515f4` CODEOWNERS hardening (PR #1)
- Institutional learnings: `zignaly-internal-transfers-api/.../php-version-upgrade-symfony-playbook.md`, `zignaly-trading-api-2/.../symfony-6.3-to-6.4-upgrade-gotchas.md`, `behatch-contexts/.../symfony-http-foundation-cve-closure-in-php-library.md`, `zignaly-trading-api/.../phpunit-9.6-attribute-dataprovider-silently-breaks-tests.md`
