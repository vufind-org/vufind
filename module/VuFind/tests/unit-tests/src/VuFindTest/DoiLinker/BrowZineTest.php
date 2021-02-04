<?php
/**
 * BrowZine Test Class
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2020.
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
namespace VuFindTest\DoiLinker;

use VuFind\DoiLinker\BrowZine;
use VuFindSearch\Backend\BrowZine\Connector;

/**
 * BrowZine Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class BrowZineTest extends \VuFindTest\Unit\TestCase
{
    use \VuFindTest\Unit\FixtureTrait;

    /**
     * Get a mock connector
     *
     * @param string $doi      DOI expected by connector
     * @param array  $response Response for connector to return
     *
     * @return void
     */
    protected function getMockConnector($doi, $response)
    {
        $connector = $this->getMockBuilder(Connector::class)
            ->disableOriginalConstructor()
            ->getMock();
        $connector->expects($this->once())
            ->method('lookupDoi')
            ->with($this->equalTo($doi))
            ->will($this->returnValue($response));
        return $connector;
    }

    /**
     * Test an API response.
     *
     * @return void
     */
    public function testApiSuccess()
    {
        $rawData = $this->getJsonFixture('browzine/doi.json');
        $testData = [
            [
                'config' => [],
                'response' => [
                    '10.1155/2020/8690540' => [
                        [
                            'link' => 'https://weblink',
                            'label' => 'View Complete Issue',
                            'icon' => 'https://assets.thirdiron.com/images/integrations/browzine-open-book-icon.svg',
                            'data' => $rawData['data'],
                        ],
                        [
                            'link' => 'https://fulltext',
                            'label' => 'PDF Full Text',
                            'icon' => 'https://assets.thirdiron.com/images/integrations/browzine-pdf-download-icon.svg',
                            'data' => $rawData['data'],
                        ]
                    ]
                ]
            ],
            [
                'config' => ['filterType' => 'exclude', 'filter' => ['browzineWebLink']],
                'response' => [
                    '10.1155/2020/8690540' => [
                        [
                            'link' => 'https://fulltext',
                            'label' => 'PDF Full Text',
                            'icon' => 'https://assets.thirdiron.com/images/integrations/browzine-pdf-download-icon.svg',
                            'data' => $rawData['data'],
                        ]
                    ]
                ]
            ],
            [
                'config' => ['filterType' => 'include', 'filter' => ['browzineWebLink']],
                'response' => [
                    '10.1155/2020/8690540' => [
                        [
                            'link' => 'https://weblink',
                            'label' => 'View Complete Issue',
                            'icon' => 'https://assets.thirdiron.com/images/integrations/browzine-open-book-icon.svg',
                            'data' => $rawData['data'],
                        ]
                    ]
                ]
            ],
        ];

        foreach ($testData as $data) {
            $dois = array_keys($data['response']);
            $connector = $this->getMockConnector($dois[0], $rawData);
            $browzine = new BrowZine($connector, $data['config']);
            $this->assertEquals(
                $data['response'],
                $browzine->getLinks($dois)
            );
        }
    }
}
