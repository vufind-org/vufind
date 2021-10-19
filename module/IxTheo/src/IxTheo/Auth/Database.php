<?php

namespace IxTheo\Auth;
use VuFind\Exception\Auth as AuthException, Laminas\Crypt\Password\Bcrypt;

class Database extends \TueFind\Auth\Database
{
    public static $countries = ["", "Afghanistan", "Åland Islands", "Albania", "Algeria", "American Samoa", "Andorra", "Angola", "Anguilla", "Antarctica", "Antigua And Barbuda", "Argentina", "Armenia", "Aruba", "Ascension Island", "Australia", "Austria", "Azerbaijan", "Bahamas", "Bahrain", "Bangladesh", "Barbados", "Belarus", "Belgium", "Belize", "Benin", "Bermuda", "Bhutan", "Bolivia", "Bosnia And Herzegovina", "Botswana", "Bouvet Island", "Brazil", "British Indian Ocean Territory", "British Virgin Islands", "Brunei Darussalam", "Bulgaria", "Burkina Faso", "Burma", "Burundi", "Cambodia", "Cameroon", "Canada", "Canary Islands", "Cape Verde", "Cayman Islands", "Central African Republic", "Chad", "Chile", "China", "Christmas Island", "Cocos (keeling) Islands", "Colombia", "Comoros", "Congo", "Congo", "Cook Islands", "Costa Rica", "CÔte D'ivoire", "Croatia", "Cuba", "Cyprus", "Czech Republic", "Denmark", "Diego Garcia", "Djibouti", "Dominica", "Dominican Republic", "East Timor", "Ecuador", "Egypt", "El Salvador", "Equatorial Guinea", "Eritrea", "Estonia", "Ethiopia", "European Union", "Falkland Islands (malvinas)", "Faroe Islands", "Fiji", "Finland", "France", "French Guiana", "French Polynesia", "French Southern Territories", "Gabon", "Georgia", "Germany", "Ghana", "Gibraltar", "Greece", "Greenland", "Grenada", "Guadeloupe", "Guam", "Guatemala", "Guernsey", "Guinea", "Guinea-Bissau", "Guyana", "Haiti", "Heard Island And Mcdonald Islands", "Honduras", "Hong Kong", "Hungary", "Iceland", "India", "Indonesia", "Iran", "Iraq", "Ireland", "Isle Of Man", "Israel", "Italy", "Jamaica", "Japan", "Jersey", "Jordan", "Kazakhstan", "Kenya", "Kiribati", "Kuwait", "Kyrgyzstan", "Lao People's Democratic Republic", "Latvia", "Lebanon", "Lesotho", "Liberia", "Libya", "Liechtenstein", "Lithuania", "Luxembourg", "Macao", "Madagascar", "Malawi", "Malaysia", "Maldives", "Mali", "Malta", "Marshall Islands", "Martinique", "Mauritania", "Mauritius", "Mayotte", "Mexico", "Micronesia", "Moldova", "Monaco", "Mongolia", "Montenegro", "Montserrat", "Morocco", "Mozambique", "Namibia", "Nauru", "Nepal", "Netherlands", "Netherlands Antilles", "New Caledonia", "New Zealand", "Nicaragua", "Niger", "Nigeria", "Niue", "Norfolk Island", "North Korea", "Northern Mariana Islands", "Norway", "Oman", "Pakistan", "Palau", "Palestinian Territory", "Panama", "Papua New Guinea", "Paraguay", "Peru", "Philippines", "Pitcairn", "Poland", "Portugal", "Puerto Rico", "Qatar", "Republic of Macedonia", "RÉunion", "Romania", "Russian Federation", "Rwanda", "Saint Helena", "Saint Kitts And Nevis", "Saint Lucia", "Saint Pierre And Miquelon", "Saint Vincent and the Grenadines", "Samoa", "San Marino", "Sao Tome And Principe", "Saudi Arabia", "Saudi–Iraqi neutral zone", "Senegal", "Serbia", "Serbien und Montenegro", "Seychelles", "Sierra Leone", "Singapore", "Slovakia", "Slovenia", "Solomon Islands", "Somalia", "South Africa", "South Georgia And The South Sandwich Islands", "South Korea", "Soviet Union", "Spain", "Sri Lanka", "Sudan", "Suriname", "Svalbard And Jan Mayen", "Swaziland", "Sweden", "Switzerland", "Syrian Arab Republic", "Taiwan", "Tajikistan", "Tanzania", "Thailand", "The Gambia", "Togo", "Tokelau", "Tonga", "Trinidad And Tobago", "Tristan da Cunha", "Tunisia", "Turkey", "Turkmenistan", "Turks And Caicos Islands", "Tuvalu", "Uganda", "Ukraine", "United Arab Emirates", "United Kingdom", "United States", "Uruguay", "Uzbekistan", "Vanuatu", "Vatican City", "Venezuela", "Viet Nam", "Virgin Islands", "Wallis And Futuna", "Western Sahara", "Yemen", "Zambia", "Zimbabwe"];
    public static $appellations = ["", "Mr", "Ms"];
    public static $titles = ["", "B.A.", "M.A.", "M.Div.", "Dipl. Theol.", "Dr.", "Ph.D.", "Th.D.", "Prof.", "Lic. theol.", "Lic. iur. can.", "Student", "Other"];

