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
//	- Updated DetectTizen(). Now tests for “mobile” to disambiguate from Samsung Smart TVs
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
//	- Removed the variable for Obigo, an embedded browser. The browser is on old devices. 
//		- Couldn’t find info on current Obigo embedded browser user agent strings.
//	- Refactored the detection logic in DetectMobileQuick() and DetectMobileLong().
//		- Moved a few detection tests for older browsers to Long. 
//
//
//
// File version 2014.01.24 (January 24, 2014)
//	Updates:
//	- Ported to C++ by Kiran T. Based on the PHP library.
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
//      PHP, JavaScript, Java, ASP.NET (C#), C++, and Ruby
//
// *******************************************
*/
#ifndef MDETECT_H
#define MDETECT_H

#include <string>
#include <algorithm>

using namespace std;

namespace MobileESP
{
   //Initialize some initial smartphone string variables.
   const string engineWebKit = "webkit";

   const string deviceIphone = "iphone";
   const string deviceIpod = "ipod";
   const string deviceIpad = "ipad";
   const string deviceMacPpc = "macintosh"; //Used for disambiguation

   const string deviceAndroid = "android";
   const string deviceGoogleTV = "googletv";
   const string deviceHtcFlyer = "htc_flyer"; //HTC Flyer
   
   const string deviceWinPhone7 = "windows phone os 7"; 
   const string deviceWinPhone8 = "windows phone 8"; 
   const string deviceWinPhone10 = "windows phone 10"; 
   const string deviceWinMob = "windows ce";
   const string deviceWindows = "windows"; 
   const string deviceIeMob = "iemobile";
   const string devicePpc = "ppc"; //Stands for PocketPC
   const string enginePie = "wm5 pie"; //An old Windows Mobile
   
   const string deviceBB = "blackberry";   
   const string deviceBB10 = "bb10"; //For the new BB 10 OS
   const string vndRIM = "vnd.rim"; //Detectable when BB devices emulate IE or Firefox
   const string deviceBBStorm = "blackberry95";  //Storm 1 and 2
   const string deviceBBBold = "blackberry97"; //Bold 97x0 (non-touch)
   const string deviceBBBoldTouch = "blackberry 99"; //Bold 99x0 (touchscreen)
   const string deviceBBTour = "blackberry96"; //Tour
   const string deviceBBCurve = "blackberry89"; //Curve2
   const string deviceBBCurveTouch = "blackberry 938"; //Curve Touch
   const string deviceBBTorch = "blackberry 98"; //Torch
   const string deviceBBPlaybook = "playbook"; //PlayBook tablet
   
   const string deviceSymbian = "symbian";
   const string deviceS60 = "series60";
   const string deviceS70 = "series70";
   const string deviceS80 = "series80";
   const string deviceS90 = "series90";
   
   const string devicePalm = "palm";
   const string deviceWebOS = "webos"; //For Palm devices
   const string deviceWebOStv = "web0s"; //For LG TVs
   const string deviceWebOShp = "hpwos"; //For HP's line of WebOS devices
   
   const string engineBlazer = "blazer"; //Old Palm browser
   const string engineXiino = "xiino"; //Another old Palm
   
   const string deviceNuvifone = "nuvifone"; //Garmin Nuvifone
   const string deviceBada = "bada"; //Samsung's Bada OS
   const string deviceTizen = "tizen"; //Tizen OS
   const string deviceMeego = "meego"; //Meego OS
   const string deviceSailfish = "sailfish"; //Sailfish OS
   const string deviceUbuntu = "ubuntu"; //Ubuntu Mobile OS

   const string deviceKindle = "kindle"; //Amazon Kindle, eInk one
   const string engineSilk = "silk-accelerated"; //Amazon's accelerated Silk browser for Kindle Fire
   
   //Initialize variables for mobile-specific content.
   const string vndwap = "vnd.wap";
   const string wml = "wml";   
   
   //Initialize variables for other random devices and mobile browsers.
   const string deviceTablet = "tablet"; //Generic term for slate and tablet devices
   const string deviceBrew = "brew";
   const string deviceDanger = "danger";
   const string deviceHiptop = "hiptop";
   const string devicePlaystation = "playstation";
   const string devicePlaystationVita = "vita";
   const string deviceNintendoDs = "nitro";
   const string deviceNintendo = "nintendo";
   const string deviceWii = "wii";
   const string deviceXbox = "xbox";
   const string deviceArchos = "archos";
   
   const string engineFirefox = "firefox"; //For Firefox OS
   const string engineOpera = "opera"; //Popular browser
   const string engineNetfront = "netfront"; //Common embedded OS browser
   const string engineUpBrowser = "up.browser"; //common on some phones
   const string engineOpenWeb = "openweb"; //Transcoding by OpenWave server
   const string deviceMidp = "midp"; //a mobile Java technology
   const string uplink = "up.link";
   const string engineTelecaQ = "teleca q"; //a modern feature phone browser
   
