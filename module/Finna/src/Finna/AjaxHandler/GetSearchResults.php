<?php

/**
 * "Get Search Results" AJAX handler
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2023-2024.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\AjaxHandler;

use Laminas\Mvc\Controller\Plugin\Params;
use VuFind\Search\Base\Results;

/**
 * "Get Search Results" AJAX handler
 *
 * @category VuFind
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetSearchResults extends \VuFind\AjaxHandler\GetSearchResults
{
    /**
     * Render pagination
     *
     * @param Params  $params   Request params
     * @param Results $results  Search results
     * @param string  $template Paginator template
     * @param string  $ulClass  Additional class for the pagination container
     * @param string  $navClass Additional class for the nav element
     *
     * @return ?string
     */
    protected function renderPagination(
        Params $params,
        Results $results,
        string $template = 'Helpers/pagination.phtml',
        string $ulClass = '',
        string $navClass = ''
    ): ?string {
        $paginationOptions = [];
        if ($ulClass) {
            $paginationOptions['className'] = $ulClass;
        }
        if ($navClass) {
            $paginationOptions['navClassName'] = $navClass;
        }
        $pagination = $this->renderer->plugin('paginationControl');
        return $pagination(
            $results->getPaginator()->setPageRange(5),
            'Sliding',
            $template,
            ['params' => ['results' => $results], 'options' => $paginationOptions]
        );
    }
}
