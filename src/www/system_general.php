<?php

/*
	Copyright (C) 2014-2015 Deciso B.V.
	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
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

require_once("guiconfig.inc");
require_once("filter.inc");
require_once("system.inc");
require_once("unbound.inc");
require_once("pfsense-utils.inc");
require_once("interfaces.inc");
require_once("services.inc");

function get_locale_list()
{
	$locales = array();

	/* first one is the default */
	$locales['en_US'] = gettext('English');
	$locales['zh_CN'] = gettext('Chinese (Simplified)');
	$locales['fr_FR'] = gettext('French');
	$locales['de_DE'] = gettext('German');
	$locales['ja_JP'] = gettext('Japanese');
	$locales['mn_MN'] = gettext('Mongolian');
	$locales['es_CO'] = gettext('Spanish');

	return $locales;
}

$pconfig['hostname'] = $config['system']['hostname'];
$pconfig['domain'] = $config['system']['domain'];
if (isset($config['system']['dnsserver'])) {
	list($pconfig['dns1'],$pconfig['dns2'],$pconfig['dns3'],$pconfig['dns4']) = $config['system']['dnsserver'];
} else {
	list($pconfig['dns1'],$pconfig['dns2'],$pconfig['dns3'],$pconfig['dns4']) = null;
}

$arr_gateways = return_gateways_array();

if (isset($config['system']['dns1gw'])) {
	$pconfig['dns1gw'] = $config['system']['dns1gw'];
} else {
	$pconfig['dns1gw'] = null;
}
if (isset($config['system']['dns2gw'])) {
	$pconfig['dns2gw'] = $config['system']['dns2gw'];
} else {
	$pconfig['dns2gw'] = null;
}
if (isset($config['system']['dns3gw'])) {
	$pconfig['dns3gw'] = $config['system']['dns3gw'];
} else {
	$pconfig['dns3gw'] = null;
}
if (isset($config['system']['dns4gw'])) {
	$pconfig['dns4gw'] = $config['system']['dns4gw'];
} else {
	$pconfig['dns4gw'] = null ;
}

$pconfig['dnsallowoverride'] = isset($config['system']['dnsallowoverride']);
$pconfig['timezone'] = $config['system']['timezone'];
$pconfig['timeupdateinterval'] = $config['system']['time-update-interval'];
$pconfig['timeservers'] = $config['system']['timeservers'];
if (isset($config['system']['theme'])) {
	$pconfig['theme'] = $config['system']['theme'];
} else {
	$pconfig['theme'] = null;
}
if (isset($config['system']['language'])) {
	$pconfig['language'] = $config['system']['language'];
} else {
	$pconfig['language'] = null;
}

$pconfig['dnslocalhost'] = isset($config['system']['dnslocalhost']);

if (!isset($pconfig['timeupdateinterval']))
	$pconfig['timeupdateinterval'] = 300;
if (empty($pconfig['timezone']))
	$pconfig['timezone'] = "Etc/UTC";
if (empty($pconfig['timeservers']))
	$pconfig['timeservers'] = "pool.ntp.org";

$pconfig['mirror'] = 'default';
if (isset($config['system']['firmware']['mirror'])) {
	$pconfig['mirror'] = $config['system']['firmware']['mirror'];
}

$pconfig['flavour'] = 'default';
if (isset($config['system']['firmware']['flavour'])) {
	$pconfig['flavour'] = $config['system']['firmware']['flavour'];
}

if (isset($_POST['timezone']) && $pconfig['timezone'] <> $_POST['timezone']) {
	filter_pflog_start();
}

$timezonelist = get_zoneinfo();

$multiwan = false;
$interfaces = get_configured_interface_list();
foreach($interfaces as $interface) {
	if(interface_has_gateway($interface)) {
		$multiwan = true;
	}
}

