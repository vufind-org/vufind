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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
namespace VuFindTest\Connection;

use VuFind\Connection\WorldCatUtils;

use Zend\Http\Client\Adapter\Test as TestAdapter;
use Zend\Http\Client as HttpClient;

/**
 * Unit tests for WorldCat utility connector.
 *
 * @category VuFind2
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class WorldCatUtilsTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test XISBN processing.
     *
     * @return void
     */
    public function testXISBN()
    {
        $client = $this->getClient('xisbn');
        $result = $client->getXISBN('0140435123');
        $this->assertEquals(82, count($result));
        $this->assertTrue(in_array('0140435123', $result));
        $this->assertTrue(in_array('1848378912', $result));
    }

    /**
     * Test XISSN processing.
     *
     * @return void
     */
    public function testXISSN()
    {
        $client = $this->getClient('xissn');
        $result = $client->getXISSN('0362-4331');
        $this->assertEquals(4, count($result));
        $this->assertTrue(in_array('0362-4331', $result));
        $this->assertTrue(in_array('1542-667X', $result));
    }

    /**
     * Test XOCLCNUM processing.
     *
     * @return void
     */
    public function testXOCLCNUM()
    {
        $client = $this->getClient('xoclcnum');
        $result = $client->getXOCLCNUM('42371494');
        $this->assertEquals(568, count($result));
        $this->assertTrue(in_array('42371494', $result));
        $this->assertTrue(in_array('1710732', $result));
    }

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
     * Test related terminology
     *
     * @return void
     */
    public function testGetRelatedTerms()
    {
        $client = $this->getClient('terms');
        $terms = $client->getRelatedTerms('hogs');
        $this->assertEquals(4, count($terms['exact']));
        $this->assertEquals(7, count($terms['broader']));
        $this->assertEquals(4, count($terms['narrower']));
        $this->assertTrue(in_array('Construction workers', $terms['broader']));
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