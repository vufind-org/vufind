<?php

/**
 * HoldsHelper test class.
 *
 * PHP version 8
 *
 * Copyright (C) Moravian Library 2023.
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
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

declare(strict_types=1);

namespace VuFindTest\ActionHelper;

use Laminas\Session\SessionManager;
use PHPUnit\Framework\TestCase;
use VuFind\ActionHelper\ForwardHelper;
use VuFind\ActionHelper\HoldsHelper;
use VuFind\Crypt\HMAC;
use VuFind\Date\Converter as DateConverter;
use VuFind\Http\RouteHelper;
use VuFind\View\FlashMessenger\FlashMessengerInterface;

/**
 * HoldsHelper test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class HoldsHelperTest extends TestCase
{
    /**
     * Test validateIds method.
     *
     * @return void
     */
    public function testValidateIds(): void
    {
        $sessionManager = new SessionManager();
        $plugin = new HoldsHelper(
            $this->createMock(HMAC::class),
            $sessionManager,
            $this->createMock(DateConverter::class),
            $this->createMock(RouteHelper::class),
            $this->createMock(ForwardHelper::class),
            $this->createMock(FlashMessengerInterface::class)
        );
        $plugin->rememberValidId('1');
        $plugin->rememberValidId('2');
        $this->assertTrue($plugin->validateIds(['1', '2']));
        $this->assertTrue($plugin->validateIds(['1']));
        $this->assertFalse($plugin->validateIds(['3']));
        $this->assertFalse($plugin->validateIds(['1', '3']));
    }
}
