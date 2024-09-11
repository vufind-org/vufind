<?php

/**
 * Import/WebCrawl command test.
 *
 * PHP version 8
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

namespace VuFindTest\Command\Import;

use Laminas\Config\Config;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Tester\CommandTester;
use VuFind\Solr\Writer;
use VuFind\XSLT\Importer;
use VuFindConsole\Command\Import\WebCrawlCommand;

/**
 * Import/WebCrawl command test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class WebCrawlCommandTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;
    use \VuFindTest\Feature\WithConsecutiveTrait;

    /**
     * Data provider for testSuccessWithMinimalParameters()
     *
     * @return array[]
     */
    public static function successWithMinimalParametersProvider(): array
    {
        return [
            'not verbose' => [false, ''],
            'verbose' => [
                true,
                'Harvesting http://foo... Harvesting http://bar... '
                . 'Deleting old records (prior to DATE)... Committing... Optimizing...',
            ],
        ];
    }

    /**
     * Test the simplest possible success case.
     *
     * @param bool   $verbose        Run in verbose mode?
     * @param string $expectedOutput Expected normalized output string
     *
     * @return void
     *
     * @dataProvider successWithMinimalParametersProvider
     */
    public function testSuccessWithMinimalParameters(bool $verbose, string $expectedOutput): void
    {
        $fixture1 = $this->getFixtureDir('VuFindConsole') . 'sitemap/index.xml';
        $fixture2 = $this->getFixtureDir('VuFindConsole') . 'sitemap/map.xml';
        $importer = $this->getMockImporter();
        $importer->expects($this->once())->method('save')
            ->with(
                $this->equalTo($fixture2),
                $this->equalTo('sitemap.properties'),
                $this->equalTo('SolrWeb'),
                $this->equalTo(false)
            );
        $solr = $this->getMockSolrWriter();
        $solr->expects($this->once())->method('deleteByQuery')
            ->with($this->equalTo('SolrWeb'));
        $solr->expects($this->once())->method('commit')
            ->with($this->equalTo('SolrWeb'));
        $solr->expects($this->once())->method('optimize')
            ->with($this->equalTo('SolrWeb'));
        $config = new Config(
            [
                'General' => compact('verbose'),
                'Sitemaps' => ['url' => ['http://foo']],
            ]
        );
        $command = $this->getMockCommand($importer, $solr, $config);
        $this->expectConsecutiveCalls(
            $command,
            'downloadFile',
            [['http://foo'], ['http://bar']],
            [$fixture1, $fixture2]
        );
        $this->expectConsecutiveCalls($command, 'removeTempFile', [[$fixture2], [$fixture1]]);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
        $normalizedOutput = trim(
            preg_replace(
                '/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z/',
                'DATE',
                str_replace("\n", ' ', $commandTester->getDisplay())
            )
        );
        $this->assertEquals($expectedOutput, $normalizedOutput);
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    /**
     * Get a mock command object
     *
     * @param Importer $importer Importer object
     * @param Writer   $solr     Solr writer object
     * @param Config   $config   Configuration
     * @param array    $methods  Methods to mock
     *
     * @return MockObject&WebCrawlCommand
     */
    protected function getMockCommand(
        Importer $importer = null,
        Writer $solr = null,
        Config $config = null,
        array $methods = ['downloadFile', 'removeTempFile']
    ): MockObject&WebCrawlCommand {
        return $this->getMockBuilder(WebCrawlCommand::class)
            ->setConstructorArgs(
                [
                    $importer ?? $this->getMockImporter(),
                    $solr ?? $this->getMockSolrWriter(),
                    $config ?? new Config([]),
                ]
            )->onlyMethods($methods)
            ->getMock();
    }

    /**
     * Get a mock importer object
     *
     * @param array $methods Methods to mock
     *
     * @return MockObject&Importer
     */
    protected function getMockImporter($methods = []): MockObject&Importer
    {
        $builder = $this->getMockBuilder(Importer::class)
            ->disableOriginalConstructor();
        if (!empty($methods)) {
            $builder->onlyMethods($methods);
        }
        return $builder->getMock();
    }

    /**
     * Get a mock solr writer object
     *
     * @param array $methods Methods to mock
     *
     * @return MockObject&Writer
     */
    protected function getMockSolrWriter($methods = []): MockObject&Writer
    {
        $builder = $this->getMockBuilder(Writer::class)
            ->disableOriginalConstructor();
        if (!empty($methods)) {
            $builder->onlyMethods($methods);
        }
        return $builder->getMock();
    }
}
