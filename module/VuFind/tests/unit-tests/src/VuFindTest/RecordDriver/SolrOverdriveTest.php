<?php
/**
 * SolrOverdrive Record Driver Test Class
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
namespace VuFindTest\RecordDriver;

use Laminas\Config\Config;
use VuFind\DigitalContent\OverdriveConnector;
use VuFind\Auth\ILSAuthenticator;
use VuFind\RecordDriver\SolrOverdrive;

/**
 * SolrOverdrive Record Driver Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SolrOverdriveTest extends \VuFindTest\Unit\TestCase
{
    use \VuFindTest\Unit\FixtureTrait;

    /**
     * Test supportsOpenUrl()
     *
     * @return void
     */
    public function testSupportsOpenUrl()
    {
        // Not supported:
        $this->assertFalse($this->getDriver()->supportsOpenUrl());
    }

    /**
     * Get a record driver to test with.
     *
     * @return SolrOverdrive
     */
    protected function getDriver($config = null, $recordConfig = null)
    {
        $finalConfig = $config ?? new Config([]);
        $finalRecordConfig = $recordConfig ?? new Config([]);
        $auth = $this->getMockBuilder(ILSAuthenticator::class)
            ->disableOriginalConstructor()->getMock();
        $connector = $this->getMockBuilder(OverdriveConnector::class)
            ->disableOriginalConstructor()->getMock();
        return new SolrOverdrive($finalConfig, $finalRecordConfig, $connector);
    }
}
