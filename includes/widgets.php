<?php
use \Evo\Models\File;

/*
 * Evo-CMS
 */
class Widgets
{
	/**
	 *  Display the main menu
	 */
	public static function menu()
	{
		return '<div id="menu">' . self::menu_branch(get_menu_tree(), 0) . '</div>';
	}


	/**
	 *  Returns a branch from the menu, meant to be used by menu()
	 */
	public static function menu_branch($tree, $id = 0)
	{
		if (!isset($tree[$id]) || !$tree[$id]) return;

		$current_page = trim(App::$REQUEST_PAGE, '/');

		$r = '<ul>';

		foreach ($tree[$id] as $menu) {
			if (($menu['visibility'] == 1 && !has_permission()) || ($menu['visibility'] == 2 && has_permission())) {
				continue;
			}

			empty($menu['slug']) or $menu['link'] = $menu['slug'];
			empty($menu['redirect']) or $menu['link'] = $menu['redirect'];

			if (!empty($menu['link']) && trim($menu['link'], '/') === $current_page) {
				$r .= '<li class="active">';
			} else {
				$r .= '<li>';
			}

			if ($menu['link'] != '')
				$r .= '<a href="' . html_encode(strpos($menu['link'], '/') !== false ? $menu['link'] : App::getURL($menu['link'])) . '">';

			if ($menu['icon'])
				$r .= '<i class="fa-fw ' . $menu['icon'] . '"></i> ';

			$r .= html_encode($menu['name']);

			if ($menu['link'] != '')
				$r .= '</a>';

			if (isset($tree[$menu['id']])) {
				$r .= self::menu_branch($tree, $menu['id']);
			}

			$r .= '</li>';
		}
		$r .= '</ul>';
		return $r;
	}


	public static function filebox($id, $caption = '', $size = '', $link_attributes = [], $class = '')
	{
		if (!$file = File::find($id, 'web_id')) {
			return '[File not found]';
		}

		$link_attributes  = self::htmlAttributes($link_attributes + ['href' => "?p=getfile&id={$file->web_id}/{$file->name}"]);
		$media_attributes = self::htmlAttributes([
			'src' => "?p=getfile&id={$file->web_id}/{$file->name}" . ($size ? '&size=' . $size : ''),
			'alt' => $file->name,
		]);

		if (stripos($file->mime_type, 'image/') === 0 || $size) {
			$filebox = "<figure class='file-block image $class'>";
			$filebox .= "<a $link_attributes class='fancybox-image'><img $media_attributes /></a>";
		} elseif (stripos($file->mime_type, 'video/') === 0) {
			$filebox = "<figure class='file-block video $class'>";
			$filebox .= "<video $media_attributes controls></video>";
		} elseif (stripos($file->mime_type, 'audio/') === 0) {
			$filebox = "<figure class='file-block audio $class'>";
			$filebox .= "<audio $media_attributes controls></audio>";
		} else {
			$filebox = "<figure class='file-block $class'>";
			$filebox .= "<a $link_attributes></a>";
		}

		if ($caption !== false) {
			$filebox .= '<figcaption><a '.$link_attributes.' class="no-fancy"><strong>'.html_encode($caption ?: $file->caption).'</strong></a><br>';
			$filebox .= '<small>('.Format::size($file->size).')</small></figcaption>';
		}

		$filebox .= "</figure>\n";

		return $filebox;
	}


	/**
	 *  Display a list of recent pages
	 */
	public static function recentPages($num_pages = 5, $truncate = 28)
	{
		/*
		$pages = get_pages($num_pages, 0, array('select' => 'r.title, p.slug, p.pub_date'));

		echo '<div class="widget pages-widget">';

		if ($pages) {
			echo '<strong>Pages récentes:</strong>';
			echo '<ul>';
			foreach ($pages as $page) {
				echo '<li><a href="' . App::getURL($page['slug']) . '"
				title="' . html_encode($page['title']) . '">' .
					html_encode(Format::truncate($page['title'], $truncate)) . '</a><br><small>' . Format::today($page['pub_date']) . '</small></li>';
			}
			echo '</ul>';
		} else {
			echo 'Rien à afficher';
		}
		echo '</div>';
		*/
	}


