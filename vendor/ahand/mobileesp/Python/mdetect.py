# *******************************************
# Copyright 2010-2015, Anthony Hand
#
#
# File version 2015.05.13 (May 13, 2015)
# Updates:
#	- Moved MobileESP to GitHub. https:#github.com/ahand/mobileesp
#	- Opera Mobile/Mini browser has the same UA string on multiple platforms and doesn't differentiate phone vs. tablet. 
#		- Removed DetectOperaAndroidPhone(). This method is no longer reliable. 
#		- Removed DetectOperaAndroidTablet(). This method is no longer reliable. 
#	- Added support for Windows Phone 10: variable and DetectWindowsPhone10()
#	- Updated DetectWindowsPhone() to include WP10. 
#	- Added support for Firefox OS.  
#		- A variable plus DetectFirefoxOS(), DetectFirefoxOSPhone(), DetectFirefoxOSTablet()
#		- NOTE: Firefox doesn't add UA tokens to definitively identify Firefox OS vs. their browsers on other mobile platforms.
#	- Added support for Sailfish OS. Not enough info to add a tablet detection method at this time. 
#		- A variable plus DetectSailfish(), DetectSailfishPhone()
#	- Added support for Ubuntu Mobile OS. 
#		- DetectUbuntu(), DetectUbuntuPhone(), DetectUbuntuTablet()
#	- Added support for 2 smart TV OSes. They lack browsers but do have WebViews for use by HTML apps. 
#		- One variable for Samsung Tizen TVs, plus DetectTizenTV()
#		- One variable for LG WebOS TVs, plus DetectWebOSTV()
#	- Added DetectTizen(). Tests for "mobile" to disambiguate from Samsung Smart TVs.
#	- Removed variables for obsolete devices: deviceHtcFlyer, deviceXoom.
#	- Updated DetectAndroid(). No longer has a special test case for the HTC Flyer tablet. 
#	- Updated DetectAndroidPhone(). 
#		- Updated internal detection code for Android. 
#		- No longer has a special test case for the HTC Flyer tablet. 
#		- Checks against DetectOperaMobile() on Android and reports here if relevant. 
#	- Updated DetectAndroidTablet(). 
#		- No longer has a special test case for the HTC Flyer tablet. 
#		- Checks against DetectOperaMobile() on Android to exclude it from here.
#	- DetectMeego(): Changed definition for this method. Now detects any Meego OS device, not just phones. 
#	- DetectMeegoPhone(): NEW. For Meego phones. Ought to detect Opera browsers on Meego, as well.  
#	- DetectTierIphone(): Added support for phones running Sailfish, Ubuntu and Firefox Mobile. 
#	- DetectTierTablet(): Added support for tablets running Ubuntu and Firefox Mobile. 
#	- DetectSmartphone(): Added support for Meego phones. 
#	- Caught this library up to the PHP, JavaScript and Java versions. Updates include: 
#		- Added support for Bada: a variable and DetectBada(). This detects any Bada OS device, but (almost) all are phones.
#		- Refactored the Windows Phone delegate-related properties and features. Now fires for any Windows Phone, not just WP7. 
#			- The event fires now when DetectWindowsPhone() is true. 
#		- Added support for Windows Phone 8: DetectWindowsPhone8().
#		- Updated DetectWindowsMobile(). Excludes any Windows Phone device, not just WP7. 
#		- Added support for BlackBerry 10 OS phones: DetectBlackBerry10Phone().
#		- Updated DetectSmartphone() to sync with the other libraries. 
#		- Updated DetectTierIphone() to sync with the other libraries.
#		- OnInit(EventArgs e): Fixed the user agent and httpaccept init logic.
#	- Refactored the detection logic in DetectMobileQuick() and DetectMobileLong().
#		- Moved a few detection tests for older browsers to Long. 
#
#
#
# File version date: Feburary 10, 2012
#       Creation:
#       - Cloned from http://code.google.com/p/mobileesp/source/browse/Java/UAgentInfo.java
#                 and http://code.google.com/p/mobileesp/source/browse/PHP/mdetect.php
# 		- Port to Python: Alexey Evseev (alexevseev@gmail.com)
# 		- Made for www.irk.fm website
#
#
#
# LICENSE INFORMATION
# Licensed under the Apache License, Version 2.0 (the "License");
# you may not use this file except in compliance with the License.
# You may obtain a copy of the License at
#        http://www.apache.org/licenses/LICENSE-2.0
# Unless required by applicable law or agreed to in writing,
# software distributed under the License is distributed on an
# "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND,
# either express or implied. See the License for the specific
# language governing permissions and limitations under the License. 
#
#
# ABOUT THIS PROJECT
#   Project Owner: Anthony Hand
#   Email: anthony.hand@gmail.com
#   Web Site: http://www.mobileesp.com
#   Source Files: https://github.com/ahand/mobileesp
#
#   Versions of this code are available for:
#      PHP, JavaScript, Java, ASP.NET (C#), Ruby and Python
#
# *******************************************


