# Copilot custom instructions — Users+

This file is the project-wide context for GitHub Copilot (Code Review, Chat,
Coding Agent, and any other surface that reads
`.github/copilot-instructions.md`). It encodes domain knowledge, conventions
and review priorities specific to this codebase. It is **not** generic
WordPress advice — it reflects how this plugin is actually written.

When you review a pull request, follow these rules. When in doubt, prefer the
project's existing patterns over textbook WordPress patterns.

---

## What this repo is

A WordPress plugin that owns everything about the people who use a site: the
fields they are asked for, how they sign in, the account area they see on the
front end, their sessions and their data.

**The distinguishing decision** is that the plugin knows nothing about the
site it runs on. It does not know what a course is, or a membership, or a
forum. Anything that belongs to another domain enters through a filter
(`users_dlx_plus_sections`, `users_dlx_plus_summaries`, `users_dlx_plus_notification_prefs`) or through a
shortcode pasted into a section. A pull request that teaches the plugin about
LifterLMS, WooCommerce or bbPress is going the wrong way — the only exception
is a `function_exists()`-guarded bridge, and there is exactly one today
(`bbp_get_user_profile_url()` in `includes/handle.php`).

The second decision worth knowing: **what an administrator turns off
disappears from the front end, with no second switch to remember.** Sections
declare an `available` callback; with no social provider enabled there is no
"Linked accounts" section at all. A feature added without that wiring will
show an empty screen to somebody.

---

## Architecture quick-reference

Procedural, no classes, no namespace. Every file in `includes/` is
independent and only registers hooks; they are loaded in alphabetical order
by a `glob()` in the main plugin file, so **nothing may depend on load
order** — if a file needs another to have run, that is a hook, not an
ordering assumption.

| Area | Files |
|---|---|
| Options and their defaults | `options.php` |
| User fields (definition, values, edit policy) | `fields.php`, `fields-forms.php` |
| Account area, sections registry | `account.php`, `account-sections.php` |
| Sign-in: link, password, passwordless mode | `login.php`, `passwordless.php` |
| Two-step verification | `auth.php`, `auth-email.php`, `auth-totp.php` |
| Passkeys (WebAuthn) | `auth-passkeys.php` |
| Social login | `sso*.php` |
| Sessions | `sessions.php` |
| Notifications the plugin sends itself | `notify.php` |
| Admin screens | `admin*.php` |
| Data migrations | `upgrade.php` |

Templates live in `templates/` and are overridable from the active theme at
`wp-content/themes/<theme>/users-dlx-plus/<path>.php`, resolved by
`users_dlx_plus_template()`. Styling is driven by `--users-dlx-plus-*` custom properties so a site
can restyle the plugin by redefining tokens, without copying its stylesheet.

---

## Hard rules — please flag any violation

### Security

- **All AJAX endpoints** verify a nonce (`check_ajax_referer`) and, when the
  action is not part of signing in, that there is a session. The two passkey
  login steps deliberately do not require a session — they exist to open one.
- **All `$_POST` / `$_GET` input** must be unslashed and sanitized:
  `sanitize_text_field( wp_unslash( $_POST['x'] ?? '' ) )`, `sanitize_key`,
  `sanitize_email`, `wp_kses_post` for HTML. Raw superglobals are a defect.
- **All output** must be escaped: `esc_html`, `esc_attr`, `esc_url`,
  `esc_textarea`. A template that echoes a variable unescaped is a defect.
- **All SQL** must use `$wpdb->prepare()`. The only unprepared queries are the
  two prefix migrations in `upgrade.php`, which take no user input and are
  documented as such.
- **Secrets are never logged and never stored in the clear.** Two-step codes
  and backup codes are stored hashed (`wp_hash`); the sign-in token likewise.
  A pull request that stores any of them readable is a defect, not a
  simplification.
- **A single-use thing must be single use.** Sign-in links, two-step codes,
  backup codes and WebAuthn challenges are all consumed on first use. Removing
  the delete-after-use is a security regression even when it "fixes" a retry.

### Coherence — the rule that is specific to this plugin

- Anything shown to a person must be **true at the moment it is shown**. If a
  notice says the second step is not being asked for, that has to hold for
  every way into the site the administrator left open — not just the one the
  author had in mind. `users_dlx_plus_2fa_ways()` exists because that notice was wrong.
- A new section must declare `available` and `why` if it can ever have nothing
  to show.
- A new setting that hides something on the front end must actually hide it.
  Half-applied settings are the failure mode this plugin is trying to avoid.

### WordPress conventions

- All user-facing strings go through translation functions with the text
  domain `users-dlx-plus`. Translations ship with the plugin.
- Multisite-aware: configuration is per-site. Users are network-wide, so
  anything that gives access calls `users_dlx_plus_join_site()`.
- HTTP calls use `wp_remote_*` with an explicit `timeout`. Never raw cURL.
- Every `.php` file starts with `defined( 'ABSPATH' ) || exit;`.
- **PHP 8.0 minimum**, WordPress 6.0 minimum.

### Data

- Renaming an option or a user meta key **requires a migration** in
  `upgrade.php`. The plugin already carries one (the `usmw_` → `users_dlx_plus_`
  rename); it is the reference for how to do the next one.
- Field keys are user-visible configuration: once a field exists, its key does
  not change, because the key is also the meta key holding everybody's answer.

---

## Style — please DO NOT comment on

Save your review tokens for things that matter.

- **Yoda conditions ARE used** in comparisons against literals
  (`'' === $value`), following WPCS. Don't suggest the swap.
- **Spanish comments and English code.** Identifiers, hooks and strings are in
  English; the comments explaining *why* are in Spanish, and that is
  deliberate — the maintainer reads them. Don't suggest translating them.
- **Comments explain decisions, not mechanics.** A comment that says what the
  next line does is noise and gets removed; one that says why the obvious
  alternative was rejected stays. Don't ask for more of the first kind.
- **Procedural code with an `users_dlx_plus_` prefix** is the convention. Don't suggest
  wrapping it in classes.

---

## What Copilot should actively look for

- **Missing escaping on output**, especially in `templates/`.
- **Missing unslash + sanitize on input**, especially in new admin handlers.
- **A new screen or panel that shows something the settings say is off.**
- **A new section without `available` / `why`** that can render empty.
- **A new option that no screen exposes**, or a screen control that saves
  nothing.
- **Renaming a stored key without a migration.**
- **Anything that makes the plugin depend on another plugin** without a
  `function_exists()` guard.
- **New strings not wrapped in a translation function.**
- **A single-use token that stops being single use.**
- **`$wpdb` queries inside loops** — suggest batching.

---

## When you're not sure

Open a question in the review. Don't guess. Reference the existing pattern by
file and function. The maintainer
([Pablo Di Loreto](https://pablodiloreto.com/)) is the final reviewer of every
merge.

---

## Updates to this file

When the architecture, conventions or rules change, update this file in the
same pull request. A stale `copilot-instructions.md` is worse than none: it
makes Copilot give confidently-wrong reviews.
