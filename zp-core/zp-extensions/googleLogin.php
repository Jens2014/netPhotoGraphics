<?php

/**
 *
 * The plugin provides login to ZenPhoto20 via Google OAuth2 protocol.
 *
 * You must configure the plugin with your Google Developer credentials. You will
 * need an <b><i>API key</i></b> as well as an OAuth2 <b><i>Client ID</i></b> and <b><i>Client Secret</i></b>.
 * (The <b><i>API key</i></b> is shared with the <var>googleMap</var> plugin, so you may already have one.)
 * You can obtain these from the
 * {@link https://console.developers.google.com/apis/dashboard Google Developers Console}
 *
 * Your <i>OAuth2 client ID</i> will need an <i>Authorized redirect URI</i> that
 * points to <var>%FULLWEBPATH%/%ZENFOLDER%/%PLUGIN_FOLDER%/googleLogin/user_authentication.php</var>
 *
 * The gmail address supplied by Google OAuth2 will become the user's <i>user ID</i>
 * (and his email address.) If this gmail address does not exist as a ZenPhoto20 user,
 * a new user will be created. The user will be assigned to the group indicated by
 * the plugin's options. If <var>Notify</var> option is checked an e-mail will be sent to
 * the site administrator informing him of the new user.
 *
 * @author Stephen Billard (sbillard)
 *
 * @package plugins
 * @subpackage users
 */
$plugin_is_filter = 900 | CLASS_PLUGIN;
$plugin_description = gettext("Handles logon via the user's <em>Google</em> account.");
$plugin_author = "Stephen Billard (sbillard)";


$option_interface = 'googleLogin';
zp_register_filter('alt_login_handler', 'googleLogin::alt_login_handler');
zp_register_filter('edit_admin_custom_data', 'googleLogin::edit_admin');

zp_session_start();

/**
 * Option class
 *
 */
class googleLogin {

	/**
	 * Option instantiation
	 */
	function __construct() {
		global $_zp_authority;
		setOptionDefault('googleLogin_group', 'viewers');
		setOptionDefault('googleLogin_ClientID', '');
		setOptionDefault('googleLogin_ClientSecret', '');
		$mailinglist = $_zp_authority->getAdminEmail(ADMIN_RIGHTS);
		if (count($mailinglist) == 0) { //	no one to send the notice to!
			setOption('register_user_notify', 0);
		} else {
			setOptionDefault('register_user_notify', 1);
		}
	}

	/**
	 * Provides option list
	 */
	function getOptionsSupported() {
		global $_zp_authority;
		$admins = $_zp_authority->getAdministrators('groups');
		$ordered = array();
		foreach ($admins as $key => $admin) {
			if ($admin['name'] == 'group' && $admin['rights'] && !($admin['rights'] & ADMIN_RIGHTS)) {
				$ordered[$admin['user']] = $admin['user'];
			}
		}

		$options = array(gettext('Assign user to') => array('key' => 'googleLogin_group', 'type' => OPTION_TYPE_SELECTOR,
						'order' => 0,
						'selections' => $ordered,
						'desc' => gettext('The user group to which to map the user.')),
				gettext('OAuth Client ID') => array('key' => 'googleLogin_ClientID', 'type' => OPTION_TYPE_TEXTBOX,
						'order' => 1,
						'desc' => gettext('This is your Google OAuth Client ID.')),
				gettext('OAuth Client Secret') => array('key' => 'googleLogin_ClientSecret', 'type' => OPTION_TYPE_TEXTBOX,
						'order' => 2,
						'desc' => gettext('This is your Google OAuth Client Secret.')),
				gettext('API key') . '&dagger;' => array('key' => 'gmap_map_api_key', 'type' => OPTION_TYPE_TEXTBOX,
						'order' => 3,
						'desc' => gettext('This is your Google Developer API key.')),
				gettext('Notify*') => array('key' => 'register_user_notify', 'type' => OPTION_TYPE_CHECKBOX,
						'order' => 7,
						'desc' => gettext('If checked, an e-mail will be sent to the gallery admin when a new user has verified his registration. '))
		);

		$mailinglist = $_zp_authority->getAdminEmail(ADMIN_RIGHTS);
		if (count($mailinglist) == 0) { //	no one to send the notice to!
			$options[gettext('Notify*')]['disabled'] = true;
			$options[gettext('Notify*')]['desc'] .= ' ' . gettext('Of course there must be some Administrator with an e-mail address for this option to make sense!');
		}

		$options['note'] = array('key' => 'menu_truncate_note',
				'type' => OPTION_TYPE_NOTE,
				'order' => 9,
				'desc' => gettext('<p class="notebox">*<strong>Note:</strong> This option is shared amoung <em>federated_logon</em>, <em>googleLogin</em> and <em>register_user</em>.</p>'));
		$options['note2'] = array('key' => 'menu_truncate_note',
				'type' => OPTION_TYPE_NOTE,
				'order' => 8,
				'desc' => gettext('<p class="notebox">&dagger;<strong>Note:</strong> This option is shared with <em>googleMap</em>.</p>'));
		return $options;
	}

