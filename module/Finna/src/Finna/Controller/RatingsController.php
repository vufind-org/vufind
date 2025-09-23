<?php

/**
 * Ratings Controller
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Jaro Ravila <jaro.ravila@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace Finna\Controller;

/**
 * Ratings controller.
 *
 * @category VuFind
 * @package  Controller
 * @author   Jaro Ravila <jaro.ravila@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class RatingsController extends \VuFind\Controller\RatingsController
{
    use Feature\UserContentTrait;

    /**
     * Array of sort options for userListAction
     *
     * @var array
     */
    protected array $sortList = [
        'created desc' => 'hold_sort_create_desc',
        'created asc' => 'hold_sort_create_asc',
        'title' => 'sort_title',
    ];
}
