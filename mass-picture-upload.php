<?php       
    
/*
    This script will parse a single CSV with ID Product and additional columns
    containing image filename.
    Images will be retrieved from remote URL and uploaded on given ID products.
    Must be placed in /scripts/ subdirectory and launched from CLI. 
    Log will be printed onscreen during execution.

    Usage: php mass-picture-upload {csv-filename} {arguments}

    Arguments: 
    check -> will check remote image existance - no actual import will be done.
*/

if (!php_sapi_name() === 'cli') {
    die('Script must be launched from CLI.'."\n");
}

ini_set('memory_limit', '512M');

include dirname(__FILE__).'/../config/config.inc.php';

if (!isset($argv[1]) || !file_exists(dirname(__FILE__).'/'.$argv[1])) {
    die('Invalid filename or missing file.'."\n");
}

define('CSV_FILE', dirname(__FILE__).'/'.$argv[1]);

/*** Configuration ***/
define('DELETE_EXISTING_PICTURES', true);
define('URL_PREFIX', 'https://localhost/media/pics/');
define('CSV_SEPARATOR', '|');
define('CHECK_ONLY', in_array('check', $argv));

$row = 0;
if (($handle = fopen(CSV_FILE, "r")) !== FALSE) {
    while (($data = fgetcsv($handle, 0, CSV_SEPARATOR)) !== FALSE) {
        $row++;
        if ($row == 1) {
            echo 'Skipping header'."\n";
            continue;
        }
        $idProduct = (int)$data[0];
        echo 'Processing ID product '.$idProduct."\n";
        $objProduct = new Product($idProduct);
        if (!Validate::isLoadedObject($objProduct)) {
            echo 'Skipping ID '.$idProduct.' - Error loading Prestashop object'."\n";
            // continue;
        }
        array_shift($data);
        
        if (CHECK_ONLY) {
            foreach ($data as $imagefilename) {
                if (!empty($imagefilename)) {
                    if (!remoteFileExists(URL_PREFIX.$imagefilename)) {
                        echo 'Remote image does not exists: '.URL_PREFIX.$imagefilename."\n";
                    }
                }
            }
            continue;
        }
        
        if (DELETE_EXISTING_PICTURES) {
            $images = $objProduct->getImages(Context::getContext()->language->id);
            foreach ($images as $image) {
                $objImage = new Image($image['id_image']);
                $objImage->delete();
                echo 'Deleted ID image '.$objImage->id."\n";
            }
        }
        
        foreach ($data as $key => $imagefilename) {
            if (empty(trim($imagefilename))) {
                continue;
            }
            $image = new Image();
            $image->id_product = (int) $objProduct->id;
            $image->position = Image::getHighestPosition($objProduct->id) + 1;
            $cover = false;
            if ($key == 0) {
                $cover = true;
            }
            $image->cover = $cover;
            $image->add();
            echo 'Added ID image '.$image->id."\n";
            if (!copyImg($objProduct->id, $image->id, URL_PREFIX.$imagefilename, 'products', true)) {
                $image->delete();
                echo 'Cannot copy image from URL: '.URL_PREFIX.$imagefilename."\n";
            }
        }
    }
    fclose($handle);
} else {
    die('Unexpected error while reading CSV from '.CSV_FILE."\n");
}


function remoteFileExists($url) {
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_NOBODY, true);
    $result = curl_exec($curl);
    $ret = false;
    if ($result !== false) {
        $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);  
        if ($statusCode == 200) {
            $ret = true;   
        }
    }
    curl_close($curl);
    return $ret;
}

function copyImg($id_entity, $id_image, $url, $entity = 'products', $regenerate = true) {
    $tmpfile = tempnam(_PS_TMP_IMG_DIR_, 'ps_import');
    $watermark_types = explode(',', Configuration::get('WATERMARK_TYPES'));


    switch ($entity) {
        default:
        case 'products':
            $image_obj = new Image($id_image);
            $path = $image_obj->getPathForCreation();
            break;
        case 'categories':
            $path = _PS_CAT_IMG_DIR_ . (int) $id_entity;
            break;
        case 'manufacturers':
            $path = _PS_MANU_IMG_DIR_ . (int) $id_entity;
            break;
        case 'suppliers':
            $path = _PS_SUPP_IMG_DIR_ . (int) $id_entity;
            break;
    }
    $url = str_replace(' ', '%20', trim($url));


    // Evaluate the memory required to resize the image: if it's too much, you can't resize it.
    if (!ImageManager::checkImageMemoryLimit($url))
        return false;

    if (Tools::copy($url, $tmpfile)) {
        ImageManager::resize($tmpfile, $path . '.jpg');
        $images_types = ImageType::getImagesTypes($entity);


        if ($regenerate)
            foreach ($images_types as $image_type) {
                echo 'Generating miniature for '.$image_type['name']."\n";
                ImageManager::resize($tmpfile, $path . '-' . stripslashes($image_type['name']) . '.jpg', $image_type['width'], $image_type['height']);
                if (in_array($image_type['id_image_type'], $watermark_types))
                    Hook::exec('actionWatermark', array('id_image' => $id_image, 'id_product' => $id_entity));
            }
    }
    else {
        unlink($tmpfile);
        return false;
    }
    unlink($tmpfile);
    return true;
}
