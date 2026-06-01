<?php

/*
 * This file is part of the ScenarioStateBehatExtension project.
 *
 * (c) Rodrigue Villetard <rodrigue.villetard@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gorghoa\ScenarioStateBehatExtension\Call\Handler;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

interface RuntimeCallHandlerScopeFixtureInterface
{
}

class RuntimeCallHandlerScopeFixture implements RuntimeCallHandlerScopeFixtureInterface
{
}

/**
 * Covers the PHP 8.0+ rewrite of the hook scope-injection logic
 * (ReflectionParameter::getClass() -> getType()/ReflectionNamedType).
 */
class RuntimeCallHandlerTest extends TestCase
{
    public function withExactType(RuntimeCallHandlerScopeFixture $scope): void
    {
    }

    public function withInterfaceType(RuntimeCallHandlerScopeFixtureInterface $scope): void
    {
    }

    public function withNullableType(?RuntimeCallHandlerScopeFixture $scope): void
    {
    }

    public function withUnionType(RuntimeCallHandlerScopeFixture|\stdClass $scope): void
    {
    }

    public function withIntersectionType(RuntimeCallHandlerScopeFixtureInterface&\Countable $scope): void
    {
    }

    public function withBuiltinType(string $scope): void
    {
    }

    public function withoutType($scope): void
    {
    }

    public static function parameterMatchingProvider(): array
    {
        return [
            'exact class type matches'        => ['withExactType', true],
            'interface/parent type matches'   => ['withInterfaceType', true],
            'nullable class type matches'     => ['withNullableType', true],
            'union type is skipped'           => ['withUnionType', false],
            'intersection type is skipped'    => ['withIntersectionType', false],
            'builtin type is skipped'         => ['withBuiltinType', false],
            'untyped parameter is skipped'    => ['withoutType', false],
        ];
    }

    #[DataProvider('parameterMatchingProvider')]
    public function testParameterAcceptsScope(string $method, bool $expected): void
    {
        $parameter = (new \ReflectionMethod($this, $method))->getParameters()[0];
        $scope = new RuntimeCallHandlerScopeFixture();

        $accepts = new \ReflectionMethod(RuntimeCallHandler::class, 'parameterAcceptsScope');

        $this->assertSame($expected, $accepts->invoke(null, $parameter, $scope));
    }
}
