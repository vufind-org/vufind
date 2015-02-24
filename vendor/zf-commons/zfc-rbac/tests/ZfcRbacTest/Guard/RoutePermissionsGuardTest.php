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
namespace ZfcRbacTest\Guard;

use Zend\Mvc\MvcEvent;
use Zend\Mvc\Router\RouteMatch;
use ZfcRbac\Guard\ControllerGuard;
use ZfcRbac\Guard\GuardInterface;
use ZfcRbac\Guard\RouteGuard;
use ZfcRbac\Guard\RoutePermissionsGuard;

/**
 * @covers \ZfcRbac\Guard\AbstractGuard
 * @covers \ZfcRbac\Guard\RoutePermissionsGuard
 */
class RoutePermissionsGuardTest extends \PHPUnit_Framework_TestCase
{
    public function testAttachToRightEvent()
    {
        $eventManager = $this->getMock('Zend\EventManager\EventManagerInterface');
        $eventManager->expects($this->once())
            ->method('attach')
            ->with(RouteGuard::EVENT_NAME);

        $guard = new RoutePermissionsGuard($this->getMock('ZfcRbac\Service\AuthorizationService', [], [], '', false));
        $guard->attach($eventManager);
    }

    /**
     * We want to ensure an order for guards
     */
    public function testAssertRoutePermissionsGuardPriority()
    {
        $this->assertLessThan(RouteGuard::EVENT_PRIORITY, RoutePermissionsGuard::EVENT_PRIORITY);
        $this->assertGreaterThan(ControllerGuard::EVENT_PRIORITY, RoutePermissionsGuard::EVENT_PRIORITY);
    }

    public function rulesConversionProvider()
    {
        return [
            // Simple string to array conversion
            [
                'rules'    => [
                    'route' => 'permission1'
                ],
                'expected' => [
                    'route' => ['permission1']
                ]
            ],
            // Array to array
            [
                'rules'    => [
                    'route' => ['permission1', 'permission2']
                ],
                'expected' => [
                    'route' => ['permission1', 'permission2']
                ]
            ],
            // Traversable to array
            [
                'rules'    => [
                    'route' => new \ArrayIterator(['permission1', 'permission2'])
                ],
                'expected' => [
                    'route' => ['permission1', 'permission2']
                ]
            ],
            // Block a route for everyone
            [
                'rules'    => [
                    'route'
                ],
                'expected' => [
                    'route' => []
                ]
            ],
        ];
    }

    /**
     * @dataProvider rulesConversionProvider
     */
    public function testRulesConversions(array $rules, array $expected)
    {
        $roleService  = $this->getMock('ZfcRbac\Service\AuthorizationService', [], [], '', false);
        $routeGuard   = new RoutePermissionsGuard($roleService, $rules);
        $reflProperty = new \ReflectionProperty($routeGuard, 'rules');
        $reflProperty->setAccessible(true);
        $this->assertEquals($expected, $reflProperty->getValue($routeGuard));
    }

