<?php
/**
 * UrlCheckTrait Test Class
 *
 * PHP version 7
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
 * UrlCheckTrait Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class UrlCheckTraitTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Test with empty configuration
     *
     * @return void
     */
    public function testEmptyConfig()
    {
        $loader = $this->getMockBuilder(MockDriver::class)
            ->addMethods(['getConfig'])->getMock();
        $loader->expects($this->exactly(2))->method('getConfig')
            ->willReturn(new \Laminas\Config\Config([]));

        $this->assertTrue($loader->check('http://localhost'));
        $this->assertTrue($loader->check('https://localhost'));
        $this->assertFalse($loader->check('ftp://localhost'));
        $this->assertFalse($loader->check('foo'));
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
                ]
            ]
        ];
        $ipv4map = [
            'foo4' => '192.168.0.1',
            'foo4.image' => '192.168.0.2',
        ];
        $ipv6map = [
            'foo6' => '::2',
            'foo6.image' => '::3',
        ];

        $loader = $this->getMockBuilder(MockDriver::class)
            ->onlyMethods(['getIPv4Address', 'getIPv6Address'])
            ->addMethods(['getConfig'])
            ->getMock();
        $loader->expects($this->any())->method('getConfig')
            ->willReturn(new \Laminas\Config\Config($config));
        $loader->expects($this->any())->method('getIPv4Address')
            ->willReturnCallback(function ($host) use ($ipv4map) {
                return $ipv4map[$host] ?? '';
            });
        $loader->expects($this->any())->method('getIPv6Address')
            ->willReturnCallback(function ($host) use ($ipv6map) {
                return $ipv6map[$host] ?? '';
            });

        $this->assertFalse($loader->check('http://127.0.0.1/img'));
        $this->assertFalse($loader->check('http://localhost/img'));
        $this->assertFalse($loader->check('https://localhost/img'));
        $this->assertFalse($loader->check('http://[::1]/img'));
        $this->assertFalse($loader->check('http://unknown/img'));
        $this->assertFalse($loader->check('http://1.172.0.1/img'));
        $this->assertFalse($loader->check('http://1.172.0.1/img'));
        $this->assertFalse($loader->check('http://imageserver2/img'));
        $this->assertFalse($loader->check('http://foo4.image/img'));
        $this->assertFalse($loader->check('http://foo6.image/img'));

        $this->assertTrue($loader->check('http://172.0.0.1/img'));
        $this->assertTrue($loader->check('http://imageserver/img'));
        $this->assertTrue($loader->check('http://s1.images/img'));
        $this->assertTrue($loader->check('http://s2.images/img'));
        $this->assertTrue($loader->check('http://foo4/img'));
        $this->assertTrue($loader->check('http://foo6/img'));
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
                ]
            ]
        ];

        $loader = $this->getMockBuilder(MockDriver::class)
            ->onlyMethods(['getIPv4Address', 'getIPv6Address'])
            ->addMethods(['getConfig', 'logWarning'])
            ->getMock();
        $loader->expects($this->any())->method('getConfig')
            ->willReturn(new \Laminas\Config\Config($config));
        $loader->expects($this->once())->method('logWarning')
            ->with(
                'URL check: http://127.0.0.2/img would be blocked (record foo.bar)'
            )
            ->willReturn('');
        $loader->expects($this->any())->method('getIPv4Address')
            ->willReturn('');
        $loader->expects($this->any())->method('getIPv6Address')
            ->willReturn('');

        $this->assertTrue($loader->check('http://127.0.0.2/img', 'foo.bar'));
        $this->assertFalse($loader->check('http://image2/img', 'foo.bar'));
        $this->assertTrue($loader->check('http://imageserver/img', 'foo.bar'));
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
            ]
        ];

        $loader = $this->getMockBuilder(MockDriver::class)
            ->onlyMethods(['getIPv4Address', 'getIPv6Address'])
            ->addMethods(['getConfig', 'logWarning'])
            ->getMock();
        $loader->expects($this->any())->method('getConfig')
            ->willReturn(new \Laminas\Config\Config($config));
        $loader->expects($this->once())->method('logWarning')
            ->with(
                'URL check: http://image3/img would not be allowed (record foo.bar)'
            )
            ->willReturn('');
        $loader->expects($this->any())->method('getIPv4Address')
            ->willReturn('');
        $loader->expects($this->any())->method('getIPv6Address')
            ->willReturn('');

        $this->assertFalse($loader->check('http://127.0.0.3/img', 'foo.bar'));
        $this->assertTrue($loader->check('http://image3/img', 'foo.bar'));
        $this->assertTrue($loader->check('http://imageserver/img', 'foo.bar'));
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
                ]
            ]
        ];

        $loader = $this->getMockBuilder(MockDriver::class)
            ->onlyMethods(['getIPv4Address', 'getIPv6Address'])
            ->addMethods(['getConfig', 'logWarning'])
            ->getMock();
        $loader->expects($this->any())->method('getConfig')
            ->willReturn(new \Laminas\Config\Config($config));
        $loader->expects($this->once())->method('logWarning')
            ->with('URL check: http://127.0.0.4/img blocked (record n/a)')
            ->willReturn('');
        $loader->expects($this->any())->method('getIPv4Address')
            ->willReturn('');
        $loader->expects($this->any())->method('getIPv6Address')
            ->willReturn('');

        $this->assertFalse($loader->check('http://127.0.0.4/img'));
        $this->assertFalse($loader->check('http://image4/img'));
        $this->assertTrue($loader->check('http://imageserver/img'));
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
            ]
        ];

        $loader = $this->getMockBuilder(MockDriver::class)
            ->onlyMethods(['getIPv4Address', 'getIPv6Address'])
            ->addMethods(['getConfig', 'logWarning'])
            ->getMock();
        $loader->expects($this->any())->method('getConfig')
            ->willReturn(new \Laminas\Config\Config($config));
        $loader->expects($this->once())->method('logWarning')
            ->with('URL check: http://image5/img not allowed (record n/a)')
            ->willReturn('');
        $loader->expects($this->any())->method('getIPv4Address')
            ->willReturn('');
        $loader->expects($this->any())->method('getIPv6Address')
            ->willReturn('');

        $this->assertFalse($loader->check('http://127.0.0.5/img'));
        $this->assertFalse($loader->check('http://image5/img'));
        $this->assertTrue($loader->check('http://imageserver/img'));
    }
}

class MockDriver
{
    use \Finna\RecordDriver\UrlCheckTrait;

    public function check($url, $id = '')
    {
        return $this->isUrlLoadable($url, $id);
    }
}
