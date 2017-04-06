<?php
/**
 * Copyright (c) 2017 Constantin Galbenu <xprt64@gmail.com>
 */

namespace Gica\ParameterInjecterMiddleware\CustomHydrator;


use Gica\ParameterInjecterMiddleware\CustomHydrator;

class StaticMethodFromString implements CustomHydrator
{
    /**
     * @inheritdoc
     */
    public function tryToHydrateFromValue(\ReflectionClass $reflectionClass, $value)
    {
        if ((is_string($value) || is_callable([$value, '__toString']))) {
            if ($reflectionClass->hasMethod('fromString')) {
                $method = $reflectionClass->getMethod('fromString');

                if ($method->isStatic()) {
                    return $method->invoke(null, (string)$value);
                }
            }
        }

        throw new \Exception("Could not hydrate value");
    }
}