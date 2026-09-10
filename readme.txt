=== Users+ ===
Contributors: soydiloreto
Tags: users, login, passwordless, two-factor, passkeys
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Users Plus for WordPress: user fields, an account area, passwordless sign-in, social login, two-step verification, passkeys and session control.

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
   `[users_dlx_plus_account]` shortcode.

== Frequently Asked Questions ==

= Does it work with any theme? =

Yes. It ships its own styles, its templates can be overridden from the theme
at `wp-content/themes/<theme>/users-dlx-plus/`, and its colours come
from CSS custom properties a site can redefine without copying a stylesheet.

= Does it work on multisite? =

Yes. Configuration is per site; users are network-wide, so anything that
grants access joins the person to the current site.

== Changelog ==

= 1.0.0 =
First public release.

* User fields with their own admin screen: text, email, phone, date, select, checkbox and country, plus WordPress's own first and last name.
* Per-field edit policy — read only, editable, or editable a fixed number of times — and a switch to allow or block access to WordPress's own profile screen.
* Front-end account area with default sections out of the box, in a horizontal or vertical layout, driven entirely by what is enabled in the admin.
* Overridable templates and CSS custom properties, so a theme can restyle it without touching the plugin.
* Passwordless sign-in by e-mail link, optionally alongside or instead of the password form.
* Social login for twelve providers, with path-based callback URLs that every provider accepts.
* Two-step verification by e-mail code or authenticator app, with its own policy per sign-in method.
* Passkeys with friendly names, which need no second step of their own.
* Session control: see where an account is signed in and close any session.
* Public names, with a live availability check against the same validation used on save.
* Avatars: uploaded photo, Gravatar or generated initials, each one switchable.
* Data export and account deletion from the front end, each one switchable.
* Multisite aware.
* Spanish translation included.
