<?php

/**
 * Abstract base class for checkouts.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2023-2026.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\Checkouts;

use Laminas\Session\Container as SessionContainer;
use Laminas\Session\SessionManager;
use VuFind\Action\AbstractTemplateRenderingAction;
use VuFind\Action\HandleIlsExceptionsTrait;
use VuFind\ILS\Connection;
use VuFind\ILS\Logic\RecordsHelper;
use VuFind\ILS\PaginationHelper;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\Validator\CsrfInterface;

/**
 * Abstract base class for checkouts.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
abstract class AbstractCheckoutsAction extends AbstractTemplateRenderingAction
{
    use HandleIlsExceptionsTrait;

    /**
     * Session container.
     *
     * @var ?SessionContainer
     */
    protected ?SessionContainer $sessionContainer = null;

    /**
     * Constructor.
     *
     * @param array            $config           VuFind configuration
     * @param CsrfInterface    $csrf             CSRF validator
     * @param SessionManager   $sessionManager   Session manager
     * @param PaginationHelper $paginationHelper Pagination helper
     * @param Connection       $ilsConnection    ILS connection
     * @param RecordsHelper    $ilsRecordsHelper ILS records helper
     */
    public function __construct(
        #[Autowire(config: 'config')]
        protected array $config,
        protected CsrfInterface $csrf,
        protected SessionManager $sessionManager,
        protected PaginationHelper $paginationHelper,
        protected Connection $ilsConnection,
        protected RecordsHelper $ilsRecordsHelper,
    ) {
    }

    /**
     * Return a session container for validating selected row ids.
     *
     * @return SessionContainer
     */
    protected function getRowIdContainer(): SessionContainer
    {
        if (null === $this->sessionContainer) {
            $this->sessionContainer = new \Laminas\Session\Container('row_ids', $this->sessionManager);
        }
        return $this->sessionContainer;
    }

    /**
     * Reset the array of valid IDs in the session (used for form submission
     * validation).
     *
     * @return void
     */
    protected function resetValidRowIds(): void
    {
        $this->getRowIdContainer()->validIds = [];
    }

    /**
     * Add an ID to the validation array.
     *
     * @param string $id ID to remember
     *
     * @return void
     */
    protected function rememberValidRowId(string $id): void
    {
        $this->getRowIdContainer()->validIds[] = $id;
    }

    /**
     * Validate supplied IDs against remembered IDs. Returns true if all supplied
     * IDs are remembered, otherwise returns false.
     *
     * @param array $ids IDs to validate
     *
     * @return bool
     */
    public function validateRowIds(array $ids): bool
    {
        return !(bool)array_diff($ids, $this->getRowIdContainer()->validIds ?? []);
    }
}
