<?php

/**
 * FinnaUrlCheckTrait Test Class
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2020.
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

namespace FinnaTest\Content;

/**
 * FinnaUrlCheckTrait Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class FinnaUrlCheckTraitTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test with empty configuration
     *
     * @return void
     */
    public function testEmptyConfig()
    {
        $tester = $this->getMockCheckTestClass();
        $this->assertTrue($tester->check('http://localhost'));
        $this->assertTrue($tester->check('https://localhost'));
        $this->assertFalse($tester->check('ftp://localhost'));
        $this->assertFalse($tester->check('foo'));
    }

    /**
     * Test with full configuration
     *
     * @return void
     */
    public function testFullConfig()
    {
        $config = [
            'Record' => [
                'disallowed_external_hosts' => [
                    '/^127\..*/',
                    'localhost',
                    '::1',
                ],
                'allowed_external_hosts' => [
                    'imageserver',
                    'foo4',
                    'foo6',
                    '192.168.0.1',
                    '/^172\./',
                    '/\.images$/',
                    '::2',
                ],
            ],
        ];
        $ipv4map = [
            'foo4' => '192.168.0.1',
            'foo4.image' => '192.168.0.2',
        ];
        $ipv6map = [
            'foo6' => '::2',
            'foo6.image' => '::3',
        ];

        $tester = $this->getMockCheckTestClass($config, $ipv4map, $ipv6map);

        $this->assertFalse($tester->check('http://127.0.0.1/img'));
        $this->assertFalse($tester->check('http://localhost/img'));
        $this->assertFalse($tester->check('https://localhost/img'));
        $this->assertFalse($tester->check('http://[::1]/img'));
        $this->assertFalse($tester->check('http://unknown/img'));
        $this->assertFalse($tester->check('http://1.172.0.1/img'));
        $this->assertFalse($tester->check('http://1.172.0.1/img'));
        $this->assertFalse($tester->check('http://imageserver2/img'));
        $this->assertFalse($tester->check('http://foo4.image/img'));
        $this->assertFalse($tester->check('http://foo6.image/img'));

        $this->assertTrue($tester->check('http://172.0.0.1/img'));
        $this->assertTrue($tester->check('http://imageserver/img'));
        $this->assertTrue($tester->check('http://s1.images/img'));
        $this->assertTrue($tester->check('http://s2.images/img'));
        $this->assertTrue($tester->check('http://foo4/img'));
        $this->assertTrue($tester->check('http://foo6/img'));
    }

    /**
     * Test disallowed report only mode
     *
     * @return void
     */
    public function testDisallowedReportOnlyMode()
    {
        $config = [
            'Record' => [
                'disallowed_external_hosts' => [
                    '127.0.0.2',
                ],
                'disallowed_external_hosts_mode' => 'report',
                'allowed_external_hosts' => [
                    '127.0.0.2',
                    'imageserver',
                ],
            ],
        ];

        $tester = $this->getMockCheckTestClass($config);
        $this->assertTrue($tester->check('http://127.0.0.2/img', 'foo.bar'));
        $this->assertFalse($tester->check('http://image2/img', 'foo.bar'));
        $this->assertTrue($tester->check('http://imageserver/img', 'foo.bar'));
        $this->assertEquals(
            'URL check: http://127.0.0.2/img would be blocked (record foo.bar)',
            $tester->getWarning()
        );
    }

    /**
     * Test allowed report only mode
     *
     * @return void
     */
    public function testAllowedReportOnlyMode()
    {
        $config = [
            'Record' => [
                'disallowed_external_hosts' => [
                    '127.0.0.3',
                ],
                'allowed_external_hosts' => [
                    'imageserver',
                ],
                'allowed_external_hosts_mode' => 'report',
            ],
        ];

        $tester = $this->getMockCheckTestClass($config);
        $this->assertFalse($tester->check('http://127.0.0.3/img', 'foo.bar'));
        $this->assertTrue($tester->check('http://image3/img', 'foo.bar'));
        $this->assertTrue($tester->check('http://imageserver/img', 'foo.bar'));
        $this->assertEquals(
            'URL check: http://image3/img would not be allowed (record foo.bar)',
            $tester->getWarning()
        );
    }

    /**
     * Test disallowed enforcing report mode
     *
     * @return void
     */
    public function testDisallowedEnforcingReportMode()
    {
        $config = [
            'Record' => [
                'disallowed_external_hosts' => [
                    '127.0.0.4',
                ],
                'disallowed_external_hosts_mode' => 'enforce-report',
                'allowed_external_hosts' => [
                    '127.0.0.4',
                    'imageserver',
                ],
            ],
        ];

        $tester = $this->getMockCheckTestClass($config);
        $this->assertFalse($tester->check('http://127.0.0.4/img'));
        $this->assertFalse($tester->check('http://image4/img'));
        $this->assertTrue($tester->check('http://imageserver/img'));
        $this->assertEquals(
            'URL check: http://127.0.0.4/img blocked (record n/a)',
            $tester->getWarning()
        );
    }

    /**
     * Test allowed enforcing report mode
     *
     * @return void
     */
    public function testAllowedEnforcingReportMode()
    {
        $config = [
            'Record' => [
                'disallowed_external_hosts' => [
                    '127.0.0.5',
                ],
                'allowed_external_hosts' => [
                    'imageserver',
                ],
                'allowed_external_hosts_mode' => 'enforce-report',
            ],
        ];

        $tester = $this->getMockCheckTestClass($config);
        $this->assertFalse($tester->check('http://127.0.0.5/img'));
        $this->assertFalse($tester->check('http://image5/img'));
        $this->assertTrue($tester->check('http://imageserver/img'));
        $this->assertEquals(
            'URL check: http://image5/img not allowed (record n/a)',
            $tester->getWarning()
        );
    }

    /**
     * Get a test harness for the trait.
     *
     * @param array $config Configuration
     * @param array $ip4Map IPv4 host to address map
     * @param array $ip6Map IPv6 host to address map
     *
     * @return object
     */
    protected function getMockCheckTestClass(array $config = [], array $ip4Map = [], array $ip6Map = [])
    {
        return new class ($config, $ip4Map, $ip6Map) {
            use \Finna\RecordDriver\Feature\FinnaUrlCheckTrait;

            /**
             * Configuration
             *
             * @var array
             */
            protected $config;

            /**
             * IPv4 host to address map
             *
             * @var array
             */
            protected $ip4Map;

            /**
             * IPv6 host to address map
             *
             * @var array
             */
            protected $ip6Map;

            /**
             * Logged warning(s)
             *
             * @var string
             */
            protected $warning = '';

            /**
             * Constructor
             *
             * @param array $config Configuration
             * @param array $ip4Map IPv4 host to address map
             * @param array $ip6Map IPv6 host to address map
             */
            public function __construct(array $config, array $ip4Map, array $ip6Map)
            {
                $this->config = $config;
                $this->ip4Map = $ip4Map;
                $this->ip6Map = $ip6Map;
            }

            /**
             * Wrapper for isUrlLoadable for testing
             *
             * @param string $url URL
             * @param string $id  Record ID
             *
             * @return bool
             */
            public function check($url, $id = ''): bool
            {
                return $this->isUrlLoadable($url, $id);
            }

            /**
             * Get any logged warning(s)
             *
             * @return string
             */
            public function getWarning(): string
            {
                return $this->warning;
            }

            /**
             * Get configuration
             *
             * @return \VuFind\Config\Config
             */
            protected function getConfig(): \VuFind\Config\Config
            {
                return new \VuFind\Config\Config($this->config);
            }

            /**
             * Get the IPv4 address for a host
             *
             * @param string $host Host
             *
             * @return string
             */
            protected function getIPv4Address(string $host): string
            {
                return $this->ip4Map[$host] ?? '';
            }

            /**
             * Get the IPv6 address for a host
             *
             * @param string $host Host
             *
             * @return string
             */
            protected function getIPv6Address(string $host): string
            {
                return $this->ip6Map[$host] ?? '';
            }

            /**
             * Log a warning
             *
             * @param string $warning Warning message
             *
             * @return void
             */
            protected function logWarning(string $warning): void
            {
                $this->warning .= $warning;
            }
        };
    }
}
