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

namespace ZfcRbacTest\Service;

use ZfcRbac\Role\InMemoryRoleProvider;
use ZfcRbac\Service\RoleService;
use Rbac\Traversal\Strategy\RecursiveRoleIteratorStrategy;

/**
 * @covers \ZfcRbac\Service\RoleService
 */
class RoleServiceTest extends \PHPUnit_Framework_TestCase
{
    public function roleProvider()
    {
        return [
            // No identity role
            [
                'rolesConfig' => [],
                'identityRoles' => [],
                'rolesToCheck' => [
                    'member'
                ],
                'doesMatch' => false
            ],

            // Simple
            [
                'rolesConfig' => [
                    'member' => [
                        'children' => ['guest']
                    ],
                    'guest'
                ],
                'identityRoles' => [
                    'guest'
                ],
                'rolesToCheck' => [
                    'member'
                ],
                'doesMatch' => false
            ],
            [
                'rolesConfig' => [
                    'member' => [
                        'children' => ['guest']
                    ],
                    'guest'
                ],
                'identityRoles' => [
                    'member'
                ],
                'rolesToCheck' => [
                    'member'
                ],
                'doesMatch' => true
            ],

            // Complex role inheritance
            [
                'rolesConfig' => [
                    'admin' => [
                        'children' => ['moderator']
                    ],
                    'moderator' => [
                        'children' => ['member']
                    ],
                    'member' => [
                        'children' => ['guest']
                    ],
                    'guest'
                ],
                'identityRoles' => [
                    'member',
                    'moderator'
                ],
                'rolesToCheck' => [
                    'admin'
                ],
                'doesMatch' => false
            ],
            [
                'rolesConfig' => [
                    'admin' => [
                        'children' => ['moderator']
                    ],
                    'moderator' => [
                        'children' => ['member']
                    ],
                    'member' => [
                        'children' => ['guest']
                    ],
                    'guest'
                ],
                'identityRoles' => [
                    'member',
                    'admin'
                ],
                'rolesToCheck' => [
                    'moderator'
                ],
                'doesMatch' => true
            ],

            // Complex role inheritance and multiple check
            [
                'rolesConfig' => [
                    'sysadmin' => [
                        'children' => ['siteadmin', 'admin']
                    ],
                    'siteadmin',
                    'admin' => [
                        'children' => ['moderator']
                    ],
                    'moderator' => [
                        'children' => ['member']
                    ],
                    'member' => [
                        'children' => ['guest']
                    ],
                    'guest'
                ],
                'identityRoles' => [
                    'member',
                    'moderator'
                ],
                'rolesToCheck' => [
                    'admin',
                    'sysadmin'
                ],
                'doesMatch' => false
            ],
            [
                'rolesConfig' => [
                    'sysadmin' => [
                        'children' => ['siteadmin', 'admin']
                    ],
                    'siteadmin',
                    'admin' => [
                        'children' => ['moderator']
                    ],
                    'moderator' => [
                        'children' => ['member']
                    ],
                    'member' => [
                        'children' => ['guest']
                    ],
                    'guest'
                ],
                'identityRoles' => [
                    'moderator',
                    'admin'
                ],
                'rolesToCheck' => [
                    'sysadmin',
                    'siteadmin',
                    'member'
                ],
                'doesMatch' => true
            ]
        ];
    }

    /**
     * @dataProvider roleProvider
     */
    public function testMatchIdentityRoles(array $rolesConfig, array $identityRoles, array $rolesToCheck, $doesMatch)
    {
        $identity = $this->getMock('ZfcRbac\Identity\IdentityInterface');
        $identity->expects($this->once())->method('getRoles')->will($this->returnValue($identityRoles));

        $identityProvider = $this->getMock('ZfcRbac\Identity\IdentityProviderInterface');
        $identityProvider->expects($this->any())
                         ->method('getIdentity')
                         ->will($this->returnValue($identity));

        $roleService = new RoleService($identityProvider, new InMemoryRoleProvider($rolesConfig), new RecursiveRoleIteratorStrategy());

        $this->assertEquals($doesMatch, $roleService->matchIdentityRoles($rolesToCheck));
    }

    public function testReturnGuestRoleIfNoIdentityIsFound()
    {
        $identityProvider = $this->getMock('ZfcRbac\Identity\IdentityProviderInterface');
        $identityProvider->expects($this->any())
                         ->method('getIdentity')
                         ->will($this->returnValue(null));

        $roleService = new RoleService(
            $identityProvider,
            new InMemoryRoleProvider([]),
            $this->getMock('Rbac\Traversal\Strategy\TraversalStrategyInterface')
        );

        $roleService->setGuestRole('guest');

        $result = $roleService->getIdentityRoles();

        $this->assertEquals('guest', $roleService->getGuestRole());
        $this->assertCount(1, $result);
        $this->assertInstanceOf('Rbac\Role\RoleInterface', $result[0]);
        $this->assertEquals('guest', $result[0]->getName());
    }

    public function testThrowExceptionIfIdentityIsWrongType()
    {
        $this->setExpectedException(
            'ZfcRbac\Exception\RuntimeException',
            'ZfcRbac expects your identity to implement ZfcRbac\Identity\IdentityInterface, "stdClass" given'
        );

        $identityProvider = $this->getMock('ZfcRbac\Identity\IdentityProviderInterface');
        $identityProvider->expects($this->any())
                         ->method('getIdentity')
                         ->will($this->returnValue(new \stdClass()));

        $roleService = new RoleService(
            $identityProvider,
            $this->getMock('ZfcRbac\Role\RoleProviderInterface'),
            $this->getMock('Rbac\Traversal\Strategy\TraversalStrategyInterface')
        );

        $roleService->getIdentityRoles();
    }
}
