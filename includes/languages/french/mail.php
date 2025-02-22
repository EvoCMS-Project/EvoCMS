<?php

return [
	'wrapper' => "Bonjour %username%,\n\n%message%\n\n%sitename%\n%siteurl%",

	'forum.topic.subject' => 'Un nouveau message sur %subject%',
	'forum.topic.body' => "%url%",
	
	'friends.request.subject' => '%friend% souhaiterait être votre ami!',
	'friends.request.body' => "Vous avez reçu une demande d'amitié de la part de %friend%!\n\nConnectez-vous pour accepter ou refuser",

	'account.activation.subject' => 'Activation de votre compte sur %sitename%',
	'account.activation.body' => "Voici votre lien pour activer votre compte sur %sitename%:\n%activation_url%\nCordialement,",
	
	'account.reset_password.subject' => 'Oublie de mot de passe',
	'account.reset_password.body' => "Voici votre lien pour redéfinir votre mot de passe:\n%resetlink%\nCordialement,",

	'message.type.0.subject' => "Nouveau message",
	'message.type.0.body' => "Vous avez reçu un nouveau message sur %sitename% de la part de %mailfrom%:\n\n%message%",

	'message.type.1.subject' => "",
	'message.type.1.body' => "%mailfrom% a parlé de vous sur %sitename%!\n\n%message%",

	'message.type.2.subject' => "Message important",
	'message.type.2.body' => "Vous avez reçu un nouveau message important sur %sitename%:\n\n%message%",

	'message.type.3.subject' => "Nouveau message",
	'message.type.3.body' => "Vous avez reçu un avertissement sur %sitename%:\n%message%",
];
