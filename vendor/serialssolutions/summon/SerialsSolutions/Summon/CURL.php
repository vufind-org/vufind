<?php
/**
 * Summon Search API Interface (Simple PHP/cURL implementation)
 *
 * PHP version 5
 *
 * Copyright (C) Serials Solutions 2011.
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
 * @category SerialsSolutions
 * @package  Summon
 * @author   Rushabh Pasad <developer@rushabhpasad.in>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://api.summon.serialssolutions.com/help/api/ API Documentation
 */
require_once dirname(__FILE__) . '/Base.php';

/**
 * Summon Search API Interface (PHP/cURL implementation)
 *
 * @category SerialsSolutions
 * @package  Summon
 * @author   Rushabh Pasad <developer@rushabhpasad.in>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://api.summon.serialssolutions.com/help/api/ API Documentation
 */
class SerialsSolutions_Summon_CURL extends SerialsSolutions_Summon_Base
{
    /**
     * Handle a fatal error.
     *
     * @param SerialsSolutions_Summon_Exception $e Exception to process.
     *
     * @return void
     */
    public function handleFatalError($e)
    {
        throw $e;
    }

    /**
     * Perform a GET HTTP request.
     *
     * @param string $baseUrl     Base URL for request
     * @param string $method      HTTP method for request
     * @param string $queryString Query string to append to URL
     * @param array  $headers     HTTP headers to send
     *
     * @throws SerialsSolutions_Summon_Exception
     * @return string             HTTP response body
     */
    protected function httpRequest($baseUrl, $method, $queryString, $headers)
    {
        $this->debugPrint(
            "{$method}: {$baseUrl}?{$queryString}"
        );

        // Modify headers as summon needs it in "key: value" format
        $modified_headers = array();
        foreach ($headers as $key=>$value) {
            $modified_headers[] = $key.": ".$value;
        }

        $curl = curl_init();
        $curlOptions = array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => "{$baseUrl}?{$queryString}",
            CURLOPT_HTTPHEADER => $modified_headers
        );
        curl_setopt_array($curl, $curlOptions);
        $result = curl_exec($curl);
        if ($result === false) {
            throw new SerialsSolutions_Summon_Exception("Error in HTTP Request.");
        }
        curl_close($curl);

        return $result;
    }
}
