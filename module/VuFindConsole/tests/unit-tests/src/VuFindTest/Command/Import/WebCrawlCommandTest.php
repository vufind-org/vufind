<?php
/**
 * Import/WebCrawl command test.
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
namespace VuFindTest\Command\Import;

use Laminas\Config\Config;
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
    use \VuFindTest\Unit\FixtureTrait;

    /**
     * Test the simplest possible success case.
     *
     * @return void
     */
    public function testSuccessWithMinimalParameters()
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
                'Sitemaps' => ['url' => ['http://foo']]
            ]
        );
        $command = $this->getMockCommand($importer, $solr, $config);
        $command->expects($this->at(0))->method('downloadFile')
            ->with($this->equalTo('http://foo'))
            ->will($this->returnValue($fixture1));
        $command->expects($this->at(1))->method('downloadFile')
            ->with($this->equalTo('http://bar'))
            ->will($this->returnValue($fixture2));
        $command->expects($this->at(2))->method('removeTempFile')
            ->with($this->equalTo($fixture2));
        $command->expects($this->at(3))->method('removeTempFile')
            ->with($this->equalTo($fixture1));
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
        $this->assertEquals(
            '', $commandTester->getDisplay()
        );
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
     * @return WebCrawlCommand
     */
    protected function getMockCommand(Importer $importer = null,
        Writer $solr = null, Config $config = null,
        array $methods = ['downloadFile', 'removeTempFile']
    ) {
        return $this->getMockBuilder(WebCrawlCommand::class)
            ->setConstructorArgs(
                [
                    $importer ?? $this->getMockImporter(),
                    $solr ?? $this->getMockSolrWriter(),
                    $config ?? new Config([]),
                ]
            )->setMethods($methods)
            ->getMock();
    }

    /**
     * Get a mock importer object
     *
     * @param array $methods Methods to mock
     *
     * @return Importer
     */
    protected function getMockImporter($methods = [])
    {
        return $this->getMockBuilder(Importer::class)
            ->disableOriginalConstructor()
            ->setMethods($methods)
            ->getMock();
    }

    /**
     * Get a mock solr writer object
     *
     * @param array $methods Methods to mock
     *
     * @return Writer
     */
    protected function getMockSolrWriter($methods = [])
    {
        return $this->getMockBuilder(Writer::class)
            ->disableOriginalConstructor()
            ->setMethods($methods)
            ->getMock();
    }
}
