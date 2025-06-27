<?php
/*
 * Evo-CMS
 *
 */
require_once 'definitions.php';
require_once 'exceptions.php';
require_once 'functions.php';

use Evo\Models\User;
use Evo\Models\Group;
use Evo\Translator;
use Evo\Lang;
use Evo\Module;

class App
{
	public static $REQUEST_PAGE;
	public static $GET, $POST, $FILES;

	public static $protections = self::CSRF|self::BOTS;
	public static $debug_mode = false;
	public static $error_log = false;

	private static $title = '';
	private static $success = '';
	private static $warning = '';
	private static $notice = '';
	private static $crumbs = [];
	private static $template = 'main.php';
	private static $variables = [];
	private static $body_class = [];
	private static $http_code = 200;
	private static $content = '';

	private static $theme;
	private static $current_user;
	private static $settings = [];
	private static $default_settings = DEFAULT_SETTINGS;
	private static $modules = [];
	private static $routes = [];

	private static $REQ_SCHEME = '//';
	private static $URL_SCHEME = '//';

	public const ROUTE_USER = 1;
	public const ROUTE_ADMIN = 2;
	public const ROUTE_BOTH = 3;
	public const CSRF = 1 << 0;
	public const BOTS = 1 << 1;

	public static function init()
	{
		set_include_path('.' . PATH_SEPARATOR . ROOT_DIR . '/includes');
		spl_autoload_register('App::autoload');
		header('X-XSS-Protection: 0'); // This is for chrome

		try {
			if (!file_exists(ROOT_DIR . '/config.php')) {
				if (file_exists(ROOT_DIR . '/install')) {
					throw new Exception('Evo-CMS n\'est pas installé / Evo-CMS isn\'t installed. <a href="install/">Installer.</a>');
				}
				throw new Exception('Le fichier de configuration est manquant! / Config file is missing!');
			}

			require ROOT_DIR.'/config.php';
			require_once 'Database/database.php';
			require_once 'Database/db.'.$db_type.'.php';

			if (self::$debug_mode = !empty($debug_mode)) {
				ini_set('display_errors', 'on');
				error_reporting(E_ALL);
			} else {
				// ini_set('display_errors', 'off');
				error_reporting(E_ALL & ~(E_DEPRECATED|E_NOTICE));
			}

			if (self::$error_log = !empty($error_log)) {
				// ini_set('log_errors', 'on');
				// ini_set('error_log', );
			}

			Db::Connect($db_host, $db_user, $db_pass, $db_name, $db_prefix);
			Db::$queryLogging = self::$debug_mode;

			foreach(Db::QueryAll('select * from {settings}') as $setting) {
				self::$settings[$setting['name']] = preg_match('/^a:\d+:\{/', $setting['value'])
					? @unserialize($setting['value'])
					: $setting['value'];
				if (!array_key_exists($setting['name'], self::$default_settings)) {
					self::$default_settings[$setting['name']] = $setting['default_value'];
				}
			}

			self::$GET = $_GET;
			self::$POST = $_POST;
			self::$REQUEST_PAGE = '/' . trim(App::GET('p', App::GET('page', '')), '/');

			self::$REQ_SCHEME = IS_HTTPS ? 'https://' : 'http://';
			self::$URL_SCHEME = self::getConfig('url_https') ? 'https://' : self::$REQ_SCHEME;

			Lang::setTranslator(
				new Translator(self::getConfig('language'), ['french'], ROOT_DIR . '/includes/languages')
			);

			if (self::getConfig('database.version') < DATABASE_VERSION) {
				if (!App::GET('upgrade')) {
					die(__('errors/database.upgrade_required', ['%url%' => '?upgrade=1']));
				}
				require_once ROOT_DIR . '/install/upgrade.php';
			}

			if (empty($safe_mode)) {
				// If our current scheme doesn't match the configured scheme we redirect
				if (self::$REQ_SCHEME !== self::$URL_SCHEME) {
					self::redirect(self::getLocalURL($_SERVER['REQUEST_URI']));
				}

				// Load all modules only if safe_mode is disabled
				foreach((array)self::getConfig('modules') as $plugin) {
					self::loadModule($plugin);
				}
			}

			self::sessionStart();
			self::setTitle(ucwords(substr(self::$REQUEST_PAGE, 1)));
			self::setTheme($safe_mode ? '' : (string)self::getConfig('theme'));

			if ($timezone = self::getCurrentUser()->timezone ?: self::getConfig('timezone')) {
				@date_default_timezone_set($timezone);
			}

			if (self::$protections & self::CSRF) {
				if (empty($_SESSION['csrf'])) {
					$_SESSION['csrf'] = random_hash(32);
				}
				if (IS_POST && App::POST('csrf') !== $_SESSION['csrf']) {
					self::setWarning(sprintf('<h4>%s</h4>%s', __('errors/csrf.title'), __('errors/csrf.message')));
					self::$POST = []; // This isn't the best way, but until we abstract it it's the only way.
				}
			}

			if ((self::$protections & self::BOTS) && App::POST('_bot_name')) {
				throw new Exception('Call me maybe, mister bot.');
			}

			if ($ban = App::checkBanlist(self::getCurrentUser()->toArray())) {
				self::logout(); // Maybe not needed?
				if (self::$REQUEST_PAGE == '/login') {
					self::setWarning('Vous êtes bannis de ce site. Seul un admin peut se connecter.');
				} else {
					self::setVariables(['ban' => $ban]);
					self::showError(new Banned());
					self::render();
					die;
				}
			}

			self::trigger('app_init_done', []);
		}
		catch (Throwable $e) {
			die("<strong>Erreur fatale: </strong>{$e->getMessage()}");
		}
	}


