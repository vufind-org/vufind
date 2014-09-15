<?php
/**
 * Cover Loader Test Class
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
namespace VuFindTest\Cover;
use VuFind\Cover\Loader;
use VuFindTheme\ThemeInfo;
use Zend\Config\Config;
use Zend\Http\Client\Adapter\Test as TestAdapter;

/**
 * Cover Loader Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class LoaderTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Test that failure to load even the baseline image causes an exception.
     *
     * @return void
     * @expectedException Exception
     * @expectedExceptionMessage Could not load default fail image.
     */
    public function testUtterFailure()
    {
        $theme = $this->getMock('VuFindTheme\ThemeInfo', array(), array('foo', 'bar'));
        $theme->expects($this->once())->method('findContainingTheme')->with($this->equalTo(array('images/noCover2.gif')))->will($this->returnValue(false));
        $loader = $this->getLoader(array(), null, $theme);
        $loader->getImage();
    }

    /**
     * Test that requesting a content type causes default data to load.
     *
     * @return void
     */
    public function testDefaultLoadingForContentType()
    {
        $loader = $this->getLoader();
        $this->assertEquals('image/gif', $loader->getContentType());
        $this->assertEquals('368', strlen($loader->getImage()));
    }

    /**
     * Get a loader object to test.
     *
     * @param array                                $config  Configuration
     * @param \VuFind\Content\Covers\PluginManager $manager Plugin manager (null to create mock)
     * @param ThemeInfo                            $theme   Theme info object (null to create default)
     * @param \Zend\Http\Client                    $client  HTTP client (null to create TestAdapter)
     * @param array|bool                           $mock    Array of functions to mock, or false for real object
     *
     * @return void
     */
    protected function getLoader($config = array(), $manager = null, $theme = null, $client = null, $mock = false)
    {
        $config = new Config($config);
        if (null === $manager) {
            $manager = $this->getMock('VuFind\Content\Covers\PluginManager');
        }
        if (null === $theme) {
            $theme = new ThemeInfo(__DIR__ . '/../../../../../../../themes', 'blueprint');
        }
        if (null === $client) {
            $adapter = new TestAdapter();
            $client = new \Zend\Http\Client();
            $client->setAdapter($adapter);
        }
        if ($mock) {
            return $this->getMock('VuFind\Cover\Loader', $mock, array($config, $manager, $theme, $client));
        }
        return new Loader($config, $manager, $theme, $client);
    }
}