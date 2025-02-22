<?php defined('EVO') or die('Que fais-tu là?');

has_permission('admin.manage_forums', true);

$cat_id = (int)App::POST('cat_id');
$edit_mode = App::POST('edit_mode');

if (App::POST('header_change'))
{
	App::setConfig('forums.name', App::POST('forums_name'));
	App::setConfig('forums.description', App::POST('forums_description'));
	App::setSuccess('Changement effectués!');
}
elseif (App::POST('new_category'))
{
	Db::Insert('forums_cat', array('name' => App::POST('category_name'), 'priority' => 0));
	App::setSuccess('Catégorie ajoutée !');
	$edit_mode = false;
}
elseif (App::POST('move_category') && $cat_id)
{
	$direction = App::POST('move_category');
	$categories = Db::QueryAll('SELECT id, name, priority FROM {forums_cat} ORDER BY priority ASC');

	$order = array_column($categories, 'id');
	$pos = array_search($cat_id, $order);

	if (($direction == -1 && $pos > 0) || ($direction == 1 && $pos < count($order) -1)) {
		$order[$pos] = $order[$pos + $direction];
		$order[$pos + $direction] = $cat_id;

		foreach($order as $pos => $id) {
			Db::Update('forums_cat', ['priority' => $pos], ['id' => $id]);
		}
	}
	$edit_mode = false;
}
elseif (App::POST('edit_category') && App::POST('category_name') && $cat_id)
{
	Db::Update('forums_cat', ['name' => App::POST('category_name')], ['id' => $cat_id]);
	App::setSuccess('Catégorie renommée !');
	unset(App::$POST['edit_category']);
	$edit_mode = false;
}
elseif (App::POST('add_forum') !== null && App::POST('name'))
{
	if (App::POST('add_forum')) {
		Db::Update('forums', [
			'cat'         => App::POST('cat'),
			'priority'    => App::POST('priority'),
			'name'        => App::POST('name'),
			'description' => App::POST('description'),
			'icon'        => App::POST('icon'),
			'redirect'    => App::POST('redirect'),
		], ['id' => App::POST('add_forum')]);

		if (Db::$affected_rows) {
			App::setSuccess('Forum mis à jour !');
			$forum_id = App::POST('add_forum');
			$edit_mode = false;
		} else {
			App::setWarning("Erreur lors de l'enregistrement");
		}
	} else {
		Db::Insert('forums', array(
			'cat'         => App::POST('cat'),
			'priority'    => App::POST('priority'),
			'name'        => App::POST('name'),
			'description' => App::POST('description'),
			'icon'        => App::POST('icon'),
			'redirect'    => App::POST('redirect')
		));
		if ($forum_id = Db::$insert_id) {
			App::setSuccess('Forum ajouté !');
			App::logEvent(0, 'forum', 'Création du forum "' . App::POST('name') . '"');
			$edit_mode = false;
		} else {
			App::setWarning("Erreur lors de l'enregistrement");
		}
	}

	if (!empty($forum_id)) {
		$values = $args = array();

		foreach(['read', 'write', 'moderation'] as $perm) {
			if (!empty(App::POST('perms')[$perm])) {
				foreach(App::POST('perms')[$perm] as $group) {
					$values[] = '("forum.'.$perm.'", ?, ?, 1)';
					$args[] = $forum_id;
					$args[] = $group;
				}
			}
		}

		if ($values) {
			Db::Delete('permissions', 'related_id = ? and name like "forum.%"', $forum_id);
			Db::Exec('replace into {permissions} (name, related_id, group_id, value) VALUES '.implode(',', $values), $args);
		}
	}
}
elseif (App::POST('del_forum'))
{
	if (Db::Delete('forums', ['id' => App::POST('del_forum')])) {
		$topics = Db::QueryAll('SELECT * from {forums_topics} WHERE forum_id = ?', App::POST('del_forum'));
		foreach($topics as $topic) {
			Db::Delete('forums_topics', ['id' => $topic['id']]);
			Db::Delete('forums_posts', ['topic_id' => $topic['id']]);
		}
		Db::Delete('permissions', 'related_id = ? and name like "forum.%"', App::POST('del_forum'));
		App::setSuccess('Élément supprimé!');
		$edit_mode = false;
	} else {
		App::setWarning((string)Db::$error);
	}
}
elseif (App::POST('reorder_forums'))
{
	foreach(App::POST('reorder_forums') as $cat => $forums) {
		foreach($forums as $priority => $k) {
			if ($k) Db::Update('forums', ['priority' => $priority], ['id' => $k]);
		}
	}
	App::setSuccess('Forum enregistré!');
	$edit_mode = false;
}
elseif (App::POST('delete_category'))
{
	if (Db::Get('SELECT * FROM {forums} WHERE cat = ?', $cat_id)) {
		App::setWarning('Vous ne pouvez supprimer une catégorie contenant des forums.');
	} else {
		Db::Delete('forums_cat', ['id' => $cat_id]);
		App::setSuccess('Catégorie supprimée !');
	}
	$edit_mode = false;
}