    /**
     * Collect parameters from request and populate them.
     *
     * @param Request $request Request object containing new account details.
     *
     * @return string[]
     */
    protected function collectParamsFromRequest($request)
    {
        $params = parent::collectParamsFromRequest($request);

        $additionalParams = [
            'ixtheo_title' => '', 'ixtheo_institution' => '', 'ixtheo_country' => '',
            'ixtheo_language' => '', 'ixtheo_appellation' => ''
        ];
        foreach ($additionalParams as $param => $default) {
            $params[$param] = $request->getPost()->get($param, $default);
        }

        return $params;
    }

    /**
     * Create a user row object from given parametes.
     *
     * @param string[]  $params Parameters returned from collectParamsFromRequest()
     * @param UserTable $table  The VuFind user table
     *
     * @return \VuFind\Db\Row\User A user row object
     */
    protected function createUserFromParams($params, $table)
    {
        $user = parent::createUserFromParams($params, $table);
        $user->ixtheo_appellation = in_array($params['ixtheo_appellation'], Database::$appellations) ? $params['ixtheo_appellation'] : $user->ixtheo_appellation;
        $user->ixtheo_title = in_array($params['ixtheo_title'], Database::$titles) ? $params['ixtheo_title'] : $user->ixtheo_title;
        $user->ixtheo_country = in_array($params['ixtheo_country'], Database::$countries) ? $params['ixtheo_country'] : $user->ixtheo_country;
        $user->ixtheo_language = $params['ixtheo_language'];
        $user->ixtheo_user_type = \IxTheo\Utility::getUserTypeFromUsedEnvironment();
        $user->save();

        // Update the TAD access flag:
        exec("/usr/local/bin/set_tad_access_flag.sh " . $user->id);

        return $user;
    }

    public function authenticate($request)
    {
        $user = parent::authenticate($request);
        $userSystem = $user->ixtheo_user_type;
        $currentSystem = \IxTheo\Utility::getUserTypeFromUsedEnvironment();

        // Write an additional log file to detect which ixtheo-users are actually used to log into bibstudies+churchlaw.
        // This is technically allowed right now and might lead to problems, so we would like to keep track of the users
        // to see if we can easily prevent them from switching instances at a later point.
        $logEntry = '[' . date('Y-m-d H:i:s') . '] User "' . $user->username . '" with type "' . $userSystem . '" logging into instance "' . basename(getenv('VUFIND_LOCAL_DIR')) . '"' . PHP_EOL;
        file_put_contents('/usr/local/var/log/tuefind/vufind_auth.log', $logEntry, FILE_APPEND);

        if ($userSystem != $currentSystem)
            throw new AuthException($this->translate('authentication_error_wrong_system',
                                    ['%%currentSystem%%' => $currentSystem,
                                     '%%userSystem%%' => $userSystem]));

        return $user;
    }

}
