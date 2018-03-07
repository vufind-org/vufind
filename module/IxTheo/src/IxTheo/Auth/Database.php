<?php

namespace IxTheo\Auth;
use VuFind\Exception\Auth as AuthException, Zend\Crypt\Password\Bcrypt;

class Database extends \VuFind\Auth\Database
{
    public static $countries = ["", "Afghanistan", "Åland Islands", "Albania", "Algeria", "American Samoa", "Andorra", "Angola", "Anguilla", "Antarctica", "Antigua And Barbuda", "Argentina", "Armenia", "Aruba", "Ascension Island", "Australia", "Austria", "Azerbaijan", "Bahamas", "Bahrain", "Bangladesh", "Barbados", "Belarus", "Belgium", "Belize", "Benin", "Bermuda", "Bhutan", "Bolivia", "Bosnia And Herzegovina", "Botswana", "Bouvet Island", "Brazil", "British Indian Ocean Territory", "British Virgin Islands", "Brunei Darussalam", "Bulgaria", "Burkina Faso", "Burma", "Burundi", "Cambodia", "Cameroon", "Canada", "Canary Islands", "Cape Verde", "Cayman Islands", "Central African Republic", "Chad", "Chile", "China", "Christmas Island", "Cocos (keeling) Islands", "Colombia", "Comoros", "Congo", "Congo", "Cook Islands", "Costa Rica", "CÔte D'ivoire", "Croatia", "Cuba", "Cyprus", "Czech Republic", "Denmark", "Diego Garcia", "Djibouti", "Dominica", "Dominican Republic", "East Timor", "Ecuador", "Egypt", "El Salvador", "Equatorial Guinea", "Eritrea", "Estonia", "Ethiopia", "European Union", "Falkland Islands (malvinas)", "Faroe Islands", "Fiji", "Finland", "France", "French Guiana", "French Polynesia", "French Southern Territories", "Gabon", "Georgia", "Germany", "Ghana", "Gibraltar", "Greece", "Greenland", "Grenada", "Guadeloupe", "Guam", "Guatemala", "Guernsey", "Guinea", "Guinea-Bissau", "Guyana", "Haiti", "Heard Island And Mcdonald Islands", "Honduras", "Hong Kong", "Hungary", "Iceland", "India", "Indonesia", "Iran", "Iraq", "Ireland", "Isle Of Man", "Israel", "Italy", "Jamaica", "Japan", "Jersey", "Jordan", "Kazakhstan", "Kenya", "Kiribati", "Kuwait", "Kyrgyzstan", "Lao People's Democratic Republic", "Latvia", "Lebanon", "Lesotho", "Liberia", "Libya", "Liechtenstein", "Lithuania", "Luxembourg", "Macao", "Madagascar", "Malawi", "Malaysia", "Maldives", "Mali", "Malta", "Marshall Islands", "Martinique", "Mauritania", "Mauritius", "Mayotte", "Mexico", "Micronesia", "Moldova", "Monaco", "Mongolia", "Montenegro", "Montserrat", "Morocco", "Mozambique", "Namibia", "Nauru", "Nepal", "Netherlands", "Netherlands Antilles", "New Caledonia", "New Zealand", "Nicaragua", "Niger", "Nigeria", "Niue", "Norfolk Island", "North Korea", "Northern Mariana Islands", "Norway", "Oman", "Pakistan", "Palau", "Palestinian Territory", "Panama", "Papua New Guinea", "Paraguay", "Peru", "Philippines", "Pitcairn", "Poland", "Portugal", "Puerto Rico", "Qatar", "Republic of Macedonia", "RÉunion", "Romania", "Russian Federation", "Rwanda", "Saint Helena", "Saint Kitts And Nevis", "Saint Lucia", "Saint Pierre And Miquelon", "Saint Vincent and the Grenadines", "Samoa", "San Marino", "Sao Tome And Principe", "Saudi Arabia", "Saudi–Iraqi neutral zone", "Senegal", "Serbia", "Serbien und Montenegro", "Seychelles", "Sierra Leone", "Singapore", "Slovakia", "Slovenia", "Solomon Islands", "Somalia", "South Africa", "South Georgia And The South Sandwich Islands", "South Korea", "Soviet Union", "Spain", "Sri Lanka", "Sudan", "Suriname", "Svalbard And Jan Mayen", "Swaziland", "Sweden", "Switzerland", "Syrian Arab Republic", "Taiwan", "Tajikistan", "Tanzania", "Thailand", "The Gambia", "Togo", "Tokelau", "Tonga", "Trinidad And Tobago", "Tristan da Cunha", "Tunisia", "Turkey", "Turkmenistan", "Turks And Caicos Islands", "Tuvalu", "Uganda", "Ukraine", "United Arab Emirates", "United Kingdom", "United States", "Uruguay", "Uzbekistan", "Vanuatu", "Vatican City", "Venezuela", "Viet Nam", "Virgin Islands", "Wallis And Futuna", "Western Sahara", "Yemen", "Zambia", "Zimbabwe"];
    public static $appellations = ["Mr", "Ms", ""];
    public static $titles = ["B.A.", "M.A.", "M.Div.", "Dipl. Theol.", "Dr.", "Ph.D.", "Th.D.", "Prof.", "Lic. theol.", "Lic. iur. can.", "Student", "Other", ""];

