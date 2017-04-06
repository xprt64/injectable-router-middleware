<?php
/**
 * Copyright (c) 2017 Constantin Galbenu <xprt64@gmail.com>
 */

namespace Gica\ParameterInjecterMiddleware\CustomHydrator;


use Gica\ParameterInjecterMiddleware\CustomHydrator;

class CompositeHydrator implements CustomHydrator
{
    /**
     * @var CustomHydrator[]
     */
    private $hydrators;

    /**
     * @param CustomHydrator[] $hydrators
     */
    public function __construct($hydrators)
    {
        $this->hydrators = $hydrators;
    }

    /**
     * @inheritdoc
     */
    public function tryToHydrateFromValue(\ReflectionClass $reflectionClass, $value)
    {
        foreach ($this->hydrators as $hydrator) {
            try {
                return $hydrator->tryToHydrateFromValue($reflectionClass, $value);
            } catch (\Exception $exception) {

            }
        }

        throw new \Exception(sprintf("Could not hydrate value by any of the %d sub-hydrators", count($this->hydrators)));
    }
}