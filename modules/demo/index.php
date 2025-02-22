<?php
defined('EVO') or die;

return new class extends Evo\Module
{
	public function init()
	{
		$this->route('/chatbox', function($params) {
			echo $this->Widget();
			return true;
		});

		// All pages in folder pages_user and pages_admin will be served by ?p=chatbox/* in their respective section
	}

	public function activate()
	{
		App::setNotice("Hellooooooooooooooooooo WORLD!");
	}

	public function deactivate()
	{
		App::setNotice("Good bye from chatbox :(");
	}

	public function hook_ajax($action)
	{
		/* Add actions that can be called through the ajax script */
		switch($action) {
			case 'chat_refresh':
				echo $this->ChatLog(App::GET('since')).'<script> '.App::REQ('id').'_time = '.time().';</script>';
				break;
		}
	}

	public function hook_user_menu(array &$items)
	{
		/* executed during the generation of the user dropdown/menu. You can append menu items to $items */
		/* items is an array of [label, fa-icon, link] */
		$items[] = ['Menu added by plugin', 'fa-steam', 'demo/test'];
	}

	public function hook_admin_menu(array &$items)
	{
		/* executed during the generation of the admin menu to add items to the "plugins" category */
		/* items is an array of [label, fa-icon, link, permission] */
		$items[] = ['Google', 'fa-home', 'https://google.ca', null];
		$items[] = ['Menu added by plugin', '', '?page=demo', null];
	}

	public function ChatLog($since = 0)
	{
		if ($since == 0) {
			return '<div class="commentaire" id="msg66"><div class="avatar"><img src="/themes/default/img/avatar.png" alt="avatar" class="avatar" height="85" width="85"></div><div class="cadre_message"><div class="auteur"><div class="float-right date text-right"><small><a href="#msg66" style="color:inherit">2013-11-09</a><br></small><div class="flag btn-group"><button class="btn btn-sm btn-warning" name="com_censure" value="66" title=""><i class="fa fa-ban"></i></button><button class="btn btn-sm btn-danger" name="com_delete" value="66" title=""><i class="fa fa-times"></i></button></div></div><strong>God miché</strong><br><span style="color:;"></span></div><div class="comment">Attends.... Attends... Ça vient...</div></div></div>';
		} else {
			return '<div class="commentaire" id="msg66"><div class="avatar"><img src="/themes/default/img/avatar.png" alt="avatar" class="avatar" height="85" width="85"></div><div class="cadre_message"><div class="auteur"><div class="float-right date text-right"><small><a href="#msg66" style="color:inherit">2013-11-09</a><br></small><div class="flag btn-group"><button class="btn btn-sm btn-warning" name="com_censure" value="66" title=""><i class="fa fa-ban"></i></button><button class="btn btn-sm btn-danger" name="com_delete" value="66" title=""><i class="fa fa-times"></i></button></div></div><strong>God miché</strong><br><span style="color:;"></span></div><div class="comment">HOLLY MOLLY<br><img src="https://fkcd.ca/s5V.jpg"></div></div></div>';
		}
	}

	public function Widget()
	{
		$id = 'chatbox'.rand(0,100);
		return '<div class="chatbox" id="'.$id.'">'.$this->ChatLog().'<div class="chatboxlog commentaires"></div><div class="chatboxinput"><input type="text"><button>Envoyer</button></div>'.
			   '<script>
					var '.$id.'_time = 0;
					setInterval(function() {
						$.get(site_url + "/ajax.php?action=chat_refresh&id='.$id.'&since=" + '.$id.'_time, function(data) {
							$("#'.$id.' .chatboxlog").append(data);
						});
					}, 5000);
				</script>';
	}
};
