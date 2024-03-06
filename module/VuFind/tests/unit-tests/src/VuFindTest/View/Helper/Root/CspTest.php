<?php

/**
 * Csp View Helper Test Class
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\View\Helper\Root;

/**
 * Csp View Helper Test Class
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
     * Test disablePolicy when the CSP is enabled
     *
     * @return void
     */
    public function testDisablePolicyWithCspEnabled(): void
    {
        $config = new \Laminas\Config\Config(
            [
                'CSP' => [
                    'use_nonce' => true,
                    'enabled' => [
                        'testing' => true,
                    ],
                ],
                'Directives' => [
                    'script-src' => [
                        "'unsafe-inline'",
                    ],
                ],
            ]
        );
        $nonceGenerator = new \VuFind\Security\NonceGenerator();
        $cspHeaderGenerator
            = new \VuFind\Security\CspHeaderGenerator($config, $nonceGenerator);

        $response = new \Laminas\Http\Response();
        $headers = $response->getHeaders();
        $header = $cspHeaderGenerator->getHeader();
        $this->assertInstanceOf(
            \Laminas\Http\Header\ContentSecurityPolicy::class,
            $header
        );
        $headers->addHeader($header);
        $added = $headers->get('Content-Security-Policy');
        $this->assertEquals(1, $added->count());

        $csp = new \VuFind\View\Helper\Root\Csp($response, $nonceGenerator->getNonce());
        $csp->disablePolicy();
        $this->assertFalse($headers->get('Content-Security-Policy'));
    }

    /**
     * Test disablePolicy when the CSP is in "report only" mode
     *
     * @return void
     */
    public function testDisablePolicyWithCspReportOnly(): void
    {
        $config = new \Laminas\Config\Config(
            [
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
            ]
        );
        $nonceGenerator = new \VuFind\Security\NonceGenerator();
        $cspHeaderGenerator
            = new \VuFind\Security\CspHeaderGenerator($config, $nonceGenerator);

        $response = new \Laminas\Http\Response();
        $headers = $response->getHeaders();
        $header = $cspHeaderGenerator->getHeader();
        $this->assertInstanceOf(
            \Laminas\Http\Header\ContentSecurityPolicyReportOnly::class,
            $header
        );
        $headers->addHeader($header);
        $added = $headers->get('Content-Security-Policy-Report-Only');
        $this->assertFalse(is_iterable($added));

        $csp = new \VuFind\View\Helper\Root\Csp($response, $nonceGenerator->getNonce());
        $csp->disablePolicy();
        $this->assertFalse($headers->get('Content-Security-Policy-Report-Only'));
    }

    /**
     * Test disablePolicy when the CSP is disabled
     *
     * @return void
     */
    public function testDisablePolicyWithCspDisabled(): void
    {
        $config = new \Laminas\Config\Config(
            [
                'CSP' => [
                    'use_nonce' => true,
                    'enabled' => [
                        'testing' => false,
                    ],
                ],
                'Directives' => [
                    'script-src' => [
                        "'unsafe-inline'",
                    ],
                ],
            ]
        );
        $nonceGenerator = new \VuFind\Security\NonceGenerator();
        $cspHeaderGenerator
            = new \VuFind\Security\CspHeaderGenerator($config, $nonceGenerator);

        $response = new \Laminas\Http\Response();
        $header = $cspHeaderGenerator->getHeader();
        $this->assertNull($header);

        $csp = new \VuFind\View\Helper\Root\Csp($response, $nonceGenerator->getNonce());
        $csp->disablePolicy();
    }
}
