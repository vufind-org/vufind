<?php
/**
 * Controller for legacy Primo URLs
 *
 * PHP Version 5
 *
 * Copyright (C) The National Library of Finland 2015.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA    02111-1307    USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace Finna\Controller;

/**
 * Controller for legacy Primo URLs
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class PCIController extends \VuFind\Controller\AbstractBase
{
    /**
     * Home action
     *
     * @return mixed
     */
    public function homeAction()
    {
        return $this->redirect()->toRoute('primo-home');
    }

    /**
     * Search action -- call standard results action
     *
     * @return mixed
     */
    public function searchAction()
    {
        $params = $this->getRequest()->getQuery()->toArray();
        if (isset($params['filterpci'])) {
            $params['filter'] = [];
            $map = ['pfilter' => 'rtype'];
            foreach ($params['filterpci'] as $filter) {
                list($facet, $val) = explode(':', $filter, 2);
                if (isset($map[$facet])) {
                    $facet = $map[$facet];
                }
                $params['filter'][] = "$facet:$val";
            }
            unset($params['filterpci']);
        }
        $url
            = $this->url()->fromRoute('primo-search')
            . '?' . http_build_query($params);

        return $this->redirect()->toUrl($url);
    }

    /**
     * Primo record action
     *
     * @return mixed
     */
    public function recordAction()
    {
        if (!$id = $this->getRequest()->getQuery()->get('id')) {
            throw new \Exception('Missing record id');
        }

        $url = $this->url()->fromRoute('primorecord') . $id;
        return $this->redirect()->toUrl($url);
    }
}
