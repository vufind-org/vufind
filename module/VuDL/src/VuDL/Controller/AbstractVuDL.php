<?php
/**
 * VuDL controller base class (defines some methods that can be shared by other
 * controllers).
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
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_controller Wiki
 */
namespace VuDL\Controller;

/**
 * VuDL controller base class (defines some methods that can be shared by other
 * controllers).
 *
 * @category VuFind2
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_controller Wiki
 */
class AbstractVuDL extends \VuFind\Controller\AbstractBase
{
    /**
     * VuDL config
     *
     * @var \Zend\Config\Config
     */
    protected $vuDLConfig = null;

    /**
     * Get the VuDL configuration object.
     *
     * @return \Zend\Config\Config
     */
    protected function getVuDLConfig()
    {
        if (null === $this->vuDLConfig) {
            $this->vuDLConfig = $this->getServiceLocator()
                ->get('VuFind\Config')->get('VuDL');
        }
        return $this->vuDLConfig;
    }

    /**
     * Get Fedora Base URL.
     *
     * @return string
     */
    protected function getFedoraBase()
    {
        $cfg = $this->getVuDLConfig();
        return isset($cfg->Fedora->url_base) ? $cfg->Fedora->url_base : null;
    }

    /**
     * Get Fedora Page Length.
     *
     * @return string
     */
    protected function getFedoraPageLength()
    {
        $cfg = $this->getVuDLConfig();
        return isset($cfg->Fedora->page_length) ? $cfg->Fedora->page_length : 16;
    }

    /**
     * Get Fedora Query URL.
     *
     * @return string
     */
    protected function getFedoraQueryURL()
    {
        $cfg = $this->getVuDLConfig();
        return isset($cfg->Fedora->query_url) ? $cfg->Fedora->query_url : null;
    }

    /**
     * Get Fedora Root ID.
     *
     * @return string
     */
    protected function getFedoraRootID()
    {
        $cfg = $this->getVuDLConfig();
        return isset($cfg->Fedora->root_id) ? $cfg->Fedora->root_id : null;
    }

    /**
     * Get VuDL Licenses.
     *
     * @return array
     */
    protected function getLicenses()
    {
        $cfg = $this->getVuDLConfig();
        return isset($cfg->Licenses) ? $cfg->Licenses->toArray() : array();
    }

    /**
     * Get VuDL Routes.
     *
     * @return array
     */
    protected function getVuDLRoutes()
    {
        $cfg = $this->getVuDLConfig();
        return isset($cfg->Routes) ? $cfg->Routes->toArray() : array();
    }
}
