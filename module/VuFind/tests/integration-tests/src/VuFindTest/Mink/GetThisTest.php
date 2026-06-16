<?php

/**
 * Test class for the GetThis functionality.
 *
 * PHP version 8
 *
 * Copyright (C) Michigan State University Board of Trustees 2025.
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
 * along with this program; if not, see <https://www.gnu.org/licenses/>
 *
 * @category VuFind
 * @package  Tests
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Mink;

use Behat\Mink\Element\DocumentElement;
use Behat\Mink\Element\NodeElement;
use Exception;
use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use VuFindTest\Feature\DemoDriverTestTrait;
use VuFindTest\Integration\MinkTestCase;

use function count;

/**
 * Test class for the GetThis functionality.
 *
 * @category VuFind
 * @package  Tests
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class GetThisTest extends MinkTestCase
{
    use DemoDriverTestTrait;

    /**
     * Search to perform.
     *
     * @var string
     */
    protected const SEARCH = 'letterhead world';

    /**
     * Mink test post-test function.
     *
     * @return void
     * @throws Exception
     */
    public function tearDown(): void
    {
        parent::tearDown();
        $this->restoreConfigs();
    }

    /**
     * Mink test pre-test function.
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->restoreConfigs();
        $this->changeConfigs([
            'config' => [
                'Record' => ['getThisEnabled' => true],
            ],
        ]);
    }

    /**
     * Available holdings to work with.
     *
     * @return array
     */
    public static function getAvailableRecordItems(): array
    {
        $items = [];
        $items['CallNumberOne'] = [
            'item_id' => 1,
            'callnumber' => 'CallNumberOne',
            'location' => 'Villanova',
            'availability' => 'Available',
            'temporary_loan_type' => 'Available',
        ];
        $items['CallNumberTwo'] = [
            'item_id' => 2,
            'callnumber' => 'CallNumberTwo',
            'location' => 'Villanova',
            'availability' => 'Available',
            'temporary_loan_type' => 'Available',
        ];
        $items['CallNumberThree'] = [
            'item_id' => 3,
            'callnumber' => 'CallNumberThree',
            'location' => 'Phobos',
            'availability' => false,
            'temporary_loan_type' => 'Unavailable',
        ];
        $items['CallNumberFour'] = [
            'item_id' => 5,
            'callnumber' => 'CallNumberFour',
            'location' => 'Phobos',
            'availability' => 'Available',
            'temporary_loan_type' => 'Available',
        ];
        $items['CallNumberFive'] = [
            'item_id' => 7,
            'callnumber' => 'CallNumberFive',
            'location' => 'Microforms',
            'availability' => false,
            'temporary_loan_type' => 'Unavailable',
        ];
        return $items;
    }

    /**
     * Get an array of holdings with the specified call numbers for the specified record id.
     *
     * @param string $recordId       Record id to work with
     * @param string ...$callNumbers Call numbers of the holdings to use
     *
     * @return array
     */
    public static function getRecordItems(string $recordId, string ...$callNumbers): array
    {
        $items = [];
        $availableItems = static::getAvailableRecordItems();
        foreach ($callNumbers as $callNumber) {
            $items[] = [
                'id' => $recordId,
                ...$availableItems[$callNumber],
            ];
        }
        return $items;
    }

    /**
     * Getter for the Demo config array.
     *
     * @param string  $recordId          Record id to work with
     * @param bool    $fullStatus        Whether to use full status (or standard status)
     * @param array   $items             Call numbers of the holdings to use
     * @param ?string $multipleLocations Setting for multiple_locations
     *
     * @return array|array[]
     */
    public static function getVufindConfigArray(
        string $recordId,
        bool $fullStatus,
        array $items,
        ?string $multipleLocations = null
    ): array {
        $config = [
            'config' => [
                'Catalog' => ['driver' => 'Demo'],
                'ConfigCache' => ['disabled' => true], // prevent config caching from interfering with YAML updates
                'Item_Status' => [
                    'show_full_status' => $fullStatus,
                    'multiple_locations' => $multipleLocations ?? 'msg',
                ],
            ],
            'Demo' => [
                'StaticHoldings' => [],
                'Holdings' => ['generateRandomHoldings' => false],
            ],
        ];

        if (!empty($items)) {
            $config['Demo']['StaticHoldings'][$recordId] = json_encode(static::getRecordItems($recordId, ...$items));
        }

        return $config;
    }

    /**
     * Provider for testGetThisStandardStatus.
     *
     * @return Iterator<(int | string), array>
     */
    public static function provideStandardStatusTestData(): Iterator
    {
        $recordId = 'autocomplete1';
        yield [
            // $config
            static::getVufindConfigArray(
                $recordId,
                false,
                [
                    'CallNumberOne',
                    'CallNumberTwo',
                    'CallNumberThree',
                    'CallNumberFour',
                ]
            ),
            // $expectedPresence
            [
                'holdings' => true,
                'biblio-info' => true,
                'inter-library' => false,
                'micro-form' => false,
                'remote-delivery' => true,
                'staff-office-delivery' => true,
            ],
            // $search
            static::SEARCH,
        ];
        yield [
            // $config
            static::getVufindConfigArray(
                $recordId,
                false,
                [
                    'CallNumberFive',
                ]
            ),
            // $expectedPresence
            [
                'holdings' => false,
                'biblio-info' => true,
                'inter-library' => true,
                'micro-form' => true,
                'remote-delivery' => false,
                'staff-office-delivery' => false,
            ],
            // $search
            static::SEARCH,
        ];
    }

    /**
     * Test opening the GetThis dialog when using standard status and the presence of the expected blocks.
     *
     * @param array  $config           Config for the Demo driver
     * @param array  $expectedPresence Blocks which should be present or not
     * @param string $search           Search to perform
     *
     * @return void
     * @throws Exception
     */
    #[DataProvider('provideStandardStatusTestData')]
    public function testGetThisStandardStatus(array $config, array $expectedPresence, string $search): void
    {
        $this->changeConfigs($config);
        $this->setCommentBlockConfig(true);

        $page = $this->openGetThisLoaderForSearch($search);
        foreach ($expectedPresence as $blockName => $presence) {
            $this->assertBlockPresence($page, $blockName, $presence);
        }
    }

    /**
     * Test opening the GetThis dialog when using standard status and multiple_locations = group in the config.
     *
     * @return void
     * @throws Exception
     */
    public function testGetThisStandardStatusMultipleLocations(): void
    {
        $recordId = 'autocomplete1';
        $this->changeConfigs(static::getVufindConfigArray(
            $recordId,
            false,
            [
                'CallNumberFive',
            ],
            'group'
        ));
        $page = $this->searchAndWaitForItemsStatus(static::SEARCH);
        $getThisLink = $this->findAndAssertLink($page, 'Get This');
        $getThisLink->click();
        $this->unFindCss($page, '.loading-overlay,.loading-spinner');
        $lightbox = $this->getLightbox($page);
        $this->assertInstanceOf(NodeElement::class, $lightbox);
        $this->assertStringContainsString('CallNumberFive', $lightbox->getHtml());
    }

    /**
     * Provider for testGetThisFullStatus.
     *
     * @return Iterator<(int | string), array>
     */
    public static function provideFullStatusTestData(): Iterator
    {
        $recordId = 'autocomplete1';

        $callNumberTest1 = 'CallNumberThree';
        $callNumberThree = static::getAvailableRecordItems()[$callNumberTest1];
        $callNumberThree['status'] = $callNumberThree['availability'] ? 'Available' : 'Unavailable';
        $callNumberTest2 = 'CallNumberFive';
        $callNumberFive = static::getAvailableRecordItems()[$callNumberTest2];
        $callNumberFive['status'] = $callNumberFive['availability'] ? 'Available' : 'Unavailable';
        yield [
            // $config
            static::getVufindConfigArray(
                $recordId,
                true,
                [
                    'CallNumberOne',
                    'CallNumberTwo',
                    'CallNumberThree',
                    'CallNumberFour',
                ]
            ),
            // $expectedBlockPresence
            [
                'holdings' => true,
                'biblio-info' => true,
                'inter-library' => true,
                'micro-form' => false,
                'remote-delivery' => false,
                'staff-office-delivery' => false,
            ],
            // $expectedTerms
            [
                'Call Number : ' . $callNumberThree['callnumber'],
                'Location : ' . $callNumberThree['location'],
                'Status : ' . $callNumberThree['status'],
            ],
            // $search
            static::SEARCH,
            // $callNumberSelected
            $callNumberTest1,
        ];
        yield [
            // $config
            static::getVufindConfigArray(
                $recordId,
                true,
                [
                    'CallNumberFive',
                ]
            ),
            // $expectedBlockPresence
            [
                'holdings' => false,
                'biblio-info' => true,
                'inter-library' => true,
                'micro-form' => true,
                'remote-delivery' => false,
                'staff-office-delivery' => false,
            ],
            // $expectedTerms
            [
                'Call Number : ' . $callNumberFive['callnumber'],
                'Location : ' . $callNumberFive['location'],
                'Status : ' . $callNumberFive['status'],
            ],
            // $search
            static::SEARCH,
            // $callNumberSelected
            $callNumberTest2,
        ];
    }

    /**
     * Test opening the GetThis dialog when using full status and the presence of the expected blocks.
     *
     * @param array  $config                Config for the Demo driver
     * @param array  $expectedBlockPresence Blocks which should be present or not
     * @param array  $expectedTerms         Text which should be present
     * @param string $search                Search to perform
     * @param string $callNumberSelected    Which callnumber to open
     *
     * @return void
     * @throws Exception
     */
    #[DataProvider('provideFullStatusTestData')]
    public function testGetThisFullStatus(
        array $config,
        array $expectedBlockPresence,
        array $expectedTerms,
        string $search,
        string $callNumberSelected
    ): void {
        $this->changeConfigs($config);
        $this->setCommentBlockConfig(true);

        $page = $this->openGetThisLinkByCallNumber($search, $callNumberSelected);
        foreach ($expectedBlockPresence as $blockName => $presence) {
            $this->assertBlockPresence($page, $blockName, $presence);
        }
        $lightboxText = $this->getLightbox($page)->getText();
        foreach ($expectedTerms as $term) {
            $this->assertStringContainsString($term, $lightboxText);
        }
    }

    /**
     * For the feature to comment the HTML with the template block name in the lightbox
     * Testing if the disabling of the feature in the config works (the enabling is tested through other tests).
     *
     * @return void
     * @throws Exception
     */
    public function testBlockCommentNotPresent(): void
    {
        $this->changeConfigs(
            static::getVufindConfigArray(
                'autocomplete1',
                false,
                [
                    'CallNumberOne',
                    'CallNumberTwo',
                    'CallNumberThree',
                    'CallNumberFour',
                ]
            )
        );
        $this->setCommentBlockConfig(false);

        $page = $this->openGetThisLoaderForSearch(static::SEARCH);
        $lightbox = $this->getLightbox($page);
        $this->assertInstanceOf(NodeElement::class, $lightbox);
        $presence = str_contains($lightbox->getHtml(), '<!-- Get-This: ');
        $this->assertFalse($presence);
    }

    /**
     * Test changing holdings in the dropdown.
     *
     * @return void
     * @throws Exception
     */
    public function testChangeSelectedItem(): void
    {
        $callNumbers = [
            'CallNumberOne',
            'CallNumberTwo',
            'CallNumberThree',
        ];
        $this->changeConfigs(static::getVufindConfigArray('autocomplete1', false, $callNumbers));
        $this->changeYamlConfigs([
            'GetThis' => [
                'holdingsDropdown' => true,
            ],
        ]);

        $page = $this->openGetThisLoaderForSearch(static::SEARCH);
        $lightbox = $this->getLightbox($page);
        $navDropdown = $this->findCss($lightbox, 'nav.get-this-dropdown');

        next($callNumbers);
        for ($i = 1; $i < count($callNumbers); $i++) {
            // Open the dropdown
            $this->clickCss($navDropdown, '.dropdown-toggle');

            // Get the link and click on the next one
            $links = $navDropdown->findAll('css', 'a.dropdown-item');
            $links[$i]->click();

            // Wait for the lightbox to be refreshed
            $this->unFindCss($page, '.loading-overlay,.loading-spinner');

            // Make sure the right callnumber is loaded
            $rightCallNumberDisplaying = str_contains(
                $this->getLightbox($page)->getText(),
                'Call Number : ' . current($callNumbers)
            );
            $this->assertTrue(
                $rightCallNumberDisplaying,
                'Error while navigating with the dropdown, "' . current($callNumbers) . '" should be displayed'
            );

            next($callNumbers);
        }
    }

    /**
     * Perform a search and wait until the status are displayed.
     *
     * @param string $search String to search for
     *
     * @return DocumentElement
     * @throws Exception
     */
    public function searchAndWaitForItemsStatus(string $search): DocumentElement
    {
        $page = $this->performSearch($search);
        $this->waitForPageLoad($page);

        // Check for sample driver location/call number in output (this will
        // only appear after AJAX returns):
        $this->unFindCss($page, '.callnumber.ajax-availability');
        $this->unFindCss($page, '.location.ajax-availability');

        return $page;
    }

    /**
     * Perform a search and open the GetThis dialog.
     *
     * @param string $search String to search for
     *
     * @return DocumentElement
     * @throws Exception
     */
    public function openGetThisLoaderForSearch(string $search): DocumentElement
    {
        $page = $this->searchAndWaitForItemsStatus($search);
        $getThisLink = $this->findAndAssertLink($page, 'Get This');
        $getThisLink->click();

        $this->unFindCss($page, '.loading-overlay,.loading-spinner');

        return $page;
    }

    /**
     * Perform a search and open the GetThis dialog for the specified callnumber.
     *
     * @param string $search     String to search for
     * @param string $callNumber Callnumber to get
     *
     * @return DocumentElement
     * @throws Exception
     */
    public function openGetThisLinkByCallNumber(string $search, string $callNumber): DocumentElement
    {
        $page = $this->searchAndWaitForItemsStatus($search);

        $callNumberLink = null;
        $recordItems = $page->findAll('css', '.callnumAndLocation .itemWithAdditionalHoldingFields');
        foreach ($recordItems as $recordItem) {
            $callNumberHtmlCell = $recordItem->find('css', '.fullCallnumber');
            if ($callNumberHtmlCell && $callNumberHtmlCell->getText() === $callNumber) {
                $callNumberLink = $this->findAndAssertLink($recordItem, 'Get This');
            }
        }

        $this->assertInstanceOf(NodeElement::class, $callNumberLink);
        $callNumberLink->click();

        $this->unFindCss($page, '.loading-overlay,.loading-spinner');

        return $page;
    }

    /**
     * Getter for the lightbox.
     *
     * @param DocumentElement $page Page element
     *
     * @return ?NodeElement
     */
    public function getLightbox(DocumentElement $page): ?NodeElement
    {
        return $this->findCss($page, '.modal-content');
    }

    /**
     * Assert block presence (or absence) in the lightbox.
     *
     * @param DocumentElement $page             Page element
     * @param string          $blockName        Block to test the presence of
     * @param bool            $expectedPresence Whether the block should be present
     *
     * @return void
     */
    public function assertBlockPresence(DocumentElement $page, string $blockName, bool $expectedPresence): void
    {
        $lightbox = $this->getLightbox($page);
        $presence = str_contains($lightbox->getHtml(), '<!-- Get-This: ' . $blockName . ' -->');
        if ($expectedPresence) {
            $this->assertTrue(
                $presence,
                'GetThis modal : The "' . $blockName . '" block is missing and should be present'
            );
        } else {
            $this->assertFalse(
                $presence,
                'GetThis modal : The "' . $blockName . '" block is present and should not be'
            );
        }
    }

    /**
     * Set the value for commentTemplateName in the GetThis config to whether comment the displayed block.
     *
     * @param bool $value Either to enable the comment feature
     *
     * @return void
     */
    public function setCommentBlockConfig(bool $value): void
    {
        $this->changeYamlConfigs([
            'GetThis' => [
                'commentTemplateName' => $value,
            ],
        ]);
    }
}
