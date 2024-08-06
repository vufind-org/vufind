<?php

/**
 * Class Database
 *
 * PHP version 8
 *
 * Copyright (C) Moravian Library 2022.
 * Copyright (C) Villanova University 2023.
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
 * @package  Form
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

declare(strict_types=1);

namespace VuFind\Form\Handler;

use Laminas\Log\LoggerAwareInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\FeedbackServiceInterface;
use VuFind\Log\LoggerAwareTrait;

/**
 * Class Database
 *
 * @category VuFind
 * @package  Form
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Database implements HandlerInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Constructor
     *
     * @param FeedbackServiceInterface $feedbackService Feedback database service
     * @param string                   $baseUrl         Site base url
     */
    public function __construct(
        protected FeedbackServiceInterface $feedbackService,
        protected string $baseUrl
    ) {
    }

    /**
     * Get data from submitted form and process them.
     *
     * @param \VuFind\Form\Form                     $form   Submitted form
     * @param \Laminas\Mvc\Controller\Plugin\Params $params Request params
     * @param ?UserEntityInterface                  $user   Authenticated user
     *
     * @return bool
     */
    public function handle(
        \VuFind\Form\Form $form,
        \Laminas\Mvc\Controller\Plugin\Params $params,
        ?UserEntityInterface $user = null
    ): bool {
        $fields = $form->mapRequestParamsToFieldValues($params->fromPost());
        $fields = array_column($fields, 'value', 'name');
        $formData = $fields;
        unset($formData['message']);
        $now = new \DateTime();
        $data = $this->feedbackService->createEntity()
            ->setUser($user)
            ->setMessage($fields['message'] ?? '')
            ->setFormData($formData)
            ->setFormName($form->getFormId())
            ->setSiteUrl($this->baseUrl)
            ->setCreated($now)
            ->setUpdated($now);
        try {
            $this->feedbackService->persistEntity($data);
        } catch (\Exception $e) {
            $this->logError('Could not save feedback data: ' . $e->getMessage());
            return false;
        }
        // If we got this far, we succeeded; otherwise, persistEntity would have
        // thrown an exception above.
        return true;
    }
}
