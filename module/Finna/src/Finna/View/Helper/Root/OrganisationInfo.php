<?php
/**
 * Organisation info component view helper
 *
 * PHP version 7
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\View\Helper\Root;

/**
 * Organisation info component view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class OrganisationInfo extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Configuration
     *
     * @var \Laminas\Config\Config
     */
    protected $config;

    /**
     * Constructor
     *
     * @param Laminas\Config\Config $config Configuration
     */
    public function __construct(\Laminas\Config\Config $config)
    {
        $this->config = $config;
    }

    /**
     * Returns HTML for embedding a organisation info.
     *
     * @param array $params Parameters
     *
     * @return mixed null|string
     */
    public function __invoke($params = false)
    {
        if (!$this->isAvailable()) {
            return null;
        }

        $id = $params['id'] ?? null;
        $buildings = $params['buildings'] ?? null;

        if (!$id) {
            if (!isset($this->config->General->defaultOrganisation)) {
                return null;
            }
            $id = $this->config->General->defaultOrganisation;
            if (isset($this->config->General->buildings)) {
                $buildings = $this->config->General->buildings->toArray();
            }
        }

        $showDetails
            = !isset($this->config->OpeningTimesWidget->details)
            || $this->config->OpeningTimesWidget->details;

        return $this->getView()->render(
            'Helpers/organisation-info.phtml', [
               'id' => $id,
               'buildings' => $buildings,
               'target' => $params['target'] ?? 'widget',
               'showDetails' => $showDetails
            ]
        );
    }

    /**
     * Check if organisation info is available
     *
     * @return bool
     */
    public function isAvailable()
    {
        return !empty($this->config->General->enabled);
    }
}
