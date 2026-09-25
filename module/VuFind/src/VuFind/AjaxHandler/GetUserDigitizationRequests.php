<?php

/**
 * "Get User Digitization Requests" AJAX handler.
 *
 * PHP version 8
 *
 * Copyright (C) Mannheim University Library 2026.
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
 * @author   Dennis Müller <dennis.mueller@uni-mannheim.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\AjaxHandler;

/**
 * "Get User Digitization Requests" AJAX handler.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Dennis Müller <dennis.mueller@uni-mannheim.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetUserDigitizationRequests extends AbstractUserRequestAction
{
    /**
     * ILS driver method for data retrieval.
     *
     * @var string
     */
    protected $lookupMethod = 'getMyDigitizationRequests';
}
