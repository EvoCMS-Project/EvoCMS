<?php defined('EVO') or die('Que fais-tu là?'); ?>
<h2 class="heading-line"><span><i class="fa fa-chalkboard-teacher"></i> <?= __('admin/dashboard.title_dash') ?></span></h2>
<div class="en-container en-container-large">
	<div class="en-container en-container-large">
		<ul class="stats-circular">
			<li class="center php">
				<a href="?page=pages">
					<p><?= Db::Get('select count(*) from {pages} where type = "article"') ?></p>
					<label><?= __('admin/dashboard.circle_Articles') ?></label>
				</a>
			</li>
			<li class="center php">
				<a href="?page=pages">
					<p><?= Db::Get('select count(*) from {pages} where type <> "article"') ?></p>
					<label><?= __('admin/dashboard.circle_Pages') ?></label>
				</a>
			</li>
			<li class="center mysql">
				<a href="?page=comments">
					<p><?= Db::Get('select count(*) from {comments}') ?></p>
					<label><?= __('admin/dashboard.circle_Comments') ?></label>
				</a>
			</li>
			<li class="center db_ver">
				<a href="?page=gallery">
					<p><?= Db::Get('select count(*) from {files}') ?></p>
					<label><?= __('admin/dashboard.circle_Files') ?></label>
				</a>
			</li>
			<li class="center cms_version">
				<a href="?page=forums">
					<p><?= Db::Get('select count(*) from {forums_topics}') ?></p>
					<label><?= __('admin/dashboard.circle_Discuss') ?></label>
				</a>
			</li>
			<li class="center cms_version">
				<a href="?page=forums">
					<p><?= Db::Get('select count(*) from {forums_posts}') ?></p>
					<label><?= __('admin/dashboard.circle_msg_forum') ?></label>
				</a>
			</li>
			<li class="center rev_date">
				<a href="?page=users">
					<p><?= Db::Get('select count(*) from {users} where id <> 0') ?></p>
					<label><?= __('admin/dashboard.circle_Members') ?></label>
				</a>
			</li>
			<li class="center cms_build">
				<a href="?page=polls">
					<p><?= Db::Get('select 0') ?></p>
					<label><?= __('admin/dashboard.circle_Modules') ?></label>
				</a>
			</li>
		</ul>
	</div>
</div>
<h2 class="heading-line secondary"><span><i class="fa fa-chalkboard-teacher"></i> <?= __('admin/dashboard.title_info') ?></span></h2>
<div class="en-container en-container-large">
	<div class="en-container en-container-large">
		<div class="row">
			<div class="col-xs-12 col-sm-6 col-md-6 col-lg-6 col-xl-6" style="padding-top: 5px">
				<ul class="stat">
					<li class="stat-title left"><?= __('admin/dashboard.info_software') ?></li>
					<li class="stat-value right"><?= EVO_VERSION ?> (<?= date('Y-m-d', strtotime(EVO_RELEASEDATE)) ?>)</li>
					<div class="clearfix"></div>
					<li class="stat-title left"><?= __('admin/dashboard.info_commit') ?></li>
					<li class="stat-value right"><?= EVO_BUILD ?> (<?= date('Y-m-d', strtotime(EVO_BUILDDATE)) ?>)</li>
					<div class="clearfix"></div>
					<li class="stat-title left"><?= __('admin/dashboard.info_space') ?></li>
					<li class="stat-value right"><?= Format::size(Db::Get('select sum(size) from {files}')) ?></li>
				</ul>
			</div>
			<div class="col-xs-12 col-sm-6 col-md-6 col-lg-6 col-xl-6" style="padding-top: 5px">
				<ul class="stat">
					<li class="stat-title left"><?= __('admin/dashboard.info_php') ?></li>
					<li class="stat-value right"><a href="?page=phpinfo"><?= preg_replace('/\+.+$/', '', phpversion()); ?></a></li>
					<div class="clearfix"></div>
					<li class="stat-title left"><?= __('admin/dashboard.info_sql') ?></li>
					<li class="stat-value right"><?= Db::DriverName() . ' ' . Db::ServerVersion() ?></li>
					<div class="clearfix"></div>
					<li class="stat-title left"><?= __('admin/dashboard.info_load') ?></li>
					<li class="stat-value right">0</li>
				</ul>
			</div>

		</div>
		<table class="ui celled table">
			<tr>
				<td><?= __('admin/dashboard.info_dev') ?></td>
				<td>
					Yan Bourgeois <small>(Coolternet)</small> : Designer<br>
					Alex Duchesne <small>(Alexus)</small>: Développeur<br>
					<a href="#credits" data-bs-toggle="modal" data-bs-target="#credits"><small>voir plus</small></a>
				</td>
			</tr>
		</table>
	</div>
</div>

<div id="credits" class="modal fade">
<div class="modal-dialog" role="document">
    <div class="modal-content">
	<div class="modal-body">
		<h3><?= __('admin/dashboard.info_credits') ?> :</h3>
		===<br>
			<a href="http://raymondhill.net/blog/?p=441">FineDiff</a> - MIT<br>
			<a href="http://parsedown.org">Parsedown</a> - MIT<br>
			<a href="https://github.com/clouddueling/mysqldump-php">MySQLDump</a> - MIT<br>
			<a href="http://maxmind.com">GeoIP</a> - LGPL<br>
			<a href="http://www.adminer.org/">Adminer</a> - Apache License<br>
			<br>
			<a href="http://jquery.com">jQuery</a> - MIT<br>
			<a href="http://getbootstrap.com">Bootstrap</a> - MIT<br>
			<a href="http://ckeditor.com/">ckeditor</a> - MPL<br>
			<a href="http://fancyapps.com/fancybox">fancybox</a> - MIT<br>
			<a href="http://markitup.jaysalvat.com/">markitup</a> - MIT<br>
			<br>
			<a href="http://fortawesome.github.io/Font-Awesome/">Font-Awesome</a> - SIL OFL 1.1<br>
			<a href="http://www.famfamfam.com/lab/icons/silk/">famfamfam - Silk</a> - CC BY 2.5<br>
			Nomicons - CC BY 2.5<br>
		</div>
		</div>
	</div>
</div>
