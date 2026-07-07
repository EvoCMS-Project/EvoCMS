<?php

return [
	'wrapper' => "Hi %username%,\n\n%message%\n\n%sitename%\n%siteurl%",

	'forum.topic.subject' => 'New message on thread %subject%',
	'forum.topic.body' => "%url%",
	
	'friends.request.subject' => '%friend% sent you a friend request!',
	'friends.request.body' => "You have received a friend request from %friend%!\n\nConnect to accept or refuse.",

	'account.activation.subject' => 'Activate your account on %sitename%',
	'account.activation.body' => "Follow this link to activate your account on %sitename%:\n%activation_url%\nRegards,",
	
	'account.reset_password.subject' => 'Forgotten password',
	'account.reset_password.body' => "Follow this link to reset your password:\n%resetlink%\nRegards,",

	'message.type.0.subject' => "You have received a message",
	'message.type.0.body' => "You've received a new message from %mailfrom%:\n\n%message%",

	'message.type.1.subject' => "Someone mentioned you",
	'message.type.1.body' => "%mailfrom% mentioned you on %sitename%!\n\n%message%",

	'message.type.2.subject' => "Important message",
	'message.type.2.body' => "You've received an important message on %sitename%:\n\n%message%",

	'message.type.3.subject' => "You have received a warning",
	'message.type.3.body' => "You've received a warning on %sitename%:\n%message%",
];
