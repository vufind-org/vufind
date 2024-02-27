<?php

/**
 * Unpaywall Test Class
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
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\DoiLinker;

use Laminas\Http\Client\Adapter\Test as TestAdapter;
use Laminas\Http\Response as HttpResponse;
use VuFind\DoiLinker\Unpaywall;

/**
 * Unpaywall Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class UnpaywallTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Test configuration validation.
     *
     * @return void
     */
    public function testConfigValidation()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Missing configuration for Unpaywall DOI linker: unpaywall_email');

        new Unpaywall(new \Laminas\Config\Config([]));
    }

    /**
     * Test an API response.
     *
     * @return void
     */
    public function testApiSuccess()
    {
        $adapter = new TestAdapter();
        $testData = [
            [
                'fixture' => $this->getFixture('unpaywall/goodresponsepdf'),
                'response' => [
                    '10.7553/66-4-1434' => [
                        [
                            'link' => 'http://sajlis.journals.ac.za/pub/article/download/1434/1332',
                            'label' => 'PDF Full Text',
                        ],
                    ],
                ],
            ],
            [
                'fixture' => $this->getFixture('unpaywall/goodresponseonline'),
                'response' => [
                    '10.7553/66-4-1434' => [
                        [
                            'link' => 'https://doi.org/10.7553/66-4-1434',
                            'label' => 'online_resources',
                        ],
                    ],
                ],
            ],
            [
                'fixture' => $this->getFixture('unpaywall/badresponse'),
                'response' => [],
            ],
        ];

        $config = [
            'unpaywall_email' => 'foo@myuniversity.edu',
        ];
        $unpaywall = new Unpaywall(new \Laminas\Config\Config($config));

        foreach ($testData as $data) {
            $responseObj = HttpResponse::fromString($data['fixture']);
            $adapter->setResponse($responseObj);
            $service = new \VuFindHttp\HttpService();
            $service->setDefaultAdapter($adapter);
            $unpaywall->setHttpService($service);
            $this->assertEquals(
                $data['response'],
                $unpaywall->getLinks(['10.7553/66-4-1434'])
            );
        }
    }
}
