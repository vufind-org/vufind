<?php
/**
 * GoogleAnalytics view helper Test Class
 *
 * PHP version 7
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\View\Helper\Root;

use VuFind\View\Helper\Root\GoogleAnalytics;

/**
 * GoogleAnalytics view helper Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class GoogleAnalyticsTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ViewTrait;

    /**
     * Test the helper (old mode)
     *
     * @return void
     */
    public function testOldSetup(): void
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
    public function testNewSetup(): void
    {
        $output = $this->renderGA('myfakekey', true);
        $this->assertTrue(false !== strstr($output, 'analytics.js'));
        $this->assertFalse(strstr($output, 'ga.js'));
        $this->assertTrue(false !== strstr($output, 'myfakekey'));
    }

    /**
     * Test custom create options.
     *
     * @return void
     */
    public function testCustomCreateOptions(): void
    {
        $createJs = "{cookieFlags: 'max-age=7200;secure;samesite=none'}";
        $options = [
            'universal' => true,
            'create_options_js' => $createJs
        ];
        $output = $this->renderGA('myfakekey', $options);
        // Confirm that the custom JS appears in the output, and that the
        // default 'auto' does not:
        $this->assertTrue(false !== strstr($output, $createJs));
        $this->assertFalse(strstr($output, "'auto'"));
    }

    /**
     * Test default create options.
     *
     * @return void
     */
    public function testDefaultCreateOptions(): void
    {
        $output = $this->renderGA('myfakekey', true);
        // Confirm that the default JS appears in the output:
        $expectedJs = "ga('create', 'myfakekey', 'auto');";
        $this->assertTrue(false !== strstr($output, $expectedJs));
    }

    /**
     * Test the helper (disabled mode)
     *
     * @return void
     */
    public function testDisabled(): void
    {
        $this->assertEquals('', $this->renderGA(false));
    }

    /**
     * Render the GA code
     *
     * @param string     $key     GA key (false for disabled)
     * @param array|bool $options Options for GA helper
     *
     * @return string
     */
    protected function renderGA(string $key, $options = []): string
    {
        $helper = new GoogleAnalytics($key, $options);
        $helper->setView($this->getPhpRenderer());
        return (string)$helper();
    }
}
