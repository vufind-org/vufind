<?php

/**
 * Mink test class for alphabetic browse.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2022.
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
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Mink;

/**
 * Mink test class for alphabetic browse.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 * @retry    4
 */
class AlphabrowseTest extends \VuFindTest\Integration\MinkTestCase
{
    /**
     * Test that extra attributes are escaped correctly.
     *
     * @return void
     */
    public function testExtraAttributeEscaping()
    {
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Alphabrowse/Home?source=lcc&from=PS3552.R878+T47+2011');
        $page = $session->getPage();
        $extras = $this->findCss($page, 'table.alphabrowse td.lcc ~ td');
        $text = $extras->getText();
        $this->assertTrue(
            strpos($text, '<HTML> The Basics') !== false,
            "Could not find '<HTML> The Basics' in '$text'"
        );
    }
}