    public function routeDataProvider()
    {
        return [
            // Assert basic one-to-one mapping with both policies
            [
                'rules'               => ['adminRoute' => 'post.edit'],
                'matchedRouteName'    => 'adminRoute',
                'identityPermissions' => [['post.edit', null, true]],
                'isGranted'           => true,
                'policy'              => GuardInterface::POLICY_ALLOW
            ],
            [
                'rules'               => ['adminRoute' => 'post.edit'],
                'matchedRouteName'    => 'adminRoute',
                'identityPermissions' => [['post.edit', null, true]],
                'isGranted'           => true,
                'policy'              => GuardInterface::POLICY_DENY
            ],
            // Assert that policy changes result for non-specified route guards
            [
                'rules'               => ['route' => 'post.edit'],
                'matchedRouteName'    => 'anotherRoute',
                'identityPermissions' => [['post.edit', null, true]],
                'isGranted'           => true,
                'policy'              => GuardInterface::POLICY_ALLOW
            ],
            [
                'rules'               => ['route' => 'post.edit'],
                'matchedRouteName'    => 'anotherRoute',
                'identityPermissions' => [['post.edit', null, true]],
                'isGranted'           => false,
                'policy'              => GuardInterface::POLICY_DENY
            ],
            // Assert that composed route work for both policies
            [
                'rules'               => ['admin/dashboard' => 'post.edit'],
                'matchedRouteName'    => 'admin/dashboard',
                'identityPermissions' => [['post.edit', null, true]],
                'isGranted'           => true,
                'policy'              => GuardInterface::POLICY_ALLOW
            ],
            [
                'rules'               => ['admin/dashboard' => 'post.edit'],
                'matchedRouteName'    => 'admin/dashboard',
                'identityPermissions' => [['post.edit', null, true]],
                'isGranted'           => true,
                'policy'              => GuardInterface::POLICY_DENY
            ],
            // Assert that wildcard route work for both policies
            [
                'rules'               => ['admin/*' => 'post.edit'],
                'matchedRouteName'    => 'admin/dashboard',
                'identityPermissions' => [['post.edit', null, true]],
                'isGranted'           => true,
                'policy'              => GuardInterface::POLICY_ALLOW
            ],
            [
                'rules'               => ['admin/*' => 'post.edit'],
                'matchedRouteName'    => 'admin/dashboard',
                'identityPermissions' => [['post.edit', null, true]],
                'isGranted'           => true,
                'policy'              => GuardInterface::POLICY_DENY
            ],
            // Assert that wildcard route does match (or not depending on the policy) if rules is after matched route name
            [
                'rules'               => ['fooBar/*' => 'post.edit'],
                'matchedRouteName'    => 'admin/fooBar/baz',
                'identityPermissions' => [['post.edit', null, true]],
                'isGranted'           => true,
                'policy'              => GuardInterface::POLICY_ALLOW
            ],
            [
                'rules'               => ['fooBar/*' => 'post.edit'],
                'matchedRouteName'    => 'admin/fooBar/baz',
                'identityPermissions' => [['post.edit', null, true]],
                'isGranted'           => false,
                'policy'              => GuardInterface::POLICY_DENY
            ],
            // Assert that it can grant access with multiple rules
            [
                'rules'               => [
                    'route1' => 'post.edit',
                    'route2' => 'post.edit'
                ],
                'matchedRouteName'    => 'route1',
                'identityPermissions' => [['post.edit', null, true]],
                'isGranted'           => true,
                'policy'              => GuardInterface::POLICY_ALLOW
            ],
            [
                'rules'               => [
                    'route1' => 'post.edit',
                    'route2' => 'post.edit'
                ],
                'matchedRouteName'    => 'route2',
                'identityPermissions' => [['post.edit', null, true]],
                'isGranted'           => true,
                'policy'              => GuardInterface::POLICY_ALLOW
            ],
            [
                'rules'               => [
                    'route1' => 'post.edit',
                    'route2' => 'post.edit'
                ],
                'matchedRouteName'    => 'route1',
                'identityPermissions' => [['post.edit', null, true]],
                'isGranted'           => true,
                'policy'              => GuardInterface::POLICY_DENY
            ],
            [
                'rules'               => [
                    'route1' => 'post.edit',
                    'route2' => 'post.edit'
                ],
                'matchedRouteName'    => 'route2',
                'identityPermissions' => [['post.edit', null, true]],
                'isGranted'           => true,
                'policy'              => GuardInterface::POLICY_DENY
            ],
            // Assert that it can grant/deny access with multiple rules based on the policy
            [
                'rules'               => [
                    'route1' => 'post.edit',
                    'route2' => 'post.edit'
                ],
                'matchedRouteName'    => 'route3',
                'identityPermissions' => [['post.edit', null, true]],
                'isGranted'           => true,
                'policy'              => GuardInterface::POLICY_ALLOW
            ],
            [
                'rules'               => [
                    'route1' => 'post.edit',
                    'route2' => 'post.edit'
                ],
                'matchedRouteName'    => 'route3',
                'identityPermissions' => [['post.edit', null, true]],
                'isGranted'           => false,
                'policy'              => GuardInterface::POLICY_DENY
            ],
            // Assert it can deny access if the only permission does not have access
            [
                'rules'               => ['route' => 'post.edit'],
                'matchedRouteName'    => 'route',
                'identityPermissions' => [
                    ['post.edit', null, false],
                    ['post.read', null, true]
                ],
                'isGranted'           => false,
                'policy'              => GuardInterface::POLICY_ALLOW
            ],
            [
                'rules'               => ['route' => 'post.edit'],
                'matchedRouteName'    => 'route',
                'identityPermissions' => [
                    ['post.edit', null, false],
                    ['post.read', null, true]
                ],
                'isGranted'           => false,
                'policy'              => GuardInterface::POLICY_DENY
            ],
            // Assert it can deny access if one of the permission does not have access
            [
                'rules'               => ['route' => ['post.edit', 'post.read']],
                'matchedRouteName'    => 'route',
                'identityPermissions' => [
                    ['post.edit', null, true],
                    ['post.read', null, true]
                ],
                'isGranted'           => true,
                'policy'              => GuardInterface::POLICY_ALLOW
            ],
            [
                'rules'               => ['route' => ['post.edit', 'post.read']],
                'matchedRouteName'    => 'route',
                'identityPermissions' => [
                    ['post.edit', null, true],
                    ['post.read', null, false]
                ],
                'isGranted'           => false,
                'policy'              => GuardInterface::POLICY_ALLOW
            ],
            [
                'rules'               => ['route' => ['post.edit', 'post.read']],
                'matchedRouteName'    => 'route',
                'identityPermissions' => [
                    ['post.edit', null, false],
                    ['post.read', null, true]
                ],
                'isGranted'           => false,
                'policy'              => GuardInterface::POLICY_ALLOW
            ],
            // Assert wildcard in permission
            [
                'rules'               => ['home' => '*'],
                'matchedRouteName'    => 'home',
                'identityPermissions' => [['post.edit', null, true]],
                'isGranted'           => true,
                'policy'              => GuardInterface::POLICY_ALLOW
            ],
            [
                'rules'               => ['home' => '*'],
                'matchedRouteName'    => 'home',
                'identityPermissions' => [['post.edit', null, true]],
                'isGranted'           => true,
                'policy'              => GuardInterface::POLICY_DENY
            ],
            // Assert wildcard wins all
            [
                'rules'               => ['home' => ['*', 'post.edit']],
                'matchedRouteName'    => 'home',
                'identityPermissions' => [['post.edit', null, false]],
                'isGranted'           => true,
                'policy'              => GuardInterface::POLICY_ALLOW
            ],
            [
                'rules'               => ['home' => ['*', 'post.edit']],
                'matchedRouteName'    => 'home',
                'identityPermissions' => [['post.edit', null, false]],
                'isGranted'           => true,
                'policy'              => GuardInterface::POLICY_DENY
            ],
        ];
    }

