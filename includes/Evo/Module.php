<?php
namespace Evo;
use App, ModuleException;

class Module
{
	protected $infos;
	protected $location;
	protected $plugin_id;
	protected $settings = [];


    public final function __construct(string $directory = null)
	{
		$this->location = $directory ?? dirname((new \ReflectionClass($this))->getFileName());
		$this->location = implode('', explode(ROOT_DIR, $this->location, 2));
		$this->plugin_id = basename($this->location);
		$this->infos = EvoInfo::fromFile(ROOT_DIR . "/{$this->location}/module.json");
		if (!$this->infos) {
			throw new ModuleException('Folder "' . $this->location . '" does not contain a valid module.');
		}
		foreach ($this->infos->settings as $key => $setting) {
			$this->settings["modules.{$this->plugin_id}.$key"] = $setting + ['allow_reset' => true];
		}
	}


	public final function __get(string $name)
	{
		return $this->$name ?? $this->infos->$name ?? null;
	}


	public final function route(string $route, callable $callback, int $access = App::ROUTE_USER)
	{
		App::route("(?#{$this->plugin_id})$route", $callback, $access);
	}


	public function getConfig($key, $default = null)
	{
		return App::getConfig("modules.{$this->name}.$key", $default);
	}


	public function setConfig($key, $value)
	{
		return App::setConfig("modules.{$this->name}.$key", $value);
	}


	// Legacy
	public function config(string $key, $default = null, bool $save = false)
	{
		if ($save) static::setConfig($key, $default);
		return static::getConfig($key, $default);
	}


	public final function has_permission(string $key = '', $rel_id = null, bool $redirect = false)
	{
		return has_permission("modules.{$this->plugin_id}.$key", $rel_id, $redirect);
	}


	/* Your plugin can override anything below this point: */

	public function init()
	{
	}

	public function activate()
	{
	}

	public function deactivate()
	{
	}

	// Hooks
	public function hook_admin_menu(array &$items) {}
	public function hook_user_menu(array &$items) {}
	public function hook_ajax($action) {}
	public function hook_app_init_done() {}
	public function hook_app_deinit() {}
	public function hook_user_updated($user_info, $edits) {}
	public function hook_user_created($user_info, $source) {}
	public function hook_user_deleted($user_info, $reason) {}
	public function hook_user_logged_in($user_info, $expire) {}
	public function hook_user_logged_out($user_info) {}
	public function hook_forum_post_created($post) {}
	public function hook_forum_post_deleted($post) {}
	public function hook_forum_post_updated($post) {}
	public function hook_forum_topic_created($topic) {}
	public function hook_forum_topic_deleted($topic) {}
	public function hook_forum_topic_updated($topic) {}
	public function hook_forum_before_posts_loop(array &$posts) {}
	public function hook_forum_before_topics_loop(array &$topics) {}
	public function hook_forum_before_forums_loop(array &$forums) {}
	public function hook_forum_before_post_signature($post) {}
	public function hook_page_display(&$page) {}
	public function hook_page_deleted($page) {}
	public function hook_page_updated($page) {}
	public function hook_head() {}
	public function hook_head_admin() {}
	public function hook_footer() {}
	public function hook_footer_admin() {}
}
