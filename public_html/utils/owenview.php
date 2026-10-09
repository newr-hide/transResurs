<?php
  // for debug only, do not touch
  if (file_exists(substr(__FILE__, 0, -strlen(basename(__FILE__))).'--test--')) {
    define('DEBUGGER', time());
  }
  // require main module
  require('owenmain.php');
  // require plot module
  require('owenplot.php');
  // for $owen_hdr title
  require('oweninfo.php');
  // time limit 55 seconds
  set_time_limit(55);

// call user-specific function if exists and filter return arguments
function safecall($name, $list = '') {
  if (function_exists($name)) {
    $args = call_user_func($name, is_array($list) ? $list : array());
    // only if array returned
    if (is_array($args) && is_array($list)) {
      foreach ($list as $k => $v) {
        $list[$k] = val_key($args, $k, $v);
      }
    } else {
      // if not array - must be string (for HTML / JS output)
      $list = strval($args);
    }
  }
  return($list);
}

function plot_date() {
  // last hours to show
  $data = strtotime(date('Y-m-d H:i:00'));
  // align to the next 15 min (last hours only)
  if (val_gint('type') == 4) {
    $i = intval(date('i', $data)) % 15;
    if ($i) {
      $data += sec_time(15 - $i, 'm');
    }
  }
  // for debug only, do not touch
  if (defined('DEBUGGER')) { $data = strtotime(date('2022-02-12 H:i:00', $data)); }
  // whole day boundary
  $i = strtotime(date('Y-m-d 00:00:00', $data)) + 86400;
  // types: last 1, 2, 3, 7 days, last hours or dates interval
  $type = array(
    array($i - (1 * 86400), $i),
    array($i - (2 * 86400), $i),
    array($i - (3 * 86400), $i),
    array($i - (7 * 86400), $i),
    array($data - (val_gint('hour') * 3600), $data),
    array(@strtotime(val_get('from')), @strtotime(val_get('till')))
  );
  // get type
  $type = $type[val_gint('type', count($type))];
  // fix type times
  for ($i = 0; $i < count($type); $i++) {
    // current time if empty
    if (!$type[$i]) { $type[$i] = $data; }
    // align with minute boundary
    $type[$i] -= ($type[$i] % 60);
  }
  return($type);
}

function plot_draw($suid) {
// for $owen_hdr
require('oweninfo.php');
  // input data
  $view = img_view();
  // logo font size
  $view['fl'] = 95;
  // logo text
  $view['tl'] = $owen_hdr;
  // for debug only, do not touch
  if (defined('DEBUGGER')) { $view['w'] = 950; $view['h'] = 450; }
  // get dates interval
  $type = plot_date();
  // rows info
  $info = array(
    // horizontal axis row
    'time' => $type
  );
  $data = array();
  $t = '';
  // call suid specific handler
  $list = array(
    'view' => $view,
    'info' => $info,
    'data' => $data,
    'sqlq' =>
      'SELECT UNIX_TIMESTAMP(time) AS time,%s FROM %s '.
      'WHERE time BETWEEN \'%s\' AND \'%s\' ORDER BY time',
    't'    => ''
  );
  $list = safecall('plt_init', $list);
  extract($list);
  // build graph
  $image = null;
  for ($i = 0; $i < count($view); $i++) {
    // get data from the database
    $list = sql_exec($sqlq,
      implode(',', array_values($data[$i])), $t,
      sql_text(date('Y-m-d H:i:s', $type[0])),
      sql_text(date('Y-m-d H:i:s', $type[1]))
    );
    // add time
    $more = $data[$i];
    $more[] = 'time';
    // result
    $text = array();
    // only required data
    foreach ($info as $k => $v) {
      if (in_array($k, $more)) {
        $text[$k] = $v;
      }
    }
    // add interpolation values
    if (!array_key_exists('null', $text)) {
      $text['null'] = array(0x000000, 'Интерполяция');
    }
    // build image
    $view[$i]['mt'] = count($view);
    // multi-axes plot legend count
    $view[$i]['ml'] = array_map('count', $data);
    $v = img_make($view[$i], $list, $text);
    // for debug only, do not touch
    if (defined('DEBUGGER')) {
      //imagepng($v, substr(__FILE__, 0, -strlen(basename(__FILE__))).'plotest'.$i.'.png', 0, PNG_NO_FILTER);
    }
    if (is_null($image)) {
      $image = $v;
    } else {
      // merge quadro images
      imagecopymerge($image, $v, 0, 0, 0, 0, imagesx($v), imagesy($v), 100);
      imagedestroy($v);
    }
  }
  // image created
  if (!is_null($image)) {
    // call suid specific handler
    $list = array(
      'view' => $view,
      'type' => $type,
      'image' => $image
    );
    $list = safecall('plt_done', $list);
    extract($list);
    // output image
    img_draw($image);
  }
}

