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

namespace ZfcRbacTest;

use ZfcRbac\Module;

/**
 * @covers \ZfcRbac\Module
 */
class ModuleTest extends \PHPUnit_Framework_TestCase
{
    public function testConfigIsArray()
    {
        $module = new Module();
        $this->assertInternalType('array', $module->getConfig());
    }

    public function testCanRegisterGuards()
    {
        $module         = new Module();
        $mvcEvent       = $this->getMock('Zend\Mvc\MvcEvent');
        $application    = $this->getMock('Zend\Mvc\Application', [], [], '', false);
        $eventManager   = $this->getMock('Zend\EventManager\EventManagerInterface');
        $serviceManager = $this->getMock('Zend\ServiceManager\ServiceManager');

        $mvcEvent->expects($this->once())->method('getTarget')->will($this->returnValue($application));
        $application->expects($this->once())->method('getEventManager')->will($this->returnValue($eventManager));
        $application->expects($this->once())->method('getServiceManager')->will($this->returnValue($serviceManager));

        $guards = [
            $this->getMock('ZfcRbac\Guard\GuardInterface')
        ];

        $serviceManager->expects($this->once())
                       ->method('get')
                       ->with('ZfcRbac\Guards')
                       ->will($this->returnValue($guards));

        $module->onBootstrap($mvcEvent);
    }
}
