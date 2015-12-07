/* *******************************************
// Copyright 2010-2015, Anthony Hand
//
//
// File version 2015.05.13 (May 13, 2015)
// Updates:
//	- Moved MobileESP to GitHub. https://github.com/ahand/mobileesp
//	- Opera Mobile/Mini browser has the same UA string on multiple platforms and doesn't differentiate phone vs. tablet. 
//		- Removed DetectOperaAndroidPhone(). This method is no longer reliable. 
//		- Removed DetectOperaAndroidTablet(). This method is no longer reliable. 
//	- Added support for Windows Phone 10: variable and DetectWindowsPhone10()
//	- Updated DetectWindowsPhone() to include WP10. 
//	- Added support for Firefox OS.  
//		- A variable plus DetectFirefoxOS(), DetectFirefoxOSPhone(), DetectFirefoxOSTablet()
//		- NOTE: Firefox doesn't add UA tokens to definitively identify Firefox OS vs. their browsers on other mobile platforms.
//	- Added support for Sailfish OS. Not enough info to add a tablet detection method at this time. 
//		- A variable plus DetectSailfish(), DetectSailfishPhone()
//	- Added support for Ubuntu Mobile OS. 
//		- DetectUbuntu(), DetectUbuntuPhone(), DetectUbuntuTablet()
//	- Added support for 2 smart TV OSes. They lack browsers but do have WebViews for use by HTML apps. 
//		- One variable for Samsung Tizen TVs, plus DetectTizenTV()
//		- One variable for LG WebOS TVs, plus DetectWebOSTV()
//	- Added DetectTizen(). Tests for “mobile” to disambiguate from Samsung Smart TVs.
//	- Removed variables for obsolete devices: deviceHtcFlyer, deviceXoom.
//	- Updated DetectAndroid(). No longer has a special test case for the HTC Flyer tablet. 
//	- Updated DetectAndroidPhone(). 
//		- Updated internal detection code for Android. 
//		- No longer has a special test case for the HTC Flyer tablet. 
//		- Checks against DetectOperaMobile() on Android and reports here if relevant. 
//	- Updated DetectAndroidTablet(). 
//		- No longer has a special test case for the HTC Flyer tablet. 
//		- Checks against DetectOperaMobile() on Android to exclude it from here.
//	- DetectMeego(): Changed definition for this method. Now detects any Meego OS device, not just phones. 
//	- DetectMeegoPhone(): NEW. For Meego phones. Ought to detect Opera browsers on Meego, as well.  
//	- DetectTierIphone(): Added support for phones running Sailfish, Ubuntu and Firefox Mobile. 
//	- DetectTierTablet(): Added support for tablets running Ubuntu and Firefox Mobile. 
//	- DetectSmartphone(): Added support for Meego phones. 
//	- Caught this library up to the PHP, JavaScript and Java versions. Updates include: 
//		- Added support for Bada: a variable and DetectBada(). This detects any Bada OS device, but (almost) all are phones.
//		- Refactored the Windows Phone delegate-related properties and features. Now fires for any Windows Phone, not just WP7. 
//			- The event fires now when DetectWindowsPhone() is true. 
//		- Added support for Windows Phone 8: DetectWindowsPhone8().
//		- Updated DetectWindowsMobile(). Excludes any Windows Phone device, not just WP7. 
//		- Added support for BlackBerry 10 OS phones: DetectBlackBerry10Phone().
//		- Updated DetectSonyMylo().
//		- Updated DetectSmartphone() to sync with the other libraries. 
//		- Updated DetectTierIphone() to sync with the other libraries.
//		- OnInit(EventArgs e): Fixed the user agent and httpaccept init logic.
//	- Refactored the detection logic in DetectMobileQuick() and DetectMobileLong().
//		- Moved a few detection tests for older browsers to Long. 
//
//
//
// LICENSE INFORMATION
// Licensed under the Apache License, Version 2.0 (the "License"); 
// you may not use this file except in compliance with the License. 
// You may obtain a copy of the License at 
//        http://www.apache.org/licenses/LICENSE-2.0 
// Unless required by applicable law or agreed to in writing, 
// software distributed under the License is distributed on an 
// "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, 
// either express or implied. See the License for the specific 
// language governing permissions and limitations under the License. 
//
//
// ABOUT THIS PROJECT
//   Project Owner: Anthony Hand
//   Email: anthony.hand@gmail.com
//   Web Site: http://www.mobileesp.com
//   Source Files: https://github.com/ahand/mobileesp
//   
//   Versions of this code are available for:
//      PHP, JavaScript, Java, ASP.NET (C#), and Ruby
//
// *******************************************
*/


using System;
using System.Collections.Generic;
using System.Web;
using System.Web.UI;
using System.Web.UI.WebControls;

/// <summary>
/// Subclass this control to inherit the built-in mobile device detection.
/// </summary>
public class MDetectControl : System.Web.UI.UserControl
{

    private string useragent = "";
    private string httpaccept = "";

    #region Fields - Detection Argument Values

    //standardized values for detection arguments.
    private const string dargsIphone = "iphone";
    private const string dargsIpod = "ipod";
    private const string dargsIpad = "ipad";
    private const string dargsIphoneOrIpod = "iphoneoripod";
    private const string dargsIos = "ios";
    private const string dargsAndroid = "android";
    private const string dargsAndroidPhone = "androidphone";
    private const string dargsAndroidTablet = "androidtablet";
    private const string dargsGoogleTV = "googletv";
    private const string dargsWebKit = "webkit";
    private const string dargsSymbianOS = "symbianos";
    private const string dargsS60 = "series60";
    private const string dargsWindowsPhone = "windowsphone";
    private const string dargsWindowsMobile = "windowsmobile";
    private const string dargsBlackBerry = "blackberry";
    private const string dargsBlackBerryWebkit = "blackberrywebkit";
    private const string dargsPalmOS = "palmos";
    private const string dargsPalmWebOS = "webos";
    private const string dargsWebOSTablet = "webostablet";
    private const string dargsSmartphone = "smartphone";
    private const string dargsBrewDevice = "brew";
    private const string dargsDangerHiptop = "dangerhiptop";
    private const string dargsOperaMobile = "operamobile";
    private const string dargsWapWml = "wapwml";
    private const string dargsKindle = "kindle";
    private const string dargsMobileQuick = "mobilequick";
    private const string dargsTierTablet = "tiertablet";
    private const string dargsTierIphone = "tieriphone";
    private const string dargsTierRichCss = "tierrichcss";
    private const string dargsTierOtherPhones = "tierotherphones";