function plot_text($suid) {
  // table and sensors description for suid
  $data = get_data($suid, 'item');
  $data = array($data[0], get_data($suid, 'test'));
  $result = array();
  // check that service not stopped
  $list = sql_exec(
    'SELECT `from` FROM owen_off WHERE suid=%u AND till IS NULL LIMIT 1',
    $suid
  );
  if (!empty($list)) {
    $result[] = sprintf('Сервис остановлен с %s.', $list[0]['from']);
  } else {
    // check that data connection still there
    $list = sql_exec(
      'SELECT UNIX_TIMESTAMP(MAX(time)) AS time FROM %s LIMIT 1',
      $data[0]
    );
    if (!empty($list)) {
      if ((intval($list[0]['time']) + sec_time(5, 'm')) < time()) {
        $result[] = 'Нет связи с локальным компьютером.';
      } else {
        // check if there any issues with sensors
        $v = array_keys($data[1]);
        for ($k = 0; $k < count($v); $k++) {
          $v[$k] = sprintf('MAX(%s) AS %s', $v[$k], $v[$k]);
        }
        $list = sql_exec(
          'SELECT %s FROM %s WHERE time>=\'%s\'',
          implode(',', $v),
          $data[0],
          sql_text(date('Y-m-d H:i:00', time() - sec_time(15, 'm')))
        );
        // got any results
        if (!empty($list)) {
          foreach ($list[0] as $k => $v) {
            if (is_null($v)) {
              $result[] = sprintf('Нет связи с датчиком "%s".', $data[1][$k]);
            }
          }
        }
      }
    }
  }
  // for debug only, do not touch
  if (defined('DEBUGGER')) { $result = array(); }
  // check intersection with offline periods
  $list = plot_date();
  for ($k = 0; $k < count($list); $k++) {
    $list[$k] = sql_text(date('Y-m-d H:i:s', $list[$k]));
  }
  // FROM_UNIXTIME(%u)
  $list = sql_exec(
    'SELECT `from`,till FROM owen_off WHERE (suid=%u) AND (till IS NOT NULL) AND '.
    '((`from` BETWEEN \'%s\' AND \'%s\') OR (till BETWEEN \'%s\' AND \'%s\')) '.
    'ORDER BY `from`',
    $suid, $list[0], $list[1], $list[0], $list[1]
  );
  for ($k = 0; $k < count($list); $k++) {
    $v = $list[$k];
    $result[] = sprintf('Сервис был отключён с %s по %s из выбранного периода.', $v['from'], $v['till']).PHP_EOL;
  }
  return(implode(PHP_EOL, $result));
}

