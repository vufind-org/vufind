<?php

/**
 * Mink test class for the browse feature.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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

use Behat\Mink\Element\DocumentElement;

/**
 * Mink test class for the browse feature.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class BrowseTest extends \VuFindTest\Integration\MinkTestCase
{
    /**
     * Configuration to turn on all browse options:
     *
     * @var array
     */
    protected static $allOn = [
        'tag' => true,
        'dewey' => true,
        'lcc' => true,
        'author' => true,
        'topic' => true,
        'genre' => true,
        'region' => true,
        'era' => true,
    ];

    /**
     * Go to the Browse page.
     *
     * @return DocumentElement
     */
    protected function goToBrowse(): DocumentElement
    {
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Browse');
        return $session->getPage();
    }

    /**
     * Data provider for testFirstColumnConfig().
     *
     * @return array[]
     */
    public static function firstColumnConfigProvider(): array
    {
        $allOff = array_map(fn () => false, self::$allOn);
        // Confirm that we differentiate call number types when multiples
        // are enabled, but we do not when there is only one option.
        return [
            'everything on' => [
                [
                    'Tag',
                    'Call Number (Dewey)',
                    'Call Number (LC)',
                    'Author',
                    'Topic',
                    'Genre',
                    'Region',
                    'Era',
                ],
                self::$allOn,
            ],
            'only LCC' => [
                ['Call Number'], ['lcc' => true] + $allOff,
            ],
            'only Dewey' => [
                ['Call Number'], ['dewey' => true] + $allOff,
            ],
        ];
    }

    /**
     * Test configuration of available browse options
     *
     * @param string[] $expected Expected option list
     * @param array    $settings Settings to adjust in config.ini [Browse] section
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('firstColumnConfigProvider')]
    public function testFirstColumnConfig(array $expected, array $settings): void
    {
        $this->changeConfigs(
            [
                'config' => [
                    'Browse' => $settings,
                ],
            ]
        );
        $page = $this->goToBrowse();
        $values = array_map(fn ($item) => $item->getText(), $page->findAll('css', '#list1 .browse-item'));
        $this->assertEquals($expected, $values);
    }

    /**
     * Data provider for testSecondColumnBehavior().
     *
     * @return array[]
     */
    public static function secondColumnConfigProvider(): array
    {
        return [
            'Tag' => [['By Alphabetical', 'By Popularity', 'By Recent'], 'Tag'],
            'Call Number (Dewey)' => [['100 - Philosophy & psychology 1'], 'Call Number (Dewey)'],
            'Call Number (LC)' => [[], 'Call Number (LC)'],         // skips directly to list3
            'Author' => [
                ['By Alphabetical', 'By Call Number', 'By Topic', 'By Genre', 'By Region', 'By Era'],
                'Author',
            ],
            'Topic' => [['By Alphabetical', 'By Genre', 'By Region','By Era'], 'Topic'],
            'Genre' => [['By Alphabetical', 'By Topic', 'By Region','By Era'], 'Genre'],
            'Region' => [['By Alphabetical', 'By Topic', 'By Genre','By Era'], 'Region'],
            'Era' => [['By Alphabetical', 'By Topic', 'By Genre','By Region'], 'Era'],
        ];
    }

    /**
     * Test that secondary options match up with primary options.
     *
     * @param string[] $expected    Expected option list
     * @param string   $typeToClick First-column option to click
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('secondColumnConfigProvider')]
    public function testSecondColumnBehavior(array $expected, string $typeToClick): void
    {
        $this->changeConfigs(
            [
                'config' => [
                    'Browse' => self::$allOn,
                ],
                'searches' => [
                    // Filter to specific records to ensure predictable results:
                    'RawHiddenFilters' => ['building:deweybrowse.mrc'],
                ],
            ]
        );
        $page = $this->goToBrowse();
        $page->clickLink($typeToClick);
        $values = array_map(fn ($item) => $item->getText(), $page->findAll('css', '#list2 .browse-item'));
        $this->assertEquals($expected, $values);
    }

    /**
     * Test LC browse behavior.
     *
     * @return void
     */
    public function testLCBrowse(): void
    {
        $this->changeConfigs(
            [
                // No need to override browse settings here; LC is on by default.
                'searches' => [
                    // Filter to specific records to ensure predictable results:
                    'RawHiddenFilters' => ['building:weird_ids.mrc'],
                ],
            ]
        );
        $page = $this->goToBrowse();
        $page->clickLink('Call Number');
        $values = array_map(fn ($item) => $item->getText(), $page->findAll('css', '#list3 .browse-item'));
        $this->assertEquals(['H - Social Science 1', 'P - Language and Literature 7'], $values);
        $page->clickLink($values[0]);
        $values2 = array_map(fn ($item) => $item->getText(), $page->findAll('css', '#list4 .browse-item'));
        $this->assertEquals(['HG - Finance 1'], $values2);
        $page->clickLink($values2[0]);
        // We should now be on search results with a filter applied:
        $this->assertEquals('HG - Finance', $this->findCssAndGetText($page, '.filter-value .text'));
    }

    /**
     * Test Dewey browse behavior, jumping to results from the penultimate column.
     *
     * @return void
     */
    public function testDeweyBrowseShallowBrowse(): void
    {
        $this->changeConfigs(
            [
                'config' => [
                    'Browse' => ['lcc' => false, 'dewey' => true],
                ],
                'searches' => [
                    // Filter to specific records to ensure predictable results:
                    'RawHiddenFilters' => ['building:deweybrowse.mrc'],
                ],
            ]
        );
        $page = $this->goToBrowse();
        $page->clickLink('Call Number');
        $page->clickLink('100 - Philosophy & psychology 1');
        $values = array_map(fn ($item) => $item->getText(), $page->findAll('css', '#list3 .browse-item'));
        $this->assertEquals(['120 - Epistemology, causation, humankind 1', 'View Records'], $values);
        $page->clickLink($values[1]);
        // We should now be on search results with a filter applied:
        $values2 = array_map(
            fn ($item) => $item->getText(),
            $page->findAll('css', '.active-filters--uncollapsible .filter-value .text')
        );
        $this->assertEquals(
            [
                '120 - Epistemology, causation, humankind',
                '100 - Philosophy & psychology',
                '* - *', // wildcard query
            ],
            $values2
        );
    }

    /**
     * Test Dewey browse behavior, going all the way to the last column.
     *
     * @return void
     */
    public function testDeweyBrowseDeepBrowse(): void
    {
        $this->changeConfigs(
            [
                'config' => [
                    'Browse' => ['lcc' => false, 'dewey' => true],
                ],
                'searches' => [
                    // Filter to specific records to ensure predictable results:
                    'RawHiddenFilters' => ['building:deweybrowse.mrc'],
                ],
            ]
        );
        $page = $this->goToBrowse();
        $page->clickLink('Call Number');
        $page->clickLink('100 - Philosophy & psychology 1');
        $values = array_map(fn ($item) => $item->getText(), $page->findAll('css', '#list3 .browse-item'));
        $this->assertEquals(['120 - Epistemology, causation, humankind 1', 'View Records'], $values);
        $page->clickLink($values[0]);
        $values2 = array_map(fn ($item) => $item->getText(), $page->findAll('css', '#list4 .browse-item'));
        $this->assertEquals(['123 - Determinism and indeterminism 1'], $values2);
        $page->clickLink($values2[0]);
        // We should now be on search results with a filter applied:
        $values3 = array_map(
            fn ($item) => $item->getText(),
            $page->findAll('css', '.active-filters--uncollapsible .filter-value .text')
        );
        $this->assertEquals(
            [
                '120 - Epistemology, causation, humankind',
                '100 - Philosophy & psychology',
                '123 - Determinism and indeterminism',
            ],
            $values3
        );
    }

    /**
     * Test alphabetical tag browse.
     *
     * @return void
     */
    public function testTagAlphaBrowse(): void
    {
        $page = $this->goToBrowse();
        $page->clickLink('Tag');
        $page->clickLink('By Alphabetical');
        $page->clickLink('A');
        $this->assertEquals('No Results!', $this->findCssAndGetText($page, '#list4 .browse-item'));
    }

    /**
     * Test alphabetical author browse.
     *
     * @return void
     */
    public function testAuthorAlphaBrowse(): void
    {
        $this->changeConfigs(
            [
                'searches' => [
                    // Filter to specific records to ensure predictable results:
                    'RawHiddenFilters' => ['building:author_relators.mrc'],
                ],
            ]
        );
        $page = $this->goToBrowse();
        $page->clickLink('Author');
        $page->clickLink('By Alphabetical');
        $page->clickLink('A');
        $this->assertEquals(
            [
                'Author, Primary 1795 - 1881 11',
                'Author, Secondary 1875 - 1950 11',
                'Ahrens, Rüdiger 1939- 2',
            ],
            array_map(fn ($item) => $item->getText(), $page->findAll('css', '#list4 .browse-item'))
        );
    }

    /**
     * Test genre/topic cross-reference.
     *
     * @return void
     */
    public function testGenreTopicBrowse(): void
    {
        $this->changeConfigs(
            [
                'searches' => [
                    // Filter to specific records to ensure predictable results:
                    'RawHiddenFilters' => ['building:weird_ids.mrc'],
                ],
            ]
        );
        $page = $this->goToBrowse();
        $page->clickLink('Genre');
        $page->clickLink('By Topic');
        $values = array_map(fn ($item) => $item->getText(), $page->findAll('css', '#list3 .browse-item'));
        $expected = [
            'Adult children of aging parents 7',
            'View Records',
            'Automobile drivers\' tests 7',
            'View Records',
            'Fathers and daughters 7',
            'View Records',
            'Middle aged women 7',
            'View Records',
            'Older men 7',
            'View Records',
            'Bank employees 1',
            'View Records',
            'Bank management 1',
            'View Records',
            'Globalization 1',
            'View Records',
            'Industrial relations 1',
            'View Records',
            'Labor unions 1',
            'View Records',
        ];
        $this->assertEquals($expected, $values);
        $page->clickLink('Labor unions 1');
        $this->assertEquals(
            ['The Study and Scor_ng of Dots.and-Dashes:Colons 1', 'Weird IDs 1'],
            array_map(fn ($item) => $item->getText(), $page->findAll('css', '#list4 .browse-item'))
        );
        $page->clickLink('Weird IDs 1');
        $filters = array_map(
            fn ($item) => $item->getText(),
            $page->findAll('css', '.active-filters--uncollapsible .filter-value .text')
        );
        $this->assertEquals(['Labor unions', 'Weird IDs'], $filters);
    }
}