   const string devicePda = "pda"; //some devices report themselves as PDAs
   const string mini = "mini";  //Some mobile browsers put 'mini' in their names.
   const string mobile = "mobile"; //Some mobile browsers put 'mobile' in their user agent strings.
   const string mobi = "mobi"; //Some mobile browsers put 'mobi' in their user agent strings.
   
   //Smart TV strings
   const string smartTV1 = "smart-tv"; //Samsung Tizen smart TVs
   const string smartTV2 = "smarttv"; //LG WebOS smart TVs

   //Use Maemo, Tablet, and Linux to test for Nokia's Internet Tablets.
   const string maemo = "maemo";
   const string llinux = "linux";
   const string qtembedded = "qt embedded"; //for Sony Mylo and others
   const string mylocom2 = "com2"; //for Sony Mylo also
   
   //In some UserAgents, the only clue is the manufacturer.
   const string manuSonyEricsson = "sonyericsson";
   const string manuericsson = "ericsson";
   const string manuSamsung1 = "sec-sgh";
   const string manuSony = "sony";
   const string manuHtc = "htc";

   //In some UserAgents, the only clue is the operator.
   const string svcDocomo = "docomo";
   const string svcKddi = "kddi";
   const string svcVodafone = "vodafone";

   //Disambiguation strings.
   const string disUpdate = "update"; //pda vs. update

//**************************
// The uagent_info class encapsulates information about
//   a browser's connection to your web site. 
//   You can use it to find out whether the browser asking for
//   your site's content is probably running on a mobile device.
//   The methods were written so you can be as granular as you want.
//   For example, enquiring whether it's as specific as an iPod Touch or
//   as general as a smartphone class device.
//   The object's methods return 1 for true, or 0 for false.
class uagent_info
{
private:
   string useragent;
   string httpaccept;
   //Let's store values for quickly accessing the same info multiple times. InitCompleted
   bool initCompleted; //Stores whether we're currently initializing the most popular functions.
   bool isWebkit; //Stores the result of DetectWebkit()
   bool isMobilePhone; //Stores the result of DetectMobileQuick()
   bool isIphone; //Stores the result of DetectIphone()
   bool isAndroid; //Stores the result of DetectAndroid()
   bool isAndroidPhone; //Stores the result of DetectAndroidPhone()
   bool isTierTablet; //Stores the result of DetectTierTablet()
   bool isTierIphone; //Stores the result of DetectTierIphone()
   bool isTierRichCss; //Stores the result of DetectTierRichCss()
   bool isTierGenericMobile; //Stores the result of DetectTierOtherPhones()

   //**************************
   // Initialize Key Stored Values.
   void InitDeviceScan()
   {
        //Save these properties to speed processing
        isWebkit = DetectWebkit();
        isIphone = DetectIphone();
        isAndroid = DetectAndroid();
        isAndroidPhone = DetectAndroidPhone();
        
        //These tiers are the most useful for web development
        isTierTablet = DetectTierTablet(); //Do first
        isTierIphone = DetectTierIphone(); //Do second
        isMobilePhone = DetectMobileQuick(); //Do third
        
        //Optional: Comment these out if you NEVER use them.
        isTierRichCss = DetectTierRichCss();
        isTierGenericMobile = DetectTierOtherPhones();
        
        initCompleted = true;
   }

public:

   //**************************
   //The constructor.
   uagent_info(const string &user_agent,const string &accept):
	useragent(user_agent),
	httpaccept(accept),
   	initCompleted(false),
  	isWebkit(false),
	isMobilePhone(false),
	isIphone(false),
	isAndroid(false),
	isAndroidPhone(false),
	isTierTablet(false),
	isTierIphone(false),
	isTierRichCss(false),
	isTierGenericMobile(false)
   { 
	 std::transform(useragent.begin(), useragent.end(),useragent.begin(), ::tolower);
	 std::transform(httpaccept.begin(), httpaccept.end(),httpaccept.begin(), ::tolower);
		
	 //Let's initialize some values to save cycles later.
	 InitDeviceScan();
   }
   
   

   //**************************
   //Returns the contents of the User Agent value, in lower case.
   string Get_Uagent()
   { 
       return useragent;
   }

   //**************************
   //Returns the contents of the HTTP Accept value, in lower case.
   string Get_HttpAccept()
   { 
       return httpaccept;
   }
   

   //**************************
   // Detects if the current device is an iPhone.
   bool DetectIphone()
   {
      if (initCompleted == true ||
          isIphone == true)
         return isIphone;
      
      if (useragent.find( MobileESP::deviceIphone) != std::string::npos)
      {
         //The iPad and iPod Touch say they're an iPhone. So let's disambiguate.
         if (DetectIpad() == true ||
             DetectIpod() == true)
            return false;
         //Yay! It's an iPhone!
         else
            return true; 
      }
      else
         return false; 
   }

