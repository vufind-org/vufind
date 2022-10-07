<?php
/**
 * Row Definition for user
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
 * @package  Db_Row
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace VuFind\Db\Row;

use Laminas\Crypt\BlockCipher as BlockCipher;
use Laminas\Crypt\Symmetric\Openssl;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Select;

/**
 * Row Definition for user
 *
 * @category VuFind
 * @package  Db_Row
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 *
 * @property int     $id
 * @property ?string $username
 * @property string  $password
 * @property ?string $pass_hash
 * @property string  $firstname
 * @property string  $lastname
 * @property string  $email
 * @property ?string $email_verified
 * @property string  $pending_email
 * @property int     $user_provided_email
 * @property ?string $cat_id
 * @property ?string $cat_username
 * @property ?string $cat_password
 * @property ?string $cat_pass_enc
 * @property string  $college
 * @property string  $major
 * @property ?string $home_library
 * @property string  $created
 * @property string  $verify_hash
 * @property string  $last_login
 * @property ?string $auth_method
 * @property string  $last_language
 */
class User extends RowGateway implements \VuFind\Db\Table\DbTableAwareInterface,
    \LmcRbacMvc\Identity\IdentityInterface
{
    use \VuFind\Db\Table\DbTableAwareTrait;

    /**
     * Is encryption enabled?
     *
     * @var bool
     */
    protected $encryptionEnabled = null;

    /**
     * Encryption key used for catalog passwords (null if encryption disabled):
     *
     * @var string
     */
    protected $encryptionKey = null;

    /**
     * VuFind configuration
     *
     * @var \Laminas\Config\Config
     */
    protected $config = null;

    /**
     * Constructor
     *
     * @param \Laminas\Db\Adapter\Adapter $adapter Database adapter
     */
    public function __construct($adapter)
    {
        parent::__construct('id', 'user', $adapter);
    }

    /**
     * Configuration setter
     *
     * @param \Laminas\Config\Config $config VuFind configuration
     *
     * @return void
     */
    public function setConfig(\Laminas\Config\Config $config)
    {
        $this->config = $config;
    }

    /**
     * Reset ILS login credentials.
     *
     * @return void
     */
    public function clearCredentials()
    {
        $this->cat_username = null;
        $this->cat_password = null;
        $this->cat_pass_enc = null;
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
        $this->cat_id = $catId;
        return $this->save();
    }

    /**
     * Save ILS login credentials.
     *
     * @param string $username Username to save
     * @param string $password Password to save
     *
     * @return mixed           The output of the save method.
     * @throws \VuFind\Exception\PasswordSecurity
     */
    public function saveCredentials($username, $password)
    {
        $this->cat_username = $username;
        if ($this->passwordEncryptionEnabled()) {
            $this->cat_password = null;
            $this->cat_pass_enc = $this->encryptOrDecrypt($password, true);
        } else {
            $this->cat_password = $password;
            $this->cat_pass_enc = null;
        }

        $result = $this->save();

        // Update library card entry after saving the user so that we always have a
        // user id:
        $this->updateLibraryCardEntry();

        return $result;
    }

    /**
     * Save date/time when email address has been verified.
     *
     * @param string $datetime optional date/time to save.
     *
     * @return mixed           The output of the save method.
     */
    public function saveEmailVerified($datetime=null)
    {
        if ($datetime === null) {
            $datetime = date('Y-m-d H:i:s');
        }

        $this->email_verified = $datetime;
        return $this->save();
    }

    /**
     * This is a getter for the Catalog Password. It will return a plaintext version
     * of the password.
     *
     * @return string The Catalog password in plain text
     * @throws \VuFind\Exception\PasswordSecurity
     */
    public function getCatPassword()
    {
        if ($this->passwordEncryptionEnabled()) {
            return isset($this->cat_pass_enc)
                ? $this->encryptOrDecrypt($this->cat_pass_enc, false) : null;
        }
        return $this->cat_password ?? null;
    }

    /**
     * Is ILS password encryption enabled?
     *
     * @return bool
     */
    protected function passwordEncryptionEnabled()
    {
        if (null === $this->encryptionEnabled) {
            $this->encryptionEnabled
                = $this->config->Authentication->encrypt_ils_password ?? false;
        }
        return $this->encryptionEnabled;
    }

    /**
     * This is a central function for encrypting and decrypting so that
     * logic is all in one location
     *
     * @param string $text    The text to be encrypted or decrypted
     * @param bool   $encrypt True if we wish to encrypt text, False if we wish to
     * decrypt text.
     *
     * @return string|bool    The encrypted/decrypted string
     * @throws \VuFind\Exception\PasswordSecurity
     */
    protected function encryptOrDecrypt($text, $encrypt = true)
    {
        // Ignore empty text:
        if (empty($text)) {
            return $text;
        }

        // Load encryption key from configuration if not already present:
        if (null === $this->encryptionKey) {
            if (!isset($this->config->Authentication->ils_encryption_key)
                || empty($this->config->Authentication->ils_encryption_key)
            ) {
                throw new \VuFind\Exception\PasswordSecurity(
                    'ILS password encryption on, but no key set.'
                );
            }
            $this->encryptionKey = $this->config->Authentication->ils_encryption_key;
        }

        // Perform encryption:
        $algo = $this->config->Authentication->ils_encryption_algo ?? 'blowfish';
        $cipher = new BlockCipher(new Openssl(['algorithm' => $algo]));
        $cipher->setKey($this->encryptionKey);
        return $encrypt ? $cipher->encrypt($text) : $cipher->decrypt($text);
    }

    /**
     * Change home library.
     *
     * @param ?string $homeLibrary New home library to store, or null to indicate
     * that the user does not want a default. An empty string is the default for
     * backward compatibility and indicates that system's default pick up location is
     * to be used
     *
     * @return mixed               The output of the save method.
     */
    public function changeHomeLibrary($homeLibrary)
    {
        $this->home_library = $homeLibrary;
        $this->updateLibraryCardEntry();
        return $this->save();
    }

    /**
     * Check whether the email address has been verified yet.
     *
     * @return bool
     */
    public function checkEmailVerified()
    {
        return !empty($this->email_verified);
    }

    /**
     * Get a list of all tags generated by the user in favorites lists.  Note that
     * the returned list WILL NOT include tags attached to records that are not
     * saved in favorites lists.
     *
     * @param string $resourceId Filter for tags tied to a specific resource (null
     * for no filter).
     * @param int    $listId     Filter for tags tied to a specific list (null for no
     * filter).
     * @param string $source     Filter for tags tied to a specific record source.
     * (null for no filter).
     *
     * @return \Laminas\Db\ResultSet\AbstractResultSet
     */
    public function getTags($resourceId = null, $listId = null, $source = null)
    {
        return $this->getDbTable('Tags')
            ->getListTagsForUser($this->id, $resourceId, $listId, $source);
    }

    /**
     * Get tags assigned by the user to a favorite list.
     *
     * @param int $listId List id
     *
     * @return \Laminas\Db\ResultSet\AbstractResultSet
     */
    public function getListTags($listId)
    {
        return $this->getDbTable('Tags')
            ->getForList($listId, $this->id);
    }

    /**
     * Same as getTags(), but returns a string for use in edit mode rather than an
     * array of tag objects.
     *
     * @param string $resourceId Filter for tags tied to a specific resource (null
     * for no filter).
     * @param int    $listId     Filter for tags tied to a specific list (null for no
     * filter).
     * @param string $source     Filter for tags tied to a specific record source
     * (null for no filter).
     *
     * @return string
     */
    public function getTagString($resourceId = null, $listId = null, $source = null)
    {
        return $this->formatTagString($this->getTags($resourceId, $listId, $source));
    }

    /**
     * Same as getTagString(), but operates on a list of tags.
     *
     * @param array $tags Tags
     *
     * @return string
     */
    public function formatTagString($tags)
    {
        $tagStr = '';
        if (count($tags) > 0) {
            foreach ($tags as $tag) {
                if (strstr($tag->tag, ' ')) {
                    $tagStr .= "\"$tag->tag\" ";
                } else {
                    $tagStr .= "$tag->tag ";
                }
            }
        }
        return trim($tagStr);
    }

    /**
     * Get all of the lists associated with this user.
     *
     * @return \Laminas\Db\ResultSet\AbstractResultSet
     */
    public function getLists()
    {
        $userId = $this->id;
        $callback = function ($select) use ($userId) {
            $select->columns(
                [
                    Select::SQL_STAR,
                    'cnt' => new Expression(
                        'COUNT(DISTINCT(?))',
                        ['ur.resource_id'],
                        [Expression::TYPE_IDENTIFIER]
                    )
                ]
            );
            $select->join(
                ['ur' => 'user_resource'],
                'user_list.id = ur.list_id',
                [],
                $select::JOIN_LEFT
            );
            $select->where->equalTo('user_list.user_id', $userId);
            $select->group(
                [
                    'user_list.id', 'user_list.user_id', 'title', 'description',
                    'created', 'public'
                ]
            );
            $select->order(['title']);
        };

        $table = $this->getDbTable('UserList');
        return $table->select($callback);
    }

    /**
     * Get information saved in a user's favorites for a particular record.
     *
     * @param string $resourceId ID of record being checked.
     * @param int    $listId     Optional list ID (to limit results to a particular
     * list).
     * @param string $source     Source of record to look up
     *
     * @return array
     */
    public function getSavedData(
        $resourceId,
        $listId = null,
        $source = DEFAULT_SEARCH_BACKEND
    ) {
        $table = $this->getDbTable('UserResource');
        return $table->getSavedData($resourceId, $source, $listId, $this->id);
    }

    /**
     * Add/update a resource in the user's account.
     *
     * @param \VuFind\Db\Row\Resource $resource        The resource to add/update
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
    public function saveResource(
        $resource,
        $list,
        $tagArray,
        $notes,
        $replaceExisting = true
    ) {
        // Create the resource link if it doesn't exist and update the notes in any
        // case:
        $linkTable = $this->getDbTable('UserResource');
        $linkTable->createOrUpdateLink($resource->id, $this->id, $list->id, $notes);

        // If we're replacing existing tags, delete the old ones before adding the
        // new ones:
        if ($replaceExisting) {
            $resource->deleteTags($this, $list->id);
        }

        // Add the new tags:
        foreach ($tagArray as $tag) {
            $resource->addTag($tag, $this, $list->id);
        }
    }

    /**
     * Given an array of item ids, remove them from all lists
     *
     * @param array  $ids    IDs to remove from the list
     * @param string $source Type of resource identified by IDs
     *
     * @return void
     */
    public function removeResourcesById($ids, $source = DEFAULT_SEARCH_BACKEND)
    {
        // Retrieve a list of resource IDs:
        $resourceTable = $this->getDbTable('Resource');
        $resources = $resourceTable->findResources($ids, $source);

        $resourceIDs = [];
        foreach ($resources as $current) {
            $resourceIDs[] = $current->id;
        }

        // Remove Resource (related tags are also removed implicitly)
        $userResourceTable = $this->getDbTable('UserResource');
        // true here makes sure that only tags in lists are deleted
        $userResourceTable->destroyLinks($resourceIDs, $this->id, true);
    }

    /**
     * Whether library cards are enabled
     *
     * @return bool
     */
    public function libraryCardsEnabled()
    {
        return isset($this->config->Catalog->library_cards)
            && $this->config->Catalog->library_cards;
    }

    /**
     * Get all library cards associated with the user.
     *
     * @return \Laminas\Db\ResultSet\AbstractResultSet
     * @throws \VuFind\Exception\LibraryCard
     */
    public function getLibraryCards()
    {
        if (!$this->libraryCardsEnabled()) {
            return new \Laminas\Db\ResultSet\ResultSet();
        }
        $userCard = $this->getDbTable('UserCard');
        return $userCard->select(['user_id' => $this->id]);
    }

    /**
     * Get library card data
     *
     * @param int $id Library card ID
     *
     * @return UserCard|false Card data if found, false otherwise
     * @throws \VuFind\Exception\LibraryCard
     */
    public function getLibraryCard($id = null)
    {
        if (!$this->libraryCardsEnabled()) {
            throw new \VuFind\Exception\LibraryCard('Library Cards Disabled');
        }

        $userCard = $this->getDbTable('UserCard');
        if ($id === null) {
            $row = $userCard->createRow();
            $row->card_name = '';
            $row->user_id = $this->id;
            $row->cat_username = '';
            $row->cat_password = '';
        } else {
            $row = $userCard->select(['user_id' => $this->id, 'id' => $id])
                ->current();
            if ($row === false) {
                throw new \VuFind\Exception\LibraryCard('Library Card Not Found');
            }
            if ($this->passwordEncryptionEnabled()) {
                $row->cat_password = $this->encryptOrDecrypt(
                    $row->cat_pass_enc,
                    false
                );
            }
        }

        return $row;
    }

    /**
     * Delete library card
     *
     * @param int $id Library card ID
     *
     * @return void
     * @throws \VuFind\Exception\LibraryCard
     */
    public function deleteLibraryCard($id)
    {
        if (!$this->libraryCardsEnabled()) {
            throw new \VuFind\Exception\LibraryCard('Library Cards Disabled');
        }

        $userCard = $this->getDbTable('UserCard');
        $row = $userCard->select(['id' => $id, 'user_id' => $this->id])->current();

        if (empty($row)) {
            throw new \Exception('Library card not found');
        }
        $row->delete();

        if ($row->cat_username == $this->cat_username) {
            // Activate another card (if any) or remove cat_username and cat_password
            $cards = $this->getLibraryCards();
            if ($cards->count() > 0) {
                $this->activateLibraryCard($cards->current()->id);
            } else {
                $this->cat_username = null;
                $this->cat_password = null;
                $this->cat_pass_enc = null;
                $this->save();
            }
        }
    }

    /**
     * Activate a library card for the given username
     *
     * @param int $id Library card ID
     *
     * @return void
     * @throws \VuFind\Exception\LibraryCard
     */
    public function activateLibraryCard($id)
    {
        if (!$this->libraryCardsEnabled()) {
            throw new \VuFind\Exception\LibraryCard('Library Cards Disabled');
        }
        $userCard = $this->getDbTable('UserCard');
        $row = $userCard->select(['id' => $id, 'user_id' => $this->id])->current();

        if (!empty($row)) {
            $this->cat_username = $row->cat_username;
            $this->cat_password = $row->cat_password;
            $this->cat_pass_enc = $row->cat_pass_enc;
            $this->home_library = $row->home_library;
            $this->save();
        }
    }

    /**
     * Save library card with the given information
     *
     * @param int    $id       Card ID
     * @param string $cardName Card name
     * @param string $username Username
     * @param string $password Password
     * @param string $homeLib  Home Library
     *
     * @return int Card ID
     * @throws \VuFind\Exception\LibraryCard
     */
    public function saveLibraryCard(
        $id,
        $cardName,
        $username,
        $password,
        $homeLib = ''
    ) {
        if (!$this->libraryCardsEnabled()) {
            throw new \VuFind\Exception\LibraryCard('Library Cards Disabled');
        }
        $userCard = $this->getDbTable('UserCard');

        // Check that the username is not already in use in another card
        $row = $userCard->select(
            ['user_id' => $this->id, 'cat_username' => $username]
        )->current();
        if (!empty($row) && ($id === null || $row->id != $id)) {
            throw new \VuFind\Exception\LibraryCard(
                'Username is already in use in another library card'
            );
        }

        $row = null;
        if ($id !== null) {
            $row = $userCard->select(['user_id' => $this->id, 'id' => $id])
                ->current();
        }
        if (empty($row)) {
            $row = $userCard->createRow();
            $row->user_id = $this->id;
            $row->created = date('Y-m-d H:i:s');
        }
        $row->card_name = $cardName;
        $row->cat_username = $username;
        if (!empty($homeLib)) {
            $row->home_library = $homeLib;
        }
        if ($this->passwordEncryptionEnabled()) {
            $row->cat_password = null;
            $row->cat_pass_enc = $this->encryptOrDecrypt($password, true);
        } else {
            $row->cat_password = $password;
            $row->cat_pass_enc = null;
        }

        $row->save();

        // If this is the first or active library card, or no credentials are
        // currently set, activate the card now
        if ($this->getLibraryCards()->count() == 1 || empty($this->cat_username)
            || $this->cat_username === $row->cat_username
        ) {
            $this->activateLibraryCard($row->id);
        }

        return $row->id;
    }

    /**
     * Verify that the current card information exists in user's library cards
     * (if enabled) and is up to date.
     *
     * @return void
     * @throws \VuFind\Exception\PasswordSecurity
     */
    protected function updateLibraryCardEntry()
    {
        if (!$this->libraryCardsEnabled() || empty($this->cat_username)) {
            return;
        }

        $userCard = $this->getDbTable('UserCard');
        $row = $userCard->select(
            ['user_id' => $this->id, 'cat_username' => $this->cat_username]
        )->current();
        if (empty($row)) {
            $row = $userCard->createRow();
            $row->user_id = $this->id;
            $row->cat_username = $this->cat_username;
            $row->card_name = $this->cat_username;
            $row->created = date('Y-m-d H:i:s');
        }
        // Always update home library and password
        $row->home_library = $this->home_library;
        $row->cat_password = $this->cat_password;
        $row->cat_pass_enc = $this->cat_pass_enc;

        $row->save();
    }

    /**
     * Destroy the user.
     *
     * @param bool $removeComments Whether to remove user's comments
     * @param bool $removeRatings  Whether to remove user's ratings
     *
     * @return int The number of rows deleted.
     */
    public function delete($removeComments = true, $removeRatings = true)
    {
        // Remove all lists owned by the user:
        $lists = $this->getLists();
        $table = $this->getDbTable('UserList');
        foreach ($lists as $current) {
            // The rows returned by getLists() are read-only, so we need to retrieve
            // a new object for each row in order to perform a delete operation:
            $list = $table->getExisting($current->id);
            $list->delete($this, true);
        }
        $resourceTags = $this->getDbTable('ResourceTags');
        $resourceTags->destroyResourceLinks(null, $this->id);
        if ($removeComments) {
            $comments = $this->getDbTable('Comments');
            $comments->deleteByUser($this);
        }
        if ($removeRatings) {
            $ratings = $this->getDbTable('Ratings');
            $ratings->deleteByUser($this);
        }

        // Remove the user itself:
        return parent::delete();
    }

    /**
     * Update the verification hash for this user
     *
     * @return bool save success
     */
    public function updateHash()
    {
        $hash = md5($this->username . $this->password . $this->pass_hash . rand());
        // Make totally sure the timestamp is exactly 10 characters:
        $time = str_pad(substr((string)time(), 0, 10), 10, '0', STR_PAD_LEFT);
        $this->verify_hash = $hash . $time;
        return $this->save();
    }

    /**
     * Updated saved language
     *
     * @param string $language New language
     *
     * @return void
     */
    public function updateLastLanguage($language)
    {
        $this->last_language = $language;
        $this->save();
    }

    /**
     * Update the user's email address, if appropriate. Note that this does NOT
     * automatically save the row; it assumes a subsequent call will be made to
     * the save() method.
     *
     * @param string $email        New email address
     * @param bool   $userProvided Was this email provided by the user (true) or
     * an automated lookup (false)?
     *
     * @return void
     */
    public function updateEmail($email, $userProvided = false)
    {
        // Only change the email if it is a non-empty value and was user provided
        // (the user is always right) or the previous email was NOT user provided
        // (a value may have changed in an upstream system).
        if (!empty($email) && ($userProvided || !$this->user_provided_email)) {
            $this->email = $email;
            $this->user_provided_email = $userProvided ? 1 : 0;
        }
    }

    /**
     * Get the list of roles of this identity
     *
     * @return string[]|\Rbac\Role\RoleInterface[]
     */
    public function getRoles()
    {
        return ['loggedin'];
    }
}
