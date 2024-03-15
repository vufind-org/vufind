<?php

/**
 * Table Definition for pages
 *
 * PHP version 8
 *
 * Copyright (C) effective WEBWORK GmbH 2023.
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
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Johannes Schultze <schultze@effective-webwork.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Db\Table;

use Exception;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Expression;
use VuFind\Db\Row\RowGateway;
use VuFind\Db\Table\PluginManager as PluginManager;

use function in_array;

/**
 * Table Definition for pages
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Johannes Schultze <schultze@effective-webwork.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Pages extends Gateway
{
    /**
     * Notifications config
     *
     * @var mixed
     */
    protected $config;

    /**
     * Constructor
     *
     * @param Adapter       $adapter Database adapter
     * @param PluginManager $tm      Table manager
     * @param array         $cfg     Laminas configuration
     * @param mixed         $config  Notifications config
     * @param RowGateway    $rowObj  Row prototype object (null for default)
     * @param string        $table   Name of database table to interface with
     */
    public function __construct(
        Adapter $adapter,
        PluginManager $tm,
        $cfg,
        $config,
        ?RowGateway $rowObj = null,
        $table = 'notifications_pages'
    ) {
        $this->config = $config;
        parent::__construct($adapter, $tm, $cfg, $rowObj, $table);
    }

    /**
     * Insert a new page into the database or update an existing one.
     *
     * @param array $data     Data to be written to the database
     * @param array $pageData Data of an existing page
     * @param int   $page_id  Id of the page to be edited
     *
     * @throws Exception
     */
    public function insertOrUpdatePage($data, $pageData = null, $page_id = null)
    {
        foreach ($this->config['Notifications']['languages'] as $language) {
            if ($pageData['id_' . $language] == null) {
                $page = $this->createRow();
            } else {
                $page = $this->getPageById($pageData['id_' . $language]);
            }

            $page->visibility = $data['visibility'];
            $page->is_external_url = $data['is_external_url'];
            $page->priority = $data['priority'];
            $page->author_id = $data['author_id'];
            $page->headline = $data['headline_' . $language];
            $page->nav_title = $data['nav_title_' . $language];
            $page->content = $data['content_' . $language];
            $page->external_url = $data['external_url_' . $language];
            $page->change_date = $data['change_date'];
            $page->create_date = $data['create_date'];
            $page->language = $language;

            if ($page_id == 'NEW') {
                $page->save();
                $page_id = $page->getPrimaryKeyId();
            }
            if ($page_id != 'NEW') {
                $page->page_id = $page_id;
            }

            $page->save();
        }
    }

    /**
     * Get a list of pages from the database
     *
     * @param array $where Filter setting for the request
     * @param array $order Order settings for the request
     */
    public function getPagesList($where = null, $order = null)
    {
        $callback = function ($select) use ($where, $order) {
            $select->columns(
                [
                    'id' => 'id',
                    'page_id' => 'page_id',
                    'visibility' => 'visibility',
                    'is_external_url' => 'is_external_url',
                    'priority' => 'priority',
                    'author_id' => 'author_id',
                    'headline' => 'headline',
                    'nav_title' => 'nav_title',
                    'content' => 'content',
                    'external_url' => 'external_url',
                    'change_date' => 'change_date',
                    'create_date' => 'create_date',
                    'language' => 'language',
                ]
            );

            if (!$order) {
                $select->order(
                    ['change_date DESC', new Expression('lower(pages.headline)')]
                );
            } else {
                $select->order($order);
            }

            if ($where) {
                $select->where($where);
            }
        };

        $pagesList = [];
        foreach ($this->select($callback) as $i) {
            $pagesList[] = [
                'id' => $i->id,
                'page_id' => $i->page_id,
                'visibility' => $i->visibility,
                'is_external_url' => $i->is_external_url,
                'priority' => $i->priority,
                'author_id' => $i->author_id,
                'headline' => $i->headline,
                'nav_title' => $i->nav_title,
                'content' => $i->content,
                'external_url' => $i->external_url,
                'change_date' => $i->change_date,
                'create_date' => $i->create_date,
            ];
        }
        return $pagesList;
    }

    /**
     * Get all data for a page
     *
     * @param array $page_id Id of the page
     */
    public function getPagesDataByPageId($page_id)
    {
        $page_data = [];
        if ($page_id) {
            $pages = $this->select(['page_id' => $page_id]);
            foreach ($pages as $page) {
                foreach ($this->config['Notifications']['languages'] as $language) {
                    if ($page->language == $language) {
                        foreach ($page->toArray() as $key => $value) {
                            if (!in_array($key, ['visibility', 'is_external_url'])) {
                                $key = $key . '_' . $language;
                            }
                            if (!isset($page_data[$key])) {
                                $page_data[$key] = $value;
                            }
                        }
                    }
                }
            }
        }
        return $page_data;
    }

    /**
     * Get a page object by id
     *
     * @param int $id Id of the page
     *
     * @return mixed page object
     */
    public function getPageById($id)
    {
        if ($id) {
            return $this->select(['id' => $id])->current();
        }
    }

    /**
     * Get all page objects with the same page_id
     *
     * @param int $page_id Id of the page
     *
     * @return mixed Array of page objects
     */
    public function getPagesByPageId($page_id)
    {
        if ($page_id) {
            return $this->select(['page_id' => $page_id]);
        }
    }

    /**
     * Get a page object by page_id and language
     *
     * @param int    $page_id  Id of the page
     * @param string $language Language of the page
     *
     * @return mixed page object
     */
    public function getPageByPageIdAndLanguage($page_id, $language)
    {
        if ($page_id && $language) {
            return $this->select(['page_id' => $page_id, 'language' => $language])->current();
        }
    }

    /**
     * Set the priority of a page
     *
     * @param int $index   New position of the page
     * @param int $page_id Id of the page
     */
    public function setPriorityForPageId($index, $page_id)
    {
        $pages = $this->getPagesByPageId($page_id);
        foreach ($pages as $page) {
            $page->priority = $index;
            $page->save();
        }
    }

    /**
     * Set the visibility of a page
     *
     * @param int $visibility New visibility of the page
     * @param int $page_id    Id of the page
     */
    public function setVisibilityForPageId($visibility, $page_id)
    {
        $pages = $this->getPagesByPageId($page_id);
        foreach ($pages as $page) {
            $page->visibility = $visibility;
            $page->save();
        }
    }

    /**
     * Set the global visibility of a page
     *
     * @param int $visibility_global New visibility of the page
     * @param int $page_id           Id of the page
     */
    public function setVisibilityGlobalForPageId($visibility_global, $page_id)
    {
        $pages = $this->getPagesByPageId($page_id);
        foreach ($pages as $page) {
            $page->visibility_global = $visibility_global;
            $page->save();
        }
    }
}
