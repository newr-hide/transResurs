<?php

// return time in seconds
function sec_time($v, $t) {
  // seconds in seconds, minuites, hours, days, weeks
  $l = array('s' => 1, 'm' => 60, 'h' => 3600, 'd' => 86400, 'w' => 604800);
  // must be lowercased character
  $t = strtolower(substr(strval($t), 0, 1));
  // apply required value
  $v *= array_key_exists($t, $l) ? $l[$t] : 1;
  return($v);
}

// return axis scale values: min, distance, max
function int_axis($min, $max) {
  // fix min / max if specified wrong
  if ($min > $max) {
    list($min, $max) = array($max, $min);
  }
  // interval value
  $v = abs($max - $min);
  // avoid divide by zero for single value
  if (!$v) { $v = 1; }
  // min, interval, max
  return(array($min, $v, $max));
}

// return aligned value to near boundary
function int_near($v, $b) {
  // can't divide by zero
  if ((!empty($v)) && (!empty($b))) {
    // step with sign: -1 or 1
    $i = $b / abs($b);
    // round to near integer if fractional part exists
    $v = intval($v) +
      // same sign as direction
      (($i == ($v / abs($v))) ?
        // fractional part exists
        (($v - intval($v)) ? $i : 0) : 0);
    // align to the near boundary
    while ($v % $b) { $v += $i; }
  }
  return($v);
}

// return max possible step for value
function int_step($v, $b, $s, $p = 1) {
  $i = $p;
  $v = abs($v);
  $b = abs($b);
  $s = abs($s);
  // can't be empty
  if ($b && $s && ($v > $s) && ($v > $b)) {
    $i = $s;
    while ((($v / $i) / $b) > 1) {
      $i += $s;
    }
  }
  return($i);
}

// return updated second dot clipped with axis
function dot_clip($p, $q, $b) {
  $y = abs($q[1] - $p[1]);
  if ($y) {
    $x = $q[0] - $p[0];
    $z = abs($q[1] - $b);
    $q = array($q[0] - (($z * $x) / $y), $b);
  }
  return($q);
}

// update visible coords for plot
function dot_plot($p, $q, $min, $max) {
  // Y axis min/max
  $min = is_null($min) ? min($p[1], $q[1]) : $min;
  $max = is_null($max) ? max($p[1], $q[1]) : $max;
  // both below or above
  if (
    (($p[1] < $min) && ($q[1] < $min)) ||
    (($p[1] > $max) && ($q[1] > $max))
  ) { return(array()); }
  // old result
  $a = $p;
  $b = $q;
  // below
  if ($p[1] < $min) { $a = dot_clip($q, $p, $min); }
  if ($q[1] < $min) { $b = dot_clip($p, $q, $min); }
  // above
  if ($p[1] > $max) { $a = dot_clip($q, $p, $max); }
  if ($q[1] > $max) { $b = dot_clip($p, $q, $max); }
  // result
  return(array($a, $b));
}

