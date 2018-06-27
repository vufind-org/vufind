<?php
/**
 * Solr autocomplete test class.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2018.
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
 * @author   Jochen Lienhard <jochen.lienhard@ub.uni-freiburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace VuFindTest\Autocomplete;

use VuFind\Autocomplete\Eds;

/**
 * Eds autocomplete test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Jochen Lienhard <jochen.lienhard@ub.uni-freiburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class EdsTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Get a mock backend
     *
     * @param string $id ID of fake backend.
     *
     * @return \VuFindSearch\Backend\EDS\Backend
     */
    protected function getMockBackend($id = 'EDS')
    {
        $backend = $this->getMockBuilder('VuFindSearch\Backend\EDS\Backend')
            ->disableOriginalConstructor()->getMock();
        $backend->expects($this->any())->method('getIdentifier')->will(
            $this->returnValue($id)
        );
        return $backend;
    }

    /**
     * Test getSuggestions.
     *
     * @return void
     */
    public function testGetSuggestions()
    {
        $eds = new Eds($this->getMockBackend());
        // Todo: implement check for getSuggestions
    }


}
