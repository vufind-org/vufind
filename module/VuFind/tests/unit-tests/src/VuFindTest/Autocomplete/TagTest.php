<?php

/**
 * Tag autocomplete test class.
 *
 * PHP version 7
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Autocomplete;

use VuFind\Autocomplete\Tag;

/**
 * Tag autocomplete test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class TagTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test that missing plugin manager causes exception.
     *
     * @return void
     */
    public function testMissingDependency()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('DB table manager missing.');

        $tag = new Tag();
        $tag->getSuggestions('foo');
    }

    /**
     * Test real suggestions.
     *
     * @return void
     */
    public function testSuggestions()
    {
        // Fake DB response:
        $tags = [
            ['tag' => 'bar1'],
            ['tag' => 'bar2'],
        ];

        // Fake services:
        $tagTable = $this->getMockBuilder(\VuFind\Db\Table\Tags::class)
            ->disableOriginalConstructor()->onlyMethods(['matchText'])->getMock();
        $tagTable->expects($this->once())->method('matchText')
            ->with($this->equalTo('foo'))
            ->will($this->returnValue($tags));
        $tableManager = $this->getMockBuilder(\VuFind\Db\Table\PluginManager::class)
            ->disableOriginalConstructor()->onlyMethods(['get'])->getMock();
        $tableManager->expects($this->once())->method('get')
            ->with($this->equalTo('Tags'))
            ->will($this->returnValue($tagTable));

        // Real object to test:
        $tag = new Tag();
        $tag->setDbTableManager($tableManager);

        $this->assertEquals(['bar1', 'bar2'], $tag->getSuggestions('foo'));
    }
}