    #endregion Fields - Detection Argument Values

    #region Fields - User Agent Keyword Values

    private const string engineWebKit = "WEBKIT";
    private const string deviceIphone = "IPHONE";
    private const string deviceIpod = "IPOD";
    private const string deviceIpad = "IPAD";
    private const string deviceMacPpc = "MACINTOSH"; //Used for disambiguation

    private const string deviceAndroid = "ANDROID";
    private const string deviceGoogleTV = "GOOGLETV";

    private const string deviceNuvifone = "NUVIFONE";  //Garmin Nuvifone
    private const string deviceBada = "BADA";  //Samsung's Bada OS    
    private const string deviceTizen = "TIZEN";  //Tizen OS    
    private const string deviceMeego = "MEEGO";  //Meego OS    
    private const string deviceSailfish = "SAILFISH";  //Sailfish OS
    private const string deviceUbuntu = "UBUNTU";  //Ubuntu Mobile OS

    private const string deviceSymbian = "SYMBIAN";
    private const string deviceS60 = "SERIES60";
    private const string deviceS70 = "SERIES70";
    private const string deviceS80 = "SERIES80";
    private const string deviceS90 = "SERIES90";

    private const string deviceWinPhone7 = "WINDOWS PHONE OS 7";
    private const string deviceWinPhone8 = "WINDOWS PHONE 8";
    private const string deviceWinPhone10 = "WINDOWS PHONE 10";
    private const string deviceWinMob = "WINDOWS CE";
    private const string deviceWindows = "WINDOWS";
    private const string deviceIeMob = "IEMOBILE";
    private const string devicePpc = "PPC"; //Stands for PocketPC
    private const string enginePie = "WM5 PIE"; //An old Windows Mobile browser

    private const string deviceBB = "BLACKBERRY";
    private const string deviceBB10 = "BB10"; //For the new BB 10 OS
    private const string vndRIM = "VND.RIM"; //Detectable when BB devices emulate IE or Firefox
    private const string deviceBBStorm = "BLACKBERRY95"; //Storm 1 and 2
    private const string deviceBBBold = "BLACKBERRY97"; //Bold 97x0 (non-touch)
    private const string deviceBBBoldTouch = "BLACKBERRY 99"; //Bold 99x0 (touchscreen)
    private const string deviceBBTour = "BLACKBERRY96"; //Tour
    private const string deviceBBCurve = "BLACKBERRY89"; //Curve2
    private const string deviceBBCurveTouch = "BLACKBERRY 938"; //Curve Touch 9380
    private const string deviceBBTorch = "BLACKBERRY 98"; //Torch
    private const string deviceBBPlaybook = "PLAYBOOK"; //PlayBook tablet

    private const string devicePalm = "PALM";
    private const string deviceWebOS = "WEBOS"; //For Palm devices
    private const string deviceWebOStv = "WEB0S"; //For LG TVs
    private const string deviceWebOShp = "HPWOS"; //For HP's line of WebOS devices

    private const string engineBlazer = "BLAZER"; //Old Palm
    private const string engineXiino = "XIINO"; //Another old Palm

    private const string deviceKindle = "KINDLE";  //Amazon Kindle, eInk one
    private const string engineSilk = "SILK";  //Amazon's accelerated Silk browser for Kindle Fire

    //Initialize private strings for mobile-specific content.
    private const string vndwap = "VND.WAP";
    private const string wml = "WML";

    //Initialize private strings for other random devices and mobile browsers.
    private const string deviceTablet = "TABLET"; //Generic term for slate and tablet devices
    private const string deviceBrew = "BREW";
    private const string deviceDanger = "DANGER";
    private const string deviceHiptop = "HIPTOP";
    private const string devicePlaystation = "PLAYSTATION";
    private const string devicePlaystationVita = "VITA";
    private const string deviceNintendoDs = "NITRO";
    private const string deviceNintendo = "NINTENDO";
    private const string deviceWii = "WII";
    private const string deviceXbox = "XBOX";
    private const string deviceArchos = "ARCHOS";

    private const string engineFirefox = "FIREFOX"; //For Firefox OS
    private const string engineOpera = "OPERA"; //Popular browser
    private const string engineNetfront = "NETFRONT"; //Common embedded OS browser
    private const string engineUpBrowser = "UP.BROWSER"; //common on some phones
    private const string engineOpenWeb = "OPENWEB"; //Transcoding by OpenWave server
    private const string deviceMidp = "MIDP"; //a mobile Java technology
    private const string uplink = "UP.LINK";
    private const string engineTelecaQ = "TELECA Q"; //a modern feature phone browser

    private const string devicePda = "PDA"; //some devices report themselves as PDAs
    private const string mini = "MINI";  //Some mobile browsers put "mini" in their names.
    private const string mobile = "MOBILE"; //Some mobile browsers put "mobile" in their user agent private strings.
    private const string mobi = "MOBI"; //Some mobile browsers put "mobi" in their user agent private strings.

    //Smart TV strings
    private const string smartTV1 = "SMART-TV"; //Samsung Tizen smart TVs
    private const string smartTV2 = "SMARTTV"; //LG WebOS smart TVs

    //Use Maemo, Tablet, and Linux to test for Nokia"s Internet Tablets.
    private const string maemo = "MAEMO";
    private const string linux = "LINUX";
    private const string qtembedded = "QT EMBEDDED"; //for Sony Mylo
    private const string mylocom2 = "COM2"; //for Sony Mylo also

    //In some UserAgents, the only clue is the manufacturer.
    private const string manuSonyEricsson = "SONYERICSSON";
    private const string manuericsson = "ERICSSON";
    private const string manuSamsung1 = "SEC-SGH";
    private const string manuSony = "SONY";
    private const string manuHtc = "HTC"; //Popular Android and WinMo manufacturer

    //In some UserAgents, the only clue is the operator.
    private const string svcDocomo = "DOCOMO";
    private const string svcKddi = "KDDI";
    private const string svcVodafone = "VODAFONE";

    //Disambiguation strings.
    private const string disUpdate = "UPDATE"; //pda vs. update

    #endregion Fields - User Agent Keyword Values


    /// <summary>
    /// To instantiate a WebPage sub-class with built-in
    /// mobile device detection delegates and events.
    /// </summary>
    public MDetectControl()
    {

    }