	/**
	 *  Core login function.
	 */
	public static function sessionStart($user_id = null, bool $remember = null): bool
	{
		$cookie_name = self::getConfig('cookie.name') ?: 'evo_cms';
		$login_cookie_name = "{$cookie_name}_login";
		$is_cookie_login = false;

		@session_name($cookie_name);
		@session_set_cookie_params(0);
		@session_start();

		if ($user_id || !empty($_SESSION['user_id'])) {
			self::setCurrentUser(self::getUser($user_id ?: $_SESSION['user_id']));
		} elseif (!empty($_COOKIE[$login_cookie_name])) {
			self::setCurrentUser(User::find($_COOKIE[$login_cookie_name], 'login_key'));
			$is_cookie_login = true;
			$remember = true;
		}

		$current_user = self::getCurrentUser();

		if ($current_user->id && !$current_user->locked) {

			// New session, do some house keeping...
			if (empty($_SESSION['user_id'])) {
				$_SESSION['user_id'] = $current_user->id;
				$_SESSION['last_visit'] = $current_user->activity;

				$current_user->login_key = $remember ? random_hash(32) : null;
				$current_user->reset_key = null;
				$current_user->save();

				setcookie($login_cookie_name, $current_user->login_key, strtotime('+90days'));
				self::logEvent($current_user->id, 'user',
				    __($is_cookie_login ? 'login.login_successful_cookie' : 'login.login_successful'));
				self::trigger('user_logged_in', [$current_user, $remember]);
			}

			if ($current_user->activity < time() - 90) {
				$current_user->activity = time();
				$current_user->last_ip = $_SERVER['REMOTE_ADDR'];
				$current_user->last_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
				$current_user->save();
			}

			return true;
		}

		self::logout();
		return false;
	}


	/**
	 * Undocumented function
	 */
	public static function login($username, $password, $expire = 0)
	{

	}


	/**
	 * Undocumented function
	 */
	public static function logout()
	{
		if (self::getCurrentUser()->id) {
			setcookie(self::getConfig('cookie.name', 'evo_cms').'_login', '', strtotime('-90days'));
			unset($_SESSION['user_id']);
			self::trigger('user_logged_out', [self::getCurrentUser()]);
			self::setCurrentUser(null);
		}
		//setcookie($cookie_name, null, 10, '/'); This is needed for CSRF and other things
	}


	/**
	 * Undocumented function
	 */
	public static function resolveModule(string $plugin_id)
	{
		static $blacklist = [];

		if (!$blacklist) {
			$blacklist = array_map(function($file) {return basename($file, '.php');}, glob(__DIR__.'/*.php'));
		}

		if (in_array(strtolower($plugin_id), $blacklist) || strpos($plugin_id, '..') !== false) {
			throw new Exception("Le nom '$plugin_id' cause un conflit et ne peut être activé!");
		}

		foreach (['plugins', 'themes'] as $folder) {
			try {
				return new Module(ROOT_DIR . '/' . $folder . '/' . $plugin_id);
			} catch(Exception $e) {
				continue;
			}
		}

		return null;
	}


