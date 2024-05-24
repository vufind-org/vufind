<?php

/**
 * Helper class for creating copy to clipboard button
 *
 * PHP version 8
 *
 * Copyright (C) Moravian Library 2021.
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
 * @package  View_Helpers
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

declare(strict_types=1);

namespace VuFind\View\Helper\Bootstrap5;

/**
 * Class CopyClipboardButton
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class CopyToClipboardButton extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * This helper creates button for copying content of an element into clipboard
     *
     * @param string $elementSelector jQuery selector for element to copy
     *
     * @return string HTML string
     */
    public function __invoke(string $elementSelector)
    {
        static $buttonNumber = 0;
        $buttonNumber++;
        $view = $this->getView();
        return $view->render(
            'Helpers/copy-to-clipboard-button.phtml',
            ['selector' => $elementSelector, 'buttonNumber' => $buttonNumber]
        );
    }
}
