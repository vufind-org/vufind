<?php

/**
 * Mink test class for cover image behavior on the record page.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Mink;

/**
 * Mink test class for cover image behavior on the record page.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class RecordCoverImageTest extends \VuFindTest\Integration\MinkTestCase
{
    /**
     * Data provider for testCoverLoading()
     *
     * @return array[]
     */
    public static function coverLoadingProvider(): array
    {
        return [
            'image available, ajax w/ backlinks' => [
                '0000183626-0', // this ID causes the Demo cover provider to return an image
                true,
                true,
                'themes/root/images/demo-cover-2.jpg',
                'Cover from vufind.org',
            ],
            'image available, ajax w/o backlinks' => [
                '0000183626-0', // this ID causes the Demo cover provider to return an image
                false,
                true,
                'themes/root/images/demo-cover-2.jpg',
                '',
            ],
            'image available, non-ajax w/o backlinks' => [
                '0000183626-0', // this ID causes the Demo cover provider to return an image
                false,
                false,
                '/Cover/Show?author=&callnumber=BL1140.4.V572.E+2002+%28BL3%29%2B&size=medium&title=%C5%9Ar%C4'
                . '%ABvi%E1%B9%A3%E1%B9%87upur%C4%81%E1%B9%87am+%3A+%28a+system+of+Hindu+mythology+and+traditi'
                . 'on%29+%3D+The+Vi%E1%B9%A3%E1%B9%87u+Pur%C4%81%E1%B9%87am+%2F&recordid=0000183626-0&source=Solr',
                '',
            ],
            'image available, non-ajax w/ backlinks' => [
                '0000183626-0', // this ID causes the Demo cover provider to return an image
                true,
                false,
                '/Cover/Show?author=&callnumber=BL1140.4.V572.E+2002+%28BL3%29%2B&size=medium&title=%C5%9Ar%C4'
                . '%ABvi%E1%B9%A3%E1%B9%87upur%C4%81%E1%B9%87am+%3A+%28a+system+of+Hindu+mythology+and+traditi'
                . 'on%29+%3D+The+Vi%E1%B9%A3%E1%B9%87u+Pur%C4%81%E1%B9%87am+%2F&recordid=0000183626-0&source=Solr',
                '', // backlinks don't work without AJAX, so we don't expect to see one
            ],
            'image unavailable, ajax w/ fallback image' => [
                '0000183626-1', // this ID causes the Demo cover provider to return no image
                false,
                true,
                '/Cover/Show?author=Vy%C4%81sa&callnumber=BL1140.4.V572.E+2002+%28BL3%29%2B&size=medium&title=%C5'
                . '%9Ar%C4%ABvi%E1%B9%A3%E1%B9%87upur%C4%81%E1%B9%87am+%3A+%28a+system+of+Hindu+mythology+and+tra'
                . 'dition%29+%3D+The+Vi%E1%B9%A3%E1%B9%87u+Pur%C4%81%E1%B9%87am+%28with+dubious+author%29%2F&reco'
                . 'rdid=0000183626-1&source=Solr',
                '',
            ],
            'image unavailable, non-ajax w/ fallback image' => [
                '0000183626-1', // this ID causes the Demo cover provider to return no image
                false,
                false,
                '/Cover/Show?author=Vy%C4%81sa&callnumber=BL1140.4.V572.E+2002+%28BL3%29%2B&size=medium&title=%C5'
                . '%9Ar%C4%ABvi%E1%B9%A3%E1%B9%87upur%C4%81%E1%B9%87am+%3A+%28a+system+of+Hindu+mythology+and+tra'
                . 'dition%29+%3D+The+Vi%E1%B9%A3%E1%B9%87u+Pur%C4%81%E1%B9%87am+%28with+dubious+author%29%2F&reco'
                . 'rdid=0000183626-1&source=Solr',
                '',
            ],
            'image unavailable, ajax w/o fallback image' => [
                '0000183626-1', // this ID causes the Demo cover provider to return no image
                false,
                true,
                '/Cover/Show?author=Vy%C4%81sa&callnumber=BL1140.4.V572.E+2002+%28BL3%29%2B&size=medium&title=%C5'
                . '%9Ar%C4%ABvi%E1%B9%A3%E1%B9%87upur%C4%81%E1%B9%87am+%3A+%28a+system+of+Hindu+mythology+and+tra'
                . 'dition%29+%3D+The+Vi%E1%B9%A3%E1%B9%87u+Pur%C4%81%E1%B9%87am+%28with+dubious+author%29%2F&reco'
                . 'rdid=0000183626-1&source=Solr',
                '',
                '', // blank string = no fallback image
            ],
            'image unavailable, non-ajax w/o fallback image' => [
                '0000183626-1', // this ID causes the Demo cover provider to return no image
                false,
                false,
                '/Cover/Show?author=Vy%C4%81sa&callnumber=BL1140.4.V572.E+2002+%28BL3%29%2B&size=medium&title=%C5'
                . '%9Ar%C4%ABvi%E1%B9%A3%E1%B9%87upur%C4%81%E1%B9%87am+%3A+%28a+system+of+Hindu+mythology+and+tra'
                . 'dition%29+%3D+The+Vi%E1%B9%A3%E1%B9%87u+Pur%C4%81%E1%B9%87am+%28with+dubious+author%29%2F&reco'
                . 'rdid=0000183626-1&source=Solr',
                '',
                '', // blank string = no fallback image
            ],
        ];
    }

    /**
     * Test record tabs for a particular ID.
     *
     * @param string  $id                    ID of record to test with
     * @param bool    $includeBacklink       Should we configure cover provider to include backlinks?
     * @param bool    $ajaxcovers            Should we use AJAX covers?
     * @param string  $expectedImage         Expected image URL (minus base path)
     * @param ?string $expectedBacklink      Expected backlink text (null for none)
     * @param string  $noCoverAvailableImage Image to load if unavailable (empty for none)
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('coverLoadingProvider')]
    public function testCoverLoading(
        string $id,
        bool $includeBacklink,
        bool $ajaxcovers,
        string $expectedImage,
        ?string $expectedBacklink,
        string $noCoverAvailableImage = 'images/noCover2.gif'
    ): void {
        // Update configurations:
        $coverimages = $includeBacklink ? 'Demo:true' : 'Demo';
        $coverimagesBrowserCache = false;
        $this->changeConfigs(
            ['config' => ['Content' => compact(
                'coverimages',
                'coverimagesBrowserCache',
                'ajaxcovers',
                'noCoverAvailableImage'
            )]]
        );

        // Load a page with the specified record:
        $url = $this->getVuFindUrl('/Record/' . rawurlencode($id));
        $session = $this->getMinkSession();
        $session->visit($url);
        $page = $session->getPage();
        $this->waitForPageLoad($page);
        $coverSelector = 'img.recordcover';
        $loaded = $session->wait(
            $this->getDefaultTimeout(),
            "document.querySelector('$coverSelector').dataset.loaded !== undefined"
        );
        $this->assertTrue($loaded, 'Expected record image to be loaded.');
        // Verify the expected backlink (or lack thereof):
        $backlinkSelector = 'p.cover-source';
        if ($expectedBacklink) {
            // Normalize whitespace to simplify comparison:
            $this->assertEquals(
                str_replace(' ', '', $expectedBacklink),
                str_replace(' ', '', $this->findCssAndGetText($page, $backlinkSelector))
            );
        } else {
            $this->unfindCss($page, $backlinkSelector);
        }

        // Confirm the expected status of the image (most importantly, should it be visible or hidden?):
        $expectedClasses = 'recordcover'
            . ($ajaxcovers ? ' ajax' : '')
            . (empty($noCoverAvailableImage) ? ' hidden' : '');
        $coverImage = $this->findCss($page, $coverSelector);
        $this->assertEquals(
            $expectedClasses,
            $coverImage?->getAttribute('class')
        );

        // Verify the expected image URL:
        $imageSrc = $coverImage?->getAttribute('src');
        $expectedImageParts = explode('?', $expectedImage);

        // Verify path
        $expectedPath = $expectedImageParts[0];
        $this->assertStringContainsString($expectedPath, $imageSrc);

        // Verify query except timestamp hash for deactivated browser cache
        $imageSrcQuery = explode('&', explode('?', $imageSrc)[1] ?? '');
        $imageSrcQuery = array_filter($imageSrcQuery, fn ($part) => !str_starts_with($part, 'browser_cache_hash'));
        $expectedQuery = explode('&', $expectedImageParts[1] ?? '');
        sort($expectedQuery);
        sort($imageSrcQuery);
        $this->assertEquals(implode('', $expectedQuery), (implode('', $imageSrcQuery)));
    }
}