    /// <summary>
    /// To run the device detection methods andd fire 
    /// any existing OnDetectXXX events. 
    /// </summary>
    public void FireEvents()
    {
        if (useragent == "" && httpaccept == "")
        {
            useragent = (Request.ServerVariables["HTTP_USER_AGENT"] ?? "").ToUpper();
            httpaccept = (Request.ServerVariables["HTTP_ACCEPT"] ?? "").ToUpper();
        }

        #region Event Fire Methods

        MDetectArgs mda = null;
        if (this.DetectIpod())
        {
            mda = new MDetectArgs(dargsIpod);
            if (this.OnDetectIpod != null)
            {
                this.OnDetectIpod(this, mda);
            }
        }
        if (this.DetectIpad())
        {
            mda = new MDetectArgs(dargsIpad);
            if (this.OnDetectIpad != null)
            {
                this.OnDetectIpad(this, mda);
            }
        }
        if (this.DetectIphone())
        {
            mda = new MDetectArgs(dargsIphone);
            if (this.OnDetectIphone != null)
            {
                this.OnDetectIphone(this, mda);
            }
        }
        if (this.DetectIphoneOrIpod())
        {
            mda = new MDetectArgs(dargsIphoneOrIpod);
            if (this.OnDetectDetectIPhoneOrIpod != null)
            {
                this.OnDetectDetectIPhoneOrIpod(this, mda);
            }
        }
        if (this.DetectIos())
        {
            mda = new MDetectArgs(dargsIos);
            if (this.OnDetectIos != null)
            {
                this.OnDetectIos(this, mda);
            }
        }
        if (this.DetectAndroid())
        {
            mda = new MDetectArgs(dargsAndroid);
            if (this.OnDetectAndroid != null)
            {
                this.OnDetectAndroid(this, mda);
            }
        }
        if (this.DetectAndroidPhone())
        {
            mda = new MDetectArgs(dargsAndroidPhone);
            if (this.OnDetectAndroidPhone != null)
            {
                this.OnDetectAndroidPhone(this, mda);
            }
        }
        if (this.DetectAndroidTablet())
        {
            mda = new MDetectArgs(dargsAndroidTablet);
            if (this.OnDetectAndroidTablet != null)
            {
                this.OnDetectAndroidTablet(this, mda);
            }
        }
        if (this.DetectGoogleTV())
        {
            mda = new MDetectArgs(dargsGoogleTV);
            if (this.OnDetectGoogleTV != null)
            {
                this.OnDetectGoogleTV(this, mda);
            }
        }
        if (this.DetectWebkit())
        {
            mda = new MDetectArgs(dargsWebKit);
            if (this.OnDetectWebkit != null)
            {
                this.OnDetectWebkit(this, mda);
            }
        }
        if (this.DetectS60OssBrowser())
        {
            mda = new MDetectArgs(dargsS60);
            if (this.OnDetectS60OssBrowser != null)
            {
                this.OnDetectS60OssBrowser(this, mda);
            }
        }
        if (this.DetectSymbianOS())
        {
            mda = new MDetectArgs(dargsSymbianOS);
            if (this.OnDetectSymbianOS != null)
            {
                this.OnDetectSymbianOS(this, mda);
            }
        }
        if (this.DetectWindowsPhone())
        {
            mda = new MDetectArgs(dargsWindowsPhone);
            if (this.OnDetectWindowsPhone != null)
            {
                this.OnDetectWindowsPhone(this, mda);
            }
        }
        if (this.DetectWindowsMobile())
        {
            mda = new MDetectArgs(dargsWindowsMobile);
            if (this.OnDetectWindowsMobile != null)
            {
                this.OnDetectWindowsMobile(this, mda);
            }
        }
        if (this.DetectBlackBerry())
        {
            mda = new MDetectArgs(dargsBlackBerry);
            if (this.OnDetectBlackBerry != null)
            {
                this.OnDetectBlackBerry(this, mda);
            }
        }
        if (this.DetectBlackBerryWebKit())
        {
            mda = new MDetectArgs(dargsBlackBerryWebkit);
            if (this.OnDetectBlackBerryWebkit != null)
            {
                this.OnDetectBlackBerryWebkit(this, mda);
            }
        }
        if (this.DetectPalmOS())
        {
            mda = new MDetectArgs(dargsPalmOS);
            if (this.OnDetectPalmOS != null)
            {
                this.OnDetectPalmOS(this, mda);
            }
        }
        if (this.DetectPalmWebOS())
        {
            mda = new MDetectArgs(dargsPalmWebOS);
            if (this.OnDetectPalmWebOS != null)
            {
                this.OnDetectPalmWebOS(this, mda);
            }
        }
        if (this.DetectWebOSTablet())
        {
            mda = new MDetectArgs(dargsWebOSTablet);
            if (this.OnDetectWebOSTablet != null)
            {
                this.OnDetectWebOSTablet(this, mda);
            }
        }
        if (this.DetectSmartphone())
        {
            mda = new MDetectArgs(dargsSmartphone);
            if (this.OnDetectSmartphone != null)
            {
                this.OnDetectSmartphone(this, mda);
            }
        }
        if (this.DetectBrewDevice())
        {
            mda = new MDetectArgs(dargsBrewDevice);
            if (this.OnDetectBrewDevice != null)
            {
                this.OnDetectBrewDevice(this, mda);
            }
        }
        if (this.DetectDangerHiptop())
        {
            mda = new MDetectArgs(dargsDangerHiptop);
            if (this.OnDetectDangerHiptop != null)
            {
                this.OnDetectDangerHiptop(this, mda);
            }
        }
        if (this.DetectOperaMobile())
        {
            mda = new MDetectArgs(dargsOperaMobile);
            if (this.OnDetectOperaMobile != null)
            {
                this.OnDetectOperaMobile(this, mda);
            }
        }
        if (this.DetectWapWml())
        {
            mda = new MDetectArgs(dargsWapWml);
            if (this.OnDetectWapWml != null)
            {
                this.OnDetectWapWml(this, mda);
            }
        }
        if (this.DetectKindle())
        {
            mda = new MDetectArgs(dargsKindle);
            if (this.OnDetectKindle != null)
            {
                this.OnDetectKindle(this, mda);
            }
        }
        if (this.DetectMobileQuick())
        {
            mda = new MDetectArgs(dargsMobileQuick);
            if (this.OnDetectMobileQuick != null)
            {
                this.OnDetectMobileQuick(this, mda);
            }
        }
        if (this.DetectTierTablet())
        {
            mda = new MDetectArgs(dargsTierTablet);
            if (this.OnDetectTierTablet != null)
            {
                this.OnDetectTierTablet(this, mda);
            }
        }
        if (this.DetectTierIphone())
        {
            mda = new MDetectArgs(dargsTierIphone);
            if (this.OnDetectTierIphone != null)
            {
                this.OnDetectTierIphone(this, mda);
            }
        }
        if (this.DetectTierRichCss())
        {
            mda = new MDetectArgs(dargsTierRichCss);
            if (this.OnDetectTierRichCss != null)
            {
                this.OnDetectTierRichCss(this, mda);
            }
        }
        if (this.DetectTierOtherPhones())
        {
            mda = new MDetectArgs(dargsTierOtherPhones);
            if (this.OnDetectTierOtherPhones != null)
            {
                this.OnDetectTierOtherPhones(this, mda);
            }
        }

        #endregion Event Fire Methods

    }

