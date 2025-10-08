<?php

/**
 * Mail log handler
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Sambhav Pokharel <sambhav.pokharel@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Log\Handler;

use Monolog\Handler\MailHandler as MonologMailHandler;
use Monolog\LogRecord;
use VuFind\Mailer\Mailer;

use function sprintf;

/**
 * Custom Mail Handler for VuFind with verbosity support and VuFind mailer integration
 *
 * @category VuFind
 * @package  Error_Logging
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Sambhav Pokharel <sambhav.pokharel@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class MailHandler extends MonologMailHandler
{
    use VerbosityTrait;

    /**
     * Constructor
     *
     * @param string $to      Recipient email address
     * @param string $subject Email subject
     * @param string $from    Sender email address
     * @param Mailer $mailer  VuFind mailer instance
     */
    public function __construct(
        protected string $to,
        protected string $subject,
        protected string $from,
        protected Mailer $mailer
    ) {
    }

    /**
     * Send the mail using VuFind's mailer
     *
     * @param string $content The email content
     * @param array  $records The log records that triggered this handler
     *
     * @return void
     */
    protected function send(string $content, array $records): void
    {
        $this->mailer->send($this->to, $this->from, $this->subject, $this->buildMessage($records));
    }

    /**
     * Writes the record down to the log
     *
     * @param LogRecord $record Log record to process
     *
     * @return void
     */
    protected function write(LogRecord $record): void
    {
        // Apply verbosity to the record before processing
        $recordData = $record->toArray();
        $modifiedRecordData = $this->applyVerbosity($recordData);

        $modifiedRecord = new LogRecord(
            $record->datetime,
            $record->channel,
            $record->level,
            $modifiedRecordData['message'],
            $modifiedRecordData['context'],
            $modifiedRecordData['extra'],
            $record->formatted
        );

        parent::write($modifiedRecord);
    }

    /**
     * Gets the formatted content for this handler
     *
     * @param array $records Array of LogRecord objects
     *
     * @return string The formatted content
     */
    protected function buildMessage(array $records): string
    {
        $message = '';

        foreach ($records as $record) {
            $recordData = $record->toArray();
            $modifiedRecordData = $this->applyVerbosity($recordData);

            $message .= sprintf(
                "[%s] %s.%s: %s\n",
                $recordData['datetime']->format('c'),
                $recordData['channel'],
                $recordData['level_name'],
                $modifiedRecordData['message']
            );

            if (!empty($modifiedRecordData['context'])) {
                $message .= 'Context: ' . json_encode($modifiedRecordData['context'], JSON_PRETTY_PRINT) . "\n";
            }

            if (!empty($modifiedRecordData['extra'])) {
                $message .= 'Extra: ' . json_encode($modifiedRecordData['extra'], JSON_PRETTY_PRINT) . "\n";
            }

            $message .= "\n" . str_repeat('-', 50) . "\n\n";
        }

        return $message;
    }
}
