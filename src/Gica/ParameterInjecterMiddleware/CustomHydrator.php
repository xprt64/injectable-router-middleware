<?php
/**
 * Copyright (c) 2017 Constantin Galbenu <xprt64@gmail.com>
 */

namespace Gica\ParameterInjecterMiddleware;


interface CustomHydrator
{
    /**
     * @param \ReflectionClass $reflectionClass
     * @param $value
     * @return mixed
     * @throws \Exception
     */
    public function tryToHydrateFromValue(\ReflectionClass $reflectionClass, $value);
}