<?php

/**
 * Email Form Handler Test Class.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Form\Handler;

use GuzzleHttp\Psr7\ServerRequest;
use Symfony\Component\Mime\Address;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Form\Form;
use VuFind\Form\Handler\Email;

/**
 * Email Form Handler Test Class.
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
     * Test extracting user data from the user object.
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
        $request = (new ServerRequest('POST', 'http://localhost'))->withParsedBody([]);
        $this->assertTrue($handler->handle($form, $request, $user));
    }

    /**
     * Test extracting user data from the request.
     *
     * @return void
     */
    public function testExtractDataFromRequest(): void
    {
        $handler = $this->getHandler();
        $form = $this->createMock(Form::class);
        $form->expects($this->once())->method('getRecipient')->willReturn([]);
        $user = $this->createMock(\VuFind\Db\Entity\UserEntityInterface::class);
        $user->expects($this->never())->method('getFirstname');
        $user->expects($this->never())->method('getLastname');
        $user->expects($this->never())->method('getEmail');
        $request = (new ServerRequest('POST', 'http://localhost'))->withParsedBody([
            'name' => 'First Last',
            'email' => 'foo@example.com',
        ]);
        $this->assertTrue($handler->handle($form, $request, $user));
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
        $request = (new ServerRequest('POST', 'http://localhost'))->withParsedBody([]);
        $this->assertTrue($handler->handle($form, $request, $user));
    }

    /**
     * Test success with a user.
     *
     * @return void
     */
    public function testSuccessWithUser(): void
    {
        $handler = $this->getHandler(
            expectedSendCount: 1,
            expectedSendParams: [
                new Address('foo@example.com', 'Foo Tester'),
                new Address('noreply@vufind.org', 'VuFind Feedback'),
                'Subject',
                'message body',
                null,
                null,
                false,
            ]
        );
        $form = $this->createMock(Form::class);
        $form->expects($this->once())->method('getRecipient')
            ->willReturn([
                ['name' => 'Foo Tester', 'email' => 'foo@example.com'],
            ]);
        $form->expects($this->once())->method('getEmailSubject')
            ->willReturn('Subject');
        $user = $this->createMock(UserEntityInterface::class);
        $request = (new ServerRequest('POST', 'http://localhost'))->withParsedBody([]);
        $this->assertTrue($handler->handle($form, $request, $user));
    }

    /**
     * Get a handler configured for testing.
     *
     * @param array $config             Configuration array
     * @param int   $expectedSendCount  Expected number of calls to send an email
     * @param array $expectedSendParams Expected email send params
     *
     * @return Email
     */
    protected function getHandler(array $config = [], int $expectedSendCount = 0, array $expectedSendParams = []): Email
    {
        $renderer = $this->createMock(\Laminas\View\Renderer\RendererInterface::class);
        $renderer->method('render')
            ->willReturn('message body');
        $mailer = $this->createMock(\VuFind\Mailer\Mailer::class);
        $mailer->expects($expectedSendCount ? $this->exactly($expectedSendCount) : $this->never())
            ->method('send')
            ->with(...$expectedSendParams);

        return new Email(
            $renderer,
            new \VuFind\Config\Config($config),
            $mailer
        );
    }
}
