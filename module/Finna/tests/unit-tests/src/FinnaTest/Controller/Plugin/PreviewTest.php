<?php

/**
 * Preview Plugin Test Class
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace FinnaTest\Controller\Plugin;

use Finna\Controller\Plugin\Preview;
use Finna\Controller\RecordPreviewController;
use FinnaTest\Container\MockContainer;
use Laminas\Cache\Storage\StorageInterface;
use Laminas\Mvc\Controller\Plugin\Params;
use Laminas\Session\SessionManager;
use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Config\Config;
use VuFind\Config\PathResolver;
use VuFind\I18n\Locale\LocaleSettings;
use VuFind\RecordDriver\PluginManager as RecordPluginManager;
use VuFindHttp\HttpService;
use VuFindTest\Feature\FixtureTrait;

/**
 * Preview Plugin Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class PreviewTest extends \PHPUnit\Framework\TestCase
{
    use FixtureTrait;

    /**
     * Data provider for testPreview
     *
     * @return array
     */
    public static function previewProvider(): array
    {
        return [
            'LIDO non-validated' => [
                'lido',
                'lido_test.xml',
                '',
                '',
                [],
                [],
                [],
            ],
            'LIDO invalid' => [
                'lido',
                'lido_test.xml',
                'lido-v1.1-profile-FINNA-v0.1.xsd',
                'lido-v1.1-profile-FINNA-v0.1.sch',
                [],
                [],
                [],
            ],
            'LIDO valid' => [
                'lido',
                'lido_valid.xml',
                'lido-v1.1-profile-FINNA-v0.1.xsd',
                'lido-v1.1-profile-FINNA-v0.1.sch',
                [],
                [],
                [],
            ],
        ];
    }

    /**
     * Test preview
     *
     * @param string $format          Record format
     * @param string $record          Record fixture
     * @param string $xsd             XSD fixture
     * @param string $schematron      Schematron rule fixture
     * @param array  $errors          Expected errors
     * @param array  $warnings        Expected warnings
     * @param array  $recommendations Expected recommendations
     *
     * @return void
     *
     * @dataProvider previewProvider
     */
    public function testPreview(
        string $format,
        string $record,
        string $xsd,
        string $schematron,
        array $errors,
        array $warnings,
        array $recommendations
    ): void {
        $config = [
            'NormalizationPreview' => [
                'validation_xsd' => [
                    $format => $xsd,
                ],
                'validation_schematron' => [
                    $format => $schematron,
                ],
            ],
        ];
        $preview = $this->getPreview($config, $format, $record, $xsd, $schematron);
        $result = $preview->loadAndValidatePreviewRecord();
        $this->assertIsObject($result['driver']);
    }

    /**
     * Get Preview plugin
     *
     * @param array  $config     VuFind configuration
     * @param string $format     Record format
     * @param string $record     Record fixture
     * @param string $xsd        XSD fixture
     * @param string $schematron Schematron rule fixture
     *
     * @return MockObject&Preview
     */
    protected function getPreview(
        array $config,
        string $format,
        string $record,
        string $xsd,
        string $schematron
    ): MockObject&Preview {
        $metadata = $this->getFixture("$format/$record", 'Finna');

        $recordPluginManager = $this->getMockBuilder(RecordPluginManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $recordPluginManager->expects($this->once())
            ->method('getSolrRecord')
            ->with(['record_format' => $format])
            ->willReturnCallback(
                function ($data) {
                    $class = '\Finna\RecordDriver\Solr' . $data['record_format'];
                    $driver = new $class();
                    $driver->setRawData($data);
                    return $driver;
                }
            );

        $pathResolver = $this->getMockBuilder(PathResolver::class)
            ->disableOriginalConstructor()
            ->getMock();
        $pathResolver->expects($this->exactly(($xsd ? 1 : 0) + ($schematron ? 1 : 0)))
            ->method('getConfigPath')
            ->willReturnCallback(
                fn ($file) => $this->getFixturePath("$format/$file", 'Finna')
            );

        $localeSettings = new LocaleSettings(
            new Config(
                [
                    'Site' => [
                        'language' => 'en',
                    ],
                    'Languages' => [
                        'en' => 'English',
                    ],
                ]
            )
        );

        $container = new MockContainer($this);
        $container->add(SessionManager::class, $this->createMock(SessionManager::class));
        $container->add(RecordPluginManager::class, $recordPluginManager);
        $container->add(PathResolver::class, $pathResolver);
        $container->add(LocaleSettings::class, $localeSettings);

        $params = $this->getMockBuilder(Params::class)
            ->onlyMethods(['fromHeader', 'fromPost'])
            ->getMock();
        $params->expects($this->exactly(3))
            ->method('fromPost')
            ->willReturnCallback(
                fn ($param) => match ($param) {
                    'format' => $format,
                    'source' => 'test',
                    'data' => $metadata,
                }
            );

        $controller = $this->getMockBuilder(RecordPreviewController::class)
            ->disableOriginalConstructor()
            ->getMock();
        $controller->expects($this->once())
            ->method('plugin')
            ->with('params', null)
            ->willReturn($params);

        $httpService = $this->createMock(HttpService::class);
        $cacheStorage = $this->createMock(StorageInterface::class);

        $preview = $this->getMockBuilder(Preview::class)
            ->setConstructorArgs([$container, $config, $httpService, $cacheStorage])
            ->onlyMethods(['loadPreviewRecordData', 'getController'])
            ->getMock();

        $preview->expects($this->once())
            ->method('loadPreviewRecordData')
            ->with($metadata, $format, 'test')
            ->willReturn(
                [
                    'errors' => [],
                    'metadata' => [
                        'record_format' => $format,
                    ],
                ]
            );

        $preview->expects($this->once())
            ->method('getController')
            ->willReturn($controller);

        return $preview;
    }
}
