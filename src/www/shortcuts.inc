<?php

/*
	Copyright (C) 2014-2015 Deciso B.V.
	Copyright (C) 2012 Jim Pingle
	Copyright (C) 2007, 2008 Scott Ullrich <sullrich@gmail.com>
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.

*/

// On the page, add in like so:
//   $shortcut_section = "relayd";

$shortcuts = array();

/* Load and process custom shortcuts. */
function get_shortcut_files($directory)
{
	$dir_array = array();

	if ($dh = @opendir($directory)) {
		while (($file = readdir($dh)) !== false) {
			$canadd = 0;
			if($file == ".")
				$canadd = 1;
			if($file == "..")
				$canadd = 1;
			if($canadd == 0)
				array_push($dir_array, $file);
		}
		closedir($dh);
	}

	return $dir_array;
}

function get_shortcut_by_service_name($servicename) {
	global $shortcuts;
	foreach ($shortcuts as $name => $shortcut) {
		if (!empty($shortcut['service']) && ($shortcut['service'] == $servicename))
			return $name;
	}
	return null;
}

function get_shortcut_main_link($shortcut_section, $addspace = true, $service = array()) {
	global $g, $shortcuts;
	if(empty($shortcut_section))
		return "";
	$space = ($addspace) ? "&nbsp;" : "" ;
	switch ($shortcut_section) {
		case "openvpn":
			if (!empty($service['mode']) && is_numeric($service['id']))
				$link = "vpn_openvpn_{$service['mode']}.php?act=edit&amp;id={$service['id']}";
			else
				$link = $shortcuts[$shortcut_section]['main'];
			break;
		case "captiveportal":
			if (!empty($service['zone']))
				$link = "services_captiveportal.php?zone={$service['zone']}";
			else
				$link = $shortcuts[$shortcut_section]['main'];
			break;
		default:
			$link = $shortcuts[$shortcut_section]['main'];
			break;
	}
	if (!empty($link)) {
		if (strtok($_SERVER['REQUEST_URI'],'?') != "/status_services.php")
			return "{$space}<a href=\"{$link}\" title=\"" . gettext("Main page for this section") . "\">Main page</a>";
		else
			return "{$space}<a href=\"{$link}\" class=\"btn btn-default\" data-toggle=\"tooltip\" data-placement=\"bottom\" title=\"" . gettext("Main page for this section") . "\"><span class=\"glyphicon glyphicon-home\"></span></a>";
	}
}

function get_shortcut_status_link($shortcut_section, $addspace = true, $service = array()) {
	global $g, $shortcuts, $cpzone;
	if(empty($shortcut_section))
		return "";
	$space = ($addspace) ? "&nbsp;" : "" ;
	if (!empty($cpzone))
		$zone = $cpzone;
	elseif (!empty($service['zone']))
		$zone = $service['zone'];
	switch ($shortcut_section) {
		case "captiveportal":
			if (!empty($zone))
				$link = "status_captiveportal.php?zone={$zone}";
			else
				$link = $shortcuts[$shortcut_section]['status'];
			break;
		default:
			if (isset($shortcuts[$shortcut_section]['status'])) {
				$link = $shortcuts[$shortcut_section]['status'];
			} else {
				$link = null;
			}
			break;
	}
	if(!empty($link))
		if (strtok($_SERVER['REQUEST_URI'],'?') != "/status_services.php")
			return "{$space}<a href=\"{$link}\" title=\"" . gettext("Status of items on this page") . "\">Status</a>";
		else
			return "{$space}<a href=\"{$link}\" class=\"btn btn-default\" data-toggle=\"tooltip\" data-placement=\"bottom\" title=\"" . gettext("Status of items on this page") . "\"><span class=\"glyphicon glyphicon-eye-open\"></span></a>";
}

function get_shortcut_log_link($shortcut_section, $addspace = true) {
	global $g, $shortcuts;
	$space = ($addspace) ? "&nbsp;" : "" ;
	if(!empty($shortcut_section) && !empty($shortcuts[$shortcut_section]['log'])) {
		if (strtok($_SERVER['REQUEST_URI'],'?') != "/status_services.php")
			return "{$space}<a href=\"{$shortcuts[$shortcut_section]['log']}\" title=\"" . gettext("Log entries for items on this page") . "\">Log</a>";
		else
			return "{$space}<a href=\"{$shortcuts[$shortcut_section]['log']}\" class=\"btn btn-default\" data-toggle=\"tooltip\" data-placement=\"bottom\" title=\"" . gettext("Log entries for items on this page") . "\"><span class=\"glyphicon glyphicon-list\"></span></a>";
	}
}

$shortcuts['upnp'] = array();
$shortcuts['upnp']['main'] = "services_upnp.php";
$shortcuts['upnp']['log'] = "diag_logs_routing.php";
$shortcuts['upnp']['status'] = "status_upnp.php";
$shortcuts['upnp']['service'] = "miniupnpd";


$shortcuts['relayd'] = array();
$shortcuts['relayd']['main'] = "load_balancer_pool.php";
$shortcuts['relayd']['log'] = "diag_logs_relayd.php";
$shortcuts['relayd']['status'] = "status_lb_pool.php";
$shortcuts['relayd']['service'] = "relayd";

$shortcuts['relayd-virtualservers'] = array();
$shortcuts['relayd-virtualservers']['main'] = "load_balancer_virtual_server.php";
$shortcuts['relayd-virtualservers']['log'] = "diag_logs_relayd.php";
$shortcuts['relayd-virtualservers']['status'] = "status_lb_vs.php";
$shortcuts['relayd-virtualservers']['service'] = "relayd";