function page_info($suid) {
  $time = strtotime(date('Y-m-d 00:00', time()));
  $list = array(
    'tnow' => $time,
    'tmin' => $time,
    'tmax' => $time,
    'from' => $time,
    'till' => $time
  );
  // suid table name value
  $data = get_data($suid, 'item');
  // get min and max time values
  if (!empty($data[0])) {
    $data = sql_exec(
      'SELECT UNIX_TIMESTAMP(MIN(time)) AS tmin, UNIX_TIMESTAMP(MAX(time)) AS tmax FROM %s',
      $data[0]
    );
    $data = array_pop($data);
    foreach ($data as $k => $v) {
      if (array_key_exists($k, $list)) {
        $list[$k] = $v;
      }
    }
  }
  // allow to select next day
  $data = sec_time(1, 'd');
  $list['till'] += $data;
  $list['tmax'] += $data;
  // convert to text format
  foreach ($list as $k => $v) {
    $list[$k] = html_escape(date('Y-m-d 00:00', $v));
  }
  return($list);
}

  // connect to MySQL
  if (!sql_open()) {
    die('ERROR: MYSQL INITIALIZATION FAILED');
  }
  // get available services list
  $suid_list = get_main_suid_list(0);
  // service unique identifier
  $suid = intval(val_get('suid'));
  // not zero and not known service
  if ($suid && (!array_key_exists($suid, $suid_list))) {
    die('ERROR: INVALID SUID');
  }
  $flag = authsuid();
  // no services allowed
  if (!testsuid($suid, $flag)) {
    die('ERROR: ACCESS DENIED');
  }
  // include suid specific module if exists
  $i = mod_path($suid, 'module.php');
  if (file_exists($i)) { require($i); }
  // not listing
  if ($suid) {
    // image requested
    if (val_get('img') !== null) {
      // save settings
      cfg_save($suid);
      plot_draw($suid);
      die('ERROR: IMAGE GENERATION FAILED');
    }
    // messages requested
    if (val_get('msg') !== null) {
      header('Content-Type: text/html; charset=windows-1251');
      die(plot_text($suid));
    }
    // check if something required here (like 'out')
    safecall('out_data');
    // get data for page
    extract(page_info($suid));
  }
  // load settings
  if ($suid) {
    $i = array('hour' => 1, 'from' => $from, 'till' => $till);
    extract(cfg_load($suid, $i));
  }
  // set correct code page
  header('Content-Type: text/html; charset=windows-1251');
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN">
<html>
<head>
<meta http-equiv="imagetoolbar" content="no">
<meta http-equiv="Content-Type" content="text/html; charset=windows-1251">
<title>
<?php echo html_escape(val_key($suid_list, $suid, 'Список доступных сервисов')); ?>

<?php echo html_escape($owen_hdr); ?>
</title>

<link type="text/css" rel="stylesheet" href="owenmisc/jquery-ui.css">
<script type="text/javascript" src="owenmisc/jquery-1.8.2.min.js"></script>
<script type="text/javascript" src="owenmisc/jquery-ui.min.js"></script>
<link rel="stylesheet" href="owenmisc/timepick/jquery-ui-timepicker-addon.css">
<script type="text/javascript" src="owenmisc/timepick/jquery-ui-sliderAccess.js"></script>
<script type="text/javascript" src="owenmisc/timepick/jquery-ui-timepicker-addon.min.js"></script>

<style type="text/css">
<!--

h1 {
  text-align: center;
  font-size: 18pt;
  margin: 0;
}

a, a:link, a:visited {
  color: #0000ff;
}

a:active, a:hover {
  color: #ff0000;
}

body {
  overflow: auto;
}

table {
  border: 0;
  border-collapse: collapse;
  margin: auto;
}

td, th {
  border: 0;
  padding: 0 20pt 0 20pt;
  white-space: nowrap;
}

th {
  font-weight: normal;
}

.t span, label {
  position: relative;
  top: -3pt;
  padding-left: 3pt;
}

.s, .s tr, .s th, .s td {
  padding: 0;
}

input[type="radio"],
input[type="checkbox"],
input[type="button"],
label {
  cursor: pointer;
}

.ui-datepicker * {
  font-size: 10pt;
}

