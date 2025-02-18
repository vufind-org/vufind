<?php

/**
 * Tag autocomplete test class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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

use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Autocomplete\Tag;
use VuFind\Tags\TagsService;

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
     * Test real suggestions.
     *
     * @return void
     */
    public function testSuggestions(): void
    {
        // Real object to test:
        $tag = new Tag($this->getTagsServiceMock());
        $this->assertEquals(['bar1', 'bar2'], $tag->getSuggestions('foo'));
    }

    /**
     * Get tagService mock.
     *
     * @return MockObject&TagsService
     */
    public function getTagsServiceMock(): MockObject&TagsService
    {
        $tagService = $this->createMock(TagsService::class);
        $tags = [
            ['tag' => 'bar1'],
            ['tag' => 'bar2'],
        ];
        $tagService->expects($this->once())->method('getNonListTagsFuzzilyMatchingString')
            ->with($this->equalTo('foo'))
            ->willReturn($tags);
        return $tagService;
    }
}