	/**
	 *  Display a list of recent comments
	 */
	public static function recentComments($num_comments = 5, $truncate = 75)
	{
		$comments = Db::QueryAll('SELECT * from {comments} JOIN {pages} USING(page_id) ORDER BY id DESC LIMIT 0, ?', $num_comments);

		echo '<div class="widget comments-widget">';

		if ($comments) {
			echo '<strong>Commentaires récents:</strong>';
			echo '<ul>';
			foreach ($comments as $comment) {
				echo '<li><em>' . html_encode(Format::truncate($comment['message'], $truncate)) . '</em><br>
						<a href="' . App::getURL($comment['slug'] . '#msg' . $comment['id']) . '">' .
					html_encode(Format::truncate($comment['slug'], 30)) . '</a><br><small>' . Format::today($comment['posted']) . '</small></li>';
			}
			echo '</ul>';
		} else {
			echo 'Rien à afficher';
		}
		echo '</div>';
	}


	/**
	 *  Display a list of page categories and the number of pages in each
	 */
	public static function categories()
	{
		$categories = Db::QueryAll('SELECT category, count(*) as cnt from {pages} WHERE pub_date > 0 GROUP BY category', true);

		echo '<div class="widget categories-list">';

		if ($categories) {
			echo '<ul>';
			foreach ($categories as $cat) {
				if (empty($cat['category'])) {
					echo '<li><a href="#"><span class="category">Non classé</span><span class="number">('. $cat['cnt'] .')</span></a></li>';
				} else {
					echo '<li><a href="' . App::getURL('category/' . strtolower(urlencode(str_replace(' ', '-', $cat['category'])))) . '"><span class="category">'.
						html_encode($cat['category']) . '</span><span class="number">('. $cat['cnt'] .')</span></a></li>';
				}
			}
			echo '</ul>';
		} else {
			echo 'Rien à afficher';
		}
		echo '</div>';
	}


	/**
	 *  Print SQL queries in a fancy way for debug purposes
	 */
	public static function sqlQueries(array $queries)
	{
		$r = '';
		foreach ($queries as $i => $query) {
			$q = preg_replace('#\s+#mu', ' ', $query['query']);
			$q = preg_replace_callback(
				array(
					'#(?<string>("[^"]*"|\'[^\']*\'))#mui',
					'#(?<symbol>\s[-()<>=\*+\?\s]+)\s#mui',
					'#(?<name>\s?`?[_a-z0-9]+`?\.|`[_a-z0-9]+`)#mui',
					'#(^|[^a-z0-9])(?<function>[_a-z]+)\(#mui',
					// '#(^|[^a-z0-9])(?<newline>SELECT|INSERT|REPLACE|UPDATE|UNION|RIGHT\sJOIN|LEFT\sJOIN|JOIN|FROM|ORDER\sBY|LIMIT|VALUES|WHERE|GROUP\sBY)([^a-z0-9])#mui',
					'#(?<newline>SELECT|SELECT\s*DISTINCT|INSERT\s*INTO|REPLACE\s*INTO|UPDATE|UNION|RIGHT\s*JOIN|LEFT\s*JOIN|JOIN|FROM|ORDER\sBY|LIMIT|VALUES|WHERE|GROUP\sBY)#mui',
					'#(^|[^a-z0-9])(?<inline>ON|AS|IS NULL|IS|IN|CASE\s*WHEN|THEN|ELSE|END\s*AS|END|LIKE|NULL|AND|OR|SET|DESC|ASC)([^a-z0-9]|$)#mui',
				),

				function ($m) {
					if (isset($m['newline'])) {
						return "\n" . '<span style="color:#708">' . strtoupper($m['newline']) . ' </span> ';
					} elseif (isset($m['inline'])) {
						return $m[1] . '<span style="color:#708">' . strtoupper($m['inline']) . '</span>' . $m[3];
					} elseif (isset($m['symbol'])) {
						return ' <span style="color:#FF00FF">' . trim($m['symbol']) . ' </span> ';
					} elseif (isset($m['function'])) {
						return ' <span style="color:#FF794C"> ' . strtoupper(trim($m['function'])) . '</span>(';
					} elseif (isset($m['name'])) {
						return '<span style="color:#05A">' . $m['name'] . '</span>';
					} elseif (isset($m['string'])) {
						return '<span style="color:#D90000"> ' . html_encode($m['string']) . '</span> ';
					}
				},
				$q . ' '
			);

			$r .= '<div class="card text-left mb-4 sql-query">';
			$r .= '  <div class="card-header ' . ($query['errno'] ? 'text-white bg-danger' : '')  . '">';

			if ($query['errno'])
				$r .= '  	' . $query['errno'] . ' ' . $query['error'];

			$r .= $i . '. ' . implode('', explode(ROOT_DIR, $query['trace']['file'], 2)) . ' #' . $query['trace']['line'];

			$r .= '  </div>';
			$r .= '  <div class="card-body">' . nl2br(trim($q), false) . '</div>';
			$r .= '  <div class="card-footer clearfix">';
			$r .= '		<div style="float:left; width:50%;">Params: ' . implode(' , ', $query['params']) . '</div>
							<div style="float:left; width:16%;">Affected Rows: ' . $query['affected_rows'] . '</div>
							<div style="float:left; width:9%;">Fetch: ' . $query['fetch'] . '</div>
							<div style="float:left; width:11%;">Insert id: ' . $query['insert_id'] . '</div>
							<div style="float:left; width:14%;">Time: ' . round($query['time'], 6) . '</div>';
			$r .= '</div>';
			$r .= '</div>';
		}
		return $r;
	}


