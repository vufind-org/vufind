<?php

/**
 * Unit tests for LibGuides connector.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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
 * @package  Connection
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindTest\Connection;

use Laminas\Config\Config;
use Laminas\Http\Client\Adapter\Test as TestAdapter;
use Laminas\Http\Client as HttpClient;
use VuFind\Connection\LibGuides;

/**
 * Unit tests for Wikipedia connector.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class LibGuidesTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Test loading accounts.
     *
     * @return void
     */
    public function testGetAccounts()
    {
        $config = $this->getConfig();
        $client = $this->getClient('accounts');
        $libGuides = new LibGuides($config, $client);
        $response = $libGuides->getAccounts();
        $this->assertCount(2, $response);

        $dewey = $response[0];
        $this->assertEquals('Melvil', $dewey->first_name);
    }

    /**
     * Create a fake LibGuidesAPI.ini config.
     *
     * @return Config The fake config
     */
    protected function getConfig()
    {
        return new Config(
            [
                'General' => [
                    'api_base_url' => 'https://foo.org/',
                    'client_id' => 'username',
                    'client_secret' => 'email',
                ],
            ]
        );
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
        $adapter->addResponse($this->getFixture('libguides/api/token'));
        $adapter->addResponse($this->getFixture("libguides/api/$fixture"));
        $adapter->setResponseIndex(1);
        $client = new HttpClient();
        $client->setAdapter($adapter);
        return $client;
    }
}
