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
 * @author   André Lahmann <lahmann@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
namespace VuFindTest\View\Helper\Root;
use VuFind\View\Helper\Root\OpenUrl, Zend\Config\Config, InvalidArgumentException;

/**
 * OpenUrl Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   André Lahmann <lahmann@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class OpenUrlTest extends \VuFindTest\Unit\ViewHelperTestCase
{
    /**
     * Configuration array providing basic settings for testing OpenUrlRules
     *
     * @var array
     */
    protected $rulesConfig = ['url' => 'http://foo/bar', 'show_in_results' => true];

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
            ->__invoke($this->getMockDriver(), 'results');
        $this->assertTrue($openUrl->isActive());
        $openUrl = $this->getOpenUrl(null, $config)
            ->__invoke($this->getMockDriver(), 'foo');
        $this->assertFalse($openUrl->isActive());
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
            ->__invoke($this->getMockDriver(), 'results');
        $this->assertFalse($openUrl->isActive());
        $openUrl = $this->getOpenUrl(null, $config)
            ->__invoke($this->getMockDriver(), 'foo');
        $this->assertTrue($openUrl->isActive());
    }

    /**
     * Test checkContext() with no URL set (everything should be false!)
     *
     * @return void
     */
    public function testCheckContextNoUrl()
    {
        $openUrl = $this->getOpenUrl()
            ->__invoke($this->getMockDriver(), 'results');
        $this->assertFalse($openUrl->isActive());
        $openUrl = $this->getOpenUrl()
            ->__invoke($this->getMockDriver(), 'foo');
        $this->assertFalse($openUrl->isActive());
    }

    /**
     * Test checkExcludedRecordRules() with rule not applying (isActive() will return
     * TRUE!!)
     *
     * @return void
     */
    public function testCheckExcludedRecordsRulesFalse()
    {
        $openUrl = $this
            ->getOpenUrl($this->getFixture("rule1.json"), $this->rulesConfig)
            ->__invoke($this->getMockDriver(), 'results');
        $this->assertTrue($openUrl->isActive());
    }

    /**
     * Test checkExcludedRecordRules() with matching rule (isActive() will return
     * FALSE!!)
     *
     * @return void
     */
    public function testCheckExcludedRecordsRulesTrue()
    {
        $openUrl = $this
            ->getOpenUrl($this->getFixture("rule2.json"), $this->rulesConfig)
            ->__invoke($this->getMockDriver(), 'results');
        $this->assertFalse($openUrl->isActive());
    }

    /**
     * Test checkExcludedRecordRules() with no matching rule (isActive() will return
     * FALSE!!). Specifically we're testing the case where a method has a generic
     * wildcard match in the rules but returns an empty value.
     *
     * @return void
     */
    public function testCheckExcludedRecordsRulesFalseDueToWildcardFailure()
    {
        $driver = $this->getMockDriver(
            'fake-data', 'VuFind\RecordDriver\SolrMarc', ['Article'], false
        );
        $openUrl = $this
            ->getOpenUrl($this->getFixture("rule5.json"), $this->rulesConfig)
            ->__invoke($driver, 'results');
        $this->assertFalse($openUrl->isActive());
    }

    /**
     * Test checkSupportedRecordRules() with no matching rule (isActive() will return
     * FALSE!!)
     *
     * @return void
     */
    public function testCheckSupportedRecordsRulesFalse()
    {
        $openUrl = $this
            ->getOpenUrl($this->getFixture("rule3.json"), $this->rulesConfig)
            ->__invoke($this->getMockDriver(), 'results');
        $this->assertFalse($openUrl->isActive());
    }

    /**
     * Test checkSupportedRecordRules() with no matching rule (isActive() will return
     * FALSE!!) This test is specifically designed to test wildcards -- we want to
     * be sure that ['CrazyFormat'] will NOT match ['Article', '*'].
     *
     * @return void
     */
    public function testCheckSupportedRecordsRulesWithWildcardStillFalse()
    {
        $driver = $this->getMockDriver(
            'fake-openurl', 'VuFind\RecordDriver\SolrDefault', ['CrazyFormat']
        );
        $openUrl = $this
            ->getOpenUrl($this->getFixture("rule5.json"), $this->rulesConfig)
            ->__invoke($driver, 'results');
        $this->assertFalse($openUrl->isActive());
    }

    /**
     * Test checkSupportedRecordRules() with matching rule (isActive() will return
     * TRUE!!)
     *
     * @return void
     */
    public function testCheckSupportedRecordsRulesTrue()
    {
        $openUrl = $this
            ->getOpenUrl($this->getFixture("rule4.json"), $this->rulesConfig)
            ->__invoke($this->getMockDriver(), 'results');
        $this->assertTrue($openUrl->isActive());
    }

    /**
     * Test checkSupportedRecordRules() to see if it accounts for record driver
     * class.
     *
     * @return void
     */
    public function testRecordDriverClassInRules()
    {
        $formats = ['Article'];
        $defaultDriver = $this->getMockDriver(
            'fake-data', 'VuFind\RecordDriver\SolrDefault', $formats
        );
        $marcDriver = $this->getMockDriver(
            'fake-data', 'VuFind\RecordDriver\SolrMarc', $formats
        );
        $openUrl = $this
            ->getOpenUrl($this->getFixture("rule1.json"), $this->rulesConfig);
        $this->assertTrue($openUrl->__invoke($defaultDriver, 'results')->isActive());
        $this->assertFalse($openUrl->__invoke($marcDriver, 'results')->isActive());
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
     * @param string $class   Class to mock
     * @param array  $formats Formats to return from getFormats
     * @param string $issn    ISSN to return from getCleanISSN
     *
     * @return \VuFind\RecordDriver\SolrDefault
     */
    protected function getMockDriver($openUrl = 'fake-data',
        $class = 'VuFind\RecordDriver\SolrDefault',
        $formats = ['ElectronicArticle', 'Article'],
        $issn = '1234-5678'
    ) {
        $driver = $this->getMockBuilder($class)
            ->disableOriginalConstructor()->getMock();
        $driver->expects($this->any())->method('getOpenUrl')
            ->will($this->returnValue($openUrl));
        $driver->expects($this->any())->method('getCleanISSN')
            ->will($this->returnValue($issn));
        $driver->expects($this->any())->method('getFormats')
            ->will($this->returnValue($formats));
        return $driver;
    }

    /**
     * Get the fixtures for testing OpenUrlRules
     *
     * @param string $fixture filename of the fixture to load
     *
     * @return mixed|null
     */
    protected function getFixture($fixture)
    {
        if ($fixture) {
            $file = realpath(
                __DIR__ .
                '/../../../../../../../tests/fixtures/openurlrules/' . $fixture
            );
            if (!is_string($file) || !file_exists($file) || !is_readable($file)) {
                throw new \InvalidArgumentException(
                    sprintf('Unable to load fixture file: %s ', $fixture)
                );
            }
            return json_decode(file_get_contents($file), true);
        }

        return null;
    }

    /**
     * Get the object to test
     *
     * @param array  $rules       JSON-decoded array containing rules (optional)
     * @param array  $config      Configuration settings (optional)
     * @param object $mockContext Mock context helper (optional)
     *
     * @return OpenURL
     */
    protected function getOpenUrl($rules = null, $config = [], $mockContext = null)
    {
        if (null === $rules) {
            $json = __DIR__
                . '/../../../../../../../../../config/vufind/OpenUrlRules.json';
            $rules = json_decode(file_get_contents($json), true);
        }
        if (null === $mockContext) {
            $mockContext = $this->getMockContext();
        }
        $openUrl = new OpenUrl($mockContext, $rules, new Config($config));
        $openUrl->setView($this->getPhpRenderer());
        return $openUrl;
    }
}