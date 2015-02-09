<?php

/**
 * Unit tests for Wikipedia connector.
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
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
namespace VuFindTest\Connection;

use VuFind\Connection\Wikipedia;

use Zend\Http\Client\Adapter\Test as TestAdapter;
use Zend\Http\Client as HttpClient;

/**
 * Unit tests for Wikipedia connector.
 *
 * @category VuFind2
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class WikipediaTest extends \PHPUnit_Framework_TestCase
{
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
        $file = realpath(__DIR__ . '/../../../../fixtures/wikipedia/' . $fixture);
        $adapter = new TestAdapter();
        $adapter->setResponse(file_get_contents($file));
        $client = new HttpClient();
        $client->setAdapter($adapter);
        return $client;
    }
}