   //**************************
   // Detects if the current device is an iPod Touch.
   bool DetectIpod()
   {
      if (useragent.find( MobileESP::deviceIpod) != std::string::npos)
         return true; 
      else
         return false; 
   }
   
   //**************************
   // Detects if the current device is an iPad tablet.
   bool DetectIpad()
   {
      if (useragent.find( MobileESP::deviceIpad) != std::string::npos &&
          DetectWebkit() == true)
         return true; 
      else
         return false; 
   }

   //**************************
   // Detects if the current device is an iPhone or iPod Touch.
   bool DetectIphoneOrIpod()
   {
       //We repeat the searches here because some iPods may report themselves as an iPhone, which would be okay.
      if (DetectIphone() == true ||
             DetectIpod() == true)
         return true; 
      else
         return false; 
   }

   //**************************
   // Detects *any* iOS device: iPhone, iPod Touch, iPad.
   bool DetectIos()
   {
      if ((DetectIphoneOrIpod() == true) ||
        (DetectIpad() == true))
         return true; 
      else
         return false;
   }


   //**************************
   // Detects *any* Android OS-based device: phone, tablet, and multi-media player.
   // Also detects Google TV.
   bool DetectAndroid()
   {
      if (initCompleted == true ||
          isAndroid == true)
         return isAndroid;

      if ((useragent.find( MobileESP::deviceAndroid) != std::string::npos)
          || (DetectGoogleTV() == true))
         return true; 
         
      return false; 
   }

   //**************************
   // Detects if the current device is a (small-ish) Android OS-based device
   // used for calling and/or multi-media (like a Samsung Galaxy Player).
   // Google says these devices will have 'Android' AND 'mobile' in user agent.
   // Ignores tablets (Honeycomb and later).
   bool DetectAndroidPhone()
   {
      if (initCompleted == true ||
          isAndroidPhone == true)
         return isAndroidPhone;

      //First, let's make sure we're on an Android device.
      if (DetectAndroid() == false)
         return false; 

      //If it's Android and has 'mobile' in it, Google says it's a phone.
      if (useragent.find( MobileESP::mobile) != std::string::npos)
         return true;
      
      //Special check for Android devices with Opera Mobile/Mini. They should report here.
      if ((DetectOperaMobile() == true))
         return true; 

      return false; 
   }

   //**************************
   // Detects if the current device is a (self-reported) Android tablet.
   // Google says these devices will have 'Android' and NOT 'mobile' in their user agent.
   bool DetectAndroidTablet()
   {
      //First, let's make sure we're on an Android device.
      if (DetectAndroid() == false)
         return false; 

      //Special check for Android devices with Opera Mobile/Mini. They should NOT report here.
      if (DetectOperaMobile() == true)
         return false; 
         
      //Otherwise, if it's Android and does NOT have 'mobile' in it, Google says it's a tablet.
      if (useragent.find( MobileESP::mobile) != std::string::npos)
         return false;
         
      return true; 
   }

   //**************************
   // Detects if the current device is an Android OS-based device and
   //   the browser is based on WebKit.
   bool DetectAndroidWebKit()
   {
      if ((DetectAndroid() == true) &&
		(DetectWebkit() == true))
         return true; 
      else
         return false; 
   }

   //**************************
   // Detects if the current device is a GoogleTV.
   bool DetectGoogleTV()
   {
      if (useragent.find( MobileESP::deviceGoogleTV) != std::string::npos)
         return true; 
      else
         return false; 
   }

   //**************************
   // Detects if the current browser is based on WebKit.
   bool DetectWebkit()
   {
      if (initCompleted == true ||
          isWebkit == true)
         return isWebkit;

      if (useragent.find( MobileESP::engineWebKit) != std::string::npos)
         return true; 
      else
         return false; 
   }


   //**************************
   // Detects if the current browser is a 
   // Windows Phone 7, 8, or 10 device.
   bool DetectWindowsPhone()
   {
      if ((DetectWindowsPhone7() == true)
			|| (DetectWindowsPhone8() == true)
			|| (DetectWindowsPhone10() == true))
         return true; 
      else
         return false; 
   }
   
   //**************************
   // Detects a Windows Phone 7 device (in mobile browsing mode).
   bool DetectWindowsPhone7()
   {
      if (useragent.find( MobileESP::deviceWinPhone7) != std::string::npos)
         return true; 
      else
         return false; 
   }
   
   //**************************
   // Detects a Windows Phone 8 device (in mobile browsing mode).
   bool DetectWindowsPhone8()
   {
      if (useragent.find( MobileESP::deviceWinPhone8) != std::string::npos)
         return true; 
      else
         return false; 
   }

