<?php
  // main module
  require('oweninfo.php');
  // TODO: support for legacy functions
function end_list($list) {
  return(is_array($list) ? end($list) : $list);
}
if (!function_exists('get_magic_quotes_gpc')) {
  function get_magic_quotes_gpc() { return(false); }
}
if (!function_exists('mysql_connect')) {
  $__mysql = false;
  function mysql_get_server_info() {
  global $__mysql;
    return(($__mysql !== false) ? $__mysql->server_info : '');
  }
  function mysql_connect($host, $user, $pass) {
  global $__mysql;
    $__mysql = mysqli_connect($host, $user, $pass);
    return($__mysql);
  }
  function mysql_select_db($value) {
  global $__mysql;
    return(mysqli_select_db($__mysql, $value));
  }
  function mysql_close() {
  global $__mysql;
    return(mysqli_close($__mysql));
  }
  function mysql_errno() {
  global $__mysql;
    return(mysqli_errno($__mysql));
  }
  function mysql_error() {
  global $__mysql;
    return(mysqli_error($__mysql));
  }
  function mysql_query($value) {
  global $__mysql;
    return(mysqli_query($__mysql, $value));
  }
  function mysql_real_escape_string($value) {
  global $__mysql;
    return(mysqli_real_escape_string($__mysql, $value));
  }
  function mysql_fetch_assoc($value) {
    return(mysqli_fetch_assoc($value));
  }
  function mysql_free_result($value) {
    return(mysqli_free_result($value));
  }
  function mysql_num_rows($value) {
    return(mysqli_num_rows($value));
  }
}

// convert Windows ANSI to UTF-8 codepage
function toutf8($text) {
  return(@iconv('CP1251', 'UTF-8', $text));
}

// convert UTF-8 to Windows ANSI codepage
function tocp1251($text) {
  return(@iconv('UTF-8', 'CP1251', $text));
}

// escapes output text
function html_escape($text) {
  return(htmlspecialchars($text, ENT_COMPAT, 'cp1251'));
}

// tiny $_COOKIE helper
function val_cookie($key, $value = null) {
  return(array_key_exists($key, $_COOKIE) ? $_COOKIE[$key] : $value);
}

// tiny $_GET helper
function val_get($key, $value = null) {
  return(array_key_exists($key, $_GET) ? $_GET[$key] : $value);
}

// tiny $_POST helper
function val_post($key, $value = null) {
  if (array_key_exists($key, $_POST)) {
    $value = get_magic_quotes_gpc() ? stripslashes($_POST[$key]) : $_POST[$key];
  }
  return($value);
}

// tiny $_GET helper for arrays indexes and default values
function val_gint($name, $max = null) {
  $name = intval(val_get($name));
  if ($name < 0) { $name = 0; }
  if ((!is_null($max)) && ($name >= $max)) { $name = 0; }
  return($name);
}

// return value by key from array if exists or default one
function val_key($list, $key, $value = null) {
  return(array_key_exists($key, $list) ? $list[$key] : $value);
}

// return this page full uri
function uri_self($drop = array()) {
  $s = val_key($_SERVER, 'HTTPS', '');
  $s = (!strcasecmp($s, 'on')) || (strval($s) == '1') || (!strcasecmp(val_key($_SERVER, 'HTTP_X_FORWARDED_PROTO', ''), 'https'));
  $s =
    'http'.(empty($s) ? '' : 's').'://'.
    val_key($_SERVER, 'HTTP_HOST', '').
    val_key($_SERVER, 'REQUEST_URI', '');
  // drop any arguments if required
  if (is_null($drop)) {
    // drop everything
    $s = preg_replace('/[?].*$/', '', $s);
  } else {
    // drop arguments list
    $drop = array_values(is_array($drop) ? $drop : array());
    for ($i = 0; $i < count($drop); $i++) {
      $k = preg_quote($drop[$i]);
      $s = preg_replace('/([?]'.$k.'=[^&]+$|[&]'.$k.'=[^&]+$|'.$k.'=[^&]+&)/i', '', $s);
    }
  }
  return($s);
}

