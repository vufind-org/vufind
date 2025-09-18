<?php

/**
 * Trait adding the ability to inspect sent emails.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Feature;

use Symfony\Component\Mime\Email;

/**
 * Trait adding the ability to inspect sent emails.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
trait EmailTrait
{
    /**
     * Get the path to the email message log file.
     *
     * @return string
     */
    protected function getEmailLogPath(): string
    {
        return APPLICATION_PATH . '/vufind-mail.log';
    }

    /**
     * Get the format to use for email message log.
     *
     * @return string
     */
    protected function getEmailLogFormat(): string
    {
        return 'serialized';
    }

    /**
     * Clear out the email log to eliminate any past contents.
     *
     * @return void
     */
    protected function resetEmailLog(): void
    {
        file_put_contents($this->getEmailLogPath(), '');
    }

    /**
     * Get a logged email from the log file.
     *
     * @param int $index Index of the message to get (0-based)
     *
     * @return Email
     */
    protected function getLoggedEmail(int $index = 0): Email
    {
        $records = $this->getLoggedEmails();
        if (null === ($record = $records[$index] ?? null)) {
            throw new \Exception("Message with index $index not found");
        }
        return $record;
    }

    /**
     * Get all logged emails from the log file.
     *
     * @return Email[]
     */
    protected function getLoggedEmails(): array
    {
        $data = file_get_contents($this->getEmailLogPath());
        if (!$data) {
            throw new \Exception('No serialized email message data found');
        }
        $decoder = fn ($email) => unserialize(base64_decode($email));
        return array_filter(array_map($decoder, explode("\x1E", $data)));
    }
}
