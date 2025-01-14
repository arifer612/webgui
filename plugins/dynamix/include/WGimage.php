<?PHP
/* Copyright 2005-2021, Lime Technology
 * Copyright 2012-2021, Bergware International.
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

$file = realpath('/etc/wireguard/peers/'.unscript($_GET['file']??''));
$lastmod = filemtime($file);
$csrf = exec("grep -Pom1 '^csrf_token=\"\K.[^\"]+' /var/local/emhttp/var.ini");
if (!$file || strpos($file,'/boot/config/wireguard')!==0 || empty($_GET['csrf_token']) || $_GET['csrf_token']!=$csrf) return;

if (strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']??'') >= $lastmod) {
  header($_SERVER["SERVER_PROTOCOL"].' 304 Not Modified');
} else {
  header('Last-Modified:'.gmdate('D, d M Y H:i:s', $lastmod).' GMT');
  header('Content-type:image/png');
  readfile($file);
}
?>