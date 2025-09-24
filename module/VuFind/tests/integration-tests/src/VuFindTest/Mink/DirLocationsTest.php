<?php

/**
 * Mink test class for inheritance on local configuration dir inheritance.
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2024.
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

use Behat\Mink\Element\DocumentElement;

/**
 * Mink test class for inheritance on local configuration dir inheritance.
 *
 * @category VuFind
 * @package  Tests
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class DirLocationsTest extends \VuFindTest\Integration\MinkTestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Test that the ini configs of the local dir stack are processed.
     *
     * @return void
     */
    public function testIniConfigs(): void
    {
        $session = $this->getMinkSession();
        // check default behavior without local searches ini
        $session->visit($this->getVuFindUrl());
        $page = $session->getPage();
        $this->checkSearchType($page, 'ParentTest');
        // check that the local configuration overrides the parent configuration.
        $this->changeConfigs(
            ['searches' => ['Basic_Searches' => ['ChildTest' => 'ChildTest']]]
        );
        $session->visit($this->getVuFindUrl());
        $page = $session->getPage();
        $this->checkSearchType($page, 'ParentTest', unFind: true);
        $this->checkSearchType($page, 'ChildTest');
    }

    /**
     * Test "use_parent_dir" parent setting in ini configs.
     *
     * @depends testIniConfigs
     *
     * @return void
     */
    public function testParentIniConfigs(): void
    {
        $session = $this->getMinkSession();
        // to check overridden configuration
        // set up local searches ini without parent config
        $this->changeConfigs(
            ['searches' => ['dummy' => ['dummy' => 'dummy']]]
        );
        $session->visit($this->getVuFindUrl());
        $page = $session->getPage();
        $this->checkSearchType($page, 'ParentTest', unFind: true);
        // check user_parent_dir configuration
        $this->changeConfigs(
            ['searches' => ['Parent_Config' => ['use_parent_dir' => 'true']]]
        );
        $session->visit($this->getVuFindUrl());
        $page = $session->getPage();
        $this->checkSearchType($page, 'ParentTest');
    }

    /**
     * Check if a search type is configured (or if it is not).
     *
     * @param DocumentElement $page   Page
     * @param string          $value  Search type value
     * @param bool            $unFind If the value is unexpected
     *
     * @return void
     */
    protected function checkSearchType(DocumentElement $page, string $value, bool $unFind = false): void
    {
        $cssSelector = '#searchForm_type option[value="' . $value . '"]';
        if ($unFind) {
            $this->unFindCss($page, $cssSelector);
        } else {
            $this->findCss($page, $cssSelector);
        }
    }

    /**
     * Test that the yaml configs of the local dir stack are processed.
     *
     * @return void
     */
    public function testYamlConfigs(): void
    {
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Search/History');
        $page = $session->getPage();
        $this->findCss($page, '.account-menu .parent-test');
        $this->changeYamlConfigs(
            [
                'AccountMenu' => [
                    'MenuItems' => [
                        [
                            'name' => 'child-test',
                            'label' => 'child test',
                            'route' => 'myresearch-favorites',
                            'icon' => 'user-favorites',
                        ],
                    ],
                ],
            ]
        );
        $session->visit($this->getVuFindUrl() . '/Search/History');
        $page = $session->getPage();
        $this->unFindCss($page, '.account-menu a.parent-test');
        $this->findCss($page, '.account-menu a.child-test');
    }

    /**
     * Test that the text domains of the local dir stack and the base languages are processed.
     *
     * @return void
     */
    public function testTranslations(): void
    {
        $page = $this->setupTranslations([
            'ChildTranslation' => 'test_string_1',
            'ParentTranslations' => 'test_string_2',
            'BaseTranslations' => 'bulk_email',
        ]);
        $this->checkTranslation($page, 'Child Text', 'ChildTranslation');
        $this->checkTranslation($page, 'Parent Text', 'ParentTranslations');
        $this->checkTranslation($page, 'Email Selected', 'BaseTranslations');
    }

    /**
     * Test that the text domains of the local dir stack and the base languages are processed.
     *
     * @return void
     */
    public function testTextDomains(): void
    {
        $page = $this->setupTranslations([
            'ChildTranslation' => 'ChildDomain::test',
            'ParentTranslations' => 'ParentDomain::test',
            'BaseTranslations' => 'HoldingStatus::availability_uncertain',
        ]);
        $this->checkTranslation($page, 'Child Text', 'ChildTranslation');
        $this->checkTranslation($page, 'Parent Text', 'ParentTranslations');
        $this->checkTranslation($page, 'Uncertain', 'BaseTranslations');
    }

    /**
     * Setup custom translation keys using the search type configuration.
     *
     * @param array $translations Translations
     *
     * @return DocumentElement
     */
    protected function setupTranslations(array $translations): DocumentElement
    {
        $this->changeConfigs(
            [
                'config' => [
                    'Cache' => [
                        'disabled' => true,
                    ],
                ],
                'searches' => [
                    'Basic_Searches' => $translations,
                ],
            ]
        );
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl());
        return $session->getPage();
    }

    /**
     * Checks the translation of the searchForm_type option.
     *
     * @param DocumentElement $page     Page
     * @param string          $expected Expected translation
     * @param string          $field    Value of option that contains the translation
     *
     * @return void
     */
    protected function checkTranslation(DocumentElement $page, string $expected, string $field): void
    {
        $this->assertEquals(
            $expected,
            $this->findCssAndGetText($page, '#searchForm_type option[value="' . $field . '"]')
        );
    }

    /**
     * Get DirLocations.ini path.
     *
     * @return string
     */
    protected function getDirLocationsIni(): string
    {
        $localDir = $this->pathResolver->getLocalConfigDirStack()[0]['directory'];
        return $localDir . '/DirLocations.ini';
    }

    /**
     * Setup DirLocations.ini.
     *
     * @return void
     */
    protected function setupDirLocationsIni(): void
    {
        $dirLocationsIni = $this->getDirLocationsIni();
        if (file_exists($dirLocationsIni)) {
            rename($dirLocationsIni, $dirLocationsIni . '.bak');
        }
        file_put_contents($dirLocationsIni, '');
        $this->writeConfigFile($dirLocationsIni, [
            'Parent_Dir' => [
                'path' => $this->getFixtureDir() . 'dirlocations',
                'is_relative_path' => false,
            ],
        ]);
    }

    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->setupDirLocationsIni();
    }

    /**
     * Reset DirLocations.ini.
     *
     * @return void
     */
    protected function resetDirLocationsIni(): void
    {
        $dirLocationsIni = $this->getDirLocationsIni();
        if (file_exists($dirLocationsIni)) {
            unlink($dirLocationsIni);
        }
        if (file_exists($dirLocationsIni . '.bak')) {
            rename($dirLocationsIni . '.bak', $dirLocationsIni);
        }
    }

    /**
     * Standard teardown method.
     *
     * @return void
     */
    public function tearDown(): void
    {
        $this->resetDirLocationsIni();
        parent::tearDown();
    }
}
