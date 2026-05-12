<?php

/**
 * Class AuthorityApiController.
 *
 * PHP version 8
 *
 * Copyright (C) Universitätsbibliothek Mannheim 2026.
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
 * @package  VuFindApi\Controller
 * @author   Stefan Weil <stefan.weil@uni-mannheim.de>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

namespace VuFindApi\Controller;

/**
 * Authority API Controller.
 *
 * Controls the Search API functionality on authority index
 *
 * @category VuFind
 * @package  VuFindApi\Controller
 * @author   Stefan Weil <stefan.weil@uni-mannheim.de>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class AuthorityApiController extends SearchApiController
{
    /**
     * Search class family to use.
     *
     * @var string
     */
    protected $searchClassId = 'SolrAuth';

    /**
     * Record route uri.
     *
     * @var string
     */
    protected $recordRoute = 'authority/record';

    /**
     * Search route uri.
     *
     * @var string
     */
    protected $searchRoute = 'authority/search';

    /**
     * Descriptive label for the index managed by this controller.
     *
     * @var string
     */
    protected $indexLabel = 'authority';

    /**
     * Prefix for use in model names used by API.
     *
     * @var string
     */
    protected $modelPrefix = 'Authority';
}
