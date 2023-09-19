<?php
    /**
     * Row Definition for broadcasts
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
     * @package  Db_Row
     * @author   Demian Katz <demian.katz@villanova.edu>
     * @author   Johannes Schultze <schultze@effective-webwork.de>
     * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
     * @link     https://vufind.org Main Site
     */
    namespace VuFind\Db\Row;

    /**
     * Row Definition for index files
     *
     * @category VuFind
     * @package  Db_Row
     * @author   Demian Katz <demian.katz@villanova.edu>
     * @author   Johannes Schultze <schultze@effective-webwork.de>
     * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
     * @link     https://vufind.org Main Site
     */
    class Pages extends \VuFind\Db\Row\RowGateway implements \VuFind\Db\Table\DbTableAwareInterface
    {
        use \VuFind\Db\Table\DbTableAwareTrait;

        /**
         * Constructor
         *
         * @param \Laminas\Db\Adapter\Adapter $adapter Database adapter
         */
        public function __construct($adapter)
        {
            parent::__construct('id', 'notifications_pages', $adapter);
        }

        public function getPrimaryKeyId() {
            if (isset($this->primaryKeyData['id'])) {
                return $this->primaryKeyData['id'];
            }
            return null;
        }
    }