class UAgentInfo(object):   
    """The UAgentInfo class encapsulates information about
    a browser's connection to your web site.
    You can use it to find out whether the browser asking for
    your site's content is probably running on a mobile device.
    The methods were written so you can be as granular as you want.
    For example, enquiring whether it's as specific as an iPod Touch or
    as general as a smartphone class device.
    The object's methods return true, or false.
    """
   
    # Initialize some initial smartphone string variables.
    engineWebKit = "webkit"
    
    deviceIphone = "iphone"
    deviceIpod = "ipod"
    deviceIpad = "ipad"
    deviceMacPpc = "macintosh" #Used for disambiguation
    
    deviceAndroid = "android"
    deviceGoogleTV = "googletv"
    
    deviceSymbian = "symbian"
    deviceS60 = "series60"
    deviceS70 = "series70"
    deviceS80 = "series80"
    deviceS90 = "series90"
    
    deviceWinPhone7 = "windows phone os 7"
    deviceWinPhone8 = "windows phone 8"
    deviceWinPhone10 = "windows phone 10"
    deviceWinMob = "windows ce"
    deviceWindows = "windows"
    deviceIeMob = "iemobile"
    devicePpc = "ppc" #Stands for PocketPC
    enginePie = "wm5 pie" #An old Windows Mobile
    
    deviceBB = "blackberry"
    deviceBB10 = "bb10" #For the new BB 10 OS
    vndRIM = "vnd.rim" #Detectable when BB devices emulate IE or Firefox
    deviceBBStorm = "blackberry95"  #Storm 1 and 2
    deviceBBBold = "blackberry97"  #Bold 97x0 (non-touch)
    deviceBBBoldTouch = "blackberry 99"  #Bold 99x0 (touchscreen)
    deviceBBTour = "blackberry96"  #Tour
    deviceBBCurve = "blackberry89"  #Curve 2
    deviceBBCurveTouch = "blackberry 938"  #Curve Touch 9380
    deviceBBTorch = "blackberry 98"  #Torch
    deviceBBPlaybook = "playbook" #PlayBook tablet
    
    devicePalm = "palm"
    deviceWebOS = "webos" #For Palm devices
    deviceWebOStv = "web0s" #For LG TVs
    deviceWebOShp = "hpwos" #For HP's line of WebOS devices
    
    engineBlazer = "blazer" #Old Palm
    engineXiino = "xiino" #Another old Palm
    
    deviceKindle = "kindle"  #Amazon Kindle, eInk one
    engineSilk = "silk"  #Amazon's accelerated Silk browser for Kindle Fire
    
    deviceNuvifone = "nuvifone"  #Garmin Nuvifone
    deviceBada = "bada" #Samsung's Bada OS    
    deviceTizen = "tizen" #Tizen OS    
    deviceMeego = "meego" #Meego OS    
    deviceSailfish = "sailfish" #Sailfish OS
    deviceUbuntu = "ubuntu"  #Ubuntu Mobile OS
    
    #Initialize variables for mobile-specific content.
    vndwap = "vnd.wap"
    wml = "wml"
    
    #Initialize variables for other random devices and mobile browsers.
    deviceTablet = "tablet" #Generic term for slate and tablet devices
    deviceBrew = "brew"
    deviceDanger = "danger"
    deviceHiptop = "hiptop"
    devicePlaystation = "playstation"
    devicePlaystationVita = "vita"
    deviceNintendoDs = "nitro"
    deviceNintendo = "nintendo"
    deviceWii = "wii"
    deviceXbox = "xbox"
    deviceArchos = "archos"
    
    engineFirefox = "firefox" #For Firefox OS
    engineOpera = "opera" #Popular browser
    engineNetfront = "netfront" #Common embedded OS browser
    engineUpBrowser = "up.browser" #common on some phones
    engineOpenWeb = "openweb" #Transcoding by OpenWave server
    deviceMidp = "midp" #a mobile Java technology
    uplink = "up.link"
    engineTelecaQ = "teleca q" #a modern feature phone browser
    devicePda = "pda" #some devices report themselves as PDAs
    mini = "mini"  #Some mobile browsers put "mini" in their names.
    mobile = "mobile" #Some mobile browsers put "mobile" in their user agent strings.
    mobi = "mobi" #Some mobile browsers put "mobi" in their user agent strings.
    
    #Smart TV strings
    smartTV1 = "smart-tv" #Samsung Tizen smart TVs
    smartTV2 = "smarttv" #LG WebOS smart TVs

    #Use Maemo, Tablet, and Linux to test for Nokia"s Internet Tablets.
    maemo = "maemo"
    linux = "linux"
    qtembedded = "qt embedded" #for Sony Mylo
    mylocom2 = "com2" #for Sony Mylo also
    
    #In some UserAgents, the only clue is the manufacturer.
    manuSonyEricsson = "sonyericsson"
    manuericsson = "ericsson"
    manuSamsung1 = "sec-sgh"
    manuSony = "sony"
    manuHtc = "htc" #Popular Android and WinMo manufacturer
    
    #In some UserAgents, the only clue is the operator.
    svcDocomo = "docomo"
    svcKddi = "kddi"
    svcVodafone = "vodafone"

    #Disambiguation strings.
    disUpdate = "update" #pda vs. update

    def __init__(self, userAgent, httpAccept):
        """Initialize the __userAgent and __httpAccept variables

        Keyword arguments:
        userAgent  -- the User-Agent header
        httpAccept -- the Accept header
        """

        # User-Agent and Accept HTTP request headers
        self.__userAgent = userAgent.lower() if userAgent else ""
        self.__httpAccept = httpAccept.lower() if httpAccept else ""

        # Let's store values for quickly accessing the same info multiple times.
        self.__isIphone = False
        self.__isAndroidPhone = False
        self.__isTierTablet = False
        self.__isTierIphone = False
        self.__isTierRichCss = False
        self.__isTierGenericMobile = False

        # Intialize key stored values.
        self.initDeviceScan()

    def getUserAgent(self):
        """Return the lower case HTTP_USER_AGENT"""
        return self.__userAgent

    def getHttpAccept(self):
        """Return the lower case HTTP_ACCEPT"""
        return self.__httpAccept
    
    def getIsIphone(self):
        """Return whether the device is an Iphone or iPod Touch"""
        return self.__isIphone

    def getIsTierTablet(self):
        """Return whether the device is in the Tablet Tier."""
        return self.__isTierTablet

    def getIsTierIphone(self):
        """Return whether the device is in the Iphone Tier."""
        return self.__isTierIphone

    def getIsTierRichCss(self):
        """Return whether the device is in the 'Rich CSS' tier of mobile devices."""
        return self.__isTierRichCss

    def getIsTierGenericMobile(self):
        """Return whether the device is a generic, less-capable mobile device."""
        return self.__isTierGenericMobile


    def initDeviceScan(self):
        """Initialize Key Stored Values."""
        self.__isIphone = self.detectIphoneOrIpod()
        self.__isAndroidPhone = self.detectAndroidPhone()
        self.__isTierTablet = self.detectTierTablet()
        self.__isTierIphone = self.detectTierIphone()
        self.__isTierRichCss = self.detectTierRichCss()
        self.__isTierGenericMobile = self.detectTierOtherPhones()

    def detectIphone(self):
        """Return detection of an iPhone

        Detects if the current device is an iPhone.
        """
        # The iPad and iPod touch say they're an iPhone! So let's disambiguate.
        return UAgentInfo.deviceIphone in self.__userAgent \
            and not self.detectIpad() \
            and not self.detectIpod()

    def detectIpod(self):
        """Return detection of an iPod Touch

        Detects if the current device is an iPod Touch.
        """
        return UAgentInfo.deviceIpod in self.__userAgent


    def detectIpad(self):
        """Return detection of an iPad

        Detects if the current device is an iPad tablet.
        """
        return UAgentInfo.deviceIpad in self.__userAgent \
            and self.detectWebkit()

    def detectIphoneOrIpod(self):
        """Return detection of an iPhone or iPod Touch

        Detects if the current device is an iPhone or iPod Touch.
        """
        #We repeat the searches here because some iPods may report themselves as an iPhone, which would be okay.
        return UAgentInfo.deviceIphone in self.__userAgent \
            or UAgentInfo.deviceIpod in self.__userAgent

    def detectIos(self):
        """Return detection of an Apple iOS device

        Detects *any* iOS device: iPhone, iPod Touch, iPad.
        """
        return self.detectIphoneOrIpod() \
            or self.detectIpad()

    def detectAndroid(self):
        """Return detection of an Android device

        Detects *any* Android OS-based device: phone, tablet, and multi-media player.
        Also detects Google TV.
        """
        if UAgentInfo.deviceAndroid in self.__userAgent \
           or self.detectGoogleTV():
            return True

        return False


    def detectAndroidPhone(self):
        """Return  detection of an Android phone

        Detects if the current device is a (small-ish) Android OS-based device
        used for calling and/or multi-media (like a Samsung Galaxy Player).
        Google says these devices will have 'Android' AND 'mobile' in user agent.
        Ignores tablets (Honeycomb and later).
        """
        #First, let's make sure we're on an Android device.
        if not self.detectAndroid():
            return False

        #If it's Android and has 'mobile' in it, Google says it's a phone.
        if UAgentInfo.mobile in self.__userAgent:
            return True

        #Special check for Android devices with Opera Mobile/Mini. They should report here.
        if self.detectOperaMobile():
            return True

        return False


    def detectAndroidTablet(self):
        """Return detection of an Android tablet

        Detects if the current device is a (self-reported) Android tablet.
        Google says these devices will have 'Android' and NOT 'mobile' in their user agent.
        """
        #First, let's make sure we're on an Android device.
        if not self.detectAndroid():
            return False

        #Special check for Android devices with Opera Mobile/Mini. They should NOT report here.
        if self.detectOperaMobile():
            return False
    
        #Otherwise, if it's Android and does NOT have 'mobile' in it, Google says it's a tablet.
        return UAgentInfo.mobile not in self.__userAgent


    def detectAndroidWebKit(self):
        """Return detection of an Android WebKit browser

        Detects if the current device is an Android OS-based device and
        the browser is based on WebKit.
        """
        return self.detectAndroid() \
            and self.detectWebkit()

    def detectGoogleTV(self):
        """Return detection of GoogleTV

        Detects if the current device is a GoogleTV.
        """
        return UAgentInfo.deviceGoogleTV in self.__userAgent


    def detectWebkit(self):
        """Return detection of a WebKit browser

        Detects if the current browser is based on WebKit.
        """
        return UAgentInfo.engineWebKit in self.__userAgent

    def detectS60OssBrowser(self):
        """Return detection of Symbian S60 Browser

        Detects if the current browser is the Symbian S60 Open Source Browser.
        """
        #First, test for WebKit, then make sure it's either Symbian or S60.
        return self.detectWebkit() \
            and (UAgentInfo.deviceSymbian in self.__userAgent \
                or UAgentInfo.deviceS60 in self.__userAgent)

    def detectSymbianOS(self):
        """Return detection of SymbianOS

        Detects if the current device is any Symbian OS-based device,
        including older S60, Series 70, Series 80, Series 90, and UIQ,
        or other browsers running on these devices.
        """
        return UAgentInfo.deviceSymbian in self.__userAgent \
            or UAgentInfo.deviceS60 in self.__userAgent \
            or UAgentInfo.deviceS70 in self.__userAgent \
            or UAgentInfo.deviceS80 in self.__userAgent \
            or UAgentInfo.deviceS90 in self.__userAgent

    def detectWindowsPhone(self):
        """Return detection of a Windows Phone device

        Detects if the current browser is a 
     	Windows Phone 7, 8, or 10 device
        """
        return self.detectWindowsPhone7() \
            or self.detectWindowsPhone8() \
            or self.detectWindowsPhone10()

    def detectWindowsPhone7(self):
        """Return detection of Windows Phone 7

        Detects if the current browser is a
        Windows Phone 7 device.
        """
        return UAgentInfo.deviceWinPhone7 in self.__userAgent

    def detectWindowsPhone8(self):
        """Return detection of Windows Phone 8

        Detects if the current browser is a
        Windows Phone 8 device.
        """
        return UAgentInfo.deviceWinPhone8 in self.__userAgent

    def detectWindowsPhone10(self):
        """Return detection of Windows Phone 10

        Detects if the current browser is a
        Windows Phone 10 device.
        """
        return UAgentInfo.deviceWinPhone10 in self.__userAgent

    def detectWindowsMobile(self):
        """Return detection of Windows Mobile

        Detects if the current browser is a Windows Mobile device.
        Excludes Windows Phone 7 devices.
        Focuses on Windows Mobile 6.xx and earlier.
        """
        #Exclude new Windows Phone.
        if self.detectWindowsPhone():
            return False
        #Most devices use 'Windows CE', but some report 'iemobile'
        #  and some older ones report as 'PIE' for Pocket IE.
        #  We also look for instances of HTC and Windows for many of their WinMo devices.
        if UAgentInfo.deviceWinMob in self.__userAgent \
           or UAgentInfo.deviceIeMob in self.__userAgent \
           or UAgentInfo.enginePie in self.__userAgent:
            return True
        # Test for certain Windwos Mobile-based HTC devices.
        if UAgentInfo.manuHtc in self.__userAgent \
           and UAgentInfo.deviceWindows in self.__userAgent:
            return True
        if self.detectWapWml() \
           and UAgentInfo.deviceWindows in self.__userAgent:
            return True
        
        #Test for Windows Mobile PPC but not old Macintosh PowerPC.
        return UAgentInfo.devicePpc in self.__userAgent \
            and UAgentInfo.deviceMacPpc not in self.__userAgent

    def detectBlackBerry(self):
        """Return detection of Blackberry

        Detects if the current browser is any BlackBerry.
        Includes the PlayBook.
        """
        return UAgentInfo.deviceBB in self.__userAgent \
            or UAgentInfo.vndRIM in self.__httpAccept

    def detectBlackBerry10Phone(self):
        """Return detection of a Blackberry 10 OS phone

        Detects if the current browser is a BlackBerry 10 OS phone.
        Excludes the PlayBook.
        """
        return UAgentInfo.deviceBB10 in self.__userAgent \
            and UAgentInfo.mobile in self.__userAgent

    def detectBlackBerryTablet(self):
        """Return detection of a Blackberry Tablet

        Detects if the current browser is on a BlackBerry tablet device.
        Example: PlayBook
        """
        return UAgentInfo.deviceBBPlaybook in self.__userAgent

    def detectBlackBerryWebKit(self):
        """Return detection of a Blackberry device with WebKit browser

        Detects if the current browser is a BlackBerry device AND uses a
        WebKit-based browser. These are signatures for the new BlackBerry OS 6.
        Examples: Torch. Includes the Playbook.
        """
        
        return self.detectBlackBerry() \
            and self.detectWebkit()


    def detectBlackBerryTouch(self):
        """Return detection of a Blackberry touchscreen device

        Detects if the current browser is a BlackBerry Touch
        device, such as the Storm, Torch, and Bold Touch. Excludes the Playbook.
        """
        return UAgentInfo.deviceBBStorm in self.__userAgent \
                or UAgentInfo.deviceBBTorch in self.__userAgent \
                or UAgentInfo.deviceBBBoldTouch in self.__userAgent \
                or UAgentInfo.deviceBBCurveTouch in self.__userAgent

    def detectBlackBerryHigh(self):
        """Return detection of a Blackberry device with a better browser

        Detects if the current browser is a BlackBerry device AND
        has a more capable recent browser. Excludes the Playbook.
        Examples, Storm, Bold, Tour, Curve2
        Excludes the new BlackBerry OS 6 and 7 browser!!
        """
        #Disambiguate for BlackBerry OS 6 or 7 (WebKit) browser
        if self.detectBlackBerryWebKit():
            return False
        if not self.detectBlackBerry():
            return False

        return self.detectBlackBerryTouch() \
            or UAgentInfo.deviceBBBold in self.__userAgent \
            or UAgentInfo.deviceBBTour in self.__userAgent \
            or UAgentInfo.deviceBBCurve in self.__userAgent

    def detectBlackBerryLow(self):
        """Return detection of a Blackberry device with a poorer browser

        Detects if the current browser is a BlackBerry device AND
        has an older, less capable browser.
        Examples: Pearl, 8800, Curve1
        """
        if not self.detectBlackBerry():
            return False

        #Assume that if it's not in the High tier, then it's Low
        return self.detectBlackBerryHigh() \
            or self.detectBlackBerryWebKit()

    def detectPalmOS(self):
        """Return detection of a PalmOS device

        Detects if the current browser is on a PalmOS device.
        """
        #Most devices nowadays report as 'Palm', but some older ones reported as Blazer or Xiino.
        if UAgentInfo.devicePalm in self.__userAgent \
           or  UAgentInfo.engineBlazer in self.__userAgent \
           or  UAgentInfo.engineXiino in self.__userAgent:
            # Make sure it's not WebOS
            return not self.detectPalmWebOS()
        return False

    def detectPalmWebOS(self):
        """Return detection of a Palm WebOS device

        Detects if the current browser is on a Palm device
        running the new WebOS.
        """
        return UAgentInfo.deviceWebOS in self.__userAgent

    def detectWebOSTablet(self):
        """Return detection of an HP WebOS tablet

        Detects if the current browser is on an HP tablet running WebOS.
        """
        return UAgentInfo.deviceWebOShp in self.__userAgent \
            and UAgentInfo.deviceTablet in self.__userAgent

    def detectWebOSTV(self):
        """Return detection of a WebOS smart TV

        Detects if the current browser is on a WebOS smart TV.
        """
        return UAgentInfo.deviceWebOStv in self.__userAgent \
            and UAgentInfo.smartTV2 in self.__userAgent

    def detectGarminNuvifone(self):
        """Return detection of a Garmin Nuvifone

        Detects if the current browser is a
        Garmin Nuvifone.
        """
        return UAgentInfo.deviceNuvifone in self.__userAgent

    def detectBada(self):
        """Return detection of a Bada device

        Detects a device running the Bada OS from Samsung.
        """
        return UAgentInfo.deviceBada in self.__userAgent

    def detectTizen(self):
        """Return detection of a Tizen device

        Detects a device running the Tizen smartphone OS.
        """
        return UAgentInfo.deviceTizen in self.__userAgent \
            and UAgentInfo.mobile in self.__userAgent

    def detectTizenTV(self):
        """Return detection of a Tizen smart TV

        Detects if the current browser is on a Tizen smart TV.
        """
        return UAgentInfo.deviceTizen in self.__userAgent \
            and UAgentInfo.smartTV1 in self.__userAgent

    def detectMeego(self):
        """Return detection of a Meego device

        Detects a device running the Meego OS.
        """
        return UAgentInfo.deviceMeego in self.__userAgent 

    def detectMeegoPhone(self):
        """Return detection of a Meego phone

        Detects a phone running the Meego OS.
        """
        return UAgentInfo.deviceMeego in self.__userAgent \
            and UAgentInfo.mobi in self.__userAgent

    def detectFirefoxOS(self):
        """Return detection of a Firefox OS mobile device

        Detects a mobile device (probably) running the Firefox OS.
        """
        return self.detectFirefoxOSPhone() \
            or self.detectFirefoxOSTablet()

    def detectFirefoxOSPhone(self):
        """Return detection of a Firefox OS phone

        Detects a phone (probably) running the Firefox OS.
        """
        if self.detectIos() \
            or self.detectAndroid() \
            or self.detectSailfish():
            return False

        if UAgentInfo.engineFirefox in self.__userAgent \
           and UAgentInfo.mobile in self.__userAgent:
            return True

        return False

    def detectFirefoxOSTablet(self):
        """Return detection of a Firefox OS tablet

        Detects a tablet (probably) running the Firefox OS.
        """
        if self.detectIos() \
            or self.detectAndroid() \
            or self.detectSailfish():
            return False

        if UAgentInfo.engineFirefox in self.__userAgent \
           and UAgentInfo.deviceTablet in self.__userAgent:
            return True

        return False

    def detectSailfish(self):
        """Return detection of a Sailfish OS device.

        Detects a device running the Sailfish OS.
        """
        return UAgentInfo.deviceSailfish in self.__userAgent

    def detectSailfishPhone(self):
        """Return detection of a Sailfish phone

        Detects a phone running the Sailfish OS.
        """
        if self.detectSailfish() \
           and UAgentInfo.mobile in self.__userAgent:
            return True

        return False

    def detectUbuntu(self):
        """Return detection of an Ubuntu Mobile OS mobile device

        Detects a mobile device running the Ubuntu Mobile OS.
        """
        return self.detectUbuntuPhone() \
            or self.detectUbuntuTablet()

    def detectUbuntuPhone(self):
        """Return detection of an Ubuntu Mobile OS phone

        Detects a phone running the Ubuntu Mobile OS.
        """
        if UAgentInfo.deviceUbuntu in self.__userAgent \
           and UAgentInfo.mobile in self.__userAgent:
            return True

        return False

    def detectUbuntuTablet(self):
        """Return detection of an Ubuntu Mobile OS tablet

        Detects a tablet running the Ubuntu Mobile OS.
        """
        if UAgentInfo.deviceUbuntu in self.__userAgent \
           and UAgentInfo.deviceTablet in self.__userAgent:
            return True

        return False


    def detectBrewDevice(self):
        """Return detection of a Brew device

        Detects whether the device is a Brew-powered device.
        """
        return UAgentInfo.deviceBrew in self.__userAgent

    def detectDangerHiptop(self):
        """Return detection of a Danger Hiptop

        Detects the Danger Hiptop device.
        """
        return UAgentInfo.deviceDanger in self.__userAgent \
            or UAgentInfo.deviceHiptop in self.__userAgent

    def detectOperaMobile(self):
        """Return detection of an Opera browser for a mobile device

        Detects Opera Mobile or Opera Mini.
        """
        return UAgentInfo.engineOpera in self.__userAgent \
            and (UAgentInfo.mini in self.__userAgent \
                or UAgentInfo.mobi in self.__userAgent)


    def detectWapWml(self):
        """Return detection of a WAP- or WML-capable device

        Detects whether the device supports WAP or WML.
        """
        return UAgentInfo.vndwap in self.__httpAccept \
            or UAgentInfo.wml in self.__httpAccept

    def detectKindle(self):
        """Return detection of a Kindle

        Detects if the current device is an Amazon Kindle (eInk devices only).
        Note: For the Kindle Fire, use the normal Android methods.
        """
        return UAgentInfo.deviceKindle in self.__userAgent \
            and not self.detectAndroid()

    def detectAmazonSilk(self):
        """Return detection of an Amazon Kindle Fire in Silk mode.

        Detects if the current Amazon device is using the Silk Browser.
        Note: Typically used by the the Kindle Fire.
        """
        return UAgentInfo.engineSilk in self.__userAgent

    def detectSonyPlaystation(self):
        """Return detection of Sony Playstation

        Detects if the current device is a Sony Playstation.
        """
        return UAgentInfo.devicePlaystation in self.__userAgent

    def detectGamingHandheld(self):
        """Return detection of a gaming handheld with a modern iPhone-class browser

        Detects if the current device is a handheld gaming device with
   		a touchscreen and modern iPhone-class browser. Includes the Playstation Vita.
        """
        return UAgentInfo.devicePlaystation in self.__userAgent \
            and UAgentInfo.devicePlaystationVita in self.__userAgent

    def detectNintendo(self):
        """Return detection of Nintendo

        Detects if the current device is a Nintendo game device.
        """
        return UAgentInfo.deviceNintendo in self.__userAgent \
            or UAgentInfo.deviceNintendo in self.__userAgent \
            or UAgentInfo.deviceNintendo in self.__userAgent

    def detectXbox(self):
        """Return detection of Xbox

        Detects if the current device is a Microsoft Xbox.
        """
        return UAgentInfo.deviceXbox in self.__userAgent

    def detectGameConsole(self):
        """Return detection of any Game Console

        Detects if the current device is an Internet-capable game console.
        """
        return self.detectSonyPlaystation() \
            or self.detectNintendo() \
            or self.detectXbox()

    def detectMidpCapable(self):
        """Return detection of a MIDP mobile Java-capable device

        Detects if the current device supports MIDP, a mobile Java technology.
        """
        return UAgentInfo.deviceMidp in self.__userAgent \
            or UAgentInfo.deviceMidp in self.__httpAccept

    def detectMaemoTablet(self):
        """Return detection of a Maemo OS tablet

        Detects if the current device is on one of the Maemo-based Nokia Internet Tablets.
        """
        if UAgentInfo.maemo in self.__userAgent:
            return True

        return UAgentInfo.linux in self.__userAgent \
            and UAgentInfo.deviceTablet in self.__userAgent \
            and not self.detectWebOSTablet() \
            and not self.detectAndroid()

    def detectArchos(self):
        """Return detection of an Archos media player

        Detects if the current device is an Archos media player/Internet tablet.
        """
        return UAgentInfo.deviceArchos in self.__userAgent

    def detectSonyMylo(self):
        """Return detection of a Sony Mylo device

        Detects if the current browser is a Sony Mylo device.
        """
        return UAgentInfo.manuSony in self.__userAgent \
            and (UAgentInfo.qtembedded in self.__userAgent 
                or UAgentInfo.mylocom2 in self.__userAgent)

    #*****************************
    # Device Classes
    #*****************************
    def detectSmartphone(self):
        """Return detection of a general smartphone device

        Checks to see whether the device is *any* 'smartphone'.
        Note: It's better to use DetectTierIphone() for modern touchscreen devices.
        """
        return self.detectTierIphone() \
            or self.detectS60OssBrowser() \
            or self.detectSymbianOS() \
            or self.detectWindowsMobile() \
            or self.detectBlackBerry() \
            or self.detectMeegoPhone() \
            or self.detectPalmWebOS()

    def detectMobileQuick(self):
        """Return detection of any mobile device using the quicker method

        Detects if the current device is a mobile device.
        This method catches most of the popular modern devices.
        Excludes Apple iPads and other modern tablets.
        """
        #Let's exclude tablets
        if self.__isTierTablet:
            return False

        #Most mobile browsing is done on smartphones
        if self.detectSmartphone():
            return True

        #Catch-all for many mobile devices
        if UAgentInfo.mobile in self.__userAgent:
            return True

        if self.detectOperaMobile():
            return True

        #We also look for Kindle devices
        if self.detectKindle() \
            or self.detectAmazonSilk():
            return True

        if self.detectWapWml() \
           or self.detectMidpCapable() \
           or self.detectBrewDevice():
            return True

        if UAgentInfo.engineNetfront in self.__userAgent \
           or UAgentInfo.engineUpBrowser in self.__userAgent:
            return True

        return False

    def detectMobileLong(self):
        """Return detection of any mobile device using the more thorough method

        The longer and more thorough way to detect for a mobile device.
        Will probably detect most feature phones,
        smartphone-class devices, Internet Tablets,
        Internet-enabled game consoles, etc.
        This ought to catch a lot of the more obscure and older devices, also --
        but no promises on thoroughness!
        """
        
        if self.detectMobileQuick() \
           or self.detectGameConsole():
            return True

        if self.detectDangerHiptop() \
           or self.detectMaemoTablet() \
           or self.detectSonyMylo() \
           or self.detectArchos():
            return True

        if UAgentInfo.devicePda in self.__userAgent \
           and UAgentInfo.disUpdate not in self.__userAgent:
            return True
        
        #detect older phones from certain manufacturers and operators.
        return UAgentInfo.uplink in self.__userAgent \
            or UAgentInfo.engineOpenWeb in self.__userAgent \
            or UAgentInfo.manuSamsung1 in self.__userAgent \
            or UAgentInfo.manuSonyEricsson in self.__userAgent \
            or UAgentInfo.manuericsson in self.__userAgent \
            or UAgentInfo.svcDocomo in self.__userAgent \
            or UAgentInfo.svcKddi in self.__userAgent \
            or UAgentInfo.svcVodafone in self.__userAgent


    #*****************************
    # For Mobile Web Site Design
    #*****************************
    def detectTierTablet(self):
        """Return detection of any device in the Tablet Tier

        The quick way to detect for a tier of devices.
        This method detects for the new generation of
        HTML 5 capable, larger screen tablets.
        Includes iPad, Android (e.g., Xoom), BB Playbook, WebOS, etc.
        """
        return self.detectIpad() \
            or self.detectAndroidTablet() \
            or self.detectBlackBerryTablet() \
            or self.detectFirefoxOSTablet() \
            or self.detectUbuntuTablet() \
            or self.detectWebOSTablet()

    def detectTierIphone(self):
        """Return detection of any device in the iPhone/Android/WP7/WebOS Tier

        The quick way to detect for a tier of devices.
        This method detects for devices which can
        display iPhone-optimized web content.
        Includes iPhone, iPod Touch, Android, Windows Phone 7, Palm WebOS, etc.
        """
        return self.__isIphone \
            or self.__isAndroidPhone \
            or self.detectWindowsPhone() \
            or self.detectBlackBerry10Phone() \
            or self.detectPalmWebOS() \
            or self.detectBada() \
            or self.detectTizen() \
            or self.detectFirefoxOSPhone() \
            or self.detectSailfishPhone() \
            or self.detectUbuntuPhone() \
            or self.detectGamingHandheld()

    def detectTierRichCss(self):
        """Return detection of any device in the 'Rich CSS' Tier

        The quick way to detect for a tier of devices.
        This method detects for devices which are likely to be capable
        of viewing CSS content optimized for the iPhone,
        but may not necessarily support JavaScript.
        Excludes all iPhone Tier devices.
        """
        #The following devices are explicitly ok.
        #Note: 'High' BlackBerry devices ONLY
        if not self.detectMobileQuick():
            return False
        #Exclude iPhone Tier and e-Ink Kindle devices
        if self.detectTierIphone() \
            or self.detectKindle():
            return False
        #The following devices are explicitly ok.
        #Note: 'High' BlackBerry devices ONLY
        #Older Windows 'Mobile' isn't good enough for iPhone Tier.
        return self.detectWebkit() \
            or self.detectS60OssBrowser() \
            or self.detectBlackBerryHigh() \
            or self.detectWindowsMobile() \
            or UAgentInfo.engineTelecaQ in self.__userAgent

    def detectTierOtherPhones(self):
        """Return detection of a mobile device in the less capable tier

        The quick way to detect for a tier of devices.
        This method detects for all other types of phones,
        but excludes the iPhone and RichCSS Tier devices.
        """
        #Exclude devices in the other 2 categories
        return self.detectMobileLong() \
            and not self.detectTierIphone() \
            and not self.detectTierRichCss()