	/**
	 * Handles the custom option $option
	 * @param $option
	 * @param $currentValue
	 */
	function handleOption($option, $currentValue) {

	}

	/**
	 * Provides a list of alternate handlers for logon
	 * @param $handler_list
	 */
	static function alt_login_handler($handler_list) {
		$link = FULLWEBPATH . '/' . ZENFOLDER . '/' . PLUGIN_FOLDER . '/googleLogin/user_authentication.php';
		$handler_list['Google'] = array('script' => $link, 'params' => array());
		return $handler_list;
	}

	/**
	 * Common logon handler.
	 * Will log the user on if he exists. Otherwise it will create a user accoung and log
	 * on that account.
	 *
	 * Redirects into zenphoto on success presuming there is a redirect link.
	 *
	 * @param $user
	 * @param $email
	 * @param $name
	 * @param $redirect
	 */
	static function credentials($user, $email, $name, $redirect) {
		global $_zp_authority;

		$userobj = $_zp_authority->getAnAdmin(array('`user`=' => $user, '`valid`=' => 1));
		$more = false;
		if ($userobj) { //	update if changed
			$save = false;
			if (!empty($email) && $email != $userobj->getEmail()) {
				$save = true;
				$userobj->setEmail($email);
			}
			if (!empty($name) && $name != $userobj->getName()) {
				$save = true;
				$userobj->setName($name);
			}
			$credentials = array('auth' => 'googleOAuth', 'user' => 'user', 'email' => 'email');
			if ($name)
				$credentials['name'] = 'name';
			if ($credentials != $userobj->getCredentials()) {
				$save = true;
				$userobj->setCredentials($credentials);
			}
			if ($save) {
				$userobj->save();
			}
		} else { //	User does not exist, create him
			$groupname = getOption('googleLogin_group');
			$groupobj = $_zp_authority->getAnAdmin(array('`user`=' => $groupname, '`valid`=' => 0));
			if ($groupobj) {
				$group = NULL;
				if ($groupobj->getName() != 'template') {
					$group = $groupname;
				}
				$userobj = Zenphoto_Authority::newAdministrator('');
				$userobj->transient = false;
				$userobj->setUser($user);
				$credentials = array('auth' => 'googleOAuth', 'user' => 'user', 'email' => 'email');
				if ($name) {
					$credentials['name'] = 'name';
				}
				$userobj->setCredentials($credentials);

				$userobj->setName($name);
				$userobj->setPass($user . HASH_SEED . gmdate('d M Y H:i:s'));
				$userobj->setObjects(NULL);
				$userobj->setLanguage(getUserLocale());
				$userobj->setObjects($groupobj->getObjects());
				if (is_valid_email_zp($email)) {
					$userobj->setEmail($email);
					if (getOption('register_user_create_album')) {
						$userobj->createPrimealbum();
					}
					$userobj->setRights($groupobj->getRights());
					$userobj->setGroup($group);
					$userobj->save();
				} else {
					$more = gettext("Invalid e-mail address supplied by Google Authentication.");
				}
			} else {
				$more = sprintf(gettext('Configuration error, group %s does not exist.'), $groupname);
			}
			if (!$more && getOption('register_user_notify')) {
				$_notify = zp_mail(gettext('ZenPhoto20 Gallery registration'), sprintf(gettext('%1$s (%2$s) has registered for the zenphoto gallery providing an e-mail address of %3$s.'), $userobj->getName(), $userobj->getUser(), $userobj->getEmail()));
			}
		}

		if ($more) {
			die($more);
		}
		zp_apply_filter('federated_login_attempt', true, $user, 'googleOAuth'); //	we will mascerade as federated logon for this filter
		Zenphoto_Authority::logUser($userobj);
		session_unset(); //	need to cleanse out google stuff or subsequent logins will fail[sic]
		if ($redirect) {
			header("Location: " . $redirect);
		} else {
			header('Location: ' . FULLWEBPATH);
		}
		exitZP();
	}

	/**
	 * Enter Admin user tab handler
	 * @param $html
	 * @param $userobj
	 * @param $i
	 * @param $background
	 * @param $current
	 * @param $local_alterrights
	 */
	static function edit_admin($html, $userobj, $i, $background, $current, $local_alterrights) {
		global $_zp_current_admin_obj;
		if (empty($_zp_current_admin_obj) || !$userobj->getValid())
			return $html;
		$federated = $userobj->getCredentials(); //	came from federated logon, disable the e-mail field
		if (!in_array('googleOAuth', $federated)) {
			$federated = false;
		}

		if ($userobj->getID() != $_zp_current_admin_obj->getID() && $federated) { //	The current logged on user
			$msg = gettext("<strong>NOTE:</strong> This user was created by a Google Account logon.");
			$myhtml = '<div class="user_left">' . "\n"
							. '<p class="notebox">' . $msg . '</p>' . "\n"
							. '</div>' . "\n"
							. '<br class="clearall">' . "\n";
			$html = $myhtml . $html;
		}
		return $html;
	}

}

?>