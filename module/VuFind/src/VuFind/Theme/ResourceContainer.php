<?php
/**
 * VuFind Theme Public Resource Handler (for CSS, JS, etc.)
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Support_Classes
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Theme;

/**
 * VuFind Theme Public Resource Handler (for CSS, JS, etc.)
 *
 * @category VuFind2
 * @package  Support_Classes
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class ResourceContainer
{
    protected $css = array();
    protected $js = array();
    protected $favicon = null;

    /**
     * Add a CSS file.
     *
     * @param array|string $css CSS file (or array of CSS files) to add (possibly
     * with extra settings from theme.ini appended to each filename string).
     *
     * @return void
     */
    public function addCss($css)
    {
        if (!is_array($css) && !is_a($css, '\Traversable')) {
            $css = array($css);
        }
        foreach ($css as $current) {
            $this->css[] = $current;
        }
    }

    /**
     * Add a Javascript file.
     *
     * @param array|string $js Javascript file (or array of files) to add (possibly
     * with extra settings from theme.ini appended to each filename string).
     *
     * @return void
     */
    public function addJs($js)
    {
        if (!is_array($js) && !is_a($js, '\Traversable')) {
            $js = array($js);
        }
        foreach ($js as $current) {
            $this->js[] = $current;
        }
    }

    /**
     * Get CSS files.
     *
     * @return array
     */
    public function getCss()
    {
        return array_unique($this->css);
    }

    /**
     * Get Javascript files.
     *
     * @return array
     */
    public function getJs()
    {
        return array_unique($this->js);
    }

    /**
     * Set the favicon.
     *
     * @param string $favicon New favicon path.
     *
     * @return void
     */
    public function setFavicon($favicon)
    {
        $this->favicon = $favicon;
    }

    /**
     * Get the favicon (null for none).
     *
     * @return string
     */
    public function getFavicon($favicon)
    {
        return $this->favicon;
    }
}