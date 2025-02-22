<?php defined('EVO') or die('Que fais-tu là?');

$mail_status = 'form';

$nom     = preg_replace('#[^a-z0-9_-]#i', '_', Format::stripAccents(App::POST('username')));
$email   = App::POST('email');
$objet   = App::POST('sujet');
$message = App::POST('message');

if (IS_POST) {
	if (preg_match(PREG_EMAIL, $email) && $objet && $message) {
		$headers  = 'From: '.$nom.'(via '.App::getConfig('name').') <'.App::getConfig('email').'>' . "\r\n";
		$headers .= 'Reply-To: '.$nom.' <'.$email.'>' . "\r\n";
		$headers .= 'Content-Type: text/plain;charset=UTF-8';

		$mail_status = mail(App::getConfig('email'), $objet, $message, $headers) ? 'yes' : 'error';
	} else {
		$mail_status = 'incomplete';
	}
}

App::renderTemplate('pages/contact.php', compact('nom', 'email', 'objet', 'message', 'mail_status'));