    public class MDetectArgs : EventArgs
    {
        public MDetectArgs(string type)
        {
            this.Type = type;
        }

        public readonly string Type;
    }

    #region Mobile Device Detection Methods 
    
    
	//*****************************
	// Start device detection
	//*****************************

    //**************************
    // Detects if the current device is an iPhone.
    public bool DetectIphone()
    {
        if (useragent.IndexOf(deviceIphone)!= -1)
        {
            //The iPad and iPod touch say they're an iPhone! So let's disambiguate.
            if (DetectIpad() || DetectIpod())
            {
                return false;
            }
            else
                return true;
        }
        else
            return false;
    }
    //IPhone delegate
    public delegate void DetectIphoneHandler(object page, MDetectArgs args);
    public event DetectIphoneHandler OnDetectIphone;


    //**************************
    // Detects if the current device is an iPod Touch.
    public bool DetectIpod()
    {
        if (useragent.IndexOf(deviceIpod)!= -1)
            return true;
        else
            return false;
    }

    //Ipod delegate
    public delegate void DetectIpodHandler(object page, MDetectArgs args);
    public event DetectIpodHandler OnDetectIpod;


    //**************************
    // Detects if the current device is an iPad tablet.
    public bool DetectIpad()
    {
        if (useragent.IndexOf(deviceIpad) != -1 && DetectWebkit())
            return true;
        else
            return false;
    }

    //Ipod delegate
    public delegate void DetectIpadHandler(object page, MDetectArgs args);
    public event DetectIpadHandler OnDetectIpad;


    //**************************
    // Detects if the current device is an iPhone or iPod Touch.
    public bool DetectIphoneOrIpod()
    {
        //We repeat the searches here because some iPods may report themselves as an iPhone, which would be okay.
        if (useragent.IndexOf(deviceIphone)!= -1 ||
            useragent.IndexOf(deviceIpod)!= -1)
            return true;
        else
            return false;
    }
    //IPhoneOrIpod delegate
    public delegate void DetectIPhoneOrIpodHandler(object page, MDetectArgs args);
    public event DetectIPhoneOrIpodHandler OnDetectDetectIPhoneOrIpod;

    //**************************
    // Detects *any* iOS device: iPhone, iPod Touch, iPad.
    public bool DetectIos()
    {
        if (DetectIphoneOrIpod() || DetectIpad())
            return true;
        else
            return false;
    }

    //Ios delegate
    public delegate void DetectIosHandler(object page, MDetectArgs args);
    public event DetectIosHandler OnDetectIos;


    //**************************
    // Detects *any* Android OS-based device: phone, tablet, and multi-media player.
    // Also detects Google TV.
    public bool DetectAndroid()
    {
        if ((useragent.IndexOf(deviceAndroid) != -1) ||
            DetectGoogleTV())
            return true;
        
        return false;
    }
    //Android delegate
    public delegate void DetectAndroidHandler(object page, MDetectArgs args);
    public event DetectAndroidHandler OnDetectAndroid;

    //**************************
    // Detects if the current device is a (small-ish) Android OS-based device
    // used for calling and/or multi-media (like a Samsung Galaxy Player).
    // Google says these devices will have 'Android' AND 'mobile' in user agent.
    // Ignores tablets (Honeycomb and later).
    public bool DetectAndroidPhone()
    {
        //First, let's make sure we're on an Android device.
        if (!DetectAndroid())
            return false;

        //If it's Android and has 'mobile' in it, Google says it's a phone.
        if (useragent.IndexOf(mobile) != -1)
            return true;

        //Special check for Android devices with Opera Mobile/Mini. They should report here.
        if (DetectOperaMobile())
            return true;
        
        return false;
    }

    //Android Phone delegate
    public delegate void DetectAndroidPhoneHandler(object page, MDetectArgs args);
    public event DetectAndroidPhoneHandler OnDetectAndroidPhone;

    //**************************
    // Detects if the current device is a (self-reported) Android tablet.
    // Google says these devices will have 'Android' and NOT 'mobile' in their user agent.
    public bool DetectAndroidTablet()
    {
        //First, let's make sure we're on an Android device.
        if (!DetectAndroid())
            return false;

        //Special check for Android devices with Opera Mobile/Mini. They should NOT report here.
        if (DetectOperaMobile())
            return false;

        //Otherwise, if it's Android and does NOT have 'mobile' in it, Google says it's a tablet.
        if (useragent.IndexOf(mobile) > -1)
            return false;
        else
            return true;
    }
    
    //Android Tablet delegate
    public delegate void DetectAndroidTabletHandler(object page, MDetectArgs args);
    public event DetectAndroidTabletHandler OnDetectAndroidTablet;

    //**************************
    // Detects if the current device is a GoogleTV device.
    public bool DetectGoogleTV()
    {
        if (useragent.IndexOf(deviceGoogleTV) != -1)
            return true;
        else
            return false;
    }
    
    //GoogleTV delegate
    public delegate void DetectGoogleTVHandler(object page, MDetectArgs args);
    public event DetectGoogleTVHandler OnDetectGoogleTV;

    //**************************
    // Detects if the current device is an Android OS-based device and
    //   the browser is based on WebKit.
    public bool DetectAndroidWebKit()
    {
        if (DetectAndroid() && DetectWebkit())
            return true;
        else
            return false;
    }

    //**************************
    // Detects if the current browser is based on WebKit.
    public bool DetectWebkit()
    {
        if (useragent.IndexOf(engineWebKit)!= -1)
            return true;
        else
            return false;
    }

    //Webkit delegate
    public delegate void DetectWebkitHandler(object page, MDetectArgs args);
    public event DetectWebkitHandler OnDetectWebkit;

