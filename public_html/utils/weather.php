<?php
  // TODO: cache image for 2 days?
  // for $owen_hdr
  require('oweninfo.php');
  // require main module
  require('owenmain.php');
  // require plot module
  require('owenplot.php');
  // current script page
  $page = html_escape($_SERVER['PHP_SELF']);
  // resources - hide site structure
  if (array_key_exists('file', $_GET)) {
    $path = dirname(__FILE__).'/owenmisc/';
    $file = $_GET['file'];
    $data = array();
    $list = array(
      'jquery-ui.css' => 'text/css',
      'jquery-ui.min.js' => 'application/javascript',
      'jquery-1.8.2.min.js' => 'application/javascript'
    );
    if (array_key_exists($file, $list)) {
      $data = array(
        @file_get_contents($path.$file),
        $list[$file],
        @filemtime($path.$file)
      );
      if ($data[1] == 'text/css') {
        $data[0] = str_replace('images/', $page.'?file=', $data[0]);
      }
    } else {
      $path .= 'images/';
      if (preg_match('/^ui\-[a-z0-9_]+\.png$/', $file) && file_exists($path.$file)) {
        $data = array(
          @file_get_contents($path.$file),
          'image/png',
          @filemtime($path.$file)
        );
      }
    }
    if (!empty($data)) {
      header('Last-Modified: '.gmdate('D, d M Y H:i:s', $data[2]).' GMT');
      header('Content-Type: '.$data[1]);
      header('Content-Length: '.strlen($data[0]));
      echo $data[0];
    }
    exit;
  }
  // current day
  $time = strtotime(date('Y-m-d 00:00:00'));
  // get variables
  $from = val_get('from');
  $till = val_get('till');
  $days = val_get('days');
  // interval correct only when both boundary are set
  if (is_null($from)) { $till = null; }
  if (is_null($till)) { $from = null; }
  // no interval or invalid interval
  if (is_null($from)) {
    // default value (2 days)
    $days = is_null($days) ? '2' : $days;
    // must be in 1..10 days interval
    $days = intval($days);
    $days = max($days, 1);
    $days = min($days, 10);
    // calculate $from and $till
    $from = $time - (sec_time(1, 'd') * ($days - 1));
    $till = $time + (sec_time(1, 'd') * 1);
  } else {
    // interval specified - days ignored
    $days = 0;
    // string to time
    $from = strtotime($from.' 00:00:00');
    $till = strtotime($till.' 00:00:00');
  }
  // connect to MySQL
  if (!sql_open()) {
    die('ERROR: MYSQL INITIALIZATION FAILED');
  }
  // cap intervals
  $stop = get_stop('owen_air'); // TODO: hardcoded name
  $from = max($from, strtotime(date('Y-m-d 00:00:00', $stop[0])));
  $till = min($till, strtotime(date('Y-m-d 00:00:00', $stop[1])) + sec_time(1, 'd'));
  //$till = min($till, $time + sec_time(1, 'd'));
  // image required
  $image = val_get('image');
  if (!is_null($image)) {
    // days between
    $time = intval(($till - $from + 1) / sec_time(1, 'd'));
    // average minutes to sum
    $time = max($time, 1) * 60;
    // input data
    $view = img_view();
    // logo font size
    $view['fl'] = 95;
    // logo text
    $view['tl'] = 'Юргинская ТЭЦ'.PHP_EOL.$owen_hdr;
     // only one axes
    $view['mt'] = 1;
    // when interpolation should kicks in
    $view['si'] = $time;
    // no axis cookie information
    $view['ac'] = null;
    // TODO: hardcoded names: owen_air, tmpo
    $text = array(
      'time' => array($from, $till),
      'tmpo' => array(0x0000FF, 'Температура'.PHP_EOL.'воздуха', '&#176;C'),
      'null' => array(0xFF0000, 'Интерполяция')
    );
    $type = $text['time'];
    // get data from the database
    $list = sql_exec(
      'SELECT UNIX_TIMESTAMP(MIN(time)) AS time, AVG(tmpo) AS tmpo '.
      'FROM owen_air WHERE time >= \'%s\' AND time <= \'%s\' '.
      'GROUP BY ((UNIX_TIMESTAMP(time) - %u) DIV %u) ORDER BY time',
      date('Y-m-d H:i:s', $from), date('Y-m-d H:i:s', $till),
      $from, $time
    );
    // make image
    $image = img_make($view, $list, $text);
    $view = img_view($view);
    // image created
    if (!is_null($image)) {
      // show only for whole intervals between [1..10] days inclusive
      $v = intval(($till - $from) / sec_time(1, 'd'));
      if (($v >= 1) && ($v <= 10)) {
        $list = sql_exec(
          'SELECT DATE(time) AS time, REPLACE(FORMAT(AVG(tmpo), 1), \'.\', \',\') AS tmpo '.
          'FROM owen_air WHERE time BETWEEN \'%s\' AND \'%s\' '.
          'GROUP BY DATE(time) ORDER BY time',
          date('Y-m-d H:i:s', $from), date('Y-m-d H:i:s', $till - 1)
        );
        // got any values
        if (count($list)) {
          $a = $view['xp'];
          $b = $view['yp'];
          $c = $a + $view['x'];
          $d = $b + $view['y'];
          $l = img_cint($image, $view['ch']);
          $v = $view['x'] / ($v * 2);
          for ($i = 0; $i < count($list); $i++) {
            $j = intval((strtotime($list[$i]['time'].' 00:00:00') - $from) / sec_time(1, 'd'));
            img_text($image,
              array($a + $v + ($j * $v * 2), 1),
              array($d + $b, 0), $view['fs'],
              $l, 'Ср.суточ. '.$list[$i]['tmpo']
            );
          }
        }
      }
      // output image
      img_draw($image);
    }
    exit;
  }
  // min/max range
  $stop[0] = html_escape(date('Y-m-d', $stop[0]));
  $stop[1] = html_escape(date('Y-m-d', $stop[1]));
  // build image query string
  $image = html_escape('&days='.$days.($days ? '' : '&from='.date('Y-m-d', $from).'&till='.date('Y-m-d', $till)));
  // from .. till
  $from = html_escape(date('Y-m-d', $from));
  $till = html_escape(date('Y-m-d', $till));
  // header
  $owen_hdr = html_escape($owen_hdr);
  // set correct code page
  header('Content-Type: text/html; charset=windows-1251');
  // current temperature
  $temp = sql_exec('SELECT time,REPLACE(FORMAT(tmpo, 1), \'.\', \',\') AS tmpo FROM owen_air ORDER BY time DESC LIMIT 1'); // TODO: hardcoded names
  $temp = array_pop($temp);
  // no values or no values for last 3 minutes
  if (empty($temp) || ((strtotime($temp['time']) + (3 * 60)) < time())) {
    $temp = '(нет связи с датчиком)';
  } else {
    $temp = array_pop($temp).chr(176).'C';
  }
  $temp = html_escape($temp);
  // temperature only
  if (array_key_exists('tmp', $_GET)) { die($temp); }
  // wait before update (in seconds)
  $wait = 5 * 60;
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN">
<html>
<head>
<meta http-equiv="imagetoolbar" content="no">
<meta http-equiv="Content-Type" content="text/html; charset=windows-1251">
<title>Текущая температура в районе Юргинской ТЭЦ <?php echo $owen_hdr; ?></title>
<?php if ($days === 2) { ?>
<noscript><meta http-equiv="refresh" content="<?php echo $wait; ?>"></noscript>
<?php } ?>
<link rel="icon" href="favicon.ico" type="image/x-icon">
<link rel="stylesheet" type="text/css" href="<?php echo $page; ?>?file=jquery-ui.css">
<script type="text/javascript" src="<?php echo $page; ?>?file=jquery-1.8.2.min.js"></script>
<script type="text/javascript" src="<?php echo $page; ?>?file=jquery-ui.min.js"></script>
<style type="text/css">
<!--
body, img {
  border: 0;
  margin: 0;
  padding: 0;
}

