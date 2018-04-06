<?php

/**
 * Unit tests for Socialstats controller.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2014.
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
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
namespace VuFindTest\Controller;

/**
 * Unit tests for Socialstats controller.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class SocialstatsControllerTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Test language mappings.
     *
     * @return void
     */
    public function testHome()
    {
        // Create mocks to simulate database lookups:
        $c = $this->getMockBuilder('VuFindAdmin\Controller\SocialstatsController')
            ->setMethods(['getTable'])->disableOriginalConstructor()->getMock();
        $comments = $this->getMockBuilder('VuFind\Db\Table\Comments')
            ->disableOriginalConstructor()->setMethods(['getStatistics'])->getMock();
        $comments->expects($this->once())->method('getStatistics')->will($this->returnValue('comments-data'));
        $c->expects($this->at(0))->method('getTable')->with($this->equalTo('comments'))->will($this->returnValue($comments));
        $userresource = $this->getMockBuilder('VuFind\Db\Table\UserResource')
            ->setMethods(['getStatistics'])->disableOriginalConstructor()->getMock();
        $userresource->expects($this->once())->method('getStatistics')->will($this->returnValue('userresource-data'));
        $c->expects($this->at(1))->method('getTable')->with($this->equalTo('userresource'))->will($this->returnValue($userresource));
        $resourcetags = $this->getMockBuilder('VuFind\Db\Table\ResourceTags')
            ->disableOriginalConstructor()->setMethods(['getStatistics'])
            ->getMock();
        $resourcetags->expects($this->once())->method('getStatistics')->will($this->returnValue('resourcetags-data'));
        $c->expects($this->at(2))->method('getTable')->with($this->equalTo('resourcetags'))->will($this->returnValue($resourcetags));

        // Confirm properly-constructed view object:
        $view = $c->homeAction();
        $this->assertEquals('admin/socialstats/home', $view->getTemplate());
        $this->assertEquals('comments-data', $view->comments);
        $this->assertEquals('userresource-data', $view->favorites);
        $this->assertEquals('resourcetags-data', $view->tags);
    }
}
