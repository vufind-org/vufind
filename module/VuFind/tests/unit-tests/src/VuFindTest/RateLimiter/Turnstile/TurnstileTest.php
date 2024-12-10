<?php

/**
 * Turnstile Test Class
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\RateLimiter\Turnstile;

use Laminas\Http\Response;
use Laminas\Mvc\MvcEvent;
use Laminas\Router\Http\RouteMatch;
use VuFind\RateLimiter\Turnstile\Turnstile;
use VuFindHttp\HttpService;

/**
 * Turnstile Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class TurnstileTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test reporting if a Turnstile challenge is allowed based
     * on the controller class.
     *
     * @return void
     */
    public function testIsChallengeAllowed()
    {
        $event = new MvcEvent();
        $turnstile = $this->buildTurnstile();

        // Configured to skip the challenge in the default config
        $event->setRouteMatch(new RouteMatch(['controller' => 'AJAX']));
        $this->assertFalse($turnstile->isChallengeAllowed($event));

        $event->setRouteMatch(new RouteMatch(['controller' => 'SearchApi']));
        $this->assertFalse($turnstile->isChallengeAllowed($event));

        $event->setRouteMatch(new RouteMatch(['controller' => 'Cover']));
        $this->assertFalse($turnstile->isChallengeAllowed($event));

        // Other example routes
        $event->setRouteMatch(new RouteMatch(['controller' => 'Search']));
        $this->assertTrue($turnstile->isChallengeAllowed($event));

        $event->setRouteMatch(new RouteMatch(['controller' => 'Record']));
        $this->assertTrue($turnstile->isChallengeAllowed($event));
    }

    /**
     * Test validating and returning positive and negative results,
     * and test reporting that there was no prior result.
     *
     * @return void
     */
    public function testValidateAndCacheResults()
    {
        $policyId = 'policy1';
        $clientIp = '111.222.123.213';
        foreach ([true, false, null] as $result) {
            $turnstile = $this->buildTurnstile($result);

            if ($result != null) {
                $turnstile->setHttpService($this->buildHttpService(
                    ['success' => $result]
                ));
                $validationResult = $turnstile->validateToken('some_token', $policyId, $clientIp);
                $this->assertEquals($result, $validationResult);
            }

            $priorResult = $turnstile->checkPriorResult($policyId, $clientIp);
            $this->assertEquals($result, $priorResult);
        }
    }

    /**
     * Build a Turnstile object.
     *
     * @param $cacheResult The result to report for a cache lookup.
     *
     * @return Turnstile
     */
    protected function buildTurnstile($cacheResult = null): Turnstile
    {
        $config = [
            'Policies' => [
                'policy1' => [
                    'turnstileRateLimiterSettings' => [
                        'limit' => '100',
                    ],
                ],
            ],
            'Turnstile' => [
                'secretKey' => '12345,',
            ],
        ];

        $cache = $this->getMockBuilder(\Laminas\Cache\Storage\StorageInterface::class)->getMock();
        $cache->method('getItem')->willReturn($cacheResult);

        $turnstile = new Turnstile($config, $cache);
        return $turnstile;
    }

    /**
     * Build a mock HttpService that returns a JSON
     * representation of the given data.
     *
     * @param $returnData The data
     *
     * @return HttpService
     */
    protected function buildHttpService($returnData): HttpService
    {
        $httpService = $this->createMock(HttpService::class);
        $response = $this->createMock(Response::class);
        $response->method('isOk')->willReturn(true);
        $response->method('getBody')->willReturn(json_encode($returnData));
        $httpService->method('post')->willReturn($response);
        return $httpService;
    }
}
