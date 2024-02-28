<?php

/**
 * Icon View Helper Test Class
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

use Laminas\Cache\Storage\Adapter\BlackHole;
use Laminas\Cache\Storage\StorageInterface;
use Laminas\View\Helper\EscapeHtmlAttr;
use VuFind\View\Helper\Root\Icon;
use VuFindTheme\View\Helper\ImageLink;

/**
 * Icon View Helper Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class IconTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ViewTrait;

    /**
     * Get a default test configuration.
     *
     * @return array
     */
    protected function getDefaultTestConfig(): array
    {
        return [
            'sets' => [
                'FontAwesome' => [
                    'template' => 'font',
                    'prefix' => 'fa fa-',
                    'src' => 'vendor/font-awesome.min.css',
                ],
                'Fugue' => [
                    'template' => 'images',
                    'src' => 'icons',
                ],
                'FakeSprite' => [
                    'template' => 'svg-sprite',
                    'src' => 'mysprites.svg',
                ],
                'Unicode' => [
                    'template' => 'unicode',
                ],
            ],
            'aliases' => [
                'bar' => 'Fugue:baz.png',
                'bar-rtl' => 'Fugue:zab.png',
                'ltronly' => 'Fugue:ltronly.png',
                'quoted' => 'Fugue:"quoted".png',
                'xyzzy' => 'FakeSprite:sprite',
                'same' => 'Alias:foo',
                'illegal' => 'Alias:criminal',
                'criminal' => 'Alias:illegal',
                'foolish' => 'Alias:foolish',
                'classy' => 'FontAwesome:spinner:extraClass',
                'extraClassy' => 'Fugue:zzz.png:weird:class foo',
                'smile' => 'Unicode:1F600',
                'wrySmile' => 'Unicode:1F600:wry',
                'classyWrySmile' => 'Unicode:1F600:wry:classy smile',
                'oddGlyph' => 'Unicode:c<de',
            ],
        ];
    }

    /**
     * Get a mock ImageLink helper
     *
     * @param string $expected Expected image
     *
     * @return ImageLink
     */
    protected function getMockImageLink(string $expected): ImageLink
    {
        $mock = $this->getMockBuilder(ImageLink::class)
            ->disableOriginalConstructor()->getMock();
        $mock->expects($this->once())->method('__invoke')
            ->with($this->equalTo($expected))
            ->will($this->returnValue(basename($expected)));
        return $mock;
    }

    /**
     * Get an Icon helper
     *
     * @param array            $config  Icon helper configuration array
     * @param StorageInterface $cache   Cache storage adapter (null for BlackHole)
     * @param array            $plugins Array of extra plugins for renderer
     * @param bool             $rtl     Are we in right-to-left mode?
     *
     * @return Icon
     */
    protected function getIconHelper(
        array $config = null,
        StorageInterface $cache = null,
        array $plugins = [],
        $rtl = false
    ): Icon {
        $icon = new Icon(
            $config ?? $this->getDefaultTestConfig(),
            $cache ?? new BlackHole(),
            new EscapeHtmlAttr(),
            $rtl
        );
        $icon->setView($this->getPhpRenderer($plugins));
        return $icon;
    }

    /**
     * Test that we can generate a font-based icon.
     *
     * @return void
     */
    public function testFontIcon(): void
    {
        $helper = $this->getIconHelper();
        $expected = '<span class="icon&#x20;icon--font&#x20;fa&#x20;fa-foo" '
            . 'role="img" aria-hidden="true"></span>';
        $this->assertEquals($expected, trim($helper('foo')));
    }

    /**
     * Test that we can generate a font-based icon with an extra class.
     *
     * @return void
     */
    public function testFontIconWithExtraClass(): void
    {
        $helper = $this->getIconHelper();
        $expected = '<span class="icon&#x20;icon--font&#x20;fa&#x20;fa-spinner&#x20;extraClass" '
            . 'role="img" aria-hidden="true"></span>';
        $this->assertEquals($expected, trim($helper('classy')));
    }

    /**
     * Test that we can generate a font-based icon with extra attributes.
     *
     * @return void
     */
    public function testFontIconWithExtras(): void
    {
        $helper = $this->getIconHelper();
        $expected = '<span class="icon&#x20;icon--font&#x20;fa&#x20;fa-foo" '
            . 'bar="baz" role="img" aria-hidden="true"></span>';
        $this->assertEquals($expected, trim($helper('foo', ['bar' => 'baz'])));

        // Add class to class
        $expected = '<span class="icon&#x20;icon--font&#x20;fa&#x20;fa-foo&#x20;foo-bar" '
            . 'role="img" aria-hidden="true"></span>';
        $this->assertEquals($expected, trim($helper('foo', ['class' => 'foo-bar'])));

        // Shortcut
        $this->assertEquals($expected, trim($helper('foo', 'foo-bar')));
    }

    /**
     * Data provider for testUnicodeIcons
     *
     * @return array
     */
    public static function unicodeIconProvider(): array
    {
        return [
            [
                '',
                '',
                '1F600',
                'smile',
                '',
            ],
            [
                'wry',
                '',
                '1F600',
                'wrySmile',
                '',
            ],
            [
                'super&#x20;wry',
                '',
                '1F600',
                'wrySmile',
                'super',
            ],
            [
                'super&#x20;wry',
                '',
                '1F600',
                'wrySmile',
                ['class' => 'super'],
            ],
            [
                'wry',
                'foo="b&#x2B;r"',
                '1F600',
                'wrySmile',
                [
                    'foo' => 'b+r',
                ],
            ],
            [
                'super&#x20;wry',
                'foo="b&#x2B;r"',
                '1F600',
                'wrySmile',
                [
                    'class' => 'super',
                    'foo' => 'b+r',
                ],
            ],
            [
                '',
                '',
                'c&lt;de',
                'oddGlyph',
                '',
            ],
        ];
    }

    /**
     * Test that we can generate a Unicode icons.
     *
     * @param string       $expectedClasses Expected extra classes
     * @param string       $expectedAttrs   Expected extra attributes
     * @param string       $expectedIcon    Expected icon code
     * @param string       $icon            Icon alias
     * @param string|array $attrs           Classes or attributes
     *
     * @dataProvider unicodeIconProvider
     *
     * @return void
     */
    public function testUnicodeIcons(
        string $expectedClasses,
        string $expectedAttrs,
        string $expectedIcon,
        string $icon,
        string|array $attrs
    ): void {
        $helper = $this->getIconHelper();
        $expected = '<span class="icon&#x20;icon--font&#x20;icon--unicode'
            . ($expectedClasses ? "&#x20;$expectedClasses" : '') . '"'
            . ($expectedAttrs ? " $expectedAttrs" : '')
            . ' role="img" aria-hidden="true" data-icon="&#x' . $expectedIcon . ';"></span>';
        $this->assertEquals($expected, trim($helper($icon, $attrs)));
    }

    /**
     * Test that caching works correctly.
     *
     * @return void
     */
    public function testCaching(): void
    {
        $expected = '<span class="icon&#x20;icon--font&#x20;fa&#x20;fa-foo" '
            . 'bar="baz" role="img" aria-hidden="true"></span>';
        $key = 'foo+c0dc783820069fb9337be7366f7945bf';

        // Create a mock cache that simulates normal cache functionality;
        // the first call to getItem returns null, then it expects a call
        // to setItem, and then the second call to getItem will return an
        // expected value.
        $cache = $this->getMockBuilder(StorageInterface::class)->getMock();
        $cache->expects($this->exactly(2))->method('getItem')
            ->with($this->equalTo($key))
            ->willReturnOnConsecutiveCalls(null, $expected);
        $cache->expects($this->once())->method('setItem')
            ->with($this->equalTo($key), $this->equalTo($expected));

        // Invoke the helper twice to meet the expectations of the cache mock:
        $helper = $this->getIconHelper(null, $cache);
        $helper('foo', ['bar' => 'baz']);
        $helper('foo', ['bar' => 'baz']);
    }

    /**
     * Test that we can generate an image-based icon.
     *
     * @return void
     */
    public function testImageIcon(): void
    {
        $plugins = ['imageLink' => $this->getMockImageLink('icons/baz.png')];
        $helper = $this->getIconHelper(null, null, $plugins);
        $expected = '<img class="icon&#x20;icon--img" src="baz.png" aria-hidden="true"'
            . ' alt="">';
        $this->assertEquals($expected, $helper('bar'));
    }

    /**
     * Test that we can generate an image-based icon where the icon contains a special character.
     *
     * @return void
     */
    public function testImageIconWithSpecialChars(): void
    {
        $plugins = ['imageLink' => $this->getMockImageLink('icons/"quoted".png')];
        $helper = $this->getIconHelper(null, null, $plugins);
        $expected = '<img class="icon&#x20;icon--img" src="&quot;quoted&quot;.png" aria-hidden="true"'
            . ' alt="">';
        $this->assertEquals($expected, $helper('quoted'));
    }

    /**
     * Test that we can generate an image-based icon with extra classes in the
     * configuration (including a class name with a colon in it).
     *
     * @return void
     */
    public function testImageIconWithExtraClasses(): void
    {
        $plugins = ['imageLink' => $this->getMockImageLink('icons/zzz.png')];
        $helper = $this->getIconHelper(null, null, $plugins);
        $expected = '<img class="icon&#x20;icon--img&#x20;weird&#x3A;class&#x20;foo" src="zzz.png"'
            . ' aria-hidden="true" alt="">';
        $this->assertEquals($expected, $helper('extraClassy'));
    }

    /**
     * Test that we can generate an image-based icon with extras.
     *
     * @return void
     */
    public function testImageIconWithExtras(): void
    {
        $plugins = ['imageLink' => $this->getMockImageLink('icons/baz.png')];
        $helper = $this->getIconHelper(null, null, $plugins);
        $expected = '<img class="icon&#x20;icon--img&#x20;myclass" src="baz.png"'
            . ' aria-hidden="true" alt="">';
        // Send a string, validating the shortcut where strings are treated as
        // classes, in addition to confirming that extras work for image icons.
        $this->assertEquals($expected, $helper('bar', 'myclass'));
    }

    /**
     * Test RTL
     *
     * @return void
     */
    public function testRTL(): void
    {
        // RTL exists
        $plugins = ['imageLink' => $this->getMockImageLink('icons/zab.png')];
        $helper = $this->getIconHelper(null, null, $plugins, true);
        $expected = '<img class="icon&#x20;icon--img" src="zab.png" aria-hidden="true"'
            . ' alt="">';
        $this->assertEquals($expected, $helper('bar'));

        // RTL does not exist
        $plugins = ['imageLink' => $this->getMockImageLink('icons/ltronly.png')];
        $helper = $this->getIconHelper(null, null, $plugins, true);
        $expected = '<img class="icon&#x20;icon--img" src="ltronly.png"'
            . ' aria-hidden="true" alt="">';
        $this->assertEquals($expected, $helper('ltronly'));
    }

    /**
     * Test that we can use an alias
     *
     * @return void
     */
    public function testAlias(): void
    {
        $helper = $this->getIconHelper();
        $expected = '<span class="icon&#x20;icon--font&#x20;fa&#x20;fa-foo" '
            . 'role="img" aria-hidden="true"></span>';
        // same is an alias for foo!
        $this->assertEquals($expected, $helper('same'));
    }

    /**
     * Test that we can detect a direct circular alias
     *
     * @return void
     */
    public function testDirectCircularAlias(): void
    {
        $this->expectExceptionMessage('Circular icon alias detected: foolish!');
        ($this->getIconHelper())('foolish');
    }

    /**
     * Test that we can detect an indirect circular alias
     *
     * @return void
     */
    public function testIndirectCircularAlias(): void
    {
        $this->expectExceptionMessage('Circular icon alias detected: illegal!');
        ($this->getIconHelper())('illegal');
    }

    /**
     * Test that we can generate an SVG icon.
     *
     * @return void
     */
    public function testSvgIcon(): void
    {
        $plugins = ['imageLink' => $this->getMockImageLink('mysprites.svg')];
        $helper = $this->getIconHelper(null, null, $plugins);
        $expected = <<<EXPECTED
            <svg class="icon&#x20;icon--svg" aria-hidden="true">
                <use xlink:href="mysprites.svg#sprite"></use>
            </svg>
            EXPECTED;
        $this->assertEquals($expected, $helper('xyzzy'));
    }

    /**
     * Test that we can generate an SVG icon with extras.
     *
     * @return void
     */
    public function testSvgIconWithExtras(): void
    {
        $plugins = ['imageLink' => $this->getMockImageLink('mysprites.svg')];
        $helper = $this->getIconHelper(null, null, $plugins);
        $expected = <<<EXPECTED
            <svg class="icon&#x20;icon--svg&#x20;myclass" data-foo="bar" aria-hidden="true">
                <use xlink:href="mysprites.svg#sprite"></use>
            </svg>
            EXPECTED;
        $this->assertEquals(
            trim($expected),
            $helper('xyzzy', ['class' => 'myclass', 'data-foo' => 'bar'])
        );
    }
}
