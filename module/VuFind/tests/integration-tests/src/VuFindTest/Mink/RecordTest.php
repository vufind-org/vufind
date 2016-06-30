<?php
/**
 * Mink test class for basic record functionality.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2011.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace VuFindTest\Mink;

/**
 * Mink test class for basic record functionality.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class RecordTest extends \VuFindTest\Unit\MinkTestCase
{
    /**
     * Test record tabs for a particular ID.
     *
     * @param string $id       ID to load
     * @param bool   $encodeId Should we URL encode the ID?
     *
     * @return void
     */
    protected function tryRecordTabsOnId($id, $encodeId = true)
    {
        $url = $this->getVuFindUrl(
            '/Record/' . ($encodeId ? rawurlencode($id) : $id)
        );
        $session = $this->getMinkSession();
        $session->visit($url);
        $page = $session->getPage();
        $staffViewTab = $this->findCss($page, '.record-tabs .details');
        $this->assertEquals('Staff View', $staffViewTab->getText());
        $staffViewTab->click();
        $this->snooze();
        $this->assertEquals($url . '#details', $session->getCurrentUrl());
        $staffViewTable = $this->findCss($page, '.record-tabs .details-tab table.citation');
        $this->assertEquals('LEADER', substr($staffViewTable->getText(), 0, 6));
    }

    /**
     * Test that record tabs work with a "normal" ID.
     *
     * @return void
     */
    public function testRecordTabsOnNormalId()
    {
        $this->tryRecordTabsOnId('testsample1');
    }

    /**
     * Test that record tabs work with an ID with a space in it.
     *
     * @return void
     */
    public function testRecordTabsOnSpacedId()
    {
        $this->tryRecordTabsOnId('dot.dash-underscore__3.space suffix');
    }

    /**
     * Test that record tabs work with an ID with a plus in it.
     *
     * @return void
     */
    public function testRecordTabsOnPlusId()
    {
        // Skip encoding on this one, because Zend Framework doesn't URL encode
        // plus signs in route segments!
        $this->tryRecordTabsOnId('theplus+andtheminus-', false);
    }
}
