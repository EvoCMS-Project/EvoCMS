<?php defined('EVO') or die('Que fais-tu là?');

has_permission('admin.manage_menu', true);

if (isset(App::$POST['add_menu'], App::$POST['name']) && !empty(App::$POST['name'])) {
	if (App::$POST['add_menu'] == 0 && Db::Insert('menu', ['parent'=>App::$POST['parent'], 'priority'=>App::$POST['priority'], 'name'=>App::$POST['name'], 'icon'=>App::$POST['icon'], 'link'=>App::$POST['link']?:App::$POST['internal_page'],'visibility'=>App::$POST['visibility']]))
		App::setSuccess('Élément ajouté!');
	elseif (Db::Update('menu',  ['parent' => App::$POST['parent'], 'priority' => App::$POST['priority'], 'name' => App::$POST['name'], 'icon' => App::$POST['icon'], 'link' => App::$POST['link'] ?: App::$POST['internal_page'], 'visibility' => App::$POST['visibility']], ['id' => App::$POST['add_menu']]))
		App::setSuccess('Élément mis à jour!');
	elseif(Db::$errno != 0)
		App::setWarning((string)Db::$error);
}
elseif (isset(App::$POST['del_menu'])) {
	if (Db::Delete('menu', ['id' => App::$POST['del_menu']])) {
		App::setSuccess('Élément supprimé!');
	} else {
		App::setWarning('Élément déjà supprimé!');
	}
}
elseif (isset(App::$POST['menu-editor'])) {
	foreach(App::$POST['menu-editor'] as $priority => $k) {
		if ($k) Db::Update('menu', ['priority' => $priority], ['id' => $k]);
	}
	App::setSuccess('Menu enregistré!');
}

$parent_list = [0 => ''];
$cur_elem = ['id' => '', 'parent' => 0, 'name' => '', 'icon' => '', 'link' => '', 'priority' => 0, 'page_name' => null, 'visibility' => 0];
$tree = get_menu_tree(true, $items);

if (isset(App::$POST['edit_menu']) && isset($items[App::$POST['edit_menu']])) {
	$cur_elem =	$items[App::$POST['edit_menu']];
}

function display_tree(int $id, $level, &$tree, &$parent_list) {
	foreach ($tree[$id] as &$menu) {
		$parent_list[$menu['id']] = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level).html_encode($menu['name']);
		echo '<tr id="' . $menu['id'] . '">';
			echo '<td>'.str_repeat('<span class="fa fa-arrow-right"></span> ', $level) . $menu['name'].'</td>';
			echo '<td><i class="fa fa-'.$menu['icon'].'"></i></td>';
			echo '<td>'.$menu['priority'].'</td>';
			if (is_null($menu['page_name']))
				echo '<td><a href="'.html_encode(strpos($menu['link'], '/') ? $menu['link'] : App::getURL($menu['link'])).'">'.html_encode(Format::truncate($menu['link'], 40)).'</a></td>';
			else
				echo '<td><a href="'.App::getURL($menu['link']).'">'.html_encode(Format::truncate($menu['page_name'], 40)).'</a></td>';
			echo '<td>'.
					'<button name="edit_menu" value="'.$menu['id'].'" class="btn btn-sm btn-primary" title="Éditer cet élément"><i class="fa fa-pencil-alt"></i></button>&nbsp;'.
					'<button name="del_menu" value="'.$menu['id'].'" class="btn btn-sm btn-danger" title="Supprimer cet élément" onclick="return confirm(\'Sur?\');"><i class="far fa-trash-alt"></i></button>'.
				 '</td>';

		echo '</tr>';
		if (isset($tree[$menu['id']])) {
			$level++;
			display_tree($menu['id'], $level, $tree, $parent_list);
			$level--;
		}
	}
}

$user_pages = Db::QueryAll('select p.page_id, title  from {pages} as p join {pages_revs} as r ON r.page_id = p.page_id AND r.revision = p.revisions order by pub_date desc, title asc');
$cat_pages = [];
foreach(Db::QueryAll('SELECT DISTINCT category from {pages} WHERE category <> ""') as $cat) {
	$cat_pages[strtr("category/{$cat['category']}", ' ', '-')] = $cat['category'];
}

