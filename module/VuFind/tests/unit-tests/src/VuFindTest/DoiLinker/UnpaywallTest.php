<?php
/**
 * Unpaywall Test Class
 *
 * PHP version 7
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

use VuFind\DoiLinker\Unpaywall;
use Zend\Http\Client\Adapter\Test as TestAdapter;
use Zend\Http\Response as HttpResponse;

/**
 * Unpaywall Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class UnpaywallTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Test configuration validation.
     *
     * @return void
     *
     * @expectedException        Exception
     * @expectedExceptionMessage Missing configuration for Unpaywall DOI linker: unpaywall_email
     */
    public function testConfigValidation()
    {
        new Unpaywall(new \Zend\Config\Config([]));
    }

    /**
     * Test an API response.
     *
     * @return void
     */
    public function testApiSuccess()
    {
        $adapter = new TestAdapter();
        $file = realpath(
            __DIR__ . '/../../../../../tests/fixtures/unpaywall/goodresponse'
        );
        $response = file_get_contents($file);
        $responseObj = HttpResponse::fromString($response);
        $adapter->setResponse($responseObj);
        $service = new \VuFindHttp\HttpService();
        $service->setDefaultAdapter($adapter);
        $config = [
            'unpaywall_email' => 'foo@myuniversity.edu',
        ];
        $unpaywall = new Unpaywall(new \Zend\Config\Config($config));
        $unpaywall->setHttpService($service);
        $this->assertEquals(
            ['10.7553/66-4-1434' => [
                    [
                        'link' => 'http://sajlis.journals.ac.za/pub/article/download/1434/1332',
                        'label' => 'PDF Full Text',
                    ]
                ]
            ],
            $unpaywall->getLinks(['10.7553/66-4-1434'])
        );
    }
}