// get default plot values or update/recalculate existing
function img_view($list = null) {
  // default values
  $view = array(
    // whole image width
    'w' => 1270,
    // whole image height
    'h' => 760,
    // graphic width
    'x' => 1080,
    // graphic height
    'y' => 660,
    // precise mode (float variables)
    'p' => null,
    // padding from top left corner
    'xp' => 40,
    'yp' => 40,
    // y scale min/max/step
    'sn' => null,
    'sx' => null,
    'st' => 5,
    // step for interpolation (in seconds)
    'si' => 60,
    // logo font size
    'fl' => 120,
    // logo text
    'tl' => '',
    // axes color
    'ca' => 0x323232,
    // logo and outer border color
    'cl' => 0xEBEBEB,
    // default black text color
    'ct' => 0,
    // zero, for negative values
    'cz' => 0x000087,
    // h_dark_blue (digits_left)
    'cv' => 0x7878FF,
    // h_light_blue
    'cw' => 0xC0C0FF,
    // v_dark_grey
    'ch' => 0x808080,
    // v_light_grey
    'ci' => 0xEEEEEE,
    // day separator color
    'cd' => 0x323232,
    // info legend font size
    'fn' => 10,
    // scales font size
    'fs' => 10,
    // axes info size
    'fi' => 8,
    // vertical ticks (660 / 22) = 30 ticks
    'vt' => 30,
    // horizontal ticks (1080 / 45) = 24 ticks
    'ht' => 60,
    // multi-axes plot index
    'mi' => 0,
    // multi-axes plot total
    'mt' => 1,
    // multi-axes plot legend count
    'ml' => array(),
    // additional cookies information (null - disabled)
    'ac' => 1
  );
  // array specified
  if (is_array($list)) {
    // for scale
    $w = $view['w'];
    $h = $view['h'];
    $s = (($list['w'] != $w) || ($list['h'] != $h));
    // update view
    foreach ($view as $k => $v) {
      $v = val_key($list, $k, $v);
      // scale all values if needed
      if ($s) {
        switch (substr($k, 0, 1)) {
          // x, y or font - scale
          case 'x':
            $v = ($v * $list['w']) / $w;
            break;
          case 'f': // font size = font height
          case 'y':
            $v = ($v * $list['h']) / $h;
            break;
        }
      }
      $view[$k] = $v;
    }
  }
  return($view);
}

