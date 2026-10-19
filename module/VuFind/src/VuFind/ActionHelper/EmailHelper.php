<?php

/**
 * Action helper for email.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2026.
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
 * @package  Action_Helper
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */

namespace VuFind\ActionHelper;

use Psr\Http\Message\ServerRequestInterface;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Config\Feature\EmailSettingsTrait;
use VuFind\ServiceManager\Factory\Autowire;

use function intval;

/**
 * Action helper for email.
 *
 * @category VuFind
 * @package  Action_Helper
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
class EmailHelper implements HelperInterface
{
    use EmailSettingsTrait;

    /**
     * Constructor.
     *
     * @param AuthManager $authManager Authentication manager
     * @param array       $config      VuFind configuration
     */
    public function __construct(
        protected AuthManager $authManager,
        #[Autowire(config: 'config')]
        protected array $config,
    ) {
    }

    /**
     * Create template parameters for an email form.
     *
     * @param ServerRequestInterface $request        Request
     * @param array                  $params         Template parameters
     * @param string                 $defaultSubject Default subject line to use
     *
     * @return array
     */
    public function createEmailTemplateParams(
        ServerRequestInterface $request,
        array $params = [],
        ?string $defaultSubject = null
    ): array {
        // Load configuration and current user for convenience:
        $params['disableFrom'] = (bool)($this->config['Mail']['disable_from'] ?? false);
        $params['editableSubject'] = (bool)($this->config['Mail']['user_editable_subjects'] ?? false);
        $params['maxRecipients'] = intval($this->config['Mail']['maximum_recipients'] ?? 1);
        $user = $this->authManager->getUserObject();

        // Send parameters back to view so form can be re-populated:
        if ($request->getMethod() === 'POST') {
            $post = $request->getParsedBody();
            $params['to'] = $post['to'] ?? null;
            if (!$params['disableFrom']) {
                $params['from'] = $post['from'] ?? null;
            }
            if ($params['editableSubject']) {
                $params['subject'] = $post['subject'] ?? null;
            }
            $params['message'] = $post['message'] ?? null;
        }

        // Set default values if applicable:
        if (empty($params['to']) && $user && ($this->config['Mail']['user_email_in_to'] ?? false)) {
            $params['to'] = $user->getEmail();
        }
        if (empty($params['from'])) {
            if ($user && ($this->config['Mail']['user_email_in_from'] ?? false)) {
                $params['userEmailInFrom'] = true;
                $params['from'] = $user->getEmail();
            } elseif ($this->config['Mail']['default_from'] ?? false) {
                $params['from'] = $this->config['Mail']['default_from'];
            }
        }
        if (empty($params['subject'])) {
            $params['subject'] = $defaultSubject;
        }

        // Fail if we're missing a from and the form element is disabled:
        if ($params['disableFrom']) {
            if (empty($params['from'])) {
                $params['from'] = $this->getEmailSenderAddress($this->config);
            }
            if (empty($params['from'])) {
                throw new \Exception('Unable to determine email from address');
            }
        }

        return $params;
    }
}
