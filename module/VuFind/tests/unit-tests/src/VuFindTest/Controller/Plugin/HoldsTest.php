<?php

/**
 * Holds controller plugin test class
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

declare(strict_types=1);

namespace VuFindTest\Controller\Plugin;

use Laminas\Session\SessionManager;
use VuFind\Controller\Plugin\Holds;
use VuFind\Crypt\HMAC;
use VuFind\Date\Converter as DateConverter;

/**
 * Class HoldsTest
 *
 * @category VuFind
 * @package  Tests
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class HoldsTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Mock container
     *
     * @var \VuFindTest\Container\MockContainer
     */
    protected $container;

    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->container = new \VuFindTest\Container\MockContainer($this);
    }

    /**
     * Test validateIds method
     *
     * @return void
     */
    public function testValidateIds()
    {
        $hmac = $this->container->createMock(HMAC::class);
        $sessionManager = new SessionManager();
        $dateConverter = $this->container->createMock(DateConverter::class);
        $plugin = new Holds($hmac, $sessionManager, $dateConverter);
        $plugin->rememberValidId('1');
        $plugin->rememberValidId('2');
        $this->assertTrue($plugin->validateIds(['1', '2']));
        $this->assertTrue($plugin->validateIds(['1']));
        $this->assertFalse($plugin->validateIds(['3']));
        $this->assertFalse($plugin->validateIds(['1', '3']));
    }
}
