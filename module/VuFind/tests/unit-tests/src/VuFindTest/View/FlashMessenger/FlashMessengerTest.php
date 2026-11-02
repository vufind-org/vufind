<?php

/**
 * Flash messenger test.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2026.
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

namespace VuFindTest\View\Helper\Root;

use Closure;
use Laminas\Session\Container;
use VuFind\View\FlashMessenger\FlashMessenger;
use VuFind\View\FlashMessenger\FlashMessengerInterface;

/**
 * Flash messenger test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class FlashMessengerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Generate a set of messages for testing.
     *
     * @param string $prefix Prefix to include on messages
     * @param int    $count  How many messages to generate (even will be strings, odd arrays)
     *
     * @return array<string|array>
     */
    protected function generateMessages(string $prefix, int $count): array
    {
        $messages = [];
        for ($i = 0; $i < $count; $i++) {
            $messages[] = ($i % 2 == 0) ? "$prefix $i" : ['msg' => "$prefix $i"];
        }
        return $messages;
    }

    /**
     * Add messages to a flash messenger.
     *
     * @param FlashMessengerInterface $messenger Flash messenger
     * @param array                   $error     Error messages to add
     * @param array                   $info      Info messages to add
     * @param array                   $success   Success messages to add
     * @param array                   $warning   Warning messages to add
     * @param array                   $custom    Custom messages to add
     *
     * @return void
     */
    protected function populateMessenger(
        FlashMessengerInterface $messenger,
        array $error = [],
        array $info = [],
        array $success = [],
        array $warning = [],
        array $custom = []
    ): void {
        foreach (compact('error', 'info', 'success', 'warning', 'custom') as $type => $messages) {
            foreach ($messages as $message) {
                switch ($type) {
                    case 'error':
                    case 'info':
                    case 'success':
                    case 'warning':
                        $method = 'add' . ucwords($type) . 'Message';
                        $messenger->$method($message);
                        break;
                    default:
                        $messenger->addMessage($message, $type);
                        break;
                }
            }
        }
    }

    /**
     * Test fetching from an undefined namespace.
     *
     * @return void
     */
    public function testUndefinedNamespace(): void
    {
        $messenger = new FlashMessenger(Closure::fromCallable(fn () => new Container()));
        $this->assertSame([], $messenger->getMessages('undefined'));
    }

    /**
     * Test setters and getters.
     *
     * @return void
     */
    public function testSettersAndGetters(): void
    {
        $messenger = new FlashMessenger(Closure::fromCallable(fn () => new Container()));
        $error = $this->generateMessages('error', 5);
        $info = $this->generateMessages('info', 5);
        $success = $this->generateMessages('success', 5);
        $warning = $this->generateMessages('warning', 5);
        $custom = $this->generateMessages('custom', 5);

        // Set all types of messages:
        $this->populateMessenger(
            $messenger,
            $error,
            $info,
            $success,
            $warning,
            $custom
        );
        $this->assertSame($error, $messenger->getErrorMessages());
        $this->assertSame($info, $messenger->getInfoMessages());
        $this->assertSame($success, $messenger->getSuccessMessages());
        $this->assertSame($warning, $messenger->getWarningMessages());
        $this->assertSame($custom, $messenger->getMessages('custom'));

        // Clear one type of message:
        $messenger->clearMessages('error');
        $this->assertSame([], $messenger->getErrorMessages());
        $this->assertSame($info, $messenger->getInfoMessages());
        $this->assertSame($success, $messenger->getSuccessMessages());
        $this->assertSame($warning, $messenger->getWarningMessages());
        $this->assertSame($custom, $messenger->getMessages('custom'));

        // Clear all messages:
        $messenger->clearAllMessages();
        $this->assertSame([], $messenger->getErrorMessages());
        $this->assertSame([], $messenger->getInfoMessages());
        $this->assertSame([], $messenger->getSuccessMessages());
        $this->assertSame([], $messenger->getWarningMessages());
        $this->assertSame([], $messenger->getMessages('custom'));
    }
}
