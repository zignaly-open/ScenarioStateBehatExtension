<?php

/*
 * This file is part of the ScenarioStateBehatExtension project.
 *
 * (c) Rodrigue Villetard <rodrigue.villetard@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gorghoa\ScenarioStateBehatExtension\Annotation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @author Vincent Chalamon <vincentchalamon@gmail.com>
 */
class ScenarioStateArgumentTest extends TestCase
{
    #[DataProvider('getArguments')]
    public function testWithValue(array $arguments, string $name, string $argument): void
    {
        $annotation = new ScenarioStateArgument($arguments);
        $this->assertEquals($name, $annotation->name);
        $this->assertEquals($argument, $annotation->argument);
    }

    public static function getArguments(): array
    {
        return [
            [
                ['value' => 'foo'],
                'foo',
                'foo',
            ],
            [
                ['name' => 'foo'],
                'foo',
                'foo',
            ],
            [
                ['name' => 'foo', 'argument' => 'bar'],
                'foo',
                'bar',
            ],
        ];
    }
}
