<?php
require_once 'version.php';

define('EVO', 1);
define('ROOT_DIR', realpath(__DIR__.'/..'));
define('IS_AJAX',  strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest');
define('IS_HTTPS', !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
define('IS_POST',  $_SERVER['REQUEST_METHOD'] === 'POST');

define('PREG_USERNAME',  '/^[-a-z0-9_][-a-z0-9_\. ]*[-a-z_\.][-a-z0-9_\. ]*[-a-z0-9_]$/i');
define('PREG_FILENAME',  '#^[-a-z0-9_\. ]+$#i');
define('PREG_FILEPATH',  '#^[-a-z0-9_\./ ]+$#i');
define('PREG_DIRNAME',   '#^[-a-z0-9_\./ ]+$#i');
define('PREG_PASSWORD',  '/^.{4,}$/');
define('PREG_EMAIL',     '#^[a-z0-9][-a-z0-9_\.]+@[-a-z0-9_\.]+$#i');
define('PREG_DIGIT',     '#^[0-9]+$#');
define('PREG_ALPHA',     '#^[a-z]+$#i');
define('PREG_URL',       '`^https?://[-A-Za-z0-9+&@/%?=~_#\.]+$`');
define('PREG_NOT_EMPTY', '#^.+$#');

define('MSG_NORMAL',       0);
define('MSG_NOTIFICATION', 1);
define('MSG_IMPORTANT',    2);
define('MSG_WARNING',      3);


$_permissions = [
	'user' => [
		'label' => 'Base',

		'Droits Généraux'=> [
			'view_uprofile' => 'Voir les profils des membres',
			'comment_send' => 'Poster des commentaires',
			'upload' => 'Uploader des fichiers (forum/avatar/etc)',
			'invite' => 'Inviter un nouveau membre',
			],

		'Forums'=> [
			'forum_tag_user' => 'Notifier un utilisateur (@username)',
			'forum_tag_group' => 'Notifier un groupe (@group ou @all)',
			],

		'Situation'=> [
			'staff' => 'Est membre du staff',
			],
	],

	'mod' => [
		'label' => 'Modération',

		'Les Rapports'=> [
			'reports' => 'Gérer les reports',
			],

		'Les Articles'=> [
			'comment_delete' => 'Supprimer des commentaires',
			'comment_censure' => 'Censurer des commentaires',
			],

		'Gestion des membres'=> [
			'ban_member' => 'Suspension de compte',
			],

		'Forums'=> [
			'forum_topic_close' => 'Fermer des discussions',
			'forum_topic_stick' => 'Épingler des discussions',
			'forum_topic_move' => 'Déplacer des discussions',
			'forum_topic_delete' => 'Supprimer des discussions',
			'forum_topic_redirect' => 'Redirection de topic',
			'forum_post_delete' => 'Supprimer des messages',
			'forum_post_edit' => 'Éditer des messages',
			],
	],

	'admin' => [
		'label' => 'Administration',

		'Général'=> [
			'manage_servers' => 'Gestion des serveurs',
			'manage_settings' => 'Modifier la configuration du site',
			'manage_modules' => 'Gestion des modules',
			'manage_security' => 'Gestion de la sécurité',
			'backup' => 'Téléchargement d\'dune sauvegarde',
			'sql' => 'Gestion SQL (Adminer)',
			'files' => 'Éditer les fichiers du CMS',
			],

		'Contenu'=> [
			'manage_pages' => 'Gestion des pages/articles',
			'manage_menu' => 'Gestion du menu',
			'manage_media' => 'Gestion des fichiers médias',
			],

		'Communauté'=> [
			'manage_forums' => 'Gestion des forums',
			'broadcast' => 'Envoi de mail de masse',
			],

		'Gestion des membres'=> [
			'change_group' => 'Modifier les groupes',
			'add_member' => 'Ajouter un membre',
			'del_member' => 'Supprimer un membre',
			'edit_uprofile' => 'Éditer le profil d\'un membre',
			'edit_ugroup' => 'Changer le groupe d\'un membre',
			'view_user_messages' => 'Voir la messagerie d\'un membre',
			'view_user_history' => 'Voir l\'historique d\'un membre',
			'view_user_files' => 'Voir les fichiers d\'un membre',
			],
		'Les Historiques'=> [
			'log_admin' => 'Administration',
			'log_user' => 'Historique membres',
			'log_mail' => 'Messagerie',
			'log_forum' => 'Log Forum',
			'log_system' => 'Log Système',
			],
	],

	'modules' => [
		'label' => 'Modules',
	]
];


const DEFAULT_SETTINGS = [
	'name'               => 'Evo-CMS',
	'url'                => '/',
	'cookie.name'        => 'evo_cms',
	'articles_per_page'  => 4,
	'open_registration'  => 1,
	'default_user_group' => 3,
	'frontpage'          => 'blog',
	'editor'             => 'wysiwyg',
	'modules'            => [],
	'upload_groups'      => "image     jpg jpeg png gif\n".
	                        "video     mp4 mkv avi flv mov\n".
	                        "audio     mp3 ogg wav\n".
	                        "archive   xpi apk zip rar jar gz bz2 7z xz\n".
	                        "document  pdf doc docx xlsx xls csv odt ott oth odm\n".
	                        "text      txt diff php html htm js ts css",
];


const PLUGIN_NAME_BLACKLIST = [

];


const GROUP_ROLES = [
	'administrator',
	'moderator',
	'none',
];


const MESSAGE_TYPES = [
	MSG_NORMAL       => ['label' => 'message', 'icon.new' => 'fa-envelope', 'icon.viewed' => 'fa-folder-open', 'class' => ''],
	MSG_NOTIFICATION => ['label' => 'notification', 'icon.new' => 'fa-bell', 'icon.viewed' => 'fa-bell', 'class' => 'info'],
	MSG_IMPORTANT    => ['label' => 'message important', 'icon.new' => 'fa-exclamation', 'icon.viewed' => 'fa-folder-open-o', 'class' => 'warning'],
	MSG_WARNING      => ['label' => 'avertissement','icon.new' => 'fa-flag', 'icon.viewed' => 'fa-flag', 'class' => 'danger'],
];


const PAGE_TYPES = [
	'article'    => 'Article',
	'page'       => 'Page',
	'page-full'  => 'Page pleine',
	'page-blank' => 'Page sans template',
];


const INTERNAL_PAGES = [
	'blog',
	'contact',
	'custom',
	'downloads',
	'feed',
	'forums',
	'server',
	'users',
];


const COUNTRIES = [
	'' =>	'',
	'AF' =>	'Afganistan',
	'AL' =>	'Albania',
	'DZ' =>	'Algeria',
	'AS' => 'American Samoa',
	'AD' => 'Andorra',
	'AO' => 'Angola',
	'AI' => 'Anguilla',
	'AQ' => 'Antarctica',
	'AG' => 'Antigua and Barbuda',
	'AR' => 'Argentina',
	'AM' => 'Armenia',
	'AW' => 'Aruba',
	'AU' => 'Australia',
	'AT' => 'Austria',
	'AZ' => 'Azerbaijan',
	'BS' => 'Bahamas',
	'BH' => 'Bahrain',
	'BD' => 'Bangladesh',
	'BB' => 'Barbados',
	'BY' => 'Belarus',
	'BE' => 'Belgium',
	'BZ' => 'Belize',
	'BJ' => 'Benin',
	'BM' => 'Bermuda',
	'BT' => 'Bhutan',
	'BO' => 'Bolivia',
	'BA' => 'Bosnia and Herzegowina',
	'BW' => 'Botswana',
	'BV' => 'Bouvet Island',
	'BR' => 'Brazil',
	'IO' => 'British Indian Ocean Territory',
	'BN' => 'Brunei Darussalam',
	'BG' => 'Bulgaria',
	'BF' => 'Burkina Faso',
	'BI' => 'Burundi',
	'KH' => 'Cambodia',
	'CM' => 'Cameroon',
	'CA' => 'Canada',
	'CV' => 'Cape Verde',
	'KY' => 'Cayman Islands',
	'CF' => 'Central African Republic',
	'TD' => 'Chad',
	'CL' => 'Chile',
	'CN' => 'China',
	'CX' => 'Christmas Island',
	'CC' => 'Cocos (Keeling) Islands',
	'CO' => 'Colombia',
	'KM' => 'Comoros',
	'CG' => 'Congo',
	'CD' => 'Congo, the Democratic Republic of the',
	'CK' => 'Cook Islands',
	'CR' => 'Costa Rica',
	'CI' => 'Cote d\'Ivoire',
	'HR' => 'Croatia (Hrvatska)',
	'CU' => 'Cuba',
	'CY' => 'Cyprus',
	'CZ' => 'Czech Republic',
	'DK' => 'Denmark',
	'DJ' => 'Djibouti',
	'DM' => 'Dominica',
	'DO' => 'Dominican Republic',
	'TP' => 'East Timor',
	'EC' => 'Ecuador',
	'EG' => 'Egypt',
	'SV' => 'El Salvador',
	'GQ' => 'Equatorial Guinea',
	'ER' => 'Eritrea',
	'EE' => 'Estonia',
	'ET' => 'Ethiopia',
	'FK' => 'Falkland Islands (Malvinas)',
	'FO' => 'Faroe Islands',
	'FJ' => 'Fiji',
	'FI' => 'Finland',
	'FR' => 'France',
	'FX' => 'France, Metropolitan',
	'GF' => 'French Guiana',
	'PF' => 'French Polynesia',
	'TF' => 'French Southern Territories',
	'GA' => 'Gabon',
	'GM' => 'Gambia',
	'GE' => 'Georgia',
	'DE' => 'Germany',
	'GH' => 'Ghana',
	'GI' => 'Gibraltar',
	'GR' => 'Greece',
	'GL' => 'Greenland',
	'GD' => 'Grenada',
	'GP' => 'Guadeloupe',
	'GU' => 'Guam',
	'GT' => 'Guatemala',
	'GN' => 'Guinea',
	'GW' => 'Guinea-Bissau',
	'GY' => 'Guyana',
	'HT' => 'Haiti',
	'HM' => 'Heard and Mc Donald Islands',
	'VA' => 'Holy See (Vatican City State)',
	'HN' => 'Honduras',
	'HK' => 'Hong Kong',
	'HU' => 'Hungary',
	'IS' => 'Iceland',
	'IN' => 'India',
	'ID' => 'Indonesia',
	'IR' => 'Iran (Islamic Republic of)',
	'IQ' => 'Iraq',
	'IE' => 'Ireland',
	'IL' => 'Israel',
	'IT' => 'Italy',
	'JM' => 'Jamaica',
	'JP' => 'Japan',
	'JO' => 'Jordan',
	'KZ' => 'Kazakhstan',
	'KE' => 'Kenya',
	'KI' => 'Kiribati',
	'KP' => 'Korea, Democratic People\'s Republic of',
	'KR' => 'Korea, Republic of',
	'KW' => 'Kuwait',
	'KG' => 'Kyrgyzstan',
	'LA' => 'Lao People\'s Democratic Republic',
	'LV' => 'Latvia',
	'LB' => 'Lebanon',
	'LS' => 'Lesotho',
	'LR' => 'Liberia',
	'LY' => 'Libyan Arab Jamahiriya',
	'LI' => 'Liechtenstein',
	'LT' => 'Lithuania',
	'LU' => 'Luxembourg',
	'MO' => 'Macau',
	'MK' => 'Macedonia, The Former Yugoslav Republic of',
	'MG' => 'Madagascar',
	'MW' => 'Malawi',
	'MY' => 'Malaysia',
	'MV' => 'Maldives',
	'ML' => 'Mali',
	'MT' => 'Malta',
	'MH' => 'Marshall Islands',
	'MQ' => 'Martinique',
	'MR' => 'Mauritania',
	'MU' => 'Mauritius',
	'YT' => 'Mayotte',
	'MX' => 'Mexico',
	'FM' => 'Micronesia, Federated States of',
	'MD' => 'Moldova, Republic of',
	'MC' => 'Monaco',
	'MN' => 'Mongolia',
	'MS' => 'Montserrat',
	'MA' => 'Morocco',
	'MZ' => 'Mozambique',
	'MM' => 'Myanmar',
	'NA' => 'Namibia',
	'NR' => 'Nauru',
	'NP' => 'Nepal',
	'NL' => 'Netherlands',
	'AN' => 'Netherlands Antilles',
	'NC' => 'New Caledonia',
	'NZ' => 'New Zealand',
	'NI' => 'Nicaragua',
	'NE' => 'Niger',
	'NG' => 'Nigeria',
	'NU' => 'Niue',
	'NF' => 'Norfolk Island',
	'MP' => 'Northern Mariana Islands',
	'NO' => 'Norway',
	'OM' => 'Oman',
	'PK' => 'Pakistan',
	'PW' => 'Palau',
	'PA' => 'Panama',
	'PG' => 'Papua New Guinea',
	'PY' => 'Paraguay',
	'PE' => 'Peru',
	'PH' => 'Philippines',
	'PN' => 'Pitcairn',
	'PL' => 'Poland',
	'PT' => 'Portugal',
	'PR' => 'Puerto Rico',
	'QA' => 'Qatar',
	'RE' => 'Reunion',
	'RO' => 'Romania',
	'RU' => 'Russian Federation',
	'RW' => 'Rwanda',
	'KN' => 'Saint Kitts and Nevis',
	'LC' => 'Saint LUCIA',
	'VC' => 'Saint Vincent and the Grenadines',
	'WS' => 'Samoa',
	'SM' => 'San Marino',
	'ST' => 'Sao Tome and Principe',
	'SA' => 'Saudi Arabia',
	'SN' => 'Senegal',
	'SC' => 'Seychelles',
	'SL' => 'Sierra Leone',
	'SG' => 'Singapore',
	'SK' => 'Slovakia (Slovak Republic)',
	'SI' => 'Slovenia',
	'SB' => 'Solomon Islands',
	'SO' => 'Somalia',
	'ZA' => 'South Africa',
	'GS' => 'South Georgia and the South Sandwich Islands',
	'ES' => 'Spain',
	'LK' => 'Sri Lanka',
	'SH' => 'St. Helena',
	'PM' => 'St. Pierre and Miquelon',
	'SD' => 'Sudan',
	'SR' => 'Suriname',
	'SJ' => 'Svalbard and Jan Mayen Islands',
	'SZ' => 'Swaziland',
	'SE' => 'Sweden',
	'CH' => 'Switzerland',
	'SY' => 'Syrian Arab Republic',
	'TW' => 'Taiwan, Province of China',
	'TJ' => 'Tajikistan',
	'TZ' => 'Tanzania, United Republic of',
	'TH' => 'Thailand',
	'TG' => 'Togo',
	'TK' => 'Tokelau',
	'TO' => 'Tonga',
	'TT' => 'Trinidad and Tobago',
	'TN' => 'Tunisia',
	'TR' => 'Turkey',
	'TM' => 'Turkmenistan',
	'TC' => 'Turks and Caicos Islands',
	'TV' => 'Tuvalu',
	'UG' => 'Uganda',
	'UA' => 'Ukraine',
	'AE' => 'United Arab Emirates',
	'GB' => 'United Kingdom',
	'US' => 'United States',
	'UM' => 'United States Minor Outlying Islands',
	'UY' => 'Uruguay',
	'UZ' => 'Uzbekistan',
	'VU' => 'Vanuatu',
	'VE' => 'Venezuela',
	'VN' => 'Viet Nam',
	'VG' => 'Virgin Islands (British)',
	'VI' => 'Virgin Islands (U.S.)',
	'WF' => 'Wallis and Futuna Islands',
	'EH' => 'Western Sahara',
	'YE' => 'Yemen',
	'YU' => 'Yugoslavia',
	'ZM' => 'Zambia',
	'ZW' => 'Zimbabwe'
];