    //**************************
    // Detects if the current browser is the Nokia S60 Open Source Browser.
    public bool DetectS60OssBrowser()
    {
        //First, test for WebKit, then make sure it's either Symbian or S60.
        if (DetectWebkit())
        {
            if (useragent.IndexOf(deviceSymbian)!= -1 ||
                useragent.IndexOf(deviceS60)!= -1)
            {
                return true;
            }
            else
                return false;
        }
        else
            return false;
    }

    //S60OssBrowser delegate
    public delegate void DetectS60OssBrowserHandler(object page, MDetectArgs args);
    public event DetectS60OssBrowserHandler OnDetectS60OssBrowser;

    //**************************
    // Detects if the current device is any Symbian OS-based device,
    //   including older S60, Series 70, Series 80, Series 90, and UIQ, 
    //   or other browsers running on these devices.
    public bool DetectSymbianOS()
    {
        if (useragent.IndexOf(deviceSymbian)!= -1 ||
            useragent.IndexOf(deviceS60)!= -1 ||
            useragent.IndexOf(deviceS70)!= -1 ||
            useragent.IndexOf(deviceS80)!= -1 ||
            useragent.IndexOf(deviceS90)!= -1)
            return true;
        else
            return false;
    }

    //SymbianOS delegate
    public delegate void DetectSymbianOSHandler(object page, MDetectArgs args);
    public event DetectSymbianOSHandler OnDetectSymbianOS;
    

    //**************************
    // Detects if the current browser is a 
    // Windows Phone 7, 8, or 10 device.
    public bool DetectWindowsPhone()
    {
        if (DetectWindowsPhone7() || 
        	DetectWindowsPhone8() ||
        	DetectWindowsPhone10())
            return true;
        
        return false;
    }

    //WindowsPhone delegate
    public delegate void DetectWindowsPhoneHandler(object page, MDetectArgs args);
    public event DetectWindowsPhoneHandler OnDetectWindowsPhone;

    //**************************
    // Detects if the current browser is a 
    // Windows Phone 7 device.
    public bool DetectWindowsPhone7()
    {
        if (useragent.IndexOf(deviceWinPhone7) != -1)
            return true;
        else
            return false;
    }

    //**************************
    // Detects if the current browser is a 
    // Windows Phone 8 device.
    public bool DetectWindowsPhone8()
    {
        if (useragent.IndexOf(deviceWinPhone8) != -1)
            return true;
        else
            return false;
    }

    //**************************
    // Detects if the current browser is a 
    // Windows Phone 10 device.
    public bool DetectWindowsPhone10()
    {
        if (useragent.IndexOf(deviceWinPhone10) != -1)
            return true;
        else
            return false;
    }

    //**************************
    // Detects if the current browser is a Windows Mobile device.
    // Excludes Windows Phone devices. 
    // Focuses on Windows Mobile 6.xx and earlier.
    public bool DetectWindowsMobile()
    {
        //Exclude new Windows Phone.
        if (DetectWindowsPhone())
            return false;
        //Most devices use 'Windows CE', but some report 'iemobile' 
        //  and some older ones report as 'PIE' for Pocket IE. 
        if (useragent.IndexOf(deviceWinMob)!= -1 ||
            useragent.IndexOf(deviceIeMob)!= -1 ||
            useragent.IndexOf(enginePie) != -1)
            return true;
        //Test for Windows Mobile PPC but not old Macintosh PowerPC.
        if (useragent.IndexOf(devicePpc) != -1 &&
            !(useragent.IndexOf(deviceMacPpc) != -1))
            return true;
        //Test for certain Windwos Mobile-based HTC devices.
        if (useragent.IndexOf(manuHtc) != -1 &&
            useragent.IndexOf(deviceWindows) != -1)
            return true;
        if (DetectWapWml() == true &&
            useragent.IndexOf(deviceWindows)!= -1)
            return true;
        else
            return false;
    }

    //WindowsMobile delegate
    public delegate void DetectWindowsMobileHandler(object page, MDetectArgs args);
    public event DetectWindowsMobileHandler OnDetectWindowsMobile;

    //**************************
    // Detects if the current browser is any BlackBerry device.
    // Includes the PlayBook.
    public bool DetectBlackBerry()
    {
        if (useragent.IndexOf(deviceBB)!= -1)
            return true;
        if (httpaccept.IndexOf(vndRIM)!= -1)
            return true;
        else
            return false;
    }
    //BlackBerry delegate
    public delegate void DetectBlackBerryHandler(object page, MDetectArgs args);
    public event DetectBlackBerryHandler OnDetectBlackBerry;

    //**************************
    // Detects if the current browser is a BlackBerry 10 OS phone.
   	// Excludes tablets.
    public bool DetectBlackBerry10Phone()
    {
        if (useragent.IndexOf(deviceBB10) != -1 &&
            useragent.IndexOf(mobile) != -1)
            return true;
        else
            return false;
    }

    //**************************
    // Detects if the current browser is on a BlackBerry tablet device.
    //    Example: PlayBook
    public bool DetectBlackBerryTablet()
    {
        if (useragent.IndexOf(deviceBBPlaybook) != -1)
            return true;
        else
            return false;
    }


    //**************************
    // Detects if the current browser is a BlackBerry device AND uses a
    //    WebKit-based browser. These are signatures for the new BlackBerry OS 6.
    //    Examples: Torch. Includes the Playbook.
    public bool DetectBlackBerryWebKit()
    {
        if (DetectBlackBerry() && DetectWebkit())
            return true;
        else
            return false;
    }
    //BlackBerry Webkit delegate
    public delegate void DetectBlackBerryWebkitHandler(object page, MDetectArgs args);
    public event DetectBlackBerryWebkitHandler OnDetectBlackBerryWebkit;


    //**************************
    // Detects if the current browser is a BlackBerry Touch
    //    device, such as the Storm, Torch, and Bold Touch. Excludes the Playbook.
    public bool DetectBlackBerryTouch()
    {
        if (DetectBlackBerry() && 
            (useragent.IndexOf(deviceBBStorm) != -1 ||
            useragent.IndexOf(deviceBBTorch) != -1 ||
            useragent.IndexOf(deviceBBBoldTouch) != -1 ||
            useragent.IndexOf(deviceBBCurveTouch) != -1))
            return true;
        else
            return false;
    }

