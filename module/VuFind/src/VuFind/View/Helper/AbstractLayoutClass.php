<?php
/**
 * Helper class for managing high-level (body vs. sidebar) page layout.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2011.
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
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\View\Helper;

/**
 * Helper class for managing high-level (body vs. sidebar) page layout.
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
abstract class AbstractLayoutClass extends \Zend\View\Helper\AbstractHelper
{
    /**
     * Does the sidebar go on the left?
     *
     * @var bool
     */
    protected $left;

    /**
     * Is the sidebar offcanvas?
     *
     * @var bool
     */
    protected $offcanvas;

    /**
     * Constructor
     *
     * @param bool $left      Does the sidebar go on the left?
     * @param bool $offcanvas Is offcanvas menu active?
     */
    public function __construct($left = false, $offcanvas = false)
    {
        $this->left = $left;
        $this->offcanvas = $offcanvas;
    }

    /**
     * Helper to allow easily configurable page layout -- given a broad class
     * name, return appropriate CSS classes to lay out the page according to
     * the current configuration file settings.
     *
     * @param string $class Type of class to return ('mainbody' or 'sidebar')
     *
     * @return string       CSS classes to apply
     */
    abstract public function __invoke($class);
}
