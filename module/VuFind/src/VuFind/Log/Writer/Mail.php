<?php

/**
 * Mail log writer
 *
 * Inspired by Laminas Mail log writer
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Log\Writer;

use Laminas\Log\Formatter\FormatterInterface;
use Laminas\Log\Formatter\Simple as SimpleFormatter;
use Laminas\Log\Writer\AbstractWriter;
use Symfony\Component\Mime\Email;
use VuFind\Exception\Mail as MailException;
use VuFind\Mailer\Mailer;

/**
 * This class implements the Laminas Logging interface for Mail systems
 *
 * Inspired by Laminas Mail log writer
 *
 * @category VuFind
 * @package  Error_Logging
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Mail extends AbstractWriter
{
    use VerbosityTrait;

    /**
     * Array of formatted events to include in message body.
     *
     * @var array
     */
    protected $eventsToMail = [];

    /**
     * Array keeping track of the number of entries per priority level.
     *
     * @var array
     */
    protected $numEntriesPerPriority = [];

    /**
     * Constructor
     *
     * @param Mailer              $mailer    Mailer
     * @param string              $from      Sender address
     * @param string              $to        Recipient address
     * @param string              $subject   Email subject
     * @param ?FormatterInterface $formatter Log entry formatter
     *
     * @throws Exception\InvalidArgumentException
     */
    public function __construct(
        protected Mailer $mailer,
        protected string $from,
        protected string $to,
        protected string $subject,
        ?FormatterInterface $formatter = null
    ) {
        $this->setFormatter($formatter ?? new SimpleFormatter());
    }

    /**
     * Write a message to the log.
     *
     * @param array $event event data
     *
     * @return void
     * @throws \Laminas\Log\Exception\RuntimeException
     */
    protected function doWrite(array $event)
    {
        $event = $this->applyVerbosity($event);
        // Track the number of entries per priority level.
        if (!isset($this->numEntriesPerPriority[$event['priorityName']])) {
            $this->numEntriesPerPriority[$event['priorityName']] = 1;
        } else {
            $this->numEntriesPerPriority[$event['priorityName']]++;
        }

        // All plaintext events are to use the standard formatter.
        $this->eventsToMail[] = $this->formatter->format($event);
    }

    /**
     * Sends mail to recipient(s) if log entries are present.  Note that both
     * plaintext and HTML portions of email are handled here.
     *
     * @return void
     */
    public function shutdown()
    {
        if (!$this->eventsToMail) {
            return;
        }

        // Merge all messages into a single text:
        $message = implode(PHP_EOL, $this->eventsToMail);

        // Finally, send the mail.  If an exception occurs, convert it into a
        // warning-level message so we can avoid an exception thrown without a
        // stack frame.
        // N.B. Logger cannot be used when reporting errors with Logger!
        try {
            $this->mailer->send(
                $this->to,
                $this->from,
                $this->subject,
                $message
            );
        } catch (MailException $e) {
            trigger_error('Unable to send log entries via email: ' . (string)$e, E_USER_WARNING);
        }
    }

    /**
     * Gets a string of number of entries per-priority level that occurred, or
     * an empty string if none occurred.
     *
     * @return string
     */
    protected function getFormattedNumEntriesPerPriority()
    {
        $strings = [];

        foreach ($this->numEntriesPerPriority as $priority => $numEntries) {
            $strings[] = "{$priority}={$numEntries}";
        }

        return implode(', ', $strings);
    }
}
