<?php defined('EVO') or die('Que fais-tu là ?');
has_permission(null, true);

use Evo\Models\File;

$user_info = App::getCurrentUser();
$groups = Db::QueryAll('select * from {groups} order by priority asc');
$timezones = generate_tz_list();
$reset = ['newsletter' => 0, 'discuss' => 0];
$warnings = $avatars = $social = $edits = [];

$fields = [ // regex/enum validation, is_required, filter
	'username' 	   => [PREG_USERNAME, true],
	'password' 	   => ['/^.{4,512}$/', false],
	'email'        => [PREG_EMAIL, true],
	'country' 	   => [array_keys(COUNTRIES), false],
	'timezone' 	   => [array_keys($timezones), false],
	'avatar' 	   => [[], false],
	'newsletter'   => [[0, 1], true],
	'discuss' 	   => [[0, 1], true],
	'ingame' 	   => [PREG_USERNAME, false],
	'raf'          => [PREG_USERNAME, false],
	'website' 	   => [PREG_URL, false],
	'about' 	   => ['/^.{0,1024}$/m', false],
];


if (has_permission('admin.edit_ugroup')) {
	$fields['group_id'] = [array_column($groups, 'id'), true];
}

if (defined('EVO_ADMIN') && has_permission('admin.edit_uprofile', true)) {
	$user_info = App::getUser(App::GET('id'));
}

if (!$user_info) {
	App::setWarning(__('profile.not_found'));
	return;
}

function get_available_avatars(array $user = null)
{
	$providers = Evo\Avatars::getProviders();
	$avatars = [];

	$avatars['Base']['file:/img/avatar.png'] = App::getAsset('/img/avatar.png');

	foreach($providers as $key => $provider) {
		if ($url = $provider($user, '')) {
			$avatars['Base'][$key] = $url;
		}
	}

	$paths = glob(ROOT_DIR.'/upload/avatars/*/');
	$paths[] = ROOT_DIR . '/assets/img/avatars/';
	$paths[] = 'img/avatars/';

	foreach($paths as $cat_dir) {
		$cat = ucfirst(basename($cat_dir));
		$avatars[$cat] = new HtmlSelectGroup();
		if ($pictures = glob($cat_dir . '*.{jpg,jpeg,png,gif}', GLOB_BRACE)) {
			foreach($pictures as $avatar_path) {
				$avatar = ucfirst(basename($avatar_path));
				$avatar = substr($avatar, 0, strrpos($avatar, '.'));
				$path = substr($avatar_path, strlen(ROOT_DIR));
				$avatars[$cat]['file:'.$path] = App::getAsset($path);
			}
		}
	}

	if (!empty($user['id'])) {
		$my_files = File::select(
			'mime_type like ? and origin like ? and poster = ? and size <= 524288',
			'image/%', 'user', $user['id']
		);

		if ($my_files) {
			$label_my_files = __('profile.my_files');
			$avatars[$label_my_files] = new HtmlSelectGroup();

			foreach($my_files as $file) {
				/* TODO: Store the dimensions in the database */
				if ($meta = @getimagesize(ROOT_DIR . $file->path)) {
					if ($meta[0] <= 1024 && $meta[1] <= 1024) {
						$avatars[$label_my_files]['user:'.$file->web_id.'/'.$file->name] = App::getURL('getfile', $file->web_id);
					}
				}
			}
		}
	}

	return $avatars;
}

foreach(get_available_avatars($user_info->toArray()) as $category => $avatars_) {
	$avatars[$category] = new HtmlSelectGroup();
	foreach($avatars_ as $key => $url) {
		$avatars[$category][] = [$key, ucfirst(basename($key)), ['data-src-alt' => $url]];
		$fields['avatar'][0][] = $key;
	}
}


