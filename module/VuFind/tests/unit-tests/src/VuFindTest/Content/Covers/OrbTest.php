<?php

/**
 * Unit tests for Orb cover loader.
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

use VuFind\Content\Covers\Orb;
use VuFind\Http\CachingDownloader;
use VuFindCode\ISBN;

/**
 * Unit tests for Orb cover loader.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class OrbTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ConfigPluginManagerTrait;
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Get Orb object to test.
     *
     * @return Orb
     */
    protected function getLoader(): Orb
    {
        return new Orb('http://foo', 'fakeuser', 'fakekey');
    }

    /**
     * Test cover loading
     *
     * @return void
     */
    public function testValidCoverLoading(): void
    {
        $loader = $this->getLoader();
        $mockDownloader = $this->getMockBuilder(CachingDownloader::class)
            ->disableOriginalConstructor()
            ->getMock();
        $fixture = $this->getFixture('content/covers/orb-cover.json');
        $mockDownloader->expects($this->once())->method('downloadJson')
            ->with($this->equalTo("https://fakeuser:fakekey@http://foo/products?eans=9781612917986&sort=ean_asc"))
            ->will($this->returnValue(json_decode($fixture)));
        $loader->setCachingDownloader($mockDownloader);

        $this->assertEquals(
            'http://bar/small',
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
        $loader = $this->getLoader();
        $this->assertFalse($loader->getUrl('', 'small', []));
    }
}
