<?php
declare(strict_types=1);

/**
 * Class Database
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
 * @package  Form
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\Form\Handler;

use Laminas\Log\LoggerAwareInterface;
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
     * Feedback database service
     *
     * @var \VuFind\Db\Service\FeedbackService
     */
    protected $db;

    /**
     * Site base url
     *
     * @var string
     */
    protected $baseUrl;

    /**
     * Constructor
     *
     * @param \VuFind\Db\Service\FeedbackService $db      Feedback database service
     * @param string                             $baseUrl Site base url
     */
    public function __construct(
        \VuFind\Db\Service\FeedbackService $db,
        string $baseUrl
    ) {
        $this->db = $db;
        $this->baseUrl = $baseUrl;
    }

    /**
     * Get data from submitted form and process them.
     *
     * @param \VuFind\Form\Form                     $form   Submitted form
     * @param \Laminas\Mvc\Controller\Plugin\Params $params Request params
     * @param ?\VuFind\Db\Row\User                  $user   Authenticated user
     *
     * @return bool
     */
    public function handle(
        \VuFind\Form\Form $form,
        \Laminas\Mvc\Controller\Plugin\Params $params,
        ?\VuFind\Db\Row\User $user = null
    ): bool {
        $fields = $form->mapRequestParamsToFieldValues($params->fromPost());
        $fields = array_column($fields, 'value', 'name');

        $formData = $fields;
        unset($formData['message']);
        $now = new \DateTime();
        $data = $this->db->createEntity()
            ->setUser($user)
            ->setMessage($fields['message'] ?? '')
            ->setFormData(json_encode($formData))
            ->setFormName($form->getFormId())
            ->setSiteUrl($this->baseUrl)
            ->setCreated($now)
            ->setUpdated($now);
        try {
            $this->db->persistEntity($data);
        } catch (\Exception $e) {
            throw $e;
            $this->logError('Could not save feedback data: ' . $e->getMessage());
            return false;
        }
        // If we got this far, we succeeded; otherwise, persistEntity would have
        // thrown an exception above.
        return true;
    }
}
