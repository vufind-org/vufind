<?php

/**
 * SetupThemeResources view helper Test Class.
 *
 * PHP version 8
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\View\Helper;

use Laminas\View\Helper\Doctype;
use Laminas\View\Helper\HeadMeta;
use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Escaper\Escaper;
use VuFind\View\Helper\Root\HeadLink;
use VuFindTheme\ResourceContainer;
use VuFindTheme\View\Helper\AssetManager;
use VuFindTheme\View\Helper\ImageLink;
use VuFindTheme\View\Helper\SetupThemeResources;

/**
 * SetupThemeResources view helper Test Class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SetupThemeResourcesTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ReflectionTrait;

    /**
     * Test the helper.
     *
     * @return void
     */
    public function testHelper()
    {
        $escaper = new Escaper();
        $doctype = new Doctype();
        $headMeta = new HeadMeta($escaper, $doctype);
        $headLink = new HeadLink(new \Laminas\View\Helper\HeadLink($escaper, $doctype));
        $helper = new SetupThemeResources(
            $this->getResourceContainer(),
            $headMeta,
            $this->getMockAssetManager(),
            $headLink,
            $this->getMockImageLink(),
        );
        $helper();

        $this->assertSame(
            <<<EOT
                <meta content="text/html; charset=utf-8" http-equiv="Content-Type">
                <meta content="fake-generator" name="Generator">
                EOT,
            $headMeta->toString()
        );
        $this->assertSame('', $headLink->toString());
    }

    /**
     * Test configuration parsing.
     *
     * @return void
     */
    public function testConfigParsing()
    {
        $tests = [
            'foo:bar:baz' => ['foo', 'bar', 'baz'],
            'http://foo/bar:baz:xyzzy' => ['http://foo/bar', 'baz', 'xyzzy'],
        ];
        foreach ($tests as $test => $expected) {
            $this->assertEquals(
                $expected,
                $this->callMethod($this->getResourceContainer(), 'parseSetting', [$test])
            );
        }
    }

    /**
     * Get a populated resource container for testing.
     *
     * @return ResourceContainer
     */
    protected function getResourceContainer()
    {
        $rc = new ResourceContainer();
        $rc->setEncoding('utf-8');
        $rc->setGenerator('fake-generator');
        return $rc;
    }

    /**
     * Get a fake AssetManager helper.
     *
     * @return MockObject&AssetManager
     */
    protected function getMockAssetManager(): MockObject&AssetManager
    {
        return $this->createMock(AssetManager::class);
    }

    /**
     * Get a fake ImageLink helper.
     *
     * @return MockObject&ImageLink
     */
    protected function getMockImageLink(): MockObject&ImageLink
    {
        return $this->createMock(ImageLink::class);
    }
}
