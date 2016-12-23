<?php

/**
 * Unit tests for WorldCat utility connector.
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
namespace VuFindTest\Connection;

use VuFind\Connection\WorldCatUtils;

use Zend\Http\Client\Adapter\Test as TestAdapter;
use Zend\Http\Client as HttpClient;

/**
 * Unit tests for WorldCat utility connector.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class WorldCatUtilsTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test related identities
     *
     * @return void
     */
    public function testGetRelatedIdentities()
    {
        $client = $this->getClient('identities');
        $ids = $client->getRelatedIdentities('Clemens, Samuel');
        $this->assertEquals(9, count($ids));
        $this->assertEquals(34, count($ids['Twain, Mark, 1835-1910']));
        $this->assertTrue(in_array('Conjoined twins', $ids['Twain, Mark, 1835-1910']));
    }

    /**
     * Test related identities on an empty string
     *
     * @return void
     */
    public function testGetRelatedIdentitiesEmptyString()
    {
        $client = $this->getClient();
        $this->assertFalse($client->getRelatedIdentities(''));
    }

    /**
     * Load WorldCatUtils client w/ fixture
     *
     * @param string $fixture Fixture name
     * @param bool   $silent  Use silent mode?
     *
     * @return WorldCatUtils
     */
    protected function getClient($fixture = null, $silent = true)
    {
        $client = new HttpClient();
        if (null !== $fixture) {
            $adapter = new TestAdapter();
            $file = realpath(__DIR__ . '/../../../../fixtures/worldcat/' . $fixture);
            $adapter->setResponse(file_get_contents($file));
            $client->setAdapter($adapter);
        }
        return new WorldCatUtils('dummy', $client, $silent);
    }
}
