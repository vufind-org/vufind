<?php
/**
 * Row Definition for user
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2015-2019.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Tuure Ilmarinen <tuure.ilmarinen@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Db\Row;

use Zend\Db\Sql\Expression;

/**
 * Row Definition for user
 *
 * @category VuFind
 * @package  Db_Row
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class User extends \VuFind\Db\Row\User
{
    use FinnaUserTrait;

    /**
     * ILS Connection
     *
     * @var \VuFind\ILS\Connection
     */
    protected $ils = null;

    /**
     * Set ILS Connection
     *
     * @param \VuFind\ILS\Connection $ils ILS Connection
     *
     * @return void
     */
    public function setILS(\VuFind\ILS\Connection $ils)
    {
        $this->ils = $ils;
    }

    /**
     * Get all of the lists associated with this user.
     *
     * @return \Zend\Db\ResultSet\AbstractResultSet
     */
    public function getLists()
    {
        $lists = parent::getLists();

        // Sort lists by id
        $listsSorted = [];
        foreach ($lists as $l) {
            $listsSorted[$l['id']] = $l;
        }
        ksort($listsSorted);

        return array_values($listsSorted);
    }

    /**
     * Get number of distinct user resources in all lists.
     *
     * @return int
     */
    public function getNumOfResources()
    {
        $resource = $this->getDbTable('Resource');
        $userResources = $resource->getFavorites(
            $this->id, null, null, null
        );
        return count($userResources);
    }

    /**
     * Save ILS ID.
     *
     * @param string $catId Catalog ID to save.
     *
     * @return mixed        The output of the save method.
     * @throws \VuFind\Exception\PasswordSecurity
     */
    public function saveCatalogId($catId)
    {
        if (isset($this->config->Site->institution)) {
            $catId = $this->config->Site->institution . ":$catId";
        }
        return parent::saveCatalogId($catId);
    }

    /**
     * Add/update a resource in the user's account.
     *
     * @param array                   $resources       The resources to add/update
     * @param \VuFind\Db\Row\UserList $list            The list to store the resource
     * in.
     * @param array                   $tagArray        An array of tags to associate
     * with the resource.
     * @param string                  $notes           User notes about the resource.
     * @param bool                    $replaceExisting Whether to replace all
     * existing tags (true) or append to the existing list (false).
     *
     * @return void
     */
    public function saveResources(
        array $resources,
        \VuFind\Db\Row\UserList $list,
        array $tagArray,
        string $notes,
        bool $replaceExisting = true
    ) {
        // Create the resource link if it doesn't exist and update the notes in any
        // case:
        $linkTable = $this->getDbTable('UserResource');
        foreach ($resources as $resource) {
            $linkTable->createOrUpdateLink(
                $resource->id,
                $this->id,
                $list->id,
                $notes
            );
            // If we're replacing existing tags, delete the old ones before adding
            // the new ones:
            if ($replaceExisting) {
                $resource->deleteTags($this, $list->id);
            }
            // Add the new tags:
            foreach ($tagArray as $tag) {
                $resource->addTag($tag, $this, $list->id);
            }
        }
    }

    /**
     * Get all library cards associated with the user.
     *
     * @return \Zend\Db\ResultSet\AbstractResultSet
     * @throws \VuFind\Exception\LibraryCard
     */
    public function getLibraryCards()
    {
        if (!$this->libraryCardsEnabled()) {
            return new \Zend\Db\ResultSet\ResultSet();
        }

        $userId = $this->id;
        $loginTargets = null;
        if ($this->ils && $this->ils->checkCapability('getLoginDrivers')) {
            $loginTargets = $this->ils->getLoginDrivers();
            $loginTargets = array_map(
                function ($a) {
                    return "$a.";
                },
                $loginTargets
            );
        }
        $callback = function ($select) use ($userId, $loginTargets) {
            $select->where->equalTo('user_id', $userId);
            if (!empty($loginTargets)) {
                $select->where->in(
                    new Expression(
                        "substring(cat_username, 1, locate('.', cat_username))",
                        null,
                        [Expression::TYPE_LITERAL]
                    ),
                    $loginTargets
                );
            }
        };

        $userCard = $this->getDbTable('UserCard');
        return $userCard->select($callback);
    }
}