$empty_elem = [
	'id' => '', 'cat' => 0, 'name' => '', 'icon' => '', 'description' => '', 'priority' => 0, 'redirect' => '',
	'forum.read' => [], 'forum.write' => [], 'forum.moderation' => [],
];

$cur_elem = $empty_elem;

$groups = Db::QueryAll('SELECT id, color, name FROM {groups} ORDER BY priority ASC, id DESC', true);
$forums = Db::QueryAll('SELECT * FROM {forums} ORDER BY priority ASC, id ASC', true);
$categories = Db::QueryAll('SELECT id, name, priority FROM {forums_cat} ORDER BY priority ASC', true);
$perms = Db::QueryAll('SELECT * FROM {permissions} WHERE name LIKE "forum.%"');

foreach($categories as $id => $c) {
	$cat_select[$c['id']] = $c['name'];
	$categories[$id]['forums'] = [];
}

foreach($perms as $p) {
	if (!isset($forums[$p['related_id']])) // Some Cleanup
		Db::Delete('permissions', 'related_id = ? and name like "forum.%"', $p['related_id']);
	elseif ($p['value'])
		$forums[$p['related_id']][$p['name']][] = $p['group_id'];
}

foreach($forums as $forum) {
	$categories[$forum['cat']]['forums'][] = $forum + $empty_elem;
	if (App::POST('edit_forum') == $forum['id'])
		$cur_elem = $forum;
}
?>

<legend><a href="?page=forums">Éditeur de forums</a></legend>

<?php if (!$edit_mode): ?>
<div class="card">
	<div class="card-header"><strong>Entête</strong></div>
	<div class="card-body">
	<form class="form-horizontal" role="form" method="post">
	  <div class="form-group row">
		<label class="col-sm-4 col-form-label text-right">Titre du forum</label>
		<div class="col-sm-5">
		  <input type="text" class="form-control" name="forums_name" placeholder="<?=html_encode(App::getConfig('name'))?>" value="<?=html_encode(App::POST('forums_name', App::getConfig('forums.name')))?>">
		</div>
	  </div>
	  <div class="form-group row">
		<label class="col-sm-4 col-form-label text-right">Description du forum</label>
		<div class="col-sm-5">
		  <input type="text" class="form-control" name="forums_description" placeholder="<?=html_encode(App::getConfig('description'))?>" value="<?=html_encode(App::POST('forums_description', App::getConfig('forums.description')))?>">
		  <small>Vous pouvez utiliser des bbcode dans la description.</small>
		</div>
	  </div>
	  <div class="text-center">
		<button type="submit" name="header_change" value="1" class="btn btn-primary">Enregistrer</button>
	  </div>
	</form>
	</div>
</div>
<div>&nbsp;</div>
<?php endif; ?>


