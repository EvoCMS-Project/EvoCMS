<?php defined('EVO') or die('Que fais-tu là ?');
has_permission(null, true);

$user_info = App::getCurrentUser();
$groups = Db::QueryAll('select * from {groups} order by priority asc');
$timezones = generate_tz_list();
$warnings = $avatars = [];

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

[$avatars, $avatar_keys] = profile_avatar_select_options($user_info);
$fields['avatar'][0] = $avatar_keys;

$user_info = profile_process_user_update($user_info, defined('EVO_ADMIN'));
?>
<legend><?= __('profile.edit_title') ?> : <?= $user_info->username?></legend>
<form method="post" role="form" class="form-horizontal" autocomplete="off">
	<div class="mb-3 row">
		<label class="col-sm-4 col-form-label text-end" for="username"><?= __('profile.edit_username') ?> :</label>
		<div class="col-sm-6">
			<input class="form-control" name="username" type="text" value="<?= $user_info->username?>">
		</div>
	</div>
	<div class="mb-3 row">
		<label class="col-sm-4 col-form-label text-end" for="mail"><?= __('profile.edit_email') ?> :</label>
		<div class="col-sm-6">
			<input class="form-control password-required" name="email" type="text" data-old-value="<?= html_encode($user_info->email)?>" value="<?= html_encode($user_info->email)?>">
		</div>
	</div>
	<div class="mb-3 row">
		<label class="col-sm-4 col-form-label text-end" for="mail"><?= __('profile.edit_country') ?> :</label>
		<div class="col-sm-6">
			<?= Widgets::select('country', COUNTRIES, $user_info->country); ?>
		</div>
	</div>
	<div class="mb-3 row">
		<label class="col-sm-4 col-form-label text-end" for="mail"><?= __('profile.edit_timezone') ?> :</label>
		<div class="col-sm-6">
			<?= Widgets::select('timezone', $timezones, $user_info->timezone); ?>
		</div>
	</div>
	<div class="mb-3 row">
		<label class="col-sm-4 col-form-label text-end" for="newsletter"><?= __('profile.edit_options') ?> :</label>
		<div class="col-sm-8">
			<input id="newsletter" name="newsletter" type="checkbox" value="1" <?php if ($user_info->newsletter == 1) echo 'checked';?>>
			<label for="newsletter" class="normal"><?= __('profile.edit_newsletter') ?></label><br>
			<input id="discuss" name="discuss" type="checkbox" value="1" <?php if ($user_info->discuss == 1) echo 'checked';?>>
			<label for="discuss" class="normal"><?= __('profile.edit_discuss_mode') ?></label><br>
		</div>
	</div>
	<div class="mb-3 row">
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
	<div class="mb-3 row">
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
	<div class="mb-3 row">
		<label class="col-sm-4 col-form-label text-end" for="parrain"><?= __('profile.edit_raf') ?> :</label>
		<div class="col-sm-4">
			<input class="form-control" data-autocomplete="userlist" name="raf" id="parrain" type="text" value="<?= html_encode($user_info->raf)?>" <?php if (!isset($fields['raf'])) echo 'disabled'; ?>>
		</div>
	</div>
	<div class="mb-3 row">
		<label class="col-sm-4 col-form-label text-end" for="in-game" title="Gamer tag">In-game name:</label>
		<div class="col-sm-6">
			<input class="form-control" id="in-game" name="ingame" type="text" value="<?= html_encode($user_info->ingame)?>" placeholder="<?= __('profile.edit_gametag') ?>">
		</div>
	</div>
	<div class="mb-3 row">
		<label class="col-sm-4 col-form-label text-end" for="website" title="Site web">Website:</label>
		<div class="col-sm-6">
			<input class="form-control" id="website" name="website" type="text" value="<?= html_encode($user_info->website)?>" placeholder="<?= __('profile.edit_website') ?>">
		</div>
	</div>
	<div class="mb-3 row">
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
		<div class="mb-3 row">
			<label class="col-sm-4 col-form-label text-end" for="<?= $network ?>" title="<?= $name ?>"><i class="fa-brands <?= $icon ?> fa-2x"></i></label>
			<div class="col-sm-6">
				<input class="form-control" id="<?= $network ?>" name="social[<?= $network ?>]" type="text" value="<?= html_encode($user_info->social[$network] ?? '')?>" placeholder="<?= __('profile.edit_social', ['%social%' => $name]) ?>">
			</div>
		</div>
		<?php } ?>
	<?php } ?>

	<legend><?= __('profile.edit_aboutme') ?></legend>
	<div class="mb-3 row admin-profile-about-row">
		<div class="col-sm-6 offset-sm-4">
			<textarea id="editor" class="form-control" name="about" placeholder="<?= __('profile.edit_aboutme2') ?>" style="height:250px;"><?= html_encode($user_info->about)?></textarea>
		</div>
	</div>

	<div class="text-center">
		<input class="btn btn-primary" type="submit" value="<?= __('profile.edit_btn_register') ?>">
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

	load_editor('editor', 'markdown');
// -->
</script>
