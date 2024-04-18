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
    /**
     * Default OAI config settings
     *
     * @var array
     */
    protected $defaultOaiConfig = [
        'OAI' => [
            'identifier' => 'vufind.org',
            'repository_name' => 'test repo',
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
        $rawXml = file_get_contents($this->getVuFindUrl() . $path);
        $xml = simplexml_load_string($rawXml);
        $this->assertEquals('Missing Verb Argument', $xml->error);
    }
}
