<?php
/**
 * El paso a paso para dar de alta la aplicación en cada proveedor.
 *
 * Vive aparte de la tabla de proveedores a propósito. `users_dlx_plus_sso_providers()`
 * es protocolo —URLs, scopes, cómo se lee el perfil— y se consulta en cada
 * pedido, incluso antes de que WordPress cargue las traducciones. Esto es
 * documentación: sólo la mira una persona parada en el admin, y ahí sí se
 * puede traducir sin que WordPress avise que se pidió una traducción
 * demasiado temprano.
 *
 * Cada guía tiene:
 *   steps   Los pasos, en orden, con los nombres literales de los campos de
 *           esa consola. Sin "configurá la app": qué botón se toca.
 *   gotcha  Lo que hace perder una tarde. Vacío si no hay.
 *
 * @package UsersDlxPlus
 */

defined( 'ABSPATH' ) || exit;

/**
 * La guía de un proveedor.
 *
 * @param string $id Identificador del proveedor.
 * @return array{steps: array<int, string>, gotcha: string}
 */
function users_dlx_plus_sso_guide( string $id ): array {
	$guides = users_dlx_plus_sso_guides();

	$guide = $guides[ $id ] ?? array();

	return array(
		'steps'  => $guide['steps'] ?? array(),
		'gotcha' => $guide['gotcha'] ?? '',
	);
}

/**
 * Todas las guías.
 *
 * @return array<string, array{steps: array<int, string>, gotcha: string}>
 */
