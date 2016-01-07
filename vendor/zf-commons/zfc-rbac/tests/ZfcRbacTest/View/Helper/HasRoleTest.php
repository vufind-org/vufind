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

namespace ZfcRbacTest\View\Helper;

use ZfcRbac\View\Helper\HasRole;
use ZfcRbacTest\Util\ServiceManagerFactory;

/**
 * @covers \ZfcRbac\View\Helper\HasRole
 */
class HasRoleTest extends \PHPUnit_Framework_TestCase
{
    public function testHelperIsRegistered()
    {
        $serviceManager = ServiceManagerFactory::getServiceManager();
        $config = $serviceManager->get('Config');
        $this->assertArrayHasKey('view_helpers', $config);
        $viewHelpersConfig = $config['view_helpers'];
        $this->assertEquals('ZfcRbac\View\Helper\HasRole', $viewHelpersConfig['aliases']['hasRole']);
        $this->assertEquals(
            'ZfcRbac\Factory\HasRoleViewHelperFactory',
            $viewHelpersConfig['factories']['ZfcRbac\View\Helper\HasRole']
        );
    }

    public function testCallAuthorizationService()
    {
        $rolesConfig = [
            ['member', true],
            [['member'], true],
        ];

        $authorizationService = $this->getMock('ZfcRbac\Service\RoleService', [], [], '', false);
        $authorizationService->expects($this->any())
            ->method('matchIdentityRoles')
            ->will($this->returnValueMap($rolesConfig));

        $helper = new HasRole($authorizationService);

        $this->assertTrue($helper('member'));
        $this->assertTrue($helper(['member']));
    }
}
