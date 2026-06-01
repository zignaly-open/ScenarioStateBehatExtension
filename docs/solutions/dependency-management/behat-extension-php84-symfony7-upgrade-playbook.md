---
title: "Upgrading a Behat extension library to PHP 8.4 / Symfony 6.4+ (clean-break 2.0)"
category: dependency-management
tags: [php-8.4, symfony-6.4, symfony-7, behat, doctrine-annotations, phpunit-13, prophecy, reflection, github-actions, supply-chain]
module: ScenarioStateBehatExtension
symptom: "Library declares php >=5.5 / Symfony 2–6 but cannot run on PHP 8.4; composer install resolves stale, tests use PHPUnit 4 APIs, CI on dead Travis"
root_cause: "Decade-old runtime/API assumptions: ReflectionParameter::getClass() (removed PHP 8.0), Symfony Process string API (removed SF5), doctrine/annotations AnnotationRegistry (removed 2.0), PHPUnit 4 test scaffolding"
status: planning-checkpoint
date: 2026-06-01
origin_plan: docs/plans/2026-06-01-chore-php84-symfony64-upgrade-plan.md
origin_brainstorm: docs/brainstorms/2026-06-01-php84-symfony64-upgrade-security-audit-brainstorm.md
verified: false
---

# Upgrading a Behat extension library to PHP 8.4 / Symfony 6.4+ (clean-break 2.0)

> **Checkpoint note:** This is a **planning-phase** learning captured before implementation. The decisions and breaker inventory below are research-verified (against a real `behat/behat:v3.31.0` install on PHP 8.4 and official changelogs), but the end-to-end fix is **not yet runtime-verified**. Update `verified: true` and add the green-CI evidence once the 2.0 PR lands.

## Problem

`gorghoa/scenariostate-behat-extension` (a small Behat extension that shares state between scenario steps via a `@ScenarioStateArgument` Doctrine annotation) declared `php >=5.5` and `symfony/* ^2.7|...|^6.0`. On a modern PHP 8.4 toolchain it is unusable: the code contains hard fatals, the test suite can't run, and CI points at a dead service. The task was a clean-break **2.0** raising the floor to **PHP `>=8.4`** and **Symfony `^6.4 || ^7.0`** while preserving the public annotation API.

## The gating question (answer first, plan second)

**Does Behat 3.x support Symfony 7 + PHP 8.4?** This decides whether `^6.4 || ^7.0` is even possible. **Answer: YES.** `behat/behat` **3.31.0** (2026-04) requires `php >=8.2 <8.6` and constrains its Symfony components to `^5.4 || ^6.4 || ^7.0` — it deliberately skips the non-LTS Symfony minors (6.0–6.3), which aligns exactly with a `^6.4 || ^7.0` target. **Do not chase Behat 4.0** — that branch exists only to support Symfony 8 and introduces its own BC breaks. Pin `behat/behat ^3.31`.

> **Reusable rule:** before planning any Symfony major bump in a project that depends on Behat (or any framework with pinned Symfony components), resolve the *transitive framework's* Symfony constraint first. The app's `extra.symfony.require` is irrelevant if Behat itself caps the components.

## Root cause — the four hard breakers (none are optional)

These are runtime fatals, not deprecations. The upgrade does not run without all four:

1. **`ReflectionParameter::getClass()` — removed in PHP 8.0.** Found at `src/Call/Handler/RuntimeCallHandler.php:68`. Replace with `getType()` + `ReflectionNamedType`:
   ```php
   foreach ($function->getParameters() as $parameter) {
       $type = $parameter->getType();
       if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
           continue; // skip null/builtin/union/intersection — none can match a hook scope
       }
       $className = $type->getName();
       if ($scope instanceof $className) {   // instanceof, not string-equality
           $arguments[$parameter->getName()] = $scope;
           break;
       }
   }
   ```
   Use `instanceof` rather than `get_class($scope) === $type->getName()` — it also fixes a latent bug where the original missed parent/interface-typed parameters. Do **not** `new \ReflectionClass($name)` to "verify"; it's unnecessary and can throw.