// quote MySQL text
function sql_text($text) {
  if (defined('OWEN_DB_ON')) {
    $text = mysql_real_escape_string($text);
  }
  return($text);
}

// execute MySQL query
function sql_exec($query) {
  $list = func_get_args();
  $query = array_shift($list);
  // more than one argument - formatted string
  if (!empty($list)) {
    $query = vsprintf($query, $list);
  }
  $list = array();
  // database online and connected
  if (defined('OWEN_DB_ON')) {
    // execute query
    $query = mysql_query($query);
    // @TODO: better way to resolve this than stop here?
    if ($query === false) {
      die('ERROR: [MySQL:'.mysql_errno().'] '.mysql_error());
    }
    // has any rows to return
    if ($query !== true) {
      // read results
      $k = mysql_num_rows($query);
      for ($i = 0; $i < $k; $i++) {
        $list[] = mysql_fetch_assoc($query);
      }
      mysql_free_result($query);
    }
  }
  return($list);
}

// test that required table exists in database
function sql_test($name) {
  $result = false;
  // database online and connected
  if (defined('OWEN_DB_ON')) {
    $list = sql_exec('SHOW TABLES');
    $list = array_map('end_list', $list);
    $result = in_array($name, $list);
  }
  return($result);
}

// open MySQL database
function sql_open() {
  // not already connected
  if (!defined('OWEN_DB_ON')) {
    // get $sql_info data
    $sql_info = array();
    require('oweninfo.php');
    // connect to the database
    $s = @mysql_connect($sql_info['host'], $sql_info['user'], $sql_info['pass']);
    // connected
    if ($s !== false) {
      if (mysql_select_db($sql_info['base'])) {
        // indication that connection established
        define('OWEN_DB_ON', 1);
        // execute connection commands
        for ($i = 0; $i < count($sql_info['list']); $i++) {
          sql_exec($sql_info['list'][$i]);
        }
      } else {
        // no database - close existing connection
        @mysql_close($s);
      }
    }
  }
  return(defined('OWEN_DB_ON'));
}

// min/max for specified table
function get_stop($name) {
  $name = preg_replace('/[^a-z0-9_]/is', '', $name);
  $list = sql_exec('SELECT MIN(time), MAX(time) FROM '.$name);
  if (empty($list)) {
    $list = array(0, 0);
  } else {
    $list = array_pop($list);
    $list = array_values($list);
    $list = array_map('strtotime', $list);
  }
  return($list);
}

// generate random token code
function newtoken($size = 12) {
  $size = abs(intval($size));
  $l =
    implode(range('a', 'z')).
    implode(range('0', '9')).
    implode(range('A', 'Z'));
  $m = strlen($l);
  $s = '';
  for ($i = 0; $i < $size; $i++) {
    //do {
      $c = $l[mt_rand(0, $m - 1)];
    //} while (strpos(strtolower($s), strtolower($c)) !== false);
    $s .= $c;
  }
  return($s);
}

// test that present in bitmask
function testsuid($suid, $mask) {
  // empty mask - test failed
  $result = empty($mask) ? false : true;
  // present in database
  if ($result) {
    // empty suid - for services listing
    $result = empty($suid) ? true : false;
    // check agains allowed mask
    if (!$result) {
      $suid = intval($suid);
      $result = (($suid >= 1) && ($suid <= 32)) ? true : false;
      if ($result) {
        $result = ($mask & (1 << ($suid - 1))) ? true : false;
      }
    }
  }
  return($result);
}