<?php if (!$edit_mode): ?>
<?php
foreach($categories as $id => $c) {
	echo '<form method="post">
			<input type="hidden" name="edit_mode" value="1">
			<input type="hidden" name="cat_id" value="'.$id.'">
			<div class="card">
				<div class="card-header">
					<div class="btn-group float-right">
						<button name="move_category" value="-1" class="btn btn-sm btn-info">↑</button>
						<button name="move_category" value="1" class="btn btn-sm btn-info">↓</button>
						<button name="edit_category" value="1" class="btn btn-sm btn-info">Renommer</button>
						<button name="delete_category" value="1" class="btn btn-sm btn-danger">Supprimer</button>
					</div>
					Catégorie: <strong>' . $c['name'] . '</strong>
				</div>
				<div class="card-body" id="cat'.$id.'">
					<table class="table sortable" id="reorder_forums['.$id.']" style="width:100%">
						<tbody>';

	foreach($c['forums'] as $forum) {
		echo '<tr id="' . $forum['id'] . '">';
			echo '<td><a href="'.App::getURL('forums', $forum['id']).'">'. html_encode($forum['name']) . '</a><br>'.
				($forum['redirect'] ? '<em>Redirection: <strong>'.$forum['redirect'].'</strong></em><br>':'').'
				<small>'. bbcode2html($forum['description']) .'</small><br>
				</td>';
			echo '<td style="width:4em;"><i class="'.$forum['icon'].'"></i></td>';
			echo '<td style="width:8em;">'.$forum['num_posts'].' posts</td>';
			echo '<td style="min-width:40%"><small>Lecture: ';

			if (!isset($forum['forum.read']))
				echo '<strong>Personne</strong>';
			elseif (!array_diff(array_keys($groups), $forum['forum.read']))
				echo '<strong>Tout le monde</strong>';
			else foreach($forum['forum.read'] as $group)
				if (isset($groups[$group]))
					echo '<i><span class="group-color-' . $groups[$group]['color'] . '">' . $groups[$group]['name'] . '</span></i> ';

			echo '<br>Écriture: ';

			if (!isset($forum['forum.write']))
				echo '<strong>Personne</strong>';
			elseif (!array_diff(array_keys($groups), $forum['forum.write']))
				echo '<strong>Tout le monde</strong>';
			else foreach($forum['forum.write'] as $group)
				if (isset($groups[$group]))
					echo '<i><span class="group-color-' . $groups[$group]['color'] . '">' . $groups[$group]['name'] . '</span></i> ';

			echo '<br>Modération: ';

			if (!isset($forum['forum.moderation']))
				echo '<strong>Modérateur globaux seulement</strong>';
			elseif (!array_diff(array_keys($groups), $forum['forum.moderation']))
				echo '<strong>Tout le monde</strong>';
			else foreach($forum['forum.moderation'] as $group)
				if (isset($groups[$group]))
					echo '<i><span class="group-color-' . $groups[$group]['color'] . '">' . $groups[$group]['name'] . '</span></i> ';

			echo '</small></td>';
			echo '<td style="width:90px;">'.
					'<button name="edit_forum" value="'.$forum['id'].'" class="btn btn-sm btn-primary" title="Éditer cet élément"><i class="fa fa-pencil-alt"></i></button> '.
					'<button name="del_forum" value="'.$forum['id'].'" class="btn btn-sm btn-danger" title="Supprimer cet élément" onclick="return confirm(\'Sur?\');"><i class="far fa-trash-alt"></i></button>'.
				'</td>';

		echo '</tr>';
	}
	echo '</tbody></table></div></div></form><div>&nbsp;</div>';
}
?>
<?php endif; ?>

