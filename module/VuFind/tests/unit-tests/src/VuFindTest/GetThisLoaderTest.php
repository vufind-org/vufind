<?php

/**
 * Get This loader Test Class.
 *
 * PHP version 8
 *
 * @category VuFind
 * @package  Catalog
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 **/

namespace VuFindTest;

use Exception;
use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use ReflectionException;
use Throwable;
use VuFind\Config\YamlReader;
use VuFind\GetThis\GetThisLoader;
use VuFind\GetThis\GetThisLoaderFactory;
use VuFind\ILS\Logic\AvailabilityStatus;
use VuFind\RecordDriver\SolrDefault;
use VuFind\Regex\Regex;
use VuFindTest\Container\MockContainer;
use VuFindTest\Feature\ConfigRelatedServicesTrait;
use VuFindTest\Feature\FixtureTrait;
use VuFindTest\Feature\ReflectionTrait;

/**
 * Get This loader Test Class.
 *
 * @category VuFind
 * @package  Tests
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class GetThisLoaderTest extends TestCase
{
    use FixtureTrait;
    use ReflectionTrait;
    use ConfigRelatedServicesTrait;

    /**
     * Yaml reader needed for GetThis.
     *
     * @var YamlReader
     */
    protected YamlReader $yamlReader;

    /**
     * GetThis base config.
     *
     * @var array
     */
    protected array $baseConfig;

    /**
     * GetThis regex config.
     *
     * @var array
     */
    protected array $regexConfig;

    /**
     * Test setUp function, before every test.
     *
     * @return void
     */
    public function setUp(): void
    {
        $yamlReader = new YamlReader($this->getPathResolver());
        $this->baseConfig = $yamlReader->get('GetThis.yaml');
        $this->regexConfig = $yamlReader->get('Regex.yaml');
        $this->regexConfig['LOCATION_EXCLUSIVE'][] = '/OUR CAMPUS/i';
    }

    /**
     * Getter for the loader.
     *
     * @param ?array $config Config to use instead of default
     *
     * @return GetThisLoader
     */
    public function getGetThis(?array $config = null): GetThisLoader
    {
        return new GetThisLoader(
            $config ?? $this->baseConfig,
            new Regex($this->regexConfig)
        );
    }

    /**
     * Create a mock driver for solr.
     *
     * @return SolrDefault|MockObject
     */
    public function getMockRecordDriver(): SolrDefault|MockObject
    {
        return $this->createMock(SolrDefault::class);
    }

    /**
     * Items to be re-used.
     *
     * @return array[]
     */
    protected static function getItems(): array
    {
        return [
            [
                'item_id' => 1,
                'location' => 'Main Library',
                'location_code' => 'ML',
                'availability' => new AvailabilityStatus(true, 'Available'),
                'temporary_loan_type' => 'Restricted',
                'callnumber' => 'callnumber00',
                'number' => 1,
            ],
            [
                'item_id' => 2,
                'location' => 'Main Library',
                'location_code' => 'ML',
                'availability' => new AvailabilityStatus(true, 'Unavailable'),
                'temporary_loan_type' => 'Someone renting',
                'callnumber' => 'callnumber007',
                'number' => 2,
            ],
            [
                'item_id' => 5,
                'location' => 'Main Library',
                'location_code' => 'ML',
                'temporary_loan_type' => 'On a shelf',
                'callnumber' => 'call_me123',
            ],
        ];
    }

    /**
     * Test method areItemsSupported.
     *
     * @return void
     */
    public function testItemsSupported(): void
    {
        $getThis = $this->getGetThis();
        $this->assertTrue($getThis->areItemsSupported(static::getItems()));
        $this->assertFalse($getThis->areItemsSupported([]));
    }

    /**
     * Test method getItems, getItem, setItems, setItemById.
     *
     * @return void
     */
    public function testItems(): void
    {
        $getThis = $this->getGetThis();
        $this->assertEmpty($getThis->getItems());
        $getThis->setItems(static::getItems());
        $this->assertEquals(static::getItems(), $getThis->getItems());
        $this->assertEquals(1, $getThis->getItem()['item_id']);
        $getThis->setDefaultItemId('2');
        $this->assertEquals(2, $getThis->getItem()['item_id']);
        $this->assertEquals(1, $getThis->getItem('1')['item_id']);
    }

    /**
     * Data provider for testConfigConditionsFunctions().
     *
     * @return Iterator<(int | string), array>
     */
    public static function provideConfigConditionsFunctionsData(): Iterator
    {
        yield [
            [
                // Items
            ],
            [
                // Expected templates
                'biblio-info',
            ],
        ];
        yield [
            static::getItems(),
            [
                // Expected templates
                'holdings',
                'biblio-info',
                'staff-office-delivery',
                'inter-library',
                'remote-delivery',
            ],
        ];
        yield [
            [
                [
                    'item_id' => 1,
                    'location' => 'Main Library',
                    'location_code' => 'ML',
                    'availability' => new AvailabilityStatus(true, 'Not Available'),
                    'temporary_loan_type' => 'AWAITING PICKUP',
                    'callnumber' => 'callnumber00',
                ],
            ],
            [
                // Expected templates
                'biblio-info',
                'inter-library',
            ],
        ];
        yield [
            [
                [
                    'item_id' => 1,
                    'location' => 'Main Library',
                    'location_code' => 'ML',
                    'availability' => new AvailabilityStatus(true, 'Not Available'),
                    'temporary_loan_type' => 'AGED TO LOST',
                    'callnumber' => 'callnumber00',
                ],
            ],
            [
                // Expected templates
                'biblio-info',
                'inter-library',
            ],
        ];
        yield [
            [
                [
                    'item_id' => 1,
                    'location' => 'Our Campus only',
                    'location_code' => 'ML',
                    'availability' => new AvailabilityStatus(true, 'Not Available'),
                    'temporary_loan_type' => 'AGED TO LOST',
                    'callnumber' => 'callnumber00',
                ],
            ],
            [
                // Expected templates
                'biblio-info',
            ],
        ];
        yield [
            [
                [
                    'item_id' => 1,
                    'location' => 'Somewhere',
                    'location_code' => 'ML',
                    'availability' => new AvailabilityStatus(true, 'RESTRICTED'),
                    'temporary_loan_type' => 'RESTRICTED',
                    'callnumber' => 'callnumber00',
                ],
            ],
            [
                // Expected templates
                'biblio-info',
                'inter-library',
            ],
        ];
        yield [
            [
                [
                    'item_id' => 1,
                    'location' => 'MICROFORMS',
                    'location_code' => 'ML',
                    'availability' => new AvailabilityStatus(true, 'Available'),
                    'temporary_loan_type' => 'Available',
                    'callnumber' => 'callnumber00',
                ],
            ],
            [
                // Expected templates
                'biblio-info',
                'micro-form',
                'staff-office-delivery',
                'remote-delivery',
            ],
        ];
    }

    /**
     * Test the conditions functions including the "show" prefixed function.
     *
     * @param $items    array Items for GetThis loader
     * @param $expected array Expected templates to display
     *
     * @return void
     * @throws Exception
     */
    #[DataProvider('provideConfigConditionsFunctionsData')]
    public function testConfigConditionsFunctions(array $items, array $expected): void
    {
        $getThis = $this->getGetThis();
        $getThis->setItems($items);
        $templates = $getThis->getSubTemplates();
        $this->assertEquals($expected, $templates);
        $templatesCached = $getThis->getSubTemplates();
        $this->assertEquals($expected, $templatesCached);
    }

    /**
     * Data provider.
     *
     * @return Iterator<(int | string), array>
     */
    public static function provideAdvancedConfigConditionsFunctionsData(): Iterator
    {
        yield [
            [
                // Config
                'my_template' => [
                    'condition_group' => [
                        [
                            'condition_function' => 'showMicroForm',
                        ],
                        [
                            'condition_group' => [
                                [
                                    'condition_function' => 'showHoldings',
                                ],
                                [
                                    'operator' => 'and',
                                ],
                                [
                                    'condition_function' => '!showMicroForm',
                                ],
                            ],
                        ],
                    ],
                ],

            ],
            [
                // Expected templates
                'my_template',
            ],
        ];
        yield [
            [
                // Config
                'my_template' => [
                    'condition_function' => 'showMicroForm',
                ],

            ],
            [
                // Expected templates
            ],
        ];
    }

    /**
     * Test method getSubTemplates and indirect functions relating to GetThis config.
     *
     * @param $templateConfig array Sub config for GetThis loader templates
     * @param $expected       array Expected templates to display
     *
     * @return void
     * @throws Exception
     */
    #[DataProvider('provideAdvancedConfigConditionsFunctionsData')]
    public function testAdvancedConfigConditionsFunctions(array $templateConfig, array $expected): void
    {
        $config = $this->baseConfig;
        $config['templates'] = $templateConfig;
        $getThis = $this->getGetThis($config);
        $getThis->setItems(static::getItems());
        $templates = $getThis->getSubTemplates();
        $this->assertEquals($expected, $templates);
    }

    /**
     * Test method getSubTemplates with error in config.
     *
     * @return void
     * @throws Exception
     */
    public function testConfigFormattingError(): void
    {
        $config = $this->baseConfig;
        $config['templates']['other_template']['condition_group'][] = ['wrong_key' => 'and'];
        $getThis = $this->getGetThis($config);
        $getThis->setItems(static::getItems());

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('It seems like conditions are not properly formatted, unexpected value in array');
        $getThis->getSubTemplates();
    }

    /**
     * Test method getSubTemplates with error in config.
     * String instead of array for a condition block.
     *
     * @return void
     * @throws Exception
     */
    public function testConfigErrorRandomErrorInConfig(): void
    {
        $config = $this->baseConfig;
        unset($config['templates']['holdings']['condition_function']);
        $config['templates']['holdings']['condition_group'] = 'wrong';
        $getThis = $this->getGetThis($config);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Error with the get this configuration');
        $getThis->getSubTemplates();
    }

    /**
     * Test method getSubTemplates with error in config.
     * Wrong function name.
     *
     * @return void
     * @throws Exception
     */
    public function testConfigErrorRandomErrorInConfig2(): void
    {
        $config = $this->baseConfig;
        $config['templates']['holdings']['condition_function'] = 'wrong';
        $getThis = $this->getGetThis($config);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            'Error with the get this configuration : The condition function "wrong" does not exist'
        );
        $getThis->getSubTemplates();
    }

    /**
     * Data provider.
     *
     * @return Iterator<(int | string), array>
     */
    public static function provideSubTemplateParamsData(): Iterator
    {
        yield [
            [
                // Config
                'my_template' => [
                    'view_variables' => [
                        'param1' => 'value1',
                        'param2' => 'value2',
                    ],
                ],
            ],
            [
                'my_template' => [
                    // Expected params
                    'param1' => 'value1',
                    'param2' => 'value2',
                ],
            ],
        ];
        yield [
            [
                // Config
                'my_template' => [],
            ],
            [
                // Expected params
            ],
        ];
    }

    /**
     * Test method getSubTemplateParams.
     *
     * @param $templateConfig array Sub config for GetThis loader templates
     * @param $expected       array Expected templates params
     *
     * @return void
     * @throws Exception
     */
    #[DataProvider('provideSubTemplateParamsData')]
    public function testSubTemplateParams(array $templateConfig, array $expected): void
    {
        $config = $this->baseConfig;
        $config['templates'] = $templateConfig;
        $getThis = $this->getGetThis($config);
        $getThis->setItems(static::getItems());
        $getThis->getSubTemplates();
        $this->assertEquals($expected, $getThis->getSubTemplateParams());
        $this->assertEquals($expected['my_template'] ?? [], $getThis->getSubTemplateParams('my_template'));
    }

    /**
     * Test method setSubTemplateParam.
     *
     * @return void
     * @throws ReflectionException
     */
    public function testSetSubTemplateParam(): void
    {
        $getThis = $this->getGetThis();
        $this->callMethod($getThis, 'setSubTemplateParam', ['my_template', 'param_key', 'param_value']);
        $this->assertSame(['param_key' => 'param_value'], $getThis->getSubTemplateParams('my_template'));
    }

    /**
     * Test method getItem, setItems, setItemById.
     *
     * @return void
     */
    public function testGetItemAndGetItemId(): void
    {
        $getThis = $this->getGetThis();
        $getThis->setItems([]);
        $item = $getThis->getItem();
        $this->assertNull($item);

        $getThis->setItems(static::getItems());
        $item = $getThis->getItem('2');
        $this->assertEquals($item, static::getItems()[1]);

        $getThis->setDefaultItemId('5');
        $item = $getThis->getItem();
        $this->assertEquals($item, static::getItems()[2]);

        $getThis->setDefaultItemId(null);
        $item = $getThis->getItem();
        $this->assertEquals($item, static::getItems()[0]);
    }

    /**
     * Test method getStatus.
     *
     * @return void
     */
    public function testStatus(): void
    {
        $getThis = $this->getGetThis();
        $getThis->setItems(static::getItems());

        $status = $getThis->getStatus('5');
        $this->assertSame('Unknown', $status);

        $getThis->getItem('1');
        $status = $getThis->getStatus('1');
        $this->assertEquals($status, static::getItems()[0]['availability']->getStatusDescription());

        $getThis->getItem('2');
        $status = $getThis->getStatus('2');
        $this->assertEquals($status, static::getItems()[1]['availability']->getStatusDescription());
    }

    /**
     * Test getLocation + getLocationCode.
     *
     * @return void
     */
    public function testGetLocationAndCode(): void
    {
        $getThis = $this->getGetThis();
        $this->assertSame('', $getThis->getLocation());

        $getThis->setItems(static::getItems());
        $this->assertSame('Main Library', $getThis->getLocation('1'));

        $this->assertSame('ML', $getThis->getLocationCode('1'));
    }

    /**
     * Test method getLink.
     *
     * @return void
     * @throws ReflectionException
     */
    public function testGetLink(): void
    {
        $getThis = $this->getGetThis();
        $getThis->setItems([
            ['item_id' => 2],
            ['item_id' => 3],
            ['item_id' => 1],
        ]);
        $driver = $this->getMockRecordDriver();
        $driver->method('getRealTimeHoldings')->willReturn([]);
        $getThis->setRecordDriver($driver);
        $this->assertSame('', $getThis->getLink());

        $driver = $this->getMockRecordDriver();
        $driver->method('getRealTimeHoldings')->willReturn(['holdings' => [123]]);
        $getThis->setRecordDriver($driver);
        $this->assertSame('', $getThis->getLink());

        $driver = $this->getMockRecordDriver();
        $driver->method('getRealTimeHoldings')->willReturn([
            'holdings' => [
                [
                    'items' => [
                        [
                            'item_id' => 2,
                            'link' => '',
                        ],
                        [
                            'item_id' => 3,
                            'link' => 'https://what_another_great_link.com',
                        ],
                    ],
                ],
                [
                    'items' => [
                        [
                            'item_id' => 1,
                            'link' => 'https://what_a_great_link.com',
                        ],
                    ],
                ],
            ],
        ]);
        $getThis->setRecordDriver($driver);
        $this->assertSame('https://what_a_great_link.com', $getThis->getLink());
        $this->assertSame('https://what_another_great_link.com', $getThis->getLink('3'));
    }

    /**
     * Test method getCallNumber.
     *
     * @return void
     */
    public function testGetCallNumber(): void
    {
        $getThis = $this->getGetThis();
        $getThis->setItems([
            [
                'item_id' => 9,
                'location' => 'Internet',
                'location_code' => 'Web',
                'availability' => new AvailabilityStatus(true, 'Available'),
                'temporary_loan_type' => '5 available copies',
                'callnumber' => null,
            ],
            [
                'item_id' => 16,
                'location' => 'Main Library',
                'location_code' => 'ML',
                'availability' => new AvailabilityStatus(true, 'Available'),
                'temporary_loan_type' => '',
                'callnumber' => null,
            ],
            [
                'item_id' => 18,
                'location' => 'Main Library',
                'location_code' => 'ML',
                'availability' => new AvailabilityStatus(true, 'Available'),
                'temporary_loan_type' => '',
                'callnumber' => 'call_me',
            ],
            [
                'item_id' => 42,
                'location' => 'Main Library',
                'location_code' => 'ML',
                'availability' => new AvailabilityStatus(true, 'Available'),
                'temporary_loan_type' => '',
                'callnumber' => 'on',
                'enumchron' => 'me',
                'callnumber_prefix' => 'call',
            ],
        ]);
        $this->assertSame('Online', $getThis->getCallNumber('9')['text']);
        $this->assertNull($getThis->getCallNumber('16'));
        $this->assertSame('call_me', $getThis->getCallNumber('18')['text']);
        $this->assertSame('call on me', $getThis->getCallNumber('42')['text']);
    }

    /**
     * Data provider for testShowCopyNumber().
     *
     * @return Iterator<(int | string), array>
     */
    public static function provideShowCopyNumberData(): Iterator
    {
        yield 'showCopyNumber unset && no holdings' => [null, [], false];
        yield 'showCopyNumber unset && with holdings' => [null, static::getItems(), false];
        yield 'showCopyNumber true && no holdings' => [true, [], false];
        yield 'showCopyNumber true && with holdings' => [true, static::getItems(), true];
        yield 'showCopyNumber false && no holdings' => [false, [], false];
        yield 'showCopyNumber false && with holdings' => [false, static::getItems(), false];
    }

    /**
     * Test method showCopyNumber.
     *
     * @param bool|null $showCopyNumber Value for the config property of the same name
     * @param array     $holdings       Holdings available for the record
     * @param bool      $result         Expected result whether the copy number should be shown
     *
     * @return void
     */
    #[DataProvider('provideShowCopyNumberData')]
    public function testShowCopyNumber(?bool $showCopyNumber, array $holdings, bool $result): void
    {
        $config = $this->baseConfig;
        $config['showCopyNumber'] = $showCopyNumber;
        $getThis = $this->getGetThis($config);
        $getThis->setItems($holdings);
        if ($result) {
            $this->assertNotNull($getThis->getCopyNumber('1'));
        } else {
            $this->assertNull($getThis->getCopyNumber('1'));
        }
    }

    /**
     * Test method getCopyNumber.
     *
     * @return void
     */
    public function testGetCopyNumber(): void
    {
        $config = $this->baseConfig;
        $config['showCopyNumber'] = true;
        $getThis = $this->getGetThis($config);
        $getThis->setItems(static::getItems());
        $this->assertEquals(1, $getThis->getCopyNumber('1'));
        $this->assertEquals(2, $getThis->getCopyNumber('2'));
        $this->assertNull($getThis->getCopyNumber('3'));
    }

    /**
     * Test method getSummary.
     *
     * @return void
     * @throws ReflectionException
     */
    public function testGetSummary(): void
    {
        $getThis = $this->getGetThis();
        $driver = $this->getMockRecordDriver();
        $driver->method('getSummary')->willReturnOnConsecutiveCalls([], ['sum1'], ['sum1', 'sum2']);
        $getThis->setRecordDriver($driver);

        $this->assertSame('', $getThis->getSummary());
        $this->assertSame('sum1', $getThis->getSummary());
        $this->assertSame('sum1, sum2', $getThis->getSummary());
    }

    /**
     * Test method isOnlineResource.
     *
     * @return void
     */
    public function testIsOnlineResource(): void
    {
        $getThis = $this->getGetThis();
        $this->assertFalse($getThis->isOnlineResource('456'));
        $getThis->setItems([
            [
                'item_id' => 9,
                'location' => 'Internet',
                'location_code' => 'Web',
                'availability' => new AvailabilityStatus(true, 'Available'),
                'temporary_loan_type' => '5 available copies',
                'callnumber' => null,
            ],
            [
                'item_id' => 16,
                'location' => 'Main Library',
                'location_code' => 'ML',
                'availability' => new AvailabilityStatus(true, 'Available'),
                'temporary_loan_type' => '',
                'callnumber' => null,
            ],
        ]);
        $this->assertTrue($getThis->isOnlineResource('9'));
        $this->assertFalse($getThis->isOnlineResource('16'));
    }

    /**
     * Test method isSerial.
     *
     * @return void
     * @throws ReflectionException
     */
    public function testIsSerial(): void
    {
        $getThis = $this->getGetThis();
        $driver = $this->getMockRecordDriver();
        $driver->method('getFormats')->willReturnOnConsecutiveCalls(
            [],
            ['serial1'],
            ['not a s.e.r.i.a.l.', 'another_serial'],
            ['still not'],
            ['neither', 'and finally not'],
        );
        $getThis->setRecordDriver($driver);

        $this->assertFalse($getThis->isSerial());
        $this->assertTrue($getThis->isSerial());
        $this->assertTrue($getThis->isSerial());
        $this->assertFalse($getThis->isSerial());
        $this->assertFalse($getThis->isSerial());
    }

    /**
     * Test method isOut.
     *
     * @return void
     */
    public function testIsOut(): void
    {
        $getThis = $this->getGetThis();
        $this->assertFalse($getThis->isOut('123'));

        $getThis->setItems([
            [
                'item_id' => 1,
                'location' => 'Main Library',
                'location_code' => 'ML',
                'callnumber' => 'callnumber00',
            ],
        ]);
        $this->assertFalse($getThis->isOut('1'));

        $getThis->setItems(static::getItems());
        $this->assertFalse($getThis->isOut('1'));
        $this->assertFalse($getThis->isOut('2'));
        $this->assertFalse($getThis->isOut('5'));

        $getThis->setItems([
            [
                'item_id' => 1,
                'location' => 'Main Library',
                'location_code' => 'ML',
                'callnumber' => 'callnumber00',
                'availability' => new AvailabilityStatus(true, 'Available'),
                'temporary_loan_type' => 'Awaiting pickup',
            ],
            [
                'item_id' => 2,
                'location' => 'Main Library',
                'location_code' => 'ML',
                'callnumber' => 'callnumber00',
                'availability' => new AvailabilityStatus(false, 'Checked out'),
            ],
        ]);
        $this->assertTrue($getThis->isOut('1'));
        $this->assertTrue($getThis->isOut('2'));
    }

    /**
     * Test method isAudioVideoMedia.
     *
     * @return void
     */
    public function testIsAudioVideoMedia(): void
    {
        $getThis = $this->getGetThis();
        $this->assertFalse($getThis->isAudioVideoMedia('123'));

        $getThis->setItems(static::getItems());
        $this->assertFalse($getThis->isAudioVideoMedia('1'));

        $getThis->setItems([
            [
                'item_id' => 1,
                'location' => 'Main Library',
                'location_code' => 'ML',
                'callnumber' => 'callnumber00',
                'availability' => new AvailabilityStatus(true, 'Available'),
                'temporary_loan_type' => 'Awaiting pickup',
            ],
            [
                'item_id' => 2,
                'location' => 'Main Library',
                'location_code' => 'ML',
                'callnumber' => 'disc',
                'availability' => new AvailabilityStatus(false, 'Checked out'),
            ],
        ]);
        $this->assertFalse($getThis->isAudioVideoMedia('1'));
        $this->assertTrue($getThis->isAudioVideoMedia('2'));
    }

    /**
     * Test method isLibUseOnly.
     *
     * @return void
     */
    public function testIsLibUseOnly(): void
    {
        $getThis = $this->getGetThis();
        $this->assertFalse($getThis->isLibUseOnly('123'));

        $getThis->setItems(static::getItems());
        $this->assertTrue($getThis->isLibUseOnly('1'));
        $this->assertFalse($getThis->isLibUseOnly('2'));
    }

    /**
     * Test method isUnavailable.
     *
     * @return void
     */
    public function testIsUnavailable(): void
    {
        $getThis = $this->getGetThis();
        $this->assertFalse($getThis->isUnavailable('123'));

        $getThis->setItems(static::getItems());
        $this->assertFalse($getThis->isUnavailable('1'));
        $this->assertTrue($getThis->isUnavailable('2'));
        $this->assertFalse($getThis->isUnavailable('5'));
    }

    /**
     * Test method setRecord.
     *
     * @return void
     * @throws ReflectionException
     * @throws Exception
     */
    public function testSetRecord(): void
    {
        $getThis = $this->getGetThis();
        $getThis->setItems(static::getItems());
        $templates = $getThis->getSubTemplates();
        $this->assertNotEmpty($templates);
        $driver = $this->getMockRecordDriver();
        $getThis->setRecordDriver($driver);
        $templates = $this->getProperty($getThis, 'subTemplates');
        $this->assertNull($templates);
    }

    /**
     * Test factory.
     *
     * @return void
     * @throws \PHPUnit\Framework\MockObject\Exception
     * @throws ContainerExceptionInterface&Throwable
     */
    public function testFactory(): void
    {
        $yaml = $this->createMock(YamlReader::class);
        $yaml->expects($this->once())->method('get')->willReturn([]);

        $regex = $this->createMock(Regex::class);

        $container = $this->createMock(MockContainer::class);
        $container->expects($this->exactly(2))->method('get')->willReturnMap([
            [Regex::class, $regex],
            [YamlReader::class, $yaml],
        ]);

        $factory = new GetThisLoaderFactory();
        $getThis = $factory($container, GetThisLoader::class);
        $this->assertInstanceOf(GetThisLoader::class, $getThis);
    }
}
