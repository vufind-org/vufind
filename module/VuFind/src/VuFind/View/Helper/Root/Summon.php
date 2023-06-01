<?php

/**
 * Summon support functions.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2012.
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
 * @package  Summon
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\View\Helper\Root;

use Laminas\View\Helper\AbstractHelper;

/**
 * Summon support functions.
 *
 * @category VuFind
 * @package  Summon
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Summon extends AbstractHelper
{
    /**
     * Export support function to convert Summon format to EndNote format.
     *
     * @param string $format Summon format
     *
     * @return string
     */
    public function getEndnoteFormat($format)
    {
        switch ($format) {
            case 'Journal Article':
                return 'Journal Article';
            case 'Book':
                return 'Book';
            case 'Book Chapter':
                return 'Book Section';
            case 'Conference Proceeding':
                return 'Conference Paper';
            case 'Dissertation':
                return 'Thesis';
            default:
                return 'Generic';
        }
    }

    /**
     * Export support function to convert Summon format to RefWorks format.
     *
     * @param string $format Summon format
     *
     * @return string
     */
    public function getRefWorksFormat($format)
    {
        switch ($format) {
            case 'Book Chapter':
                return 'Book, Section';
            case 'Book':
                return 'Book, Whole';
            case 'eBook':
                return 'Book, Whole';
            case 'Computer File':
                return 'Computer Program';
            case 'Conference Proceeding':
                return 'Conference Proceedings';
            case 'Dissertation':
                return 'Dissertation/Thesis';
            case 'Journal Article':
                return 'Journal Article';
            case 'Journal':
                return 'Journal, Electronic';
            case 'Trade Publication Article':
                return 'Magazine Article';
            case 'Map':
                return 'Map';
            case 'Music Score':
                return 'Music Score';
            case 'Newspaper Article':
                return 'Newspaper Article';
            case 'Report':
                return 'Report';
            case 'Audio Recording':
                return 'Sound Recording';
            case 'Video Recording':
                return 'Video/ DVD';
            case 'Web Resource':
                return 'Web Page';
            default:
                return 'Generic';
        }
    }
}
