<?php

/**
 * Helper for Street Search
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2016.
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
 * @package  View_Helpers
 * @author   Jyrki Messo <jyrki.messo@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\View\Helper\Root;

/**
 * Helper for Street Search
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Jyrki Messo <jyrki.messo@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class StreetSearch extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * This component outputs code for Finna Street
     *
     * @return string
     */
    public function __invoke()
    {
        return $this->getView()->render('Helpers/streetsearch.phtml');
    }
}
