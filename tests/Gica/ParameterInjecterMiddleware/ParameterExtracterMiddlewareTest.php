<?php
/******************************************************************************
 * Copyright (c) 2017 Constantin Galbenu <gica.galbenu@gmail.com>             *
 ******************************************************************************/

namespace tests\Gica\ParameterInjecterMiddleware;

use Gica\ParameterInjecterMiddleware\ParameterInjecter;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ParameterExtracterMiddlewareTest extends \PHPUnit_Framework_TestCase
{

    public function test___invoke()
    {
        $request = $this->getMockBuilder(ServerRequestInterface::class)
            //   ->setMethods(['getAttribute', 'getQueryParams'])
            ->getMock();

        $matchedRoute = $this->getMockBuilder('Zend\Expressive\Router\RouteResult')
            ->disableOriginalConstructor()
            ->setMethods(['getMatchedMiddleware'])
            ->getMock();

        $matchedRoute->method('getMatchedMiddleware')
            ->willReturn(WebPage::class);

        $map = [
            'Zend\Expressive\Router\RouteResult' => $matchedRoute,
            'integerAttribute' => 123,
            'floatAttribute'   => 123.456,
            'stringAttribute'  => 'abc',
            'integers'         => '1|2|3',
        ];

        $request->method('getAttribute')
            ->will($this->returnCallback(function ($what) use ($map) {
                return isset($map[$what]) ? $map[$what] : null;
            }));

        $get = [
            'someQueryParameter' => 'someQueryParameterValue',
            'customAttribute'    => 'customAttributeValue',
            'integers'           => '100|101|102',//this should be ignored
        ];

        $request->method('getQueryParams')
            ->willReturn($get);

        $response = $this->getMockBuilder(ResponseInterface::class)
            ->getMock();

        $called = $this->getMockBuilder(WebPage::class)
            ->getMock();

        $called->expects($this->once())
            ->method('__invoke')
            ->with(
                $this->identicalTo(123),
                $this->identicalTo(123.456),
                $this->identicalTo('abc'),
                $this->isInstanceOf(CustomAttribute::class),
                $this->identicalTo($request),
                $this->identicalTo('someQueryParameterValue'),
                $this->isNull(),
                $this->equalTo([1, 2, 3])
            );


        $container = $this->getMockBuilder(ContainerInterface::class)
            ->getMock();
        $container->method('get')
            ->with($this->equalTo(WebPage::class))
            ->willReturn($called);

        /** @var ContainerInterface $container */
        $sut = new ParameterInjecter($container);

        /** @var ServerRequestInterface $request */
        /** @var ResponseInterface $response */

        $sut->__invoke($request, $response);
    }
}

class WebPage
{
    /**
     * @param int $integerAttribute
     * @param float $floatAttribute
     * @param string $stringAttribute
     * @param CustomAttribute $customAttribute
     * @param ServerRequestInterface $request
     * @param string $someQueryParameter
     * @param string|null $someNullQueryParameter
     * @param int[] $integers
     */
    public function __invoke(
        int $integerAttribute,
        float $floatAttribute,
        string $stringAttribute,
        CustomAttribute $customAttribute,
        ServerRequestInterface $request,
        string $someQueryParameter,
        string $someNullQueryParameter = null,
        array $integers
    )
    {

    }
}

class CustomAttribute
{

    private $value;

    public function __construct(
        $value
    )
    {
        if (false === stripos($value, '-custom-attribute')) {
            throw new \Exception("Invalid Custom value");
        }

        $this->value = $value;
    }

    public static function makeValue($str): string
    {
        return $str . '-custom-attribute';
    }

    public function getValue()
    {
        return $this->value;
    }

    public static function fromString($str)
    {
        return new self(self::makeValue($str));
    }
}