	/**
	 *  Simple pagination script
	 *
	 *  @param integer $total total pages
	 *  @param integer $page current page
	 *  @param integer $display how many pages to display at once
	 *  @param string $link link format
	 *  @param integer $prev previous page (to decide if the uses is moving forward or backward)
	 *  @return string html
	 */
	public static function pager($total, $page, $display = 10, $link = null, $prev = 0)
	{
		$r = '<div class="text-center"><ul class="pagination paginator">';

		$total   = ceil($total);
		$display = ceil($display);
		$page    = (int) $page;
		$prev    = (int) $prev;

		if (!$link) {
			$args = \App::GET();
			unset($args['pn']);
			$args['pn'] = '';
			if ($prev) $args['prevpn'] = $prev;
			$link = '?' . http_build_query($args);
		}

		if ($page <= 1)
			$r .= '<li class="page-item disabled"><a class="page-link">' . __('paging.previous') . '</a></li>';
		else
			$r .= '<li class="page-item"><a class="page-link" href="' . $link . ($page - 1) . '">' . __('paging.previous') . '</a></li>';

		$range = self::pagerAsList($total, $page, $display, $prev);

		foreach ($range as $i => $l)
			if ($i == $page)
				$r .= '<li class="page-item active"><span class="page-link">' . $i . ' <span class="sr-only">(current)</span></span></li>';
			else
				$r .= '<li class="page-item"><a class="page-link" href="' . $link . $i . '">' . $l . '</a></li>';

		if ($page >= $total)
			$r .= '<li class="page-item disabled"><a class="page-link">' . __('paging.next') . '</a></li>';
		else
			$r .= '<li class="page-item"><a class="page-link" href="' . $link . ($page + 1) . '">' . __('paging.next') . '</a></li>';

		$r .= '</ul></div>';
		return $r;
	}


	public static function pagerAsList($total, $page, $display = 10, $prev = 0)
	{
		$page = (int) $page;
		$display = $display - 1;
		$range = [];

		if ($total > 0 && $display > 0) {
			$end = ceil($page / $display) * $display + 1;
			$start = (int) $end - $display;

			if ($page == $start && $prev > $page && $page != 1) {
				$end = ceil(($page - 1) / $display) * $display + 1;
				$start = $end - $display;
			}

			$first = $start ?: 1;
			$last = $end > $total ? $total : $end;

			$first_tip = $tip = ceil($first / 2);
			$last_tip = $last + round(($total - $last) / 2);

			$range = [$first_tip => '...', $last_tip => '...', $total => $total, 1 => 1];

			if ($page == $first && $page !== 1) {
				$first--;
				$last--;
			}

			if ($page == $last && $page != $last) {
				$first++;
				$last++;
			}

			if ($first_tip != 1) {
				if ($page - 1 > $first) {
					$first++;
				} elseif ($page + 1 < $last) {
					$last--;
				}
			}

			if ($last_tip != $total) {
				if ($page - 1 > $first) {
					$first++;
				} elseif ($page + 1 < $last) {
					$last--;
				}
			}

			$range = $range + array_combine(range($first, $last), range($first, $last));
		}

		ksort($range);

		return $range;
	}


