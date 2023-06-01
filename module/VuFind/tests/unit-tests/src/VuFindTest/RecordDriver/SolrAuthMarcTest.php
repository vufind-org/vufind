<?php

/**
 * SolrAuthMarc Record Driver Test Class
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

namespace VuFindTest\RecordDriver;

/**
 * SolrAuthMarc Record Driver Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SolrAuthMarcTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Test LCCN support.
     *
     * @return void
     */
    public function testRawLCCN()
    {
        // LCCN in 700
        $marc = $this->getFixture('marc/authlccn1.xml');
        $config = new \Laminas\Config\Config([]);
        $record = new \VuFind\RecordDriver\SolrAuthMarc($config);
        $record->setRawData(['fullrecord' => $marc]);
        $this->assertEquals('foo', $record->getRawLCCN());

        // LCCN in 010
        $marc2 = $this->getFixture('marc/authlccn2.xml');
        $record2 = new \VuFind\RecordDriver\SolrAuthMarc($config);
        $record2->setRawData(['fullrecord' => $marc2]);
        $this->assertEquals('92005291', $record2->getRawLCCN());

        // No LCCN
        $marc3 = $this->getFixture('marc/altscript.xml');
        $record3 = new \VuFind\RecordDriver\SolrAuthMarc($config);
        $record3->setRawData(['fullrecord' => $marc3]);
        $this->assertFalse($record3->getRawLCCN());
    }
}
