<?php
/**
 * Organisation info page controller.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2016-2019.
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
 * Organisation info page controller.
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class OrganisationInfoController extends \VuFind\Controller\AbstractBase
{
    /**
     * Default action if none provided
     *
     * @return Laminas\View\Model\ViewModel
     */
    public function homeAction()
    {
        $config = $this->serviceLocator
            ->get(\VuFind\Config\PluginManager::class)->get('OrganisationInfo');

        $id = $this->params()->fromQuery('id');
        $buildings = $this->params()->fromQuery('buildings');

        if (!$id) {
            if (!isset($config->General->defaultOrganisation)) {
                throw new \Exception('Organisation id not defined');
            }
            $id = $config->General->defaultOrganisation;
            if (isset($config->General->buildings)) {
                $buildings = $config->General->buildings->toArray();
            }
        }

        // Try to find a translation for the organisation id
        $organisation = $this->translate(
            "0/$id/",
            [],
            $this->translate(
                "source_$id",
                [],
                $this->translate(
                    'source_' . strtolower($id),
                    [],
                    $id
                )
            )
        );

        $consortiumInfo = isset($config->OrganisationPage->consortiumInfo)
            ? $config->OrganisationPage->consortiumInfo : false;

        $title = isset($config->OrganisationPage->title)
            ? $config->OrganisationPage->title : 'organisation_info_page_title';

        $title = str_replace(
            '%%organisation%%',
            $organisation,
            $this->translate($title)
        );

        $facetConfig = $this->serviceLocator
            ->get(\VuFind\Config\PluginManager::class)->get('facets');

        $buildingOperator = '';
        if (isset($facetConfig->Results_Settings->orFacets)) {
            $orFacets = array_map(
                'trim', explode(',', $facetConfig->Results_Settings->orFacets)
            );
            if (!empty($orFacets[0])
                && ($orFacets[0] == '*' || in_array('building', $orFacets))
            ) {
                $buildingOperator = '~';
            }
        }

        $view = $this->createViewModel();

        $view->title = $title;
        $view->id = $id;
        if ($buildings) {
            $view->buildings = implode(',', $buildings);
        }
        $view->buildingFacetOperator = $buildingOperator;
        $view->consortiumInfo = $consortiumInfo;

        return $view;
    }
}
