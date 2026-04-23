<?php

/**
 * Mink test class for web analytics tools.
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
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Mink;

use VuFind\Http\GuzzleService;

/**
 * Mink test class for web analytics tools.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class AnalyticsTest extends \VuFindTest\Integration\MinkTestCase
{
    /**
     * Data provider for testTitleSearchNormalization.
     *
     * @return \Iterator
     */
    public static function analyticsProvider(): \Iterator
    {
        $fakeJs = '{fakety: "fakefake"}';
        yield 'Google analytics loads on home page' => [
            '/',
            ['config' => ['GoogleAnalytics' => ['apiKey' => 'testGAid', 'create_options_js' => $fakeJs]]],
            ['testGAid', $fakeJs],
        ];
        yield 'Google tag manager loads on home page' => [
            '/',
            ['config' => ['GoogleTagManager' => ['gtmContainerId' => 'testGTMid']]],
            ['testGTMid'],
        ];
        $basicMatomoConfig = ['config' => ['Matomo' => ['url' => 'http://fakeMatomo', 'site_id' => 987654321]]];
        $customVariablesMatomoConfig = [
            'config' => ['Matomo' => $basicMatomoConfig['config']['Matomo'] + ['custom_variables' => true]],
        ];
        yield 'Matomo loads on home page (no custom variables by default)' => [
            '/',
            $basicMatomoConfig,
            ['fakeMatomo', '987654321'],
            ['setCustomVariable'],
        ];
        yield 'Matomo loads on home page with custom variables enabled' => [
            '/',
            $customVariablesMatomoConfig,
            ['fakeMatomo', '987654321', "'setCustomVariable',1,'Context'"],
        ];
        yield 'Matomo tracks search on search page (no custom variables by default)' => [
            '/Search/Results?lookfor=foo',
            $basicMatomoConfig,
            [
                'fakeMatomo',
                '987654321',
                "'trackSiteSearch', 'Solr|foo', 'basic'",
            ],
            ['setCustomVariable'],
        ];
        yield 'Matomo tracks search on search page (with custom variables enabled)' => [
            '/Search/Results?lookfor=foo',
            $customVariablesMatomoConfig,
            [
                'fakeMatomo',
                '987654321',
                "'trackSiteSearch', 'Solr|foo', 'basic'",
                "'setCustomVariable',1,'Facets','','page'",
                "'setCustomVariable',2,'FacetTypes','','page'",
                "'setCustomVariable',3,'SearchType','basic','page'",
                "'setCustomVariable',4,'SearchBackend','Solr','page'",
                "'setCustomVariable',5,'Sort','relevance','page'",
                "'setCustomVariable',6,'Page','1','page'",
                "'setCustomVariable',7,'Limit','20','page'",
                "'setCustomVariable',8,'View','list','page'",
                "'setCustomVariable',9,'Context','page','page'",
            ],
        ];
        yield 'Matomo tracks search on faceted search page (with custom variables enabled)' => [
            '/Search/Results?lookfor=foo&filter=format:Book',
            $customVariablesMatomoConfig,
            [
                'fakeMatomo',
                '987654321',
                "'trackSiteSearch', 'Solr|foo', 'basic'",
                "'setCustomVariable',1,'Facets','format\\x7CBook','page'",
                "'setCustomVariable',2,'FacetTypes','Format','page'",
                "'setCustomVariable',3,'SearchType','basic','page'",
                "'setCustomVariable',4,'SearchBackend','Solr','page'",
                "'setCustomVariable',5,'Sort','relevance','page'",
                "'setCustomVariable',6,'Page','1','page'",
                "'setCustomVariable',7,'Limit','20','page'",
                "'setCustomVariable',8,'View','list','page'",
                "'setCustomVariable',9,'Context','page','page'",
            ],
        ];
        yield 'Matomo works on record page (no custom variables by default)' => [
            '/Record/testbug2',
            $basicMatomoConfig,
            ['fakeMatomo', '987654321'],
            ['setCustomVariable'],
        ];
        yield 'Matomo provides appropriate custom variables on record page (when enabled)' => [
            '/Record/testbug2',
            $customVariablesMatomoConfig,
            [
                'fakeMatomo',
                '987654321',
                "'setCustomVariable',1,'Context','page','page'",
                "'setCustomVariable',2,'RecordFormat','Book','page'",
                "'setCustomVariable',3,'RecordData','testbug2",
                "'setCustomVariable',4,'RecordInstitution','MyInstitution','page'",
            ],
        ];
        yield 'Matomo works on AJAX-loaded record tab (no custom variables by default)' => [
            '/Record/testbug2/AjaxTab',
            $basicMatomoConfig,
            ['fakeMatomo', '987654321'],
            ['setCustomVariable'],
        ];
        yield 'Matomo provides appropriate custom variables on AJAX-loaded record tab (when enabled)' => [
            '/Record/testbug2/AjaxTab',
            $customVariablesMatomoConfig,
            [
                'fakeMatomo',
                '987654321',
                "'setCustomVariable',1,'Context','tabs','page'",
                "'setCustomVariable',2,'RecordFormat','Book','page'",
                "'setCustomVariable',3,'RecordData','testbug2",
                "'setCustomVariable',4,'RecordInstitution','MyInstitution','page'",
            ],
        ];
    }

    /**
     * Test that web analytics helpers insert expected text into pages.
     *
     * @param string   $path           Path to add to base URL
     * @param array    $configs        Configs to change
     * @param string[] $expectedText   Array of strings to look for in the resulting HTML
     * @param string[] $unexpectedText Array of strings NOT expected in the resulting HTML
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('analyticsProvider')]
    public function testAnalytics(string $path, array $configs, array $expectedText, array $unexpectedText = []): void
    {
        $this->changeConfigs($configs);
        // We don't want to actually send data to analytics providers, so let's not use browser-based testing;
        // instead, we'll just fetch the raw HTML and make sure that the appropriate code has triggered to
        // insert the appropriate values.
        $http = new GuzzleService([]);
        $response = $http->get($this->getVuFindUrl($path));
        $body = (string)$response->getBody();
        foreach ($expectedText as $expected) {
            $this->assertStringContainsString($expected, $body, "Could not find $expected at $path");
        }
        foreach ($unexpectedText as $unexpected) {
            $this->assertStringNotContainsString($unexpected, $body, "Unexpectedly found $unexpected at $path");
        }
    }

    /**
     * Standard teardown method.
     *
     * @return void
     */
    public function tearDown(): void
    {
        // This test uses tools from the Mink base class but doesn't actually use Mink; thus, we only need to
        // clean up custom configurations in the tearDown method:
        $this->restoreConfigs();
    }
}
