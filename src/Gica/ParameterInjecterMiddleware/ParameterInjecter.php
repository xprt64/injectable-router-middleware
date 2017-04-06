<?php
/******************************************************************************
 * Copyright (c) 2017 Constantin Galbenu <gica.galbenu@gmail.com>             *
 ******************************************************************************/

namespace Gica\ParameterInjecterMiddleware;

use Gica\CodeAnalysis\Shared\FqnResolver;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ParameterInjecter
{

    /**
     * @var ContainerInterface
     */
    private $container;

    private $variables;

    /** @var \ReflectionClass */
    private $middlewareClass;
    /**
     * @var CustomHydrator
     */
    private $customHydrator;

    public function __construct(
        ContainerInterface $container,
        CustomHydrator $customHydrator
    )
    {
        $this->container = $container;
        $this->customHydrator = $customHydrator;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next = null)
    {
        $matchedRoute = $request->getAttribute('Zend\Expressive\Router\RouteResult');

        if (!$matchedRoute) {
            return $next($request, $request);
        }

        $className = $matchedRoute->getMatchedMiddleware();

        if (!$className) {
            return $next($request, $request);
        }

        $this->middlewareClass = new \ReflectionClass($className);

        if (!$this->middlewareClass->hasMethod('__invoke')) {
            return $next($request, $request);
        }

        $invoke = $this->middlewareClass->getMethod('__invoke');

        $get = $request->getQueryParams();
        $body = $request->getParsedBody();

        $this->variables = [
            'request'  => $request,
            'isPost'   => 'POST' == $request->getMethod(),
            'isGet'    => 'GET' == $request->getMethod(),
            'response' => $response,
            'next'     => $next,
            'get'      => $get,
            'body'     => $body,
        ];

        $callArguments = [];

        foreach ($invoke->getParameters() as $parameter) {
            $rawValue = $this->getValue($request, $parameter, $get, $body);

            if ($rawValue === '') {
                $rawValue = null;
            }

            //echo $parameter->getName() . ' ' . (string)$parameter->getType() . ' ' . print_r($rawValue, true) . (is_null($rawValue) ? ' (NULL)' : '') . "\n\n";

            $callArguments[] = $this->parseParameter($rawValue, $parameter, $invoke);
        }


//        foreach($callArguments as $i => $argument)
//        {
//            echo $i . "." . (null !== $argument ? get_class($argument) : 'NULL') . "\n\n";
//        }
//
//        echo $className;
//
//        die();

        unset($this->variables);
        unset($this->middlewareClass);

        return call_user_func_array([$this->container->get($className), '__invoke'], $callArguments);
    }

    private function parseParameter($rawValue, \ReflectionParameter $parameter, \ReflectionMethod $method)
    {
        $name = $parameter->getName();
        if (isset($this->variables[$name])) {
            return $this->variables[$name];
        }

        if (!$parameter->hasType()) {
            return $rawValue;
        } else {
            if (null === $rawValue) {
                if (!$parameter->getType()->allowsNull()) {
                    if ($parameter->isDefaultValueAvailable()) {
                        return $parameter->getDefaultValue();
                    } else {
                        throw new \Exception("Parameter $name must not be null");
                    }
                } else {
                    return null;
                }
            }

            if ('array' == $parameter->getType()) {
                return $this->hydrateArrayValue($rawValue, $parameter, $method);
            } else if ($this->isScalar((string)$parameter->getType())) {
                return $this->hydrateBuiltinValue($rawValue, $parameter->getType());
            } else {
                return $this->hydrateCustomValue($rawValue, $parameter->getClass(), $parameter->name);
            }
        }
    }

    private function isScalar(string $shortType): bool
    {
        return in_array($shortType, ['bool', 'string', 'int', 'boolean', 'null', 'float', 'double']);
    }

    private function hydrateBuiltinValue($rawValue, string $type)
    {

        switch ($type) {
            case 'string':
                return strval($rawValue);
            case 'int':
                return intval($rawValue, 10);
            case 'double':
            case 'float':
                return floatval($rawValue);
            case 'bool':
            case 'boolean':
                return boolval($rawValue);

            default:
                return $rawValue;
        }
    }

    private function hydrateCustomValue($rawValue, \ReflectionClass $reflectionClass, string $parameterName)
    {
        try {
            return $this->customHydrator->tryToHydrateFromValue($reflectionClass, $rawValue);
        } catch (\Exception $exception) {
            throw new \Exception("unkown custom value for {$parameterName}@{$reflectionClass->name}: {$exception->getMessage()}");
        }
    }

    private function hydrateArrayValue($rawValue, \ReflectionParameter $parameter, \ReflectionMethod $method)
    {
        $class = $this->detectClassNameFromPropertyComment($parameter, $method);

        $rawValues = is_array($rawValue) ? $rawValue : explode('|', $rawValue);

        return array_map(function ($rawValue) use ($parameter, $class) {
            if ($this->isScalar($class)) {
                return $this->hydrateBuiltinValue($rawValue, $class);
            } else {
                return $this->hydrateCustomValue($rawValue, new \ReflectionClass($class), $parameter->name);
            }
        }, $rawValues);
    }

    private function parseTypeFromPropertyVarDoc(string $parameterName, \ReflectionMethod $method)
    {
        if (!preg_match('#\@param\s+(?P<shortType>[\\\\a-z0-9_\]\[]+)\s+\$' . $parameterName . '#ims', $method->getDocComment(), $m)) {
            throw new \Exception("Could not detect type from vardoc for parameter {$parameterName}");
        }
        return $m['shortType'];
    }

    private function detectClassNameFromPropertyComment(\ReflectionParameter $parameter, \ReflectionMethod $method)
    {
        $shortType = $this->parseTypeFromPropertyVarDoc($parameter->name, $method);

        $shortType = rtrim($shortType, '[]');

        if ('array' === $shortType) {
            return null;
        }

        if ('\\' == $shortType[0]) {
            return ltrim($shortType, '\\');
        }

        if ($this->isScalar($shortType)) {
            return $shortType;
        }

        return ltrim($this->resolveShortClassName($shortType, $this->middlewareClass), '\\');
    }

    private function resolveShortClassName($shortName, \ReflectionClass $contextClass)
    {
        return (new FqnResolver())->resolveShortClassName($shortName, $contextClass);

    }

    private function getValue(ServerRequestInterface $request, \ReflectionParameter $parameter, $get, $body)
    {
        if (preg_match('#^(.+)Body$#ims', $parameter->getName(), $m) || preg_match('#^(.+)Post#ims', $parameter->getName(), $m)) {
            return isset($body[$m[1]]) ? $body[$m[1]] : null;
        }

        return (null !== $request->getAttribute($parameter->name)) ? $request->getAttribute($parameter->name) : @$get[$parameter->name];
    }
}