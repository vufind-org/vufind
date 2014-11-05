# Worldcat Discovery PHP Library

A PHP library for WorldCat Discovery API. 

## Installation

The easiest way to install the OCLC Auth library is with Composer. Composer is a PHP dependency management tool that let's you declare the dependencies your project requires and installs them for you.

Sample Composer file

```javascript
{
"name" : "MyApp",

	"repositories": [
	{
	"type": "git",
	"url": "https://github.com/OCLC-Developer-Network/worldcat-discovery-php.git"
	},
    {
    "type": "git",
    "url": "https://github.com/OCLC-Developer-Network/oclc-auth-php.git"
    }
	],
	"require" : {
	"worldcat/discovery" : ">=0.7"
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
<?php
require_once('vendor/autoload.php');
```

Basic Example Reading a Bibliographic Record looks like this
```php
   require_once('vendor/autoload.php');

   use OCLC\Auth\WSKey;
   use OCLC\Auth\AccessToken;
   use WorldCat\Discovery\Bib;
   
   $key = 'api-key';
   $secret = 'api-key-secret';
   $options = array('services' => array('WorldCatDiscoveryAPI', 'refresh_token'));
   $wskey = new WSKey($key, $secret, $options);
   $accessToken = $wskey->getAccessTokenWithClientCredentials('128807', '128807'));
   
   $bib = Bib::Find(7977212, $accessToken);
   
   if (is_a($bib, 'WorldCat\Discovery\Error')) {
   		echo $bib->getErrorCode();
   		echo $bib->getErrorMessage();
   } else {
   		echo $bib->getName();
   		print_r($bib->getID());
   		echo $bib->getID()
   		print_r($bib->type();
   		echo $bib->type();
   		print_r($bib->getAuthor());
   		echo $bib->getAuthor->getName();
   		$contributors = array_map($bib->getContributors(), function($contributor){return $contributor->getName();});
   		print_r($contributors);
   }
```

## [Other Examples](https://github.com/OCLC-Developer-Network/worldcat-discovery-php/blob/master/docs/example.rst)
##  [Testing](https://github.com/OCLC-Developer-Network/worldcat-discovery-php/blob/master/docs/RunningTests.rst)