if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "hostname domain");
	$reqdfieldsn = array(gettext("Hostname"),gettext("Domain"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if ($_POST['hostname'] && !is_hostname($_POST['hostname'])) {
		$input_errors[] = gettext("The hostname may only contain the characters a-z, 0-9 and '-'.");
	}
	if ($_POST['domain'] && !is_domain($_POST['domain'])) {
		$input_errors[] = gettext("The domain may only contain the characters a-z, 0-9, '-' and '.'.");
	}

	$ignore_posted_dnsgw = array();

	for ($dnscounter=1; $dnscounter<5; $dnscounter++){
		$dnsname="dns{$dnscounter}";
		$dnsgwname="dns{$dnscounter}gw";
		if (($_POST[$dnsname] && !is_ipaddr($_POST[$dnsname]))) {
			$input_errors[] = gettext("A valid IP address must be specified for DNS server $dnscounter.");
		} else {
			if(($_POST[$dnsgwname] <> "") && ($_POST[$dnsgwname] <> "none")) {
				// A real gateway has been selected.
				if (is_ipaddr($_POST[$dnsname])) {
					if ((is_ipaddrv4($_POST[$dnsname])) && (validate_address_family($_POST[$dnsname], $_POST[$dnsgwname]) === false )) {
						$input_errors[] = gettext("You can not specify IPv6 gateway '{$_POST[$dnsgwname]}' for IPv4 DNS server '{$_POST[$dnsname]}'");
					}
					if ((is_ipaddrv6($_POST[$dnsname])) && (validate_address_family($_POST[$dnsname], $_POST[$dnsgwname]) === false )) {
						$input_errors[] = gettext("You can not specify IPv4 gateway '{$_POST[$dnsgwname]}' for IPv6 DNS server '{$_POST[$dnsname]}'");
					}
				} else {
					// The user selected a gateway but did not provide a DNS address. Be nice and set the gateway back to "none".
					$ignore_posted_dnsgw[$dnsgwname] = true;
				}
			}
		}
	}

	$direct_networks_list = explode(" ", filter_get_direct_networks_list());
	for ($dnscounter=1; $dnscounter<5; $dnscounter++) {
		$dnsitem = "dns{$dnscounter}";
		$dnsgwitem = "dns{$dnscounter}gw";
		if ($_POST[$dnsgwitem]) {
			if(interface_has_gateway($_POST[$dnsgwitem])) {
				foreach($direct_networks_list as $direct_network) {
					if(ip_in_subnet($_POST[$dnsitem], $direct_network)) {
						$input_errors[] = sprintf(gettext("You can not assign a gateway to DNS '%s' server which is on a directly connected network."),$_POST[$dnsitem]);
					}
				}
			}
		}
	}

	$t = (int)$_POST['timeupdateinterval'];
	if (($t < 0) || (($t > 0) && ($t < 6)) || ($t > 1440)) {
		$input_errors[] = gettext("The time update interval must be either 0 (disabled) or between 6 and 1440.");
	}
	# it's easy to have a little too much whitespace in the field, clean it up for the user before processing.
	$_POST['timeservers'] = preg_replace('/[[:blank:]]+/', ' ', $_POST['timeservers']);
	$_POST['timeservers'] = trim($_POST['timeservers']);
	foreach (explode(' ', $_POST['timeservers']) as $ts) {
		if (!is_domain($ts)) {
			$input_errors[] = gettext("A NTP Time Server name may only contain the characters a-z, 0-9, '-' and '.'.");
		}
	}

	if (!$input_errors) {
		update_if_changed("hostname", $config['system']['hostname'], $_POST['hostname']);
		update_if_changed("domain", $config['system']['domain'], $_POST['domain']);

		update_if_changed("timezone", $config['system']['timezone'], $_POST['timezone']);
		update_if_changed("NTP servers", $config['system']['timeservers'], strtolower($_POST['timeservers']));
		update_if_changed("NTP update interval", $config['system']['time-update-interval'], $_POST['timeupdateinterval']);

		if ($_POST['language'] && $_POST['language'] != $config['system']['language']) {
			$config['system']['language'] = $_POST['language'];
			set_language($config['system']['language']);
		}

		if (!isset($config['system']['firmware'])) {
			$config['system']['firmware'] = array();
		}
		if ($_POST['mirror'] == 'default') {
			if (isset($config['system']['firmware']['mirror'])) {
				/* default does not set anything for backwards compat */
				unset($config['system']['firmware']['mirror']);
			}
		} else {
			$config['system']['firmware']['mirror'] = $_POST['mirror'];
		}
		if ($_POST['flavour'] == 'default') {
			if (isset($config['system']['firmware']['flavour'])) {
				/* default does not set anything for backwards compat */
				unset($config['system']['firmware']['flavour']);
			}
		} else {
			$config['system']['firmware']['flavour'] = $_POST['flavour'];
		}

		update_if_changed("System Theme", $config['theme'], $_POST['theme']);

		/* XXX - billm: these still need updating after figuring out how to check if they actually changed */
		$olddnsservers = $config['system']['dnsserver'];
		unset($config['system']['dnsserver']);
		if ($_POST['dns1'])
			$config['system']['dnsserver'][] = $_POST['dns1'];
		if ($_POST['dns2'])
			$config['system']['dnsserver'][] = $_POST['dns2'];
		if ($_POST['dns3'])
			$config['system']['dnsserver'][] = $_POST['dns3'];
		if ($_POST['dns4'])
			$config['system']['dnsserver'][] = $_POST['dns4'];

		$olddnsallowoverride = $config['system']['dnsallowoverride'];

		unset($config['system']['dnsallowoverride']);
		$config['system']['dnsallowoverride'] = $_POST['dnsallowoverride'] ? true : false;

		if($_POST['dnslocalhost'] == "yes")
			$config['system']['dnslocalhost'] = true;
		else
			unset($config['system']['dnslocalhost']);

		/* which interface should the dns servers resolve through? */
		$outdnscounter = 0;
		for ($dnscounter=1; $dnscounter<5; $dnscounter++) {
			$dnsname="dns{$dnscounter}";
			$dnsgwname="dns{$dnscounter}gw";
			$olddnsgwname = $config['system'][$dnsgwname];

			if ($ignore_posted_dnsgw[$dnsgwname])
				$thisdnsgwname = "none";
			else
				$thisdnsgwname = $pconfig[$dnsgwname];

			// "Blank" out the settings for this index, then we set them below using the "outdnscounter" index.
			$config['system'][$dnsgwname] = "none";
			$pconfig[$dnsgwname] = "none";
			$pconfig[$dnsname] = "";

			if ($_POST[$dnsname]) {
				// Only the non-blank DNS servers were put into the config above.
				// So we similarly only add the corresponding gateways sequentially to the config (and to pconfig), as we find non-blank DNS servers.
				// This keeps the DNS server IP and corresponding gateway "lined up" when the user blanks out a DNS server IP in the middle of the list.
				$outdnscounter++;
				$outdnsname="dns{$outdnscounter}";
				$outdnsgwname="dns{$outdnscounter}gw";
				$pconfig[$outdnsname] = $_POST[$dnsname];
				if($_POST[$dnsgwname]) {
					$config['system'][$outdnsgwname] = $thisdnsgwname;
					$pconfig[$outdnsgwname] = $thisdnsgwname;
				} else {
					// Note: when no DNS GW name is chosen, the entry is set to "none", so actually this case never happens.
					unset($config['system'][$outdnsgwname]);
					$pconfig[$outdnsgwname] = "";
				}
			}
			if (($olddnsgwname != "") && ($olddnsgwname != "none") && (($olddnsgwname != $thisdnsgwname) || ($olddnsservers[$dnscounter-1] != $_POST[$dnsname]))) {
				// A previous DNS GW name was specified. It has now gone or changed, or the DNS server address has changed.
				// Remove the route. Later calls will add the correct new route if needed.
				if (is_ipaddrv4($olddnsservers[$dnscounter-1]))
					mwexec("/sbin/route delete " . escapeshellarg($olddnsservers[$dnscounter-1]));
				else
					if (is_ipaddrv6($olddnsservers[$dnscounter-1]))
						mwexec("/sbin/route delete -inet6 " . escapeshellarg($olddnsservers[$dnscounter-1]));
			}
		}

		write_config();

		$retval = 0;
		$retval = system_hostname_configure();
		$retval |= system_hosts_generate();
		$retval |= system_resolvconf_generate();
		if (isset($config['dnsmasq']['enable']))
			$retval |= services_dnsmasq_configure();
		elseif (isset($config['unbound']['enable']))
			$retval |= services_unbound_configure();
		$retval |= system_timezone_configure();
		$retval |= system_firmware_configure();
		$retval |= system_ntp_configure();

		if ($olddnsallowoverride != $config['system']['dnsallowoverride']) {
			configd_run("dns reload");
		}

		// Reload the filter - plugins might need to be run.
		$retval |= filter_configure();

		$savemsg = get_std_save_message();
	}

	unset($ignore_posted_dnsgw);
}

$pgtitle = array(gettext("System"),gettext("Settings"),gettext("General"));
include("head.inc");

?>

<body>
    <?php include("fbegin.inc"); ?>

<!-- row -->
<section class="page-content-main">
	<div class="container-fluid">

        <div class="row">
            <?php
		if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors);
		if (isset($savemsg)) print_info_box($savemsg);
            ?>
            <section class="col-xs-12">
                <div class="content-box tab-content">

			<form action="system_general.php" method="post">
				<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="main area" class="table table-striped">
					<thead>
						<tr>
							<th colspan="2" valign="top" class="listtopic"><?=gettext("System"); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td width="22%" valign="top" class="vncellreq"><?=gettext("Hostname"); ?></td>
							<td width="78%" class="vtable"> <input name="hostname" type="text" class="formfld unknown" id="hostname" size="40" value="<?=htmlspecialchars($pconfig['hostname']);?>" />
								<br />
								<span class="vexpl">
									<?=gettext("Name of the firewall host, without domain part"); ?>
									<br />
									<?=gettext("e.g."); ?> <em>firewall</em>
								</span>
							</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncellreq"><?=gettext("Domain"); ?></td>
							<td width="78%" class="vtable"> <input name="domain" type="text" class="formfld unknown" id="domain" size="40" value="<?=htmlspecialchars($pconfig['domain']);?>" />
								<br />
								<span class="vexpl">
									<?=gettext("Do not use 'local' as a domain name. It will cause local hosts running mDNS (avahi, bonjour, etc.) to be unable to resolve local hosts not running mDNS."); ?>
									<br />
									<?=gettext("e.g."); ?> <em><?=gettext("mycorp.com, home, office, private, etc."); ?></em>
								</span>
							</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncell"><?=gettext("DNS servers"); ?></td>
							<td width="78%" class="vtable">

								<table border="0" cellpadding="0" cellspacing="0" summary="dns servers and gateways" class="table table-striped">
									<thead>
										<tr>
											<th><?=gettext("DNS Server"); ?></th>
											<?php if ($multiwan): ?>
											<th><?=gettext("Use gateway"); ?></th>
											<?php endif; ?>
										</tr>
									</thead>
									<tbody>
									<?php
										for ($dnscounter=1; $dnscounter<5; $dnscounter++):
											$fldname="dns{$dnscounter}gw";
									?>
									<tr>
										<td width="348px">
											<input name="dns<?php echo $dnscounter;?>" type="text" class="formfld unknown" id="dns<?php echo $dnscounter;?>" size="28" value="<?php echo $pconfig['dns'.$dnscounter];?>" />
										</td>
										<td>
                                                    <?php if ($multiwan): ?>
											<select name='<?=$fldname;?>' class='selectpicker' data-style='btn-default' data-width='auto'>
												<?php
													$gwname = "none";
													$dnsgw = "dns{$dnscounter}gw";
													if($pconfig[$dnsgw] == $gwname) {
														$selected = "selected=\"selected\"";
													} else {
														$selected = "";
													}
													echo "<option value='$gwname' $selected>$gwname</option>\n";
													foreach($arr_gateways as $gwname => $gwitem) {
														//echo $pconfig[$dnsgw];
														if((is_ipaddrv4(lookup_gateway_ip_by_name($pconfig[$dnsgw])) && (is_ipaddrv6($gwitem['gateway'])))) {
															continue;
														}
														if((is_ipaddrv6(lookup_gateway_ip_by_name($pconfig[$dnsgw])) && (is_ipaddrv4($gwitem['gateway'])))) {
															continue;
														}
														if($pconfig[$dnsgw] == $gwname) {
															$selected = "selected=\"selected\"";
														} else {
															$selected = "";
														}
														echo "<option value='$gwname' $selected>$gwname - {$gwitem['friendlyiface']} - {$gwitem['gateway']}</option>\n";
													}
												?>
											</select>
                                                        <?php endif; ?>
										</td>
									</tr>
									<?php endfor; ?>
									</tbody>
								</table>

								<span class="vexpl">
									<?=gettext("Enter IP addresses to be used by the system for DNS resolution. " .
									"These are also used for the DHCP service, DNS forwarder and for PPTP VPN clients."); ?>
									<br />
									<?php if($multiwan): ?>
									<br />
									<?=gettext("In addition, optionally select the gateway for each DNS server. " .
									"When using multiple WAN connections there should be at least one unique DNS server per gateway."); ?>
									<br />
									<?php endif; ?>
									<br />
									<input name="dnsallowoverride" type="checkbox" id="dnsallowoverride" value="yes" <?php if ($pconfig['dnsallowoverride']) echo "checked=\"checked\""; ?> />
									<strong>
										<?=gettext("Allow DNS server list to be overridden by DHCP/PPP on WAN"); ?>
									</strong>
									<br />
									<?php printf(gettext("If this option is set, %s will " .
									"use DNS servers assigned by a DHCP/PPP server on WAN " .
									"for its own purposes (including the DNS forwarder). " .
									"However, they will not be assigned to DHCP and PPTP " .
									"VPN clients."), $g['product_name']); ?>
									<br />
									<br />
									<input name="dnslocalhost" type="checkbox" id="dnslocalhost" value="yes" <?php if ($pconfig['dnslocalhost']) echo "checked=\"checked\""; ?> />
									<strong>
										<?=gettext("Do not use the DNS Forwarder as a DNS server for the firewall"); ?>
									</strong>
									<br />
									<?=gettext("By default localhost (127.0.0.1) will be used as the first DNS server where the DNS Forwarder or DNS Resolver is enabled and set to listen on Localhost, so system can use the local DNS service to perform lookups. ".
									"Checking this box omits localhost from the list of DNS servers."); ?>
								</span>

							</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncell"><?=gettext("Time zone"); ?></td>
							<td width="78%" class="vtable">
								<select name="timezone" id="timezone" class="selectpicker" data-style="btn-default" data-live-search="true">
									<?php foreach ($timezonelist as $value): ?>
									<?php if(strstr($value, "GMT")) continue; ?>
									<option value="<?=htmlspecialchars($value);?>" <?php if ($value == $pconfig['timezone']) echo "selected=\"selected\""; ?>>
										<?=htmlspecialchars($value);?>
									</option>
									<?php endforeach; ?>
								</select>
								<br />
								<span class="vexpl">
									<?=gettext("Select the location closest to you"); ?>
								</span>
							</td>
						</tr>
                        <!--
						<tr>
							<td width="22%" valign="top" class="vncell">Time update interval</td>
							<td width="78%" class="vtable">
								<input name="timeupdateinterval" type="text" class="formfld unknown" id="timeupdateinterval" size="4" value="<?=htmlspecialchars($pconfig['timeupdateinterval']);?>" />
								<br />
								<span class="vexpl">
									Minutes between network time sync. 300 recommended,
									or 0 to disable
								</span>
							</td>
						</tr>
                        -->
						<tr>
							<td width="22%" valign="top" class="vncell"><?=gettext("NTP time server"); ?></td>
							<td width="78%" class="vtable">
								<input name="timeservers" type="text" class="formfld unknown" id="timeservers" size="40" value="<?=htmlspecialchars($pconfig['timeservers']);?>" />
								<br />
								<span class="vexpl">
									<?=gettext("Use a space to separate multiple hosts (only one " .
									"required). Remember to set up at least one DNS server " .
									"if you enter a host name here!"); ?>
								</span>
							</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncell"><?=gettext("Language");?></td>
							<td width="78%" class="vtable">
								<select name="language" class="selectpicker" data-style="btn-default" data-width="auto">
									<?php
									foreach (get_locale_list() as $lcode => $ldesc) {
										$selected = ' selected="selected"';
										if($lcode != $pconfig['language'])
											$selected = '';
										echo "<option value=\"{$lcode}\"{$selected}>{$ldesc}</option>";
									}
									?>
								</select>
								<strong>
									<?=gettext("Choose a language for the webConfigurator"); ?>
								</strong>
							</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncell"><?=gettext("Theme"); ?></td>
							<td width="78%" class="vtable">
								<select name="theme" class="selectpicker" data-style="btn-default" data-width="auto">
									<?php
										$files = return_dir_as_array('/usr/local/opnsense/www/themes/');
										$curtheme = get_current_theme();

										foreach ($files as $file):
											$selected = '';
											if ($file == $curtheme) {
												$selected = ' selected="selected"';
											}
									?>
									<option <?=$selected;?>><?=$file;?></option>
									<?php endforeach; ?>
								</select>
								<strong>
									<?=gettext("This will change the look and feel of"); ?>
									<?=$g['product_name'];?>.
								</strong>
							</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncell"><?=gettext("Firmware Mirror"); ?></td>
							<td width="78%" class="vtable">
								<select name="mirror" class="selectpicker" data-style="btn-default" data-width="auto">
									<?php
									foreach (get_firmware_mirrors() as $mcode => $mdesc) {
										$selected = ' selected="selected"';
										if($mcode != $pconfig['mirror']) {
											$selected = '';
										}
										echo "<option value=\"{$mcode}\"{$selected}>{$mdesc}</option>";
									}
									?>
								</select>
								<strong>
									<?=gettext("Select an alternate firmware mirror."); ?>
								</strong>
							</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncell"><?=gettext("Firmware Flavour"); ?></td>
							<td width="78%" class="vtable">
								<select name="flavour" class="selectpicker" data-style="btn-default" data-width="auto">
									<?php
									foreach (get_firmware_flavours() as $fcode => $fdesc) {
										$selected = ' selected="selected"';
										if($fcode != $pconfig['flavour']) {
											$selected = '';
										}
										echo "<option value=\"{$fcode}\"{$selected}>{$fdesc}</option>";
									}
									?>
								</select>
								<strong>
									<?=gettext("Select the firmware cryptography flavour."); ?>
								</strong>
							</td>
						</tr>
						<tr>
							<td width="22%" valign="top">&nbsp;</td>
							<td width="78%">
								<input name="Submit" type="submit" class="btn btn-primary" value="<?=gettext("Save");?>" />
							</td>
						</tr>
					</tbody>
				</table>
                    </form>

                </div>
            </section>

        </div>

	</div>
</section>

<?php include("foot.inc"); ?>
