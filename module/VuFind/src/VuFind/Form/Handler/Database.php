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

/**
 * Class Database
 *
 * @category VuFind
 * @package  Form
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Database implements HandlerInterface
{
    /**
     * Feedback table
     *
     * @var \VuFind\Db\Table\Feedback
     */
    protected $table;

    /**
     * Constructor
     *
     * @param \VuFind\Db\Table\Feedback $feedbackTable Feedback db table
     */
    public function __construct(\VuFind\Db\Table\Feedback $feedbackTable)
    {
        $this->table = $feedbackTable;
    }

    /**
     * Gets data from submitted form and process them.
     * Returns array with keys: (bool) success - mandatory, (string) errorMessages,
     * (string) successMessage
     *
     * @param \VuFind\Form\Form                     $form   Submitted form
     * @param \Laminas\Mvc\Controller\Plugin\Params $params Request params
     * @param ?\VuFind\Db\Row\User                  $user   Authenticated user
     *
     * @return array
     */
    public function handle(
        \VuFind\Form\Form $form,
        \Laminas\Mvc\Controller\Plugin\Params $params,
        ?\VuFind\Db\Row\User $user = null
    ): array {
        $fields = $form->mapRequestParamsToFieldValues($params->fromPost());
        $fields = array_column($fields, 'value', 'name');

        $row = $this->table->createRow();
        $row->populate(
            [
                'user_id' => ($user) ? $user->id : null,
                'referrer' => $fields['referrer'] ?? null,
                'user_agent' => $fields['useragent'] ?? null,
                'user_name' => $fields['name'] ?? null,
                'user_email' => $fields['email'] ?? null,
                'message' => $fields['message'] ?? '',
            ]
        );
        $saved = $row->save();

        if ($saved) {
            return ['success' => true];
        }
        return [
            'success' => false,
            'errorMessages' => ['Could not save your Feedback'],
        ];
    }
}
