<?php

/**
 * HTTP POST log writer for Slack
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
 * @package  Error_Logging
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Log\Writer;

use Laminas\Http\Client;

/**
 * This class extends the Laminas Logging to send errors to Slack
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
    protected $channel = '#vufind_log';

    /**
     * The slack username messages are posted under.
     *
     * @var string
     */
    protected $username = 'VuFind Log';

    /**
     * Icons that appear at the start of log messages in Slack, by severity
     *
     * @var array
     */
    protected $messageIcons = [
        ':fire: :fire: :fire: ', // EMERG
        ':rotating_light: ',     // ALERT
        ':red_circle: ',         // CRIT
        ':exclamation: ',        // ERR
        ':warning: ',            // WARN
        ':speech_balloon: ',     // NOTICE
        ':information_source: ', // INFO
        ':beetle: ',              // DEBUG
    ];

    /**
     * Constructor
     *
     * @param string $url     URL to open as a stream
     * @param Client $client  Pre-configured http client
     * @param array  $options Optional settings (may contain 'channel' for the
     * Slack channel to use and/or 'name' for the username messages are posted under)
     */
    public function __construct($url, Client $client, array $options = [])
    {
        if (isset($options['channel'])) {
            $this->channel = $options['channel'];
        }
        if (isset($options['name'])) {
            $this->username = $options['name'];
        }
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
        $data = [
            'channel' => $this->channel,
            'username' => $this->username,
            'text' => $this->messageIcons[$event['priority']]
                . $this->formatter->format($event) . PHP_EOL,
        ];
        return json_encode($data);
    }
}