$shortcuts['captiveportal'] = array();
$shortcuts['captiveportal']['main'] = "services_captiveportal_zones.php";
$shortcuts['captiveportal']['log'] = "diag_logs_auth.php";
$shortcuts['captiveportal']['status'] = "status_captiveportal.php";
$shortcuts['captiveportal']['service'] = "captiveportal";

$shortcuts['captiveportal-vouchers'] = array();
$shortcuts['captiveportal-vouchers']['log'] = "diag_logs_auth.php";
$shortcuts['captiveportal-vouchers']['status'] = "status_captiveportal_vouchers.php";
$shortcuts['captiveportal-vouchers']['service'] = "captiveportal";

$shortcuts['dhcp'] = array();
$shortcuts['dhcp']['main'] = "services_dhcp.php";
$shortcuts['dhcp']['log'] = "diag_logs_dhcp.php";
$shortcuts['dhcp']['status'] = "status_dhcp_leases.php";
$shortcuts['dhcp']['service'] = "dhcpd";

$shortcuts['dhcp6'] = array();
$shortcuts['dhcp6']['main'] = "services_dhcpv6.php";
$shortcuts['dhcp6']['log'] = "diag_logs_dhcp.php";
$shortcuts['dhcp6']['status'] = "status_dhcpv6_leases.php";


$shortcuts['ipsec'] = array();
$shortcuts['ipsec']['main'] = "vpn_ipsec.php";
$shortcuts['ipsec']['log'] = "diag_logs_ipsec.php";
$shortcuts['ipsec']['status'] = "diag_ipsec.php";
$shortcuts['ipsec']['service'] = "ipsec";

$shortcuts['openvpn'] = array();
$shortcuts['openvpn']['main'] = "vpn_openvpn_server.php";
$shortcuts['openvpn']['log'] = "diag_logs_openvpn.php";
$shortcuts['openvpn']['status'] = "status_openvpn.php";
$shortcuts['openvpn']['service'] = "openvpn";

$shortcuts['firewall'] = array();
$shortcuts['firewall']['main'] = "firewall_rules.php";
$shortcuts['firewall']['log'] = "diag_logs_filter.php";
$shortcuts['firewall']['status'] = "status_filter_reload.php";

$shortcuts['routing'] = array();
$shortcuts['routing']['main'] = "system_routes.php";
$shortcuts['routing']['log'] = "diag_logs_routing.php";
$shortcuts['routing']['status'] = "diag_routes.php";

$shortcuts['gateways'] = array();
$shortcuts['gateways']['main'] = "system_gateways.php";
$shortcuts['gateways']['log'] = "diag_logs_gateways.php";
$shortcuts['gateways']['status'] = "status_gateways.php";
$shortcuts['gateways']['service'] = "apinger";

$shortcuts['gateway-groups'] = array();
$shortcuts['gateway-groups']['main'] = "system_gateway_groups.php";
$shortcuts['gateway-groups']['log'] = "diag_logs_gateways.php";
$shortcuts['gateway-groups']['status'] = "status_gateway_groups.php";

$shortcuts['interfaces'] = array();
$shortcuts['interfaces']['main'] = "interfaces_assign.php";
$shortcuts['interfaces']['status'] = "status_interfaces.php";

$shortcuts['forwarder'] = array();
$shortcuts['forwarder']['main'] = "services_dnsmasq.php";
$shortcuts['forwarder']['log'] = "diag_logs_resolver.php";
$shortcuts['forwarder']['service'] = "dnsmasq";

$shortcuts['resolver'] = array();
$shortcuts['resolver']['main'] = "services_unbound.php";
$shortcuts['resolver']['log'] = "diag_logs_resolver.php";
$shortcuts['resolver']['service'] = "unbound";

$shortcuts['wireless'] = array();
$shortcuts['wireless']['main'] = "interfaces_wireless.php";
$shortcuts['wireless']['log'] = "diag_logs_wireless.php";
$shortcuts['wireless']['status'] = "status_wireless.php";

$shortcuts['ntp'] = array();
$shortcuts['ntp']['main'] = "services_ntpd.php";
$shortcuts['ntp']['log'] = "diag_logs_ntpd.php";
$shortcuts['ntp']['status'] = "status_ntpd.php";
$shortcuts['ntp']['service'] = "ntpd";

$shortcuts['pptps'] = array();
$shortcuts['pptps']['main'] = "vpn_pptp.php";
$shortcuts['pptps']['log'] = "diag_logs_vpn.php";

$shortcuts['pppoes'] = array();
$shortcuts['pppoes']['main'] = "vpn_pppoe.php";
$shortcuts['pppoes']['log'] = "diag_logs_vpn.php?vpntype=poes";

$shortcuts['l2tps'] = array();
$shortcuts['l2tps']['main'] = "vpn_l2tp.php";
$shortcuts['l2tps']['log'] = "diag_logs_vpn.php?vpntype=l2tp";

$shortcuts['snmp'] = array();
$shortcuts['snmp']['main'] = "services_snmp.php";
$shortcuts['snmp']['service'] = "bsnmpd";

$shortcuts['authentication'] = array();
$shortcuts['authentication']['main'] = "system_authservers.php";
$shortcuts['authentication']['status'] = "diag_authentication.php";

$shortcuts['aliases'] = array();
$shortcuts['aliases']['main'] = "firewall_aliases.php";
$shortcuts['aliases']['status'] = "diag_tables.php";

$shortcuts['squid'] = array();
$shortcuts['squid']['main'] = "ui/proxy/";
$shortcuts['squid']['log'] = "diag_logs_proxy.php";
//TODO: $shortcuts['squid']['status'] = "";
$shortcuts['squid']['service'] = "squid";
