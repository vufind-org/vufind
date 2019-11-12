<?php
/**
 * XSLT geographic helper tests.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2019.
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
namespace VuFindTest\XSLT\Import;

use VuFind\XSLT\Import\VuFindGeo;

/**
 * XSLT geographic helper tests.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class VuFindGeoTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Test basic data extraction using valid values.
     *
     * @return void
     */
    public function testDataExtraction()
    {
        $coverage = 'name=Dehradun; westlimit=77.8884; southlimit=30.2259; '
            . 'eastlimit=78.2234; northlimit=30.4511';
        $this->assertEquals('Dehradun', VuFindGeo::getLabelFromCoverage($coverage));
        $this->assertEquals(
            '77.8884 78.2234 30.4511 30.2259',
            VuFindGeo::getDisplayCoordinatesFromCoverage($coverage)
        );
        $this->assertEquals(
            'ENVELOPE(77.8884,78.2234,30.4511,30.2259)',
            VuFindGeo::getAllCoordinatesFromCoverage($coverage)
        );
    }

    /**
     * Test missing coordinate data.
     *
     * @return void
     */
    public function testMissingData()
    {
        $badInputs = [
            '',
            'name=Dehradun; westlimit=77.8884; southlimit=30.2259;',
            'eastlimit=78.2234; northlimit=30.4511',
        ];
        foreach ($badInputs as $input) {
            // When one or more coordinates are missing, we expect a null return:
            $this->assertNull(VuFindGeo::getDisplayCoordinatesFromCoverage($input));
            $this->assertNull(VuFindGeo::getAllCoordinatesFromCoverage($input));
        }
    }
}