#view {
  margin-top: 10pt;
}

#text {
  font-weight: bold;
  color: #ff0000;
  text-align: center;
  white-space: pre;
}

#mh, #mv {
  border: 0;
  margin: 0;
  padding: 0;
  position: absolute;
}

#mh {
  cursor: crosshair;
  border-top: 1px solid #808080;
}

#mv {
  cursor: crosshair;
  border-left: 1px solid #808080;
}

#rdiv {
  position: absolute;
  top: 0;
  right: 0;
  text-align: right;
}

#ldiv {
  position: absolute;
  top: 0;
  left: 0;
  text-align: left;
}

.grayed {
  color: #808080;
  cursor: default;
}

-->
</style>
</head>
<body>
<?php if ($suid) { ?>
<div id="ldiv"><select name="cross"><option value="0">нет прицела</option><option value="1">при движении</option><option value="2">по щелчку</option></select></div>
<div id="rdiv"><input type="checkbox" name="auto" id="auto0"><label for="auto0">Автообновление данных</label></div>
<h1>
<?php echo html_escape(val_key($suid_list, $suid, '')); ?>

<?php echo html_escape($owen_hdr); ?></h1>
<table class="t">
<tr>
  <td>
    <fieldset>
    <legend>Данные</legend>
<?php echo safecall('htm_data'); ?>
    </fieldset>
  </td>
  <td>
    <input type="radio" name="type" value="0" id="type0"><label for="type0">Данные за сутки</label> <span>|</span>
    <input type="radio" name="type" value="1" id="type1"><label for="type1">Данные за двое суток</label> <span>|</span>
    <input type="radio" name="type" value="2" id="type2"><label for="type2">Данные за трое суток</label> <span>|</span>
    <input type="radio" name="type" value="3" id="type3"><label for="type3">Данные за семь дней</label>
    <br><br>
    <input type="radio" name="type" value="4" id="type4"><label for="type4">Последние часы</label>
      <input type="text" name="hour" value="<?php echo $hour; ?>" size="3" maxlength="3">
      <input type="button" name="showhour" value="Показать">
      <br><br>
    <input type="radio" name="type" value="5" id="type5"><label for="type5">Произвольный период</label>
      <input type="text" name="fromdate" value="<?php echo $from; ?>" size="16" maxlength="16" readonly>
      <input type="text" name="tilldate" value="<?php echo $till; ?>" size="16" maxlength="16" readonly>
      <input type="button" name="showdate" value="Показать">
<?php if (defined('DEBUGGER')) { ?>
      <input type="button" name="_lt_date" value="&lt;">
      <input type="button" name="_rt_date" value="&gt;">
<?php } ?>
<?php echo safecall('htm_more', array('from' => $from, 'till' => $till)); ?>
  </td>
</tr>
<?php /* TODO: additional data here */ ?>
</table>

<div id="text"></div>

<table class="s"><tr><td><img id="view" src="<?php echo html_escape($_SERVER['REQUEST_URI'].'&img='.time()); ?>" alt=""></td></tr></table>

<div id="mh"></div><div id="mv"></div>
<script type="text/javascript">
intervalTimerId = null;
var plot_all = [0, 0, 0, 0, 0, 0];
var plot_axs = [];

function item_state(item, flag) {
  if (flag) {
    $('#' + item).prop('disabled', false).css('cursor', 'pointer');
    $('label[for="' + item + '"]').removeClass('grayed');
  } else {
    $('#' + item).prop('disabled', true).css('cursor', 'default');
    $('label[for="' + item + '"]').addClass('grayed');
  }
}

function mask_build(name) {
  var v = 0;
  var l = name.length;
  $('input[name^="' + name + '"]:checked').each(function(){
    var i = $(this).attr('name');
    i = i.substr(l, i.length - l);
    i = Math.abs(parseInt(i, 10) - 1);
    v |= Math.pow(2, i);
  });
  return(v);
}

function draw_cross(t, x, y) {
  if ($('select[name="cross"]:first').val() == t.toString()) {
    $('#mh').offset({left: $('#view').offset().left, top: y}).width($('#view').width());
    $('#mv').offset({left: x, top: $('#view').offset().top}).height($('#view').height());
    var sx = $('#view').offset().left;
    var sy = $('#view').offset().top;
    var xx = x - sx - plot_all[0];
    var yy = y - sy - plot_all[1];
    if ((xx >= 0) && (yy >= 0) && (xx < plot_all[2]) && (yy < plot_all[3])) {
      $('span[id^="ax"]').each(function(){
        var i = parseInt($(this).attr('id').substring(2), 10);
        if (i < plot_axs.length) {
          /* plot_all: a, b, x, y, font_size, count */
          /* plot_axs: min, max, x, y, color */
          if (i == 0) {
            $(this).offset({left: sx + plot_axs[i][2] + xx, top: sy + plot_axs[i][3]});
            i = plot_axs[i][0] +
              (((plot_axs[i][1] - plot_axs[i][0]) * xx) / plot_all[2]);
            i = parseInt(i.toString(), 10);
            i = new Date(i * 1000);
            i = [i.getHours(), i.getMinutes()];
            i[0] = ((i[0] < 10) ? '0' : '') + i[0].toString();
            i[1] = ((i[1] < 10) ? '0' : '') + i[1].toString();
            i = i[0] + ':' + i[1];
          } else {
            $(this).offset({left: sx + plot_axs[i][2], top: sy + plot_axs[i][3] + yy});
            xx = plot_axs[i][1] - plot_axs[i][0];
            i = plot_axs[i][0] +
              ((xx * (plot_all[3] - yy)) / plot_all[3]);
            if ((plot_all[5] == 1) && (xx <= 20)) {
              i = i.toFixed((xx <= 10) ? 2 : 1);
              i = i.toString();
              i = i.replace('.', ',');
            } else {
              i = parseInt(i.toString(), 10);
              i = i.toString();
            }
          }
          $(this).text(i).offset({
            left: $(this).offset().left - parseInt($(this).width() / 2, 10),
             top: $(this).offset().top - parseInt($(this).height() / 2, 10)
          });
        }
      });
    }
  }
}

function text_update() {
  var img = $('#view');
  $.get(img.attr('src').replace(/img=[0-9]+/i, 'msg=' + $.now())).done(function(data) {
    $('#text').text(data);
  });
}

function auto_body() {
  var img = $('#view');
  img.attr('src', img.attr('src').replace(/img=[0-9]+/i, 'img=' + $.now()));
  text_update();
}

function auto_stop() {
  if (intervalTimerId !== null) {
    clearInterval(intervalTimerId);
    intervalTimerId = null;
  }
}

function auto_init() {
  auto_stop(intervalTimerId);
  intervalTimerId = setInterval(auto_body, 60000);
}

function cook_data(cname, cooktext, cmax) {
var name = cname + '=';
var ca = cooktext.split(';');
var rt = [];
  for (var i = 0; i < ca.length; i++) {
    var c = ca[i];
    while (c.charAt(0) == ' ') {
      c = c.substring(1);
    }
    if (c.indexOf(name) == 0) {
      c = c.substring(name.length, c.length);
      rt = c.split('_');
      break;
    }
  }
  while (rt.length < cmax) { rt.push('0'); }
  for (var i = 0; i < (cmax - 1); i++) {
    rt[i] = ((i < 2) && (cmax == 5)) ? parseFloat(rt[i]) : parseInt(rt[i]);
  }
  return(rt);
}

$(document).ready(function() {
  $('#view').on('load', function(){
    var cs = decodeURIComponent(document.cookie);
    /* plot_all: a, b, x, y, font_size, count */
    plot_all = cook_data('plot_all', cs, 6);
    plot_all[4] += 3;
    var bd = $('body:first');
    var vs = $('select[name="cross"]:first').val();
    vs = parseInt(vs, 10);
    plot_axs = [];
    for (var i = 0; i <= plot_all[5]; i++) {
      /* plot_axs: min, max, x, y, color */
      plot_axs.push(cook_data('plot_ax' + i.toString(), cs, 5));
      var item = document.getElementById('ax' + i.toString());
      if (!item) {
        item = document.createElement('span');
        item.style.display = vs ? '' : 'none';
        item.id = 'ax' + i.toString();
        item.style.position = 'absolute';
        item.style.color = '#ffffff';
        item.style.fontFamily = 'Arial';
        item.style.padding = '0 1px';
        bd.append(item);
      }
      item.style.backgroundColor = '#' + plot_axs[i][4];
      item.style.fontSize = plot_all[4] + 'px';
    }
    $('span[id^="ax"]').each(function(){
      var n = parseInt($(this).attr('id').substring(2), 10);
      if (n > plot_all[5]) { $(this).remove(); }
    });
    if (vs) {
      draw_cross(vs, $('#mv').offset().left, $('#mh').offset().top);
    }
  });
  $('input[name$="date"][type="text"]').datetimepicker({
    timeText: 'Время',
    hourText: 'Часы',
    minuteText: 'Минуты',
    secondText: 'Секунды',
    currentText: 'Сейчас',
    closeText: 'Закрыть',
    monthNames: ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'],
    dayNamesMin: ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'],
    /*showOn: 'button',
    buttonText: '*',*/
    dateFormat: 'yy-mm-dd',
    timeFormat: 'HH:mm',
    showSecond: false,
    showMillisec: false,
    showTimezone: false,
    timeInput: true,
    firstDay: 1,
    minDate: '<?php echo $tmin; ?>',
    maxDate: '<?php echo $tmax; ?>'
  });
  $('input[type="button"][name^="show"]').click(function(){
    auto_stop();
    var x = $('input[name="type"]:checked').val();
    var l = {
      data: <?php echo safecall('jsc_data', '$(\'input[name="data"]:checked\').val()'); ?>,
      type: x,
<?php if ($suid == 999) { /* TODO: send item value (if exists) */ ?>
      item: <?php echo safecall('jsc_item', '$(\'input[name="item"]:checked\').val()'); ?>,
<?php } ?>
      hour: (x == '4') ? $('input[name="hour"]:first').val() : null,
      from: (x == '5') ? $('input[name="fromdate"]:first').val() : null,
      till: (x == '5') ? $('input[name="tilldate"]:first').val() : null,
      img: $.now()
    };
    x = '';
    for (var k in l) {
      if (l[k] !== null) {
        x += String.fromCharCode(38) + k + '=' + l[k];
      }
    }
    $('#view').attr('src', window.location.href.replace(/#.*$/, '') + x);
    text_update();
    if ($('input[name="auto"]:first').attr('checked')) { auto_init(); }
  });
  $('input[type="radio"]').click(function(){
    $('input[id^="item"],input[id^="data"]').each(function(){ item_state($(this).attr('id'), 1); });
    var list = [
<?php /* jsc_stat TODO: item states here - which enabled or disabled at start */ ?>
    ];
    for (var i = 0; i < list.length; i++) {
      var item = $('#item' + list[i][0].toString());
      for (var j = 1; j < list[i].length; j++) {
        var data = 'data' + list[i][j].toString();
        if (item.attr('checked')) {
          item_state(data, 0);
        } else {
          if ($('#' + data).attr('checked')) {
            item_state(item.attr('id'), 0);
            break;
          }
        }
      }
    }
    if (($(this).attr('name') != 'type') || (parseInt($(this).val(), 10) < 4)) {
      $('input[type="button"]:first').click();
    }
  });
  $('input[name="type"]').click(function(){
    $('input[name="hour"],input[name$="date"],input[type="button"][name^="show"]').hide();
    if ($(this).val() == '4') {
      $('input[name$="hour"]').show();
      $('input[name="hour"]').focus().select();
    }
    if (($(this).val() == '5')) {
      $('input[name$="date"]').show();
    }
  });
<?php echo safecall('jsc_init', array('tmin' => $tmin, 'tmax' => $tmax, 'data' => $data, 'item' => $item)); ?>
<?php if (is_null($type)) { ?>
  $('input[name="type"]:first').attr('checked', 'checked').click();
<?php } else { ?>
  $('input[name="type"][value="<?php echo $type; ?>"]:first').attr('checked', 'checked').click();
<?php if ($type > 3) { ?>
  $('input[type="button"][name^="show"]').click();
<?php } ?>
<?php } ?>
  var auto = $('input[name="auto"]:first');
  auto.attr('checked', 'checked').click(function(){
    auto_stop();
    if ($(this).attr('checked')) {
      auto_body();
      auto_init();
    }
  });
  $('input[name="hour"]:first').keypress(function(e) {
    if (e.which == 13) {
      $(this).select();
      $('input[name="showhour"]:first').click();
    }
  });
  $('#view, #mh, #mv').mousemove(function(e){
    draw_cross(1, e.pageX, e.pageY);
  });
  $('#view, #mh, #mv').click(function(e) {
    draw_cross(2, e.pageX, e.pageY);
  });
  $('select[name="cross"]:first').change(function(){
    if ($(this).val() == 0) {
      $('#mh, #mv').hide();
      $('#view').css('cursor', 'default');
      $('span[id^="ax"]').hide();
    } else {
      $('#mh, #mv').show();
      $('#view').css('cursor', 'crosshair');
      $('span[id^="ax"]').show();
    }
    $(this).blur();
  });
  $('#mh, #mv').offset({left:0,top:0}).width(1).height(1).hide();
  $('select[name="cross"]:first option[value="0"]').attr('selected', 'selected');
  $(document).keydown(function(e) {
    if (e.which === 32) {
      e.preventDefault();
      $(':focus').blur();
      var x = $('select[name="cross"]:first');
      x.val((parseInt(x.val(), 10) + 1) % 2);
      x.change();
    }
  });
  auto_init();
<?php if (defined('DEBUGGER')) { ?>
  // 2022-02-12 00:00
  // https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Date/UTC
  val_move = 0;
  $('input[name^="_"]').click(function(){
    var p = 7 * 24 * 60 * (60 * 1000);
    if ($(this).attr('name').substr(1,1) == 'l') {
      val_move -= (val_move > 0) ? p : 0;
    } else {
      val_move += p;
    }
    var datemove = new Date(Date.UTC(2022, 1, 11, 17, 0, 0));
    $('input[name="fromdate"]:first').val(
      datemove.getFullYear() + '-' +
      (datemove.getMonth() + 1) + '-' +
      datemove.getDate() + ' ' +
      datemove.getHours() + ':' +
      datemove.getMinutes()
    );
    datemove.setTime(datemove.getTime() + val_move);
    $('input[name="tilldate"]:first').val(
      datemove.getFullYear() + '-' +
      (datemove.getMonth() + 1) + '-' +
      datemove.getDate() + ' ' +
      datemove.getHours() + ':' +
      datemove.getMinutes()
    );
    $('input[name="showdate"]:first').click();
  }).hide();
<?php } ?>
});
</script>

<?php } else { ?>

<h1>Список доступных сервисов</h1>

<table class="t">
<?php
  $l = html_escape(uri_self(null).'?suid=');
  foreach ($suid_list as $k => $v) {
    if ($flag & (1 << ($k - 1))) {
      echo '<tr><td><a href="'.$l.$k.'">'.html_escape($v).'</a></td></tr>'.PHP_EOL;
    }
  }
?>
</table>
<?php } ?>

</body>
</html>
