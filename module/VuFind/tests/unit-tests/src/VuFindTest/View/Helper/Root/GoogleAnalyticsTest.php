<?php
/**
 * GoogleAnalytics view helper Test Class
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
namespace VuFindTest\View\Helper\Root;
use VuFind\View\Helper\Root\GoogleAnalytics;

/**
 * GoogleAnalytics view helper Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class GoogleAnalyticsTest extends \VuFindTest\Unit\ViewHelperTestCase
{
    /**
     * Test the helper (old mode)
     *
     * @return void
     */
    public function testOldSetup()
    {
        $output = $this->renderGA('myfakekey', false);
        $this->assertTrue(false !== strstr($output, 'ga.js'));
        $this->assertFalse(strstr($output, 'analytics.js'));
        $this->assertTrue(false !== strstr($output, 'myfakekey'));
    }

    /**
     * Test the helper (Universal Analytics mode)
     *
     * @return void
     */
    public function testNewSetup()
    {
        $output = $this->renderGA('myfakekey', true);
        $this->assertTrue(false !== strstr($output, 'analytics.js'));
        $this->assertFalse(strstr($output, 'ga.js'));
        $this->assertTrue(false !== strstr($output, 'myfakekey'));
    }

    /**
     * Test the helper (disabled mode)
     *
     * @return void
     */
    public function testDisabled()
    {
        $this->assertEquals('', $this->renderGA(false));
    }

    /**
     * Render the GA code
     *
     * @param string $key GA key (false for disabled)
     * @param bool   $uni Universal mode?
     *
     * @return string
     */
    protected function renderGA($key, $uni = false)
    {
        $helper = new GoogleAnalytics($key, $uni);
        $helper->setView($this->getPhpRenderer());
        return (string)$helper();
    }
}