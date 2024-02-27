<?php

/**
 * OpenSearch Feed Plugin Test Class
 *
 * PHP version 8
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
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Feed\Writer\Extension\OpenSearch;

use VuFind\Feed\Writer\Extension\OpenSearch\Feed;

/**
 * OpenSearch Feed Plugin Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class FeedTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test illegal feed type.
     *
     * @return void
     */
    public function testIllegalLinkFeedType(): void
    {
        $this->expectExceptionMessage(
            'Invalid parameter: "type"; You must declare the type of feed'
            . ' the link points to, i.e. RSS, RDF or Atom'
        );
        $feed = new Feed();
        $feed->addOpenSearchLink('http://foo', 'role', 'bad-type');
    }

    /**
     * Test basic use of addOpensearchLink/getOpensearchLinks.
     *
     * @return void
     */
    public function testLinks(): void
    {
        $feed = new Feed();
        $feed->addOpenSearchLink('http://foo', null, 'rss')
            ->addOpenSearchLink('http://bar', 'role', 'atom', 'title');
        $this->assertEquals(
            [
                [
                    'url' => 'http://foo',
                    'role' => null,
                    'type' => 'rss',
                ],
                [
                    'url' => 'http://bar',
                    'role' => 'role',
                    'type' => 'atom',
                    'title' => 'title',
                ],
            ],
            $feed->getOpensearchLinks()
        );
    }
}
