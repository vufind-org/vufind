<?php

/**
 * Unit tests for ProQuestFSG connector.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindTest\Backend\ProQuestFSG;

use Laminas\Http\Client;
use VuFindSearch\Backend\ProQuestFSG\Connector;
use VuFindSearch\ParamBag;

/**
 * Unit tests for ProQuestFSG connector.
 *
 * @category VuFind
 * @package  Search
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class ConnectorTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Test search.
     *
     * @return void
     */
    public function testSearch()
    {
        $responseBody = $this->getFixture('proquestfsg/searchresult.xml');
        $connector = $this->getConnector(
            $this->getMockClient($responseBody)
        );
        $params = new ParamBag([
            'query' => '(cql.serverChoice all "painted pomegranates and needlepoint rabbis")',
            'x-navigators' => 'database',
            'sortKey' => 'relevance',
        ]);
        $searchResult = $connector->search($params, 1, 2);
        $this->assertEquals(1, $searchResult['offset']);
        $this->assertEquals(31, $searchResult['total']);
        $this->assertCount(21, $searchResult['facets']['Databases']);
        $this->assertCount(2, $searchResult['docs']);
    }

    /**
     * Test getRecord.
     *
     * @return void
     */
    public function testGetRecord()
    {
        $responseBody = $this->getFixture('proquestfsg/record.xml');
        $connector = $this->getConnector(
            $this->getMockClient($responseBody)
        );
        $searchResult = $connector->getRecord('2811962947');
        $this->assertEquals(1, $searchResult['offset']);
        $this->assertEquals(1, $searchResult['total']);
        $this->assertCount(2, $searchResult['facets']['Databases']);
        $this->assertCount(1, $searchResult['docs']);
    }

    /**
     * Get a mock HTTP client.
     *
     * @param string $responseBody Response body returned by client.
     *
     * @return MockObject&Client
     */
    protected function getMockClient($responseBody)
    {
        $client = $this->createMock(\Laminas\Http\Client::class);
        $response = $this->createMock(\Laminas\Http\Response::class);
        $response->expects($this->once())->method('isSuccess')
            ->willReturn(true);
        $response->expects($this->once())->method('getBody')
            ->willReturn($responseBody);
        $client->expects($this->once())->method('setMethod')
            ->willReturn($client);
        $client->expects($this->once())->method('send')
            ->willReturn($response);
        return $client;
    }

    /**
     * Get a connector.
     *
     * @param Client $client HTTP client
     *
     * @return Connector
     */
    protected function getConnector($client)
    {
        $connector = new Connector($client);
        return $connector;
    }
}