function users_dlx_plus_sso_guides(): array {
	$guides = array(

		'google'    => array(
			'steps'  => array(
				__( 'Open the Google Cloud console and pick a project in the selector at the top, or create one. Everything below happens inside that project.', 'users-dlx-plus' ),
				__( 'Go to “APIs & Services” → “OAuth consent screen”. Choose user type “External”, and fill in the app name, the support email and the developer contact email. Save.', 'users-dlx-plus' ),
				__( 'Still on the consent screen, under “Audience”, either add your own account under “Test users” or press “Publish app”. While the app is in testing, only the test users can sign in.', 'users-dlx-plus' ),
				__( 'Go to “APIs & Services” → “Credentials” → “Create credentials” → “OAuth client ID”.', 'users-dlx-plus' ),
				__( 'Application type: “Web application”. Give it a name — this one is only for you.', 'users-dlx-plus' ),
				__( 'Under “Authorized redirect URIs” press “Add URI” and paste the redirect URL from above. Under “Authorized JavaScript origins” put just the domain, with no path.', 'users-dlx-plus' ),
				__( 'Press “Create”. Google shows the client ID and the client secret in a dialog — copy both before closing it.', 'users-dlx-plus' ),
			),
			'gotcha' => __( 'A brand new project needs no API enabled: sign-in works with the OAuth client alone. What does stop it is leaving the app in “Testing” and forgetting to add the person as a test user — they get “access blocked”.', 'users-dlx-plus' ),
		),

		'microsoft' => array(
			'steps'  => array(
				__( 'Open the Microsoft Entra admin center and go to “Identity” → “Applications” → “App registrations” → “New registration”.', 'users-dlx-plus' ),
				__( 'Give it a name — this is what people see on the consent screen.', 'users-dlx-plus' ),
				__( 'Supported account types: “Accounts in any organizational directory and personal Microsoft accounts”. Any narrower option locks out @outlook.com and @hotmail.com addresses.', 'users-dlx-plus' ),
				__( 'Under “Redirect URI” pick the platform “Web” and paste the redirect URL from above. Then press “Register”.', 'users-dlx-plus' ),
				__( 'On the overview page copy the “Application (client) ID”.', 'users-dlx-plus' ),
				__( 'Go to “Certificates & secrets” → “New client secret”, set an expiry, and press “Add”.', 'users-dlx-plus' ),
				__( 'Copy the secret’s “Value”, not its “Secret ID”. The value is shown once and never again.', 'users-dlx-plus' ),
			),
			'gotcha' => __( 'The openid, email and profile permissions are already there by default — you do not have to add anything under “API permissions”.', 'users-dlx-plus' ),
		),

		'linkedin'  => array(
			'steps'  => array(
				__( 'Open the LinkedIn developer portal and press “Create app”.', 'users-dlx-plus' ),
				__( 'LinkedIn asks for a company page: paste the page URL and press “Verify”. It opens a link that an admin of that page has to confirm. Without a verified page the app cannot be created.', 'users-dlx-plus' ),
				__( 'Go to the “Products” tab and request “Sign In with LinkedIn using OpenID Connect”. It is granted automatically, but it takes a minute to show as added.', 'users-dlx-plus' ),
				__( 'Go to the “Auth” tab. Under “OAuth 2.0 settings” → “Authorized redirect URLs for your app” press the pencil, then “Add redirect URL”, and paste the redirect URL from above.', 'users-dlx-plus' ),
				__( 'On that same tab copy the “Client ID” and press “Generate” next to “Primary Client Secret” to reveal it.', 'users-dlx-plus' ),
			),
			'gotcha' => __( 'Until the OpenID Connect product shows as added, the sign-in fails with “unauthorized_scope_error”. Check the “Auth” tab: the scopes openid, profile and email have to be listed.', 'users-dlx-plus' ),
		),

		'twitter'   => array(
			'steps'  => array(
				__( 'Open the X developer portal. If you have never used it, it asks you to sign up for the free tier first: answer what you are building and accept the terms.', 'users-dlx-plus' ),
				__( 'Create a project, and inside it an app. The app name has to be unique across all of X.', 'users-dlx-plus' ),
				__( 'In the app, go to “User authentication settings” and press “Set up”.', 'users-dlx-plus' ),
				__( 'App permissions: “Read”. That is enough to sign people in.', 'users-dlx-plus' ),
				__( 'Type of App: “Web App, Automated App or Bot”. This is the one that issues a client secret; the other types do not.', 'users-dlx-plus' ),
				__( 'Under “App info” paste the redirect URL from above into “Callback URI / Redirect URL”, and your site’s address into “Website URL”. Save.', 'users-dlx-plus' ),
				__( 'X shows the “OAuth 2.0 Client ID” and “Client Secret” once — copy both. They are NOT the “API Key” and “API Key Secret”, which belong to the old OAuth 1.1.', 'users-dlx-plus' ),
			),
			'gotcha' => __( 'X never gives out the email address. Whoever signs in with X gets an account built from their username, and the site asks for a real address afterwards. If you want the email up front, this is not the network for it.', 'users-dlx-plus' ),
		),

		'facebook'  => array(
			'steps'  => array(
				__( 'Open the Meta developer console and press “Create app”.', 'users-dlx-plus' ),
				__( 'Use case: “Authenticate and request data from users with Facebook Login”. Then pick or create the business portfolio it belongs to.', 'users-dlx-plus' ),
				__( 'In the app, go to “Facebook Login” → “Settings” in the left menu.', 'users-dlx-plus' ),
				__( 'Paste the redirect URL from above into “Valid OAuth Redirect URIs” and save. Leave “Client OAuth login” and “Web OAuth login” on.', 'users-dlx-plus' ),
				__( 'Go to “App settings” → “Basic” and copy the “App ID” and the “App secret” (press “Show”).', 'users-dlx-plus' ),
				__( 'On that same page fill in the “Privacy Policy URL”: Facebook will not let the app go live without it.', 'users-dlx-plus' ),
				__( 'Switch the toggle at the top from “Development” to “Live”.', 'users-dlx-plus' ),
			),
			'gotcha' => __( 'While the app is in “Development”, only people listed in “App roles” can sign in — everyone else gets an error page. The live test below works with your own account before you flip it.', 'users-dlx-plus' ),
		),

		'github'    => array(
			'steps'  => array(
				__( 'Open GitHub’s developer settings and go to “OAuth Apps” → “New OAuth App”. (The other list, “GitHub Apps”, is for something else.)', 'users-dlx-plus' ),
				__( 'Application name: what people see when they authorise. Homepage URL: your site’s address.', 'users-dlx-plus' ),
				__( 'Paste the redirect URL from above into “Authorization callback URL”.', 'users-dlx-plus' ),
				__( 'Press “Register application”. The client ID is on the page.', 'users-dlx-plus' ),
				__( 'Press “Generate a new client secret” and copy it right away — GitHub hides it as soon as you leave the page.', 'users-dlx-plus' ),
			),
			'gotcha' => __( 'GitHub hides the email of anyone who turned on “Keep my email address private”. The plugin asks for the user:email scope, which returns the verified address anyway.', 'users-dlx-plus' ),
		),

		'wordpress' => array(
			'steps'  => array(
				__( 'Open the WordPress.com developer console and press “Create New Application”. It signs you in with your WordPress.com account.', 'users-dlx-plus' ),
				__( 'Fill in the name, a short description and the website URL.', 'users-dlx-plus' ),
				__( 'Paste the redirect URL from above into “Redirect URL”.', 'users-dlx-plus' ),
				__( 'Type: “Web Client”.', 'users-dlx-plus' ),
				__( 'Save, then open the app again to copy the “Client ID” and the “Client Secret”.', 'users-dlx-plus' ),
			),
			'gotcha' => __( 'The redirect URL has to match character for character. WordPress.com rejects anything that differs, even a trailing slash.', 'users-dlx-plus' ),
		),

		'yahoo'     => array(
			'steps'  => array(
				__( 'Open the Yahoo developer console and press “Create an App”, signing in with a Yahoo account.', 'users-dlx-plus' ),
				__( 'Application Name: what people see when they authorise.', 'users-dlx-plus' ),
				__( 'Application Type: “Web Application”.', 'users-dlx-plus' ),
				__( 'Paste the redirect URL from above into “Redirect URI(s)”. Yahoo only takes https, and refuses localhost.', 'users-dlx-plus' ),
				__( 'API Permissions: tick “Profiles (Social Directory)” with “Read Public”, and under “OpenID Connect Permissions” tick “email” and “profile”.', 'users-dlx-plus' ),
				__( 'Press “Create App”. Copy the “Client ID (Consumer Key)” and the “Client Secret (Consumer Secret)”.', 'users-dlx-plus' ),
			),
			'gotcha' => __( 'Without the OpenID Connect permissions ticked, Yahoo signs the person in but returns a profile with no email, and the account cannot be created.', 'users-dlx-plus' ),
		),

		'twitch'    => array(
			'steps'  => array(
				__( 'Turn on two-factor authentication on the Twitch account first: the console refuses to register an app without it.', 'users-dlx-plus' ),
				__( 'Open the Twitch developer console and press “Register Your Application”.', 'users-dlx-plus' ),
				__( 'Name: it has to be unique across all of Twitch, so “ConoSurTech Login” beats “Login”.', 'users-dlx-plus' ),
				__( 'Paste the redirect URL from above into “OAuth Redirect URLs” and press “Add”.', 'users-dlx-plus' ),
				__( 'Category: “Website Integration”. Client Type: “Confidential”.', 'users-dlx-plus' ),
				__( 'Press “Create”, then “Manage” on the app you just made.', 'users-dlx-plus' ),
				__( 'The client ID is there. Press “New Secret” and copy the secret — it is shown once.', 'users-dlx-plus' ),
			),
			'gotcha' => __( 'Client Type has to be “Confidential”. A “Public” app gets no secret, and the token exchange this plugin does fails.', 'users-dlx-plus' ),
		),

		'discord'   => array(
			'steps'  => array(
				__( 'Open the Discord developer portal and press “New Application”. Give it a name and accept the terms.', 'users-dlx-plus' ),
				__( 'Go to “OAuth2” in the left menu.', 'users-dlx-plus' ),
				__( 'Under “Redirects” press “Add Redirect”, paste the redirect URL from above, and press “Save Changes” at the bottom.', 'users-dlx-plus' ),
				__( 'On that same page copy the “Client ID”.', 'users-dlx-plus' ),
				__( 'Press “Reset Secret” to reveal the “Client Secret” and copy it.', 'users-dlx-plus' ),
			),
			'gotcha' => __( 'Discord only hands over the email when the account has it verified. An unverified account signs in and comes back with no address.', 'users-dlx-plus' ),
		),

		'gitlab'    => array(
			'steps'  => array(
				__( 'On GitLab, open your avatar menu → “Edit profile” → “Applications”. (For a whole group, the same screen exists under the group’s Settings.)', 'users-dlx-plus' ),
				__( 'Press “Add new application” and give it a name.', 'users-dlx-plus' ),
				__( 'Paste the redirect URL from above into “Redirect URI”, one per line.', 'users-dlx-plus' ),
				__( 'Leave “Confidential” ticked.', 'users-dlx-plus' ),
				__( 'Scopes: tick “openid”, “email” and “profile”.', 'users-dlx-plus' ),
				__( 'Press “Save application”. Copy the “Application ID” and the “Secret” — the secret is shown once.', 'users-dlx-plus' ),
			),
			'gotcha' => __( 'This is for gitlab.com. A self-hosted GitLab has the same screens but different URLs, and the plugin points at gitlab.com.', 'users-dlx-plus' ),
		),

		'amazon'    => array(
			'steps'  => array(
				__( 'Open the Login with Amazon console and press “Create a New Security Profile”.', 'users-dlx-plus' ),
				__( 'Fill in the name, the description, and a privacy policy URL. All three are required.', 'users-dlx-plus' ),
				__( 'Save. Back on the list, open the gear menu on your profile → “Web Settings” → “Edit”.', 'users-dlx-plus' ),
				__( 'Paste the redirect URL from above into “Allowed Return URLs”. In “Allowed Origins” put just the domain, with no path.', 'users-dlx-plus' ),
				__( 'Save, then copy the “Client ID” and press “Show Secret” to copy the “Client Secret”.', 'users-dlx-plus' ),
			),
			'gotcha' => __( 'The return URL lives under “Web Settings”, not on the profile’s main page — that is the step everyone misses.', 'users-dlx-plus' ),
		),
	);

	/**
	 * Filtra las guías de alta de cada proveedor.
	 *
	 * @param array<string, array{steps: array<int, string>, gotcha: string}> $guides
	 */
	return apply_filters( 'users_dlx_plus_sso_guides', $guides );
}
