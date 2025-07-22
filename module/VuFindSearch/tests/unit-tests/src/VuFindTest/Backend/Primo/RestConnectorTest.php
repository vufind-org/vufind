<?php

/**
 * Unit tests for Primo REST connector.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010-2025.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindTest\Backend\Primo;

use InvalidArgumentException;
use Laminas\Http\Client\Adapter\Test as TestAdapter;
use Laminas\Http\Client as HttpClient;
use PHPUnit\Framework\TestCase;
use VuFindSearch\Backend\Primo\RestConnector;

/**
 * Unit tests for Primo REST connector.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class RestConnectorTest extends TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Current response.
     *
     * @var string
     */
    protected $response;

    /**
     * Test default timeout value
     *
     * @return void
     */
    public function testInstitutionCode(): void
    {
        $this->assertEquals('fakeinst', $this->createConnector()->getInstitutionCode());
    }

    /**
     * Test that an empty query causes an error.
     *
     * @return void
     */
    public function testEmptyQueryError(): void
    {
        $conn = $this->createConnector();
        $terms = [];
        $result = $conn->query('dummyinst', $terms);
        $this->assertEquals(0, $result['recordCount']);
        $this->assertEquals('empty_search_disallowed', $result['error']);
    }

    /**
     * Create connector with fixture file.
     *
     * @param ?string $fixture Fixture file
     *
     * @return RestConnector
     *
     * @throws InvalidArgumentException Fixture file does not exist
     */
    protected function createConnector(?string $fixture = null): RestConnector
    {
        $fakeUrl = 'http://fakeaddress.none';
        $adapter = new TestAdapter();
        if ($fixture) {
            $this->response
                = $this->getFixture("primo/response/$fixture", 'VuFindSearch');
            $adapter->setResponse($this->response);
        }
        $client = new HttpClient();
        $client->setAdapter($adapter);
        $clientFactory = fn () => $client;
        $session = $this->createMock(\Laminas\Session\Container::class);
        $conn = new RestConnector($fakeUrl, $fakeUrl, 'fakeinst', $clientFactory, $session);
        return $conn;
    }
}
