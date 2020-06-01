<?php

/**
 * Class AjaxCovers
 *
 * PHP version 7
 *
 * Copyright (C) Moravian Library 2020.
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
 * @package  VuFind\View\Helper\Root
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://knihovny.cz Main Page
 */
namespace VuFind\View\Helper\Root;

/**
 * Class AjaxCovers
 *
 * @category VuFind
 * @package  VuFind\View\Helper\Root
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://knihovny.cz Main Page
 */
class AjaxCovers extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Render javascript for load cover by ajax for detial
     *
     * @param string $size Size of cover
     *
     * @return string
     */
    public function detail($size = "large")
    {
        $template = 'AjaxCovers/detail.phtml';
        return $this->render($template, $size);
    }

    /**
     * Render javascript for load cover by ajax for detial
     *
     * @param string $size Size of cover
     *
     * @return string
     */
    public function results($size = "small")
    {
        $template = 'AjaxCovers/results.phtml';
        return $this->render($template, $size);
    }

    /**
     * Render template with param 'size'
     *
     * @param string $template Template name to render
     * @param string $size     Size of cover
     *
     * @return string
     */
    protected function render($template, $size)
    {
        return $this->getView()->render($template, ['size' => $size]);
    }
}