	/**
	 * Undocumented function
	 */
	public static function activateModule(string $plugin_id)
	{
		if ($plugin = static::loadModule($plugin_id)) {
			$plugin->activate();
			$enabled = static::getConfig('modules') ?: [];
			$enabled[] = $plugin_id;
			static::setConfig('modules', $enabled);
			static::logEvent(null, 'system', "Activation du module '{$plugin_id}'");
			return true;
		}

		return false;
	}


	/**
	 * Undocumented function
	 */
	public static function deactivateModule(string $plugin_id)
	{
		if ($plugin = static::getModule($plugin_id)) {
			$plugin->deactivate();
			$enabled = static::getConfig('modules') ?: [];
			static::setConfig('modules', array_diff($enabled, [$plugin_id]));
			unset(self::$modules[$plugin_id]);
			static::logEvent(null, 'system', "Deactivation du module '{$plugin_id}'");
			return true;
		}

		return false;
	}


	/**
	 * Undocumented function
	 */
	public static function deleteModule(string $plugin_id)
	{
		try { static::deactivateModule($plugin_id); }
		catch (Exception $e) {}

		if ($plugin = static::resolveModule($plugin_id)) {
			foreach($plugin->settings as $key => $value) {
				static::delConfig($key);
			}
			if ($plugin->location) {
				rrmdir(ROOT_DIR . '/' . $plugin->location);
			}
			static::logEvent(null, 'system', "Suppression du module '{$plugin_id}'");
			return true;
		}

		return false;
	}


	/**
	 * Undocumented function
	 */
	public static function loadModule(string $plugin_id)
	{
		global $_permissions;

		// if (!self::resolveModule($plugin_id)) {
		if ($plugin_id === '' || strpos($plugin_id, '..') !== false) {
			return false;
		}

		$plugin = null;

		foreach (['plugins', 'themes'] as $folder) {
			$path = ROOT_DIR . '/' . $folder . '/' . $plugin_id;
			if (is_dir($path)) {
				if (file_exists($path . '/index.php')) {
					$plugin = include $path . '/index.php';
				}
				if (empty($plugin) || !($plugin instanceof Module)) {
					$plugin = new Module($path);
				}
				if ($plugin) {
					break;
				}
			}
		}

		if (!$plugin) {
			return false;
		}

		$location = ROOT_DIR . '/' . $plugin->location;
		// self::$modules[$plugin->name] = $plugin;
		self::$modules[$plugin_id] = $plugin;

		// Load contributed language files
		if ($dictionaries = glob("{$location}/languages/*.php")) {
			foreach ($dictionaries as $dictionary) {
				Evo\Lang::addDictionary(include $dictionary, $plugin_id, basename($dictionary, '.php'));
			}
		}

		// Setup contributed settings
		foreach ($plugin->settings as $key => $setting) {
			self::$default_settings[$key] = $setting['default'] ?? null;
		}

		// Setup contributed permissions
		foreach ($plugin->permissions as $key => $permission) {
			$_permissions['modules'][$plugin->name]["{$plugin->name}.$key"] = $permission;
		}

		// Register default routes for subdirs pages/ and admin_pages/
		$plugin->route('/' . $plugin_id . '(/(?<page>[^/]+)?)?$', function($e) use ($location) {
			return $location . '/pages_admin/' . ($e['page'] ?? 'index') . '.php';
		}, self::ROUTE_ADMIN);
		$plugin->route('/' . $plugin_id . '(/(?<page>[^/]+)?)?$', function($e) use ($location) {
				return $location . '/pages_user/' . ($e['page'] ?? 'index') . '.php';
		}, self::ROUTE_USER);

		$plugin->init();

		return $plugin;
	}


	/**
	 * Undocumented function
	 */
	public static function getModule(string $plugin_id)
	{
		return self::$modules[$plugin_id] ?? null;
	}


	/**
	 * Undocumented function
	 */
	public static function getModules()
	{
		return self::$modules;
	}


