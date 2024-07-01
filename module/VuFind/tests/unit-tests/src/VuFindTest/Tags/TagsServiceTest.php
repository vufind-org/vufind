<?php

/**
 * Tags Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010-2024.
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

namespace VuFindTest\Tags;

use VuFind\Db\Service\ResourceTagsService;
use VuFind\Db\Service\TagServiceInterface;
use VuFind\Db\Service\UserListServiceInterface;
use VuFind\Record\ResourcePopulator;
use VuFind\Tags\TagsService;

/**
 * Tags Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class TagsServiceTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Get an object to test
     *
     * @param int                $maxLength         Maximum tag length
     * @param ?ResourcePopulator $resourcePopulator Resource populator service (null for default mock)
     *
     * @return TagsService
     */
    public function getTagsService($maxLength = 64, ?ResourcePopulator $resourcePopulator = null): TagsService
    {
        return new TagsService(
            $this->createMock(TagServiceInterface::class),
            $this->createMock(ResourceTagsService::class),
            $this->createMock(UserListServiceInterface::class),
            $resourcePopulator ?? $this->createMock(ResourcePopulator::class),
            $maxLength
        );
    }

    /**
     * Test tag parsing
     *
     * @return void
     */
    public function testTagParsing()
    {
        $this->assertEquals(
            ['this', 'that', 'the other'],
            $this->getTagsService()->parse('this that "the other"')
        );
    }

    /**
     * Test empty tag parsing
     *
     * @return void
     */
    public function testEmptyTagParsing()
    {
        $this->assertEquals([], $this->getTagsService()->parse(''));
    }

    /**
     * Test deduplication
     *
     * @return void
     */
    public function testDeduplication()
    {
        $this->assertEquals(['test'], $this->getTagsService()->parse('test test test'));
    }

    /**
     * Test truncation
     *
     * @return void
     */
    public function testTruncation()
    {
        // Create custom object w/ small size limit:
        $this->assertEquals(['0123456789'], $this->getTagsService(10)->parse('01234567890'));
    }
}
