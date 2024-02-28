<?php

/**
 * StartPage Plugin Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2021.
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

namespace VuFindTest\Sitemap\Plugin;

use VuFind\Sitemap\Plugin\StartPage;

/**
 * StartPage Plugin Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class StartPageTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test defaults.
     *
     * @return void
     */
    public function testDefaults(): void
    {
        // By default, plugin returns nothing and uses 'pages' name.
        $plugin = new StartPage();
        $this->assertEquals('pages', $plugin->getSitemapName());
        $this->assertEquals([], iterator_to_array($plugin->getUrls()));
        $this->assertEquals('', $plugin->getFrequency());
        $this->assertTrue($plugin->supportsVuFindLanguages());
    }

    /**
     * Test behavior with options configured.
     *
     * @return void
     */
    public function testOptions(): void
    {
        // Use anonymous class to test the callable verbose message option:
        $messageCollector = new class () {
            /**
             * Messages collected
             */
            public $messages = [];

            /**
             * Receive a message
             *
             * @param string $msg Message
             *
             * @return void
             */
            public function __invoke($msg)
            {
                $this->messages[] = $msg;
            }
        };
        $plugin = new StartPage();
        $plugin->setOptions(
            [
                'verboseMessageCallback' => $messageCollector,
                'baseUrl' => 'http://foo',
            ]
        );
        $this->assertEquals(['http://foo'], iterator_to_array($plugin->getUrls()));
        $this->assertEquals(
            ['Adding start page http://foo'],
            $messageCollector->messages
        );
    }
}
