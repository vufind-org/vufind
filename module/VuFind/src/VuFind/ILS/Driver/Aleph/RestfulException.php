<?php

/**
 * Restful Exception support class for Aleph ILS driver
 *
 * PHP version 8
 *
 * Copyright (C) UB/FU Berlin
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
 * @package  ILS_Drivers
 * @author   Vaclav Rosecky <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */

namespace VuFind\ILS\Driver\Aleph;

use VuFind\Exception\ILS as ILSException;

/**
 * Restful Exception
 *
 * @category VuFind
 * @package  Exceptions
 * @author   Vaclav Rosecky <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class RestfulException extends ILSException
{
    /**
     * XML response (false for none)
     *
     * @var string|bool
     */
    protected $xmlResponse = false;

    /**
     * Attach an XML response to the exception
     *
     * @param string $body XML
     *
     * @return void
     */
    public function setXmlResponse($body)
    {
        $this->xmlResponse = $body;
    }

    /**
     * Return XML response (false if none)
     *
     * @return string|bool
     */
    public function getXmlResponse()
    {
        return $this->xmlResponse;
    }
}
