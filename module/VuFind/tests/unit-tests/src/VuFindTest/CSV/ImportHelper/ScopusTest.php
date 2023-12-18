<?php

/**
 * Scopus CSV Import helper test class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2022.
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

namespace VuFindTest\CSV\ImportHelper;

use VuFind\CSV\ImportHelper\Scopus;

/**
 * Scopus CSV Import helper test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ScopusTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test name splitting (default behavior).
     *
     * @return void
     */
    public function testNameSplittingDefaultBehavior(): void
    {
        $input = 'Rai, P., Bajgai, Y., Rabgyal, J., Katwal, T.B., Delmond, A.R.';
        $this->assertEquals(
            [
                'Rai, P.',
                'Bajgai, Y.',
                'Rabgyal, J.',
                'Katwal, T.B.',
                'Delmond, A.R.',
            ],
            Scopus::splitNames($input)
        );
    }

    /**
     * Test name splitting with hyphenated initials in the list.
     *
     * @return void
     */
    public function testNameSplittingWithHyphenatedInitials(): void
    {
        $input = 'Bellone, R., Failloux, A.-B.';
        $this->assertEquals(
            [
                'Bellone, R.',
                'Failloux, A.-B.',
            ],
            Scopus::splitNames($input)
        );
    }

    /**
     * Test name splitting with a one-part name in the list.
     *
     * @return void
     */
    public function testNameSplittingWithMissingInitials(): void
    {
        $input = 'Khan, M.Q., Yaseen, Zahid, H., Numan, M., da Silva Vaz, I.';
        $this->assertEquals(
            [
                'Khan, M.Q.',
                'Yaseen',
                'Zahid, H.',
                'Numan, M.',
                'da Silva Vaz, I.',
            ],
            Scopus::splitNames($input)
        );
    }

    /**
     * Test name splitting (with "first only" flag).
     *
     * @return void
     */
    public function testNameSplittingWithFirstOnlyFlag(): void
    {
        $input = 'Rai, P., Bajgai, Y., Rabgyal, J., Katwal, T.B., Delmond, A.R.';
        $this->assertEquals(
            [
                'Rai, P.',
            ],
            Scopus::splitNames($input, 1)
        );
    }
}
