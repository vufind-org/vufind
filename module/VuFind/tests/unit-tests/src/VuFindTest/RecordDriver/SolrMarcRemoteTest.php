<?php
/**
 * SolrMarcRemote Record Driver Test Class
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

use Exception;
use Laminas\Config\Config;
use VuFind\RecordDriver\SolrMarcRemote;

/**
 * SolrMarcRemote Record Driver Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SolrMarcRemoteTest extends \VuFindTest\Unit\TestCase
{
    use \VuFindTest\Unit\FixtureTrait;

    /**
     * Test config validation.
     *
     * @return void
     */
    public function testRequiredConfigException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('SolrMarcRemote baseUrl-setting missing.');
        new SolrMarcRemote();
    }

    /**
     * Test record ID validation (a record with no ID cannot be resolved).
     *
     * @return void
     */
    public function testMissingRecordId(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No unique id given for fullrecord retrieval');
        $this->getDriver()->getSummary();
    }

    /**
     * Get a SolrMarcRemote driver preconfigured to load a record.
     *
     * @return SolrMarcRemote
     */
    protected function getDriver(): SolrMarcRemote
    {
        $url = 'http://foo';
        $config = new Config(['Record' => ['remote_marc_url' => $url]]);
        $driver = new SolrMarcRemote($config);
        return $driver;
    }
}