p {
  margin-bottom: 0;
}

body {
  text-align: center;
  align: center;
}

input[type="text"] {
  cursor: pointer;
}
-->
</style>
</head>
<body>
<h1>Текущая температура в районе<br>Юргинской ТЭЦ <?php echo $owen_hdr; ?></h1>
<h2 id="tmp"><?php echo $temp; ?></h2>
<p>
<a href="<?php echo $page; ?>?days=1">Данные за сутки</a> |
<a href="<?php echo $page; ?>?days=2">Данные за двое суток</a> |
<a href="<?php echo $page; ?>?days=3">Данные за трое суток</a> |
<a href="<?php echo $page; ?>?days=7">Данные за семь дней</a>
</p>
<form method="GET" action="<?php $page; ?>"><p>
Произвольный период: <input type="hidden" name="days" value="0"><br>
Начало: <input type="text" name="from" id="from" value="<?php echo $from; ?>" readonly>
Окончание: <input type="text" name="till" id="till" value="<?php echo $till; ?>" readonly>
<input type="submit" value="Показать">
</p></form>
<p><img id="img" alt="" src="<?php echo $page; ?>?image=<?php echo strval(time()).$image; ?>"></p>
<script type="text/javascript">
$(function() {
  var m = ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'];
  var d = ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'];
  $('#from').datepicker({dateFormat: 'yy-mm-dd'});
  $('#from').datepicker('option', 'monthNames', m);
  $('#till').datepicker({dateFormat: 'yy-mm-dd'});
  $('#till').datepicker('option', 'monthNames', m);
  $('#from').datepicker('option', 'dayNamesMin', d);
  $('#till').datepicker('option', 'dayNamesMin', d);
  $('#from').datepicker('option', 'firstDay', 1);
  $('#till').datepicker('option', 'firstDay', 1);
  $('#from').datepicker('option', 'minDate', '<?php echo $stop[0]; ?>');
  $('#till').datepicker('option', 'minDate', '<?php echo $stop[0]; ?>');
  $('#from').datepicker('option', 'maxDate', '<?php echo $stop[1]; ?>');
  $('#till').datepicker('option', 'maxDate', '<?php echo $stop[1]; ?>');
  $('#from').datepicker('setDate', '<?php echo $from; ?>');
  $('#till').datepicker('setDate', '<?php echo $till; ?>');
<?php if ($days === 2) { ?>
  setInterval(function() {
    var obj = $('#img');
    obj.attr('src', obj.attr('src').replace(/image=[0-9]+/i, 'image=' + $.now()));
    $.get('<?php echo $page; ?>?tmp=' + $.now()).done(function(data) { $('#tmp').text(data); });
  }, <?php echo $wait; ?> * 1000);
<?php } ?>
});
</script>
</body>
</html>
