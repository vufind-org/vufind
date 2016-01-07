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

use Zend\Http\Response as HttpResponse;
use Zend\Mvc\MvcEvent;
use ZfcRbac\Exception\UnauthorizedException;
use ZfcRbac\Options\UnauthorizedStrategyOptions;
use ZfcRbac\View\Strategy\UnauthorizedStrategy;

/**
 * @covers \ZfcRbac\View\Strategy\UnauthorizedStrategy
 * @covers \ZfcRbac\View\Strategy\AbstractStrategy
 */
class UnauthorizedStrategyTest extends \PHPUnit_Framework_TestCase
{
    public function testAttachToRightEvent()
    {
        $strategyListener = new UnauthorizedStrategy(new UnauthorizedStrategyOptions());

        $eventManager = $this->getMock('Zend\EventManager\EventManagerInterface');
        $eventManager->expects($this->once())
                     ->method('attach')
                     ->with(MvcEvent::EVENT_DISPATCH_ERROR);

        $strategyListener->attach($eventManager);
    }

    public function testFillEvent()
    {
        $response = new HttpResponse();

        $mvcEvent = new MvcEvent();
        $mvcEvent->setParam('exception', new UnauthorizedException());
        $mvcEvent->setResponse($response);

        $options = new UnauthorizedStrategyOptions([
            'template' => 'error/403'
        ]);

        $unauthorizedStrategy = new UnauthorizedStrategy($options);

        $unauthorizedStrategy->onError($mvcEvent);

        $this->assertEquals(403, $mvcEvent->getResponse()->getStatusCode());
        $this->assertInstanceOf('Zend\View\Model\ModelInterface', $mvcEvent->getResult());
    }
}
