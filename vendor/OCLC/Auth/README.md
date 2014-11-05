OCLC PHP Auth Library
=============
This library is a php wrapper around the Web Service Authentication system used by OCLC web services. 

## Installation

The easiest way to install the OCLC Auth library is with Composer. Composer is a PHP dependency management tool that let's you declare the dependencies your project requires and installs them for you.

Sample Composer file

```javascript
{
  "name" : "MyApp",
  "repositories": 
  [
    {
      "type": "git",
      "url": "https://github.com/OCLC-Developer-Network/oclc-auth-php.git"
    }
  ],
  "require" : 
  {
    "OCLC/Auth" : ">=1.0"
  }
}
```

#### Step 1: Prepare your project

In a Terminal Window

```bash
$ cd {YOUR-PROJECT-ROOT}
$ pico composer.json
```

Copy the contents of the sample composer file above to the `composer.json` file.

#### Step 2: Use composer to install the dependencies

Download composer and use the `install` command to read the JSON file created in step 1 to install the WSKey library in a vendor direcory

```bash
$ curl -s https://getcomposer.org/installer | php
$ php composer.phar install
```

#### Step 3: Include the libraries in your code
To start using this library you need to include the OCLC Auth library in your code. Add the following to the top of your file:
```php
require_once('vendor/autoload.php');
```

Basic Example: Use an HMAC Signature on a request to the [WorldCat Metadata API](http://www.oclc.org/developer/develop/web-services/worldcat-metadata-api.en.html)
```php
<?php
   use OCLC\Auth\WSKey;
   use OCLC\User;
   use Guzzle\Http\Client;
   
   $key = 'api-key';
   $secret = 'api-key-secret';
   $wskey = new WSKey($key, $secret);
   
   $url = 'https://worldcat.org/bib/data/823520553?classificationScheme=LibraryOfCongress&holdingLibraryCode=MAIN';
   
   $user = new User('128807', 'principalID', 'principalIDNS');
   $options = array('user'=> $user);
   
   $authorizationHeader = $wskey->getHMACSignature('GET', $url, $options);
    
   $client = new Client();
   $client->getClient()->setDefaultOption('config/curl/' . CURLOPT_SSLVERSION, 3);
   $headers = array();
   $headers['Authorization'] = $authorizationHeader;
   $request = $client->createRequest('GET', $url, $headers);
   
   try {
        $response = $request->send();
        echo $response->getBody(TRUE);
   } catch (\Guzzle\Http\Exception\BadResponseException $error) {
        echo $error->getResponse()->getStatusCode();
        echo $error->getResponse()->getWwwAuthenticate();
        echo $error->getResponse()->getBody(true);
   }
?>
```

[Other Examples](https://github.com/OCLC-Developer-Network/oclc-auth-php/blob/master/docs/example.rst)

###WSKey Configuration For Explicit Authorization Code Flow
In order to be able to use the Explicit Authorization Code flow your WSKey will need to be configured with a redirect URI. The redirect URI is the url your application lives at.
For example if my applicaiton lives at http://library.share.worldcat.org/myApp.php this will be your redirect URI. The redirect URI can be sest to localhost addresses for testing purposes.
If you need a new WSKey with a redirect_uri, this can be requested via Service Config.
If you already have a WSKey that you want a redirect_uri added to send an email to devnet[at]oclc[dot]org specifying your WorldCat username, the WSKey you want changed and the value of your redirect URI.

##[Other Installation Methods](https://github.com/OCLC-Developer-Network/oclc-auth-php/blob/master/docs/otherInstallMethods.rst)