// authorize or get id and suid for already authorized user
function get_user() {
  $user = array();
  do {
    // no database
    if (!defined('OWEN_DB_ON')) {
      break;
    }
    // already authorized
    if (defined('OWEN_USER')) {
      $user = explode('|', constant('OWEN_USER'));
      $user = array_map('intval', $user);
      break;
    }
    // variables to reduce code
    $sql_find = 'SELECT id,suid FROM owen_usr WHERE id=%u AND auth=\'%s\' AND `time` IS NOT NULL AND `time` > NOW() LIMIT 1';
    $sql_make = 'UPDATE owen_usr SET auth=\'%s\',`time`=NOW() + INTERVAL 30 DAY WHERE id=%u';
    $ses_preg = '/^u([0-9]+)s([0-9a-zA-Z]+)$/';
    $act_preg = '/^u([0-9]+)a([0-9a-zA-Z]+)$/';
    // [1] check cookie (already authorized)
    $auth = val_cookie('auth', '');
    if (preg_match($ses_preg, $auth)) {
      // find auth key in database
      $list = sql_exec($sql_find, intval(substr($auth, 1)), sql_text($auth));
      // found
      if (!empty($list)) {
        // update session key and time
        sql_exec($sql_make, sql_text($auth), intval(substr($auth, 1)));
        // 1 month
        setcookie('auth', $auth, time() + (30 * 24 * 60 * 60));
        // user id and service list mask
        $user = array(
          $list[0]['id'],
          $list[0]['suid']
        );
        // prefetched
        define('OWEN_USER', implode('|', $user));
        break;
      } else {
        // remove cookie
        setcookie('auth', '');
      }
    }
    // [2] do link activation
    $auth = val_post('auth', '');
    if (preg_match($act_preg, $auth)) {
      // find auth key in database
      $list = sql_exec($sql_find, intval(substr($auth, 1)), sql_text($auth));
      // found
      if (!empty($list)) {
        // create cookie session key
        $auth = 'u'.$list[0]['id'].'s'.newtoken();
        // update session key and time
        sql_exec($sql_make, sql_text($auth), intval(substr($auth, 1)));
        // set cookie for one month
        setcookie('auth', $auth, time() + (30 * 24 * 60 * 60));
        // redirect
        header('Location: '.uri_self(null));
        exit;
      }
    }
    // [3] attempt to activate link (prevent instant messaging systems link prefetch)
    $auth = val_get('auth', '');
    if (preg_match($act_preg, $auth)) {
      // find auth key in database
      $list = sql_exec($sql_find, intval(substr($auth, 1)), sql_text($auth));
      // found
      if (!empty($list)) {
        // show activation page
        header('Content-Type: text/html; charset=windows-1251');
        echo
          '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN">'.PHP_EOL.
          '<html><head><meta http-equiv="Content-Type" content="text/html; charset=windows-1251">'.PHP_EOL.
          '<title>ACTIVATE</title></head><body>'.PHP_EOL.
          '<form method="post" action="'.html_escape(uri_self(null)).'"><div>'.PHP_EOL.
          '<input type="hidden" name="auth" value="'.html_escape($auth).'">'.PHP_EOL.
          '<input type="submit" value="ACTIVATE" style="cursor:pointer"></div></form></body></html>'.PHP_EOL;
        exit;
      }
    }
  } while (0);
  return($user);
}

// check if token code authorized for this suid
function authsuid() {
  $result = get_user();
  $result = empty($result) ? 0 : $result[1];
  return($result);
}

// load user config settings
function cfg_load($suid, $null = array()) {
  $data = array(
    'data' => null,
    'item' => null,
    'type' => null,
    'hour' => null,
    'from' => null,
    'till' => null
  );
  $user = get_user();
  // user found and suid not empty
  if ((!empty($user)) && (!empty($suid))) {
    $list = sql_exec(
      'SELECT data FROM owen_cfg WHERE `user` = %u AND suid = %u LIMIT 1',
      $user[0], $suid
    );
    $data = count($list) ? json_decode($list[0]['data'], true) : $data;
  }
  // default values
  foreach ($data as $k => $v) {
    $data[$k] = (is_null($v) && array_key_exists($k, $null)) ? $null[$k] : $v;
  }
  return($data);
}

