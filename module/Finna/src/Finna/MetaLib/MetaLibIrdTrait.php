<?php
/**
 * Finna MetaLib IRD trait.
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\MetaLib;

/**
 * Finna MetaLib IRD trait.
 *
 * @category VuFind2
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
trait MetaLibIrdTrait
{
    /**
     * Return configured MetaLib search sets that are searchable.
     *
     * @return array
     */
    protected function getMetaLibSets()
    {
        $auth
            = $this->serviceLocator->get('ZfcRbac\Service\AuthorizationService');
        $configLoader = $this->getServiceLocator()->get('VuFind\Config');
        $access = $auth->isGranted('finna.authorized') ? 'authorized' : 'guest';
        $sets = $configLoader->get('MetaLibSets')->toArray();
        $allowedSets = [];
        foreach ($sets as $key => $set) {
            if (!isset($set['access']) || $set['access'] == $access) {
                $allowedSets[$key] = $set;
            }
        }
        return $allowedSets;
    }

    /**
     * Verify that the user may search in the given MetaLib search set.
     *
     * Returns an array with elements:
     *   boolean: true if the given search set is configured, i.e. not an IRD.
     *   string:  Searchable search set. Fallbacks to the first configured
     *            search set.
     *
     * @param string $set Search set.
     *
     * @return array
     */
    protected function getMetaLibSet($set = false)
    {
        $allowedSets = $this->getMetaLibSets();
        //$currentSet = $this->getRequest()->getQuery()->get('set');
        if ($set && strncmp($set, '_ird:', 5) == 0) {
            $ird = substr($set, 5);
            if (!preg_match('/\W/', $ird)) {
                return [true, $set];
            }
        } else if ($set) {
            if (array_key_exists($set, $allowedSets)) {
                return [false, $set];
            }
        }
        return [false, current(array_keys($allowedSets))];
    }

    /**
     * Return MetaLib search set IRD's.
     *
     * @param string $set IRD
     *
     * @return array
     */
    protected function getMetaLibIrds($set)
    {
        list($isIrd, $set) = $this->getMetaLibSet($set);
        if (!$isIrd) {
            $allowedSets = $this->getMetaLibSets();
            if (!isset($allowedSets[$set]['ird_list'])) {
                return false;
            }
            $set = $allowedSets[$set]['ird_list'];
        }
        return explode(',', $set);
    }
}