    //**************************
    // Detects if the current browser is a BlackBerry device AND
    //    has a more capable recent browser. Excludes the Playbook.
    //    Excludes the new BlackBerry OS 6 and 7 browser!!
    public bool DetectBlackBerryHigh()
    {
        //Disambiguate for BlackBerry OS 6 or 7 (WebKit) browser
        if (DetectBlackBerryWebKit())
            return false;
        if (DetectBlackBerry())
        {
            if (DetectBlackBerryTouch() ||
                useragent.IndexOf(deviceBBBold) != -1 ||
                useragent.IndexOf(deviceBBTour) != -1 ||
                useragent.IndexOf(deviceBBCurve) != -1)
                return true;
            else
                return false;
        }
        else
            return false;
    }

    //**************************
    // Detects if the current browser is a BlackBerry device AND
    //    has an older, less capable browser. 
    //    Examples: Pearl, 8800, Curve1.
    public bool DetectBlackBerryLow()
    {
        if (DetectBlackBerry())
        {
            //Assume that if it's not in the High tier, then it's Low.
            if (DetectBlackBerryHigh() || DetectBlackBerryWebKit())
                return false;
            else
                return true;
        }
        else
            return false;
    }


    //**************************
    // Detects if the current browser is on a PalmOS device.
    public bool DetectPalmOS()
    {
        //Most devices nowadays report as 'Palm', but some older ones reported as Blazer or Xiino.
        if (useragent.IndexOf(devicePalm) != -1 ||
            useragent.IndexOf(engineBlazer) != -1 ||
            useragent.IndexOf(engineXiino) != -1)
        {
            //Make sure it's not WebOS first
            if (DetectPalmWebOS() == true)
                return false;
            else
                return true;
        }
        else
            return false;
    }
    //PalmOS delegate
    public delegate void DetectPalmOSHandler(object page, MDetectArgs args);
    public event DetectPalmOSHandler OnDetectPalmOS;


    //**************************
    // Detects if the current browser is on a Palm device
    //    running the new WebOS.
    public bool DetectPalmWebOS()
    {
        if (useragent.IndexOf(deviceWebOS) != -1)
            return true;
        else
            return false;
    }

    //PalmWebOS delegate
    public delegate void DetectPalmWebOSHandler(object page, MDetectArgs args);
    public event DetectPalmWebOSHandler OnDetectPalmWebOS;


    //**************************
    // Detects if the current browser is on an HP tablet running WebOS.
    public bool DetectWebOSTablet()
    {
        if (useragent.IndexOf(deviceWebOShp) != -1 &&
            useragent.IndexOf(deviceTablet) != -1)
        {
            return true;
        }
        else
            return false;
    }
    //WebOS tablet delegate
    public delegate void DetectWebOSTabletHandler(object page, MDetectArgs args);
    public event DetectWebOSTabletHandler OnDetectWebOSTablet;


    //**************************
    // Detects if the current browser is on a WebOS smart TV.
    public bool DetectWebOSTV()
    {
        if (useragent.IndexOf(deviceWebOStv) != -1 &&
            useragent.IndexOf(smartTV2) != -1)
        {
            return true;
        }
        else
            return false;
    }

    //**************************
    // Detects if the current browser is Opera Mobile or Mini.
    public bool DetectOperaMobile()
    {
        if (useragent.IndexOf(engineOpera)!= -1)
        {
            if ((useragent.IndexOf(mini)!= -1) ||
             (useragent.IndexOf(mobi)!= -1))
            {
                return true;
            }
            else
                return false;
        }
        else
            return false;
    }

    //Opera Mobile delegate
    public delegate void DetectOperaMobileHandler(object page, MDetectArgs args);
    public event DetectOperaMobileHandler OnDetectOperaMobile;


    //**************************
    // Detects if the current browser is a
    //    Garmin Nuvifone.
    public bool DetectGarminNuvifone()
    {
        if (useragent.IndexOf(deviceNuvifone) != -1)
            return true;
        else
            return false;
    }

    //**************************
    // Detects a device running the Bada OS from Samsung.
    public bool DetectBada()
    {
        if (useragent.IndexOf(deviceBada) != -1)
        {
            return true;
        }
        else
            return false;
    }

    //**************************
    // Detects a device running the Tizen smartphone OS.
    public bool DetectTizen()
    {
        if (useragent.IndexOf(deviceTizen) != -1 &&
            useragent.IndexOf(mobile) != -1)
        {
            return true;
        }
        else
            return false;
    }

    //**************************
    // Detects if the current browser is on a Tizen smart TV.
    public bool DetectTizenTV()
    {
        if (useragent.IndexOf(deviceTizen) != -1 &&
            useragent.IndexOf(smartTV1) != -1)
        {
            return true;
        }
        else
            return false;
    }

    //**************************
    // Detects a device running the Meego OS.
    public bool DetectMeego()
    {
        if (useragent.IndexOf(deviceMeego) != -1)
        {
            return true;
        }
        else
            return false;
    }

    //**************************
    // Detects a phone running the Meego OS.
    public bool DetectMeegoPhone()
    {
        if (useragent.IndexOf(deviceMeego) != -1 &&
            useragent.IndexOf(mobile) != -1)
        {
            return true;
        }
        else
            return false;
    }

    //**************************
    // Detects a mobile device (probably) running the Firefox OS.
    public bool DetectFirefoxOS()
    {
        if (DetectFirefoxOSPhone() || DetectFirefoxOSTablet())
            return true;
        
        return false;
    }

    //**************************
    // Detects a phone (probably) running the Firefox OS.
    public bool DetectFirefoxOSPhone()
    {
        //First, let's make sure we're NOT on another major mobile OS.
        if (DetectIos() || 
        	DetectAndroid() ||
        	DetectSailfish())
            return false;        
        
        if ((useragent.IndexOf(engineFirefox) != -1) &&
            (useragent.IndexOf(mobile) != -1))
            return true;
        
        return false;
    }

    //**************************
    // Detects a tablet (probably) running the Firefox OS.
    public bool DetectFirefoxOSTablet()
    {
        //First, let's make sure we're NOT on another major mobile OS.
        if (DetectIos() || 
        	DetectAndroid() ||
        	DetectSailfish())
            return false;        
        
        if ((useragent.IndexOf(engineFirefox) != -1) &&
            (useragent.IndexOf(deviceTablet) != -1))
            return true;
        
        return false;
    }

    //**************************
    // Detects a device running the Sailfish OS.
    public bool DetectSailfish()
    {
        if (useragent.IndexOf(deviceSailfish) != -1)
            return true;
        else
            return false;
    }
    
    //**************************
    // Detects a phone running the Sailfish OS.
    public bool DetectSailfishPhone()
    {
        if (DetectSailfish() &&
            (useragent.IndexOf(mobile) != -1))
            return true;
        
        return false;
    }