<?php if ($categories && !App::POST('edit_category')): ?>
	<div class="card" id="edit-forum">
		<div class="card-header"><strong>
			<?= $cur_elem['id'] ? 'Modifier le forum #'.$cur_elem['id'] : 'Ajouter un forum' ?>
		</strong></div>
		<div class="card-body">
	<form class="form-horizontal" method="post" action="#">
		<div class="form-group row">
			<label class="col-sm-3 col-form-label text-right" for="name">Nom :</label>
			<div class="col-sm-8 controls">
				<input class="form-control" name="name" type="text" value="<?= html_encode($cur_elem['name'])?>">
			</div>
		</div>
		<div class="form-group row">
			<label class="col-sm-3 col-form-label text-right" for="description">Description :</label>
			<div class="col-sm-8 controls">
				<input class="form-control" name="description" type="text" value="<?= html_encode($cur_elem['description'])?>">
				<small>Vous pouvez utiliser des bbcode dans la description.</small>
			</div>
		</div>
		<div class="form-group row">
			<label class="col-sm-3 col-form-label text-right" for="redirect">Redirection :</label>
			<div class="col-sm-8 controls">
				<input class="form-control" name="redirect" type="text" placeholder="Exemple: https://google.ca" value="<?= html_encode($cur_elem['redirect']) ?>">
				<small>Afficher un lien externe dans la liste de forums. <strong>Le forum ne sera plus accessible</strong>.</small>
			</div>
		</div>
		<div class="form-group row">
			<label class="col-sm-3 col-form-label text-right" for="permission">Accès :</label>
			<div class="col-sm-2">
				<strong>Lecture</strong>
				<?php
					echo '<select class="form-control" size="'.count($groups).'" name="perms[read][]" multiple>';
					foreach($groups as $group) {
						$selected = in_array($group['id'], $cur_elem['forum.read'] ?? []) || ($group['id'] != 0 && !$cur_elem['id']);
						echo '<option class="group-color-'.$group['color'].'" value="'.$group['id'].'" '.($selected ?'selected="selected"':'').'>'.
								html_encode($group['name']).'</option>';
					}
					echo '</select>';
				?>
			</div>
			<div class="col-sm-2">
				<strong>Écriture</strong>
				<?php
					echo '<select class="form-control" size="'.count($groups).'" name="perms[write][]" multiple>';
					foreach($groups as $group) {
						$selected = in_array($group['id'], $cur_elem['forum.write'] ?? []) || ($group['id'] != 4 && !$cur_elem['id']);
						echo '<option class="group-color-'.$group['color'].'" value="'.$group['id'].'" '.($selected ?'selected="selected"':'').'>'.
								html_encode($group['name']).'</option>';
					}
					echo '</select>';
				?>
			</div>
			<div class="col-sm-2">
				<strong>Modération</strong>
				<?php
					echo '<select class="form-control" size="'.count($groups).'" name="perms[moderation][]" multiple>';
					foreach($groups as $group) {
						$selected = in_array($group['id'], $cur_elem['forum.moderation'] ?? []);
						echo '<option class="group-color-'.$group['color'].'" value="'.$group['id'].'" '.($selected ?'selected="selected"':'').'>'.
								html_encode($group['name']).'</option>';
					}
					echo '</select>';
				?>
			</div>
		</div>
		<div class="form-group row">
			<label class="col-sm-3 col-form-label text-right" for="icon">Icône :</label>
			<div class="col-sm-8 controls">
			<?= Widgets::iconSelect('icon', $cur_elem['icon']) ?>
			</div>
		</div>
		<div class="form-group row">
			<label class="col-sm-3 col-form-label text-right" for="account">Catégorie :</label>
			<div class="col-sm-8 controls" style="font-family: 'Font Awesome 5 Free', 'Font Awesome 5 Brands', 'sans-serif'">
				<?= Widgets::select('cat', $cat_select, $cur_elem['cat'], false) ?>
			</div>
		</div>
		<div class="form-group row">
			<label class="col-sm-3 col-form-label text-right" for="account">Ordre :</label>
			<div class="col-sm-8 controls">
				<?= Widgets::select('priority', array_keys(array_fill(0, 100, '')), $cur_elem['priority']) ?>
			</div>
		</div>
		<div class="text-center">
			<button class="btn btn-medium btn-primary" name="add_forum" value="<?= $cur_elem['id'] ?>" type="submit">Enregistrer le forum</button>
			<button class="btn btn-danger">Annuler</button>
		</div>
	</form>
		</div>
	</div>
<?php endif; ?>

<div>&nbsp;</div>

<?php if (!App::POST('add_forum')): ?>
<div class="card">
	<div class="card-header">
		<strong><?= $edit_mode ? 'Renommer la catégorie' : 'Créer une catégorie' ?></strong>
	</div>
	<div class="card-body">
	<form class="form-horizontal" role="form" style="margin-bottom: -13px;" method="post">
	  <div class="form-group row">
		<label class="col-sm-4 col-form-label text-right">Nom de la catégorie</label>
		<div class="col-sm-5">
		  <input type="text" class="form-control" name="category_name" value="<?= html_encode($categories[$cat_id]['name'] ?? '') ?>">
		</div>
	<?php if ($edit_mode): ?>
		<input type="hidden" value="<?= $cat_id ?>" name="cat_id">
		<button type="submit" name="edit_category" value="<?= $cat_id; ?>" class="btn btn-success" style="margin-top: 2px;">Renommer la catégorie</button>
		<button type="submit" name="cancel" value="" class="btn btn-danger" style="margin-top: 2px;">Annuler</button>
	<?php else: ?>
		<button type="submit" name="new_category" value="1" class="btn btn-success" style="margin-top: 2px;">Créer la catégorie</button>
	<?php endif; ?>
	  </div>
	</form>
	</div>
</div>
<?php endif; ?>
