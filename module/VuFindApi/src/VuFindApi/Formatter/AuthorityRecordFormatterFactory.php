<?php

/**
 * Class AuthorityRecordFormatterFactory.
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
 * @package  API_Formatter
 * @author   Stefan Weil <stefan.weil@uni-mannheim.de>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFindApi\Formatter;

/**
 * Authority Record Formatter factory.
 *
 * @category VuFind
 * @package  API_Formatter
 * @author   Stefan Weil <stefan.weil@uni-mannheim.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class AuthorityRecordFormatterFactory extends RecordFormatterFactory
{
    /**
     * Record fields configuration file name.
     *
     * @var string
     */
    protected $configFile = 'AuthorityApiRecordFields.yaml';
}
