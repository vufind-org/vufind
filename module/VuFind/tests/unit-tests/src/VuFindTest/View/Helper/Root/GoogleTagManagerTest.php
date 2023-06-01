<?php

/**
 * GoogleTagManager view helper Test Class
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
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\View\Helper\Root;

use VuFind\View\Helper\Root\GoogleTagManager;

/**
 * GoogleTagManager view helper Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class GoogleTagManagerTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ViewTrait;

    /**
     * Test getHeadCode()
     *
     * @return void
     */
    public function testHeadCode(): void
    {
        $output = $this->renderGTMHeadCode('fakeContainerId');
        $this->assertTrue(false !== strstr($output, 'gtm.js'));
        $this->assertTrue(false !== strstr($output, 'fakeContainerId'));
    }

    /**
     * Test getBodyCode()
     *
     * @return void
     */
    public function testBodyCode(): void
    {
        $output = $this->renderGTMBodyCode('fakeContainerId');
        $this->assertTrue(false !== strstr($output, 'ns.html'));
        $this->assertTrue(false !== strstr($output, 'fakeContainerId'));
    }

    /**
     * Test the helper (disabled mode)
     *
     * @return void
     */
    public function testDisabled(): void
    {
        $this->assertEquals('', $this->renderGTMHeadCode(false));
        $this->assertEquals('', $this->renderGTMBodyCode(false));
    }

    /**
     * Render the GTM Head element code
     *
     * @param string $gtmContainerId GTM Container ID (false for disabled)
     *
     * @return string
     */
    protected function renderGTMHeadCode(string $gtmContainerId): string
    {
        $helper = new GoogleTagManager($gtmContainerId);
        $helper->setView($this->getPhpRenderer());
        return (string)$helper->getHeadCode();
    }

    /**
     * Render the GTM Body element code
     *
     * @param string $gtmContainerId GTM Container ID (false for disabled)
     *
     * @return string
     */
    protected function renderGTMBodyCode(string $gtmContainerId): string
    {
        $helper = new GoogleTagManager($gtmContainerId);
        $helper->setView($this->getPhpRenderer());
        return (string)$helper->getBodyCode();
    }
}
