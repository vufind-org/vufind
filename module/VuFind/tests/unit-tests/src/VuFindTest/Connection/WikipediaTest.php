<?php

/**
 * Unit tests for Wikipedia connector.
 *
 * PHP version 8
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
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindTest\Connection;

use Laminas\Http\Client\Adapter\Test as TestAdapter;
use Laminas\Http\Client as HttpClient;
use VuFind\Connection\Wikipedia;

/**
 * Unit tests for Wikipedia connector.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class WikipediaTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Test processing of English-language Jane Austen entry.
     *
     * @return void
     */
    public function testEnglishJane()
    {
        $client = $this->getClient('en-austen');
        $wiki = new Wikipedia($client);
        $response = $wiki->get('Jane Austen');
        $this->assertEquals('en', $response['wiki_lang']);
        $this->assertEquals('Jane Austen', $response['name']);
        $this->assertTrue(false !== strstr($response['description'], '16 December 1775'));
    }

    /**
     * Load HTTP client w/ fixture
     *
     * @param string $fixture Fixture name
     *
     * @return HttpClient
     */
    protected function getClient($fixture)
    {
        $adapter = new TestAdapter();
        $adapter->setResponse($this->getFixture("wikipedia/$fixture"));
        $client = new HttpClient();
        $client->setAdapter($adapter);
        return $client;
    }
}
