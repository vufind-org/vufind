<?php

/**
 * HTTP POST log handler
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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
 * @package  Error_Logging
 * @author   Chris Hallberg <challber@villanova.edu>
 * @author   Sambhav Pokharel <sambhav.pokharel@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Log\Handler;

use Laminas\Http\Client;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;

use function is_array;

/**
 * This class extends the Monolog Logging to sent POST messages over HTTP
 *
 * @category VuFind
 * @package  Error_Logging
 * @author   Chris Hallberg <challber@villanova.edu>
 * @author   Sambhav Pokharel <sambhav.pokharel@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class PostHandler extends AbstractProcessingHandler
{
    use VerbosityTrait;

    /**
     * Content type header
     *
     * @var string Content type
     */
    protected $contentType = 'application/x-www-form-urlencoded';

    /**
     * Constructor
     *
     * @param string $url    URL to open as a stream
     * @param Client $client Pre-configured http client
     */
    public function __construct(protected string $url, protected Client $client)
    {
    }

    /**
     * Set content type
     *
     * @param int $type content type string
     *
     * @return void
     */
    public function setContentType($type)
    {
        $this->contentType = $type;
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
        return json_encode(
            ['message' => $event['message'] . PHP_EOL]
        );
    }

    /**
     * Writes the record down to the log
     *
     * @param LogRecord $record LogRecord
     *
     * @return void
     */
    protected function write(LogRecord $record): void
    {
        $event = [
            'timestamp' => $record->datetime,
            'priority' => $record->level->value,
            'priorityName' => $record->level->getName(),
            'message' => is_array($record->formatted) ? $record->formatted[$this->verbosity] : $record->formatted,
            'extra' => $record->extra,
            'context' => $record->context,
            'channel' => $record->channel,
        ];

        $this->client->setUri($this->url);
        $this->client->setMethod('POST');
        $this->client->setEncType($this->contentType);
        $this->client->setRawBody($this->getBody($this->applyVerbosity($event)));
        // Send
        $this->client->send();
    }
}
