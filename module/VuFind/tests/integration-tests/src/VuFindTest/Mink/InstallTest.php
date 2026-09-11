<?php

/**
 * Installer test class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2026.
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
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Mink;

use Generator;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Installer test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class InstallTest extends \VuFindTest\Integration\MinkTestCase
{
    /**
     * Data provider for testAutoConfigureSetting().
     *
     * @return Generator<string, array>
     */
    public static function autoconfigureProvider(): Generator
    {
        yield 'enabled' => [true];
        yield 'disabled' => [false];
    }

    /**
     * Test that the installer respects the autoConfigure setting.
     *
     * @param bool $autoConfigure Should we enable or disable the autoConfigure setting?
     *
     * @return void
     */
    #[DataProvider('autoconfigureProvider')]
    public function testAutoConfigureSetting(bool $autoConfigure): void
    {
        $this->changeConfigs(['config' => ['System' => compact('autoConfigure')]]);
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Install');
        $page = $session->getPage();
        $this->assertSame('Auto Configure', $this->findCssAndGetText($page, '.vc-page-title'));
        $getExpected = fn ($ac) => $ac ? 'Basic Configuration' : 'Auto configuration is disabled.';
        $expected = $getExpected($autoConfigure);
        $notExpected = $getExpected(!$autoConfigure);
        $pageContent = (string)$page->getContent();
        $this->assertStringContainsString($expected, $pageContent);
        $this->assertStringNotContainsString($notExpected, $pageContent);
    }
}
