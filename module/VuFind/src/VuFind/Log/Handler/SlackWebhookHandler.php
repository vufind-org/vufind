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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Error_Logging
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Sambhav Pokharel <sambhav.pokharel@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Log\Handler;

use Monolog\Handler\SlackWebhookHandler as MonologSlackWebhookHandler;
use Monolog\LogRecord;

/**
 * This class extends the Laminas Logging towards streams
 *
 * @category VuFind
 * @package  Error_Logging
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Sambhav Pokharel <sambhav.pokharel@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class SlackWebhookHandler extends MonologSlackWebhookHandler
{
    use VerbosityTrait;

    /**
     * Icons that appear at the start of log messages in Slack, by severity
     *
     * @var array
     */
    protected $messageIcons = [
        ':fire: :fire: :fire: ', // EMERGENCY
        ':rotating_light: ',     // ALERT
        ':red_circle: ',         // CRITICAL
        ':exclamation: ',        // ERROR
        ':warning: ',            // WARNING
        ':speech_balloon: ',     // NOTICE
        ':information_source: ', // INFO
        ':beetle: ',             // DEBUG
    ];

    /**
     * Constructor
     *
     * @param string      $webhookUrl             Slack webhook URL
     * @param string      $channel                Slack channel (default: '#log')
     * @param string      $username               Username for messages (default: 'VuFind Log')
     * @param bool        $useAttachment          Whether to use attachment formatting
     * @param string|null $iconEmoji              Icon emoji for messages
     * @param bool        $useShortAttachment     Whether to use short attachment format
     * @param bool        $includeContextAndExtra Whether to include context and extra data
     */
    public function __construct(
        string $webhookUrl,
        protected string $channel = '#log',
        protected string $username = 'VuFind Log',
        bool $useAttachment = true,
        ?string $iconEmoji = null,
        bool $useShortAttachment = false,
        bool $includeContextAndExtra = false
    ) {
        parent::__construct(
            $webhookUrl,
            $this->channel,
            $this->username,
            $useAttachment,
            $iconEmoji,
            $useShortAttachment,
            $includeContextAndExtra
        );
    }

    /**
     * Writes the record down to the log
     *
     * @param LogRecord $record Log record to write
     *
     * @return void
     */
    protected function write(LogRecord $record): void
    {
        $event = [
            'timestamp' => $record->datetime,
            'priority' => $record->level->value,
            'priorityName' => $record->level->getName(),
            'message' => $record->message,
            'extra' => $record->extra,
            'context' => $record->context,
            'channel' => $record->channel,
        ];

        // Apply verbosity filter
        $filteredEvent = $this->applyVerbosity($event);
        $modifiedRecord = new LogRecord(
            $record->datetime,
            $record->channel,
            $record->level,
            $this->formatMessage($filteredEvent),
            $filteredEvent['context'],
            $filteredEvent['extra'],
            $record->formatted
        );

        parent::write($modifiedRecord);
    }

    /**
     * Format a log message with appropriate icon
     *
     * @param array $event Event data array
     *
     * @return string Formatted message with icon
     */
    protected function formatMessage(array $event): string
    {
        $icon = $this->messageIcons[$event['priority']] ?? '';
        return $icon . $event['message'];
    }

    /**
     * Get Slack data for the log record
     *
     * @param LogRecord $record Log record to process
     *
     * @return array Slack data array
     */
    protected function getSlackData(LogRecord $record): array
    {
        $data = parent::getSlackRecord()->getSlackData($record);

        $data['channel'] = $this->channel;
        $data['username'] = $this->username;

        return $data;
    }
}
