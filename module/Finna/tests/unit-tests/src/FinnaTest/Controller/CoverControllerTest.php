<?php

/**
 * CoverController test class
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace FinnaTest\Controller;

use Finna\Controller\CoverController;
use Finna\Cover\Loader;
use FinnaTest\Traits\MockLoadersTrait;
use FinnaTest\Traits\MockServicesTrait;
use Generator;
use Laminas\Http\Headers;
use Laminas\Http\PhpEnvironment\Response;
use Laminas\Stdlib\Parameters;
use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Config\Config;
use VuFind\Cover\CachingProxy;
use VuFind\Http\PhpEnvironment\Request;
use VuFind\Session\Settings;
use VuFindTest\Feature\FixtureTrait;

/**
 * CoverController test class
 *
 * @category VuFind
 * @package  Tests
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class CoverControllerTest extends \PHPUnit\Framework\TestCase
{
    use FixtureTrait;
    use MockLoadersTrait;
    use MockServicesTrait;

    /**
     * Data provider for testing image piping
     *
     * @return Generator
     */
    public static function getTestImagePipedData(): Generator
    {
        $configWithKeys = [
          'Content' => [
            'api_keys' => [
              'test_key_123',
            ],
          ],
        ];
        $datasourceConfigPiped = [
          'test' => [
            'permissions' => [
              'image_piping' => true,
            ],
          ],
        ];
        $requestWithApiKey = [
          'headers' => [
            'X-API-KEY' => 'test_key_123',
          ],
          'query' => [
            'id' => 'test.123',
            'source' => DEFAULT_SEARCH_BACKEND,
            'size' => 'large',
            'index' => 0,
          ],
        ];
        $requestWithApiKeyAndWrongIndex = [
          'headers' => [
            'X-API-KEY' => 'test_key_123',
          ],
          'query' => [
            'id' => 'test.123',
            'source' => DEFAULT_SEARCH_BACKEND,
            'size' => 'large',
            'index' => 10,
          ],
        ];
        $requestWithWrongApiKey = [
          'headers' => [
            'X-API-KEY' => 'not_going_to_work_123',
          ],
          'query' => [
            'id' => 'test.123',
            'source' => DEFAULT_SEARCH_BACKEND,
            'size' => 'large',
            'index' => 1,
          ],
        ];
        $datasourceConfigUnPiped = [
          'test' => [
            'permissions' => [
              'image_piping' => false,
            ],
          ],
        ];
        $requestWithoutApiKey = [
          'headers' => [

          ],
          'query' => [
            'id' => 'very_record.123',
            'source' => DEFAULT_SEARCH_BACKEND,
            'size' => 'large',
            'index' => 0,
          ],
        ];
        $requestWithMissingRecord = [
          'headers' => [
            'X-API-KEY' => 'test_key_123',
          ],
          'query' => [
            'id' => 'test.missing',
            'source' => DEFAULT_SEARCH_BACKEND,
            'size' => 'large',
            'index' => 0,
          ],
        ];
        $expectedResponse = new Response();
        $expectedResponse->setStatusCode(Response::STATUS_CODE_200);
        yield 'test with success' => [
          $configWithKeys,
          $datasourceConfigPiped,
          $requestWithApiKey,
          $expectedResponse,
        ];

        $expectedResponse = new Response();
        $expectedResponse->setStatusCode(Response::STATUS_CODE_401);
        yield 'test with wrong api key in request' => [
          $configWithKeys,
          $datasourceConfigPiped,
          $requestWithWrongApiKey,
          $expectedResponse,
        ];

        $expectedResponse = new Response();
        $expectedResponse->setStatusCode(Response::STATUS_CODE_401);
        yield 'test with no api key in request' => [
          $configWithKeys,
          $datasourceConfigPiped,
          $requestWithoutApiKey,
          $expectedResponse,
        ];

        $expectedResponse = new Response();
        $expectedResponse->setStatusCode(Response::STATUS_CODE_403);
        yield 'test with no permission to pipe image but has api key' => [
          $configWithKeys,
          $datasourceConfigUnPiped,
          $requestWithApiKey,
          $expectedResponse,
        ];

        $expectedResponse = new Response();
        $expectedResponse->setStatusCode(Response::STATUS_CODE_401);
        yield 'test with no permission to pipe image and no api key' => [
          $configWithKeys,
          $datasourceConfigUnPiped,
          $requestWithoutApiKey,
          $expectedResponse,
        ];

        $expectedResponse = new Response();
        $expectedResponse->setStatusCode(Response::STATUS_CODE_404);
        yield 'test with api key and permission but wrong index' => [
          $configWithKeys,
          $datasourceConfigPiped,
          $requestWithApiKeyAndWrongIndex,
          $expectedResponse,
        ];

        $expectedResponse = new Response();
        $expectedResponse->setStatusCode(Response::STATUS_CODE_404);
        yield 'test with missing record' => [
          $configWithKeys,
          $datasourceConfigPiped,
          $requestWithMissingRecord,
          $expectedResponse,
        ];
    }

    /**
     * Test pipe functionality
     *
     * @param array    $config           Main config
     * @param array    $datasourceConfig Datasource config
     * @param array    $params           Parameters for the action
     * @param Response $expected         Expected result
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestImagePipedData')]
    public function testImagePiped(array $config, array $datasourceConfig, array $params, Response $expected): void
    {
        $coverController = $this->getCoverController($config, $datasourceConfig, $params);
        $result = $coverController->pipeAction();
        $this->assertEquals($expected, $result);
    }

    /**
     * Get cover controller for testing
     *
     * @param array $config           Main config
     * @param array $datasourceConfig Datasource config
     * @param array $params           Test params for requesting image
     *
     * @return MockObject&CoverController
     */
    protected function getCoverController(
        array $config = [],
        array $datasourceConfig = [],
        array $params = []
    ): MockObject&CoverController {
        $records = [
          [
            'fixture' => 'lido/lido_test.xml',
            'raw_data' => [
              'datasource_str_mv' => ['test'],
              'id' => 'test.123',
              'source' => DEFAULT_SEARCH_BACKEND,
            ],
          ],
        ];
        $recordLoader = $this->getFinnaRecordLoader($records);

        $fileLoader = $this->getFinnaFileLoader([
          'https://largekuvanlinkki2.com',
          'https://largekuvanlinkki.com',
        ]);

        $accessTokenService = $this->getFinnaAccessTokenService();

        $coverControllerMock = $this->getMockBuilder(CoverController::class)
          ->onlyMethods(['getRequest'])->setConstructorArgs([
            $this->getMockBuilder(Loader::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(CachingProxy::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(Settings::class)->disableOriginalConstructor()->getMock(),
            new Config($datasourceConfig),
            $recordLoader,
            $config['Content'] ?? [],
            $fileLoader,
            $accessTokenService,
          ])->getMock();

        $testRequest = new Request();
        $headers = new Headers();
        $headers->addHeaders($params['headers'] ?? []);
        $testRequest->setHeaders($headers);
        $query = new Parameters($params['query'] ?? []);
        $testRequest->setQuery($query);
        $coverControllerMock->expects($this->any())->method('getRequest')->willReturn($testRequest);
        return $coverControllerMock;
    }
}
