<?php
/**
 * This is a helper that lets the layout know whether or not to include the feedback
 * tab
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
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\View\Helper\Root;

/**
 * This is a helper that lets the layout know whether or not to include the feedback
 * tab
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Josiah Knoll <jk1135@ship.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class Feedback extends \Zend\View\Helper\AbstractHelper
{
    /**
     * Is the tab enabled?
     *
     * @var bool
     */
    protected $tab;

    /**
     * Constructor
     *
     * @param bool $enabled Is the tab enabled?
     */
    public function __construct($enabled = true)
    {
        $this->tab = $enabled;
    }

    /**
     * This will retrieve the config for whether or not the tab is enabled.
     *
     * @return boolean
     */
    public function tabEnabled()
    {
        return $this->tab;
    }
}
