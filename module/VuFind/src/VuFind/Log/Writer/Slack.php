<?php
/**
 * HTTP POST log writer for Slack
 *
 * PHP version 5
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
 * @package  Error_Logging
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace VuFind\Log\Writer;
use Zend\Http\Client;

/**
 * This class extends the Zend Logging to send errors to Slack
 *
 * @category VuFind
 * @package  Error_Logging
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Slack extends Post
{
    /**
     * The slack channel that should receive messages.
     *
     * @var string
     */
    protected $channel;

    /**
     * Constructor
     *
     * @param string $url     URL to open as a stream
     * @param Client $client  Pre-configured http client
     * @param string $channel Slack channel
     */
    public function __construct($url, Client $client, $channel = '#vufind_log')
    {
        $this->channel = $channel;
        parent::__construct($url, $client);
    }

    /**
     * Get data for raw body
     *
     * @param array $event event data
     *
     * @return string
     */
    protected function getBody($event)
    {
        $data = ['text' => $this->formatter->format($event) . PHP_EOL];
        if ($this->channel) {
            $data['channel'] = $this->channel;
        }
        return json_encode($data);
    }
}
