<?php

/**
 * UrlHelper test class.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2026.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\ActionHelper;

use PHPUnit\Framework\TestCase;
use VuFind\ActionHelper\UrlHelper;
use VuFind\Http\ServerUrlHelper;
use VuFindTest\Feature\AutowireTrait;

/**
 * UrlHelper test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class UrlHelperTest extends TestCase
{
    use AutowireTrait;

    /**
     * Test the isLocalUrl method.
     *
     * @return void
     */
    public function testIsLocalUrl(): void
    {
        $serverUrlHelper = $this->createMock(ServerUrlHelper::class);
        $serverUrlHelper->expects($this->once())
            ->method('getUrlForPath')
            ->willReturn('http://vufind.org/');

        $helper = $this->getAutowiredObject(
            UrlHelper::class,
            [
                ServerUrlHelper::class => $serverUrlHelper,
            ]
        );

        // Check each address twice to ensure that caching works:
        $this->assertTrue($helper->isLocalUrl('http://vufind.org/'));
        $this->assertTrue($helper->isLocalUrl('http://vufind.org/'));
        $this->assertTrue($helper->isLocalUrl('http://vufind.org/foo'));
        $this->assertTrue($helper->isLocalUrl('http://vufind.org/foo'));
        $this->assertFalse($helper->isLocalUrl('http://vufind.com/foo'));
        $this->assertFalse($helper->isLocalUrl('http://vufind.com/foo'));
    }
}
