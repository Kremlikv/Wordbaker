<?php
header('Content-Type: text/plain; charset=utf-8');
$host = 'smtp-relay.brevo.com';
$ports = [587, 465];
foreach ($ports as $p) {
  $t0 = microtime(true);
  $fp = @fsockopen($host, $p, $errno, $errstr, 10);
  $dt = number_format((microtime(true)-$t0)*1000, 0);
  if ($fp) {
    echo "OK  : $host:$p  ($dt ms)\n";
    fclose($fp);
  } else {
    echo "FAIL: $host:$p  [$errno] $errstr  ($dt ms)\n";
  }
}