2. **Symfony `Process` string API — removed in Symfony 5.** `features/bootstrap/FeatureContext.php` used `new Process(null)` + `$process->setCommandLine($string)`. Replace with `Process::fromShellCommandline($commandLine)` (preserves the existing `escapeshellarg`/shell-string build) followed by `setWorkingDirectory(...)`. The array-command form (`new Process([...])`) is the alternative but forces parsing the args string.

3. **`AnnotationRegistry::registerFile()` — removed in doctrine/annotations 2.0.** Called in `ScenarioStateExtension::initialize()`. **Just delete it** — 2.0 resolves annotation classes via the normal Composer autoloader. `AnnotationReader`, the `Reader` interface, `getMethodAnnotation(s)`, and `addGlobalIgnoredName()` all survive. A custom annotation declared with `@Annotation` + `@Target("METHOD")` and an `array $options` constructor **parses unchanged** in 2.0 (the default non-named-constructor path; `@NamedArgumentConstructor` is opt-in).

4. **`Behat\Behat\Context\SnippetAcceptingContext` — removed in modern Behat.** Drop the import and the `implements` entry; snippet generation no longer needs the marker.

## PHPUnit 4 → 13 is effectively a test-scaffolding rewrite

PHP 8.4 forces **PHPUnit 13** (`^13.0` requires PHP 8.4). For a PHP-8.4-only library, pin **`^13.0`** — *not* `^12.5 || ^13.0`: the dual constraint creates a schema/runner mismatch on the `--prefer-lowest` CI leg (the 13.x `phpunit.xml` schema vs. a resolved 12.5 runner) for zero compatibility gain.

Migration checklist (each item is a hard break across the 4→13 jump):

| Legacy API | Replacement |
|---|---|
| `\PHPUnit_Framework_TestCase` | `use PHPUnit\Framework\TestCase` (namespaced since PHPUnit 6) |
| `\PHPUnit_Framework_Assert` | `\PHPUnit\Framework\Assert` |
| `setExpectedException($cls)` | `$this->expectException($cls)` (+ `expectExceptionMessage`) |
| `setUp()` | `protected function setUp(): void` (mandatory return type) |
| string-haystack `assertContains($needle, $str)` | `assertStringContainsString(...)` |
| docblock `@dataProvider foo` | `#[DataProvider('foo')]` **and** the provider method must be `static` (docblock metadata removed in PHPUnit 12) |
| `phpunit.xml` 4.x schema (`<filter>`/`<whitelist>`) | 13.x schema: `<source><include>`, `cacheDirectory`, `bootstrap="vendor/autoload.php"` |

**Prophecy gotcha (the load-bearing risk):** PHPUnit unbundled Prophecy in 10. Add `phpspec/prophecy-phpunit ^2.5` (the version supporting PHPUnit 12/13) and `use Prophecy\PhpUnit\ProphecyTrait;` in each test that calls `$this->prophesize()`. **But** if the suite prophesizes *internal reflection classes* (`\ReflectionMethod`, `\ReflectionParameter`) — as this one does — doubling them is fragile on modern PHP. **Validate that first**; if it fails, the entire "keep Prophecy" strategy collapses and you fall back to real fixture methods + a real `ReflectionMethod`. Never mock reflection in the new `getType()` test — use a real fixture.

## Solution shape (phase ordering matters)

A clean-break 2.0 in dependency order. **Phases are not all "independently green-able"** — `tests/`, `features/`, and `testapp/` share one autoload classpath (via `autoload-dev`), so all `PHPUnit_Framework_*` symbol fixes must happen in **one sweep**, not split across a "unit tests" and an "acceptance" phase:

