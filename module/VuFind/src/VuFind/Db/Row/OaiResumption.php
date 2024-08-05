<?php

/**
 * Row Definition for oai_resumption
 *
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Db_Row
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Db\Row;

use DateTime;
use VuFind\Db\Entity\OaiResumptionEntityInterface;

/**
 * Row Definition for oai_resumption
 *
 * @category VuFind
 * @package  Db_Row
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 *
 * @property int    $id
 * @property string $params
 * @property string $expires
 */
class OaiResumption extends RowGateway implements OaiResumptionEntityInterface
{
    /**
     * Constructor
     *
     * @param \Laminas\Db\Adapter\Adapter $adapter Database adapter
     */
    public function __construct($adapter)
    {
        parent::__construct('id', 'oai_resumption', $adapter);
    }

    /**
     * Extract an array of parameters from the object.
     *
     * @return array Original saved parameters.
     *
     * @deprecated Use parse_str() instead
     */
    public function restoreParams()
    {
        $parts = explode('&', $this->params);
        $params = [];
        foreach ($parts as $part) {
            [$key, $value] = explode('=', $part);
            $key = urldecode($key);
            $value = urldecode($value);
            $params[$key] = $value;
        }
        return $params;
    }

    /**
     * Encode an array of parameters into the object.
     *
     * @param array $params Parameters to save.
     *
     * @return void
     *
     * @deprecated Use \VuFind\Db\Service\OaiResumptionService::createAndPersistToken()
     */
    public function saveParams($params)
    {
        ksort($params);
        $processedParams = [];
        foreach ($params as $key => $value) {
            $processedParams[] = urlencode($key) . '=' . urlencode($value);
        }
        $this->params = implode('&', $processedParams);
    }

    /**
     * Id getter
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Resumption parameters setter
     *
     * @param ?string $params Resumption parameters.
     *
     * @return OaiResumptionEntityInterface
     */
    public function setResumptionParameters(?string $params): OaiResumptionEntityInterface
    {
        $this->params = $params;
        return $this;
    }

    /**
     * Get resumption parameters.
     *
     * @return ?string
     */
    public function getResumptionParameters(): ?string
    {
        return $this->params;
    }

    /**
     * Expiry date setter.
     *
     * @param DateTime $dateTime Expiration date
     *
     * @return OaiResumptionEntityInterface
     */
    public function setExpiry(DateTime $dateTime): OaiResumptionEntityInterface
    {
        $this->expires = $dateTime->format('Y-m-d H:i:s');
        return $this;
    }

    /**
     * Get expiry date.
     *
     * @return DateTime
     */
    public function getExpiry(): DateTime
    {
        return DateTime::createFromFormat('Y-m-d H:i:s', $this->expires);
    }
}
