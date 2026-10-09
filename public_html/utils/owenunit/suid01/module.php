<?php
  require 'config.php';

// init graphics
function plt_init($work) {
  // create stubs just in case
  $view = array();
  $info = array();
  $data = array();
  // create initial variables
  extract($work);
  // load configuration
  require 'config.php';
  // =====================
  // add some space for water feed data
  $view['y'] = val_key($view, 'y', 0) - val_key($view, 'yp', 0);
  // rows info
  $info = array_merge($info, $config['line']);
  // get required plots mask
  $list = array_keys($config['line']);
  // all columns
  for ($i = 0; $i < count($list); $i++) {
    $data[] = $list[$i];
  }
  $data = array($data);
  $view = array($view);
  // table name
  $t = $config['item'][0];
  // =====================
  foreach ($work as $k => $v) { $work[$k] = $$k; }
  return($work);
}

// table approximation function
function tab_tmpw($t) {
  $list = array(
    array( -1, 70),
    array( -2, 72),
    array( -3, 75),
    array( -4, 77),
    array( -5, 79),
    array( -6, 81),
    array( -7, 83),
    array( -8, 85),
    array( -9, 87),
    array(-10, 90),
    array(-11, 92),
    array(-12, 94),
    array(-13, 96),
    array(-14, 98),
    array(-15, 100),
    array(-16, 102),
    array(-17, 104),
    array(-18, 106),
    array(-19, 108),
    array(-20, 110),
    array(-21, 112),
    array(-22, 114),
    array(-23, 115)
  );
  do {
    // empty value or out of range
    if (is_null($t) || ($t > 5)) {
      $t = null;
      break;
    }
    // min value
    $p = end($list);
    if ($t <= $p[0]) {
      $t = $p[1];
      break;
    }
    // max value
    $p = reset($list);
    if ($t >= $p[0]) {
      $t = $p[1];
      break;
    }
    // find place in table
    for ($i = 0; $i < (count($list) - 1); $i++) {
      // got interval
      if ($t > $list[$i + 1][0]) {
        // (x1,y1) (x2,y2)
        $p = array($list[$i], $list[$i + 1], 0, 0);
        // k = (y1 - y2) / (x1 - x2)
        $p[2] = ($p[0][1] - $p[1][1]) / ($p[0][0] - $p[1][0]);
        // b = y2 - (k * x2)
        $p[3] = $p[1][1] - ($p[2] * $p[1][0]);
        // y = (a * x) + b
        $t = ($p[2] * $t) + $p[3];
        break;
      }
    }
  } while (0);
  return($t);
}