	public static function countryFlag($country, $show_name = false)
	{
		$country_code = '00';
		$country_name = 'Unknown';

		if (strlen($country) === 2) {
			$country_code = strtoupper($country);
			$country_name = COUNTRIES[$country_code] ?? $country_name;
		} else {
			foreach (COUNTRIES as $code => $name) {
				if (strcasecmp($country, $name) === 0) {
					$country_code = $code;
					$country_name = $name;
					break;
				}
			}
		}

		$class = 'flag-icon flag-' . strtolower($country_code);
		$label = html_encode($country_name);

		if ($show_name) {
			$html = '<span><i class="' . $class . '" title="' . $label . '"></i> ' . $label . '</span>';
		} else {
			$html = '<span><i class="' . $class . '" title="' . $label . '"></i></span>';
		}

		return $html;
	}


	public static function userAgentIcons($useragent)
	{
		$browsers = [
			'Arora','AWeb','Camino','Epiphany','Galeon','HotJava','iCab','MSIE','Maxthon','Chrome','Safari','Konqueror','Flock','Iceweasel','SeaMonkey','Firebird','Netscape','Firefox','Mozilla','Opera','PhaseOut','SlimBrowser','Unknown'
		];

		$systems = [
			'CentOS','Debian','Fedora','Freespire','Gentoo','Katonix','KateOS','Knoppix','Kubuntu','Linspire','Mandriva','Mandrake','RedHat','Slackware','Slax','Suse','Xubuntu','Ubuntu','Xandros','Arch','Ark',
			'Amiga','BeOS','FreeBSD','HP-UX','Linux','NetBSD','OS/2','SunOS','Symbian','Unix','Windows','Sun','Macintosh','Mac','Unknown'
		];

		foreach($browsers as $browser) if (stripos($useragent, $browser) !== false) break;
		foreach($systems as $system) if (stripos($useragent, $system) !== false) break;

		if (preg_match('#(Version|'.$browser.')[\s/]*([\.0-9]*)#i', $useragent, $m)) {
			$browser_version = $m[2];
		} else {
			$browser_version = 'N/A';
		}

		if ($browser === 'MSIE') {
			$browser = 'Internet Explorer';
		}

		if ($system === 'Windows') {
			$version = (string)(float)substr($useragent, stripos($useragent, 'windows nt ') + 11);
			$versions = ['5.0'=>'XP','5.1'=>'XP','5.2'=>'XP','5.3'=>'XP','6'=>'Vista','6.1'=>'7','6.2'=>'8','6.3'=>'8.1','10'=>'10'];
			if (isset($versions[$version])) $system = 'Windows '.$versions[$version];
		}

		$browser_name    = $browser ? $browser.' '.$browser_version : 'Unknown';
		$system_img      = App::getAsset('/img/user_agent/system/'.preg_replace('/[^\.a-z0-9_]/', '', strtolower($system)).'.png');
		$browser_img     = App::getAsset('/img/user_agent/browser/'.preg_replace('/[^\.a-z0-9_]/', '', strtolower($browser)).'.png');

		if (has_permission('moderator')) {
			$more_info = 'style="cursor: pointer" onclick="alert(\''.html_encode(addslashes($useragent).'\n\n'.
					     'System:\t'.addslashes($system).'\nBrowser:\t'.addslashes($browser_name)).'\')"';
		}

		$result = '<span class="user-agent" '.($more_info ?? '').'><img src="'.$system_img.'" title="'.html_encode($system).'" alt="'.html_encode($system).'"> '.
				'<img src="'.$browser_img.'" title="'.html_encode($browser_name).'" alt="'.html_encode($browser_name).'"></span>';

		return $result;
	}


