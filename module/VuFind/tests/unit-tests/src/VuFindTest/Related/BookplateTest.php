<?php

/**
 * Bookplate Related Items Test Class
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

namespace VuFindTest\Related;

use VuFind\Config\PluginManager as ConfigManager;
use VuFind\Related\Bookplate;
use VuFind\Related\BookplateFactory;
use VuFindTest\Container\MockContainer;
use VuFindTest\RecordDriver\TestHarness as RecordDriver;

/**
 * Bookplate Related Items Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class BookplateTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ConfigPluginManagerTrait;

    /**
     * Test default behavior (no bookplates)
     *
     * @return void
     */
    public function testDefaultsWithNoConfig(): void
    {
        $bookplate = $this->getBookplate();
        $driver = $this->getTestRecord();
        $bookplate->init('', $driver);
        $this->assertEquals([], $bookplate->getBookplateDetails());
    }

    /**
     * Test bookplate display with default config location, title display on,
     * and single-valued index fields.
     *
     * @return void
     */
    public function testBookplateWithDefaultConfigLocation(): void
    {
        $config = [
            'Record' => [
                'bookplate_titles_field' => 'donor_str',
                'bookplate_images_field' => 'donor_image_str',
                'bookplate_thumbnails_field' => 'donor_thumb_str',
                'bookplate_full' => 'https://localhost/%%img%%',
                'bookplate_thumb' => 'https://localhost/%%thumb%%',
                'bookplate_display_title' => true,
            ],
        ];
        $container = $this->getContainer('config', $config);
        $driver = $this->getTestRecord(
            [
                'donor_str' => 'Mr. Donor',
                'donor_image_str' => 'image.jpg',
                'donor_thumb_str' => 'thumb.jpg',
            ]
        );
        $bookplate = $this->getBookplate($container);
        $bookplate->init('', $driver);
        $expected = [
            [
                'title' => 'Mr. Donor',
                'fullUrl' => 'https://localhost/image.jpg',
                'thumbUrl' => 'https://localhost/thumb.jpg',
                'displayTitle' => true,
            ],
        ];
        $this->assertEquals($expected, $bookplate->getBookplateDetails());
    }

    /**
     * Test bookplate display with non-default config location, title display off,
     * and multi-valued index fields.
     *
     * @return void
     */
    public function testBookplateWithNonDefaultConfigLocation(): void
    {
        $config = [
            'bar' => [
                'bookplate_titles_field' => 'donor_str_mv',
                'bookplate_images_field' => 'donor_image_str_mv',
                'bookplate_thumbnails_field' => 'donor_thumb_str_mv',
                'bookplate_full' => 'https://localhost/%%img%%',
                'bookplate_thumb' => 'https://localhost/%%thumb%%',
                'bookplate_display_title' => false,
            ],
        ];
        $container = $this->getContainer('foo', $config);
        $driver = $this->getTestRecord(
            [
                'donor_str_mv' => ['Mr. Donor', 'Mrs. Donor'],
                'donor_image_str_mv' => ['image1.jpg', 'image2.jpg'],
                'donor_thumb_str_mv' => ['thumb1.jpg', 'thumb2.jpg'],
            ]
        );
        $bookplate = $this->getBookplate($container);
        $bookplate->init('foo:bar', $driver);
        $expected = [
            [
                'title' => 'Mr. Donor',
                'fullUrl' => 'https://localhost/image1.jpg',
                'thumbUrl' => 'https://localhost/thumb1.jpg',
                'displayTitle' => false,
            ],
            [
                'title' => 'Mrs. Donor',
                'fullUrl' => 'https://localhost/image2.jpg',
                'thumbUrl' => 'https://localhost/thumb2.jpg',
                'displayTitle' => false,
            ],
        ];
        $this->assertEquals($expected, $bookplate->getBookplateDetails());
    }

    /**
     * Get the test subject.
     *
     * @param ?MockContainer $container Container with dependencies for Bookplate
     *
     * @return Bookplate
     */
    protected function getBookplate(MockContainer $container = null): Bookplate
    {
        $factory = new BookplateFactory();
        return $factory($container ?? $this->getContainer(), Bookplate::class);
    }

    /**
     * Get the mock container.
     *
     * @param string $expectedConfig Name of config that will be used
     * @param array  $config         Config to return
     *
     * @return MockContainer
     */
    protected function getContainer(
        string $expectedConfig = 'config',
        array $config = []
    ): MockContainer {
        $container = new MockContainer($this);
        $container->set(
            ConfigManager::class,
            $this->getMockConfigPluginManager([$expectedConfig => $config])
        );
        return $container;
    }

    /**
     * Get a record driver to test with.
     *
     * @param array $rawData Data to load into the driver
     *
     * @return RecordDriver
     */
    protected function getTestRecord($rawData = []): RecordDriver
    {
        $driver = new RecordDriver();
        $driver->setRawData($rawData);
        return $driver;
    }
}
