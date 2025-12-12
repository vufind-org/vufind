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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Connection
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindTest\Connection;

use Laminas\Http\Client\Adapter\Test as TestAdapter;
use Laminas\Http\Client as HttpClient;
use VuFind\Config\Config;
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
     * GetAZ test provider.
     *
     * @return array[]
     */
    public static function getAzProvider(): array
    {
        return [
            'exclude hidden (explicitly)' => [ true, 4 ],
            'do not exclude hidden' => [ false, 5 ],
            'exclude hidden (by default)' => [ null, 4 ],
        ];
    }

    /**
     * Test loading AZ.
     *
     * @param ?bool $excludeHidden Whether to exclude hidden databases
     * @param int   $expectedCount Expected result count
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getAzProvider')]
    public function testGetAz(?bool $excludeHidden, int $expectedCount): void
    {
        $config = $this->getConfig();
        $client = $this->getClient('az');
        $libGuides = new LibGuides($config, $client);

        $params = $excludeHidden === null ? [] : [$excludeHidden];
        $response = $libGuides->getAz(...$params);
        $this->assertCount($expectedCount, $response);
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