	/**
	 * $fields = [
	 * 	[
	 * 		'name' => field name
	 * 		'type' => text,number,color,textarea,checkbox,enum,select,password,boolean,image,avatar
	 * 		'value' => current value
	 * 		'choices' => [list of valid options for enum/select]
	 * 		'default' => default value
	 * 		'allow_reset' => show reset box with default value
	 * 		'label' => field label
	 * 		'attributes' => [ ...html attributes ],
	 * 		'required' => whether the value has to be present. If false and value is absent then this key is ignored and validation not performed
	 * 		'validate' => validator, a regex or a callback. The cms defines some presets like PREG_USERNAME or PREG_NOT_EMPTY
	 * 		'filter' => callback to clean the value prior to validation/saving
	 * 	]
	 * ]
	 */
	public static function formBuilder($title, array $fields, $form_tag = true, $submit_label = 'Send')
	{
		$form = 'form-'.random_hash(4);
		$buffer = '';

		if ($title) {
			$buffer = '<legend>' . html_encode($title) . '</legend>';
		}

		if ($form_tag) {
			$buffer .= '<form method="post" role="form" class="form-horizontal" enctype="multipart/form-data" id="'.$form.'">';
		}

		foreach($fields as $name => $props)
		{
			$subfields = $props['type'] === 'multiple' ? $props['fields'] : [$name => $props];

			$buffer .= '<div class="form-group row">';

			$buffer .= '<label class="col-sm-4 col-form-label text-end" for="' . $form.'-'.md5(key($subfields)) . '">' . $props['label'] . ' ';

			if (!empty($props['help'])) {
				$buffer .= ' <i class="fa fa-question-circle" title="' . html_encode($props['help']) . '"></i>';
			}

			$buffer .= '</label>';

			$buffer .= '<div class="col-sm-6">';

			foreach($subfields as $key => $field) {
				$fieldId = $form.'-'.md5($key);
				$name = str_replace('.', '||', $key); // PHP will eat the . in POST
				$field += ['value' => '', 'default' => '', 'attributes' => [], 'placeholder' => ''];
				$base_attributes = ['id' => $fieldId, 'name' => $name, 'class' => 'form-control'];

				if (isset($field['default']) && is_scalar($field['default'])) {
					$field['attributes']['data-default'] = $field['default'];
				}

				$attributes = self::htmlAttributes((array)$field['attributes'] + $base_attributes);

				switch($field['type']) {
					case 'textarea':
						$buffer .= '<textarea ' .$attributes . '>' . html_encode($field['value']) . '</textarea>';
						break;

					case 'checkbox':
						$buffer .= '<input type="checkbox" value="' . html_encode($field['value']) . '" ' . (empty($field['checked']) ? '' : 'checked') . ' class="" ' .$attributes . '>'.
								'<label for="' . $fieldId . '" class="normal"> ' . html_encode($field['label']) . '</label><br>';
						break;

					case 'select': case 'enum':
						$buffer .= self::select(null, (array)$field['choices'], $field['value'], true, $attributes);
						break;


					case 'multi':
						$buffer .= self::select(null, (array)$field['choices'], (array)$field['value'], true, "multiple $attributes");
						break;

					case 'boolean': case 'bool':
						$buffer .= self::select(null, [0 => 'Non', 1 => 'Oui'], $field['value'], true, $attributes);
						break;

					case 'avatar':
						break;

					case 'image':
						$files = ['' => 'Valeur par défaut'] + array_column(Db::QueryAll('select path, name from {files} where origin = ?', 'settings') ?: [], 'name', 'path');
						$attributes .= " style='display:inline;width:40%' onchange='document.getElementById(\"$fieldId\").src=this.value ? site_url+this.value : \"data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7\";'";
						$buffer .= self::select(null, $files, $field['value'], true, $attributes);
						$buffer .= ' ou <input name="' . $name . '" type="file" style="display:inline"><br>';
						$buffer .= '<img id="'.$fieldId.'" src="'.($field['value'] ? App::getAsset($field['value']) : 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7').'" alt="Image preview" title="Image preview" style="height: 150px"><br>';
						break;

					default: // text,password,color,number,etc...
						$value = html_encode($field['value']);
						// Pour les champs de couleur, s'assurer qu'il y a une valeur par défaut valide
						if ($field['type'] === 'color' && empty($value)) {
							$value = '#000000'; // Valeur par défaut pour les champs de couleur
						}
						$buffer .= '<input type="'.$field['type'].'" value="' . $value . '" ' .$attributes . '>';
						break;
				}

				if (!empty($field['allow_reset'])) { //  && isset($field['default'])
					$buffer .= '<label class="normal"><input data-reset="'.$fieldId.'" name="' . $name . '" type="checkbox" '.($field['default'] == $field['value'] ? 'checked' : '').' value="'.html_encode(addslashes($field['default'])).'"> Valeur par défaut</label>';
				}
			}

			$buffer .= '</div></div>';
		}

		$buffer .= '<div class="text-center"><input class="btn btn-medium btn-primary" type="submit" value="' . html_encode($submit_label) . '"></div>';

		$buffer .= '<script>
			$(".form-control").bind("change keyup", function() {
				var resetCB = $("[data-reset=" + $(this).attr("id") + "]");
				if (resetCB.length == 1) resetCB[0].checked = $(this).data("default") == $(this).val();
			});
		</script>';

		if ($form_tag) {
			$buffer .= '</form>';
		}

		return $buffer;
	}


	public static function select($name, array $options, $default = null, $escape = true, $attributes = 'class="form-control"')
	{
		if (count($options) > 0) {
			$attributes = self::htmlAttributes($attributes);
			if ($name !== null) { // let the caller define them in $attributes
				$attributes .= ' id="'.$name.'" name="'.$name.'"';
			}
			$r = "<select $attributes>";
			foreach($options as $value => $opts) {
				$r .= self::select_opt([$value, $opts], $default, $escape);
			}
			$r .= '</select>';
			return $r;
		} else
			return 'Aucun choix!';
	}


	private static function select_opt(array $option, $selected = null, $escape = true)
	{
		$option += ['', '', ''];

		if ($option[1] instanceOf \HtmlSelectGroup) { // optgroup
			$option[2] = self::htmlAttributes($option[2]);
			$r = '<optgroup label="' . html_encode($option[0]) . '" ' . $option[2] . '>';
			foreach($option[1] as $value => $label) {
				$attributes = '';
				if (is_integer($value) && is_array($label)) {
					list($value, $label, $attributes) = $label + ['', '', ''];
				}
				$r .= self::select_opt([$value, $label, $attributes], $selected, $escape);
			}
			$r .= '</optgroup>';
		} else {
			if (is_array($option[1])) {
				$option = (array)$option[1];
			}

			list($value, $label, $attributes) = $option + ['', '', ''];
			$attributes = self::htmlAttributes($attributes);

			$r = '<option' . (in_array($value, (array)$selected) ? ' selected' : '') . ' ' . $attributes . ' value="' . html_encode($value) . '">' . ($escape ? html_encode($label) : $label) . '</option>';
		}

		return $r;
	}

	private static function htmlAttributes($attributes)
	{
		$attributes = (array)$attributes;

		foreach($attributes as $attr => &$value) {
			if (!is_int($attr)) {
				$value = $attr . '="' . html_encode($value) . '"';
			}
		}

		return implode(' ', $attributes);
	}


	public static function iconSelect(string $name, $default = null)
	{
		// To update: https://fontawesome.com/cheatsheet/free/solid
		// for (let icon of $$('.icon')) {list.push('fab ' + icon.querySelector('.icon-name').textContent);}
		$fa_icons = json_decode(file_get_contents(__DIR__ . '/lib-data/font-awesome.json'));
		$choices[''] = 'Aucune';
		foreach($fa_icons as $group => $icons) {
			$choices[$group] = new HtmlSelectGroup();
			foreach($icons as $icon) {
				$choices[$group][$icon] = substr($icon, 7);
			}
		}

		return '<div>
					<div style="width:85%;display:inline-block;"> '.self::select('icon', $choices, $default).'</div>
					<small><a href="https://fontawesome.com/cheatsheet/free/solid" target="_blank">Cheatsheet</a></small>
				</div>';
	}


	public static function imageSelect(string $name, $default = null)
	{
	}
}
