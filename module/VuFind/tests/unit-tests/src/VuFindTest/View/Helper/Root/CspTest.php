<?php

/**
 * Csp View Helper Test Class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2021.
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
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\View\Helper\Root;

/**
 * Csp View Helper Test Class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class CspTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test disablePolicy when the CSP is enabled.
     *
     * @return void
     */
    public function testDisablePolicyWithCspEnabled(): void
    {
        $config = [
            'CSP' => [
                'use_nonce' => true,
                'enabled' => [
                    'development' => true,
                    'production' => true,
                    'testing' => true,
                ],
            ],
            'Directives' => [
                'script-src' => [
                    "'unsafe-inline'",
                ],
            ],
        ];
        $nonceGenerator = new \VuFind\Security\NonceGenerator();
        $cspHeaderGenerator
            = new \VuFind\Security\CspHeaderGenerator($config, $nonceGenerator);

        $header = $cspHeaderGenerator->getCspHeader();
        $this->assertIsArray($header);
        $this->assertSame('Content-Security-Policy', $header['name'] ?? null);

        $csp = new \VuFind\View\Helper\Root\Csp($cspHeaderGenerator, $nonceGenerator);
        $csp->disablePolicy();
        $this->assertNull($cspHeaderGenerator->getCspHeader());
    }

    /**
     * Test disablePolicy when the CSP is in "report only" mode.
     *
     * @return void
     */
    public function testDisablePolicyWithCspReportOnly(): void
    {
        $config = [
            'CSP' => [
                'use_nonce' => true,
                'enabled' => [
                    'testing' => 'report_only',
                ],
            ],
            'Directives' => [
                'script-src' => [
                    "'unsafe-inline'",
                ],
            ],
        ];
        $nonceGenerator = new \VuFind\Security\NonceGenerator();
        $cspHeaderGenerator = new \VuFind\Security\CspHeaderGenerator($config, $nonceGenerator);

        $response = new \Laminas\Http\Response();
        $headers = $response->getHeaders();
        $header = $cspHeaderGenerator->getCspHeader();
        $this->assertIsArray($header);
        $this->assertSame('Content-Security-Policy-Report-Only', $header['name'] ?? null);

        $csp = new \VuFind\View\Helper\Root\Csp($cspHeaderGenerator, $nonceGenerator);
        $csp->disablePolicy();
        $this->assertNull($cspHeaderGenerator->getCspHeader());
    }

    /**
     * Test disablePolicy when the CSP is disabled.
     *
     * @return void
     */
    public function testDisablePolicyWithCspDisabled(): void
    {
        $config = [
            'CSP' => [
                'use_nonce' => true,
                'enabled' => [
                    'development' => false,
                    'production' => false,
                    'testing' => false,
                ],
            ],
            'Directives' => [
                'script-src' => [
                    "'unsafe-inline'",
                ],
            ],
        ];
        $nonceGenerator = new \VuFind\Security\NonceGenerator();
        $cspHeaderGenerator = new \VuFind\Security\CspHeaderGenerator($config, $nonceGenerator);

        $header = $cspHeaderGenerator->getCspHeader();
        $this->assertNull($header);

        $csp = new \VuFind\View\Helper\Root\Csp($cspHeaderGenerator, $nonceGenerator);
        $csp->disablePolicy();
    }
}