// add something to the image after it was done
function plt_done($work) {
  // create stubs just in case
  $view = array();
  $type = array(0, 0);
  $image = null;
  // create initial variables
  extract($work);
  // load configuration
  require 'config.php';
  // =====================
  // calculate average temperature
  $v = array(
    strtotime(date('Y-m-d', $type[0]).' 00:00:00'),
    strtotime(date('Y-m-d', $type[1]).' 00:00:00')
  );
  // start and end on the day boundary
  if (($v[0] == $type[0]) && ($v[1] == $type[1])) {
    // days between
    $v = intval(($type[1] - $type[0]) / sec_time(1, 'd'));
    // show only for whole intervals between [1..10] days inclusive
    if (($v >= 1) && ($v <= 10)) {
      $line = $config['line'];
      // all columns
      $list = array_keys($line);
      $what = array();
      for ($i = 0; $i < count($list); $i++) {
        $what[] = $list[$i];
      }
      $list = $what;
      for ($i = 0; $i < count($list); $i++) {
        $list[$i] = sprintf(
          'REPLACE(FORMAT(AVG(%s), 1), \'.\', \',\') AS %s',
          $list[$i], $list[$i]
        );
      }
      $list = sql_exec(
        'SELECT DATE(time) AS time,%s '.
        'FROM %s WHERE time BETWEEN \'%s\' AND \'%s\' '.
        'GROUP BY DATE(time) ORDER BY time',
        implode(',', $list),
        $config['item'][0],
        date('Y-m-d H:i:s', $type[0]), date('Y-m-d H:i:s', $type[1] - 1)
      );
      // got any values
      if (count($list)) {
        // update view
        $view = img_view($view[0]);
        $a = $view['xp'];
        $b = $view['yp'];
        //$c = $a + $view['x'];
        $d = $view['h'] - ($b * 2); //$b + $view['y'];
        $l = img_cint($image, $view['ch']);
        $v = $view['x'] / ($v * 2);
        for ($i = 0; $i < count($list); $i++) {
          $j = intval((strtotime($list[$i]['time'].' 00:00:00') - $type[0]) / sec_time(1, 'd'));
          $w = array();
          // show only for week
          if (count($list) <= 7) {
            // new table values for temperature water feed
            $t = $list[$i][$what[0]];
            $t = is_null($t) ? null : floatval(str_replace(',', '.', $t));
            // get table water temperature value
            $t = tab_tmpw($t);
            // is able to calculate
            if (is_null($t)) {
              $w = array('???', '???', '???');
            } else {
              $p = ($t * 3) / 100;
              $w = array($t, $t - $p, $t + $p);
              for ($p = 0; $p < count($w); $p++) {
                // avoid rounding
                $w[$p] = ($w[$p] < 0) ? (ceil($w[$p] * 10) / 10) : (floor($w[$p] * 10) / 10);
                $w[$p] = str_replace('.', ',', sprintf('%0.1f', $w[$p]));
              }
            }
          }
          $t = 'Ср. суточ. '.$list[$i][$what[0]].PHP_EOL;
          if (!empty($w)) {
            $t .=
              'Тпр по граф. '.$w[0].PHP_EOL.
              'Тпр по граф. -3% '.$w[1].PHP_EOL.
              'Тпр по граф. +3% '.$w[2];
          }
          img_text($image,
            array($a + $v + ($j * $v * 2), 1),
            array($d + $b, 1), $view['fs'],
            $l, $t
          );
        }
      }
    }
  }
  // =====================
  foreach ($work as $k => $v) { $work[$k] = $$k; }
  return($work);
}

function htm_data($work) {
  // create initial variables
  extract($work);
  // load configuration
  require 'config.php';
  ob_start();
  // =====================
?>
    <table class="t"><tr><td>
    <?php echo html_escape($config['name']); ?>
    </td></tr></table>
<?php
  // =====================
  $work = ob_get_contents();
  ob_end_clean();
  return($work);
}

function htm_more($work) {
  // create initial variables
  extract($work);
  ob_start();
  // =====================
  // only days
  $from = substr($from, 0, 10);
  $till = substr($till, 0, 10);
?>
      <br><br>
      <fieldset>
      <legend>Данные в табличном виде</legend>
        Начало: <input type="text" name="fromload" value="<?php echo $from; ?>" size="10" maxlength="10" readonly>
        Конец: <input type="text" name="tillload" value="<?php echo $till; ?>" size="10" maxlength="10" readonly>
        Тип: <select name="typeload">
          <option value="1">Все значения</option>
          <option value="2">Среднечасовые</option>
          <option value="3">Среднесуточные</option>
        </select>
        <input type="checkbox" name="fileload" id="file0"><label for="file0">Файл</label>
        <span>| <a
          href="<?php echo html_escape($_SERVER['REQUEST_URI'].'&out=1&fmt=0&from='.$from.'&till='.$till); ?>"
          target="_blank" id="load">Сформировать</a></span>
      </fieldset>
<?php
  // =====================
  $work = ob_get_contents();
  ob_end_clean();
  return($work);
}

function jsc_data($work) {
  return('1'); // stub
}

function jsc_init($work) {
  // create initial variables
  extract($work);
  ob_start();
?>
  $('*[name$="load"]').change(function() {
    var d = $('#load').attr('href');
    d = d.replace(/fmt=[0-9]+/, 'fmt=' + ($('input[name="fileload"]').attr('checked') ? '1' : '0'));
    d = d.replace(/out=[0-9]+/, 'out=' + $('select[name="typeload"]').val());
    d = d.replace(/from=[0-9\-]+/, 'from=' + $('input[name="fromload"]').val().trim());
    d = d.replace(/till=[0-9\-]+/, 'till=' + $('input[name="tillload"]').val().trim());
    $('#load').attr('href', d);
  }).change();
  $('input[name$="load"][type="text"]').datetimepicker({
    currentText: 'Сейчас',
    closeText: 'Закрыть',
    monthNames: ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'],
    dayNamesMin: ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'],
    dateFormat: 'yy-mm-dd',
    timeFormat: '',
    showTime: false,
    showHour: false,
    showMinute: false,
    showSecond: false,
    showMillisec: false,
    showTimezone: false,
    firstDay: 1,
    minDate: '<?php echo $tmin; ?>',
    maxDate: '<?php echo $tmax; ?>'
  });
<?php
  // =====================
  $work = ob_get_contents();
  ob_end_clean();
  return($work);
}

