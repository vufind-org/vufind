<?php

/**
 * Email Form Handler Test Class
 *
 * PHP version 7
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
