<?php
/**
 * La tabla de proveedores de login social.
 *
 * OAuth 2 es el mismo baile en todos: mandar a autorizar, volver con un
 * código, cambiarlo por un token, pedir el perfil. Lo único propio de cada
 * proveedor son cuatro URLs, un scope y cómo se lee el perfil que devuelve.
 * Eso es una tabla, no una clase por proveedor: agregar uno nuevo es agregar
 * una fila acá.
 *
 * Sólo entran los que funcionan con este flujo. Quedan afuera, a propósito:
 *
 *   - **Apple**: el secreto de cliente es un JWT firmado con ES256 que hay que
 *     regenerar cada seis meses, y la respuesta vuelve por POST (form_post).
 *     Es otro flujo, no una fila más.
 *   - **Steam**: no usa OAuth 2 sino OpenID 2.0, que es un protocolo distinto
 *     y además no devuelve el correo.
 *
 * Un botón que no anda es peor que no tener el botón.
 *
 * @package UsersPlus
 */

defined( 'ABSPATH' ) || exit;

/**
 * Todos los proveedores que el plugin sabe manejar.
 *
 * Campos de cada uno:
 *   name       Cómo se llama para la gente.
 *   color      El color de su marca, para la tarjeta y el botón.
 *   authorize  URL adonde se manda a la persona.
 *   token      URL donde se cambia el código por un token.
 *   profile    URL de donde se lee el perfil.
 *   scope      Los permisos que se piden.
 *   extra      Parámetros sueltos que pide ese proveedor.
 *   pkce       Si exige PKCE (X lo exige; al resto no le molesta).
 *   map        La función que lee su respuesta.
 *   console    Dónde se crea la aplicación.
 *   guide      La documentación del proveedor.
 *
 * @return array<string, array<string, mixed>>
 */