   //**************************
   // Detects a Windows Phone 10 device (in mobile browsing mode).
   bool DetectWindowsPhone10()
   {
      if (useragent.find( MobileESP::deviceWinPhone10) != std::string::npos)
         return true; 
      else
         return false; 
   }

   //**************************
   // Detects if the current browser is a Windows Mobile device.
   // Excludes Windows Phone 7 and later devices. 
   // Focuses on Windows Mobile 6.xx and earlier.
   bool DetectWindowsMobile()
   {
      if (DetectWindowsPhone() == true)
         return false;
      
      //Most devices use 'Windows CE', but some report 'iemobile' 
      //  and some older ones report as 'PIE' for Pocket IE. 
      if (useragent.find( MobileESP::deviceWinMob) != std::string::npos ||
          useragent.find( MobileESP::deviceIeMob) != std::string::npos ||
          useragent.find( MobileESP::enginePie) != std::string::npos)
         return true; 
      //Test for Windows Mobile PPC but not old Macintosh PowerPC.
	  if (useragent.find( MobileESP::devicePpc) != std::string::npos
		  && !(useragent.find( MobileESP::deviceMacPpc) > 1))
         return true; 
      //Test for certain Windwos Mobile-based HTC devices.
      if (useragent.find( MobileESP::manuHtc) != std::string::npos &&
          useragent.find( MobileESP::deviceWindows) != std::string::npos)
         return true; 
      if (DetectWapWml() == true &&
          useragent.find( MobileESP::deviceWindows) != std::string::npos) 
         return true; 
      else
         return false; 
   }

   //**************************
   // Detects if the current browser is any BlackBerry device.
   // Includes BB10 OS, but excludes the PlayBook.
   bool DetectBlackBerry()
   {
       if ((useragent.find( MobileESP::deviceBB) != std::string::npos) ||
          (httpaccept.find( MobileESP::vndRIM) != std::string::npos))
         return true;
      if (DetectBlackBerry10Phone() == true) 
         return true;       
       else
         return false; 
   }
   
   //**************************
   // Detects if the current browser is a BlackBerry 10 OS phone.
   // Excludes tablets.
   bool DetectBlackBerry10Phone()
   {
       if ((useragent.find( MobileESP::deviceBB10) != std::string::npos) &&
          (useragent.find( MobileESP::mobile) != std::string::npos))
         return true; 
       else
         return false; 
   }
   
   //**************************
   // Detects if the current browser is on a BlackBerry tablet device.
   //    Examples: PlayBook
   bool DetectBlackBerryTablet()
   {
      if ((useragent.find( MobileESP::deviceBBPlaybook) != std::string::npos))
         return true; 
      else
        return false; 
   }

   //**************************
   // Detects if the current browser is a BlackBerry phone device AND uses a
   //    WebKit-based browser. These are signatures for the new BlackBerry OS 6.
   //    Examples: Torch. Includes the Playbook.
   bool DetectBlackBerryWebKit()
   {
      if ((DetectBlackBerry() == true) &&
		(DetectWebkit() == true))
         return true; 
      else
        return false; 
   }

   //**************************
   // Detects if the current browser is a BlackBerry Touch phone device with
   //    a large screen, such as the Storm, Torch, and Bold Touch. Excludes the Playbook.
   bool DetectBlackBerryTouch()
   {  
       if ((useragent.find( MobileESP::deviceBBStorm) != std::string::npos) ||
		(useragent.find( MobileESP::deviceBBTorch) != std::string::npos) ||
		(useragent.find( MobileESP::deviceBBBoldTouch) != std::string::npos) ||
		(useragent.find( MobileESP::deviceBBCurveTouch) != std::string::npos))
         return true; 
       else
         return false; 
   }
   