	/**
	 * Undocumented function
	 */
	public static function trigger(string $name, array $args = [])
	{
		$name = "hook_$name";
		foreach (self::$modules as $module) {
			if (method_exists($module, $name)) {
				$module->$name(...$args);
			}
		}
	}


	/**
	 * Undocumented function
	 */
	public static function logEvent(?int $uid, string $type, string $event = '')
	{
		Db::Insert('history', [
			'e_uid'     => self::getCurrentUser()->id,
			'a_uid'     => $uid ?: self::getCurrentUser()->id,
			'type'      => $type,
			'timestamp' => time(),
			'ip'        => $_SERVER['REMOTE_ADDR'],
			'event'     => $event
		]);
	}


	/**
	 *  Check if current visitor matches one of our ban rules
	 */
	public static function checkBanlist(array $visitor = null)
	{
		if (rand(0, 5) === 1) {
			foreach(Db::QueryAll('select * from {banlist} where expires <> 0 and expires < '. time()) as $ban) {
				App::logEvent(null, 'admin', "Expiration d'une règle de bannissement: {$ban['type']} = {$ban['rule']}");
				Db::Delete('banlist', ['id' => $ban['id']]);
			}
		}

		if (!isset($_SESSION['country'])) {
			$_SESSION['country'] = geoip_country_code($_SERVER['REMOTE_ADDR']);
		}

		if (isset($visitor['group_id']) && $visitor['group_id'] == 1) {
			return null;
		}

		if (isset($visitor['email'])) {
			$checks[] = "(? like rule and type = 'email')";
			$params[] = $visitor['email'];
		}

		if (isset($visitor['username'])) {
			$checks[] = "(? like rule and type = 'username')";
			$params[] = $visitor['username'];
		}

		$checks[] = "(? like rule and type = 'ip')";
		$checks[] = "(? like rule and type = 'country')";
		$params[] = $_SERVER['REMOTE_ADDR'];
		$params[] = $_SESSION['country'];

		return Db::Get('select * from {banlist} where ' . implode(' or ', $checks), ...$params);
	}


	/**
	 *
	 */
	public static function getDefaultConfig(string $key)
	{
		return self::$default_settings[strtolower($key)] ?? null;
	}


	/**
	 *
	 */
	public static function getConfig(string $key, $default = null)
	{
		$key = strtolower($key);
		return self::$settings[$key] ?? self::$default_settings[$key] ?? $default;
	}


	/**
	 *
	 */
	public static function setConfig(string $key, $value = null)
	{
		$key = strtolower($key);
		$default = self::getDefaultConfig($key);

		Db::Insert('settings', [
			'name'          => $key,
			'value'         => is_array($value) ? serialize($value) : $value,
			'default_value' => is_array($default) ? serialize($default) : $default
		], true);
		self::$settings[$key] = $value;
	}


	/**
	 *
	 */
	public static function delConfig(string $key)
	{
		$key = strtolower($key);
		Db::Delete('settings', ['name' => $key]);
		unset(self::$default_settings[$key]);
		unset(self::$settings[$key]);
	}


	/**
	 * Route and then run the first script matching the route
	 */
	public static function run(array $routes = null)
	{
		try {
			ob_start();
			$routes = $routes ?? self::$routes;
			$matched = 0;

			foreach($routes as $route => $callback) {
				if (preg_match('`^'.$route.'$`', self::$REQUEST_PAGE, $match)) {
					$_return = $callback($match);

					if (is_string($_return) && file_exists($_return)) {
						self::setBodyClass('page-'.basename($_return, '.php'));
						self::$GET = array_diff_key((array)$match, range(0, 10)) + self::$GET;
						$matched++;
						require $_return;
						break;
					} elseif ($_return === true) { /** For modules who want to stop routing, not the best way but for now... */
						$matched++;
						break;
					}
				}
			}

			if ($matched === 0) {
				throw new PageNotFound();
			}
			self::setContent(ob_get_clean());
		}
		catch(HttpException $e) {
			@ob_end_clean();
			self::$http_code = $e::HTTP_CODE;
			self::showError($e, get_class($e));
		}
		catch(Warning $e) {
			@ob_end_clean();
			self::showError($e, get_class($e));
		}
	}