if (IS_POST && !IS_AJAX) {
	if (!has_permission('admin.edit_uprofile') && $ban = App::checkBanlist(App::POST())) {
		$warnings[] = __('profile.edit_cant_mod'). ' ' . html_encode($ban['reason']);
		unset($fields['username'], $fields['email']);
	}

	$form_values = array_intersect_key(App::POST(), $fields); // We keep only valid elements in case of form forgery
	$form_values = $form_values + $reset;

	foreach ($form_values as $field => $value) {
		$f = $fields[$field];
		if (isset($f[2])) {
			$value = preg_replace($f[2], '', $value);
		}
		if ((string)$user_info->$field === $value) {
			continue;
		} elseif (
			((is_array($f[0]) && !in_array($value, $f[0])) // If value not within array
			|| (is_string($f[0]) && !preg_match($f[0], $value))) // OR if not acceptable string
			&& ($f[1] === true || $value !== '') // AND if the parameter is optional or not empty
		) {
			$warnings[$field] = 'Champ invalide: '.$field;
		} elseif ($f[1] === true && $value === '') {
			$warnings[$field] = 'Champ requis: '.$field;
		} else {
			$edits[$field] = $value;
		}
	}

	foreach(Evo\Social::getProviders() as $network => [$name, $icon, $validation]) {
		$account = App::POST('social')[$network] ?? $user_info->social[$network] ?? '';
		if ($account === '' || preg_match($validation, $account)) {
			$social[$network] = $account;
		} else {
			$warnings["social.$network"] = 'Champ invalide: '.$name;
		}
	}
	$edits['social'] = array_filter($social);

	$can_edit_login = defined('EVO_ADMIN') || $user_info->verifyPassword(App::POST('password_old', ''));

	if (!empty($edits['password'])) {
		if ($can_edit_login) {
			$user_info->changePassword($edits['password']);
		} else {
			$warnings['password'] = __('profile.edit_password_warning');
		}
	}
	unset($edits['password']);

	if (!empty($edits['email'])) {
		if (!$can_edit_login) {
			$warnings['email'] = __('profile.edit_email_warning');
			unset($edits['email']);
		}
	}

	if (isset($edits['group_id']) && $group = App::getGroup($edits['group_id'])) {
		if (App::getCurrentUser()->group->priority > $group->priority) {
			$warnings['group_id'] = __('profile.edit_group_warning');
			unset($edits['group_id']);
		}
	}

	if (isset($edits['username']) && App::getUser($edits['username'])) {
		$warnings['username'] = __('profile.edit_username_warning');
		unset($edits['username']);
	}

	if ($warnings) {
		App::setWarning(implode('<br>', $warnings));
	}

	foreach($edits as $key => $value) {
		$user_info->$key = $value;
	}

	if (!empty($edits) && $user_info->save()) {
		if ($warnings) {
			App::setSuccess(__('profile.edit_success1'));
		} else {
			App::setSuccess(__('profile.edit_success2'));
		}

		App::logEvent($user_info->id, 'user', __('profile.edit_success2').''.implode(', ', array_keys($edits)));
		App::trigger('user_updated', [$user_info, $edits]);

		if ($user_info->id !== App::getCurrentUser()->id) {
			App::logEvent($user_info->id, 'admin', __('profile.edit_success2').''.$user_info->username.': '.implode(', ', array_keys($edits)));
		}
	}
}
?>
<legend><?= __('profile.edit_title') ?> : <?= $user_info->username?></legend>
<form method="post" role="form" class="form-horizontal" autocomplete="off">
	<div class="form-group row">
		<label class="col-sm-4 col-form-label text-end" for="username"><?= __('profile.edit_username') ?> :</label>
		<div class="col-sm-6">
			<input class="form-control" name="username" type="text" value="<?= $user_info->username?>">
		</div>
	</div>
	<div class="form-group row">
		<label class="col-sm-4 col-form-label text-end" for="mail"><?= __('profile.edit_email') ?> :</label>
		<div class="col-sm-6">
			<input class="form-control password-required" name="email" type="text" data-old-value="<?= html_encode($user_info->email)?>" value="<?= html_encode($user_info->email)?>">
		</div>
	</div>
	<div class="form-group row">
		<label class="col-sm-4 col-form-label text-end" for="mail"><?= __('profile.edit_country') ?> :</label>
		<div class="col-sm-6">
			<?= Widgets::select('country', COUNTRIES, $user_info->country); ?>
		</div>
	</div>
	<div class="form-group row">
		<label class="col-sm-4 col-form-label text-end" for="mail"><?= __('profile.edit_timezone') ?> :</label>
		<div class="col-sm-6">
			<?= Widgets::select('timezone', $timezones, App::getCurrentUser()->timezone); ?>
		</div>
	</div>
	<div class="form-group row">
		<label class="col-sm-4 col-form-label text-end" for="newsletter"><?= __('profile.edit_options') ?> :</label>
		<div class="col-sm-8">
			<input id="newsletter" name="newsletter" type="checkbox" value="1" <?php if ($user_info->newsletter == 1) echo 'checked';?>>
			<label for="newsletter" class="normal"><?= __('profile.edit_newsletter') ?></label><br>
			<input id="discuss" name="discuss" type="checkbox" value="1" <?php if ($user_info->discuss == 1) echo 'checked';?>>
			<label for="discuss" class="normal"><?= __('profile.edit_discuss_mode') ?></label><br>
		</div>
	</div>
	<div class="form-group row">
		<label class="col-sm-4 col-form-label text-end" for="password"><?= __('profile.edit_password') ?> :</label>
		<div class="col-sm-6">
			<input name="password" type="password" hidden><!-- that's to stop chrome's autocomplete -->
			<input name="password" type="password" data-old-value="" class="form-control password-required" placeholder="<?= __('profile.edit_new_password_ph') ?>">
		<?php if (!defined('EVO_ADMIN')) { ?>
			<br>
			<input name="password_old" type="password" class="form-control" placeholder="<?= __('profile.edit_old_password_ph') ?>">
		<?php } ?>
		</div>
	</div>
	<div class="form-group row">
		<label class="col-sm-4 col-form-label text-end" for="permission"><?= __('profile.edit_rank') ?> : </label>
		<div class="col-sm-6">
			<?php
				$groups = Db::QueryAll('select * from {groups} order by priority asc', true);
				$options = [];
				foreach($groups as $group) {
					$options[] = [
						$group['id'],
						$group['name'],
						['class' => 'group-color-'.$group['color']]
					];
				}
				if (isset($fields['group_id']))
					echo Widgets::select('group_id', $options, $user_info->group_id);
				else
					echo '<label class="col-sm-4 col-form-label text-end group-color-'.$user_info->group->color.'">'.html_encode($user_info->group->name).'</label>';
			?>
		</div>
	</div>
	<div class="form-group row">
		<label class="col-sm-4 col-form-label text-end" for="parrain"><?= __('profile.edit_raf') ?> :</label>
		<div class="col-sm-4">
			<input class="form-control" data-autocomplete="userlist" name="raf" id="parrain" type="text" value="<?= html_encode($user_info->raf)?>" <?php if (!isset($fields['raf'])) echo 'disabled'; ?>>
		</div>
	</div>
	<div class="form-group row">
		<label class="col-sm-4 col-form-label text-end" for="in-game" title="Gamer tag">In-game name:</label>
		<div class="col-sm-6">
			<input class="form-control" id="in-game" name="ingame" type="text" value="<?= html_encode($user_info->ingame)?>" placeholder="<?= __('profile.edit_gametag') ?>">
		</div>
	</div>
	<div class="form-group row">
		<label class="col-sm-4 col-form-label text-end" for="website" title="Site web">Website:</label>
		<div class="col-sm-6">
			<input class="form-control" id="website" name="website" type="text" value="<?= html_encode($user_info->website)?>" placeholder="<?= __('profile.edit_website') ?>">
		</div>
	</div>
	<div class="form-group row">
		<label class="col-sm-4 col-form-label text-end" for="avatar"><?= __('profile.edit_avatar') ?> :</label>
		<div class="col-sm-5">
			<?= Widgets::select('avatar', $avatars, $user_info->avatar, true, ['class' => 'avatar_selector form-control']); ?>
			<span style="margin-left: 10px;position: relative;top: -4px;"><img id="avatar_selector_preview" title="<?= __('profile.edit_avatar_now') ?>" width="42" height="42" src="<?= get_avatar($user_info, 42, true)?>"></span>
		</div>
	</div>

	<div id="avatar_selector_box" class="bg-light p-3 rounded"></div>

	<br><br>

	<?php if ($socialproviders = Evo\Social::getProviders()) { ?>
		<legend><?= __('profile.edit_socialnetworks') ?></legend>

		<?php foreach($socialproviders as $network => [$name, $icon]) { ?>
		<div class="form-group row">
			<label class="col-sm-4 col-form-label text-end" for="<?= $network ?>" title="<?= $name ?>"><i class="fab <?= $icon ?> fa-2x"></i></label>
			<div class="col-sm-6">
				<input class="form-control" id="<?= $network ?>" name="social[<?= $network ?>]" type="text" value="<?= html_encode($user_info->social[$network] ?? '')?>" placeholder="<?= __('profile.edit_social', ['%social%' => $name]) ?>">
			</div>
		</div>
		<?php } ?>
	<?php } ?>

	<legend><?= __('profile.edit_aboutme') ?></legend>
	<div class="form-group row">
		<div class="col-md-12 offset-md-1">
			<textarea id="editor" class="form-control" name="about" placeholder="<?= __('profile.edit_aboutme2') ?>" style="height:250px;"><?= html_encode($user_info->about)?></textarea>
		</div>
	</div>

	<div class="text-center">
		<input class="btn btn-medium btn-primary" type="submit" value="<?= __('profile.edit_btn_register') ?>">
	</div>