    //**************************
    // Detects a mobile device running the Ubuntu Mobile OS.
    public bool DetectUbuntu()
    {
        if (DetectUbuntuPhone() || DetectUbuntuTablet())
            return true;
        
        return false;
    }

    //**************************
    // Detects a phone running the Ubuntu Mobile OS.
    public bool DetectUbuntuPhone()
    {
        if ((useragent.IndexOf(deviceUbuntu) != -1) &&
            (useragent.IndexOf(mobile) != -1))
            return true;
        
        return false;
    }

    //**************************
    // Detects a tablet running the Ubuntu Mobile OS.
    public bool DetectUbuntuTablet()
    {
        if ((useragent.IndexOf(deviceUbuntu) != -1) &&
            (useragent.IndexOf(deviceTablet) != -1))
            return true;
        
        return false;
    }

    //**************************
    // Detects whether the device is a Brew-powered device.
    public bool DetectBrewDevice()
    {
        if (useragent.IndexOf(deviceBrew)!= -1)
            return true;
        else
            return false;
    }

    //BrewDevice delegate
    public delegate void DetectBrewDeviceHandler(object page, MDetectArgs args);
    public event DetectBrewDeviceHandler OnDetectBrewDevice;

    //**************************
    // Detects the Danger Hiptop device.
    public bool DetectDangerHiptop()
    {
        if (useragent.IndexOf(deviceDanger)!= -1 ||
            useragent.IndexOf(deviceHiptop)!= -1)
            return true;
        else
            return false;
    }
    //DangerHiptop delegate
    public delegate void DetectDangerHiptopHandler(object page, MDetectArgs args);
    public event DetectDangerHiptopHandler OnDetectDangerHiptop;

    //**************************
    // Detects whether the device supports WAP or WML.
    public bool DetectWapWml()
    {
        if (httpaccept.IndexOf(vndwap)!= -1 ||
            httpaccept.IndexOf(wml)!= -1)
            return true;
        else
            return false;
    }
    //WapWml delegate
    public delegate void DetectWapWmlHandler(object page, MDetectArgs args);
    public event DetectWapWmlHandler OnDetectWapWml;


    //**************************
    // Detects if the current device is an Amazon Kindle (eInk devices only).
    // Note: For the Kindle Fire, use the normal Android methods.
    public bool DetectKindle()
    {
        if (useragent.IndexOf(deviceKindle) != -1 &&
            !DetectAndroid())
            return true;
        else
            return false;
    }

    //Kindle delegate
    public delegate void DetectKindleHandler(object page, MDetectArgs args);
    public event DetectKindleHandler OnDetectKindle;


    //**************************
    // Detects if the current Amazon device is using the Silk Browser.
    // Note: Typically used by the the Kindle Fire.
    public bool DetectAmazonSilk()
    {
        if (useragent.IndexOf(engineSilk) != -1)
            return true;
        else
            return false;
    }


    //**************************
    // Detects if the current device is a Sony Playstation.
    public bool DetectSonyPlaystation()
    {
        if (useragent.IndexOf(devicePlaystation)!= -1)
            return true;
        else
            return false;
    }

    //**************************
    // Detects if the current device is a handheld gaming device with
    // a touchscreen and modern iPhone-class browser. Includes the Playstation Vita.
    public bool DetectGamingHandheld()
    {
        if (useragent.IndexOf(devicePlaystation)!= -1 &&
             useragent.IndexOf(devicePlaystationVita)!= -1)
            return true;
        else
            return false;
    }

    //**************************
    // Detects if the current device is a Nintendo game device.
    public bool DetectNintendo()
    {
        if (useragent.IndexOf(deviceNintendo)!= -1 ||
             useragent.IndexOf(deviceWii)!= -1 ||
             useragent.IndexOf(deviceNintendoDs)!= -1)
            return true;
        else
            return false;
    }

    //**************************
    // Detects if the current device is a Microsoft Xbox.
    public bool DetectXbox()
    {
        if (useragent.IndexOf(deviceXbox)!= -1)
            return true;
        else
            return false;
    }

    //**************************
    // Detects if the current device is an Internet-capable game console.
    public bool DetectGameConsole()
    {
        if (DetectSonyPlaystation())
            return true;
        else if (DetectNintendo())
            return true;
        else if (DetectXbox())
            return true;
        else
            return false;
    }

    //**************************
    // Detects if the current device supports MIDP, a mobile Java technology.
    public bool DetectMidpCapable()
    {
        if (useragent.IndexOf(deviceMidp)!= -1 ||
            httpaccept.IndexOf(deviceMidp)!= -1)
            return true;
        else
            return false;
    }

    //**************************
    // Detects if the current device is on one of the Maemo-based Nokia Internet Tablets.
    public bool DetectMaemoTablet()
    {
        if (useragent.IndexOf(maemo) != -1)
            return true;
        //For Nokia N810, must be Linux + Tablet, or else it could be something else. 
        else if (useragent.IndexOf(linux) != -1 &&
            useragent.IndexOf(deviceTablet) != -1 &&
            !DetectWebOSTablet() &&
            !DetectAndroid())
            return true;
        else
            return false;
    }

    //**************************
    // Detects if the current device is an Archos media player/Internet tablet.
    public bool DetectArchos()
    {
        if (useragent.IndexOf(deviceArchos)!= -1)
            return true;
        else
            return false;
    }

    //**************************
    // Detects if the current browser is a Sony Mylo device.
    public bool DetectSonyMylo()
    {
        if ((useragent.IndexOf(manuSony)!= -1) &&
        	((useragent.IndexOf(qtembedded)!= -1) ||
             (useragent.IndexOf(mylocom2)!= -1)))
			return true;        
        
        return false;
    }



	//*****************************
	// Device Classes
	//*****************************
   
    //**************************
    // Check to see whether the device is any device
    //   in the 'smartphone' category.
    // Note: It's better to use DetectTierIphone() for modern touchscreen devices. 
    public bool DetectSmartphone()
    {
        if (DetectTierIphone() ||
            DetectS60OssBrowser() ||
            DetectSymbianOS() ||
            DetectWindowsMobile() ||
            DetectBlackBerry() ||
            DetectMeegoPhone() ||
            DetectPalmOS())
            return true;
        else
            return false;
    }

    //DetectSmartphone delegate
    public delegate void DetectSmartphoneHandler(object page, MDetectArgs args);
    public event DetectSmartphoneHandler OnDetectSmartphone;

