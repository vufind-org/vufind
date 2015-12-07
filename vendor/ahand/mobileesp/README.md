#MobileESP
[![License](https://img.shields.io/hexpm/l/plug.svg?style=flat)](http://blog.mobileesp.com/?page_id=13)

[![Platform](https://img.shields.io/badge/platform-PHP-orange.svg?style=flat)](http://blog.mobileesp.com/?page_id=53)
[![Platform](https://img.shields.io/badge/platform-Java-orange.svg?style=flat)](http://blog.mobileesp.com/?page_id=53)
[![Platform](https://img.shields.io/badge/platform-ASP.NET-orange.svg?style=flat)](http://blog.mobileesp.com/?page_id=53)
[![Platform](https://img.shields.io/badge/platform-Python-orange.svg?style=flat)](http://blog.mobileesp.com/?page_id=53)
[![Platform](https://img.shields.io/badge/platform-C++-orange.svg?style=flat)](http://blog.mobileesp.com/?page_id=53)
[![Platform](https://img.shields.io/badge/platform-JavaScript-orange.svg?style=flat)](http://blog.mobileesp.com/?page_id=53)



At last, a dead simple way for web site publishers to detect visitors on mobile web sites! 

MobileESP is free, open source and lightweight. MobileESP has a simple API for detecting mobile devices. The API returns the simple Boolean result of TRUE if the device is the specified type, or FALSE if it isn't. For example, want to know if your visitor is on an iPhone, Android or Windows Phone device? Simply use the method DetectTierIphone(). It's that easy.

Visit the MobileESP web site for tons more information: <a href="http://www.mobileesp.org">www.MobileESP.org</a>.


##Core Principles
MobileESP believes in making it dead easy for a website publisher to detect mobile visitors. As a result, the API follows the DetectXXX() pattern and returns a simple Boolean (true or false) for the type of mobile device or platform desired. The API DetectXXX() methods are consistent by design across supported platforms. 

In addition, a second core principle is that the code is easy for the user to read and understand. Therefore, we believe it's easier for the user to change, update or extend the library, if desired. 

The code is also purposefully written to be modular so that it is not only easier to maintain, but also easier to extend. 


##Server-Side Platforms
> <a href="http://blog.mobileesp.com/?page_id=53">API Documentation</a>

MobileESP started with PHP (and JavaScript) and has been extended by the community to a bunch of other platforms:
- PHP
- Java
- ASP.NET (C#) 
- Python
- C++

Also available in separate repositories: 
- <a href="https://github.com/eimermusic/mobileesp">Ruby</a>: This project was ported by Martin, who separately maintains the Ruby codebase on GitHub.
- <a href="https://bitbucket.org/mbarrero/mobileesp-classic-asp">Classic ASP (VBscript)</a>: This project was ported by Miguel Barrero, who separately maintains the codebase on BitBucket.


##Client-Side Platforms
> <a href="http://blog.mobileesp.com/?page_id=60">JavaScript API Documentation</a>

MobileESP is also available for JavaScript to be run in the browser. Support for client-side JavaScript processing on phones is very poor to completely non-existent. Though much better on modern smartphones, JavaScript is still not quite at the desktop level. Generally speaking, JavaScript is probably reliable only for distinguishing between devices among modern smartphones and tablets. So if you want to know whether your visitor is one of these devices, the JavaScript probably works fine: iPhone, Android, Windows Phone, and BlackBerry 10+. A few other modern smartphone OSes such as Sailfish and Ubuntu are also expected to perform well.

A server-side library is recommended for pretty much everything else: from older smartphone OSes to all feature phones, plus smart TVs, gaming devices, and ereaders.


##Live Demo
Anthony updates the live demo pages when he updates the library. The live demo pages show the results of each of the API calls. Point your mobile device browser to the parent demo page: <a href="http://www.hand-interactive.com/detect/">http://www.hand-interactive.com/detect/</a>
- <a href="http://www.hand-interactive.com/detect/mobileesp_demo_php.htm">PHP Demo Page</a>
- <a href="http://www.hand-interactive.com/detect/mobileesp_demo_javascript.htm">JavaScript Demo Page</a>


##Useragent Test Strings
The MobileESP project tests the PHP and JavaScript live demos with the included spreadsheet of useragent strings. This set of useragent strings isn't meant to be exhaustive, but rather to feature a wide variety of strings to verify that each of the DetectXXX() methods work as expected. Feel free to use this list of useragent strings on your own project, as well.


##Updated May 2015!
Anthony provided a much-needed update in May 2015. Please see the change log in the beginning of each file for full details. 

Anthony tested the PHP and JavaScript libraries with the new useragent strings in the spreadsheet. However, Anthony doesn't have the environments set up to test the Java, C#, Python and C++ libraries. Can you help to test these? 

In addition, the Ruby and Classic ASP (VBScript) libraries haven't been updated in several years. Would anyone be interested in updating them?

##Python Question
We've been notified that the Python library doesn't follow the Python community coding standards regarding method names in lower case with underscores separating elements rather than camel case. What do y'all think about this? Would it be better to conform to the Python standards or keep the method names consistent between libraries? Please let Anthony know. 

##Backstory
This project maintains and extends the original code created in 2008 by Anthony Hand of Hand Interactive (<a href="http://www.hand-interactive.com">http://www.hand-interactive.com</a>). Anthony was working on a web site project for a client and wanted an easy way to customize some of the content for smartphones, especially that era's flagship iPhone, Symbian, and BlackBerry devices. However, commonly used Grep-style algorithms at the time were dumb, blunt force tools treating all devices as equal. Thus the PHP and JavaScript libraries were originally born.

NOTE: The MobileESP project was previously hosted on the Google Code web site. Because Google closed down that site and there had been many user requests, MobileESP is now hosted with GitHub. 

##YouTube Quick Talk Video
MobileESP founder, Anthony Hand, gave a short 6 minute talk on the origin and basics of MobileESP at a Silicon Valley event in 2012. Check it out!
<a href="https://youtu.be/F_mFNTbe9lQ">https://youtu.be/F_mFNTbe9lQ</a>


##License
Apache v2.0. More info and the link to the Apache v2.0 license page in each code file. 


##Important Caveat
The MobileESP project code is lightweight and ideal for many web sites. However, this project is not meant to replace other projects offering greater specificity and control, such as <a href="http://wurfl.sourceforge.net/">WURFL</a> or <a href="http://www.handsetdetection.com//">HandsetDetection.com</a>.


##Donations
Yes, usage of the MobileESP code is FREE, so no worries about that. But a donation to the cause helps provide motivation for Anthony to frequently update the code and continue to enhance the cross-platform APIs. And get new phones to do new tests with!
> <a href="http://blog.mobileesp.com/?page_id=25">Donation Info</a>
