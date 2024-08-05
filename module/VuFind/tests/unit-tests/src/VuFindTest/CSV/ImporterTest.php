<?php

/**
 * CSV Importer Test Class
 *
 * PHP version 8
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
use VuFindTest\Container\MockContainer;

use function array_slice;

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
    use \VuFindTest\Feature\PathResolverTrait;

    /**
     * Location of fixture files.
     *
     * @var string
     */
    protected $csvFixtureDir;

    /**
     * Mock container for use by tests.
     *
     * @var MockContainer
     */
    protected $container;

    /**
     * Setup method.
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->csvFixtureDir = $this->getFixtureDir() . 'csv/';
        $this->container = new MockContainer($this);
        $this->addPathResolverToContainer($this->container);
    }

    /**
     * Get an Importer configured for testing.
     *
     * @return Importer
     */
    protected function getImporter(): Importer
    {
        $configBaseDir = implode(
            '/',
            array_slice(explode('/', realpath($this->csvFixtureDir)), -5)
        );
        return new Importer($this->container, compact('configBaseDir'));
    }

    /**
     * Run a test in test mode.
     *
     * @param array $options Options to override.
     *
     * @return void
     */
    protected function runTestModeTest($options = []): void
    {
        $importer = $this->getImporter();
        $result = $importer->save(
            $this->csvFixtureDir . ($options['csv'] ?? 'test.csv'),
            $options['ini'] ?? 'test.ini',
            'Solr',
            true
        );
        $expectedFile = $options['expected'] ?? 'test.json';
        $expected = file_get_contents($this->csvFixtureDir . $expectedFile);
        $this->assertJsonStringEqualsJsonString($expected, $result);
    }

    /**
     * Test importer functionality (in test mode).
     *
     * @return void
     */
    public function testImportInTestMode(): void
    {
        $this->runTestModeTest();
    }

    /**
     * Test that importer injects dependencies into static callback classes
     * when appropriate.
     *
     * @return void
     */
    public function testCallbackDependencyInjection(): void
    {
        // Before running the test, there will be no dependencies injected
        // into the static callback container, and trying to call getConfig
        // will throw an exception due to the missing dependency.
        $errorMsg = '';
        try {
            \VuFind\XSLT\Import\VuFind::getConfig();
        } catch (\Throwable $t) {
            $errorMsg = $t->getMessage();
        }
        $this->assertEquals('Call to a member function get() on null', $errorMsg);
        $this->runTestModeTest(
            [
                'ini' => 'test-injection.ini',
            ]
        );
        // After running the test, dependencies will have been injected, so
        // we can now call the same method without errors:
        \VuFind\XSLT\Import\VuFind::getConfig();
    }

    /**
     * Test skipping the header row in the CSV
     *
     * @return void
     */
    public function testSkipHeader(): void
    {
        $this->runTestModeTest(
            [
                'ini' => 'test-skip-header.ini',
            ]
        );
    }

    /**
     * Test importing a CSV with no header row.
     *
     * @return void
     */
    public function testNoHeader(): void
    {
        $this->runTestModeTest(
            [
                'ini' => 'test-no-header.ini',
                'expected' => 'test-no-header.json',
            ]
        );
    }

    /**
     * Test importing a CSV with extra callbacks using advanced features
     *
     * @return void
     */
    public function testAdvancedCallbacks(): void
    {
        $this->runTestModeTest(
            [
                'ini' => 'test-callbacks.ini',
                'expected' => 'test-callbacks.json',
            ]
        );
    }

    /**
     * Test importer functionality with non-default ISO-8859-1 encoding (in test
     * mode).
     *
     * @return void
     */
    public function testImportIsoEncoding(): void
    {
        $this->runTestModeTest(
            [
                'csv' => 'test-iso.csv',
                'ini' => 'test-iso.ini',
            ]
        );
    }

    /**
     * Test importer functionality with multiline CSV values (in test mode).
     *
     * @return void
     */
    public function testMultilineValues(): void
    {
        $this->runTestModeTest(
            [
                'csv' => 'test-multiline.csv',
                'ini' => 'test-multiline.ini',
            ]
        );
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
        $importer = $this->getImporter();
        $importer->save(
            $this->csvFixtureDir . 'test-iso.csv',
            'test.ini',
            'Solr',
            true
        );
    }

    /**
     * Test importer functionality (in non-test mode).
     *
     * @return void
     */
    public function testImportInLiveMode(): void
    {
        $mockWriter = $this->getMockBuilder(\VuFind\Solr\Writer::class)
            ->disableOriginalConstructor()->getMock();
        $mockWriter->expects($this->once())->method('save')->with(
            $this->equalTo('Solr'),
            $this->callback(
                function ($doc) {
                    $expected = file_get_contents($this->csvFixtureDir . 'test.json');
                    $this->assertJsonStringEqualsJsonString(
                        $expected,
                        $doc->getContent()
                    );
                    // If we got past the assertion, we can report success!
                    return true;
                }
            ),
            $this->equalTo('update')
        );
        $this->container->set(\VuFind\Solr\Writer::class, $mockWriter);
        $importer = $this->getImporter();
        $result = $importer->save(
            $this->csvFixtureDir . 'test.csv',
            'test.ini',
            'Solr',
            false
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
        $mockWriter = $this->getMockBuilder(\VuFind\Solr\Writer::class)
            ->disableOriginalConstructor()->getMock();
        $mockWriter->expects($this->exactly(3))->method('save')->with(
            $this->equalTo('Solr'),
            $this->callback(
                function ($doc) {
                    return $doc instanceof RawJSONDocument;
                }
            ),
            $this->equalTo('update')
        );
        $this->container->set(\VuFind\Solr\Writer::class, $mockWriter);
        $importer = $this->getImporter();
        $result = $importer->save(
            $this->csvFixtureDir . 'test.csv',
            'test-small-batch.ini',
            'Solr',
            false
        );
        $this->assertEquals('', $result); // no output in non-test mode
    }
}
