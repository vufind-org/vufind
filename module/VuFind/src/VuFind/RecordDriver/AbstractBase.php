<?php
/**
 * Abstract base record model.
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\RecordDriver;
use VuFind\Exception\LoginRequired as LoginRequiredException,
    VuFind\XSLT\Import\VuFind as ArticleStripper;

/**
 * Abstract base record model.
 *
 * This abstract class defines the basic methods for modeling a record in VuFind.
 *
 * @category VuFind2
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
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
     * @var \Zend\Config\Config
     */
    protected $mainConfig;

    /**
     * Record-specific configuration
     *
     * @var \Zend\Config\Config
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
     * @param \Zend\Config\Config $mainConfig   VuFind main configuration (omit for
     * built-in defaults)
     * @param \Zend\Config\Config $recordConfig Record-specific configuration file
     * (omit to use $mainConfig as $recordConfig)
     */
    public function __construct($mainConfig = null, $recordConfig = null)
    {
        $this->mainConfig = $mainConfig;
        $this->recordConfig = (null === $recordConfig) ? $mainConfig : $recordConfig;
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
            $this->getUniqueId(), $this->getResourceSource()
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
    public function getTags($list_id = null, $user_id = null, $sort = 'count',
        $ownerId = null
    ) {
        $tags = $this->getDbTable('Tags');
        return $tags->getForResource(
            $this->getUniqueId(),
            $this->getResourceSource(),
            0, $list_id, $user_id, $sort, $ownerId
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
            $this->getUniqueId(), $this->getResourceSource()
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
            $this->getUniqueId(), $this->getResourceSource()
        );
        foreach ($tags as $tag) {
            $resource->deleteTag($tag, $user);
        }
    }

    /**
     * Save this record to the user's favorites.
     *
     * @param array               $params Array with some or all of these keys:
     *  <ul>
     *    <li>mytags - Tag array to associate with record (optional)</li>
     *    <li>notes - Notes to associate with record (optional)</li>
     *    <li>list - ID of list to save record into (omit to create new list)</li>
     *  </ul>
     * @param \VuFind\Db\Row\User $user   The user saving the record
     *
     * @return void
     */
    public function saveToFavorites($params, $user)
    {
        // Validate incoming parameters:
        if (!$user) {
            throw new LoginRequiredException('You must be logged in first');
        }

        // Get or create a list object as needed:
        $listId = isset($params['list']) ? $params['list'] : '';
        $table = $this->getDbTable('UserList');
        if (empty($listId) || $listId == 'NEW') {
            $list = $table->getNew($user);
            $list->title = $this->translate('My Favorites');
            $list->save($user);
        } else {
            $list = $table->getExisting($listId);
            // Validate incoming list ID:
            if (!$list->editAllowed($user)) {
                throw new \VuFind\Exception\ListPermission('Access denied.');
            }
            $list->rememberLastUsed(); // handled by save() in other case
        }

        // Get or create a resource object as needed:
        $resourceTable = $this->getDbTable('Resource');
        $resource = $resourceTable->findResource(
            $this->getUniqueId(), $this->getResourceSource(), true, $this
        );

        // Add the information to the user's account:
        $user->saveResource(
            $resource, $list,
            isset($params['mytags']) ? $params['mytags'] : [],
            isset($params['notes']) ? $params['notes'] : ''
        );
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
            $this->getUniqueId(), $this->getResourceSource(), $list_id, $user_id
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
            $this->getUniqueId(), $this->getResourceSource(), $user_id
        );
    }

    /**
     * Get the source value used to identify resources of this type in the database.
     *
     * @return string
     */
    public function getResourceSource()
    {
        // Normally resource source is the same as source identifier, but for legacy
        // reasons we need to call Solr 'VuFind' instead.  TODO: clean this up.
        $id = $this->getSourceIdentifier();
        return $id == 'Solr' ? 'VuFind' : $id;
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
        // Normalize "VuFind" identifier to "Solr" (see above).  TODO: clean this up.
        $this->sourceIdentifier = $identifier == 'VuFind' ? 'Solr' : $identifier;
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
     * Return an array of related record suggestion objects (implementing the
     * \VuFind\Related\RelatedInterface) based on the current record.
     *
     * @param \VuFind\Related\PluginManager $factory Related module plugin factory
     * @param array                         $types   Array of relationship types to
     * load; each entry should be a service name (i.e. 'Similar' or 'Editions')
     * optionally followed by a colon-separated list of parameters to pass to the
     * constructor.  If the parameter is set to null instead of an array, default
     * settings will be loaded from config.ini.
     *
     * @return array
     */
    public function getRelated(\VuFind\Related\PluginManager $factory, $types = null)
    {
        if (is_null($types)) {
            $types = isset($this->recordConfig->Record->related) ?
                $this->recordConfig->Record->related : [];
        }
        $retVal = [];
        foreach ($types as $current) {
            $parts = explode(':', $current);
            $type = $parts[0];
            $params = isset($parts[1]) ? $parts[1] : null;
            if ($factory->has($type)) {
                $plugin = $factory->get($type);
                $plugin->init($params, $this);
                $retVal[] = $plugin;
            } else {
                throw new \Exception("Related module {$type} does not exist.");
            }
        }
        return $retVal;
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
     * Get an array of supported, user-activated citation formats.
     *
     * @return array Strings representing citation formats.
     */
    public function getCitationFormats()
    {
        // Default behavior: use all supported options.
        if (!isset($this->mainConfig->Record->citation_formats)
            || $this->mainConfig->Record->citation_formats === true
            || $this->mainConfig->Record->citation_formats === 'true'
        ) {
            return $this->getSupportedCitationFormats();
        }

        // Citations disabled:
        if ($this->mainConfig->Record->citation_formats === false
            || $this->mainConfig->Record->citation_formats === 'false'
        ) {
            return [];
        }

        // Whitelist:
        $whitelist = array_map(
            'trim', explode(',', $this->mainConfig->Record->citation_formats)
        );
        return array_intersect($whitelist, $this->getSupportedCitationFormats());
    }

    /**
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
        return isset($this->extraDetails[$key]) ? $this->extraDetails[$key] : null;
    }

    /**
     * Try to call the requested method and return null if it is unavailable; this is
     * useful for checking for the existence of get methods for particular types of
     * data without causing fatal errors.
     *
     * @param string $method Name of method to call.
     * @param array  $params Array of parameters to pass to method.
     *
     * @return mixed
     */
    public function tryMethod($method, $params = [])
    {
        return is_callable([$this, $method])
            ? call_user_func_array([$this, $method], $params)
            : null;
    }
}
