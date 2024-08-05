<?php

/**
 * OAI-PMH test class.
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
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

declare(strict_types=1);

namespace VuFindTest\Mink;

use function count;

/**
 * OAI-PMH test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class OaiTest extends \VuFindTest\Integration\MinkTestCase
{
    use \VuFindTest\Feature\HttpRequestTrait;

    /**
     * Default OAI config settings
     *
     * @var array
     */
    protected $defaultOaiConfig = [
        'OAI' => [
            'identifier' => 'vufind.org',
            'repository_name' => 'test repo',
            'page_size' => 15,
        ],
    ];

    /**
     * Data provider describing OAI servers.
     *
     * @return array[]
     */
    public static function serverProvider(): array
    {
        return [
            'auth' => ['/OAI/AuthServer'],
            'biblio' => ['/OAI/Server'],
        ];
    }

    /**
     * Test that OAI-PMH is disabled by default.
     *
     * @param string $path URL path to OAI-PMH server.
     *
     * @return void
     *
     * @dataProvider serverProvider
     */
    #[\VuFindTest\Attribute\HtmlValidation(false)]
    public function testDisabledByDefault(string $path): void
    {
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . $path);
        $page = $session->getPage();
        $this->assertEquals(
            'OAI Server Not Configured.',
            $page->getText()
        );
    }

    /**
     * Test that a verb is required when enabled.
     *
     * @param string $path URL path to OAI-PMH server.
     *
     * @return void
     *
     * @dataProvider serverProvider
     */
    public function testVerbRequired(string $path): void
    {
        $this->changeConfigs(['config' => $this->defaultOaiConfig]);
        $rawXml = $this->httpGet($this->getVuFindUrl() . $path)->getBody();
        $xml = simplexml_load_string($rawXml);
        $this->assertEquals('Missing Verb Argument', $xml->error);
    }

    /**
     * Test that an identify response is provided and includes an appropriate repository name.
     *
     * @param string $path URL path to OAI-PMH server.
     *
     * @return void
     *
     * @dataProvider serverProvider
     */
    public function testIdentifyResponseRepositoryName(string $path): void
    {
        $this->changeConfigs(['config' => $this->defaultOaiConfig]);
        $rawXml = $this->httpGet($this->getVuFindUrl() . $path . '?verb=Identify')->getBody();
        $xml = simplexml_load_string($rawXml);
        // Authority endpoint overrides default name:
        $expectedName = $path === '/OAI/AuthServer'
            ? 'Authority Data Store' : $this->defaultOaiConfig['OAI']['repository_name'];
        $this->assertEquals($expectedName, $xml->Identify->repositoryName);
    }

    /**
     * Test the ListRecords verb.
     *
     * @return void
     */
    public function testListRecords(): void
    {
        $this->changeConfigs(['config' => $this->defaultOaiConfig]);

        // Get the first page of results. We expect 22 total results because we only turned on change
        // tracking in the demo setup for one 20-record file, plus we've created 2 fake deleted records
        // as part of our standard setup procedure; if more change tracking is added in future, this
        // test will need to be adjusted.
        $rawXml = $this
            ->httpGet($this->getVuFindUrl() . '/OAI/Server?verb=ListRecords&metadataPrefix=oai_dc')
            ->getBody();
        $xml = simplexml_load_string($rawXml);
        $resultSetSize = 22;
        $pageSize = $this->defaultOaiConfig['OAI']['page_size'];
        $resumptionAttributes = $xml->ListRecords->resumptionToken->attributes();
        $this->assertCount($pageSize, $xml->ListRecords->record);
        $this->assertEquals($resultSetSize, (int)$resumptionAttributes['completeListSize']);
        $this->assertEquals(0, (int)$resumptionAttributes['cursor']);
        $resumptionToken = (string)$xml->ListRecords->resumptionToken;
        $firstPageFirstId = (string)$xml->ListRecords->record[0]->header->identifier;
        $this->assertStringStartsWith('oai:vufind.org:', $firstPageFirstId);

        // Assert that only the first two records are marked deleted:
        $this->assertEquals('deleted', (string)$xml->ListRecords->record[0]->header->attributes());
        $this->assertEquals('deleted', (string)$xml->ListRecords->record[1]->header->attributes());
        $this->assertEquals('', (string)$xml->ListRecords->record[2]->header->attributes());

        // Now get the second page of results, using the resumption token from the first. Make sure
        // the results are different than before by comparing first record IDs.
        $rawXml2 = $this->httpGet(
            $this->getVuFindUrl() . '/OAI/Server?verb=ListRecords&resumptionToken=' . urlencode($resumptionToken)
        )->getBody();
        $xml2 = simplexml_load_string($rawXml2);
        $resumptionAttributes2 = $xml2->ListRecords->resumptionToken->attributes();
        $this->assertEquals($resultSetSize - $pageSize, count($xml2->ListRecords->record));
        $this->assertEquals($resultSetSize, (int)$resumptionAttributes2['completeListSize']);
        $this->assertEquals($pageSize, (int)$resumptionAttributes2['cursor']);
        $resumptionToken2 = (string)$xml2->ListRecords->resumptionToken;
        $secondPageFirstId = (string)$xml2->ListRecords->record[0]->header->identifier;
        $this->assertStringStartsWith('oai:vufind.org:', $secondPageFirstId);
        $this->assertNotEquals($firstPageFirstId, $secondPageFirstId);
        $this->assertEquals('', $resumptionToken2); // end of set
    }
}
