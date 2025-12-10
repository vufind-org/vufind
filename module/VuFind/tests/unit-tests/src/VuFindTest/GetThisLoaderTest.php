<?php

/**
 * Get This loader Test Class
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
use Laminas\View\HelperPluginManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use VuFind\Config\YamlReader;
use VuFind\GetThis\GetThisLoader;
use VuFind\GetThis\GetThisLoaderFactory;
use VuFind\ILS\Logic\AvailabilityStatus;
use VuFind\RecordDriver\SolrDefault;
use VuFind\Regex\Regex;
use VuFind\View\Helper\Root\Translate;
use VuFindTest\Container\MockContainer;
use VuFindTest\Feature\ConfigRelatedServicesTrait;
use VuFindTest\Feature\FixtureTrait;
use VuFindTest\Feature\ReflectionTrait;

/**
 * Get This loader Test Class
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
     * Yaml reader needed for GetThis
     *
     * @var YamlReader
     */
    protected YamlReader $yamlReader;

    /**
     * GetThis config
     *
     * @var array
     */
    protected array $config;

    /**
     * Loader itself
     *
     * @var GetThisLoader
     */
    protected GetThisLoader $getThis;

    /**
     * Test setUp function, before every test
     *
     * @return void
     * @throws \PHPUnit\Framework\MockObject\Exception
     */
    public function setUp(): void
    {
        $this->yamlReader = new YamlReader($this->getPathResolver());
        $this->config = $this->yamlReader->get(GetThisLoader::CONFIG_FILENAME);
        $regexConfig = $this->yamlReader->get('Regex.yaml');
        $regexConfig['LOCATION_EXCLUSIVE'][] = '/OUR CAMPUS/i';
        $translator = $this->createMock(Translate::class);
        $translator->method('translate')->willReturnCallback(fn($p) => $p);
        $this->getThis = new GetThisLoader(
            $this->config,
            new Regex($regexConfig),
            $translator
        );
    }

    /**
     * Using reflection class replace current GetThis config
     *
     * @param array $config Config to set
     *
     * @return void
     */
    public function setGetThisConfig(array $config): void
    {
        try {
            $this->setProperty($this->getThis, 'config', $config);
        } catch (ReflectionException $e) {
            die('Reflection exception when trying to set the config: ' . $e->getMessage());
        }
    }

    /**
     * Create a mock driver for solr
     *
     * @return SolrDefault|MockObject
     */
    public function getMockRecordDriver(): SolrDefault|MockObject
    {
        try {
            return $this->createMock(SolrDefault::class);
        } catch (\PHPUnit\Framework\MockObject\Exception $e) {
            die('An exception has occurred while creating a mock for the record driver: ' . $e->getMessage());
        }
    }

    /**
     * Items to be re-used
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
     * Test method areItemsSupported
     *
     * @return void
     */
    public function testItemsSupported()
    {
        $supported = $this->getThis->areItemsSupported(self::getItems());
        $this->assertTrue($supported);
        $this->assertFalse($this->getThis->areItemsSupported([]));
    }

    /**
     * Test method getItems, getItem, setItems, setItemById
     *
     * @return void
     */
    public function testItems()
    {
        $this->assertEmpty($this->getThis->getItems());
        $this->getThis->setItems(self::getItems());
        $this->assertEquals(self::getItems(), $this->getThis->getItems());
        $this->assertEquals(1, $this->getThis->getItem()['item_id']);
        $this->getThis->setItemById(2);
        $this->assertEquals(2, $this->getThis->getItem()['item_id']);
        $this->assertEquals(1, $this->getThis->getItem(1)['item_id']);
    }

    /**
     * Data provider
     *
     * @return array[]
     */
    public static function provideConfigConditionsFunctionsData(): array
    {
        return [
            [
                [
                    // Items
                ],
                [
                    // Expected templates
                    'biblio-info',
                ],
            ],
            [
                self::getItems(),
                [
                    // Expected templates
                    'holdings',
                    'biblio-info',
                    'staff-office-delivery',
                    'inter-library',
                    'remote-delivery',
                ],
            ],
            [
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
            ],
            [
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
            ],
            [
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
            ],
            [
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
            ],
            [
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
            ],
        ];
    }

    /**
     * Test the conditions functions including the "show" prefixed function
     *
     * @param $items    array Items for GetThis loader
     * @param $expected array Expected templates to display
     *
     * @return void
     * @throws Exception
     */
    #[DataProvider('provideConfigConditionsFunctionsData')]
    public function testConfigConditionsFunctions(array $items, array $expected)
    {
        $this->getThis->setItems($items);
        $templates = $this->getThis->getSubTemplates();
        $this->assertEquals($expected, $templates);
        $templatesCached = $this->getThis->getSubTemplates();
        $this->assertEquals($expected, $templatesCached);
    }

    /**
     * Data provider
     *
     * @return array[]
     */
    public static function provideAdvancedConfigConditionsFunctionsData(): array
    {
        return [
            [
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
            ],
            [
                [
                    // Config
                    'my_template' => [
                        'condition_function' => 'showMicroForm',
                    ],

                ],
                [
                    // Expected templates
                ],
            ],
        ];
    }

    /**
     * Test method getSubTemplates and indirect functions relating to GetThis config
     *
     * @param $templateConfig array Sub config for GetThis loader templates
     * @param $expected       array Expected templates to display
     *
     * @return void
     * @throws Exception
     */
    #[DataProvider('provideAdvancedConfigConditionsFunctionsData')]
    public function testAdvancedConfigConditionsFunctions(array $templateConfig, array $expected)
    {
        $config = $this->config;
        $config['templates'] = $templateConfig;
        $this->setGetThisConfig($config);
        $this->getThis->setItems(self::getItems());
        $templates = $this->getThis->getSubTemplates();
        $this->assertEquals($expected, $templates);
    }

    /**
     * Test method getSubTemplates with error in config
     *
     * @return void
     * @throws Exception
     */
    public function testConfigFormattingError()
    {
        $config = $this->config;
        $config['templates']['other_template']['condition_group'][] = ['wrong_key' => 'and'];
        $this->setGetThisConfig($config);
        $this->getThis->setItems(self::getItems());

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('It seems like conditions are not properly formatted, unexpected value in array');
        $this->getThis->getSubTemplates();
    }

    /**
     * Test method getSubTemplates with error in config
     *
     * @return void
     * @throws Exception
     */
    public function testConfigErrorRandomErrorInConfig()
    {
        $config = $this->config;
        // This function is not intended for use as a condition function
        // It requires parameter
        // It is used only for the sake of generating an Exception
        $config['templates']['holdings']['condition_function'] = 'isConditionsAnd';
        $this->setGetThisConfig($config);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Error with the get this configuration');
        $this->getThis->getSubTemplates();
    }

    /**
     * Data provider
     *
     * @return array
     */
    public static function provideSubTemplateParamsData(): array
    {
        return [
            [
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
            ],
            [
                [
                    // Config
                    'my_template' => [],
                ],
                [
                    // Expected params
                ],
            ],
        ];
    }

    /**
     * Test method getSubTemplateParams
     *
     * @param $templateConfig array Sub config for GetThis loader templates
     * @param $expected       array Expected templates params
     *
     * @return void
     * @throws Exception
     */
    #[DataProvider('provideSubTemplateParamsData')]
    public function testSubTemplateParams(array $templateConfig, array $expected)
    {
        $config = $this->config;
        $config['templates'] = $templateConfig;
        $this->setGetThisConfig($config);
        $this->getThis->setItems(self::getItems());
        $this->getThis->getSubTemplates();
        $this->assertEquals($expected, $this->getThis->getSubTemplateParams());
        $this->assertEquals($expected['my_template'] ?? [], $this->getThis->getSubTemplateParams('my_template'));
    }

    /**
     * Test method setSubTemplateParam
     *
     * @return void
     * @throws ReflectionException
     */
    public function testSetSubTemplateParam()
    {
        $reflection = new ReflectionClass($this->getThis);
        $method = $reflection->getMethod('setSubTemplateParam');
        $method->invoke($this->getThis, 'my_template', 'param_key', 'param_value');
        $this->assertEquals(['param_key' => 'param_value'], $this->getThis->getSubTemplateParams('my_template'));
    }

    /**
     * Test method getItem, setItems, setItemById
     *
     * @return void
     */
    public function testGetItemAndGetItemId()
    {
        $this->getThis->setItems([]);
        $item = $this->getThis->getItem();
        $this->assertNull($item);

        $this->getThis->setItems(self::getItems());
        $item = $this->getThis->getItem(2);
        $this->assertEquals($item, self::getItems()[1]);

        $this->getThis->setItemById(5);
        $item = $this->getThis->getItem();
        $this->assertEquals($item, self::getItems()[2]);

        $this->getThis->setItemById(null);
        $item = $this->getThis->getItem();
        $this->assertEquals($item, self::getItems()[0]);
    }

    /**
     * Test method getStatus
     *
     * @return void
     */
    public function testStatus()
    {
        $this->getThis->setItems(self::getItems());

        $status = $this->getThis->getStatus(5);
        $this->assertEquals('Unknown', $status);

        $this->getThis->getItem(1);
        $status = $this->getThis->getStatus(1);
        $this->assertEquals($status, self::getItems()[0]['availability']->getStatusDescription());

        $this->getThis->getItem(2);
        $status = $this->getThis->getStatus(2);
        $this->assertEquals($status, self::getItems()[1]['availability']->getStatusDescription());
    }

    /**
     * Test getLocation + getLocationCode
     *
     * @return void
     */
    public function testGetLocationAndCode()
    {
        $this->assertEquals('', $this->getThis->getLocation());

        $this->getThis->setItems(self::getItems());
        $this->assertEquals('Main Library', $this->getThis->getLocation(1));

        $this->assertEquals('ML', $this->getThis->getLocationCode(1));
    }

    /**
     * Test method getLink
     *
     * @return void
     * @throws ReflectionException
     */
    public function testGetLink()
    {
        $this->getThis->setItems([
            ['item_id' => 2],
            ['item_id' => 3],
            ['item_id' => 1],
        ]);
        $driver = $this->getMockRecordDriver();
        $driver->method('getRealTimeHoldings')->willReturn([]);
        $this->setProperty($this->getThis, 'record', $driver);
        $this->assertEquals('', $this->getThis->getLink());

        $driver = $this->getMockRecordDriver();
        $driver->method('getRealTimeHoldings')->willReturn(['holdings' => [123]]);
        $this->setProperty($this->getThis, 'record', $driver);
        $this->assertEquals('', $this->getThis->getLink());

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
        $this->setProperty($this->getThis, 'record', $driver);
        $this->assertEquals('https://what_a_great_link.com', $this->getThis->getLink());
        $this->assertEquals('https://what_another_great_link.com', $this->getThis->getLink(3));
    }

    /**
     * Test method getCallNumber
     *
     * @return void
     */
    public function testGetCallNumber()
    {
        $this->getThis->setItems([
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
        $this->assertEquals('Online', $this->getThis->getCallNumber(9));
        $this->assertNull($this->getThis->getCallNumber(16));
        $this->assertEquals('call_me', $this->getThis->getCallNumber(18));
        $this->assertEquals('call on me', $this->getThis->getCallNumber(42));
    }

    /**
     * Test method showCopyNumber
     *
     * @return void
     */
    public function testShowCopyNumber()
    {
        $this->assertFalse($this->getThis->showCopyNumber());

        $this->getThis->setItems(self::getItems());
        $this->assertTrue($this->getThis->showCopyNumber());

        $config = $this->config;
        $config['showCopyNumber'] = false;
        $this->setGetThisConfig($config);
        $this->getThis->setItems(self::getItems());
        $this->assertFalse($this->getThis->showCopyNumber());

        $config = $this->config;
        unset($config['showCopyNumber']);
        $this->setGetThisConfig($config);
        $this->getThis->setItems(self::getItems());
        $this->assertFalse($this->getThis->showCopyNumber());
    }

    /**
     * Test method getCopyNumber
     *
     * @return void
     */
    public function testGetCopyNumber()
    {
        $this->getThis->setItems(self::getItems());
        $this->assertEquals(1, $this->getThis->getCopyNumber(1));
        $this->assertEquals(2, $this->getThis->getCopyNumber(2));
        $this->assertNull($this->getThis->getCopyNumber(3));
    }

    /**
     * Test method getSummary
     *
     * @return void
     * @throws ReflectionException
     */
    public function testGetSummary()
    {
        $driver = $this->getMockRecordDriver();
        $driver->method('getSummary')->willReturnOnConsecutiveCalls([], ['sum1'], ['sum1', 'sum2']);
        $this->setProperty($this->getThis, 'record', $driver);

        $this->assertEquals('', $this->getThis->getSummary());
        $this->assertEquals('sum1', $this->getThis->getSummary());
        $this->assertEquals('sum1, sum2', $this->getThis->getSummary());
    }

    /**
     * Test method isOnlineResource
     *
     * @return void
     */
    public function testIsOnlineResource()
    {
        $this->assertFalse($this->getThis->isOnlineResource(456));
        $this->getThis->setItems([
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
        $this->assertTrue($this->getThis->isOnlineResource(9));
        $this->assertFalse($this->getThis->isOnlineResource(16));
    }

    /**
     * Test method isSerial
     *
     * @return void
     * @throws ReflectionException
     */
    public function testIsSerial()
    {
        $driver = $this->getMockRecordDriver();
        $driver->method('getFormats')->willReturnOnConsecutiveCalls(
            [],
            ['serial1'],
            ['not a s.e.r.i.a.l.', 'another_serial'],
            ['still not'],
            ['neither', 'and finally not'],
        );
        $this->setProperty($this->getThis, 'record', $driver);

        $this->assertFalse($this->getThis->isSerial());
        $this->assertTrue($this->getThis->isSerial());
        $this->assertTrue($this->getThis->isSerial());
        $this->assertFalse($this->getThis->isSerial());
        $this->assertFalse($this->getThis->isSerial());
    }

    /**
     * Test method isOut
     *
     * @return void
     */
    public function testIsOut()
    {
        $this->assertFalse($this->getThis->isOut(123));

        $this->getThis->setItems([
            [
                'item_id' => 1,
                'location' => 'Main Library',
                'location_code' => 'ML',
                'callnumber' => 'callnumber00',
            ],
        ]);
        $this->assertFalse($this->getThis->isOut(1));

        $this->getThis->setItems(self::getItems());
        $this->assertFalse($this->getThis->isOut(1));
        $this->assertFalse($this->getThis->isOut(2));
        $this->assertFalse($this->getThis->isOut(5));

        $this->getThis->setItems([
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
        $this->assertTrue($this->getThis->isOut(1));
        $this->assertTrue($this->getThis->isOut(2));
    }

    /**
     * Test method isAudioVideoMedia
     *
     * @return void
     */
    public function testIsAudioVideoMedia()
    {
        $this->assertFalse($this->getThis->isAudioVideoMedia(123));

        $this->getThis->setItems(self::getItems());
        $this->assertFalse($this->getThis->isAudioVideoMedia(1));

        $this->getThis->setItems([
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
        $this->assertFalse($this->getThis->isAudioVideoMedia(1));
        $this->assertTrue($this->getThis->isAudioVideoMedia(2));
    }

    /**
     * Test method isLibUseOnly
     *
     * @return void
     */
    public function testIsLibUseOnly()
    {
        $this->assertFalse($this->getThis->isLibUseOnly(123));

        $this->getThis->setItems(self::getItems());
        $this->assertTrue($this->getThis->isLibUseOnly(1));
        $this->assertFalse($this->getThis->isLibUseOnly(2));
    }

    /**
     * Test method isUnavailable
     *
     * @return void
     */
    public function testIsUnavailable()
    {
        $this->assertFalse($this->getThis->isUnavailable(123));

        $this->getThis->setItems(self::getItems());
        $this->assertFalse($this->getThis->isUnavailable(1));
        $this->assertTrue($this->getThis->isUnavailable(2));
        $this->assertFalse($this->getThis->isUnavailable(5));
    }

    /**
     * Test method setRecord
     *
     * @return void
     * @throws ReflectionException
     */
    public function testSetRecord()
    {
        $this->getThis->setItems(self::getItems());
        $templates = $this->getThis->getSubTemplates();
        $this->assertNotEmpty($templates);
        $driver = $this->getMockRecordDriver();
        $this->getThis->setRecord($driver);
        $templates = $this->getProperty($this->getThis, 'subTemplates');
        $this->assertNull($templates);
    }

    /**
     * Test factory
     *
     * @return void
     * @throws \PHPUnit\Framework\MockObject\Exception
     * @throws \Psr\Container\ContainerExceptionInterface&\Throwable
     */
    public function testFactory()
    {
        $yaml = $this->createMock(YamlReader::class);
        $yaml->expects($this->once())->method('get')->willReturn([]);

        $regex = $this->createMock(Regex::class);

        $translator = $this->createMock(Translate::class);
        $translator->method('translate')->willReturnCallback(fn ($p) => $p);

        $viewHelperManager = $this->createMock(HelperPluginManager::class);
        $viewHelperManager->expects($this->once())->method('get')->willReturn($translator);

        $container = $this->createMock(MockContainer::class);
        $container->method('get')->willReturnMap([
            [Regex::class, $regex],
            [YamlReader::class, $yaml],
            ['ViewHelperManager', $viewHelperManager],
        ]);

        $factory = new GetThisLoaderFactory();
        $getThis = $factory($container, GetThisLoader::class);
        $this->assertInstanceOf(GetThisLoader::class, $getThis);
    }
}
