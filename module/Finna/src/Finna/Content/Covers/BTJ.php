<?php
/**
 * BTJ Cover Image Service cover content loader.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2018.
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
 * @package  Content
 * @author   Kalle Pyykkönen <kalle.pyykkonen@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace Finna\Content\Covers;

use VuFindCode\ISBN;

/**
 * BTJ Cover Image Service cover content loader.
 *
 * @category VuFind
 * @package  Content
 * @author   Kalle Pyykkönen <kalle.pyykkonen@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class BTJ extends \VuFind\Content\AbstractCover
{
    /**
     * Recordloader to fetch the current record
     *
     * @var VuFind\RecordLoader
     */
    protected $recordLoader = null;

    /**
     * Constructor
     *
     * @param VuFind\RecordLoader $recordLoader Record loader.
     */
    public function __construct(\VuFind\Record\Loader $recordLoader)
    {
        $this->recordLoader = $recordLoader;
        $this->supportsIsbn = false;
        $this->cacheAllowed = false;
    }

    /**
     * Get image URL for a particular API key and set of IDs (or false if invalid).
     *
     * @param string $key  API key
     * @param string $size Size of image to load (small/medium/large)
     * @param array  $ids  Associative array of identifiers (keys may include 'isbn'
     * pointing to an ISBN object and 'issn' pointing to a string)
     *
     * @return string|bool
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getUrl($key, $size, $ids)
    {
        $sizeCodes = [
            'medium' => '04',
            'small' => '06',
            'large' => '07'
        ];
        try {
            $driver = $this->getRecord($ids['recordid']);
            $recordISBN = new ISBN($driver->getCleanISBN());
            if ($isbn = $recordISBN->get13()) {
                return "https://armas.btj.fi/request.php?error=1&"
                . "id=$key&pid=$isbn&ftype=$sizeCodes[$size]";
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Does this plugin support the provided ID array?
     *
     * @param array $ids IDs that will later be sent to load() -- see below.
     *
     * @return bool
     */
    public function supports($ids)
    {
        return isset($ids['recordid']);
    }

    /**
     * Get record by id
     *
     * @param string $id Id for the record to load.
     *
     * @return \VuFind\RecordDriver\AbstractBase
     */
    protected function getRecord($id)
    {
        return $this->recordLoader->load($id, 'Solr');
    }
}
