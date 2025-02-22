<?php defined('EVO') or die('Que fais-tu là?');

use Evo\Models\File;

$files = File::select('origin = ? order by posted desc', 'downloads');
App::renderTemplate('pages/downloads.php', compact('files'));
