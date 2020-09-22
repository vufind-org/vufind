<?php
/**
 * Followup view helper.
 * Retrieves session variables from the Followup controller plugin.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2020.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace Finna\View\Helper\Root;

use VuFind\Controller\Plugin\Followup as FollowupPlugin;

/**
 * Followup view helper.
 * Retrieves session variables from the Followup controller plugin.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Followup extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Followup controller plugin.
     *
     * @var FollowupPlugin
     */
    protected $followup;

    /**
     * Constructor
     *
     * @param FollowupPLugin $followup Followup controller plugin.
     */
    public function __construct(FollowupPlugin $followup
    ) {
        $this->followup = $followup;
    }

    /**
     * Retrieve the stored followup information.
     *
     * @param mixed ...$args Arguments (see \VuFind\Controller\Plugin\Followup)
     *
     * @return mixed
     */
    public function retrieve(...$args)
    {
        return $this->followup->retrieve(...$args);
    }

    /**
     * Retrieve and then clear a particular followup element.
     *
     * @param mixed ...$args Arguments (see \VuFind\Controller\Plugin\Followup)
     *
     * @return mixed
     */
    public function retrieveAndClear(...$args)
    {
        return $this->followup->retrieveAndClear(...$args);
    }
}
