# Changelog

## 2.0.0

Clean-break modernization to a current PHP/Symfony baseline. The public API
(the `@ScenarioStateArgument` annotation and the
`ScenarioStateAwareContext` / `ScenarioStateAwareTrait`) is **unchanged** — the
only consumer-visible change is the raised platform floor. Projects on older
stacks should stay on the `1.x` line.

### BC breaks

- **PHP**: now requires `>=8.4` (was `>=5.5`).
- **Symfony**: components now require `^6.4 || ^7.0` (dropped 2.x–5.x).
- **Behat**: now requires `^3.31` (the first line supporting Symfony 7 + PHP 8.4).
- **doctrine/annotations**: upgraded to `^2.0`; the removed
  `AnnotationRegistry::registerFile()` call is gone (annotation classes are now
  resolved through the Composer autoloader).

### Internal / tooling

- `RuntimeCallHandler`: replaced the removed (PHP 8.0) `ReflectionParameter::getClass()`
  with `getType()` / `ReflectionNamedType`; hook scope matching now also matches
  parent/interface type declarations. Hook calls are re-wrapped around the
  original `HookCall` so Behat 3.31 hook statistics keep working.
- Test suite migrated to PHPUnit `^13.0` with the `phpspec/prophecy-phpunit`
  bridge; dropped the unused `phpspec/phpspec` dev dependency.
- Acceptance fixture migrated to the Symfony 5+ `Process` API and modern Behat
  `Context` interface.
- CI moved from Travis to GitHub Actions (PHP 8.4, lowest + highest deps);
  added Dependabot and `roave/security-advisories`.