1. **composer.json** — narrow constraints (table below); remove the `"Symfony\\Component\\Process\\": "vendor/symfony/process/"` autoload hack (it shadows the real package — a dependency-confusion smell); add `config.allow-plugins: false`. Pre-flight `rm -rf vendor composer.lock`.
2. **src/ breakers** — fixes 1, 3 above + delete dead `Hook\*` imports/constants.
3. **Test toolchain + symbol sweep** — PHPUnit 13 config, Prophecy trait, `#[DataProvider]`, and *all* `PHPUnit_Framework_*` across tests + both FeatureContexts. Gate: `phpunit` green.
4. **Acceptance behavior** — fixes 2, 4 above + behavioral assertions. Gate: `behat --strict` green.
5. **CI** — Travis → GitHub Actions, matrix `php:[8.4] × dependencies:[lowest,highest]`.
6. **Security & docs** — Dependabot, hardening, README/CHANGELOG.

### Final pinned constraints
```json
"require": {
    "php": ">=8.4",
    "behat/behat": "^3.31",
    "symfony/dependency-injection": "^6.4 || ^7.0",
    "symfony/process": "^6.4 || ^7.0",
    "symfony/config": "^6.4 || ^7.0",
    "doctrine/annotations": "^2.0"
},
"require-dev": {
    "phpunit/phpunit": "^13.0",
    "phpspec/prophecy-phpunit": "^2.5",
    "roave/security-advisories": "dev-latest"
}
```
(Remove `phpspec/phpspec` — unused, no `spec/` dir.)

## Prevention / best practices

- **Test `--prefer-lowest` in CI**, not just highest. It's the *only* thing that proves your declared floors (`behat ^3.31`, `symfony ^6.4`) are real and not lying to consumers. For a library this is the single highest-value CI cell.
- **Run the upgrade tooling inside the target PHP.** Rector's `SetList::PHP_84` and any analysis silently no-op on a < 8.4 runtime. Print `php -v` before running.
- **Stale vendor is the #1 false error.** `rm -rf vendor composer.lock` before diagnosing any "vendor code is broken on 8.4" symptom.
- **Libraries stay lock-free.** Don't commit `composer.lock`; Dependabot reads `composer.json`. Corollary: `composer audit --locked` is meaningless here — use plain `composer audit` (audits the resolved tree per matrix cell).
- **Supply-chain hardening for a public OSS lib** (cheap, high-value): SHA-pin all GitHub Actions (with version comments so Dependabot still bumps them); `permissions: contents: read` + `persist-credentials: false`; branch protection with **required Code Owner review** (CODEOWNERS is inert without it); signed + tag-protected `v2.0.0` (the integrity anchor for a source-only Composer package — SLSA attestation is N/A, there's no build artifact); Dependabot `cooldown: 7d` to avoid auto-PRing freshly-compromised releases.
- **GitHub Actions + Behat-that-shells-out:** run `vendor/bin/behat` from repo root (never `cd testapp`); `vendor/bin` is not on `PATH` by default — rely on the fixture's `PhpExecutableFinder`, and ensure `setup-php` runs first. Use `--strict --no-interaction`.
- **doctrine/annotations is in maintenance mode.** Keeping it (vs. native `#[Attribute]`) is the lower-risk call for a single PR because the attribute migration breaks the consumer-facing docblock API. Log it as deliberate tech-debt for a future 3.0.

## Cross-references

- Plan: `docs/plans/2026-06-01-chore-php84-symfony64-upgrade-plan.md`
- Brainstorm: `docs/brainstorms/2026-06-01-php84-symfony64-upgrade-security-audit-brainstorm.md`
- Sibling org learnings (same patterns, app context): `zignaly-internal-transfers-api/docs/solutions/upgrades/php-version-upgrade-symfony-playbook.md`; `zignaly-trading-api-2/docs/solutions/dependency-management/symfony-6.3-to-6.4-upgrade-gotchas.md`; `zignaly-trading-api/docs/solutions/test-failures/phpunit-9.6-attribute-dataprovider-silently-breaks-tests.md`; `behatch-contexts/docs/solutions/security-issues/symfony-http-foundation-cve-closure-in-php-library.md`
- External: [behat 3.31](https://packagist.org/packages/behat/behat) · [doctrine/annotations 2.0 UPGRADE](https://github.com/doctrine/annotations/blob/2.0.x/UPGRADE.md) · [PHPUnit 13](https://phpunit.de/announcements/phpunit-13.html) · [Symfony EOL](https://endoflife.date/symfony)
