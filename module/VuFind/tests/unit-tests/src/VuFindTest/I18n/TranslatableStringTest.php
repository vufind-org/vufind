<?php

/**
 * TranslatableString Test Class
 *
 * Note that most tests using TranslatableString are in
 * VuFindTest\View\Helper\Root\TranslateTest
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

namespace VuFindTest\I18n\Translator\Loader;

use VuFind\I18n\TranslatableString;

/**
 * TranslatableString Test Class
 *
 * Note that most tests using TranslatableString are in
 * VuFindTest\View\Helper\Root\TranslateTest
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class TranslatableStringTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test standalone behavior.
     *
     * @return void
     */
    public function testWithoutTranslate()
    {
        $s = new TranslatableString('foo', 'bar');
        $this->assertEquals('foo', (string)$s);
        $this->assertEquals('bar', $s->getDisplayString());
        $this->assertTrue($s->isTranslatable());

        $s = new TranslatableString('foo', new TranslatableString('bar', 'baz'));
        $this->assertEquals('foo', (string)$s);
        $this->assertEquals('bar', (string)$s->getDisplayString());
        $this->assertEquals('baz', $s->getDisplayString()->getDisplayString());
        $this->assertTrue($s->isTranslatable());

        $s = new TranslatableString('foo', 'bar', false);
        $this->assertFalse($s->isTranslatable());
    }
}
