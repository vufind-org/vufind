<?php
/**
 * Abstract base record model.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace VuFind\RecordDriver;

use VuFind\XSLT\Import\VuFind as ArticleStripper;

/**
 * Abstract base record model.
 *
 * This abstract class defines the basic methods for modeling a record in VuFind.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
abstract class AbstractBase implements \VuFind\Db\Table\DbTableAwareInterface,
    \VuFind\I18n\Translator\TranslatorAwareInterface,
    \VuFindSearch\Response\RecordInterface
{
    use \VuFind\Db\Table\DbTableAwareTrait;
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Used for identifying search backends
     *
     * @var string
     */
    protected $sourceIdentifier = 'Solr';

    /**
     * For storing extra data with record
     *
     * @var array
     */
    protected $extraDetails = [];

    /**
     * Main VuFind configuration
     *
     * @var \Laminas\Config\Config
     */
    protected $mainConfig;

    /**
     * Record-specific configuration
     *
     * @var \Laminas\Config\Config
     */
    protected $recordConfig;

    /**
     * Raw data
     *
     * @var array
     */
    protected $fields = [];

    /**
     * Constructor
     *
     * @param \Laminas\Config\Config $mainConfig   VuFind main configuration (omit
     * for built-in defaults)
     * @param \Laminas\Config\Config $recordConfig Record-specific configuration file
     * (omit to use $mainConfig as $recordConfig)
     */
    public function __construct($mainConfig = null, $recordConfig = null)
    {
        $this->mainConfig = $mainConfig;
        $this->recordConfig = $recordConfig ?? $mainConfig;
    }

    /**
     * Set raw data to initialize the object.
     *
     * @param mixed $data Raw data representing the record; Record Model
     * objects are normally constructed by Record Driver objects using data
     * passed in from a Search Results object.  The exact nature of the data may
     * vary depending on the data source -- the important thing is that the
     * Record Driver + Search Results objects work together correctly.
     *
     * @return void
     */
    public function setRawData($data)
    {
        $this->fields = $data;
    }

    /**
     * Retrieve raw data from object (primarily for use in staff view and
     * autocomplete; avoid using whenever possible).
     *
     * @return mixed
     */
    public function getRawData()
    {
        return $this->fields;
    }

    /**
     * Get text that can be displayed to represent this record in breadcrumbs.
     *
     * @return string Breadcrumb text to represent this record.
     */
    abstract public function getBreadcrumb();

    /**
     * Return the unique identifier of this record for retrieving additional
     * information (like tags and user comments) from the external MySQL database.
     *
     * @return string Unique identifier.
     */
    abstract public function getUniqueID();

    /**
     * Get comments associated with this record.
     *
     * @return array
     */
    public function getComments()
    {
        $table = $this->getDbTable('Comments');
        return $table->getForResource(
            $this->getUniqueId(),
            $this->getSourceIdentifier()
        );
    }

    /**
     * Get a sortable title for the record (i.e. no leading articles).
     *
     * @return string
     */
    public function getSortTitle()
    {
        // Child classes should override this with smarter behavior, and the "strip
        // articles" logic probably belongs in a more appropriate place, but for now
        // in the absence of a better plan, we'll just use the XSLT Importer's strip
        // articles functionality.
        return ArticleStripper::stripArticles($this->getBreadcrumb());
    }

    /**
     * Get tags associated with this record.
     *
     * @param int    $list_id ID of list to load tags from (null for all lists)
     * @param int    $user_id ID of user to load tags from (null for all users)
     * @param string $sort    Sort type ('count' or 'tag')
     * @param int    $ownerId ID of user to check for ownership
     *
     * @return array
     */
    public function getTags(
        $list_id = null,
        $user_id = null,
        $sort = 'count',
        $ownerId = null
    ) {
        $tags = $this->getDbTable('Tags');
        return $tags->getForResource(
            $this->getUniqueId(),
            $this->getSourceIdentifier(),
            0,
            $list_id,
            $user_id,
            $sort,
            $ownerId
        );
    }

    /**
     * Add tags to the record.
     *
     * @param \VuFind\Db\Row\User $user The user posting the tag
     * @param array               $tags The user-provided tags
     *
     * @return void
     */
    public function addTags($user, $tags)
    {
        $resources = $this->getDbTable('Resource');
        $resource = $resources->findResource(
            $this->getUniqueId(),
            $this->getSourceIdentifier()
        );
        foreach ($tags as $tag) {
            $resource->addTag($tag, $user);
        }
    }

    /**
     * Remove tags from the record.
     *
     * @param \VuFind\Db\Row\User $user The user posting the tag
     * @param array               $tags The user-provided tags
     *
     * @return void
     */
    public function deleteTags($user, $tags)
    {
        $resources = $this->getDbTable('Resource');
        $resource = $resources->findResource(
            $this->getUniqueId(),
            $this->getSourceIdentifier()
        );
        foreach ($tags as $tag) {
            $resource->deleteTag($tag, $user);
        }
    }

    /**
     * Get notes associated with this record in user lists.
     *
     * @param int $list_id ID of list to load tags from (null for all lists)
     * @param int $user_id ID of user to load tags from (null for all users)
     *
     * @return array
     */
    public function getListNotes($list_id = null, $user_id = null)
    {
        $db = $this->getDbTable('UserResource');
        $data = $db->getSavedData(
            $this->getUniqueId(),
            $this->getSourceIdentifier(),
            $list_id,
            $user_id
        );
        $notes = [];
        foreach ($data as $current) {
            if (!empty($current->notes)) {
                $notes[] = $current->notes;
            }
        }
        return $notes;
    }

    /**
     * Get a list of lists containing this record.
     *
     * @param int $user_id ID of user to load tags from (null for all users)
     *
     * @return array
     */
    public function getContainingLists($user_id = null)
    {
        $table = $this->getDbTable('UserList');
        return $table->getListsContainingResource(
            $this->getUniqueId(),
            $this->getSourceIdentifier(),
            $user_id
        );
    }

    /**
     * Set the source backend identifier.
     *
     * @param string $identifier Backend identifier
     *
     * @return void
     */
    public function setSourceIdentifier($identifier)
    {
        $this->sourceIdentifier = $identifier;
    }

    /**
     * Return the source backend identifier.
     *
     * @return string
     */
    public function getSourceIdentifier()
    {
        return $this->sourceIdentifier;
    }

    /**
     * Returns true if the record supports real-time AJAX status lookups.
     *
     * @return bool
     */
    public function supportsAjaxStatus()
    {
        return false;
    }

    /**
     * Checks the current record if it's supported for generating OpenURLs.
     *
     * @return bool
     */
    public function supportsOpenUrl()
    {
        return true;
    }

    /**
     * Checks the current record if it's supported for generating COinS-OpenURLs.
     *
     * @return bool
     */
    public function supportsCoinsOpenUrl()
    {
        return true;
    }

    /**
     * Store a piece of supplemental information in the record driver.
     *
     * @param string $key Name of stored information
     * @param mixed  $val Information to store
     *
     * @return void
     */
    public function setExtraDetail($key, $val)
    {
        $this->extraDetails[$key] = $val;
    }

    /**
     * Expand deprecated abbreviations to
     * @param  string $abbr ALA, MLA, Chicago, Vancouver
     * @return string
     */
    protected function expandLegacyAbbreviation(string $abbr) {
        switch (trim($abbr)) {
            case 'APA':
                return 'APA:apa';
            case 'Chicago':
                return 'Chicago:chicago-annotated-bibliography';
            case 'MLA':
                return 'MLA:modern-language-association';
            case 'Vancouver':
                return 'Vancouver:vancouver';
        };

        return '';
    }

    /**
     * Get an array of supported, user-activated citation formats.
     *
     * @return array Strings representing citation formats.
     */
    public function getCitationFormats()
    {
        $formatSetting = $this->mainConfig->Record->citation_formats ?? false;

        // Citations disabled:
        if ($formatSetting === false || $formatSetting === 'false') {
            return [];
        }

        // Legacy: convert to array
        if (is_string($formatSetting)) {
            $formatSetting = explode(',', $formatSetting);
        }

        // Default behavior: use all supported options.
        if ($formatSetting === true || $formatSetting === 'true') {
            $formatSetting = $this->tryMethod(
                'getDefaultCitationFormats',
                [], // ← params, ↓ fallback to legacy
                $this->getSupportedCitationFormats()
            );
        }

        // If no colon: convert to 9.x format
        foreach ($formatSetting as $i => $format) {
            if (strpos($format, ':') === false) {
                $formatSetting[$i] = $this->expandLegacyAbbreviation($format);
            }
        }

        // Trim and remove empty elements:
        return array_filter(array_map('trim', $formatSetting));
    }

    /**
     * Get an array of strings representing default citation formats.
     *
     * For possible legal values,
     * see https://github.com/citation-style-language/styles.
     *
     * @return array Strings representing citation formats.
     */
    protected function getDefaultCitationFormats()
    {
        return [];
    }

    /**
     * @deprecated
     * @see getCitationFormats
     * @see getDefaultCitationFormats
     *
     * Get an array of strings representing citation formats supported
     * by this record's data (empty if none).  For possible legal values,
     * see /application/themes/root/helpers/Citation.php.
     *
     * @return array Strings representing citation formats.
     */
    protected function getSupportedCitationFormats()
    {
        return [];
    }

    /**
     * Retrieve a piece of supplemental information stored using setExtraDetail().
     *
     * @param string $key Name of stored information
     *
     * @return mixed
     */
    public function getExtraDetail($key)
    {
        return $this->extraDetails[$key] ?? null;
    }

    /**
     * Try to call the requested method and return null if it is unavailable; this is
     * useful for checking for the existence of get methods for particular types of
     * data without causing fatal errors.
     *
     * @param string $method  Name of method to call.
     * @param array  $params  Array of parameters to pass to method.
     * @param mixed  $default A default value to return if the method is not
     * callable
     *
     * @return mixed
     */
    public function tryMethod($method, $params = [], $default = null)
    {
        return is_callable([$this, $method]) ? $this->$method(...$params) : $default;
    }
}