    /**
     * @dataProvider routeDataProvider
     */
    public function testRoutePermissionGranted(
        array $rules,
        $matchedRouteName,
        array $identityPermissions,
        $isGranted,
        $protectionPolicy
    ) {
        $routeMatch = new RouteMatch([]);
        $routeMatch->setMatchedRouteName($matchedRouteName);

        $event = new MvcEvent();
        $event->setRouteMatch($routeMatch);

        $authorizationService = $this->getMock('ZfcRbac\Service\AuthorizationServiceInterface', [], [], '', false);
        $authorizationService->expects($this->any())
            ->method('isGranted')
            ->will($this->returnValueMap($identityPermissions));

        $routeGuard = new RoutePermissionsGuard($authorizationService, $rules);
        $routeGuard->setProtectionPolicy($protectionPolicy);

        $this->assertEquals($isGranted, $routeGuard->isGranted($event));
    }

    public function testProperlyFillEventOnAuthorization()
    {
        $eventManager = $this->getMock('Zend\EventManager\EventManagerInterface');

        $application = $this->getMock('Zend\Mvc\Application', [], [], '', false);
        $application->expects($this->never())
            ->method('getEventManager')
            ->will($this->returnValue($eventManager));

        $routeMatch = new RouteMatch([]);
        $routeMatch->setMatchedRouteName('adminRoute');

        $event = new MvcEvent();
        $event->setRouteMatch($routeMatch);
        $event->setApplication($application);

        $authorizationService = $this->getMock('ZfcRbac\Service\AuthorizationServiceInterface', [], [], '', false);
        $authorizationService->expects($this->once())
            ->method('isGranted')
            ->with('post.edit')
            ->will($this->returnValue(true));

        $routeGuard = new RoutePermissionsGuard($authorizationService, [
            'adminRoute' => 'post.edit'
        ]);
        $routeGuard->onResult($event);

        $this->assertEmpty($event->getError());
        $this->assertNull($event->getParam('exception'));
    }

    public function testProperlySetUnauthorizedAndTriggerEventOnUnauthorization()
    {
        $eventManager = $this->getMock('Zend\EventManager\EventManagerInterface');
        $eventManager->expects($this->once())
            ->method('trigger')
            ->with(MvcEvent::EVENT_DISPATCH_ERROR);

        $application = $this->getMock('Zend\Mvc\Application', [], [], '', false);
        $application->expects($this->once())
            ->method('getEventManager')
            ->will($this->returnValue($eventManager));

        $routeMatch = new RouteMatch([]);
        $routeMatch->setMatchedRouteName('adminRoute');

        $event = new MvcEvent();
        $event->setRouteMatch($routeMatch);
        $event->setApplication($application);

        $authorizationService = $this->getMock('ZfcRbac\Service\AuthorizationServiceInterface', [], [], '', false);
        $authorizationService->expects($this->once())
            ->method('isGranted')
            ->with('post.edit')
            ->will($this->returnValue(false));

        $routeGuard = new RoutePermissionsGuard($authorizationService, [
            'adminRoute' => 'post.edit'
        ]);
        $routeGuard->onResult($event);

        $this->assertTrue($event->propagationIsStopped());
        $this->assertEquals(RouteGuard::GUARD_UNAUTHORIZED, $event->getError());
        $this->assertInstanceOf('ZfcRbac\Exception\UnauthorizedException', $event->getParam('exception'));
    }
}
