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

namespace ZfcRbacTest\Factory;

use Zend\ServiceManager\ServiceManager;
use ZfcRbac\Factory\RoutePermissionsGuardFactory;
use ZfcRbac\Guard\GuardInterface;
use ZfcRbac\Guard\GuardPluginManager;
use ZfcRbac\Options\ModuleOptions;

/**
 * @covers \ZfcRbac\Factory\RoutePermissionsGuardFactory
 */
class RoutePermissionsGuardFactoryTest extends \PHPUnit_Framework_TestCase
{
    public function testFactory()
    {
        $creationOptions = [
            'route' => 'role'
        ];

        $options = new ModuleOptions([
            'identity_provider' => 'ZfcRbac\Identity\AuthenticationProvider',
            'guards'            => [
                'ZfcRbac\Guard\RoutePermissionsGuard' => $creationOptions
            ],
            'protection_policy' => GuardInterface::POLICY_ALLOW,
        ]);

        $serviceManager = new ServiceManager();
        $serviceManager->setService('ZfcRbac\Options\ModuleOptions', $options);
        $serviceManager->setService(
            'ZfcRbac\Service\AuthorizationService',
            $this->getMock('ZfcRbac\Service\AuthorizationService', [], [], '', false)
        );

        $pluginManager = new GuardPluginManager();
        $pluginManager->setServiceLocator($serviceManager);

        $factory    = new RoutePermissionsGuardFactory();
        $routeGuard = $factory->createService($pluginManager);

        $this->assertInstanceOf('ZfcRbac\Guard\RoutePermissionsGuard', $routeGuard);
        $this->assertEquals(GuardInterface::POLICY_ALLOW, $routeGuard->getProtectionPolicy());
    }
}
