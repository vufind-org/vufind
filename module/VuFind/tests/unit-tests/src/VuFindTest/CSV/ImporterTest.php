<?php
/**
 * CSV Importer Test Class
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2021.
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
namespace VuFindTest\CSV;

use VuFind\CSV\Importer;
use VuFindSearch\Backend\Solr\Document\RawJSONDocument;

/**
 * CSV Importer Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ImporterTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Test importer functionality (in test mode).
     *
     * @return void
     */
    public function testImportInTestMode(): void
    {
        $container = new \VuFindTest\Container\MockContainer($this);
        $fixtureDir = $this->getFixtureDir() . 'csv/';
        $configBaseDir = implode('/', array_slice(explode('/', realpath($fixtureDir)), -5));
        $importer = new Importer($container, compact('configBaseDir'));
        $result = $importer->save(
            $fixtureDir . 'test.csv', 'test.ini', 'Solr', true
        );
        $expected = file_get_contents($fixtureDir . 'test.json');
        $this->assertJsonStringEqualsJsonString($expected, $result);
    }

    /**
     * Test skipping the header row in the CSV
     *
     * @return void
     */
    public function testSkipHeader(): void
    {
        $container = new \VuFindTest\Container\MockContainer($this);
        $fixtureDir = $this->getFixtureDir() . 'csv/';
        $configBaseDir = implode('/', array_slice(explode('/', realpath($fixtureDir)), -5));
        $importer = new Importer($container, compact('configBaseDir'));
        $result = $importer->save(
            $fixtureDir . 'test.csv', 'test-skip-header.ini', 'Solr', true
        );
        $expected = file_get_contents($fixtureDir . 'test.json');
        $this->assertJsonStringEqualsJsonString($expected, $result);
    }

    /**
     * Test importing a CSV with no header row.
     *
     * @return void
     */
    public function testNoHeader(): void
    {
        $container = new \VuFindTest\Container\MockContainer($this);
        $fixtureDir = $this->getFixtureDir() . 'csv/';
        $configBaseDir = implode('/', array_slice(explode('/', realpath($fixtureDir)), -5));
        $importer = new Importer($container, compact('configBaseDir'));
        $result = $importer->save(
            $fixtureDir . 'test.csv', 'test-no-header.ini', 'Solr', true
        );
        $expected = file_get_contents($fixtureDir . 'test-no-header.json');
        $this->assertJsonStringEqualsJsonString($expected, $result);
    }

    /**
     * Test importing a CSV with extra callbacks using advanced features
     *
     * @return void
     */
    public function testAdvancedCallbacks(): void
    {
        $container = new \VuFindTest\Container\MockContainer($this);
        $fixtureDir = $this->getFixtureDir() . 'csv/';
        $configBaseDir = implode('/', array_slice(explode('/', realpath($fixtureDir)), -5));
        $importer = new Importer($container, compact('configBaseDir'));
        $result = $importer->save(
            $fixtureDir . 'test.csv', 'test-callbacks.ini', 'Solr', true
        );
        $expected = file_get_contents($fixtureDir . 'test-callbacks.json');
        $this->assertJsonStringEqualsJsonString($expected, $result);
    }

    /**
     * Test importer functionality with non-default encoding (in test mode).
     *
     * @return void
     */
    public function testImportIsoEncoding(): void
    {
        $container = new \VuFindTest\Container\MockContainer($this);
        $fixtureDir = $this->getFixtureDir() . 'csv/';
        $configBaseDir = implode('/', array_slice(explode('/', realpath($fixtureDir)), -5));
        $importer = new Importer($container, compact('configBaseDir'));
        $result = $importer->save(
            $fixtureDir . 'test-iso.csv', 'test-iso.ini', 'Solr', true
        );
        $expected = file_get_contents($fixtureDir . 'test.json');
        $this->assertJsonStringEqualsJsonString($expected, $result);
    }

    /**
     * Test behavior when the actual encoding and configured encoding are mismatched.
     *
     * @return void
     */
    public function testEncodingMismatch(): void
    {
        $this->expectExceptionMessage(
            'Malformed UTF-8 characters, possibly incorrectly encoded'
        );
        $container = new \VuFindTest\Container\MockContainer($this);
        $fixtureDir = $this->getFixtureDir() . 'csv/';
        $configBaseDir = implode('/', array_slice(explode('/', realpath($fixtureDir)), -5));
        $importer = new Importer($container, compact('configBaseDir'));
        $importer->save(
            $fixtureDir . 'test-iso.csv', 'test.ini', 'Solr', true
        );
    }

    /**
     * Test importer functionality (in non-test mode).
     *
     * @return void
     */
    public function testImportInLiveMode(): void
    {
        $fixtureDir = $this->getFixtureDir() . 'csv/';
        $container = new \VuFindTest\Container\MockContainer($this);
        $mockWriter = $this->getMockBuilder(\VuFind\Solr\Writer::class)
            ->disableOriginalConstructor()->getMock();
        $mockWriter->expects($this->once())->method('save')->with(
            $this->equalTo('Solr'),
            $this->callback(function ($doc) use ($fixtureDir) {
                $expected = file_get_contents($fixtureDir . 'test.json');
                $this->assertJsonStringEqualsJsonString(
                    $expected, $doc->getContent()
                );
                // If we got past the assertion, we can report success!
                return true;
            }),
            $this->equalTo('update')
        );
        $container->set(\VuFind\Solr\Writer::class, $mockWriter);
        $configBaseDir = implode('/', array_slice(explode('/', realpath($fixtureDir)), -5));
        $importer = new Importer($container, compact('configBaseDir'));
        $result = $importer->save(
            $fixtureDir . 'test.csv', 'test.ini', 'Solr', false
        );
        $this->assertEquals('', $result); // no output in non-test mode
    }

    /**
     * Test importer functionality (with small batch size set).
     *
     * @return void
     */
    public function testImportInSmallBatches(): void
    {
        $fixtureDir = $this->getFixtureDir() . 'csv/';
        $container = new \VuFindTest\Container\MockContainer($this);
        $mockWriter = $this->getMockBuilder(\VuFind\Solr\Writer::class)
            ->disableOriginalConstructor()->getMock();
        $mockWriter->expects($this->exactly(3))->method('save')->with(
            $this->equalTo('Solr'),
            $this->callback(function ($doc) {
                return $doc instanceof RawJSONDocument;
            }),
            $this->equalTo('update')
        );
        $container->set(\VuFind\Solr\Writer::class, $mockWriter);
        $configBaseDir = implode('/', array_slice(explode('/', realpath($fixtureDir)), -5));
        $importer = new Importer($container, compact('configBaseDir'));
        $result = $importer->save(
            $fixtureDir . 'test.csv', 'test-small-batch.ini', 'Solr', false
        );
        $this->assertEquals('', $result); // no output in non-test mode
    }
}