	/**
	 * Render and outbut a template
	 */
	public static function render(string $template = '', array $variables = [], int $http_code = null)
	{
		while(ob_get_status() && ob_end_clean());

		$variables += [
			'_notice' => self::$notice, '_warning' => self::$warning, '_success' => self::$success,
			'_crumbs' => self::getCrumbs(), '_body_class' => implode(' ', self::$body_class),
			'_content' => self::$content, '_title' => self::getTitle()
		];

		$buffer = self::renderTemplate($template ?: self::$template, $variables, true);

		if (self::$protections & self::CSRF) {
			$buffer = str_replace('</form>', '<input type="hidden" name="csrf" value="'.$_SESSION['csrf'].'"></form>', $buffer);
		}

		if (self::$protections & self::BOTS) {
			$buffer = str_replace('</form>', '<input type="text" name="_bot_name" hidden></form>', $buffer);
		}

		http_response_code($http_code ?? self::$http_code);
		echo $buffer;
	}


	/**
	 * Renders a single template
	 */
	public static function renderTemplate(string $template, array $variables = [], bool $buffer_output = false)
	{
		$search_paths = [self::getTheme()->location . "/templates/$template", "includes/templates/$template"];
		$variables += self::$variables;

		extract($variables);

		if ($buffer_output) {
			ob_start();
		}

		foreach($search_paths as $tpl_file) {
			if (file_exists(ROOT_DIR . "/$tpl_file")) {
				include ROOT_DIR . "/$tpl_file";
				break;
			}
		}

		if ($buffer_output) {
			return ob_get_clean();
		}
	}


	/**
	 *  Find an asset file. It will look in the current theme, then in the default theme, then in the ROOT_DIR.
	 */
	public static function getAsset(string $filename, bool $local_path = false)
	{
		$paths = preg_replace('#[/\\\\]+#', '/', [
			self::getTheme()->location . "/$filename", "assets/$filename", $filename
		]);
		foreach($paths as $path) {
		 	$full_path = ROOT_DIR . '/' . $path;
			if (file_exists($full_path)) {
				$version = is_dir($full_path) ? [] : ['v' => filemtime($full_path)];
				return $local_path ? $full_path : App::getLocalURL($path, $version, '');
			}
		}
		return false;
	}


	/**
	 * Like getLocalURL but it will transform to ?p=$page&id= if rewriting is disabled
	 */
	public static function getURL(string $page = '/', $args = [], string $hash = null)
	{
		/* Assume id */
		$args = is_array($args) ? $args : ['id' => $args];
		$page = ltrim($page, '/');

		if ($page !== '') {
			if (isset($args['id'])) {
				$page .= '/' . $args['id'];
				unset($args['id']);
			}

			if (!self::getConfig('url_rewriting')) {
				$args = ['p' => $page] + $args;
				$page = '';
			}
		}

		return self::getLocalURL($page, $args, $hash, true);
	}


	/**
	 *
	 */
	public static function getLocalURL(string $path = '/', $args = [], string $hash = null, bool $auto_scheme = true)
	{
		$url = self::getConfig('url');
		if ($auto_scheme || !preg_match('!^(https?:|http:)//!i', $url)) {
			$url = self::$URL_SCHEME . preg_replace('!^(https?:|http:)//!i', '', $url);
		}
		$url .= '/' . ltrim($path, '/');

		if ($args) $url .= '?' . http_build_query($args);
		if ($hash) $url .= '#' . ltrim($hash, '#');

		return $url;
	}


	/**
	 *
	 */
	public static function getAdminURL(string $page = '/', $args = [], string $hash = null, bool $auto_scheme = true)
	{
		if (trim($page, '/') !== '') {
			$args = ['page' => $page] + $args;
		}
		return self::getLocalURL('/admin/', $args, $hash, $auto_scheme);
	}


	/**
	 * If $url contains http:// it will not be changed, otherwise App::getURL() is called
	 *
	 */
	public static function redirect(string $url, $args = [], string $hash = '')
	{
		if (!preg_match('/^(https?:)?\/\//i', $url)) {
			$url = self::getURL($url, $args, $hash);
		}
		header('Location: ' . $url);
		die('Redirected to: ' . $url);
	}


