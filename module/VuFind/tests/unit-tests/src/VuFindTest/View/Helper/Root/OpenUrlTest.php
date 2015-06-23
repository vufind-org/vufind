<?php
/**
 * OpenUrl Test Class
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
namespace VuFindTest\View\Helper\Root;
use VuFind\View\Helper\Root\OpenUrl, Zend\Config\Config;

/**
 * CitationBuilder Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class OpenUrlTest extends \VuFindTest\Unit\ViewHelperTestCase
{
    /**
     * Test checkContext() default behavior.
     *
     * @return void
     */
    public function testCheckContextDefaults()
    {
        $config = [
            'url' => 'http://foo/bar'
        ];
        $openUrl = $this->getOpenUrl(null, $config)
            ->__invoke($this->getMockDriver());
        $this->assertTrue($openUrl->isActive('results'));
        $this->assertFalse($openUrl->isActive('foo'));
    }

    /**
     * Test checkContext() behavior with configuration overrides.
     *
     * @return void
     */
    public function testCheckContextWithOverrides()
    {
        $config = [
            'url' => 'http://foo/bar',
            'show_in_results' => false,
            'show_in_foo' => true,
        ];
        $openUrl = $this->getOpenUrl(null, $config)
            ->__invoke($this->getMockDriver());
        $this->assertFalse($openUrl->isActive('results'));
        $this->assertTrue($openUrl->isActive('foo'));
    }

    /**
     * Test checkContext() with no URL set (everything should be false!)
     *
     * @return void
     */
    public function testCheckContextNoUrl()
    {
        $openUrl = $this->getOpenUrl()
            ->__invoke($this->getMockDriver());
        $this->assertFalse($openUrl->isActive('results'));
        $this->assertFalse($openUrl->isActive('foo'));
    }

    /**
     * Get mock context helper.
     *
     * @return \VuFind\View\Helper\Root\Context
     */
    protected function getMockContext()
    {
        return $this->getMockBuilder('VuFind\View\Helper\Root\Context')
            ->disableOriginalConstructor()->getMock();
    }

    /**
     * Get mock driver that returns an openURL.
     *
     * @param string $openUrl OpenURL to return
     * @return \VuFind\RecordDriver\SolrDefault
     */
    protected function getMockDriver($openUrl = 'fake-data')
    {
        $driver = $this->getMockBuilder('VuFind\RecordDriver\SolrDefault')
            ->disableOriginalConstructor()->getMock();
        $driver->expects($this->any())->method('getOpenURL')
            ->will($this->returnValue($openUrl));
        $driver->expects($this->any())->method('getCleanISSN')
            ->will($this->returnValue('1234-5678'));
        return $driver;
    }

    /**
     * Get the object to test
     *
     * @param object $rules       JSON-decoded object containing rules (optional)
     * @param array  $config      Configuration settings (optional)
     * @param object $mockContext Mock context helper (optional)
     *
     * @return OpenUrl
     */
    protected function getOpenUrl($rules = null, $config = [], $mockContext = null)
    {
        if (!is_object($rules)) {
            $json = __DIR__
                . '/../../../../../../../../../config/vufind/OpenUrlRules.json';
            $rules = json_decode(file_get_contents($json));
        }
        if (null === $mockContext) {
            $mockContext = $this->getMockContext();
        }
        $openUrl = new OpenUrl($mockContext, $rules, new Config($config));
        $openUrl->setView($this->getPhpRenderer());
        return $openUrl;
    }
}