// save user config settings
function cfg_save($suid) {
  $user = get_user();
  // user found and suid not empty
  if ((!empty($user)) && (!empty($suid))) {
    // load already existing config
    $data = cfg_load($suid);
    // refresh with new values
    foreach ($data as $k => $v) {
      $v = val_get($k);
      if (!is_null($v)) {
        $v = trim(strval($v));
        $v = preg_replace('/[\s]+/s', ' ', $v);
        $v = preg_replace('/[^\s0-9:-]/s', '', $v);
        $v = (strval(intval($v)) == $v) ? intval($v) : $v;
        $data[$k] = $v;
      }
    }
    $data = json_encode($data);
    $data = sql_text($data);
    // add or update settings
    sql_exec(
      'INSERT INTO owen_cfg (`user`, suid, data) '.
      'VALUES (%u, %u, \'%s\') ON DUPLICATE KEY '.
      'UPDATE `user` = %u, suid = %u, data = \'%s\'',
      $user[0], $suid, $data,
      $user[0], $suid, $data
    );
  }
}

// image color from integer
function img_cint(&$image, $color) {
  return(
    is_null($image) ? false : imagecolorallocate(
      $image, ($color >> 16) & 0xFF, ($color >> 8) & 0xFF, $color & 0xFF
  ));
}

// image integer from color
function img_intc(&$image, $color) {
  if (!is_null($image)) {
    $color = @imagecolorsforindex($image, $color);
    $color = empty($color) ? 0 : (
      (($color['red'] & 0xFF) << 16) | (($color['green'] & 0xFF) << 8) | ($color['blue'] & 0xFF)
    );
  } else {
    $color = 0;
  }
  return($color);
}

// fit image in a text box
function img_tbox($font, $size, $angle, $text, $wmax, $hmax) {
  $orig = array($size, $text);
  while ($size > 0) {
    // remove white spaces and line feeds
    $text = trim(preg_replace('/\s+/s', ' ', $text));
    $slen = strlen($text) - 1;
    $w = $wmax + 1;
    $h = $hmax + 1;
    $last = -1;
    $i = 0;
    while ($i <= $slen) {
      // space or last character in a string
      if (($text[$i] == ' ') || ($i == $slen)) {
        $list = imagettfbbox($size, $angle, $font, substr($text, 0, $i + 1));
        $w = abs($list[4] - $list[0]) + 1;
        $h = abs($list[5] - $list[1]) + 1;
        // too high
        if ($h > $hmax) { break; }
        // too wide
        if ($w > $wmax) {
          // too big to fit for current font size
          if ($last == -1) { break; }
          // break line
          $text = substr_replace($text, PHP_EOL, $last, 1);
          // update text size
          $slen += strlen(PHP_EOL) - 1;
          // this character again
          $i--;
          $last = -1;
        } else {
          $last = $i;
        }
      }
      $i++;
    }
    // suitable font size found
    if (($w <= $wmax) && ($h <= $hmax)) { break; }
    $size--;
  }
  // return font size and formatted text
  return($size ? array($size, $text) : $orig);
}

// PHP 8.2+ Deprecated: Implicit conversion from float 62.5 to int loses precision
function img_line(&$image, $x1, $y1, $x2, $y2, $color) {
  $x1 = intval($x1);
  $y1 = intval($y1);
  $x2 = intval($x2);
  $y2 = intval($y2);
  imageline($image, $x1, $y1, $x2, $y2, $color);
}

