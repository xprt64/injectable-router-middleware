<?php
/**
 * Copyright (c) 2017 Constantin Galbenu <xprt64@gmail.com>
 */

namespace Gica\ParameterInjecterMiddleware\CustomHydrator;


use Gica\ParameterInjecterMiddleware\CustomHydrator;

class StaticMethodFromPrimitive implements CustomHydrator
{
    /**
     * @inheritdoc
     */
    public function tryToHydrateFromValue(\ReflectionClass $reflectionClass, $value)
    {
        if (is_scalar($value) || is_array($value)) {
            if (is_callable([$reflectionClass->getName(), 'fromPrimitive'])) {
                return call_user_func([$reflectionClass->getName(), 'fromPrimitive'], $value);
            }
        }

        throw new \Exception("Could not hydrate value");
    }
}