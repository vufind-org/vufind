<?php

/**
 * GoogleAnalytics view helper Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010-2023.
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
     * Test the helper (basic setup)
     *
     * @return void
     */
    public function testBasicSetup(): void
    {
        $expectedUrl = 'https&#x3A;&#x2F;&#x2F;www.googletagmanager.com&#x2F;gtag&#x2F;js&#x3F;id&#x3D;myfakekey';
        $expected = <<<JS
            <script type="text&#x2F;javascript" async="async" src="$expectedUrl"></script>
            <script type="text&#x2F;javascript">
                //<!--
                window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', 'myfakekey', 'auto');
                //-->
            </script>
            JS;
        $this->assertEquals($expected, $this->renderGA('myfakekey'));
    }

    /**
     * Test custom create options.
     *
     * @return void
     */
    public function testCustomCreateOptions(): void
    {
        $createJs = "{cookie_flags: 'max-age=7200;secure;samesite=none'}";
        $options = [
            'universal' => true,
            'create_options_js' => $createJs,
        ];
        $expectedUrl = 'https&#x3A;&#x2F;&#x2F;www.googletagmanager.com&#x2F;gtag&#x2F;js&#x3F;id&#x3D;myfakekey';
        $expected = <<<JS
            <script type="text&#x2F;javascript" async="async" src="$expectedUrl"></script>
            <script type="text&#x2F;javascript">
                //<!--
                window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', 'myfakekey', {cookie_flags: 'max-age=7200;secure;samesite=none'});
                //-->
            </script>
            JS;
        $this->assertEquals($expected, $this->renderGA('myfakekey', $options));
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
     * @param string $key     GA key (false for disabled)
     * @param array  $options Options for GA helper
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
