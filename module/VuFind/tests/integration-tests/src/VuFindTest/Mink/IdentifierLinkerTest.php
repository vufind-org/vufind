<?php

/**
 * Mink test class for identifier linker.
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2025.
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
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Mink;

/**
 * Mink test class for identifier linker.
 *
 * @category VuFind
 * @package  Tests
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class IdentifierLinkerTest extends \VuFindTest\Integration\MinkTestCase
{
    /**
     * Test identifier linker also works if first used on second page loaded via JS.
     *
     * @return void
     */
    public function testIdentifierLinksViaJS(): void
    {
        $this->changeConfigs(
            [
                'config' => [
                    'IdentifierLinks' => [
                        'resolver' => 'Demo',
                        'supportedIdentifiers' => ['issn'],
                    ],
                ],
            ],
        );
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Search/Results?lookfor=id:(0000183626-1 OR testsample1)&limit=1');
        $page = $session->getPage();
        $this->waitForPageLoad($page);
        $this->unfindCss($page, '.identifierLink a');
        $this->clickCss($page, '.page-next .page-link');
        $this->waitForPageLoad($page);
        $this->findCss($page, '.identifierLink a');
    }
}
