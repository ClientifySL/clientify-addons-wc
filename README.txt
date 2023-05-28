=== Clientify-Ecommerce ===
Contributors: CLIENTIFY® Development team
Tags: woocommerce, clientify, integration, CRM, ecommerce
Requires at least: 4.0
Tested up to: 6.2.2
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Integrate WooCommerce actions with Clientify for syncing orders, products, customers, and abandoned carts.

== Description ==

The Clientify-Ecommerce plugin allows you to integrate WooCommerce actions with Clientify, a CRM, to facilitate data synchronization. With this integration, you can automatically send information about orders, products, customers, and abandoned carts to your Clientify account.

= Screenshots =

[Attach some screenshots here showcasing your plugin in action]

= Requirements =

- WordPress 4.0 or higher
- WooCommerce 3.0 or higher
- An active account on Clientify

== Installation Instructions ==

1. Download the plugin zip file to your computer.
2. Log in to your WordPress admin panel.
3. Go to "Plugins" in the sidebar menu.
4. Click on "Add New" and then "Upload Plugin".
5. Select the plugin zip file you downloaded and click "Install Now".
6. Once installed, activate the plugin.
7. In the sidebar, you will find the "Clientify" option. Click on it to access the settings.
8. Enter your Clientify API Key and click the "Connect" button.
9. If the data is correct, you will receive a successful connection notification, and a new tab will open to the Clientify app. If there is an error, you will be notified of the issue.
10. If you want to disconnect the integration, you can do so at any time from the plugin settings.

== Usage ==

1. After successfully connecting your Clientify account, the plugin will automatically send information about orders, products, customers, and abandoned carts to your CRM.
2. Make sure WooCommerce is properly configured, and relevant events (such as completing a purchase or cart abandonment) trigger the appropriate actions in your store.

== FAQ (Frequently Asked Questions) ==

Q: What should I do if I get a connection error when trying to connect with Clientify?
A: Make sure the API Key you entered is correct. If the problem persists, please contact Clientify support at development@clientify.com.

Q: How can I disconnect the integration with Clientify?
A: Go to the Clientify plugin settings and click the "Disconnect" button. This will prevent data from being sent to your Clientify account.

== Support ==

For support, please contact the Clientify development team by sending an email to development@clientify.com.

== Contribution ==

Due to our current policies, we do not accept contributions from third parties.

== License ==

GPL used for WordPress plugins is GPL-2.0 (or later).











=== Plugin Name ===
Contributors: 
Tags: ecommerce, clientify
Requires at least: 5.8.7
Tested up to: 6.2.2
Stable tag: 6.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Here is a short description of the plugin.  This should be no more than 150 characters.  No markup here.

== Description ==

This is the long description.  No limit, and you can use Markdown (as well as in the following sections).

For backwards compatibility, if this section is missing, the full length of the short description will be used, and
Markdown parsed.

A few notes about the sections above:

*   "Contributors" is a comma separated list of wp.org/wp-plugins.org usernames
*   "Tags" is a comma separated list of tags that apply to the plugin
*   "Requires at least" is the lowest version that the plugin will work on
*   "Tested up to" is the highest version that you've *successfully used to test the plugin*. Note that it might work on
higher versions... this is just the highest one you've verified.
*   Stable tag should indicate the Subversion "tag" of the latest stable version, or "trunk," if you use `/trunk/` for
stable.

    Note that the `readme.txt` of the stable tag is the one that is considered the defining one for the plugin, so
if the `/trunk/readme.txt` file says that the stable tag is `4.3`, then it is `/tags/4.3/readme.txt` that'll be used
for displaying information about the plugin.  In this situation, the only thing considered from the trunk `readme.txt`
is the stable tag pointer.  Thus, if you develop in trunk, you can update the trunk `readme.txt` to reflect changes in
your in-development version, without having that information incorrectly disclosed about the current stable version
that lacks those changes -- as long as the trunk's `readme.txt` points to the correct stable tag.

    If no stable tag is provided, it is assumed that trunk is stable, but you should specify "trunk" if that's where
you put the stable version, in order to eliminate any doubt.

== Installation ==

This section describes how to install the plugin and get it working.

e.g.

1. Upload `clientify-addons.php` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Place `<?php do_action('plugin_name_hook'); ?>` in your templates

== Frequently Asked Questions ==

= A question that someone might have =

An answer to that question.

= What about foo bar? =

Answer to foo bar dilemma.

== Screenshots ==

1. This screen shot description corresponds to screenshot-1.(png|jpg|jpeg|gif). Note that the screenshot is taken from
the /assets directory or the directory that contains the stable readme.txt (tags or trunk). Screenshots in the /assets
directory take precedence. For example, `/assets/screenshot-1.png` would win over `/tags/4.3/screenshot-1.png`
(or jpg, jpeg, gif).
2. This is the second screen shot

== Changelog ==

= 1.0 =
* A change since the previous version.
* Another change.

= 0.5 =
* List versions from most recent at top to oldest at bottom.

== Upgrade Notice ==

= 1.0 =
Upgrade notices describe the reason a user should upgrade.  No more than 300 characters.

= 0.5 =
This version fixes a security related bug.  Upgrade immediately.

== Arbitrary section ==

You may provide arbitrary sections, in the same format as the ones above.  This may be of use for extremely complicated
plugins where more information needs to be conveyed that doesn't fit into the categories of "description" or
"installation."  Arbitrary sections will be shown below the built-in sections outlined above.

== A brief Markdown Example ==

Ordered list:

1. Some feature
1. Another feature
1. Something else about the plugin

Unordered list:

* something
* something else
* third thing

Here's a link to [WordPress](http://wordpress.org/ "Your favorite software") and one to [Markdown's Syntax Documentation][markdown syntax].
Titles are optional, naturally.

[markdown syntax]: http://daringfireball.net/projects/markdown/syntax
            "Markdown is what the parser uses to process much of the readme file"

Markdown uses email style notation for blockquotes and I've been told:
> Asterisks for *emphasis*. Double it up  for **strong**.

`<?php code(); // goes in backticks ?>`