   //**************************
   // Detects if the current browser is a BlackBerry OS 5 device AND
   //    has a more capable recent browser. Excludes the Playbook.
   //    Examples, Storm, Bold, Tour, Curve2
   //    Excludes the new BlackBerry OS 6 and 7 browser!!
   bool DetectBlackBerryHigh()
   {
      //Disambiguate for BlackBerry OS 6 or 7 (WebKit) browser
      if (DetectBlackBerryWebKit() == true)
         return false; 
      if (DetectBlackBerry() == true)
      {
          if ((DetectBlackBerryTouch() == true) ||
            useragent.find( MobileESP::deviceBBBold) != std::string::npos ||
            useragent.find( MobileESP::deviceBBTour) != std::string::npos ||
            useragent.find( MobileESP::deviceBBCurve) != std::string::npos)
          {
             return true; 
          }
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
   bool DetectBlackBerryLow()
   {
      if (DetectBlackBerry() == true)
      {
          //Assume that if it's not in the High tier, then it's Low.
          if ((DetectBlackBerryHigh() == true) ||
			(DetectBlackBerryWebKit() == true))
             return false; 
          else
            return true; 
      }
      else
        return false; 
   }


   //**************************
   // Detects if the current browser is the Nokia S60 Open Source Browser.
   bool DetectS60OssBrowser()
   {
      //First, test for WebKit, then make sure it's either Symbian or S60.
      if (DetectWebkit() == true)
      {
        if (useragent.find( MobileESP::deviceSymbian) != std::string::npos ||
            useragent.find( MobileESP::deviceS60) != std::string::npos)
        {
           return true;
        }
        else
           return false; 
      }
      else
         return false; 
   }
   
   //**************************
   // Detects if the current device is any Symbian OS-based device,
   //   including older S60, Series 70, Series 80, Series 90, and UIQ, 
   //   or other browsers running on these devices.
   bool DetectSymbianOS()
   {
       if (useragent.find( MobileESP::deviceSymbian) != std::string::npos || 
           useragent.find( MobileESP::deviceS60) != std::string::npos ||
           useragent.find( MobileESP::deviceS70) != std::string::npos || 
           useragent.find( MobileESP::deviceS80) != std::string::npos ||
           useragent.find( MobileESP::deviceS90) != std::string::npos)
         return true; 
      else
         return false; 
   }


   //**************************
   // Detects if the current browser is on a PalmOS device.
   bool DetectPalmOS()
   {
		//Make sure it's not WebOS first
		if (DetectPalmWebOS() == true)
			return false;

      //Most devices nowadays report as 'Palm', but some older ones reported as Blazer or Xiino.
      if (useragent.find( MobileESP::devicePalm) != std::string::npos ||
          useragent.find( MobileESP::engineBlazer) != std::string::npos ||
          useragent.find( MobileESP::engineXiino) != std::string::npos)
            return true; 
      else
         return false; 
   }


   //**************************
   // Detects if the current browser is on a Palm device
   //   running the new WebOS.
   bool DetectPalmWebOS()
   {
      if (useragent.find( MobileESP::deviceWebOS) != std::string::npos)
         return true; 
      else
         return false; 
   }

   //**************************
   // Detects if the current browser is on an HP tablet running WebOS.
   bool DetectWebOSTablet()
   {
      if ((useragent.find( MobileESP::deviceWebOShp) != std::string::npos)
			&& (useragent.find( MobileESP::deviceTablet) != std::string::npos))
         return true; 
      else
         return false; 
   }

   //**************************
   // Detects if the current browser is on a WebOS smart TV.
   bool DetectWebOSTV()
   {
      if ((useragent.find( MobileESP::deviceWebOStv) != std::string::npos)
			&& (useragent.find( MobileESP::smartTV2) != std::string::npos))
         return true; 
      else
         return false; 
   }

   //**************************
   // Detects if the current browser is Opera Mobile or Mini.
   bool DetectOperaMobile()
   {
      if (useragent.find( MobileESP::engineOpera) != std::string::npos)
      {
         if ((useragent.find( MobileESP::mini) != std::string::npos) ||
          (useragent.find( MobileESP::mobi) != std::string::npos))
            return true; 
         else
            return false; 
      }
      else
         return false; 
   }

   //**************************
   // Detects if the current device is an Amazon Kindle (eInk devices only).
   // Note: For the Kindle Fire, use the normal Android methods. 
   bool DetectKindle()
   {
      if (useragent.find( MobileESP::deviceKindle) != std::string::npos &&
          DetectAndroid() == false)
         return true; 
      else
         return false; 
   }
   
   //**************************
   // Detects if the current Amazon device has turned on the Silk accelerated browsing feature.
   // Note: Typically used by the the Kindle Fire.
   bool DetectAmazonSilk()
   {
      if (useragent.find( MobileESP::engineSilk) != std::string::npos)
         return true; 
      else
         return false; 
   }

   //**************************
   // Detects if a Garmin Nuvifone device.
   bool DetectGarminNuvifone()
   {
      if (useragent.find( MobileESP::deviceNuvifone) != std::string::npos)
         return true; 
      else
         return false; 
   }
   
   //**************************
   // Detects a device running the Bada smartphone OS from Samsung.
   bool DetectBada()
   {
      if (useragent.find( MobileESP::deviceBada) != std::string::npos)
         return true; 
      else
         return false; 
   }

   //**************************
   // Detects a device running the Tizen smartphone OS.
   bool DetectTizen()
   {
      if ((useragent.find( MobileESP::deviceTizen) != std::string::npos)
			&& (useragent.find( MobileESP::mobile) != std::string::npos))
         return true; 
      else
         return false; 
   }
   
    //**************************
   // Detects if the current browser is on a Tizen smart TV.
   bool DetectTizenTV()
   {
      if ((useragent.find( MobileESP::deviceTizen) != std::string::npos)
			&& (useragent.find( MobileESP::smartTV1) != std::string::npos))
         return true; 
      else
         return false; 
   }

   //**************************
   // Detects a device running the Meego OS.
   bool DetectMeego()
   {
      if (useragent.find( MobileESP::deviceMeego) != std::string::npos)
         return true; 
      else
         return false; 
   }
   
    //**************************
   // Detects a phone running the Meego OS.
   bool DetectMeegoPhone()
   {
      if ((useragent.find( MobileESP::deviceMeego) != std::string::npos)
			&& (useragent.find( MobileESP::mobi) != std::string::npos))
         return true; 
      else
         return false; 
   }

   //**************************
   // Detects a mobile device (probably) running the Firefox OS.
   bool DetectFirefoxOS()
   {
      if ((DetectFirefoxOSPhone() == true) || DetectFirefoxOSTablet())
         return true;
      
      return false; 
   }

   //**************************
   // Detects a phone (probably) running the Firefox OS.
   bool DetectFirefoxOSPhone()
   {
      //First, let's make sure we're NOT on another major mobile OS.
      if (DetectIos() || 
      	DetectAndroid() || 
      	DetectSailfish())
         return false;
            
      if ((useragent.find( MobileESP::engineFirefox) != std::string::npos) &&
		(useragent.find( MobileESP::mobile) != std::string::npos))
         return true;
      
      return false; 
   }

   //**************************
   // Detects a tablet (probably) running the Firefox OS.
   bool DetectFirefoxOSTablet()
   {
      //First, let's make sure we're NOT on another major mobile OS.
      if (DetectIos() || 
      	DetectAndroid() || 
      	DetectSailfish())
         return false;
            
      if ((useragent.find( MobileESP::engineFirefox) != std::string::npos) &&
		(useragent.find( MobileESP::deviceTablet) != std::string::npos))
         return true;
      
      return false; 
   }

   //**************************
   // Detects a device running the Sailfish OS.
   bool DetectSailfish()
   {
      if (useragent.find( MobileESP::deviceSailfish) != std::string::npos)
         return true; 
      else
         return false; 
   }

   //**************************
   // Detects a phone running the Sailfish OS.
   bool DetectSailfishPhone()
   {
      if ((DetectSailfish() == true) &&
		(useragent.find( MobileESP::mobile) != std::string::npos))
         return true;
      
      return false; 
   }

   //**************************
   // Detects a mobile device running the Ubuntu Mobile OS.
   bool DetectUbuntu()
   {
      if ((DetectUbuntuPhone() == true) || DetectUbuntuTablet() == true)
         return true;
      
      return false; 
   }

   //**************************
   // Detects a phone running the Ubuntu Mobile OS.
   bool DetectUbuntuPhone()
   {
      if ((useragent.find( MobileESP::deviceUbuntu) != std::string::npos) &&
		(useragent.find( MobileESP::mobile) != std::string::npos))
         return true;
      
      return false; 
   }

   //**************************
   // Detects a tablet running the Ubuntu Mobile OS.
   bool DetectUbuntuTablet()
   {
      if ((useragent.find( MobileESP::deviceUbuntu) != std::string::npos) &&
		(useragent.find( MobileESP::deviceTablet) != std::string::npos))
         return true;
      
      return false; 
   }
   
   //**************************
   // Detects the Danger Hiptop device.
   bool DetectDangerHiptop()
   {
      if (useragent.find( MobileESP::deviceDanger) != std::string::npos ||
          useragent.find( MobileESP::deviceHiptop) != std::string::npos)
         return true; 
      else
         return false; 
   }

   //**************************
   // Detects if the current browser is a Sony Mylo device.
   bool DetectSonyMylo()
   {
      if ((useragent.find( MobileESP::manuSony) != std::string::npos) &&
         ((useragent.find( MobileESP::qtembedded) != std::string::npos) ||
          (useragent.find( MobileESP::mylocom2) != std::string::npos)))
         return true; 
      else
         return false; 
   }
   
   //**************************
   // Detects if the current device is on one of the Maemo-based Nokia Internet Tablets.
   bool DetectMaemoTablet()
   {
      if (useragent.find( MobileESP::maemo) != std::string::npos)
         return true; 
      //For Nokia N810, must be Linux + Tablet, or else it could be something else. 
      if ((useragent.find( MobileESP::llinux) != std::string::npos)
		&& (useragent.find( MobileESP::deviceTablet) != std::string::npos) 
		&& (DetectWebOSTablet() == false)
		&& (DetectAndroid() == false))
         return true; 
      else
         return false; 
   }

   //**************************
   // Detects if the current device is an Archos media player/Internet tablet.
   bool DetectArchos()
   {
      if (useragent.find( MobileESP::deviceArchos) != std::string::npos)
         return true; 
      else
         return false; 
   }

   //**************************
   // Detects if the current device is an Internet-capable game console.
   // Includes many handheld consoles.
   bool DetectGameConsole()
   {
      if ((DetectSonyPlaystation() == true) ||
		 (DetectNintendo() == true) ||
		 (DetectXbox() == true))
         return true; 
      else
         return false; 
   }
   
   //**************************
   // Detects if the current device is a Sony Playstation.
   bool DetectSonyPlaystation()
   {
      if (useragent.find( MobileESP::devicePlaystation) != std::string::npos)
         return true; 
      else
         return false; 
   }

   //**************************
   // Detects if the current device is a handheld gaming device with
   // a touchscreen and modern iPhone-class browser. Includes the Playstation Vita.
   bool DetectGamingHandheld()
   {
      if ((useragent.find( MobileESP::devicePlaystation) != std::string::npos) &&
         (useragent.find( MobileESP::devicePlaystationVita) != std::string::npos))
         return true; 
      else
         return false; 
   }

   //**************************
   // Detects if the current device is a Nintendo game device.
   bool DetectNintendo()
   {
      if (useragent.find( MobileESP::deviceNintendo) != std::string::npos || 
           useragent.find( MobileESP::deviceWii) != std::string::npos ||
           useragent.find( MobileESP::deviceNintendoDs) != std::string::npos)
         return true; 
      else
         return false; 
   }

   //**************************
   // Detects if the current device is a Microsoft Xbox.
   bool DetectXbox()
   {
      if (useragent.find( MobileESP::deviceXbox) != std::string::npos)
         return true; 
      else
         return false; 
   }

   //**************************
   // Detects whether the device is a Brew-powered device.
   bool DetectBrewDevice()
   {
       if (useragent.find( MobileESP::deviceBrew) != std::string::npos)
         return true; 
      else
         return false; 
   }
   
   //**************************
   // Detects whether the device supports WAP or WML.
   bool DetectWapWml()
   {
       if (httpaccept.find( MobileESP::vndwap) != std::string::npos ||
           httpaccept.find( MobileESP::wml) != std::string::npos)
         return true; 
      else
         return false; 
   }
   
   //**************************
   // Detects if the current device supports MIDP, a mobile Java technology.
   bool DetectMidpCapable()
   {
       if (useragent.find( MobileESP::deviceMidp) != std::string::npos || 
           httpaccept.find( MobileESP::deviceMidp) != std::string::npos)
         return true; 
      else
         return false; 
   }



  //*****************************
  // Device Classes
  //*****************************
   
   //**************************
   // Check to see whether the device is *any* 'smartphone'.
   //   Note: It's better to use DetectTierIphone() for modern touchscreen devices. 
   bool DetectSmartphone()
   {
      //Exclude duplicates from TierIphone
      if ((DetectTierIphone() == true)
		|| (DetectS60OssBrowser() == true)
		|| (DetectSymbianOS() == true) 
		|| (DetectWindowsMobile() == true)
		|| (DetectBlackBerry() == true)
		|| (DetectMeegoPhone() == true)
		|| (DetectPalmWebOS() == true))
         return true; 
      else
         return false; 
   }

   //**************************
   // The quick way to detect for a mobile device.
   //   Will probably detect most recent/current mid-tier Feature Phones
   //   as well as smartphone-class devices. Excludes Apple iPads and other modern tablets.
   bool DetectMobileQuick()
   {
      if (initCompleted == true ||
          isMobilePhone == true)
         return isMobilePhone;

      //Let's exclude tablets
      if (isTierTablet == true) 
         return false;
      
      //Most mobile browsing is done on smartphones
      if (DetectSmartphone() == true) 
         return true;

      //Catch-all for many mobile devices
      if (useragent.find( MobileESP::mobile) != std::string::npos)
         return true; 

      if (DetectOperaMobile() == true)
         return true;

      //We also look for Kindle devices
      if (DetectKindle() == true ||
         DetectAmazonSilk() == true) 
         return true;

      if ((DetectWapWml() == true) 
			|| (DetectMidpCapable() == true) 
			|| (DetectBrewDevice() == true))
         return true;
         
      if ((useragent.find( MobileESP::engineNetfront) != std::string::npos)
			|| (useragent.find( MobileESP::engineUpBrowser) != std::string::npos))
         return true; 
         
      else
         return false; 
   }
  
   //**************************
   // The longer and more thorough way to detect for a mobile device.
   //   Will probably detect most feature phones,
   //   smartphone-class devices, Internet Tablets, 
   //   Internet-enabled game consoles, etc.
   //   This ought to catch a lot of the more obscure and older devices, also --
   //   but no promises on thoroughness!
   bool DetectMobileLong()
   {
      if (DetectMobileQuick() == true) 
         return true; 
      if (DetectGameConsole() == true) 
         return true; 
         
      if ((DetectDangerHiptop() == true) 
			|| (DetectMaemoTablet() == true)
			|| (DetectSonyMylo() == true)
			|| (DetectArchos() == true))
         return true; 

       if ((useragent.find( MobileESP::devicePda) != std::string::npos) &&
		 !(useragent.find( MobileESP::disUpdate) != std::string::npos))
         return true;
      
       //Detect older phones from certain manufacturers and operators. 
       if ((useragent.find( MobileESP::uplink) != std::string::npos)
       		|| (useragent.find( MobileESP::engineOpenWeb) != std::string::npos)
       		|| (useragent.find( MobileESP::manuSamsung1) != std::string::npos)
       		|| (useragent.find( MobileESP::manuSonyEricsson) != std::string::npos)
       		|| (useragent.find( MobileESP::manuericsson) != std::string::npos)
       		|| (useragent.find( MobileESP::svcDocomo) != std::string::npos)
       		|| (useragent.find( MobileESP::svcKddi) != std::string::npos)
       		|| (useragent.find( MobileESP::svcVodafone) != std::string::npos))
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
   bool DetectTierTablet()
   {
      if (initCompleted == true ||
          isTierTablet == true)
         return isTierTablet;

      if ((DetectIpad() == true) 
         || (DetectAndroidTablet() == true) 
         || (DetectBlackBerryTablet() == true) 
         || (DetectFirefoxOSTablet() == true)
         || (DetectUbuntuTablet() == true)
         || (DetectWebOSTablet() == true))
         return true; 
      else
         return false; 
   }


   //**************************
   // The quick way to detect for a tier of devices.
   //   This method detects for devices which can 
   //   display iPhone-optimized web content.
   //   Includes iPhone, iPod Touch, Android, Windows Phone 7 and 8, BB10, WebOS, Playstation Vita, etc.
   bool DetectTierIphone()
   {
      if (initCompleted == true ||
          isTierIphone == true)
         return isTierIphone;

      if ((DetectIphoneOrIpod() == true)
			|| (DetectAndroidPhone() == true)
			|| (DetectWindowsPhone() == true)
			|| (DetectBlackBerry10Phone() == true)
			|| (DetectPalmWebOS() == true)
			|| (DetectBada() == true)
			|| (DetectTizen() == true)
			|| (DetectFirefoxOSPhone() == true)
			|| (DetectSailfishPhone() == true)
			|| (DetectUbuntuPhone() == true)
			|| (DetectGamingHandheld() == true))
         return true; 
      
      //Note: BB10 phone is in the previous paragraph
      if ((DetectBlackBerryWebKit() == true) &&
		(DetectBlackBerryTouch() == true))
         return true;
      
      else
         return false; 
   }
   
   //**************************
   // The quick way to detect for a tier of devices.
   //   This method detects for devices which are likely to be capable 
   //   of viewing CSS content optimized for the iPhone, 
   //   but may not necessarily support JavaScript.
   //   Excludes all iPhone Tier devices.
   bool DetectTierRichCss()
   {
      if (initCompleted == true ||
          isTierRichCss == true)
         return isTierRichCss;

      if (DetectMobileQuick() == true) 
      {
        //Exclude iPhone Tier and e-Ink Kindle devices
        if ((DetectTierIphone() == true) ||
            (DetectKindle() == true))
           return false;
           
        //The following devices are explicitly ok.
        if (DetectWebkit() == true) //Any WebKit
           return true;
        if (DetectS60OssBrowser() == true)
           return true;
           
        //Note: 'High' BlackBerry devices ONLY
        if (DetectBlackBerryHigh() == true)
           return true;
        
        //Older Windows 'Mobile' isn't good enough for iPhone Tier. 
        if (DetectWindowsMobile() == true)
           return true;
        if (useragent.find( MobileESP::engineTelecaQ) != std::string::npos)
           return true;
         
        //default
        else
           return false;
      }
      else
         return false; 
   }

   //**************************
   // The quick way to detect for a tier of devices.
   //   This method detects for all other types of phones,
   //   but excludes the iPhone and RichCSS Tier devices.
   bool DetectTierOtherPhones()
   {
      if (initCompleted == true ||
          isTierGenericMobile == true)
         return isTierGenericMobile;

      //Exclude devices in the other 2 categories 
      if ((DetectMobileLong() == true)
		&& (DetectTierIphone() == false)
		&& (DetectTierRichCss() == false))
           return true;
      else
         return false; 
   }
};

}

/* Example code *
#include <iostream>
#include "mdetect.h"

int main(int argc, char** argv)
{
	int ret = 0;
	if(argc < 3) {
		std::cout << "Usage is mdetect [user agent string] [Accept string]" << std::endl;
	} else {
		MobileESP::uagent_info usageInfo(argv[1],argv[2]);
		if(true == usageInfo.DetectMobileQuick()) {
			std::cout << "Passed in user agent is from a mobile browser" << std::endl;
		} else {
			std::cout << "Passed in user agent is *NOT* from a mobile browser" << std::endl;
		}
	}
	return ret;
}

*/
#endif //MDETECT_H
