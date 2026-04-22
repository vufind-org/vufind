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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
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
     * @param bool $allowEmpty Controls behavior when no emails are logged;
     * true = return empty array; false = throw exception.
     *
     * @return Email[]
     */
    protected function getLoggedEmails($allowEmpty = false): array
    {
        $data = file_get_contents($this->getEmailLogPath());
        if (!$data) {
            if ($allowEmpty) {
                return [];
            }
            throw new \Exception('No serialized email message data found');
        }
        $decoder = fn ($email) => unserialize(base64_decode($email));
        return array_filter(array_map($decoder, explode("\x1E", $data)));
    }

    /**
     * Extract one-time login code from logged email.
     *
     * @param string $expectedRecipient Expected recipient address
     *
     * @return string
     */
    protected function extractLoginCodeFromEmail(string $expectedRecipient): string
    {
        $email = $this->getLoggedEmail();
        $headers = $email->getHeaders();
        $body = $email->getBody()->getBody();
        $this->assertSame('From: noreply@vufind.org', $headers->get('from')->toString());
        $this->assertSame("To: $expectedRecipient", $headers->get('to')->toString());

        preg_match('/Your code: (\\d+)/', $body, $matches);
        $this->assertArrayHasKey(
            1,
            $matches,
            "No login code in email: $body"
        );
        return $matches[1];
    }

    /**
     * Extract one-time verification code from logged email.
     *
     * @param string $expectedRecipient Expected recipient address
     *
     * @return string
     */
    protected function extractVerificationCodeFromEmail(string $expectedRecipient): string
    {
        $email = $this->getLoggedEmail();
        $headers = $email->getHeaders();
        $body = $email->getBody()->getBody();
        $this->assertSame('From: noreply@vufind.org', $headers->get('from')->toString());
        $this->assertSame("To: $expectedRecipient", $headers->get('to')->toString());

        preg_match('/Use the following code to verify your email address.*: (\\d+)/', $body, $matches);
        $this->assertArrayHasKey(
            1,
            $matches,
            "No verification code in email: $body"
        );
        return $matches[1];
    }

    /**
     * Extract account recovery code from logged email.
     *
     * @return string
     */
    protected function extractRecoveryCodeFromEmail(): string
    {
        $email = $this->getLoggedEmail()->getBody()->getBody();
        preg_match('/Use the following code to reset your password.*: (\\d+)/', $email, $matches);
        $this->assertArrayHasKey(
            1,
            $matches,
            "No recovery code in email: $email"
        );
        return $matches[1];
    }
}
