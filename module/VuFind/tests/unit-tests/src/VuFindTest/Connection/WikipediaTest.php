<?php

/**
 * Unit tests for Wikipedia connector.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindTest\Connection;

use Laminas\Http\Client as HttpClient;
use Laminas\Http\Response;
use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Connection\Wikipedia;

/**
 * Unit tests for Wikipedia connector.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class WikipediaTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Property to capture the first URI requested from the HTTP client.
     *
     * @var ?string
     */
    protected ?string $firstRequestedUri = null;

    /**
     * Test processing of English-language Jane Austen entry.
     *
     * @return void
     */
    public function testEnglishJane(): void
    {
        $expectedUri = 'https://en.wikipedia.org/w/api.php'
            . '?action=query&prop=revisions&rvprop=content&format=json&list=allpages&titles=Jane+Austen';
        $client = $this->getClient('en-austen');
        $wiki = new Wikipedia($client);
        $response = $wiki->get('Jane Austen');
        $this->assertSame($expectedUri, $this->firstRequestedUri);
        $this->assertEquals('en', $response['wiki_lang']);
        $this->assertEquals('Jane Austen', $response['name']);
        $this->assertNotFalse(strstr($response['description'], '16 December 1775'));
    }

    /**
     * Load HTTP client w/ fixture.
     *
     * @param string $fixture Fixture name
     *
     * @return HttpClient&MockObject
     */
    protected function getClient(string $fixture): HttpClient&MockObject
    {
        $response = $this->createMock(Response::class);
        $response->method('isSuccess')->willReturn(true);
        $response->method('getBody')->willReturn($this->getFixture("wikipedia/$fixture"));
        $client = $this->createMock(HttpClient::class);
        $client->method('setUri')->willReturnCallback(function ($uri) use ($client) {
            $this->firstRequestedUri ??= $uri;
            return $client;
        });
        $client->method('setMethod')->with('GET')->willReturn($client);
        $client->method('send')->willReturn($response);
        return $client;
    }
}
