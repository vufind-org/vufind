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
use VuFind\Config\Reader as ConfigReader,
    VuFind\Exception\LoginRequired as LoginRequiredException,
    VuFind\Tags, VuFind\XSLT\Import\VuFind as ArticleStripper,
    Zend\ServiceManager\ServiceLocatorInterface,
    Zend\ServiceManager\ServiceLocatorAwareInterface;

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
abstract class AbstractBase implements ServiceLocatorAwareInterface
{
    protected $resourceSource = 'VuFind';   // Used for identifying database records
    protected $extraDetails = array();      // For storing extra data with record
    protected $recordIni = null;            // ini file for record settings
    protected $fields = array();

    /**
     * Service locator
     *
     * @var ServiceLocatorInterface
     */
    protected $serviceLocator;

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
        return $table->getForResource($this->getUniqueId(), $this->resourceSource);
    }

    /**
     * Get a sortable title for the record (i.e. no leading articles).
     *
     * @return string
     */
    public function getSortTitle()
    {
        // Child classes should override this with smarter behavior, and the "strip
        // articles" logic probably belongs in a more appropriate place, but for now,
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
     *
     * @return array
     */
    public function getTags($list_id = null, $user_id = null, $sort = 'count')
    {
        $tags = $this->getDbTable('Tags');
        return $tags->getForResource(
            $this->getUniqueId(), $this->resourceSource, 0, $list_id, $user_id, $sort
        );
    }

    /**
     * Add tags to the record.
     *
     * @param \VuFind\Db\Row\User $user The user posting the tag
     * @param string              $tags The user-provided tag string
     *
     * @return void
     */
    public function addTags($user, $tags)
    {
        $resources = $this->getDbTable('Resource');
        $resource
            = $resources->findResource($this->getUniqueId(), $this->resourceSource);
        foreach (Tags::parse($tags) as $tag) {
            $resource->addTag($tag, $user);
        }
    }

    /**
     * Save this record to the user's favorites.
     *
     * @param array               $params Array with some or all of these keys:
     *  <ul>
     *    <li>mytags - Unparsed tag string to associate with record (optional)</li>
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
            $list->rememberLastUsed(); // handled by save() in other case
        }

        // Get or create a resource object as needed:
        $resourceTable = $this->getDbTable('Resource');
        $resource = $resourceTable->findResource(
            $this->getUniqueId(), $this->resourceSource, true, $this
        );

        // Add the information to the user's account:
        $user->saveResource(
            $resource, $list,
            isset($params['mytags'])
            ? Tags::parse(trim($params['mytags'])) : array(),
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
            $this->getUniqueId(), $this->resourceSource, $list_id, $user_id
        );
        $notes = array();
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
            $this->getUniqueId(), $this->resourceSource, $user_id
        );
    }

    /**
     * Get the source value used to identify resources of this type in the database.
     *
     * @return string
     */
    public function getResourceSource()
    {
        return $this->resourceSource;
    }

    /**
     * Return an array of related record suggestion objects (implementing the
     * \VuFind\Related\RelatedInterface) based on the current record.
     *
     * @param array $types Array of relationship types to load; each entry should
     * be a partial class name (i.e. 'Similar' or 'Editions') optionally followed
     * by a colon-separated list of parameters to pass to the constructor.  If the
     * parameter is set to null instead of an array, default settings will be loaded
     * from config.ini.
     *
     * @return array
     */
    public function getRelated($types = null)
    {
        if (is_null($types)) {
            $config = ConfigReader::getConfig($this->recordIni);
            $types = isset($config->Record->related) ?
                $config->Record->related : array();
        }
        $retVal = array();
        foreach ($types as $current) {
            $parts = explode(':', $current);
            $type = $parts[0];
            $params = isset($parts[1]) ? $parts[1] : null;
            $factory = $this->getServiceLocator()->getServiceLocator()
                ->get('RelatedPluginManager');
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
     * Returns an associative array (action => description) of record tabs supported
     * by the data.
     *
     * @return array
     */
    public function getTabs()
    {
        return array();
    }

    /**
     * Does the OpenURL configuration indicate that we should display OpenURLs in
     * the specified context?
     *
     * @param string $area 'results', 'record' or 'holdings'
     *
     * @return bool
     */
    public function openURLActive($area)
    {
        $config = ConfigReader::getConfig();

        // Doesn't matter the target area if no OpenURL resolver is specified:
        if (!isset($config->OpenURL->url)) {
            return false;
        }

        // If a setting exists, return that:
        $key = 'show_in_' . $area;
        if (isset($config->OpenURL->$key)) {
            return $config->OpenURL->$key;
        }

        // If we got this far, use the defaults -- true for results, false for
        // everywhere else.
        return ($area == 'results');
    }

    /**
     * Should we display regular URLs when an OpenURL is present?
     *
     * @return bool
     */
    public function replaceURLsWithOpenURL()
    {
        $config = ConfigReader::getConfig();
        return isset($config->OpenURL->replace_other_urls)
            ? $config->OpenURL->replace_other_urls : false;
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
     * Returns true if the record supports export in the requested format.
     *
     * @param string $format Format corresponding with export.ini
     *
     * @return bool
     */
    public function supportsExport($format)
    {
        // Check the requirements for export in the requested format:
        $exportConfig = ConfigReader::getConfig('export');
        if (isset($exportConfig->$format)) {
            if (isset($exportConfig->$format->requiredMethods)) {
                foreach ($exportConfig->$format->requiredMethods as $method) {
                    // If a required method is missing, give up now:
                    if (!method_exists($this, $method)) {
                        return false;
                    }
                }
            }
            // If we got this far, we didn't encounter a problem, and the
            // requested export format is valid, so we can report success!
            return true;
        }

        // If we got this far, we couldn't find evidence of support:
        return false;
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
     * Get an array of strings representing citation formats supported
     * by this record's data (empty if none).  For possible legal values,
     * see /application/themes/root/helpers/Citation.php.
     *
     * @return array Strings representing citation formats.
     */
    public function getCitationFormats()
    {
        return array();
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
    public function tryMethod($method, $params = array())
    {
        return is_callable(array($this, $method))
            ? call_user_func_array(array($this, $method), $params)
            : null;
    }

    /**
     * Set the service locator.
     *
     * @param ServiceLocatorInterface $serviceLocator Locator to register
     *
     * @return AbstractBase
     */
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;
        return $this;
    }

    /**
     * Get the service locator.
     *
     * @return \Zend\ServiceManager\ServiceLocatorInterface
     */
    public function getServiceLocator()
    {
        return $this->serviceLocator;
    }

    /**
     * Get a database table object.
     *
     * @param string $table Name of table to retrieve
     *
     * @return \VuFind\Db\Table\Gateway
     */
    protected function getDbTable($table)
    {
        return $this->getServiceLocator()->getServiceLocator()
            ->get('DbTablePluginManager')->get($table);
    }

    /**
     * Translate a string if a translator is available.
     *
     * @param string $msg Message to translate
     *
     * @return string
     */
    public function translate($msg)
    {
        $sm = $this->getServiceLocator()->getServiceLocator();
        return $sm->has('Translator')
            ? $sm->get('Translator')->translate($msg) : $msg;
    }
}
