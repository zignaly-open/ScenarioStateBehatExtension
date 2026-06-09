<?php

/*
 * This file is part of the ScenarioStateBehatExtension project.
 *
 * (c) Rodrigue Villetard <rodrigue.villetard@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gorghoa\ScenarioStateBehatExtension;

use Gorghoa\ScenarioStateBehatExtension\Exception\MissingStateException;
use PHPUnit\Framework\TestCase;

/**
 * @author Walter Dolce <walterdolce@gmail.com>
 */
class ScenarioStateTest extends TestCase
{
    public function testItThrowsExceptionWhenStateIsMissing(): void
    {
        $this->expectException(MissingStateException::class);
        (new ScenarioState())->getStateFragment('not_existing_state');
    }
}
