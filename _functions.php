<?php

function protect($string) {
	$protection = htmlspecialchars(trim($string), ENT_QUOTES); // Change tag characters to HTML entities
	return $protection;
}

function renderStyledUsername($username, $styleJson) {
    $username = (string)$username;
    $styleArr = json_decode((string)$styleJson, true);

    if (!is_array($styleArr) || empty($styleArr)) {
        return htmlspecialchars($username, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    $out = '';
    $concatenatedText = '';

    foreach ($styleArr as $run) {
        if (!is_array($run)) continue;

        $text = (string)($run['text'] ?? '');
        $color = (string)($run['color'] ?? '');

        if ($text === '') continue;
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $color = '#ffffff';
        }

        $out .= '<span style="color:' . htmlspecialchars($color, ENT_QUOTES, 'UTF-8') . ';">'
             . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
             . '</span>';
             
        $concatenatedText .= $text;
    }

    if ($concatenatedText !== $username) {
        return htmlspecialchars($username, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    return $out !== '' ? $out : htmlspecialchars($username, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function generateRandomString($length = 8) {
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= random_int(0, 9);
    }
    return $randomString;
}

function getHTTP() {
	if(isset($_SERVER['HTTPS']) && (trim($_SERVER['HTTPS']) == "" || $_SERVER['HTTPS'] == "off")) {
		$dispHTTP = "http://";
	}else {
		$dispHTTP = "https://";
	}
	return $dispHTTP;
}

function getPreciseTime($intTime, $timeFormat="", $bypassTimeDiff=false) {

	$timeDiff = (!$bypassTimeDiff) ? time() - $intTime : 99999;

	if($timeDiff < 3) {
		$dispLastDate = "just now";
	}elseif($timeDiff < 60) {
		$dispLastDate = "$timeDiff seconds ago";
	}elseif($timeDiff < 3600) {
		$minDiff = round($timeDiff/60);
		$dispMinute = "minutes";
		if($minDiff == 1) {
			$dispMinute = "minute";
		}
		$dispLastDate = "$minDiff $dispMinute ago";
	}elseif($timeDiff < 86400) {
		$hourDiff = round($timeDiff/3600);
		$dispHour = "hours";
		if($hourDiff == 1) {
			$dispHour = "hour";
		}
		$dispLastDate = "$hourDiff $dispHour ago";
	}elseif($timeDiff < 2592000) {
		$dayDiff = round($timeDiff/86400);
		$dispHour = "days";
		if($dayDiff == 1) {
			$dispHour = "day";
		}
		$dispLastDate = "$dayDiff $dispHour ago";
	}else {
        if($timeDiff < 31104000) {
		    if($timeFormat == "") {
			    $timeFormat = "j M g:i a";
		    }
        }else {
			if($timeFormat == "") {
			    $timeFormat = "j M, Y g:i a";
		    }
		}
		$dispLastDate = date($timeFormat, $intTime);
	}
	return $dispLastDate;
}

function gettimeago(int $timestamp): string {
	$now = time();
	$diff = $now - $timestamp;
	if ($diff < 0) $diff = 0;

	if ($diff < 10) return 'just now';
	if ($diff < 60) return $diff . ' second' . ($diff > 1 ? 's' : '') . ' ago';
	if ($diff < 3600) return (int)floor($diff / 60) . ' minute' . ((int)floor($diff/60) > 1 ? 's' : '') . ' ago';
	if ($diff < 86400) return (int)floor($diff / 3600) . ' hour' . ((int)floor($diff/3600) > 1 ? 's' : '') . ' ago';
	if ($diff < 2592000) return (int)floor($diff / 86400) . ' day' . ((int)floor($diff/86400) > 1 ? 's' : '') . ' ago';

	return date('M j, Y H:i', $timestamp);
}



function country($value) {
	$list = array("AF" => "Afghanistan", "AL" => "Albania", "DZ" => "Algeria", "AS" => "American Samoa", "AD" => "Andorra", "AO" => "Angola", "AI" => "Anguilla", "AQ" => "Antarctica", "AG" => "Antigua and Barbuda", "AR" => "Argentina", "AM" => "Armenia", "AW" => "Aruba", "AU" => "Australia", "AT" => "Austria", "AZ" => "Azerbaijan", "AX" => "Åland Islands", "BS" => "Bahamas", "BH" => "Bahrain", "BD" => "Bangladesh", "BB" => "Barbados", "BY" => "Belarus", "BE" => "Belgium", "BZ" => "Belize", "BJ" => "Benin", "BM" => "Bermuda", "BT" => "Bhutan", "BO" => "Bolivia", "BA" => "Bosnia and Herzegovina", "BW" => "Botswana", "BV" => "Bouvet Island", "BR" => "Brazil", "BQ" => "British Antarctic Territory", "IO" => "British Indian Ocean Territory", "VG" => "British Virgin Islands", "BN" => "Brunei", "BG" => "Bulgaria", "BF" => "Burkina Faso", "BI" => "Burundi", "KH" => "Cambodia", "CM" => "Cameroon", "CA" => "Canada", "CT" => "Canton and Enderbury Islands", "CV" => "Cape Verde", "KY" => "Cayman Islands", "CF" => "Central African Republic", "TD" => "Chad", "CL" => "Chile", "CN" => "China", "CX" => "Christmas Island", "CC" => "Cocos [Keeling] Islands", "CO" => "Colombia", "KM" => "Comoros", "CG" => "Congo - Brazzaville", "CD" => "Congo - Kinshasa", "CK" => "Cook Islands", "CR" => "Costa Rica", "HR" => "Croatia", "CU" => "Cuba", "CY" => "Cyprus", "CZ" => "Czech Republic", "CI" => "Côte d’Ivoire", "DK" => "Denmark", "DJ" => "Djibouti", "DM" => "Dominica", "DO" => "Dominican Republic", "NQ" => "Dronning Maud Land", "DD" => "East Germany", "EC" => "Ecuador", "EG" => "Egypt", "SV" => "El Salvador", "GQ" => "Equatorial Guinea", "ER" => "Eritrea", "EE" => "Estonia", "ET" => "Ethiopia", "FK" => "Falkland Islands", "FO" => "Faroe Islands", "FJ" => "Fiji", "FI" => "Finland", "FR" => "France", "GF" => "French Guiana", "PF" => "French Polynesia", "TF" => "French Southern Territories", "FQ" => "French Southern and Antarctic Territories", "GA" => "Gabon", "GM" => "Gambia", "GE" => "Georgia", "DE" => "Germany", "GH" => "Ghana", "GI" => "Gibraltar", "GR" => "Greece", "GL" => "Greenland", "GD" => "Grenada", "GP" => "Guadeloupe", "GU" => "Guam", "GT" => "Guatemala", "GG" => "Guernsey", "GN" => "Guinea", "GW" => "Guinea-Bissau", "GY" => "Guyana", "HT" => "Haiti", "HM" => "Heard Island and McDonald Islands", "HN" => "Honduras", "HK" => "Hong Kong SAR China", "HU" => "Hungary", "IS" => "Iceland", "IN" => "India", "ID" => "Indonesia", "IR" => "Iran", "IQ" => "Iraq", "IE" => "Ireland", "IM" => "Isle of Man", "IL" => "Israel", "IT" => "Italy", "JM" => "Jamaica", "JP" => "Japan", "JE" => "Jersey", "JT" => "Johnston Island", "JO" => "Jordan", "KZ" => "Kazakhstan", "KE" => "Kenya", "KI" => "Kiribati", "KW" => "Kuwait", "KG" => "Kyrgyzstan", "LA" => "Laos", "LV" => "Latvia", "LB" => "Lebanon", "LS" => "Lesotho", "LR" => "Liberia", "LY" => "Libya", "LI" => "Liechtenstein", "LT" => "Lithuania", "LU" => "Luxembourg", "MO" => "Macau SAR China", "MK" => "Macedonia", "MG" => "Madagascar", "MW" => "Malawi", "MY" => "Malaysia", "MV" => "Maldives", "ML" => "Mali", "MT" => "Malta", "MH" => "Marshall Islands", "MQ" => "Martinique", "MR" => "Mauritania", "MU" => "Mauritius", "YT" => "Mayotte", "FX" => "Metropolitan France", "MX" => "Mexico", "FM" => "Micronesia", "MI" => "Midway Islands", "MD" => "Moldova", "MC" => "Monaco", "MN" => "Mongolia", "ME" => "Montenegro", "MS" => "Montserrat", "MA" => "Morocco", "MZ" => "Mozambique", "MM" => "Myanmar [Burma]", "NA" => "Namibia", "NR" => "Nauru", "NP" => "Nepal", "NL" => "Netherlands", "AN" => "Netherlands Antilles", "NT" => "Neutral Zone", "NC" => "New Caledonia", "NZ" => "New Zealand", "NI" => "Nicaragua", "NE" => "Niger", "NG" => "Nigeria", "NU" => "Niue", "NF" => "Norfolk Island", "KP" => "North Korea", "VD" => "North Vietnam", "MP" => "Northern Mariana Islands", "NO" => "Norway", "OM" => "Oman", "PC" => "Pacific Islands Trust Territory", "PK" => "Pakistan", "PW" => "Palau", "PS" => "Palestinian Territories", "PA" => "Panama", "PZ" => "Panama Canal Zone", "PG" => "Papua New Guinea", "PY" => "Paraguay", "YD" => "People's Democratic Republic of Yemen", "PE" => "Peru", "PH" => "Philippines", "PN" => "Pitcairn Islands", "PL" => "Poland", "PT" => "Portugal", "PR" => "Puerto Rico", "QA" => "Qatar", "RO" => "Romania", "RU" => "Russia", "RW" => "Rwanda", "RE" => "Réunion", "BL" => "Saint Barthélemy", "SH" => "Saint Helena", "KN" => "Saint Kitts and Nevis", "LC" => "Saint Lucia", "MF" => "Saint Martin", "PM" => "Saint Pierre and Miquelon", "VC" => "Saint Vincent and the Grenadines", "WS" => "Samoa", "SM" => "San Marino", "SA" => "Saudi Arabia", "SN" => "Senegal", "RS" => "Serbia", "CS" => "Serbia and Montenegro", "SC" => "Seychelles", "SL" => "Sierra Leone", "SG" => "Singapore", "SK" => "Slovakia", "SI" => "Slovenia", "SB" => "Solomon Islands", "SO" => "Somalia", "ZA" => "South Africa", "GS" => "South Georgia and the South Sandwich Islands", "KR" => "South Korea", "ES" => "Spain", "LK" => "Sri Lanka", "SD" => "Sudan", "SR" => "Suriname", "SJ" => "Svalbard and Jan Mayen", "SZ" => "Swaziland", "SE" => "Sweden", "CH" => "Switzerland", "SY" => "Syria", "ST" => "São Tomé and Príncipe", "TW" => "Taiwan", "TJ" => "Tajikistan", "TZ" => "Tanzania", "TH" => "Thailand", "TL" => "Timor-Leste", "TG" => "Togo", "TK" => "Tokelau", "TO" => "Tonga", "TT" => "Trinidad and Tobago", "TN" => "Tunisia", "TR" => "Turkey", "TM" => "Turkmenistan", "TC" => "Turks and Caicos Islands", "TV" => "Tuvalu", "UM" => "U.S. Minor Outlying Islands", "PU" => "U.S. Miscellaneous Pacific Islands", "VI" => "U.S. Virgin Islands", "UG" => "Uganda", "UA" => "Ukraine", "SU" => "Union of Soviet Socialist Republics", "AE" => "United Arab Emirates", "GB" => "United Kingdom", "US" => "United States", "ZZ" => "Unknown or Invalid Region", "UY" => "Uruguay", "UZ" => "Uzbekistan", "VU" => "Vanuatu", "VA" => "Vatican City", "VE" => "Venezuela", "VN" => "Vietnam", "WK" => "Wake Island", "WF" => "Wallis and Futuna", "EH" => "Western Sahara", "YE" => "Yemen", "ZM" => "Zambia", "ZW" => "Zimbabwe");
	
	foreach($list as $code => $name) {
		if($value == $code) {
			$country = $name;
		}else {
			$country = 'Algeria';
		}
	}
	return $country;
}

function encryptPassword($password) {
    $options = [
        'cost' => 12,
    ];
    
    $encryptPassword = password_hash($password, PASSWORD_DEFAULT);
    
    $returnArr = array(
        "password" => $encryptPassword, 
        "salt" => ""
    );
    
    return $returnArr;
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function validate_image_upload($file, $uploadDir, $prefix = "avatar_", $mode = "avatar") {
    $response = [
        'status'   => 'error',
        'msg'      => 'Unknown error',
        'original' => null,
        'thumb'    => null,
    ];

    if (!is_array($file) || !isset($file['name'])) {
        $response['msg'] = "No file data received.";
        return $response;
    }

    $error   = isset($file['error']) ? (int)$file['error'] : UPLOAD_ERR_NO_FILE;
    $name    = trim((string)($file['name'] ?? ''));
    $tmpName = $file['tmp_name'] ?? '';
    $size    = isset($file['size']) ? (int)$file['size'] : 0;

    if ($name === '' || $error !== UPLOAD_ERR_OK) {
        $msg = "Please provide an image or there was an upload error.";
        switch ($error) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $msg = "Uploaded file is too large.";
                break;
            case UPLOAD_ERR_PARTIAL:
                $msg = "File was only partially uploaded.";
                break;
            case UPLOAD_ERR_NO_FILE:
                $msg = "No file was uploaded.";
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $msg = "Missing temporary folder on server.";
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $msg = "Failed to write uploaded file to disk.";
                break;
            case UPLOAD_ERR_EXTENSION:
                $msg = "A PHP extension stopped the file upload.";
                break;
        }
        $response['msg'] = $msg;
        return $response;
    }

    if ($tmpName === '' || !file_exists($tmpName) || !is_uploaded_file($tmpName)) {
        $response['msg'] = "Uploaded file not found (tmp file missing).";
        return $response;
    }

    $allowed_mimes = [
        'image/gif'  => '.gif',
        'image/jpeg' => '.jpg',
        'image/png'  => '.png',
    ];

    $finfo = @finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo === false) {
        $response['msg'] = "Server misconfiguration: fileinfo unavailable.";
        return $response;
    }
    $mime = @finfo_file($finfo, $tmpName);
    finfo_close($finfo);

    if (!is_string($mime) || !isset($allowed_mimes[$mime])) {
        $response['msg'] = "Only GIF, JPEG, or PNG images allowed. Detected: " . ($mime ?: 'unknown');
        return $response;
    }
    $ext = $allowed_mimes[$mime];

    $imgInfo = @getimagesize($tmpName);
    if ($imgInfo === false || !isset($imgInfo[0], $imgInfo[1])) {
        $response['msg'] = "Uploaded file is not a valid image.";
        return $response;
    }
    $width  = (int)$imgInfo[0];
    $height = (int)$imgInfo[1];

    $ONE_MB = 1048576;
    $iniLimit = ini_get("upload_max_filesize") ?: "2M";
    $limitMB = (int)filter_var($iniLimit, FILTER_SANITIZE_NUMBER_INT);
    if ($limitMB <= 0) $limitMB = 2;
    if ($size > ($limitMB * $ONE_MB)) {
        $response['msg'] = "Image too large (server limit: {$limitMB}MB).";
        return $response;
    }

    if ($width < 16 || $height < 16) {
        $response['msg'] = 'Avatar too small (min 16x16).';
        return $response;
    }
    if ($width > 8000 || $height > 8000) {
        $response['msg'] = 'Avatar dimensions too large (max 8000x8000).';
        return $response;
    }

    if ($uploadDir === false || $uploadDir === '') {
        $response['msg'] = "Server path error: upload directory not found.";
        return $response;
    }
    $uploadDir = rtrim($uploadDir, DIRECTORY_SEPARATOR);
    if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true)) {
        $response['msg'] = "Unable to create upload directory.";
        return $response;
    }
    if (!is_writable($uploadDir)) {
        $response['msg'] = "Upload directory is not writable.";
        return $response;
    }

    $thumbsDir = $uploadDir . DIRECTORY_SEPARATOR . 'thumbs';
    if (!is_dir($thumbsDir) && !@mkdir($thumbsDir, 0755, true)) {
        $response['msg'] = "Unable to create thumbnails directory.";
        return $response;
    }
    if (!is_writable($thumbsDir)) {
        $response['msg'] = "Thumbnails directory is not writable.";
        return $response;
    }

    try {
        $random = bin2hex(random_bytes(12));
    } catch (Throwable $e) {
        $random = uniqid('', true);
    }
    $filename   = preg_replace('/[^a-z0-9_\-\.]/i', '', $prefix . $random . $ext);
    $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $filename;
    $thumbPath  = rtrim($thumbsDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

    $srcImg = false;
    switch ($mime) {
        case 'image/gif':  $srcImg = @imagecreatefromgif($tmpName); break;
        case 'image/jpeg': $srcImg = @imagecreatefromjpeg($tmpName); break;
        case 'image/png':  $srcImg = @imagecreatefrompng($tmpName); break;
    }

    if ($srcImg === false) {
        $response['msg'] = "Failed to process image data (corrupt or unsupported).";
        return $response;
    }

    if ($mode !== 'avatar_thumb_only') {
        $originalSaved = false;
        switch ($mime) {
            case 'image/gif':
                $originalSaved = @imagegif($srcImg, $targetPath);
                break;
            case 'image/jpeg':
                $originalSaved = @imagejpeg($srcImg, $targetPath, 90);
                break;
            case 'image/png':
                imagealphablending($srcImg, false);
                imagesavealpha($srcImg, true);
                $originalSaved = @imagepng($srcImg, $targetPath, 6);
                break;
        }

        if (!$originalSaved) {
            imagedestroy($srcImg);
            $response['msg'] = "Failed to sanitize and save original image.";
            return $response;
        }
    }

    $side = min($width, $height);
    $srcX = (int) floor(($width - $side) / 2);
    $srcY = (int) floor(($height - $side) / 2);
    $thumbSize = 200; 

    $thumb = imagecreatetruecolor($thumbSize, $thumbSize);
    if ($thumb === false) {
        imagedestroy($srcImg);
        @unlink($targetPath);
        $response['msg'] = "Failed to create thumbnail buffer.";
        return $response;
    }

    if ($mime === 'image/png' || $mime === 'image/gif') {
        imagecolortransparent($thumb, imagecolorallocatealpha($thumb, 0, 0, 0, 127));
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
    }

    $resampled = imagecopyresampled(
        $thumb,
        $srcImg,
        0, 0,
        $srcX, $srcY,
        $thumbSize, $thumbSize,
        $side, $side
    );

    if ($resampled === false) {
        imagedestroy($srcImg);
        imagedestroy($thumb);
        @unlink($targetPath);
        $response['msg'] = "Failed to resample image for thumbnail.";
        return $response;
    }

    $thumbSaved = false;
    switch ($mime) {
        case 'image/gif':
            $thumbSaved = @imagegif($thumb, $thumbPath);
            break;
        case 'image/jpeg':
            $thumbSaved = @imagejpeg($thumb, $thumbPath, 90);
            break;
        case 'image/png':
            $thumbSaved = @imagepng($thumb, $thumbPath, 6);
            break;
    }

    imagedestroy($thumb);
    imagedestroy($srcImg);

    if ($thumbSaved === false) {
        @unlink($targetPath);
        @unlink($thumbPath);
        $response['msg'] = "Failed to save thumbnail to disk.";
        return $response;
    }

    $compute_public = function($uploadDirPath, $fileName) {
        $norm = str_replace('\\', '/', $uploadDirPath);
        if (preg_match('#/images/[^/]+#i', $norm, $m)) {
            $publicBase = trim($m[0], '/');
        } else {
            $publicBase = basename($norm);
        }
        return $publicBase . '/' . $fileName;
    };

    $publicOriginal = $compute_public($uploadDir, $filename);
    $publicThumb    = $compute_public($uploadDir, 'thumbs/' . $filename);

    if ($mode === 'avatar_thumb_only') {
        $response['status']   = 'success';
        $response['msg']      = 'Avatar uploaded; thumbnail generated.';
        $response['original'] = null;
        $response['thumb']    = $publicThumb;
        return $response;
    }

    $response['status']   = 'success';
    $response['msg']      = 'Avatar uploaded successfully.';
    $response['original'] = $publicOriginal;
    $response['thumb']    = $publicThumb;
    
    return $response;
}


function sendAdminNotify(array $payloadData, $target = null, $store = true) {
    $adminSecret = getenv('WS_ADMIN_SECRET');
    $url = 'http://127.0.0.1:8081/notify';

    $body = [
        'payload' => $payloadData,
        'store' => $store
    ];
    if (is_array($target)) $body['target'] = $target;
    elseif ($target !== null) $body['target_user_id'] = $target;

    $json = json_encode($body);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'x-admin-token: ' . $adminSecret
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) {
        error_log("sendAdminNotify curl err: $err");
        return false;
    }
    if ($code < 200 || $code >= 300) {
        error_log("sendAdminNotify http code: $code, resp: $res");
        return false;
    }
    return json_decode($res, true);
}

function getRowCount($mysqli, $table) {
    $res = $mysqli->query("SELECT COUNT(*) as total FROM {$table}");
    $row = $res->fetch_assoc();
    $count = $row['total'];
    $res->free();
    return $count;
}

function spanerror($text) {
	return "<p style='color: #ff6b6b; font-size: 11px;'>".$text."</p>";
}


function Loader($class_name) {
	include_once(BASE_DIRECTORY."classes/".strtolower($class_name).".php");
}

spl_autoload_register("Loader", true, true);

?>