	/**
	 * Replaces any self::$content by the rendering of an exception
	 */
	public static function showError(Exception $e, string $render_as = '')
	{
		if ($e instanceof Warning) {
			$variables = ['title' => $e->getTitle(), 'message' => $e->getMessage()];
		} else {
			$variables = ['title' => get_class($e), 'message' => $e->getMessage()];
		}

		$type = $render_as ?: get_class($e);

		self::setContent(self::renderTemplate("errors/$type.php", $variables, true));
		self::setBodyClass('error error-'.get_class($e));
	}


	/**
	 * Undocumented function
	 */
	public static final function route(string $route, callable $callback, int $access = self::ROUTE_BOTH)
	{
		if (($access & App::ROUTE_ADMIN) && defined('EVO_ADMIN')) {
			self::$routes[$route] = $callback;
		} elseif (($access & App::ROUTE_USER) && !defined('EVO_ADMIN')) {
			self::$routes[$route] = $callback;
		}
	}


	/**
	 * Add variables to be passed to templates rendered by render()
	 */
	public static function setVariables(array $variables)
	{
		self::$variables = $variables + self::$variables;
	}


	/**
	 * Enable or disable csrf and bot protections
	 */
	public static function setProtections(int $flags)
	{
		self::$protections = $flags;
	}


	/**
	 * Set the theme and loads its settings
	 */
	public static function setTheme(?string $theme)
	{
		if ($theme && ($plugin = self::getModule($theme)) && in_array('theme', $plugin->exports)) {
			self::$theme = $plugin;
		} else {
			self::$theme = \Evo\EvoInfo::fromFile(ROOT_DIR . '/assets/theme.json');
			self::$theme->location = 'assets/';
		}
	}


	/**
	 * Undocumented function
	 */
	public static function getTheme()
	{
		return self::$theme;
	}


	/**
	 * Set the main template to be rendered
	 */
	public static function setTemplate(string $template)
	{
		self::$template = $template;
	}


	/**
	 * Sets the HTTP response status code
	 */
	public static function setStatusCode(int $http_code)
	{
		self::$http_code = $http_code;
	}


	/**
	 * Sets the content to be displayed in ::render()'s template body
	 */
	public static function setContent(string $content)
	{
		self::$content = $content;
	}


	/**
	 * Adds CSS classes to be added to render()'s template body tag
	 */
	public static function setBodyClass(string  $class, bool $append = true)
	{
		self::$body_class[] = $class;
	}


	/**
	 * Sets the page's title
	 */
	public static function setTitle(string $title)
	{
		self::$title = $title;
	}


	/**
	 * Undocumented function
	 */
	public static function getTitle(bool $append_sitename = true)
	{
		if ($append_sitename) {
			return self::$title . (self::$title ? ' - ' : '') . self::getConfig('name');
		}

		return self::$title;
	}


	/**
	 * Undocumented function
	 */
	public static function setSuccess(string $message, bool $append = false)
	{
		$message = '<div>'.$message.'</div>';
		self::$success = $append ? self::$success . $message : $message;
	}


	/**
	 * Undocumented function
	 */
	public static function setWarning(string $message, bool $append = false)
	{
		$message = '<div>'.$message.'</div>';
		self::$warning = $append ? self::$warning . $message : $message;
	}


	/**
	 * Undocumented function
	 */
	public static function setNotice(string $message, bool $append = false)
	{
		$message = '<div>'.$message.'</div>';
		self::$notice = $append ? self::$notice . $message : $message;
	}


	/**
	 * Add crumb level to the navigation crumbs
	 */
	public static function addCrumb(string $title, string $link = null)
	{
		self::$crumbs[] = [$title, $link];
	}


	/**
	 * Undocumented function
	 */
	public static function getCrumbs(bool $text_only = false): array
	{
		$crumbs = [];

		foreach(self::$crumbs as $crumb) {
			list($text, $link) = $crumb;
			if (!$text_only && $link !== null) {
				$crumbs[] = '<a href="' . $link. '">' . html_encode($text) . '</a>';
			} else {
				$crumbs[] = html_encode($text);
			}
		}
		return $crumbs;
	}


	/**
	 * Undocumented function
	 */
	public static function getCurrentUser(): ?User
	{
		if (!self::$current_user) {
			self::setCurrentUser(null);
		}
		return self::$current_user;
	}


