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

use Zend\ServiceManager\Exception\ServiceNotCreatedException;
use Zend\ServiceManager\ServiceManager;
use ZfcRbac\Exception\RuntimeException;
use ZfcRbac\Role\RoleProviderPluginManager;

/**
 * @covers \ZfcRbac\Factory\ObjectRepositoryRoleProviderFactory
 */
class ObjectRepositoryRoleProviderFactoryTest extends \PHPUnit_Framework_TestCase
{
    public function testFactoryUsingObjectRepository()
    {
        $pluginManager  = new RoleProviderPluginManager();
        $serviceManager = new ServiceManager();

        $pluginManager->setServiceLocator($serviceManager);

        $options = [
            'role_name_property' => 'name',
            'object_repository'  => 'RoleObjectRepository'
        ];

        $serviceManager->setService('RoleObjectRepository', $this->getMock('Doctrine\Common\Persistence\ObjectRepository'));

        $roleProvider = $pluginManager->get('ZfcRbac\Role\ObjectRepositoryRoleProvider', $options);
        $this->assertInstanceOf('ZfcRbac\Role\ObjectRepositoryRoleProvider', $roleProvider);
    }

    public function testFactoryUsingObjectManager()
    {
        $pluginManager  = new RoleProviderPluginManager();
        $serviceManager = new ServiceManager();

        $pluginManager->setServiceLocator($serviceManager);

        $options = [
            'role_name_property' => 'name',
            'object_manager'     => 'ObjectManager',
            'class_name'         => 'Role'
        ];

        $objectManager = $this->getMock('Doctrine\Common\Persistence\ObjectManager');
        $objectManager->expects($this->once())
                      ->method('getRepository')
                      ->with($options['class_name'])
                      ->will($this->returnValue($this->getMock('Doctrine\Common\Persistence\ObjectRepository')));

        $serviceManager->setService('ObjectManager', $objectManager);

        $roleProvider = $pluginManager->get('ZfcRbac\Role\ObjectRepositoryRoleProvider', $options);
        $this->assertInstanceOf('ZfcRbac\Role\ObjectRepositoryRoleProvider', $roleProvider);
    }

    /**
     * This is required due to the fact that the ServiceManager catches ALL exceptions and throws it's own...
     */
    public function testThrowExceptionIfNoRoleNamePropertyIsSet()
    {
        try {
            $pluginManager  = new RoleProviderPluginManager();
            $serviceManager = new ServiceManager();

            $pluginManager->setServiceLocator($serviceManager);
            $pluginManager->get('ZfcRbac\Role\ObjectRepositoryRoleProvider', []);
        } catch (ServiceNotCreatedException $smException) {
            while ($e = $smException->getPrevious()) {
                if ($e instanceof RuntimeException) {
                    return true;
                }
            }
        }

        $this->fail(
            'ZfcRbac\Factory\ObjectRepositoryRoleProviderFactory::createService() :: '
            .'ZfcRbac\Exception\RuntimeException was not found in the previous Exceptions'
        );
    }

    /**
     * This is required due to the fact that the ServiceManager catches ALL exceptions and throws it's own...
     */
    public function testThrowExceptionIfNoObjectManagerNorObjectRepositoryIsSet()
    {
        try {
            $pluginManager  = new RoleProviderPluginManager();
            $serviceManager = new ServiceManager();

            $pluginManager->setServiceLocator($serviceManager);
            $pluginManager->get('ZfcRbac\Role\ObjectRepositoryRoleProvider', [
                'role_name_property' => 'name'
            ]);
        } catch (ServiceNotCreatedException $smException) {

            while ($e = $smException->getPrevious()) {
                if ($e instanceof RuntimeException) {
                    return true;
                }
            }
        }

        $this->fail(
             'ZfcRbac\Factory\ObjectRepositoryRoleProviderFactory::createService() :: '
            .'ZfcRbac\Exception\RuntimeException was not found in the previous Exceptions'
        );
    }
}
