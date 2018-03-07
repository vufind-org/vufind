# Readme #

Documention for ixTheo theme development.
Written by Benjamin Schnabel <benjamin.schnabel@uni-tuebingen.de>, Oct 2017.

## Introduction ##

Welcome to the theme for ixTheo, relBib and KrimDok.

All the themes derive from the ixTheoTheme. Therefore refer to the ixTheoTheme as the main point of reference.

The whole vufind system is built on the Zend engine, Version 2.4.0. You can find the manuals here [https://framework.zend.com/manual/2.4].
The engine is the default Zend templating engine.
You can find the documentation on vufind here [https://vufind.org/wiki].


The Theme is extend of the default "bootstrap 3" theme, which comes with vufind. 
The file `theme.config.php` configures the extentions of the theme, as well as the css and js files.

For now there is no Bootstrap 4 theme, which means we have to compile and include the needed 
resources for bootstrap ourselves.

The theme comes with bower, which installs all the needed componentens, which are:
* Bootstrap 4
* Popper
* jQuery (jQuery is actually installed by default, but the whole thing is somehow not working properly. Maybe it is an old version.)
* Font-Awesome (Since Bootstrap 4 does not include Glyphicons anymore, we need Font-Awesome. Also the version included does not work.)
* bootstrap-select (depreciated, used for bootstrap 3, but included for backward compability.)

## Gulp ##


## Sass ##
Since Bootstrap 4 there is no less anymore, which means we need to compile css form sass.
So Sass is the preferred language, even vufind supports less. You can compile the less files of the theme to sass files,
using the grunt taskrunner. 

## CSS ##

## Javascript ##

## Site Generator ##

The site generator is an external module which can generate static sites, used within the theme.

