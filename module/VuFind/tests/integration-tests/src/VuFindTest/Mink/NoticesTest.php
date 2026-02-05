<?php

/**
 * Notices test class.
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2026.
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
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Mink;

use VuFindTest\Feature\CacheManagementTrait;

/**
 * Notices test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class NoticesTest extends \VuFindTest\Integration\MinkTestCase
{
    use CacheManagementTrait;

    /**
     * Test that no messages are displayed by default.
     *
     * @return void
     */
    public function testDisabledByDefault(): void
    {
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl());
        $page = $session->getPage();
        $this->unFindCss($page, '.notices');
    }

    /**
     * Test configured notices.
     *
     * @return void
     */
    public function testConfiguredNotices(): void
    {
        $this->changeYamlConfigs(
            [
                'Notices' => [
                    'notices' =>
                        [
                            [
                                'style' => 'success',
                                'content' => 'Test Content',
                            ],
                            [
                                'style' => 'warning',
                                'position' => 'header',
                                'content' => 'Test Content2',
                            ],
                        ],
                ],
            ],
        );

        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl());
        $page = $session->getPage();
        $this->waitForPageLoad($page);

        $notice1 = $this->findCssAndGetText($page, '#content > .notices .alert-success');
        $this->assertSame('Test Content', $notice1);

        $notice2 = $this->findCssAndGetText($page, '.banner .notices .alert-warning');
        $this->assertSame('Test Content2', $notice2);
    }

    /**
     * Test configured notices with translations.
     *
     * @return void
     */
    public function testConfiguredNoticesWithTranslations(): void
    {
        $this->changeConfigs($this->getCacheClearPermissionConfig());
        $this->clearCache('yaml');
        $this->changeYamlConfigs(
            [
                'Notices' => [
                    'notices' =>
                        [
                            [
                                'style' => 'success',
                                'translations' => [
                                    'de' => 'German Content',
                                    'en' => 'English Content',
                                    'es' => [],
                                ],
                            ],
                        ],
                ],
            ],
        );

        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl());
        $page = $session->getPage();
        $this->waitForPageLoad($page);

        $notice = $this->findCssAndGetText($page, '.notices .alert-success');
        $this->assertSame('English Content', $notice);

        // Test other configured translation
        $session->visit($this->getVuFindUrl() . '?lng=de');
        $this->waitForPageLoad($page);
        $notice = $this->findCssAndGetText($page, '.notices .alert-success');
        $this->assertSame('German Content', $notice);

        // Test fallback when translation is empty
        $session->visit($this->getVuFindUrl() . '?lng=es');
        $this->waitForPageLoad($page);
        $notice = $this->findCssAndGetText($page, '.notices .alert-success');
        $this->assertSame('English Content', $notice);

        // Test that no notice is displayed if language is not configured
        $session->visit($this->getVuFindUrl() . '?lng=fr');
        $this->waitForPageLoad($page);
        $this->unFindCss($page, '.notices .alert-success');
    }

    /**
     * Test configured notices with configured class in style.
     *
     * @return void
     */
    public function testConfiguredNoticesWithConfiguredClass(): void
    {
        $this->changeYamlConfigs(
            [
                'Notices' => [
                    'styles' => [
                      'success' => [
                          'classes' => 'test-class',
                      ],
                    ],
                    'notices' =>
                        [
                            [
                                'style' => 'success',
                                'content' => 'Test Content',
                            ],
                        ],
                ],
            ],
        );

        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl());
        $page = $session->getPage();
        $this->waitForPageLoad($page);
        $this->unFindCss($page, '.notices .alert-success');
        $notice1 = $this->findCssAndGetText($page, '#content > .notices .test-class');
        $this->assertSame('Test Content', $notice1);
    }
}
