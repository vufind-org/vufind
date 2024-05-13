<?php

/**
 * Email Form Handler Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2022.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Form\Handler;

use VuFind\Form\Form;
use VuFind\Form\Handler\Email;

/**
 * Email Form Handler Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class EmailTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\WithConsecutiveTrait;
    use \VuFindTest\Feature\ReflectionTrait;

    /**
     * Test default email values with no configuration specified.
     *
     * @return void
     */
    public function testDefaultEmailBehaviorNoConfig(): void
    {
        $handler = $this->getHandler();
        $form = $this->createMock(Form::class);
        $this->assertEquals(
            ['VuFind Feedback', 'noreply@vufind.org'],
            $this->callMethod($handler, 'getSender', [$form])
        );
    }

    /**
     * Test default email values with configuration specified.
     *
     * @return void
     */
    public function testDefaultEmailBehaviorWithConfig(): void
    {
        $handler = $this->getHandler(
            [
                'Feedback' => [
                    'sender_email' => 'foo@example.com',
                    'sender_name' => 'Bar',
                ],
            ]
        );
        $form = $this->createMock(Form::class);
        $this->assertEquals(
            ['Bar', 'foo@example.com'],
            $this->callMethod($handler, 'getSender', [$form])
        );
    }

    /**
     * Test user object handling.
     *
     * @return void
     */
    public function testExtractDataFromUserObject(): void
    {
        $handler = $this->getHandler();
        $form = $this->createMock(Form::class);
        $form->expects($this->once())->method('getRecipient')->willReturn([]);
        $user = $this->createMock(\VuFind\Db\Entity\UserEntityInterface::class);
        $user->expects($this->once())->method('getFirstname')->willReturn('First');
        $user->expects($this->once())->method('getLastname')->willReturn('Last');
        $user->expects($this->once())->method('getEmail')->willReturn('foo@example.com');
        $params = $this->createMock(\Laminas\Mvc\Controller\Plugin\Params::class);
        $this->expectConsecutiveCalls(
            $params,
            'fromPost',
            [
                [null],
                ['name', 'First Last'],
                ['email', 'foo@example.com'],
            ],
            [
                [], 'First Last', 'foo@example.com',
            ]
        );
        $this->assertTrue($handler->handle($form, $params, $user));
    }

    /**
     * Test absent user object handling.
     *
     * @return void
     */
    public function testHandleMissingUserObject(): void
    {
        $handler = $this->getHandler();
        $form = $this->createMock(Form::class);
        $form->expects($this->once())->method('getRecipient')->willReturn([]);
        $user = null;
        $params = $this->createMock(\Laminas\Mvc\Controller\Plugin\Params::class);
        $this->expectConsecutiveCalls(
            $params,
            'fromPost',
            [
                [null],
                ['name', null],
                ['email', null],
            ],
            [
                [], null, null,
            ]
        );
        $this->assertTrue($handler->handle($form, $params, $user));
    }

    /**
     * Get a handler configured for testing.
     *
     * @param array $config Configuration array
     *
     * @return Email
     */
    protected function getHandler(array $config = []): Email
    {
        return new Email(
            $this->createMock(\Laminas\View\Renderer\RendererInterface::class),
            new \Laminas\Config\Config($config),
            $this->createMock(\VuFind\Mailer\Mailer::class)
        );
    }
}
