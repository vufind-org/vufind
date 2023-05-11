<?php

/**
 * Checklist tests.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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

namespace VuFindTest\Record;

use VuFind\Record\Checklist;

/**
 * Checklist tests.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ChecklistTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test checklists
     *
     * @return void
     */
    public function testChecklist()
    {
        $list = new Checklist(['a', 'b', 'c']);
        $this->assertFalse($list->hasChecked());
        $this->assertTrue($list->hasUnchecked());
        // Check only returns true the first time:
        $this->assertTrue($list->check('b'));
        $this->assertFalse($list->check('b'));
        // Not in list:
        $this->assertFalse($list->check('nope'));
        // Check intermediate state:
        $this->assertEquals(['a', 'c'], $list->getUnchecked());
        $this->assertEquals(['b'], $list->getChecked());
        $this->assertTrue($list->hasChecked());
        $this->assertTrue($list->hasUnchecked());
        // Can't uncheck unchecked value:
        $this->assertFalse($list->uncheck('c'));
        // Check rest of list:
        $this->assertTrue($list->check('a'));
        $this->assertTrue($list->check('c'));
        $this->assertFalse($list->hasUnchecked());
        // Now uncheck something:
        $this->assertTrue($list->uncheck('b'));
        $this->assertEquals(['a', 'c'], $list->getChecked());
        $this->assertEquals(['b'], $list->getUnchecked());
    }
}
