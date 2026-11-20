<?php

/**
 * Abstract custom links action.
 *
 * PHP version 8
 *
 * Copyright (C) effective WEBWORK GmbH 2023.
 * Copyright (C) Hebis Verbundzentrale 2026.
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
 * @package  Action
 * @author   Johannes Schultze <schultze@effective-webwork.de>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFindAdmin\Action\AdminCustomLinks;

use Psr\Http\Message\ResponseInterface;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\Content\ContentPageManager;
use VuFind\Content\CustomLinkManager;
use VuFind\Exception\BadRequest;
use VuFind\Exception\NotFound;
use VuFind\I18n\Locale\LocaleSettingsAwareInterface;
use VuFind\I18n\Locale\LocaleSettingsAwareTrait;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\View\GlobalsContainer;
use VuFindAdmin\Action\Admin\AbstractAdminAction;
use VuFindAdmin\Action\ContentActionTrait;

/**
 * Abstract custom links action.
 *
 * @category VuFind
 * @package  Action
 * @author   Johannes Schultze <schultze@effective-webwork.de>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
abstract class AbstractCustomLinkAction extends AbstractAdminAction implements LocaleSettingsAwareInterface
{
    use LocaleSettingsAwareTrait;
    use ContentActionTrait;

    /**
     * Constructor.
     *
     * @param GlobalsContainer   $globalsContainer   Globals container
     * @param array              $config             VuFind configuration
     * @param CustomLinkManager  $customLinkManager  Custom link manager
     * @param ContentPageManager $contentPageManager Content page manager
     */
    #[Autowire]
    public function __construct(
        GlobalsContainer $globalsContainer,
        #[Autowire(config: 'config')]
        array $config,
        protected CustomLinkManager $customLinkManager,
        protected ContentPageManager $contentPageManager,
    ) {
        parent::__construct($globalsContainer, $config);
    }

    /**
     * Redirect to custom links admin overview.
     *
     * @return ResponseInterface
     */
    protected function returnToCustomLinksAdminHome(): ResponseInterface
    {
        return $this->getHelper(RedirectHelper::class)->redirectToRoute($this->response, 'admin/custom-links');
    }

    /**
     * Get custom link by the query parameter "custom_link_id".
     *
     * @return array
     */
    protected function getCustomLinkByQueryParam(): array
    {
        $customLinkId = $this->getQueryParam('custom_link_id');
        if (!$customLinkId) {
            throw new BadRequest('Query parameter "custom_link_id" is missing.');
        }

        $customLink = $this->customLinkManager->getByDatabaseId($customLinkId);
        if ($customLink === null) {
            throw new NotFound('Custom link does not exist');
        }
        return $customLink;
    }

    /**
     * Get form data optionally based on existing custom link.
     *
     * @param ?array $customLink Custom link data (optional)
     *
     * @return array
     */
    protected function getFormData(?array $customLink = null): array
    {
        $customLinkFormConfig = $this->customLinkManager->getCustomLinkConfig()['adminForm'] ?? [];
        $formData = [];

        $contexts = $customLinkFormConfig['contexts'] ?? [];
        $formData['contexts'] = array_keys($contexts);
        if ($customLink && $activeContext = $this->getBestMatchingContext($customLink, $contexts)) {
            $formData['activeContext'] = $activeContext;
        }

        $formData['types'] = $customLinkFormConfig['types'] ?? [];
        if ($activeType = $customLink['type'] ?? null) {
            $formData['activeType'] = $activeType;
        }

        $translations = $customLink['translations'] ?? [];

        if ($pageHrid = $customLink['page'] ?? null) {
            $contentPage = $this->contentPageManager->getByHridFromDatabase($pageHrid);
            foreach ($contentPage['translations'] as $language => $translation) {
                $translations[$language]['headline'] = $translation['headline'] ?? null;
                $translations[$language]['content'] = $translation['content'] ?? null;
            }
        }

        foreach ($this->getFormLanguages($customLinkFormConfig) as $language) {
            $formData['translations'][$language] = $translations[$language] ?? null;
        }

        return $formData;
    }

    /**
     * Map form data to custom link array format.
     *
     * @param ?string $pageHrid Optional page HRID
     *
     * @return array
     */
    protected function formDataToCustomLink(?string $pageHrid): array
    {
        $customLink = $this->customLinkManager->getDefaults();
        $customLinkFormConfig = $this->customLinkManager->getCustomLinkConfig()['adminForm'] ?? [];

        if ($context = $this->getPostParam('context_fieldset')['context'] ?? null) {
            $contextConfig = $customLinkFormConfig['contexts'][$context] ?? [];
            $customLink['position'] = $contextConfig['position'] ?? 'default';
        }

        if ($type = $this->getPostParam('type_fieldset')['type'] ?? null) {
            $customLink['type'] = $type;
        }

        $customLink['page'] = $pageHrid;

        $customLink['translations'] = $this->getPostParam('translations');

        foreach (array_keys($customLink['translations']) as $language) {
            unset($customLink['translations'][$language]['headline']);
            unset($customLink['translations'][$language]['content']);
        }

        return $customLink;
    }

    /**
     * Map form data to content page array format.
     *
     * @return array
     */
    protected function formDataToContentPage(): array
    {
        $contentPage = [
            'translations' => $this->getPostParam('translations'),
        ];

        foreach (array_keys($contentPage['translations']) as $language) {
            unset($contentPage['translations'][$language]['title']);
            unset($contentPage['translations'][$language]['url']);
        }

        return $contentPage;
    }
}
