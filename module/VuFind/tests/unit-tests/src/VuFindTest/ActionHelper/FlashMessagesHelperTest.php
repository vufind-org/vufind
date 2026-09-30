<?php

/**
 * FlashMessagesHelper test class.
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
 * @author   Emmanuel Afuadajo <afuadajoe@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\ActionHelper;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\View\FlashMessenger\FlashMessenger;

/**
 * FlashMessagesHelper test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Emmanuel Afuadajo <afuadajoe@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class FlashMessagesHelperTest extends TestCase
{
    /**
     * Test that getFlashMessenger() returns the injected messenger.
     *
     * @return void
     */
    public function testGetFlashMessenger(): void
    {
        $messenger = $this->createMock(FlashMessenger::class);
        $helper = new FlashMessagesHelper($messenger);
        $this->assertSame($messenger, $helper->getFlashMessenger());
    }

    /**
     * Data provider for testAddMessageDelegates().
     *
     * @return \Iterator
     */
    public static function addMessageProvider(): \Iterator
    {
        yield 'error, string' => ['addErrorMessage', 'addErrorMessage', 'an error'];
        yield 'error, array' => ['addErrorMessage', 'addErrorMessage', ['msg' => 'an error', 'tokens' => []]];
        yield 'info, string' => ['addInfoMessage', 'addInfoMessage', 'some info'];
        yield 'info, array' => ['addInfoMessage', 'addInfoMessage', ['msg' => 'some info', 'tokens' => []]];
        yield 'success, string' => ['addSuccessMessage', 'addSuccessMessage', 'success'];
        yield 'success, array' => ['addSuccessMessage', 'addSuccessMessage', ['msg' => 'success', 'tokens' => []]];
        yield 'warning, string' => ['addWarningMessage', 'addWarningMessage', 'a warning'];
        yield 'warning, array' => ['addWarningMessage', 'addWarningMessage', ['msg' => 'a warning', 'tokens' => []]];
    }

    /**
     * Test that each add method delegates to the matching messenger method with the provided message.
     *
     * @param string       $helperMethod    Method to call on the helper
     * @param string       $messengerMethod Method expected to be called on the messenger
     * @param string|array $message         Message to pass through
     *
     * @return void
     */
    #[DataProvider('addMessageProvider')]
    public function testAddMessageDelegates(string $helperMethod, string $messengerMethod, string|array $message): void
    {
        $messenger = $this->createMock(FlashMessenger::class);
        $messenger->expects($this->once())->method($messengerMethod)->with($message);

        $helper = new FlashMessagesHelper($messenger);
        $helper->$helperMethod($message);
    }
}
