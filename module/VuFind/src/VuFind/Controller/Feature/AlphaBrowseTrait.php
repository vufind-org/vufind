<?php

/**
 * VuFind Action Feature Trait - Alphabetic browse support
 * Depends on direct access to the Service Manager.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2021
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
 * @package  Controller_Plugins
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Controller\Feature;

use VuFindSearch\Command\AlphabeticBrowseCommand;

use function func_get_args;

/**
 * VuFind Action Feature Trait - Alphabetic browse support
 *
 * @category VuFind
 * @package  Controller_Plugins
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
trait AlphaBrowseTrait
{
    /**
     * The name of the backend providing alphabrowse services.
     *
     * @var string
     */
    protected $alphabrowseBackend = 'Solr';

    /**
     * Proxy Backend::alphabeticBrowse using the CallMethodCommand.
     *
     * @return array
     */
    protected function alphabeticBrowse()
    {
        $service = $this->getService(\VuFindSearch\Service::class);
        $command = new AlphabeticBrowseCommand(
            $this->alphabrowseBackend,
            ...func_get_args()
        );
        return $service->invoke($command)->getResult();
    }
}
