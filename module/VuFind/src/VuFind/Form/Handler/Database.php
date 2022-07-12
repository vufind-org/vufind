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
     * Feedback table
     *
     * @var \VuFind\Db\Table\Feedback
     */
    protected $table;

    /**
     * Site base url
     *
     * @var string
     */
    protected $baseUrl;

    /**
     * Constructor
     *
     * @param \VuFind\Db\Table\Feedback $feedbackTable Feedback db table
     * @param string                    $baseUrl       Site base url
     */
    public function __construct(
        \VuFind\Db\Table\Feedback $feedbackTable,
        string $baseUrl
    ) {
        $this->table = $feedbackTable;
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
        $data = [
            'user_id' => ($user) ? $user->id : null,
            'message' => $fields['message'] ?? '',
            'form_data' => json_encode($formData),
            'form_name' => $form->getFormId(),
            'site_url' => $this->baseUrl,
            'created' => date('Y-m-d H:i:s'),
            'updated' => date('Y-m-d H:i:s'),
        ];
        try {
            $success = (bool)$this->table->insert($data);
        } catch (\Exception $e) {
            $this->logError('Could not save feedback data: ' . $e->getMessage());
            return false;
        }
        return $success;
    }
}
