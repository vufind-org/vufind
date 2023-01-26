<?php

/**
 * Unit tests for Google cover loader.
 *
 * PHP version 7
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
 * @link     https://vufind.org
 */
namespace VuFindTest\Content\Covers;

use VuFind\Content\Covers\Google;
use VuFind\Http\CachingDownloader;
use VuFindCode\ISBN;

/**
 * Unit tests for Google cover loader.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class GoogleTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Test cover loading
     *
     * @return void
     */
    public function testValidCoverLoading(): void
    {
        $loader = new Google();
        $mockDownloader = $this->getMockBuilder(CachingDownloader::class)
            ->disableOriginalConstructor()
            ->getMock();
        $downloadCallback = function ($url, $params, $callback) {
            $this->assertEquals(
                'https://books.google.com/books?jscmd=viewapi'
                . '&bibkeys=ISBN:9781612917986&callback=addTheCover',
                $url
            );
            $this->assertEquals([], $params);
            $fixture = $this->getFixture('content/covers/google-cover.js');
            $response = $this->getMockBuilder(\Laminas\Http\Response::class)
                ->disableOriginalConstructor()
                ->getMock();
            $response->expects($this->once())->method('getBody')
                ->will($this->returnValue($fixture));
            return $callback($response, $url);
        };
        $mockDownloader->expects($this->once())->method('download')
            ->will($this->returnCallback($downloadCallback));
        $loader->setCachingDownloader($mockDownloader);
        $this->assertEquals(
            'https://books.google.com/books/content'
            . '?id=dEMBBAAAQBAJ&printsec=frontcover&img=1&zoom=5&edge=curl',
            $loader->getUrl(
                '',
                'small',
                ['isbn' => new ISBN('1612917984')]
            )
        );
    }

    /**
     * Test missing ISBN
     *
     * @return void
     */
    public function testMissingIsbn(): void
    {
        $loader = new Google();
        $this->assertFalse($loader->getUrl('', 'small', []));
    }
}