// make image graph
function img_make($view, $list, $info) {
  // get image view default settings and update with existing
  $view = img_view($view);
  // create image
  $image = img_init($view['w'], $view['h'], ($view['mi'] > 1) ? 0 : 0xFFFFFA);
  // image created
  if (!is_null($image)) {
    // quadro mode
    if ($view['mi'] > 1) {
      $v = img_cint($image, 0xFFFFFA);
      $v = imagecolortransparent($image, $v);
      imagefilledrectangle($image, 0, 0, $view['w'] - 1, $view['h'] - 1, $v);
    }
    // create colors
    foreach ($view as $k => $v) {
      // color - create
      if (substr($k, 0, 1) == 'c') {
        $view[$k] = img_cint($image, $v);
      }
    }
    // create some values to reduce and cleanup code
    $w = $view['w'];
    $h = $view['h'];
    $x = $view['x'];
    $y = $view['y'];
    // internal rectangle (a,b), (c,d)
    $a = $view['xp'];
    $b = $view['yp'];
    $c = $a + $x;
    $d = $b + $y;
    // not quadro mode or first graph
    if ($view['mi'] < 2) {
      // outer border
      imagerectangle($image, 0, 0, $w - 1, $h - 1, $view['cl']);
      // logo text
      img_text($image, array($c / 2, 1), array($d / 2, 1), $view['fl'], $view['cl'], $view['tl']);
    }
    // for debug only, do not touch
    if (defined('DEBUGGER')) {
      img_text($image, 0, 0, $view['fs'], $view['ct'], date('H:i:s'));
    }
    // if there is no data to display
    if (empty($list)) {
      // draw graphic rectangle
      imagerectangle($image, $a, $b, $c, $d, $view['ct']);
      // output error text
      $v = $y / ($view['fn'] * 2);
      $k = 'ÇÍÀ×ÅÍÈß ÈÇ ÓÊÀÇÀÍÍÎÃÎ ÏÅÐÈÎÄÀ ÎÒÑÓÒÑÒÂÓÞÒ Â ÁÀÇÅ ÄÀÍÍÛÕ';
      $l = array($a + ($x / 2), 1);
      for ($i = 0; ($i + $v) < $y; $i += $v) {
        img_text($image, $l, $b + $i, $view['fn'], $view['ct'], $k);
      }
    } else {
      // horizontal row info - first element
      reset($info);
      $type = key($info);
      // remove from info
      $type = array_merge(array_shift($info), array($type));
      // create user colors and prev x/y coord for plot
      $data = array();
      foreach ($info as $k => $v) {
        // create colors for this axis
        $info[$k][0] = img_cint($image, $v[0]);
        // prev x/y coords
        $data[$k] = array(null, null);
      }
      // find min/max for all rows
      $ax = null;
      $ay = null;
      // find min and max values for each row
      for ($i = 0; $i < count($list); $i++) {
        foreach ($list[$i] as $k => $v) {
          // filter out horizontal row
          if (array_key_exists($k, $data)) {
            // filter out non-existent null values
            if (!is_null($v)) {
              $ax = is_null($ax) ? $v : min($ax, $v);
              $ay = is_null($ay) ? $v : max($ay, $v);
            }
          }
        }
      }
      // for debug only, do not touch
      if (defined('DEBUGGER')) {
        img_text($image, 0, array($h, 2), $view['fs'], $view['ct'], sprintf('%.2f / %.2f', $ax, $ay));
      }
      // align scale coeffs
      $ay = int_axis(
        // overwrite axes
        is_null($view['sn']) ? int_near($ax, -5) : $view['sn'],
        is_null($view['sx']) ? int_near($ay, 1) : $view['sx']
      );
      // horizontal row
      $ax = int_axis(
        $type[0],
        $type[1]
      );
      // check for precise mode
      if (is_null($view['p'])) {
        if ($ay[1] <= 10) {
          $view['p'] = 0.5;
          if ($ay[1] <= 2  ) { $view['p'] = 0.1; }
          if ($ay[1] <= 0.5) { $view['p'] = 0.05; }
        }
      }
      // for debug only, do not touch
      if (defined('DEBUGGER')) {
        img_text($image, false, array($h, 2), $view['fs'], $view['ct'], $ay[0].' / '.$ay[2]);
      }
      // -----------------------------------------------------------------------
      // find horizontal ticks step
      // time intervals
      $l = array(
        array(sec_time(256, 'd') - 1, sec_time( 4, 'w')),
        array(sec_time(128, 'd') - 1, sec_time( 2, 'w')),
        array(sec_time( 90, 'd') - 1, sec_time( 1, 'w')),
        array(sec_time( 31, 'd')    , sec_time( 2, 'd')),
        array(sec_time(  2, 'w')    , sec_time( 1, 'd')),
        array(sec_time( 10, 'd')    , sec_time(12, 'h')),
        array(sec_time(  1, 'w')    , sec_time( 6, 'h')),
        array(sec_time(  3, 'd')    , sec_time( 3, 'h')),
        array(sec_time(  2, 'd')    , sec_time( 2, 'h')),
        array(sec_time(  1, 'd')    , sec_time( 1, 'h')),
        array(sec_time(  6, 'h')    , sec_time(30, 'm')),
        // new intervals
        array(sec_time(  1, 'h') - 1, sec_time(15, 'm')),
        array(sec_time( 10, 'm')    , sec_time( 1, 'm'))
      );
      // default interval
      $v = 60;
      for ($i = 0; $i < count($l); $i++) {
        // got inside required interval
        if ($ax[1] > $l[$i][0]) {
          $v = $l[$i][1];
          break;
        }
      }
      // draw horizontal ticks only in normal mode or first plot
      if ($view['mi'] < 2) {
//        // thin image line
//        imagesetthickness($image, 1);
        /*// draw intermediate ticks only if interval a month or less
        if ($ax[1] <= sec_time(31, 'd')) {
          // avoid divide by zero
          $l = intval($ax[1] / $v);
          $l = $l ? intval($view['ht'] / $l) : 0;
          // intermediate ticks possible to draw
          if ($l > 1) {
            // scale step
            $l = ($v / $l);
            for ($i = $ax[0]; $i <= $ax[2]; $i += $l) {
              $k = $a + ((($i - $ax[0]) * $x) / $ax[1]);
              img_line($image, $k, $b, $k, $d, $view['ci']);
            }
          }
        }*/
        $u = 0;
        for ($i = $ax[0]; $i <= $ax[2]; $i += $v) {
          $k = $a + ((($i - $ax[0]) * $x) / $ax[1]);
          img_line($image, $k, $b, $k, $d, $view['ci']);
          img_text($image,
            array($k, 1), $u ? array($b - ($b / 2), 1) : array($d + ($b / 2), 1),
            $view['fs'], $view['ch'], date('H:i', $i)
          );
          //if (($ax[1] / $v) > 24) {}
          $u ^= 1;
        }
        // draw 24 and 6 hour ticks
        $l = sec_time(6, 'h');
        // show 6 hours only if less than 1 month
        $v = ($ax[1] <= sec_time(31, 'd')) ? 1 : 0;
        // prev coord, up/down pos
        $u = array(0, 1);
        for ($i = strtotime(date('Y-m-d 00:00:00', $ax[0])); $i <= $ax[2]; $i += $l) {
          // must be inside plot interval
          if ($i >= $ax[0]) {
            $k = $a + ((($i - $ax[0]) * $x) / $ax[1]);
            // 6 hours
            if ($v) {
              imagesetthickness($image, 1);
              img_line($image, $k, $b, $k, $d, $view['ch']);
            }
            // days
            if (!intval(date('H', $i))) {
              imagesetthickness($image, 2);
              img_line($image, $k, $b, $k, $d, $view['cd']);
              $k = intval($k - $a);
              if ($u[0] != $k) {
                img_text($image,
                  array($a + (($u[0] + $k) / 2), 1), $u[1] ? 0 : array($d + $b, 2),
                  $view['fs'], $view['ch'], date('d-m-Y', $i - sec_time(1, 'd'))
                );
              }
              // save prev coord
              $u[0] = $k;
              // toggle up/down pos
              if ($ax[1] > sec_time(2, 'w')) { $u[1] ^= 1; }
            }
          }
        }
        // add last day (if any)
        if (intval(date('H', $ax[2])) | intval(date('i', $ax[2]))) {
          img_text($image,
            array($a + (($u[0] + $x) / 2), 1), $u[1] ? 0 : array($d + $b, 2),
            $view['fs'], $view['ch'], date('d-m-Y', $ax[2])
          );
        }
      }
      // -----------------------------------------------------------------------
      // revert back lines
      imagesetthickness($image, 1);
      // align max value to ticks
      $v = int_step($ay[1], $view['vt'], $view['st']);
      $ay = int_axis(
        $ay[0],
        is_null($view['sx']) ? ($ay[0] + int_near($v * abs($ay[1] / $v), $v)) : $view['sx']
      );
      // find vertical ticks step
      $v = int_step($ay[1], $view['vt'], $view['st'], is_null($view['p']) ? 1 : $view['p']);
      // not in quadro mode
      if ($view['mt'] < 2) {
        // draw intermediate vertical ticks if possible
        $u = round($ay[1] / $v);
        $l = $u ? intval($view['vt'] / $u) : 0;
        if ($l > 1) {
          // steps: 2 (half), 5 or 10
          $l = ($l < 5) ? 2 : (intval($l / 5) * 5);
          // can't be more than 10
          $l = min($l, 10);
          // tick interval
          $l = $ay[1] / ($u * $l);
          for ($i = $ay[0]; $i <= $ay[2]; $i += $l) {
            $k = $d - round((($i - $ay[0]) * $y) / $ay[1]);
            img_line($image, $a, $k, $c, $k, $view['cw']);
          }
        }
      }
      // draw vertical ticks
      if ($view['mt'] > 1) {
        $k = 2 + (intval(($view['mt'] - 1) / 2) * 2);
        $k = intval($a / $k);
        $u = array();
        for ($i = 0; $i < $view['mt']; $i++) {
          $u[] = (($i & 1) * $c) + $k + ($k * ($i >> 1) * 2);
        }
      } else {
        $u = array(
          array($a / 2, 1),
          array($c + ($a / 2), 1)
        );
      }
      // cookies
      if (!is_null($view['ac'])) {
        // get axes position
        $view['ac'] = ($view['mt'] > 1) ? $u[$view['mi'] - 1] : $u[0][0];
      }
      for ($i = $ay[0]; round($i, 2) <= $ay[2]; $i += $v) {
        $k = $d - round((($i - $ay[0]) * $y) / $ay[1]);
        $p = is_null($view['p']) ? intval($i) : str_replace('.', ',', strval(round($i / $view['p']) * $view['p']));
        // quadro mode
        if ($view['mt'] > 1) {
          $l = img_text($image, array($u[$view['mi'] - 1], 1), array($k, 1), $view['fs'], current(reset($info)), $p);
          // ticks for exact values
          if ($view['mi'] & 1) {
            $l[0] = $u[$view['mi'] - 1] + $u[0] - 3;
            $l[1] = $l[0] + 2;
          } else {
            $l[0] = $u[$view['mi'] - 1] - $u[0];
            $l[1] = $l[0] + 2;
          }
          img_line($image, $l[0], $k, $l[1], $k, current(reset($info)));
        } else {
          // zero for negative values
          $l = ((!$i) && ($ay[0] < 0)) ? $view['cz'] : $view['cv'];
          img_line($image, $a, $k, $c, $k, $l);
          img_text($image, $u[0], array($k, 1), $view['fs'], $view['cv'], $p);
          img_text($image, $u[1], array($k, 1), $view['fs'], $view['cv'], $p);
        }
      }
      // info for this axis
      $v = reset($info);
      if (array_key_exists(2, $v)) {
        if ($view['mt'] > 1) {
          img_text($image, array($u[$view['mi'] - 1], 1), array($b / 2, 0), $view['fi'], $v[0], $v[2], 45);
        } else {
          img_text($image, $u[0], array($b / 2, 0), $view['fi'], $view['cv'], $v[2], 45);
          img_text($image, $u[1], array($b / 2, 0), $view['fi'], $view['cv'], $v[2], 45);
        }
      }
      // -----------------------------------------------------------------------
      // plot line thickness
      imagesetthickness($image, 2);
      // add axes margin to image rectangle
      /*$a += 2; $b += 2;
      $c -= 2; $d -= 2;
      $x -= 4; $y -= 4;*/
      $a++; $b++;
      $x--; $y--;
      // additional legend to show
      $more = array();
      // draw plot
      for ($i = 0; $i < count($list); $i++) {
        $l = $list[$i];
        foreach ($data as $k => $v) {
          // key exists
          if (array_key_exists($k, $l)) {
            // add legend field
            $more[$k] = 1;
            // ignore nulls
            if (!is_null($l[$k])) {
              // add legend about zero values
              if (!$l[$k]) { $more['zero'] = 1; }
              // values
              $u = array(
                $l[$type[2]], // horizontal axis
                $l[$k] // current vertical row value
              );
              // firsy x (when no data at left side)
              if (is_null($v[0])) { $v[0] = $ax[0]; }
              // first y
              if (is_null($v[1])) { $v[1] = $u[1]; }
              // cap points
              $p = dot_plot($v, $u, $view['sn'], $view['sx']);
              // more than minute interval - mark missing data
              if (($u[0] - $v[0]) > $view['si']) {
                // draw interpolation
                if (!empty($p)) {
                  img_line($image,
                    //$a + ((($v[0] - $ax[0]) * $x) / $ax[1]), $d - ((($v[1] - $ay[0]) * $y) / $ay[1]),
                    //$a + ((($u[0] - $ax[0]) * $x) / $ax[1]), $d - ((($u[1] - $ay[0]) * $y) / $ay[1]),
                    $a + ((($p[0][0] - $ax[0]) * $x) / $ax[1]), $d - ((($p[0][1] - $ay[0]) * $y) / $ay[1]),
                    $a + ((($p[1][0] - $ax[0]) * $x) / $ax[1]), $d - ((($p[1][1] - $ay[0]) * $y) / $ay[1]),
                    $info['null'][0]
                  );
                }
                // move prev point to current
                $v = $u;
                // add legend about missing values
                $more['null'] = 1; // interpolation
              }
              // cap points
              $p = dot_plot($v, $u, $view['sn'], $view['sx']);
              // draw line
              if (!empty($p)) {
                img_line($image,
                  //$a + ((($v[0] - $ax[0]) * $x) / $ax[1]), $d - ((($v[1] - $ay[0]) * $y) / $ay[1]),
                  //$a + ((($u[0] - $ax[0]) * $x) / $ax[1]), $d - ((($u[1] - $ay[0]) * $y) / $ay[1]),
                  $a + ((($p[0][0] - $ax[0]) * $x) / $ax[1]), $d - ((($p[0][1] - $ay[0]) * $y) / $ay[1]),
                  $a + ((($p[1][0] - $ax[0]) * $x) / $ax[1]), $d - ((($p[1][1] - $ay[0]) * $y) / $ay[1]),
                  $info[$k][0]
                );
              }
              // update coords
              $data[$k] = $u;
            } else {
              // add legend about missing values
              $more['null'] = 1; // interpolation
            }
          }
        }
      }
      // return image rectangle coords back
      /*$a -= 2; $b -= 2;
      $c += 2; $d += 2;
      $x += 4; $y += 4;*/
      $a--; $b--;
      $x++; $y++;
      // additional cookies information
      if (!is_null($view['ac'])) {
        // main plot details
        $l = array($a, $b, $x, $y, $view['fs'], $view['mt']);
        $l = array_map('intval', $l); // fix
        setcookie('plot_all', implode('_', $l));
        // time axis
        $l = array($ax[0], $ax[2], $a, $d + ($b / 2), img_intc($image, $view['ch']));
        $l = array_map('intval', $l); // fix
        $l[4] = sprintf('%06X', $l[4]);
        setcookie('plot_ax0', implode('_', $l));
        // current axis
        $l = img_intc($image, ($view['mt'] > 1) ? current(reset($info)) : $view['cv']); // color
        $l = array($ay[0], $ay[2], $view['ac'], $b, $l);
        for ($i = 0; $i < count($l); $i++) {
          switch ($i) {
            // axis min/max - float
            case 0:
            case 1:
              $l[$i] = sprintf('%.1f', $l[$i]);
              break;
            // axis position - integer
            case 2:
            case 3:
              $l[$i] = intval($l[$i]);
              break;
            // axis color - hex
            case 4:
              $l[$i] = sprintf('%06X', intval($l[$i]));
              break;
          }
        }
        $i = ($view['mt'] > 1) ? $view['mi'] : 1;
        setcookie('plot_ax'.$i, implode('_', $l));
      }
      // legend coords
      if (empty($view['ml'])) {
        // FIXME: old code in this branch - remove?
        $i = $b * 2;
        // quadro mode padding
        if ($view['mt'] > 1) {
          $i += (($y - $b) / $view['mt']) * ($view['mi'] - 1);
          // fix to allow space for the legend
          if (($view['mt'] > 4) && ($view['mt'] & 1)) {
            $c -= $a / (2 + (intval(($view['mt'] - 1) / 2) * 2));
          }
        }
        $u = null;
      } else {
        // one item height: graph height / (total items in groups + null for each group)
        $u = $y / (array_sum($view['ml']) + count($view['ml']));
        // current start y offset
        $i = ($view['mt'] > 1) ? ($view['mi'] - 1) : 0;
        $i = $b + ($u * (array_sum(array_slice($view['ml'], 0, $i)) + $i));
      }
      //$u = array($c + $a + (($w - $c - $a) / 2), 1);
      $l = array(0, $view['y'] - $i);
      // draw legend
      foreach ($info as $k => $v) {
        // if row present in plot data
        if (array_key_exists($k, $more)) {
          // draw row line with specified color
          img_line($image, $c + $a, $i, $w, $i, $info[$k][0]);
          // draw row text
          $l = img_text($image, $c + $a, $i + ($view['fn'] / 2), $view['fn'], $view['ct'],
            $info[$k][1].(array_key_exists(2, $info[$k]) ? (PHP_EOL.$info[$k][2]) : ''),
            // fit into box - auto font size
            0, array($view['w'] - ($c + $a), (is_null($u) ? ($l[1] + $b) : $u) - $view['fn'])
          );
          // add text height with padding for next row
          $i += is_null($u) ? ($l[1] + $b) : $u;
        }
      }
    }
  }
  // return image object so the caller
  // can add something to it before output
  // or save as file instead displaying on the screen
  return($image);
}
