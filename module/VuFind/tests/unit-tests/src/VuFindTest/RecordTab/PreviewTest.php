<?php
/**
 * Preview Test Class
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
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
namespace VuFindTest\RecordTab;

use VuFind\RecordTab\Preview;

/**
 * Reviews Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class PreviewTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test getting Description.
     *
     * @return void
     */
    public function testGetDescription(): void
    {
        $obj = $this->getPreview();
        $expected = 'Preview';
        $this->assertSame($expected, $obj->getDescription());
    }

    /**
     * Test if the tab is active.
     *
     * @return void
     */
    public function testisActive(): void
    {
        $expected_false = false;
        $expected_true = true;
        $obj1=$this->getPreview(false);
        $obj2=$this->getPreview(true);
        $this->assertSame($expected_false, $obj1->isActive());
        $this->assertSame($expected_true, $obj2->isActive());
    }

    /**
     * Test if the tab is intially visible.
     *
     * @return void
     */
    public function testisVisible(): void
    {
        $expected = false;
        $obj=$this->getPreview();
        $this->assertSame($expected, $obj->isVisible());
    }

    /**
     * Test if the tab can be loaded via AJAX.
     *
     * @return void
     */
    public function testsupportsAjax(): void
    {
        $expected = false;
        $obj=$this->getPreview();
        $this->assertSame($expected, $obj->supportsAjax());
    }

    /**
     * Get a configured Preview object.
     *
     * @param bool $active Is this tab active?
     *
     * @return Preview
     */
    protected function getPreview($active = false)
    {
        return new Preview($active);
    }
}
