<?php

/**
 * UserContentHelper test class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2026.
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
 * @author   Emmanuel Afuadajo <afuadajoe@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\ActionHelper;

use GuzzleHttp\Psr7\ServerRequest;
use Laminas\Paginator\Adapter\ArrayAdapter;
use Laminas\Paginator\Paginator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use VuFind\ActionHelper\UserContentHelper;
use VuFind\Config\AccountCapabilities;
use VuFind\Record\Loader as RecordLoader;
use VuFind\RecordDriver\DefaultRecord as RecordDriver;

/**
 * UserContentHelper test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Emmanuel Afuadajo <afuadajoe@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class UserContentHelperTest extends TestCase
{
    /**
     * Get a helper instance with mock dependencies.
     *
     * @param ?RecordLoader        $recordLoader        Record loader (null for mock)
     * @param ?AccountCapabilities $accountCapabilities Account capabilities (null for mock)
     *
     * @return UserContentHelper
     */
    protected function getHelper(
        ?RecordLoader $recordLoader = null,
        ?AccountCapabilities $accountCapabilities = null
    ): UserContentHelper {
        return new UserContentHelper(
            $recordLoader ?? $this->createStub(RecordLoader::class),
            $accountCapabilities ?? $this->createStub(AccountCapabilities::class)
        );
    }

    /**
     * Test that getSortList() builds the expected structure and marks the active option.
     *
     * @return void
     */
    public function testGetSortList(): void
    {
        $options = ['title' => 'Title', 'author name' => 'Author'];
        $result = $this->getHelper()->getSortList($options, 'title');
        $this->assertEquals(
            [
                'title' => ['desc' => 'Title', 'url' => '?sort=title', 'selected' => true],
                'author name' => ['desc' => 'Author', 'url' => '?sort=author+name', 'selected' => false],
            ],
            $result
        );
    }

    /**
     * Test that getSortList() marks nothing selected when the active sort is not in the options.
     *
     * @return void
     */
    public function testGetSortListWithNoMatchingActive(): void
    {
        $result = $this->getHelper()->getSortList(['title' => 'Title'], 'nothing');
        $this->assertFalse($result['title']['selected']);
    }

    /**
     * Data provider for testGetPagingParams().
     *
     * @return \Iterator
     */
    public static function pagingParamsProvider(): \Iterator
    {
        $sortList = ['title' => [], 'author' => []];
        $defaultResult = ['page' => 1, 'limit' => 20, 'sort' => 'title'];
        yield 'defaults' => [[], $sortList, $defaultResult];

        yield 'normal query' => [
            ['page' => '3', 'sort' => 'author'],
            $sortList,
            ['page' => 3, 'limit' => 20, 'sort' => 'author'],
        ];

        yield 'invalid sort' => [['sort' => 'nothing'], $sortList, $defaultResult];

        yield 'page clamped' => [['page' => '0'], $sortList, $defaultResult];

        yield 'empty sort list' => [[], [], ['page' => 1, 'limit' => 20, 'sort' => '']];
    }

    /**
     * Test getPagingParams() query-string handling.
     *
     * @param array $queryParams Query parameters on the request
     * @param array $sortList    Allowed sort options
     * @param array $expected    Expected result
     *
     * @return void
     */
    #[DataProvider('pagingParamsProvider')]
    public function testGetPagingParams(array $queryParams, array $sortList, array $expected): void
    {
        $accountCapabilities = $this->createMock(AccountCapabilities::class);
        $accountCapabilities->method('getUserContentPageSize')->willReturn(20);

        $request = (new ServerRequest('GET', 'http://localhost/'))
            ->withQueryParams($queryParams);

        $result = $this->getHelper(null, $accountCapabilities)->getPagingParams($request, $sortList);
        $this->assertSame($expected, $result);
    }

    /**
     * Test that getUserContentRecordTitles() composes record ids correctly, delegates to the record loader, and
     * adds the loaded titles back onto the content items.
     *
     * @return void
     */
    public function testGetUserContentRecordTitles(): void
    {
        $inputArray  = [
            ['source' => 'Solr', 'record_id' => 'record1'],
            ['source' => 'Summon', 'record_id' => 'record2'],
        ];
        $contents = new Paginator(new ArrayAdapter($inputArray));

        // Set up expectation: the output will be like the input, but with titles added:
        $expectedOutputArray = $inputArray;
        $expectedOutputArray[0]['recordTitle'] = 'First title';
        $expectedOutputArray[1]['recordTitle'] = 'Second title';

        // Create fake record drivers using the expected titles set above:
        $driver1 = $this->createMock(RecordDriver::class);
        $driver1->method('getTitle')->willReturn($expectedOutputArray[0]['recordTitle']);
        $driver2 = $this->createMock(RecordDriver::class);
        $driver2->method('getTitle')->willReturn($expectedOutputArray[1]['recordTitle']);

        $recordLoader = $this->createMock(RecordLoader::class);
        $recordLoader->expects($this->once())->method('loadBatch')
            ->with(['Solr|record1', 'Summon|record2'], true)
            ->willReturn([$driver1, $driver2]);

        $result = $this->getHelper($recordLoader)->getUserContentRecordTitles($contents);

        // Make sure the output is not a reference to the input:
        $this->assertNotSame($contents, $result);
        $this->assertSame($expectedOutputArray, iterator_to_array($result));
    }

    /**
     * Data provider for testCapabilityFlags().
     *
     * @return \Iterator
     */
    public static function capabilityFlagProvider(): \Iterator
    {
        yield 'comments enabled' => ['commentsEnabled', 'getCommentSetting', 'enabled', true];
        yield 'comments disabled' => ['commentsEnabled', 'getCommentSetting', 'disabled', false];
        yield 'ratings enabled' => ['ratingsEnabled', 'getRatingSetting', 'enabled', true];
        yield 'ratings disabled' => ['ratingsEnabled', 'getRatingSetting', 'disabled', false];
        yield 'lists enabled' => ['listsEnabled', 'getListSetting', 'enabled', true];
        yield 'lists disabled' => ['listsEnabled', 'getListSetting', 'disabled', false];
        yield 'tags enabled' => ['tagsEnabled', 'getTagSetting', 'enabled', true];
        yield 'tags disabled' => ['tagsEnabled', 'getTagSetting', 'disabled', false];
    }

    /**
     * Test the capability flags.
     *
     * @param string $helperMethod     Method to call on the helper
     * @param string $capabilityMethod AccountCapabilities method it reads
     * @param string $settingValue     Value the capability method returns
     * @param bool   $expected         Expected boolean result
     *
     * @return void
     */
    #[DataProvider('capabilityFlagProvider')]
    public function testCapabilityFlags(
        string $helperMethod,
        string $capabilityMethod,
        string $settingValue,
        bool $expected
    ): void {
        $accountCapabilities = $this->createMock(AccountCapabilities::class);
        $accountCapabilities->method($capabilityMethod)->willReturn($settingValue);
        $this->assertSame($expected, $this->getHelper(null, $accountCapabilities)->$helperMethod());
    }

    /**
     * Test that isRatingRemovalAllowed() delegates to the account capabilities.
     *
     * @return void
     */
    public function testIsRatingRemovalAllowed(): void
    {
        foreach ([true, false] as $value) {
            $accountCapabilities = $this->createMock(AccountCapabilities::class);
            $accountCapabilities->method('isRatingRemovalAllowed')->willReturn($value);
            $this->assertSame($value, $this->getHelper(null, $accountCapabilities)->isRatingRemovalAllowed());
        }
    }
}
