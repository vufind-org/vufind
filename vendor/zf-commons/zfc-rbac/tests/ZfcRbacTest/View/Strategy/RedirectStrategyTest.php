<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license.
 */

namespace ZfcRbacTest\View\Strategy;

use Zend\Authentication\AuthenticationService;
use Zend\Http\Request as HttpRequest;
use Zend\Http\Response as HttpResponse;
use Zend\Mvc\MvcEvent;
use Zend\Mvc\Router\Http\TreeRouteStack;
use ZfcRbac\Exception\UnauthorizedException;
use ZfcRbac\Options\RedirectStrategyOptions;
use ZfcRbac\View\Strategy\RedirectStrategy;

/**
 * @covers \ZfcRbac\View\Strategy\RedirectStrategy
 * @covers \ZfcRbac\View\Strategy\AbstractStrategy
 */
class RedirectStrategyTest extends \PHPUnit_Framework_TestCase
{
    public function testAttachToRightEvent()
    {
        $strategyListener = new RedirectStrategy(new RedirectStrategyOptions(), new AuthenticationService());

        $eventManager = $this->getMock('Zend\EventManager\EventManagerInterface');
        $eventManager->expects($this->once())
                     ->method('attach')
                     ->with(MvcEvent::EVENT_DISPATCH_ERROR);

        $strategyListener->attach($eventManager);
    }

    public function testReturnNullIfNotRightException()
    {
        $redirectStrategy = new RedirectStrategy(new RedirectStrategyOptions(), new AuthenticationService());
        $event            = new MvcEvent();
        $event->setParam('exception', new \RuntimeException());

        $this->assertNull($redirectStrategy->onError($event));
    }

    public function testCanRedirectWhenDisconnected()
    {
        $response = new HttpResponse();

        $router = new TreeRouteStack();
        $router->addRoute('login', [
            'type'    => 'Zend\Mvc\Router\Http\Literal',
            'options' => [
                'route' => '/login'
            ]
        ]);

        $mvcEvent = new MvcEvent();
        $mvcEvent->setParam('exception', new UnauthorizedException());
        $mvcEvent->setResponse($response);
        $mvcEvent->setRouter($router);

        $options = new RedirectStrategyOptions([
            'redirect_to_route_disconnected' => 'login',
            'append_previous_uri'            => false
        ]);

        $authenticationService = $this->getMock('Zend\Authentication\AuthenticationService');
        $authenticationService->expects($this->once())->method('hasIdentity')->will($this->returnValue(false));

        $redirectStrategy = new RedirectStrategy($options, $authenticationService);

        $redirectStrategy->onError($mvcEvent);

        $this->assertInstanceOf('Zend\Stdlib\ResponseInterface', $mvcEvent->getResult());
        $this->assertEquals(302, $mvcEvent->getResponse()->getStatusCode());
        $this->assertEquals('/login', $mvcEvent->getResponse()->getHeaders()->get('Location')->getFieldValue());
    }

    public function testCanRedirectWhenConnected()
    {
        $response = new HttpResponse();

        $router = new TreeRouteStack();
        $router->addRoute('home', [
                'type'    => 'Zend\Mvc\Router\Http\Literal',
                'options' => [
                    'route' => '/home'
                ]
            ]);

        $mvcEvent = new MvcEvent();
        $mvcEvent->setParam('exception', new UnauthorizedException());
        $mvcEvent->setResponse($response);
        $mvcEvent->setRouter($router);

        $options = new RedirectStrategyOptions([
            'redirect_to_route_connected'    => 'home',
            'append_previous_uri'            => false
        ]);

        $authenticationService = $this->getMock('Zend\Authentication\AuthenticationService');
        $authenticationService->expects($this->once())->method('hasIdentity')->will($this->returnValue(true));

        $redirectStrategy = new RedirectStrategy($options, $authenticationService);

        $redirectStrategy->onError($mvcEvent);

        $this->assertInstanceOf('Zend\Stdlib\ResponseInterface', $mvcEvent->getResult());
        $this->assertEquals(302, $mvcEvent->getResponse()->getStatusCode());
        $this->assertEquals('/home', $mvcEvent->getResponse()->getHeaders()->get('Location')->getFieldValue());
    }
	
    public function testWontRedirectWhenConnectedAndOptionDisabled()
    {
        $response = new HttpResponse();

        $router = new TreeRouteStack();
        $router->addRoute('home', [
                'type'    => 'Zend\Mvc\Router\Http\Literal',
                'options' => [
                    'route' => '/home'
                ]
            ]);

        $mvcEvent = new MvcEvent();
        $mvcEvent->setParam('exception', new UnauthorizedException());
        $mvcEvent->setResponse($response);
        $mvcEvent->setRouter($router);

        $options = new RedirectStrategyOptions([
            'redirect_when_connected' => false
        ]);

        $authenticationService = $this->getMock('Zend\Authentication\AuthenticationService');
        $authenticationService->expects($this->once())->method('hasIdentity')->will($this->returnValue(true));

        $redirectStrategy = new RedirectStrategy($options, $authenticationService);

        $redirectStrategy->onError($mvcEvent);

        $this->assertNotEquals(302, $mvcEvent->getResponse()->getStatusCode());
    }

    public function testCanAppendPreviousUri()
    {
        $response = new HttpResponse();

        $request  = new HttpRequest();
        $request->setUri('http://example.com');

        $router = new TreeRouteStack();
        $router->addRoute('login', [
                'type'    => 'Zend\Mvc\Router\Http\Literal',
                'options' => [
                    'route' => '/login'
                ]
            ]);

        $mvcEvent = new MvcEvent();
        $mvcEvent->setParam('exception', new UnauthorizedException());
        $mvcEvent->setResponse($response);
        $mvcEvent->setRequest($request);
        $mvcEvent->setRouter($router);

        $options = new RedirectStrategyOptions([
            'redirect_to_route_disconnected' => 'login',
            'append_previous_uri'            => true,
            'previous_uri_query_key'         => 'redirect-uri'
        ]);

        $authenticationService = $this->getMock('Zend\Authentication\AuthenticationService');
        $authenticationService->expects($this->once())->method('hasIdentity')->will($this->returnValue(false));

        $redirectStrategy = new RedirectStrategy($options, $authenticationService);

        $redirectStrategy->onError($mvcEvent);

        $this->assertInstanceOf('Zend\Stdlib\ResponseInterface', $mvcEvent->getResult());
        $this->assertEquals(302, $mvcEvent->getResponse()->getStatusCode());
        $this->assertEquals(
            '/login?redirect-uri=http://example.com/',
            $mvcEvent->getResponse()->getHeaders()->get('Location')->getFieldValue()
        );
    }
}
