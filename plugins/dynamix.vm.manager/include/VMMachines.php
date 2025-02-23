<?PHP
/* Copyright 2005-2021, Lime Technology
 * Copyright 2015-2021, Derek Macias, Eric Schultz, Jon Panozzo.
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
// add translations
$_SERVER['REQUEST_URI'] = 'vms';
require_once "$docroot/webGui/include/Translations.php";

require_once "$docroot/webGui/include/Helpers.php";
require_once "$docroot/plugins/dynamix.vm.manager/include/libvirt_helpers.php";


$user_prefs = '/boot/config/plugins/dynamix.vm.manager/userprefs.cfg';
$vms = $lv->get_domains();
if (empty($vms)) {
  echo '<tr><td colspan="8" style="text-align:center;padding-top:12px">'._('No Virtual Machines installed').'</td></tr>';
  return;
}
if (file_exists($user_prefs)) {
  $prefs = parse_ini_file($user_prefs); $sort = [];
  foreach ($vms as $vm) $sort[] = array_search($vm,$prefs) ?? 999;
  array_multisort($sort,SORT_NUMERIC,$vms);
} else {
  natcasesort($vms);
}
$i = 0;
$kvm = ['var kvm=[];'];
$show = explode(',',unscript($_GET['show']??''));

foreach ($vms as $vm) {
  $res = $lv->get_domain_by_name($vm);
  $desc = $lv->domain_get_description($res);
  $uuid = $lv->domain_get_uuid($res);
  $dom = $lv->domain_get_info($res);
  $id = $lv->domain_get_id($res) ?: '-';
  $is_autostart = $lv->domain_get_autostart($res);
  $state = $lv->domain_state_translate($dom['state']);
  $icon = $lv->domain_get_icon_url($res);
  $image = substr($icon,-4)=='.png' ? "<img src='$icon' class='img'>" : (substr($icon,0,5)=='icon-' ? "<i class='$icon img'></i>" : "<i class='fa fa-$icon img'></i>");
  $arrConfig = domain_to_config($uuid);
  if ($state == 'running') {
    $mem = $dom['memory'] / 1024;
  } else {
    $mem = $lv->domain_get_memory($res) / 1024;
  }
  $mem = round($mem).'M';
  $vcpu = $dom['nrVirtCpu'];
  $auto = $is_autostart ? 'checked':'';
  $template = $lv->_get_single_xpath_result($res, '//domain/metadata/*[local-name()=\'vmtemplate\']/@name');
  if (empty($template)) $template = 'Custom';
  $log = (is_file("/var/log/libvirt/qemu/$vm.log") ? "libvirt/qemu/$vm.log" : '');
  $disks = '-';
  $diskdesc = '';
  if (($diskcnt = $lv->get_disk_count($res)) > 0) {
    $disks = $diskcnt.' / '.$lv->get_disk_capacity($res);
    $diskdesc = 'Current physical size: '.$lv->get_disk_capacity($res, true);
  }
  $arrValidDiskBuses = getValidDiskBuses();
  $vncport = $lv->domain_get_vnc_port($res);
  $vnc = '';
  $graphics = '';
  if ($vncport > 0) {
    $wsport = $lv->domain_get_ws_port($res);
    $vnc = autov('/plugins/dynamix.vm.manager/vnc.html',true).'&autoconnect=true&host=' . $_SERVER['HTTP_HOST'] . '&port=&path=/wsproxy/' . $wsport . '/';
    $graphics = 'VNC:'.$vncport;
  } elseif ($vncport == -1) {
    $graphics = 'VNC:auto';
  } elseif (!empty($arrConfig['gpu'])) {
    $arrValidGPUDevices = getValidGPUDevices();
    foreach ($arrConfig['gpu'] as $arrGPU) {
      foreach ($arrValidGPUDevices as $arrDev) {
        if ($arrGPU['id'] == $arrDev['id']) {
          if (count(array_filter($arrValidGPUDevices, function($v) use ($arrDev) { return $v['name'] == $arrDev['name']; })) > 1) {
            $graphics .= $arrDev['name'].' ('.$arrDev['id'].')'."\n";
          } else {
            $graphics .= $arrDev['name']."\n";
          }
        }
      }
    }
    $graphics = str_replace("\n", "<br>", trim($graphics));
  }
  unset($dom);
  $menu = sprintf("onclick=\"addVMContext('%s','%s','%s','%s','%s','%s')\"", addslashes($vm),addslashes($uuid),addslashes($template),$state,addslashes($vnc),addslashes($log));
  $kvm[] = "kvm.push({id:'$uuid',state:'$state'});";
  switch ($state) {
  case 'running':
    $shape = 'play';
    $status = 'started';
    $color = 'green-text';
    break;
  case 'paused':
  case 'pmsuspended':
    $shape = 'pause';
    $status = 'paused';
    $color = 'orange-text';
    break;
  default:
    $shape = 'square';
    $status = 'stopped';
    $color = 'red-text';
    break;
  }

  /* VM information */
  echo "<tr parent-id='$i' class='sortable'><td class='vm-name' style='width:220px;padding:8px'>";
  echo "<span class='outer'><span id='vm-$uuid' $menu class='hand'>$image</span><span class='inner'><a href='#' onclick='return toggle_id(\"name-$i\")' title='click for more VM info'>$vm</a><br><i class='fa fa-$shape $status $color'></i><span class='state'>"._($status)."</span></span></span></td>";
  echo "<td>$desc</td>";
  echo "<td><a class='vcpu-$uuid' style='cursor:pointer'>$vcpu</a></td>";
  echo "<td>$mem</td>";
  echo "<td title='$diskdesc'>$disks</td>";
  echo "<td>$graphics</td>";
  echo "<td><input class='autostart' type='checkbox' name='auto_{$vm}' title=\""._('Toggle VM autostart')."\" uuid='$uuid' $auto></td></tr>";

  /* Disk device information */
  echo "<tr child-id='$i' id='name-$i".(in_array('name-'.$i++,$show) ? "'>" : "' style='display:none'>");
  echo "<td colspan='8' style='margin:0;padding:0'>";
  echo "<table class='tablesorter domdisk' id='domdisk_table'>";
  echo "<thead><tr><th><i class='fa fa-hdd-o'></i> <b>"._('Disk devices')."</b></th><th>"._('Bus')."</th><th>"._('Capacity')."</th><th>"._('Allocation')."</th></tr></thead>";
  echo "<tbody id='domdisk_list'>";

  /* Display VM disks */
  foreach ($lv->get_disk_stats($res) as $arrDisk) {
    $capacity = $lv->format_size($arrDisk['capacity'], 0);
    $allocation = $lv->format_size($arrDisk['allocation'], 0);
    $disk = $arrDisk['file'] ?? $arrDisk['partition'];
    $dev = $arrDisk['device'];
    $bus = $arrValidDiskBuses[$arrDisk['bus']] ?? 'VirtIO';
    echo "<tr><td>$disk</td><td>$bus</td>";
    if ($state == 'shutoff') {
      echo "<td title='Click to increase Disk Size'>";
      echo "<form method='get' action=''>";
      echo "<input type='hidden' name='subaction' value='disk-resize'>";
      echo "<input type='hidden' name='uuid' value='".$uuid."'>";
      echo "<input type='hidden' name='disk' value='".htmlspecialchars($disk)."'>";
      echo "<input type='hidden' name='oldcap' value='".$capacity."'>";
      echo "<span class='diskresize' style='width:30px'>";
      echo "<span class='text'><a href='#' onclick='return false'>$capacity</a></span>";
      echo "<input class='input' type='text' style='width:46px' name='cap' value='$capacity' val='diskresize' hidden>";
      echo "</span></form></td>";
    } else {
      echo "<td>$capacity</td>";
    }
    echo "<td>$allocation</td></tr>";
  }

  /* Display VM cdroms */
  foreach ($lv->get_cdrom_stats($res) as $arrCD) {
    $capacity = $lv->format_size($arrCD['capacity'], 0);
    $allocation = $lv->format_size($arrCD['allocation'], 0);
    $disk = $arrCD['file'] ?? $arrCD['partition'];
    $dev = $arrCD['device'];
    $bus = $arrValidDiskBuses[$arrCD['bus']] ?? 'VirtIO';
    echo "<tr><td>$disk</td><td>$bus</td><td>$capacity</td><td>$allocation</td></tr>";
  }

  /* Display VM  IP Addresses "execute":"guest-network-get-interfaces" --pretty */
  echo "<thead><tr><th><i class='fa fa-sitemap'></i> <b>"._('Interfaces')."</b></th><th>"._('Type')."</th><th>"._('IP Address')."</th><th>"._('Prefix')."</th></tr></thead>";
  $ip = $lv->domain_qemu_agent_command($res, '{"execute":"guest-network-get-interfaces"}', 10, 0) ;
  if ($ip != false) {
    $ip = json_decode($ip,true) ;
    $ip = $ip["return"] ;
    $duplicates = []; // hide duplicate interface names
    foreach ($ip as $arrIP) {
      $ipname = $arrIP["name"] ;
      if (preg_match('/^(lo|Loopback)/',$ipname)) continue; // omit loopback interface
      $iphdwadr = $arrIP["hardware-address"] == "" ?  _("N/A") : $arrIP["hardware-address"] ;
      $iplist = $arrIP["ip-addresses"] ;
      foreach ($iplist as $arraddr) {
        $ipaddrval = $arraddr["ip-address"] ;
        if (preg_match('/^f[c-f]/',$ipaddrval)) continue; // omit ipv6 private addresses
        $iptype = $arraddr["ip-address-type"] ;
        $ipprefix = $arraddr["prefix"] ;
        $ipnamemac = "$ipname ($iphdwadr)";
        if (!in_array($ipnamemac,$duplicates)) $duplicates[] = $ipnamemac; else $ipnamemac = "";
        echo "<tr><td>$ipnamemac</td><td>$iptype</td><td>$ipaddrval</td><td>$ipprefix</td></tr>";
        }
    }
  } else echo "<tr><td>"._('Guest not running or guest agent not installed')."</td><td></td><td></td><td></td></tr>";

  echo "</tbody></table>";
  echo "</td></tr>";
}
echo "\0".implode($kvm);
?>
