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
 * @package  Theme
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFindTheme;

/**
 * VuFind Theme Public Resource Handler (for CSS, JS, etc.)
 *
 * @category VuFind2
 * @package  Theme
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class ResourceContainer
{
    /**
     * Less CSS files
     *
     * @var array
     */
    protected $less = array();
    
    /**
     * Sass CSS files
     *
     * @var array
     */
    protected $sass = array();

    /**
     * CSS files
     *
     * @var array
     */
    protected $css = array();

    /**
     * Javascript files
     *
     * @var array
     */
    protected $js = array();

    /**
     * Favicon
     *
     * @var string
     */
    protected $favicon = null;

    /**
     * Encoding type
     *
     * @var string
     */
    protected $encoding = 'UTF-8';

    /**
     * Generator value for <meta> tag
     *
     * @var string
     */
    protected $generator = '';

    /**
     * Add a Less CSS file.
     *
     * @param array|string $css Less CSS file (or array of Less CSS files) to add
     *
     * @return void
     */
    public function addLessCss($less)
    {
        if (!is_array($less) && !is_a($less, 'Traversable')) {
            $less = array($less);
        }
        foreach ($less as $current) {
            $this->less[] = $current;
        }
    }

    /**
     * Add a Sass CSS file.
     *
     * @param array|string $css Less CSS file (or array of Less CSS files) to add
     *
     * @return void
     */
    public function addSassCss($sass)
    {
        if (!is_array($sass) && !is_a($less, 'Traversable')) {
            $sass = array($sass);
        }
        foreach ($sass as $current) {
            $this->sass[] = $current;
        }
    }

    /**
     * Add a CSS file.
     *
     * @param array|string $css CSS file (or array of CSS files) to add (possibly
     * with extra settings from theme config appended to each filename string).
     *
     * @return void
     */
    public function addCss($css)
    {
        if (!is_array($css) && !is_a($css, 'Traversable')) {
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
     * with extra settings from theme config appended to each filename string).
     *
     * @return void
     */
    public function addJs($js)
    {
        if (!is_array($js) && !is_a($js, 'Traversable')) {
            $js = array($js);
        }
        foreach ($js as $current) {
            $this->js[] = $current;
        }
    }

    /**
     * Get Less CSS files.
     *
     * @return array
     */
    public function getLessCss()
    {
        return array_unique($this->less);
    }
    /**
     * Get Sass CSS files.
     *
     * @return array
     */
    public function getSassCss()
    {
        return array_unique($this->sass);
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
     * Set the encoding.
     *
     * @param string $e New encoding
     *
     * @return void
     */
    public function setEncoding($e)
    {
        $this->encoding = $e;
    }

    /**
     * Get the encoding.
     *
     * @return void
     */
    public function getEncoding()
    {
        return $this->encoding;
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
    public function getFavicon()
    {
        return $this->favicon;
    }

    /**
     * Set the generator.
     *
     * @param string $generator New generator.
     *
     * @return void
     */
    public function setGenerator($generator)
    {
        $this->generator = $generator;
    }

    /**
     * Get the generator.
     *
     * @return string
     */
    public function getGenerator()
    {
        return $this->generator;
    }
}
