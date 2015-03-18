<?php
/**
 * ILS driver test
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2011.
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
 * @author   Jochen Lienhard <lienhard@ub.uni-freiburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFindTest\ILS\Driver;
use VuFind\ILS\Driver\DAIA;

use Zend\Http\Client\Adapter\Test as TestAdapter;
use Zend\Http\Client as HttpClient;

use PHPUnit_Framework_TestCase;
use InvalidArgumentException;

/**
 * ILS driver test
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class DAIATest extends \VuFindTest\Unit\ILSDriverTestCase
{

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->driver = new DAIA();
    }


    /**
     * Test that dummy history.
     *
     * @return void
     */
    public function testgetPurchaseHistory()
    {

    }

    /**
     * Test 
     *
     * @return void
     */
    public function testJSONgetStatus()
    {
        $conn = $this->createConnector('daiajson');
        $conn->setConfig(['DAIA' => ['responseFormat' => 'json', 'baseUrl' => 'http://daia.example.org']]);
        $conn->init();
        //$result = $conn->getStatus('123456');
        $this->assertEquals("test", "bla");
    }

    /**
     * Test 
     *
     * @return void
     */
/*    public function testXMLgetStatus()
    {
       $conn = $this->createConnector('daiaxml');
       $conn->setConfig(['DAIA' => ['responseFormat' => 'xml', 'baseUrl' => 'http://daia.example.org']]);
       $conn->init();

        $result = $conn->getStatus('123456');
        $this->assertEquals("test", $result['id']);
    }
*/

    /**
     * Create connector with fixture file.
     *
     * @param string $fixture Fixture file
     *
     * @return Connector
     *
     * @throws InvalidArgumentException Fixture file does not exist
     */
    protected function createConnector($fixture = null)
    {
        $adapter = new TestAdapter();
        if ($fixture) {
            $file = realpath(__DIR__ . '/../../../../../../tests/fixtures/daia/response/' . $fixture);
            if (!is_string($file) || !file_exists($file) || !is_readable($file)) {
                throw new InvalidArgumentException(sprintf('Unable to load fixture file: %s ', $file));
            }
            $response = file_get_contents($file);
            $adapter->setResponse($response);
        }
        $client = new HttpClient();
        $client->setAdapter($adapter);
        $conn = new DAIA($client);
        return $conn;
    }
}
