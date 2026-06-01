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

use Behat\Behat\Transformation\Call\TransformationCall;
use Behat\Testwork\Call\Call;
use Behat\Testwork\Call\CallResult;
use Behat\Testwork\Call\Handler\CallHandler;
use Behat\Testwork\Environment\Call\EnvironmentCall;
use Behat\Testwork\Hook\Call\HookCall;
use Gorghoa\ScenarioStateBehatExtension\Resolver\ArgumentsResolver;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
final class RuntimeCallHandler implements CallHandler
{
    /**
     * @var CallHandler
     */
    private $decorated;

    /**
     * @var ArgumentsResolver
     */
    private $argumentsResolver;

    /**
     * @param CallHandler       $decorated
     * @param ArgumentsResolver $argumentsResolver
     */
    public function __construct(CallHandler $decorated, ArgumentsResolver $argumentsResolver)
    {
        $this->decorated = $decorated;
        $this->argumentsResolver = $argumentsResolver;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsCall(Call $call)
    {
        return $this->decorated->supportsCall($call);
    }

    /**
     * {@inheritdoc}
     */
    public function handleCall(Call $call)
    {
        /** @var \ReflectionMethod $function */
        $function = $call->getCallee()->getReflection();
        $arguments = $call->getArguments();
        $originalCall = $call;

        if ($call instanceof HookCall) {
            $scope = $call->getScope();

            // Manage `scope` argument
            foreach ($function->getParameters() as $parameter) {
                if (self::parameterAcceptsScope($parameter, $scope)) {
                    $arguments[$parameter->getName()] = $scope;
                    break;
                }
            }
        }

        $arguments = $this->argumentsResolver->resolve($function, $arguments);

        if ($call instanceof TransformationCall) {
            $call = new TransformationCall($call->getEnvironment(), $call->getDefinition(), $call->getCallee(), $arguments);
        } elseif ($call instanceof HookCall) {
            // HookCall is final and forces its arguments to [$scope], so it
            // cannot carry the resolved arguments. Execute the call as a generic
            // EnvironmentCall instead.
            $call = new EnvironmentCall(
                $call->getScope()->getEnvironment(),
                $call->getCallee(),
                $arguments,
                $call->getErrorReportingLevel()
            );
        }

        $result = $this->decorated->handleCall($call);

        // Behat associates hook statistics with the original HookCall and calls
        // getScope() on it; since hooks are executed through a rebuilt
        // EnvironmentCall, re-wrap the result around the original HookCall.
        if ($originalCall instanceof HookCall) {
            $result = new CallResult(
                $originalCall,
                $result->getReturn(),
                $result->getException(),
                $result->getStdOut()
            );
        }

        return $result;
    }

    /**
     * Whether a hook parameter should receive the scope object.
     *
     * Only a single, non-builtin class type can match: the scope is injected
     * when it is an instance of the parameter's declared class/interface.
     * Untyped, builtin (int, string, …), union and intersection types never
     * match. This replaces the removed (PHP 8.0) ReflectionParameter::getClass()
     * and additionally matches parent/interface type declarations.
     */
    private static function parameterAcceptsScope(\ReflectionParameter $parameter, object $scope): bool
    {
        $type = $parameter->getType();

        if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
            return false;
        }

        $className = $type->getName();

        return $scope instanceof $className;
    }
}
