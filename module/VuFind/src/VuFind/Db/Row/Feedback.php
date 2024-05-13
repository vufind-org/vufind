<?php

/**
 * Class Feedback
 *
 * PHP version 8
 *
 * Copyright (C) Moravian Library 2022.
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
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

declare(strict_types=1);

namespace VuFind\Db\Row;

use DateTime;
use VuFind\Db\Entity\FeedbackEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\DbServiceAwareInterface;
use VuFind\Db\Service\DbServiceAwareTrait;
use VuFind\Db\Service\UserServiceInterface;

/**
 * Class Feedback
 *
 * @category VuFind
 * @package  Db_Row
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 *
 * @property int    $id
 * @property int    $user_id
 * @property string $message
 * @property string $form_data
 * @property string $form_name
 * @property string $created
 * @property string $updated
 * @property int    $updated_by
 * @property string $status
 * @property string $site_url
 */
class Feedback extends RowGateway implements FeedbackEntityInterface, DbServiceAwareInterface
{
    use DbServiceAwareTrait;

    /**
     * Constructor
     *
     * @param \Laminas\Db\Adapter\Adapter $adapter Database adapter
     */
    public function __construct($adapter)
    {
        parent::__construct('id', 'feedback', $adapter);
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
     * Message setter
     *
     * @param string $message Message
     *
     * @return FeedbackEntityInterface
     */
    public function setMessage(string $message): FeedbackEntityInterface
    {
        $this->message = $message;
        return $this;
    }

    /**
     * Message getter
     *
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Form data setter.
     *
     * @param array $data Form data
     *
     * @return FeedbackEntityInterface
     */
    public function setFormData(array $data): FeedbackEntityInterface
    {
        $this->form_data = json_encode($data);
        return $this;
    }

    /**
     * Form data getter
     *
     * @return array
     */
    public function getFormData(): array
    {
        return json_decode($this->form_data, true);
    }

    /**
     * Form name setter.
     *
     * @param string $name Form name
     *
     * @return FeedbackEntityInterface
     */
    public function setFormName(string $name): FeedbackEntityInterface
    {
        $this->form_name = $name;
        return $this;
    }

    /**
     * Form name getter
     *
     * @return string
     */
    public function getFormName(): string
    {
        return $this->form_name;
    }

    /**
     * Created setter.
     *
     * @param DateTime $dateTime Created date
     *
     * @return FeedbackEntityInterface
     */
    public function setCreated(DateTime $dateTime): FeedbackEntityInterface
    {
        $this->created = $dateTime->format('Y-m-d H:i:s');
        return $this;
    }

    /**
     * Created getter
     *
     * @return DateTime
     */
    public function getCreated(): DateTime
    {
        return DateTime::createFromFormat('Y-m-d H:i:s', $this->created);
    }

    /**
     * Updated setter.
     *
     * @param DateTime $dateTime Last update date
     *
     * @return FeedbackEntityInterface
     */
    public function setUpdated(DateTime $dateTime): FeedbackEntityInterface
    {
        $this->updated = $dateTime->format('Y-m-d H:i:s');
        return $this;
    }

    /**
     * Updated getter
     *
     * @return DateTime
     */
    public function getUpdated(): DateTime
    {
        return DateTime::createFromFormat('Y-m-d H:i:s', $this->updated);
    }

    /**
     * Status setter.
     *
     * @param string $status Status
     *
     * @return FeedbackEntityInterface
     */
    public function setStatus(string $status): FeedbackEntityInterface
    {
        $this->status = $status;
        return $this;
    }

    /**
     * Status getter
     *
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Site URL setter.
     *
     * @param string $url Site URL
     *
     * @return FeedbackEntityInterface
     */
    public function setSiteUrl(string $url): FeedbackEntityInterface
    {
        $this->site_url = $url;
        return $this;
    }

    /**
     * Site URL getter
     *
     * @return string
     */
    public function getSiteUrl(): string
    {
        return $this->site_url;
    }

    /**
     * User setter.
     *
     * @param ?UserEntityInterface $user User that created request
     *
     * @return FeedbackEntityInterface
     */
    public function setUser(?UserEntityInterface $user): FeedbackEntityInterface
    {
        $this->user_id = $user?->getId();
        return $this;
    }

    /**
     * User getter
     *
     * @return ?UserEntityInterface
     */
    public function getUser(): ?UserEntityInterface
    {
        return $this->user_id
            ? $this->getDbServiceManager()->get(UserServiceInterface::class)->getUserById($this->user_id)
            : null;
    }

    /**
     * Updatedby setter.
     *
     * @param ?UserEntityInterface $user User that updated request
     *
     * @return FeedbackEntityInterface
     */
    public function setUpdatedBy(?UserEntityInterface $user): FeedbackEntityInterface
    {
        $this->updated_by = $user ? $user->getId() : null;
        return $this;
    }

    /**
     * Updatedby getter
     *
     * @return ?UserEntityInterface
     */
    public function getUpdatedBy(): ?UserEntityInterface
    {
        return $this->updated_by
            ? $this->getDbServiceManager()->get(UserServiceInterface::class)->getUserById($this->updated_by)
            : null;
    }
}
