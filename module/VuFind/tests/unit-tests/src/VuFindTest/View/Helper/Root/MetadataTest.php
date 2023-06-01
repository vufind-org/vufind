<?php

/**
 * Metadata Test Class
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

namespace VuFindTest\View\Helper\Root;

use Laminas\Config\Config;
use Laminas\View\Helper\HeadMeta;
use VuFind\MetadataVocabulary\PluginManager;
use VuFind\MetadataVocabulary\PRISM;
use VuFind\View\Helper\Root\Metadata;
use VuFindTest\RecordDriver\TestHarness;

/**
 * Metadata Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class MetadataTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Get a fake record driver
     *
     * @param array $data Test data
     *
     * @return TestHarness
     */
    protected function getDriver($data)
    {
        $driver = new TestHarness();
        $driver->setRawData($data);
        return $driver;
    }

    /**
     * Get a mock HeadMeta helper
     *
     * @return HeadMeta
     */
    protected function getMetaHelper()
    {
        $mock = $this->getMockBuilder(HeadMeta::class)
            ->disableOriginalConstructor()
            ->addMethods(['appendName'])    // mocking __call
            ->getMock();
        $mock->expects($this->once())->method('appendName')
            ->with($this->equalTo('prism.title'), $this->equalTo('Fake Title'));
        return $mock;
    }

    /**
     * Get a mock plugin manager
     *
     * @return PluginManager
     */
    protected function getPluginManager()
    {
        $mock = $this->getMockBuilder(PluginManager::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get'])
            ->getMock();
        $mock->expects($this->once())->method('get')
            ->with($this->equalTo('PRISM'))
            ->will($this->returnValue(new PRISM()));
        return $mock;
    }

    /**
     * Test basic functionality of the helper.
     *
     * @return void
     */
    public function testMetadata()
    {
        $helper = new Metadata(
            $this->getPluginManager(),
            new Config(['Vocabularies' => [TestHarness::class => ['PRISM']]]),
            $this->getMetaHelper()
        );
        $helper->generateMetatags(
            $this->getDriver(['Title' => 'Fake Title'])
        );
    }
}