	/**
	 * Undocumented function
	 */
	public static function setCurrentUser(?User $user)
	{
		self::$current_user = $user ?: new User(['id' => 0, 'group_id' => 4, 'username' => __('guest.username')]);
	}


	/**
	 *  get user from id or username
	 */
	public static function getUser($id): ?User
	{
		return ctype_digit((string)$id) ? User::find($id) : User::find($id, 'username');
	}


	/**
	 *  get group from id or name
	 */
	public static function getGroup($id): ?Group
	{
		return ctype_digit((string)$id) ? Group::find($id) : Group::find($id, 'name');
	}


	/**
	 *  Verify if a group is granted a permission.
	 */
	public static function groupHasPermission(int $group_id, string $permission, int $rel_id = null): bool
	{
		static $roles = [], $permissions = [];

		if (!isset($permissions[$group_id])) {
			if ($group = self::getGroup($group_id)) {
				$roles[$group_id] = strtolower($group->role ?? $group->internal);
				$permissions[$group_id] = $group->getPermissions();
			} else {
				$roles[$group_id] = 'none';
				$permissions[$group_id] = [];
			}
		}

		// Administrator role has all permissions
		if ($roles[$group_id] === 'administrator')
			return true;

		// Moderator role has all mod permissions
		if ($roles[$group_id] === 'moderator' && strpos($permission, 'mod.') === 0)
			return true;

		// Just checking if group is of specific role
		if ($roles[$group_id] === $permission)
			return true;

		if ($rel_id && !empty($permissions[$group_id][$permission][$rel_id]))
			return $permissions[$group_id][$permission][$rel_id];

		if (!$rel_id && !empty($permissions[$group_id][$permission]))
			return $permissions[$group_id][$permission];

		return false;
	}


	/**
	 * Undocumented function
	 */
	public static function GET(string $key = null, $default = null)
	{
		if ($key === null) {
			return self::$GET;
		}
		return self::$GET[$key] ?? $default;
	}


	/**
	 * Undocumented function
	 */
	public static function POST(string $key = null, $default = null)
	{
		if ($key === null) {
			return self::$POST;
		}
		return self::$POST[$key] ?? $default;
	}


	/**
	 * Undocumented function
	 */
	public static function REQ(string $key, $default = null)
	{
		return App::GET($key, App::POST($key, $default));
	}


	/**
	 * Undocumented function
	 */
	public static function sendmail(string $to, string $subject, string $text_message, string $html_message = '', &$error = null): bool
	{
		try {
			$mail = new PHPMailer\PHPMailer\PHPMailer(true);

			if (App::getConfig('mail.send_method') === 'smtp') {
				$mail->isSMTP();
				$mail->SMTPOptions = ['ssl' => [ 'verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
				$mail->SMTPSecure = App::getConfig('mail.smtp_encryption');
				$mail->Host = App::getConfig('mail.smtp_host');
				$mail->Port = App::getConfig('mail.smtp_port');
				if (App::getConfig('mail.smtp_username')) {
					$mail->SMTPAuth = true;
					$mail->Username = App::getConfig('mail.smtp_username');
					$mail->Password = App::getConfig('mail.smtp_password');
				}
			} else {
				$mail->isMail(); //$mail->isSendmail();
			}

			$mail->setFrom(App::getConfig('email'), App::getConfig('name'));
			$mail->addAddress($to);
			$mail->Sender = App::getConfig('email');
			$mail->XMailer = 'Evo-CMS';
			$mail->Subject = $subject;

			if ($html_message) {
				$mail->isHTML(true);
				$mail->Body    = $html_message;
				$mail->AltBody = $text_message;
			} else {
				$mail->Body = $text_message;
			}
			return $mail->send();
		}
		catch(Exception $e) {
			App::logEvent(null, 'system', 'PHPMailer: ' . $e->getMessage());
			$error = $mail->ErrorInfo;
			return false;
		}
	}


	/**
	 * Undocumented function
	 */
	public static function autoload(string $className)
	{
		$fileName = ltrim(strtr($className, '\\', '/'), '/');

		if (stream_resolve_include_path("$fileName.php")) {
			return require "$fileName.php";
		} elseif (stream_resolve_include_path(strtolower("$fileName.php"))) { // We should probably not do that
			return require strtolower("$fileName.php");
		}

		throw new Exception("Class $className not found");
	}
}
