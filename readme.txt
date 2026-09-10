=== Users Plus ===
Contributors: soydiloreto
Tags: users, login, passwordless, two-factor, passkeys
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 0.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

User fields, a front-end account area, passwordless sign-in, social login, two-step verification, passkeys and session control.

== Description ==

Everything about the people who use your site: the details you ask them for,
how they sign in, what they see of their own, and what they can do with it.
In most sites that gets solved again every time, with four plugins that do not
talk to each other. Here it lives once.

* **User fields** defined from the dashboard: name, type, whether it is
  required, where it goes, and who can change it and how many times. The ones
  WordPress already has — first and last name — are in the same list and follow
  the same rules.
* **An account area on the front end**: Home, Your details, Linked accounts,
  Security, Privacy and Notifications, with tabs on top or a menu down the
  side. Sections can be renamed, reordered, turned off and added; one of your
  own is a name, an address and a shortcode.
* **How people get in**: a link sent to their email with no password at all,
  username and password, or both. With control over what happens to the
  WordPress registration and to its profile screen.
* **Social login** with twelve providers, a step-by-step guide for each
  console, buttons with the real brand marks, and a live test before you turn
  one on.
* **Two-step verification**: a code by email, an authenticator app with a QR
  code, and backup codes. With a policy per role and per way in.
* **Passkeys** (WebAuthn), each one with a name of its own.
* **Sessions**: how long they last, where they are open and how to close them.
* **Privacy**: the export and erasure requests WordPress already knows how to
  handle, put where people look for them.

None of this depends on another plugin. What belongs to someone else — a
course, a membership, a forum — comes in through a filter or a shortcode.

= What it will not do =

Whatever an administrator turns off disappears from the front end, with no
second switch to remember. With no social provider enabled there is no
"Linked accounts" section at all; with neither data download nor account
deletion allowed there is no "Privacy" section.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate it from the Plugins screen.
3. Go to **Users+ → Account area** and pick the page that holds the
   `[users_plus_account]` shortcode.

== Frequently Asked Questions ==

= Does it work with any theme? =

Yes. It ships its own styles, its templates can be overridden from the theme
at `wp-content/themes/<theme>/users-plus/`, and its colours come
from CSS custom properties a site can redefine without copying a stylesheet.

= Does it work on multisite? =

Yes. Configuration is per site; users are network-wide, so anything that
grants access joins the person to the current site.

== Changelog ==

= 0.2.1 =
* First public release.