</form>
<?php include ROOT_DIR . '/includes/Editors/editors.php'; ?>
<script>
//<!--
	$('select.avatar_selector option[value=""]').attr('data-src-alt', "<?= get_avatar(['email' => $user_info->email], 85, true); ?>");
	$('select.avatar_selector option[value="ingame"]').attr('data-src-alt', "<?= get_avatar(['email' => $user_info->email, 'ingame' => $user_info->ingame], 85, true); ?>");
	$('select.avatar_selector')
		.after('<select style="float: left;width: 200px;" class="form-control" id="cat_only_selectbox"></select>')
		.addClass('d-none');
	$("select.avatar_selector > optgroup").each(function() {
		var f = $(this).children('option');
		var in_group = $(this).children('option[selected]').length;
		if (f.length != 0) {
			$('#cat_only_selectbox').append('<option value="' + f[0].value + '" ' + (in_group ? 'selected':'') + '>' + this.label + '</option>');
		}
	});
	$('#cat_only_selectbox').bind('change keyup', function(e) {
		$('select.avatar_selector').val($(this).val()).change();
	});
	$('.password-required').on('change keyup', function() {
		if ($(this).attr('data-old-value') != $(this).val()) {
			$('input[name="password_old"]').css('background-color', 'pink');
		} else {
			$('input[name="password_old"]').css('background-color', '');
		}
	});

	var invalid = <?= json_encode(array_keys($warnings)); ?>;
	for (let field of invalid) {
		$('input[name="' + field + '"]').css({'border-color': '#ff0000', 'border-width': '2px'});
	}

	load_editor('editor', 'markdown');
// -->
</script>