    /**
     * Create a new user account from the request.
     *
     * @param \Zend\Http\PhpEnvironment\Request $request Request object containing
     * new account details.
     *
     * @throws AuthException
     * @return \VuFind\Db\Row\User New user row.
     */
    public function create($request, $user = null, $ixTheoUser = null)
    {
        // Ensure that all expected parameters are populated to avoid notices
        // in the code below.
        $params = [
            'firstname' => '', 'lastname' => '', 'username' => '',
            'password' => '', 'password2' => '', 'email' => '',
            'title' => '', 'institution' => '', 'country' => '',
            'language' => '', 'appellation' => ''
        ];
        foreach ($params as $param => $default) {
            $params[$param] = $request->getPost()->get($param, $default);
        }

        // Validate Input
        $this->validateUsernameAndPassword($params);

        // Invalid Email Check
        $validator = new \Zend\Validator\EmailAddress();
        if (!$validator->isValid($params['email'])) {
            throw new AuthException('Email address is invalid');
        }
        if (!$this->emailAllowed($params['email'])) {
            throw new AuthException('authentication_error_creation_blocked');
        }

        // Make sure we have a unique username
        $table = $this->getUserTable();
        if ($table->getByUsername($params['username'], false)) {
            throw new AuthException('That username is already taken');
        }
        // Make sure we have a unique email
        if ($table->getByEmail($params['email'])) {
            throw new AuthException('That email address is already used');
        }

        // If we got this far, we're ready to create the account:
        if (is_null($user)) {
            $user = $table->createRowForUsername($params['username']);
        }
        $user->firstname = $params['firstname'];
        $user->lastname = $params['lastname'];
        $user->email = $params['email'];
        if ($this->passwordHashingEnabled()) {
            $bcrypt = new Bcrypt();
            $user->pass_hash = $bcrypt->create($params['password']);
        } else {
            $user->password = $params['password'];
        }
        $user->save();

        if (!isset($ixTheoUser)) {
            $ixTheoUser = $this->getDbTableManager()->get('IxTheoUser')->getNew($user->id);
        }
        $this->updateIxTheoUser($params, $user, $ixTheoUser);

	// Update the TAD access flag:
	exec("/usr/local/bin/set_tad_access_flag.sh " . $user->id);

        return $user;
    }

    public function updateIxTheoUser($params, \VuFind\Db\Row\User $user,
                                     \IxTheo\Db\Row\IxTheoUser $ixTheoUser)
    {
        $user->firstname = $params['firstname'];
        $user->lastname = $params['lastname'];
        $user->email = $params['email'];
        $user->save();

        $ixTheoUser->appellation = in_array($params['appellation'], Database::$appellations) ? $params['appellation'] : $ixTheoUser->appellation;
        $ixTheoUser->title = in_array($params['title'], Database::$titles) ? $params['title'] : $ixTheoUser->title;
        $ixTheoUser->institution = $params['institution'];
        $ixTheoUser->country = in_array($params['country'], Database::$countries) ? $params['country'] : $ixTheoUser->country;
        $ixTheoUser->language = $params['language'];
        $ixTheoUser->user_type = \IxTheo\Utility::getUserTypeFromUsedEnvironment();
        $ixTheoUser->save();

        // Update the TAD access flag:
        exec("/usr/local/bin/set_tad_access_flag.sh " . $user->id);
    }


    protected function updateUserType($userID) {
         $ixTheoUserTable = $this->getDbTableManager()->get('IxTheoUser');
         if (!isset($ixTheoUserTable) || !$ixTheoUserTable)
             return;
         $ixtheoSelect = $ixTheoUserTable->getSql()->select()->where(['id' => $userID]);
         $userRow = $ixTheoUserTable->selectWith($ixtheoSelect)->current();
         // Derive user_type from the instance used
         if (!isset($userRow))
             return;
         $userRow->user_type = \IxTheo\Utility::getUserTypeFromUsedEnvironment();
         $userRow->save();
    }


    public function authenticate($request)

    {
        // Make sure the credentials are non-blank:
        $this->username = trim($request->getPost()->get('username'));
        $this->password = trim($request->getPost()->get('password'));
        if ($this->username == '' || $this->password == '') {
            throw new AuthException('authentication_error_blank');
        }

        // Validate the credentials:
        $user = $this->getUserTable()->getByUsername($this->username, false);
        if (!is_object($user) || !$this->checkPassword($this->password, $user)) {
            throw new AuthException('authentication_error_invalid');
        }

        // If we got this far, the login was successful:
        $this->updateUserType($user->id);
        return $user;
    }

}
