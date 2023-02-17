<?php       

/*
    This script will parse all PDF in "pdf" directory and upload them as Prestashop attachment massively.
    It will also check if file with same name had already been uploaded to avoid duplication.
    A log.csv with id_attachment|file_name will be written in the script folder at the end of the script.
    
    Usage: Place it in a /script/ directory under Prestashop root and launch: "php mass-attachment-upload.php"
    PDF(s) should reside under /script/pdf folder
    Note: make sure to use 32 character max filenames
*/

include __dir__.'/../config/config.inc.php';

define('LOG_FILE', __dir__.'/log.csv');
file_put_contents(LOG_FILE, '');

$search_results = glob(__dir__.'/pdf/*.pdf');
$db = Db::getInstance();

$alreadyExistingFiles = array_column($db->executeS('SELECT file_name FROM '._DB_PREFIX_.'attachment'), 'file_name');

foreach ($search_results as $search_result) {
    $original_path_file = $search_result;
    $name = basename($search_result);
    
    if (in_array($name, $alreadyExistingFiles)) {
        echo 'Skipping '.$name.' since a file with same name had already been uploaded'."\n";
        continue;
    }
    $description = str_replace('.pdf', '', $name);

    $attachment = new Attachment();
    $languages = Language::getLanguages();
    foreach ($languages as $language) {
        $attachment->name[$language['id_lang']] = substr($name, 0, 31);
        $attachment->description[$language['id_lang']] = $description;
    }


    $attachment->file = sha1($name);
    $attachment->file_name = $name;

    $path_file = _PS_DOWNLOAD_DIR_.$attachment->file;
    $attachment->file_size = filesize($original_path_file);

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $attachment->mime = finfo_file($finfo, $original_path_file); 

    if (!copy($original_path_file, $path_file)) {
        echo 'Skipping '.$name.' since there were an error during copy in download folder.'."\n";
        continue;
    }

    if ($attachment->add()) {
        echo 'Uploaded '.$name.' - ID attachment '.(int)$attachment->id."\n";
        file_put_contents(LOG_FILE, (int)$attachment->id.'|'.$name."\n", FILE_APPEND);		
    }
}
