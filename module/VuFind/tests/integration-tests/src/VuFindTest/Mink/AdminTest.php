<?php

/**
 * Admin module test class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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

/**
 * Admin module test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class AdminTest extends \VuFindTest\Integration\MinkTestCase
{
    use \VuFindTest\Feature\HttpRequestTrait;

    /**
     * Test that the admin module is disabled by default.
     *
     * @return void
     */
    public function testDisabledByDefault(): void
    {
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Admin');
        $page = $session->getPage();
        $this->assertEquals('The Admin module is currently disabled.', $this->findCssAndGetText($page, 'p.error b'));
    }

    /**
     * Data provider for testAdminTheme()
     *
     * @return array[]
     */
    public static function adminThemeProvider(): array
    {
        return [
            'no admin theme' => [false],
            'custom admin theme' => [true],
        ];
    }

    /**
     * Test that admin themes are applied correctly.
     *
     * @param bool $enabled Should we enable the admin theme?
     *
     * @return void
     *
     * @dataProvider adminThemeProvider
     */
    public function testAdminTheme(bool $enabled): void
    {
        $config = [
            'Site' => [
                'admin_enabled' => true,
            ],
        ];
        if ($enabled) {
            $config['Site']['admin_theme'] = 'local_theme_example';
        }
        $this->changeConfigs(compact('config'));
        $html = $this->httpGet($this->getVuFindUrl() . '/Admin')->getBody();
        $assertion = $enabled ? 'assertStringContainsString' : 'assertStringNotContainsString';
        $this->$assertion('local_theme_example', $html);
    }
}
