<?php

/**
 * ObalkyKnih table of contents handler test.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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

namespace VuFindTest\Content\TOC;

use VuFind\Content\TOC\ObalkyKnih;

/**
 * ObalkyKnih table of contents handler test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ObalkyKnihTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test normal operation.
     *
     * @return void
     */
    public function testLoadByIsbn(): void
    {
        $key = 'fake_key';
        $isbn = new \VuFindCode\ISBN('0123456789');
        $service = $this->createMock(\VuFind\Content\ObalkyKnihService::class);
        $data = (object)['toc_thumbnail_url' => 'http://foo/thumb', 'toc_pdf_url' => 'http://foo/pdf'];
        $service->expects($this->once())->method('getData')->with(compact('isbn'))->willReturn($data);
        $toc = new ObalkyKnih($service);
        $this->assertEquals(
            '<p><a href="http://foo/pdf" target="_blank" ><img src="http://foo/thumb"></a></p>',
            $toc->loadByIsbn($key, $isbn)
        );
    }
}