$pages = [
	''           => '---',
	'Pages'      => new HtmlSelectGroup(array_column($user_pages, 'title', 'page_id')),
	'Categories' => new HtmlSelectGroup($cat_pages),
	'Internes'   => new HtmlSelectGroup(array_combine(INTERNAL_PAGES, array_map('ucwords', INTERNAL_PAGES))),
];
?>
<legend>Éditeur de menus</legend>
<form method="post"  action="#edit">
<?php if (!$tree): ?>
	<div class="text-center alert alert-warning">Aucun élément trouvé!</div>
<?php else: ?>
	<table class="table sortable" id="menu-editor">
		<thead>
			<tr>
				<th>Nom</th>
				<th></th>
				<th>Ordre</th>
				<th>Adresse</th>
				<th style="width:90px;"> </th>
			</tr>
		</thead>
		<?php display_tree(0, 0, $tree, $parent_list); ?>
	</table>
<?php endif; ?>
</form>
<br>
<a name="edit"></a>
<div class="card">
	<div class="card-header">
	<?php
		if ($cur_elem['id'])
			echo '<strong>Modifier l\'élément #'.$cur_elem['id'].'</strong>';
		else
			echo '<strong>Ajouter un élément</strong>';
	?>
	</div>
	<div class="card-body">
	<form class="form-horizontal" method="post" action="#">
	<div class="form-group row">
		<label class="col-sm-3 col-form-label text-right" for="account">Nom :</label>
		<div class="col-sm-8 controls">
			<input class="form-control" name="name" type="text" value="<?php echo html_encode($cur_elem['name'])?>">
		</div>
	</div>
	<div class="form-group row">
		<label class="col-sm-3 col-form-label text-right" for="account">Icône :</label>
		<div class="col-sm-8 controls">
		<?= Widgets::iconSelect('icon', $cur_elem['icon']) ?>
		</div>
	</div>
	<div class="form-group row">
		<label class="col-sm-3 col-form-label text-right" for="account">Parent :</label>
		<div class="col-sm-8 controls">
			<?= Widgets::select('parent', $parent_list, $cur_elem['parent'], false) ?>
		</div>
	</div>
	<div class="form-group row">
		<label class="col-sm-3 col-form-label text-right" for="account">Ordre :</label>
		<div class="col-sm-8 controls">
			<?= Widgets::select('priority', array_keys(array_fill(0, 100, '')), $cur_elem['priority']) ?>
		</div>
	</div>
	<div class="form-group row">
		<label class="col-sm-3 col-form-label text-right" for="account">Lien :</label>
		<div class="col-sm-8 controls">
			<input class="form-control" name="link" id="link" type="text" value="<?php echo $cur_elem['page_name'] ? '' : html_encode($cur_elem['link'])?>">
			ou
			<?= Widgets::select('internal_page', $pages, $cur_elem['link']) ?>
			<script> $('#internal_page').change(function() { $('#link').val(''); }); </script>
		</div>
	</div>
	<div class="form-group row">
		<label class="col-sm-3 col-form-label text-right" for="account">Visibilité :</label>
		<div class="col-sm-8 controls">
		<?php
			echo '<select class="form-control" name="visibility">';
			echo '<option value="0">Tout le monde</option>';
			echo '<option value="1" '. ($cur_elem['visibility'] == 1 ? 'selected':'').'>Membres seulement</option>';
			echo '<option value="2" '. ($cur_elem['visibility'] == 2 ? 'selected':'').'>Invités seulement</option>';
			echo '</select>';
		?>
		</div>
	</div>
	<div class="text-center">
		<button class="btn btn-medium btn-primary" name="add_menu" value="<?php echo $cur_elem['id']?>" type="submit">Enregistrer le menu</button>  <button class="btn btn-danger">Annuler</button>
	</div>
</form>
	</div>
</div>