    //**************************
    //   Detects if the current device is a mobile device.
    //   This method catches most of the popular modern devices.
    //   Excludes Apple iPads and other modern tablets.
    public bool DetectMobileQuick()
    {
        //Let's exclude tablets
        if (DetectTierTablet())
            return false;

        //Most mobile browsing is done on smartphones
        if (DetectSmartphone())
            return true;

        //Catch-all for many mobile devices
        if (useragent.IndexOf(mobile) != -1)
            return true;

        if (DetectOperaMobile())
            return true;
        
        //We also look for Kindle devices
        if (DetectKindle() ||
            DetectAmazonSilk())
            return true;
        
        if (DetectWapWml() ||
            DetectMidpCapable() ||
            DetectBrewDevice())
            return true;

        if ((useragent.IndexOf(engineNetfront) != -1) ||
            (useragent.IndexOf(engineUpBrowser) != -1))
            return true;

        return false;
    }

    //DetectMobileQuick delegate
    public delegate void DetectMobileQuickHandler(object page, MDetectArgs args);
    public event DetectMobileQuickHandler OnDetectMobileQuick;


    //**************************
    // The longer and more thorough way to detect for a mobile device.
    //   Will probably detect most feature phones,
    //   smartphone-class devices, Internet Tablets, 
    //   Internet-enabled game consoles, etc.
    //   This ought to catch a lot of the more obscure and older devices, also --
    //   but no promises on thoroughness!
    public bool DetectMobileLong()
    {
        if (DetectMobileQuick())
            return true;
            
        if (DetectGameConsole())
            return true;

        if (DetectDangerHiptop() ||
            DetectMaemoTablet() ||
            DetectSonyMylo() ||
            DetectArchos())
            return true;

        if ((useragent.IndexOf(devicePda) != -1) &&
            (useragent.IndexOf(disUpdate) != -1))
            return true;

        //Detect older phones from certain manufacturers and operators. 
        if ((useragent.IndexOf(uplink) != -1)  ||
            (useragent.IndexOf(engineOpenWeb) != -1) ||
            (useragent.IndexOf(manuSamsung1) != -1) ||
            (useragent.IndexOf(manuSonyEricsson) != -1) ||
            (useragent.IndexOf(manuericsson) != -1) ||
            (useragent.IndexOf(svcDocomo) != -1) ||
            (useragent.IndexOf(svcKddi) != -1) ||
            (useragent.IndexOf(svcVodafone) != -1))
            return true;

        return false;
    }



    //*****************************
    // For Mobile Web Site Design
    //*****************************


    //**************************
    // The quick way to detect for a tier of devices.
    //   This method detects for the new generation of
    //   HTML 5 capable, larger screen tablets.
    //   Includes iPad, Android (e.g., Xoom), BB Playbook, WebOS, etc.
    public bool DetectTierTablet()
    {
        if (DetectIpad()
            || DetectAndroidTablet()
            || DetectBlackBerryTablet()
            || DetectFirefoxOSTablet()
            || DetectUbuntuTablet()
            || DetectWebOSTablet())
            return true;
        else
            return false;
    }

    //DetectTierTablet delegate
    public delegate void DetectTierTabletHandler(object page, MDetectArgs args);
    public event DetectTierTabletHandler OnDetectTierTablet;


    //**************************
    // The quick way to detect for a tier of devices.
    //   This method detects for devices which can 
    //   display iPhone-optimized web content.
    //   Includes iPhone, iPod Touch, Android, Windows Phone, BB10, Playstation Vita, etc.
    public bool DetectTierIphone()
    {
        if (DetectIphoneOrIpod() ||
            DetectAndroidPhone() ||
            DetectWindowsPhone() ||
            DetectBlackBerry10Phone() ||
            DetectPalmWebOS() ||
            DetectBada() ||
            DetectTizen() ||
            DetectFirefoxOSPhone() ||
            DetectSailfishPhone() ||
            DetectUbuntuPhone() ||
			DetectGamingHandheld())
            return true;
        else
            return false;
    }
    
    //DetectTierIphone delegate
    public delegate void DetectTierIphoneHandler(object page, MDetectArgs args);
    public event DetectTierIphoneHandler OnDetectTierIphone;


    //**************************
    // The quick way to detect for a tier of devices.
    //   This method detects for devices which are likely to be capable 
    //   of viewing CSS content optimized for the iPhone, 
    //   but may not necessarily support JavaScript.
    //   Excludes all iPhone Tier devices.
    public bool DetectTierRichCss()
    {
        if (DetectMobileQuick())
        {
            //Exclude iPhone Tier and e-Ink Kindle devices
            if (DetectTierIphone() || DetectKindle())
                return false;

            if (DetectWebkit() ||
                DetectS60OssBrowser())
                return true;

            //Note: 'High' BlackBerry devices ONLY
            if (DetectBlackBerryHigh() == true)
                return true;

            //Older Windows 'Mobile' isn't good enough for iPhone Tier.
            if (DetectWindowsMobile() == true)
                return true;
            if (useragent.IndexOf(engineTelecaQ) != -1)
                return true;

            else
                return false;
        }
        else
            return false;
    }

    //DetectTierRichCss delegate
    public delegate void DetectTierRichCssHandler(object page, MDetectArgs args);
    public event DetectTierRichCssHandler OnDetectTierRichCss;


    //**************************
    // The quick way to detect for a tier of devices.
    //   This method detects for all other types of phones,
    //   but excludes the iPhone and Smartphone Tier devices.
    public bool DetectTierOtherPhones()
    {
        if (DetectMobileLong() == true)
        {
            //Exclude devices in the other 2 categories
            if (DetectTierIphone() ||
                DetectTierRichCss())
                return false;
            else
                return true;
        }
        else
            return false;
    }

    //DetectTierOtherPhones delegate
    public delegate void DetectTierOtherPhonesHandler(object page, MDetectArgs args);
    public event DetectTierOtherPhonesHandler OnDetectTierOtherPhones;

    //***************************************************************
    #endregion

    //Instead of overriding OnInit(), you can override InitializeCulture()
    //  so that detection can happen earlier in page's lifecycle, if necessary. 
    protected override void OnInit(EventArgs e)
    {
        base.OnInit(e);
        
        if (useragent == "" && httpaccept == "")
        {
            useragent = (Request.ServerVariables["HTTP_USER_AGENT"] ?? "").ToUpper();
            httpaccept = (Request.ServerVariables["HTTP_ACCEPT"] ?? "").ToUpper();
        }

    }
}