function users_plus_sso_providers(): array {
	$providers = array(
		'google'    => array(
			'name'      => 'Google',
			'color'     => '#EA4335',
			'authorize' => 'https://accounts.google.com/o/oauth2/v2/auth',
			'token'     => 'https://oauth2.googleapis.com/token',
			'profile'   => 'https://openidconnect.googleapis.com/v1/userinfo',
			'scope'     => 'openid email profile',
			'extra'     => array( 'prompt' => 'select_account' ),
			'pkce'      => false,
			'map'       => 'users_plus_sso_map_oidc',
			'console'   => 'https://console.cloud.google.com/apis/credentials',
			'guide'     => 'https://developers.google.com/identity/openid-connect/openid-connect',
		),
		'microsoft' => array(
			'name'      => 'Microsoft',
			'color'     => '#0067B8',
			'authorize' => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
			'token'     => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
			'profile'   => 'https://graph.microsoft.com/oidc/userinfo',
			'scope'     => 'openid email profile',
			'extra'     => array(),
			'pkce'      => false,
			'map'       => 'users_plus_sso_map_oidc',
			'console'   => 'https://entra.microsoft.com/',
			'guide'     => 'https://learn.microsoft.com/entra/identity-platform/v2-protocols-oidc',
		),
		'linkedin'  => array(
			'name'      => 'LinkedIn',
			'color'     => '#0A66C2',
			'authorize' => 'https://www.linkedin.com/oauth/v2/authorization',
			'token'     => 'https://www.linkedin.com/oauth/v2/accessToken',
			'profile'   => 'https://api.linkedin.com/v2/userinfo',
			'scope'     => 'openid profile email',
			'extra'     => array(),
			'pkce'      => false,
			'map'       => 'users_plus_sso_map_oidc',
			'console'   => 'https://www.linkedin.com/developers/apps',
			'guide'     => 'https://learn.microsoft.com/linkedin/consumer/integrations/self-serve/sign-in-with-linkedin-v2',
		),
		'twitter'   => array(
			'name'      => 'X (Twitter)',
			'color'     => '#0F1419',
			'authorize' => 'https://twitter.com/i/oauth2/authorize',
			'token'     => 'https://api.twitter.com/2/oauth2/token',
			'profile'   => 'https://api.twitter.com/2/users/me?user.fields=name,username',
			'scope'     => 'tweet.read users.read',
			'extra'     => array(),
			// X exige PKCE y no devuelve el correo: la cuenta se crea con un
			// correo derivado del usuario, o se vincula desde el perfil.
			'pkce'      => true,
			'map'       => 'users_plus_sso_map_twitter',
			'console'   => 'https://developer.twitter.com/en/portal/dashboard',
			'guide'     => 'https://docs.x.com/resources/fundamentals/authentication/oauth-2-0/authorization-code',
		),
		'facebook'  => array(
			'name'      => 'Facebook',
			'color'     => '#1877F2',
			'authorize' => 'https://www.facebook.com/v19.0/dialog/oauth',
			'token'     => 'https://graph.facebook.com/v19.0/oauth/access_token',
			'profile'   => 'https://graph.facebook.com/me?fields=id,email,first_name,last_name',
			'scope'     => 'email',
			'extra'     => array(),
			'pkce'      => false,
			'map'       => 'users_plus_sso_map_facebook',
			'console'   => 'https://developers.facebook.com/apps/',
			'guide'     => 'https://developers.facebook.com/docs/facebook-login/guides/advanced/manual-flow',
		),
		'github'    => array(
			'name'      => 'GitHub',
			'color'     => '#24292F',
			'authorize' => 'https://github.com/login/oauth/authorize',
			'token'     => 'https://github.com/login/oauth/access_token',
			'profile'   => 'https://api.github.com/user',
			'scope'     => 'read:user user:email',
			'extra'     => array(),
			'pkce'      => false,
			'map'       => 'users_plus_sso_map_github',
			'console'   => 'https://github.com/settings/developers',
			'guide'     => 'https://docs.github.com/apps/oauth-apps/building-oauth-apps/authorizing-oauth-apps',
		),
		'wordpress' => array(
			'name'      => 'WordPress.com',
			'color'     => '#117AC9',
			'authorize' => 'https://public-api.wordpress.com/oauth2/authorize',
			'token'     => 'https://public-api.wordpress.com/oauth2/token',
			'profile'   => 'https://public-api.wordpress.com/rest/v1/me',
			'scope'     => 'auth',
			'extra'     => array(),
			'pkce'      => false,
			'map'       => 'users_plus_sso_map_wordpress',
			'console'   => 'https://developer.wordpress.com/apps/',
			'guide'     => 'https://developer.wordpress.com/docs/oauth2/',
		),
		'yahoo'     => array(
			'name'      => 'Yahoo',
			'color'     => '#6001D2',
			'authorize' => 'https://api.login.yahoo.com/oauth2/request_auth',
			'token'     => 'https://api.login.yahoo.com/oauth2/get_token',
			'profile'   => 'https://api.login.yahoo.com/openid/v1/userinfo',
			'scope'     => 'openid email profile',
			'extra'     => array(),
			'pkce'      => false,
			'map'       => 'users_plus_sso_map_oidc',
			'console'   => 'https://developer.yahoo.com/apps/',
			'guide'     => 'https://developer.yahoo.com/oauth2/guide/openid_connect/',
		),
		'twitch'    => array(
			'name'      => 'Twitch',
			'color'     => '#9146FF',
			'authorize' => 'https://id.twitch.tv/oauth2/authorize',
			'token'     => 'https://id.twitch.tv/oauth2/token',
			// El endpoint OIDC y no /helix/users: éste anda con el Bearer solo,
			// el otro además pide la cabecera Client-Id.
			'profile'   => 'https://id.twitch.tv/oauth2/userinfo',
			'scope'     => 'openid user:read:email',
			'extra'     => array( 'claims' => '{"userinfo":{"email":null,"preferred_username":null}}' ),
			'pkce'      => false,
			'map'       => 'users_plus_sso_map_oidc',
			'console'   => 'https://dev.twitch.tv/console/apps',
			'guide'     => 'https://dev.twitch.tv/docs/authentication/getting-tokens-oidc/',
		),
		'discord'   => array(
			'name'      => 'Discord',
			'color'     => '#5865F2',
			'authorize' => 'https://discord.com/oauth2/authorize',
			'token'     => 'https://discord.com/api/oauth2/token',
			'profile'   => 'https://discord.com/api/users/@me',
			'scope'     => 'identify email',
			'extra'     => array(),
			'pkce'      => false,
			'map'       => 'users_plus_sso_map_discord',
			'console'   => 'https://discord.com/developers/applications',
			'guide'     => 'https://discord.com/developers/docs/topics/oauth2',
		),
		'gitlab'    => array(
			'name'      => 'GitLab',
			'color'     => '#FC6D26',
			'authorize' => 'https://gitlab.com/oauth/authorize',
			'token'     => 'https://gitlab.com/oauth/token',
			'profile'   => 'https://gitlab.com/oauth/userinfo',
			'scope'     => 'openid email profile',
			'extra'     => array(),
			'pkce'      => false,
			'map'       => 'users_plus_sso_map_oidc',
			'console'   => 'https://gitlab.com/-/profile/applications',
			'guide'     => 'https://docs.gitlab.com/ee/integration/openid_connect_provider.html',
		),
		'amazon'    => array(
			'name'      => 'Amazon',
			'color'     => '#FF9900',
			'authorize' => 'https://www.amazon.com/ap/oa',
			'token'     => 'https://api.amazon.com/auth/o2/token',
			'profile'   => 'https://api.amazon.com/user/profile',
			'scope'     => 'profile',
			'extra'     => array(),
			'pkce'      => false,
			'map'       => 'users_plus_sso_map_amazon',
			'console'   => 'https://developer.amazon.com/loginwithamazon/console/site/lwa/overview.html',
			'guide'     => 'https://developer.amazon.com/docs/login-with-amazon/web-docs.html',
		),
	);

	/**
	 * Filtra los proveedores de login social.
	 *
	 * @param array<string, array<string, mixed>> $providers
	 */
	return apply_filters( 'users_plus_sso_providers', $providers );
}
