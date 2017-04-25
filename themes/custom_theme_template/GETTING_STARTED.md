# Making a new theme

We recommend you make a copy of the `custom_theme_template` directory, inside of the `themes` directory. That way, when there are updates to VuFind, you won't run into trouble with the updates changing your customizations or causing conflicts in git.

## Configuration

There are three places in the VuFind configuration that you can change to see your custom theme in action.

The first and most straight-forward is by changing `config.ini > [Site] > theme`. This will change the default theme for everyone who comes to your site and is the setting you should change for production.

The two more development-oriented options are below `theme`: `alternate_themes` and `selectable_themes`. They both take comma-separated lists of themes in the format of `name1:theme1,name2:theme2`. Changing the theme via either of these two options sets a cookie that will keep your theme choice over the course of your session.

Setting up themes under `alternate_themes` allows you to add a `ui=name` parameter to the end of your URL to see that theme. For example, the default configuration of `custom:custom_theme_template` would cause `https://vufind.org/?ui=custom` to show the theme described in the directory `custom_theme_template`.

Setting up themes under `selectable_themes` causes a dropdown to appear in the toolbar next to Login. This will allow you and any user to change their theme via this dropdown.

## Theme Config

The first thing you'll have to set in a new theme is the file `theme.config.php`. This file allows you to establish your theme as a child of another theme and allows you to add new CSS and Javascript files. If you've created custom view helpers, you will also set up their factories in this file. You only need to add files here that wouldn't be matched via inheritance (such as adding a new jQuery plugin).

A basic setup is already included in the custom theme template. For a full example, see the [bootstrap config file](https://github.com/vufind-org/vufind/blob/master/themes/bootstrap3/theme.config.php).

## Theme Inheritance

VuFind's themes system allows you to base your custom theme on a previously created theme. Doing so will cause VuFind to look for a template, CSS, Javascript, or image file first in your custom theme, then its parent, then its grand-parent, all the way up to root. This allows you to change any individual file within the theme without having to copy every file over. For example, you might noticed that the [bootprint theme has no templates](https://github.com/vufind-org/vufind/blob/master/themes/bootprint3/) of its own. It is entirely a style overhaul based on the bootstrap theme.

This is configured in `theme.config.php`. LESS inheritance is targetted towards and controlled by the [`compiled.less`](https://github.com/vufind-org/vufind/blob/master/themes/custom_theme_template/less/compiled.less) file.

# Style Customization

## LESS

[LESS](http://lesscss.org/#) is an abstraction of CSS that is compiled into CSS. This allows LESS to have more advanced features and more concise syntax.

VuFind also supports [SASS](https://sass-lang.com/). We use a custom command, `grunt lessToSass` to convert our LESS files to SASS automatically. We test for SASS syntax errors regularly and apologize for any errors. We encourage you to help us support SASS better.

## Grunt

After you make any changes to a theme's LESS, you need to run the command `grunt less` in the command line. Make sure you have run `npm install` and have [installed the Grunt CLI](https://gruntjs.com/getting-started). For times when you're making a lot of changes, you can have Grunt watch for changes and compile LESS accordingly. To do this, start the command `grunt watch:less` in the command line.

For SASS, you can run `grunt scss` and `grunt watch:scss`.