// draw text on image
function img_text(&$image, $x, $y, $size, $color, $text, $angle = 0, $tbox = array()) {
  $w = 0;
  $h = 0;
  if (!is_null($image)) {
    $font = dirname(__FILE__).'/owenmisc/arial.ttf';
    // convert text to utf-8
    $text = toutf8($text);
    // scale text size if negative or leave as is if positive
    $size = ($size < 0) ? ((imagesy($image) * (-$size)) / 760) : $size;
    // fit to text box required
    if (count($tbox) == 2) {
      list($size, $text) = img_tbox($font, $size, $angle, $text, reset($tbox), end($tbox));
      //imagerectangle($image, $x, $y, $x + reset($tbox), $y + end($tbox), $color);
    }
    // get text boundary box
    $list = imagettfbbox($size, $angle, $font, $text);
    // image dimension
    $w = abs($list[4] - $list[0]) + 1;
    $h = abs($list[5] - $list[1]) + 1;
    // extended position - centered relative to specified point
    if (is_array($x)) {
      $t = abs(intval(array_pop($x))) % 3;
      $x = intval(array_pop($x));
      $x -= ($w * $t) / 2;
    }
    if (is_array($y)) {
      $t = abs(intval(array_pop($y))) % 3;
      $y = intval(array_pop($y));
      $y -= ($h * $t) / 2;
    }
    // center position: true - center; false - right/bottom aligned
    if (is_bool($x)) { $x = (imagesx($image) - $w) / ($x ? 2 : 1); }
    if (is_bool($y)) { $y = (imagesy($image) - $h) / ($y ? 2 : 1); }
    //imagerectangle($image, $x, $y, $x + $w, $y + $h, $color);
    // output text (negative color disables antialising)
    imagettftext($image, $size, $angle, intval($x - $list[6]), intval($y - $list[7]), $color, $font, $text);
  }
  return(array($w, $h));
}

// create new image
function img_init($width, $height, $back) {
  // create palette-based image (less size)
  $image = @imagecreate($width, $height);
  if (!empty($image)) {
    // first call to imagecolorallocate() fills the background color in palette-based images
    img_cint($image, $back);
    ob_start();
  } else {
    $image = null;
  }
  return($image);
}

// output and destroy image
function img_draw(&$image) {
  if (!is_null($image)) {
    if ((!headers_sent()) && (!ob_get_length())) {
      ob_end_clean();
      header('Content-Type: image/png');
      imagepng($image, null, 9, PNG_ALL_FILTERS);
      exit;
    } else {
      ob_end_flush();
    }
    imagedestroy($image);
  }
}

// generate unit module path
function mod_path($suid, $file = '') {
  return(sprintf('%s/owenunit/suid%02u/%s', dirname(__FILE__), $suid, $file));
}

// read config file for specified suid
function mod_read($suid) {
  $suid = mod_path($suid, 'config.php');
  if (file_exists($suid)) { require $suid; }
  return(isset($config) ? $config : null);
}

// build global all suid bit-mask
function get_mask($suid = 0) {
  // build and cache suid list mask
  if (!defined('SUIDMASK')) {
    $mask = 0;
    for ($i = 0; $i < 32; $i++) {
      if (file_exists(mod_path($i + 1))) {
        $mask |= 1 << $i;
      }
    }
    // create mask for further calls
    define('SUIDMASK', $mask);
  }
  if (empty($suid)) {
    // if empty - get all bit-mask
    $suid = SUIDMASK;
  } else {
    // else - check that required suid exists
    $suid = SUIDMASK & (1 << ($suid - 1));
  }
  return($suid);
}

// get suid specific configuration
function get_data($suid, $type = null) {
  $config = array(
    'name' => '',
    'data' => '',
    'item' => array('', '', ''),
    'line' => array(),
    'test' => array(),
    'list' => array()
  );
  // suid exists
  if (get_mask($suid)) {
    // build path
    $file = mod_path($suid, 'config.php');
    // file exists
    if (file_exists($file)) {
      // include file
      require($file);
    }
  }
  return(empty($type) ? $config : val_key($config, $type, array()));
}

// get suid list info
function get_main_suid_list($type) {
  $mask = get_mask();
  for ($i = 0; $i < 32; $i++) {
    if ($mask & (1 << $i)) {
      $list = get_data($i + 1);
      $suid[$i + 1] = array(
        $list['name'],
        $list['item'][0],
        $list['data']
      );
    }
  }
  $type = intval($type);
  $list = array();
  foreach ($suid as $k => $v) {
    $list[$k] = val_key($v, $type);
  }
  return($list);
}