function out_data($work) {
  // create initial variables
  extract($work);
  // load configuration
  require 'config.php';
  // some variables
  $crlf = chr(13).chr(10);
  $page =
    '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN">'.$crlf.
    '<html><head><meta http-equiv="Content-Type" content="text/html; charset=windows-1251">'.$crlf.
    '<title><!--name--></title><style type="text/css"><!--'.$crlf.
    'body { border: 0; margin: 0; padding: 0; overflow: auto; }'.$crlf.
    'table { border: 0; border-collapse: collapse; margin: auto; }'.$crlf.
    'th,td { border: 1px solid #000000; padding: 4px; }'.$crlf.
    '--></style>'.$crlf.
    '</head><body><!--head--><table>'.$crlf.
    '<!--body-->'.
    '</table><!--code--></body></html>'.$crlf;
  // check if we request something
  $out = intval(val_get('out'));
  if (($out >= 1) && ($out <= 3)) {
    $out--;
    // output format type
    $fmt = val_gint('fmt', 2);
    // from and till dates
    $from = strtotime(val_get('from').' 00:00:00');
    $till = strtotime(val_get('till').' 00:00:00') - 1;
    // build columns list
    $item = array('time' => array(0, 'Дата и время', ''));
    $item = array_merge($item, $config['line']);
    $data = array(
       array('', '', 'a'),
       array('AVG', 'GROUP BY HOUR(time)', 'h'),
       array('AVG', 'GROUP BY DAY(time)', 'd')
    );
    $list = array();
    foreach ($item as $k => $v) {
      $item[$k] = str_replace(PHP_EOL, ' ', $v[1]);
      if (empty($list)) {
        // time
        $list[] = $k;
      } else {
        // data
        $list[] = sprintf(
          'REPLACE(REPLACE(FORMAT(%s(%s), 10), \',\', \'\'), \'.\', \',\') AS %s',
          $data[$out][0], $k, $k
        );
      }
    }
    // get data
    $list = sql_exec(
      'SELECT %s FROM %s WHERE time BETWEEN FROM_UNIXTIME(%u) AND FROM_UNIXTIME(%u) %s ORDER BY time',
      implode(',', $list),
      $config['item'][0],
      $from, $till,
      $data[$out][1]
    );
    $name = date('Ymd', $from).'-'.date('Ymd', $till).'-'.$data[$out][2];
    if (empty($list)) {
      $list = 'NOTHING FOR INTERVAL '.$name;
    } else {
      // got something
      for ($i = 0; $i < count($list); $i++) {
        if (!$fmt) {
          // page
          $list[$i] = '<tr><td>'.implode('</td><td>', $list[$i]).'</td></tr>';
        } else {
          // file
          $list[$i] = implode(';', $list[$i]);
        }
        // connection lost or user aborted
        if (connection_aborted() || connection_status()) {
          $list = array();
          break;
        }
      }
      $list = implode($crlf, $list).$crlf;
      // page of file
      if (!$fmt) {
        // page
        header('Content-Type: text/html; charset=windows-1251');
        $page = str_replace('<!--name-->', $name, $page);
        $list = str_replace('<!--body-->', '<tr><th>'.implode('</th><th>', $item).'</th></tr>'.$crlf.$list, $page);
      } else {
        // file
        header('Content-Type: application/octet-stream');
        header('Content-Transfer-Encoding: binary');
        header('Content-Disposition: attachment; filename="'.$name.'.csv"');
        $list = implode(';', $item).$crlf.$list;
      }
    }
    die($list);
  }
  // return nothing
  return('');
}
