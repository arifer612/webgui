<?PHP
/* Copyright 2005-2021, Lime Technology
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 */
?>
<?
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
require_once "$docroot/webGui/include/Secure.php";

function addLog($line) {echo "<script>addLog('$line');</script>";}

readfile("$docroot/logging.htm");
$var = parse_ini_file('state/var.ini');
$url = unscript($_GET['url']??'');

$parsed_url = parse_url($url);
if (isset($parsed_url['host']) && ($parsed_url['host']=="keys.lime-technology.com" || $parsed_url['host']=="lime-technology.com")) {
  addLog("Downloading $url ... ");
  $key_file = basename($url);
  exec("/usr/bin/wget -q -O ".escapeshellarg("/boot/config/$key_file")." ".escapeshellarg($url), $output, $return_var);
  if ($return_var === 0) {
    if ($var['mdState'] == "STARTED")
      addLog("<br>Installing ... Please Stop array to complete key installation.<br>");
    else
      addLog("<br>Installed ...<br>");
  }
  else {
    addLog("ERROR ($return_var)<br>");
  }
}
else
  addLog("ERROR, bad or missing key file URL: $url<br>");
?>
