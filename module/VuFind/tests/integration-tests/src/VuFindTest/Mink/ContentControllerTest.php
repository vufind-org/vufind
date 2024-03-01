<?php

/**
 * Mink test class for the static content controller.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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
 * @link     https://vufind.org Main Page
 */

declare(strict_types=1);

namespace VuFindTest\Mink;

/**
 * Mink test class for the static content controller.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class ContentControllerTest extends \VuFindTest\Integration\MinkTestCase
{
    /**
     * Data provider for testMarkdownContentRendering() to confirm that the initial part
     * of the content URL is case-insensitive.
     *
     * @return array
     */
    public static function basePathProvider(): array
    {
        return [
            'capitalized path' => ['/Content'],
            'lowercase path' => ['/content'],
        ];
    }

    /**
     * Test that Markdown static content rendering is working.
     *
     * @param string $basePath Base path of content route
     *
     * @dataProvider basePathProvider
     *
     * @return void
     */
    public function testMarkdownContentRendering(string $basePath): void
    {
        // Switch to the example theme, because that's where a Markdown example lives:
        $this->changeConfigs(
            [
                'config' => [
                    'Site' => [
                        'theme' => 'local_theme_example',
                    ],
                ],
            ]
        );
        // Open the page:
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . $basePath . '/example');
        $page = $session->getPage();
        // Confirm that the Markdown was converted into appropriate h1/a tags:
        $this->assertEquals(
            'Static Content Example',
            $this->findCssAndGetText($page, 'h1')
        );
        $this->assertEquals(
            'Static Pages documentation',
            $this->findCssAndGetText($page, '#content a')
        );
    }

    /**
     * Data provider for testDirectoryHandling().
     *
     * @return array
     */
    public static function requestPathProvider(): array
    {
        return [
            'main path en' => [
                'en',
                'test',
                'MAIN LEVEL TEST',
            ],
            'main path de' => [
                'de',
                'test',
                'MAIN LEVEL TEST',
            ],
            'main path fi' => [
                'fi',
                'test',
                'FINNISH TEST',
            ],
            'sub path phtml' => [
                'en',
                'test/test',
                'SUB LEVEL PHTML',
            ],
            'sub path md' => [
                'en',
                'test/testmd',
                'SUB LEVEL MD',
            ],
            'sub sub path phtml' => [
                'en',
                'test/sub/test',
                'SUB SUB LEVEL PHTML',
            ],
            'bad sub path phtml' => [
                'en',
                'test/sub/bad/test',
                'An error has occurred',
            ],
            'bad path 1' => [
                'en',
                'test//testmd',
                'An error has occurred',
            ],
            'bad path 2' => [
                'en',
                'test/.testmd',
                'An error has occurred',
            ],
            'bad path 3' => [
                'en',
                '../../../local_theme_example/templates/content/example',
                'Not Found',
            ],
        ];
    }

    /**
     * Test directory handling.
     *
     * @param string $language Language
     * @param string $path     Path to request
     * @param string $expected Expected heading
     *
     * @dataProvider requestPathProvider
     *
     * @return void
     */
    public function testDirectoryHandling(string $language, string $path, string $expected): void
    {
        // Switch to the minktest theme:
        $this->changeConfigs(
            [
                'config' => [
                    'Site' => [
                        'theme' => 'minktest',
                        'language' => $language,
                    ],
                ],
            ]
        );
        // Open the page:
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . "/Content/$path");
        $page = $session->getPage();
        // Confirm that the correct page was retrieved:
        $this->assertEquals($expected, $this->findCssAndGetText($page, 'h1'));
    }
}
