<?php
/* Adminer bootstrap */
require '../../includes/app.php';
App::init();
has_permission('admin.sql', true);

function adminer_object() {
	return new class extends Adminer\Adminer {
		public function name() {
			return '<a href="' . App::getAdminURL() . '"><small>' . App::getConfig('name') . '</small></a>';
		}
        function login($login, $password) {
            return true;
        }
		public function database() { // Doesn't work for some reason
			return Db::$database;
		}
		public function loginForm() {
			$type = Db::DriverName();
			$db = ($type !== 'sqlite') ? Db::$database : (Db::$database[0] == '/' ? Db::$database : ROOT_DIR . '/' . Db::$database);
			if ($type === 'mysql') { // Why, adminer, why?
				$type = 'server';
			}
			echo '<input type="hidden" name="auth[db]" value="'.$db.'">';
			echo '<button name="auth[driver]" type="submit" value="'.$type.'">Login</button>';
		}
		public function credentials() {
			return [Db::$host, Db::$user, Db::$password];
		}
	};
}

// ob_start();

require 'adminer.php';