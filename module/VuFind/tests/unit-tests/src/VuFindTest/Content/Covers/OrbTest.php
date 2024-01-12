<?php

/**
 * Unit tests for Orb cover loader.
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
     * @param ?string $fixtureFile Fixture file to return (null to skip downloader initialization)
     * @param string  $expectedEAN Expected EAN in URL when $fixtureFile is not null
     *
     * @return Orb
     */
    protected function getLoader(?string $fixtureFile = null, string $expectedEAN = ''): Orb
    {
        $loader = new Orb('http://foo', 'fakeuser', 'fakekey');
        if ($fixtureFile) {
            $mockDownloader = $this->getMockBuilder(CachingDownloader::class)
                ->disableOriginalConstructor()
                ->getMock();
            $fixture = $this->getFixture($fixtureFile);
            $mockDownloader->expects($this->once())->method('downloadJson')
                ->with($this->equalTo("https://fakeuser:fakekey@http://foo/products?eans=$expectedEAN&sort=ean_asc"))
                ->will($this->returnValue(json_decode($fixture)));
            $loader->setCachingDownloader($mockDownloader);
        }
        return $loader;
    }

    /**
     * Test cover loading
     *
     * @return void
     */
    public function testValidCoverLoading(): void
    {
        $loader = $this->getLoader('content/covers/orb-cover.json', '9781612917986');

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
     * Test cover not available
     *
     * @return void
     */
    public function testUnavailableCoverLoading(): void
    {
        $loader = $this->getLoader('content/covers/orb-cover.json', '9780123456786');

        $this->assertFalse(
            $loader->getUrl(
                '',
                'small',
                ['isbn' => new ISBN('0123456789')]
            )
        );
    }

    /**
     * Test missing downloader
     *
     * @return void
     */
    public function testMissingDownloader(): void
    {
        $loader = $this->getLoader();
        $this->expectExceptionMessage('CachingDownloader initialization failed.');
        $loader->getUrl(
            '',
            'small',
            ['isbn' => new ISBN('0123456789')]
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
