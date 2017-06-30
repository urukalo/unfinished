<?php

declare(strict_types=1);

namespace Test\Article\Controller;

class DiscussionFactoryTest extends \PHPUnit_Framework_TestCase
{
    public function testInvokingFactoryShouldReturnExpectedInstance()
    {
        $router = $this->getMockBuilder('Zend\Expressive\Router\RouterInterface')
            ->getMockForAbstractClass();
        $template = $this->getMockBuilder(\Zend\Expressive\Template\TemplateRendererInterface::class)
            ->getMockForAbstractClass();
        $discussionService = $this->getMockBuilder('Article\Service\DiscussionService')
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $categoryService = $this->getMockBuilder('Category\Service\CategoryService')
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $session = new \Zend\Session\SessionManager();
        $container = $this->getMockBuilder(\Interop\Container\ContainerInterface::class)
            ->setMethods(['get'])
            ->getMockForAbstractClass();
        $container->expects(static::at(0))
            ->method('get')
            ->will(static::returnValue($template));
        $container->expects(static::at(1))
            ->method('get')
            ->will(static::returnValue($router));
        $container->expects(static::at(2))
            ->method('get')
            ->will(static::returnValue($discussionService));
        $container->expects(static::at(3))
            ->method('get')
            ->will(static::returnValue($session));
        $container->expects(static::at(4))
            ->method('get')
            ->will(static::returnValue($categoryService));
        $factory = new \Article\Factory\Controller\DiscussionFactory();
        static::assertInstanceOf(\Article\Controller\DiscussionController::class, $factory($container));
    }
}
