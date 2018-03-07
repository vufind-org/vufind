# TueFind-theme
VuFind-Extensions for basis themes like root and bootptrint3, used in IxTheo, KrimDok and RelBib (e.g. additional javascript functions)

Examples for functionality of this theme:
* Setting focus on several search fields, dependent on current loaded module
* Also, not only setting focus (like in vufind standard behaviour), but also setting cursor to end of field if field has content. This is compatible with Microsoft Edge as well as Firefox
* Place to put common JavaScript helper functions (e.g. possibility to add additional javascript window.onload handlers, without overwriting the existing one)

## Installation note
After git pull of your main repository (e.g. ixtheo/krimdok), dont forget:
* git submodule init
* git submodule update

For later checkouts
* git submodule update --init --recursive --remote
##
Integration of motty/keyboard
For providing a virtual keyboard it also contains files taken from of https://github.com/Mottie/Keyboard
