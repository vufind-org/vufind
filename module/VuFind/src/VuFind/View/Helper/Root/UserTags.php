<?php

/**
 * Tag view helper
 *
 * PHP version 7
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use Laminas\View\Helper\AbstractHelper;

/**
 * Tag view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class UserTags extends AbstractHelper
{
    /**
     * Tag mode (enabled or disabled)
     *
     * @var string
     */
    protected $mode;

    /**
     * List tag mode (enabled or disabled)
     *
     * @var string
     */
    protected $listMode;

    /**
     * Constructor
     *
     * @param string $mode     Tag mode (enabled or disabled)
     * @param string $listMode List tag mode (enabled or disabled)
     */
    public function __construct($mode = 'enabled', $listMode = 'disabled')
    {
        $this->mode = $mode;
        $this->listMode = $listMode;
    }

    /**
     * Get mode
     *
     * @return string
     */
    public function getMode()
    {
        return $this->mode;
    }

    /**
     * Get list mode
     *
     * @return string
     */
    public function getListMode()
    {
        return $this->listMode;
    }
}
