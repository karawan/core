<?php

/*
	Copyright (C) 2014-2015 Deciso B.V.
	Copyright (C) 2008 Ermal Luçi
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
require_once("pfsense-utils.inc");
require_once("services.inc") ;
require_once("interfaces.inc");

/* returns true if $uname is a valid dynamic DNS username */
function is_dyndns_username($uname) {
        if (!is_string($uname))
                return false;

        if (preg_match("/[^a-z0-9\-.@_:]/i", $uname))
                return false;
        else
                return true;
}

if (!isset($config['dyndnses']['dyndns'])) {
	$config['dyndnses']['dyndns'] = array();
}

$a_dyndns = &$config['dyndnses']['dyndns'];

if (is_numericint($_GET['id']))
	$id = $_GET['id'];
if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && isset($a_dyndns[$id])) {
	$pconfig['username'] = $a_dyndns[$id]['username'];
	$pconfig['password'] = $a_dyndns[$id]['password'];
	$pconfig['host'] = $a_dyndns[$id]['host'];
	$pconfig['mx'] = $a_dyndns[$id]['mx'];
	$pconfig['type'] = $a_dyndns[$id]['type'];
	$pconfig['enable'] = !isset($a_dyndns[$id]['enable']);
	$pconfig['interface'] = $a_dyndns[$id]['interface'];
	$pconfig['wildcard'] = isset($a_dyndns[$id]['wildcard']);
	$pconfig['verboselog'] = isset($a_dyndns[$id]['verboselog']);
	$pconfig['curl_ipresolve_v4'] = isset($a_dyndns[$id]['curl_ipresolve_v4']);
	$pconfig['curl_ssl_verifypeer'] = isset($a_dyndns[$id]['curl_ssl_verifypeer']);
	$pconfig['zoneid'] = $a_dyndns[$id]['zoneid'];
	$pconfig['ttl'] = $a_dyndns[$id]['ttl'];
	$pconfig['updateurl'] = $a_dyndns[$id]['updateurl'];
	$pconfig['resultmatch'] = $a_dyndns[$id]['resultmatch'];
	$pconfig['requestif'] = $a_dyndns[$id]['requestif'];
	$pconfig['descr'] = $a_dyndns[$id]['descr'];
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	if(($pconfig['type'] == "freedns" || $pconfig['type'] == "namecheap") && $_POST['username'] == "")
		$_POST['username'] = "none";

	/* input validation */
	$reqdfields = array();
	$reqdfieldsn = array();
	$reqdfields = array('type');
	$reqdfieldsn = array(gettext('Service type'));
	if ($pconfig['type'] != 'custom' && $pconfig['type'] != 'custom-v6') {
		$reqdfields[] = 'host';
		$reqdfieldsn[] = gettext('Hostname');
		$reqdfields[] = 'username';
		$reqdfieldsn[] = gettext('Username');
		if ($pconfig['type'] != 'duckdns') {
			$reqdfields[] = 'password';
			$reqdfieldsn[] = gettext('Password');
		}
	} else {
		$reqdfields[] = 'updateurl';
		$reqdfieldsn[] = gettext('Update URL');
	}

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (isset($_POST['host']) && in_array('host', $reqdfields)) {
		/* Namecheap can have a @. in hostname */
		if ($pconfig['type'] == "namecheap" && substr($_POST['host'], 0, 2) == '@.')
			$host_to_check = substr($_POST['host'], 2);
		else
			$host_to_check = $_POST['host'];

		if (!is_domain($host_to_check))
			$input_errors[] = gettext("The Hostname contains invalid characters.");

		unset($host_to_check);
	}

	if (($_POST['mx'] && !is_domain($_POST['mx'])))
		$input_errors[] = gettext("The MX contains invalid characters.");
	if ((in_array("username", $reqdfields) && $_POST['username'] && !is_dyndns_username($_POST['username'])) || ((in_array("username", $reqdfields)) && ($_POST['username'] == "")))
		$input_errors[] = gettext("The username contains invalid characters.");

	if (!$input_errors) {
		$dyndns = array();
		$dyndns['type'] = $_POST['type'];
		$dyndns['username'] = $_POST['username'];
		$dyndns['password'] = $_POST['password'];
		$dyndns['host'] = $_POST['host'];
		$dyndns['mx'] = $_POST['mx'];
		$dyndns['wildcard'] = $_POST['wildcard'] ? true : false;
		$dyndns['verboselog'] = $_POST['verboselog'] ? true : false;
		$dyndns['curl_ipresolve_v4'] = $_POST['curl_ipresolve_v4'] ? true : false;
		$dyndns['curl_ssl_verifypeer'] = $_POST['curl_ssl_verifypeer'] ? true : false;
		/* In this place enable means disabled */
		if ($_POST['enable'])
			unset($dyndns['enable']);
		else
			$dyndns['enable'] = true;
		$dyndns['interface'] = $_POST['interface'];
		$dyndns['zoneid'] = $_POST['zoneid'];
		$dyndns['ttl'] = $_POST['ttl'];
		$dyndns['updateurl'] = $_POST['updateurl'];
		// Trim hard-to-type but sometimes returned characters
		$dyndns['resultmatch'] = trim($_POST['resultmatch'], "\t\n\r");
		($dyndns['type'] == "custom" || $dyndns['type'] == "custom-v6") ? $dyndns['requestif'] = $_POST['requestif'] : $dyndns['requestif'] = $_POST['interface'];
		$dyndns['descr'] = $_POST['descr'];
		$dyndns['force'] = isset($_POST['force']);

		if($dyndns['username'] == "none")
			$dyndns['username'] = "";

		if (isset($id) && $a_dyndns[$id])
			$a_dyndns[$id] = $dyndns;
		else {
			$a_dyndns[] = $dyndns;
			$id = count($a_dyndns) - 1;
		}

		$dyndns['id'] = $id;
		//Probably overkill, but its better to be safe
		for($i = 0; $i < count($a_dyndns); $i++) {
			$a_dyndns[$i]['id'] = $i;
		}

		write_config();

		services_dyndns_configure_client($dyndns);

		header("Location: services_dyndns.php");
		exit;
	}
}

$pgtitle = array(gettext("Services"),gettext("Dynamic DNS client"));
include("head.inc");

?>


<body>
<?php include("fbegin.inc"); ?>

	<script type="text/javascript">
	//<![CDATA[
	function _onTypeChange(type){
		switch(type) {
			case "custom":
			case "custom-v6":
				document.getElementById("_resulttr").style.display = '';
				document.getElementById("_urltr").style.display = '';
				document.getElementById("_requestiftr").style.display = '';
				document.getElementById("_curloptions").style.display = '';
				document.getElementById("_hostnametr").style.display = 'none';
				document.getElementById("_mxtr").style.display = 'none';
				document.getElementById("_wildcardtr").style.display = 'none';
				document.getElementById("r53_zoneid").style.display='none';
				document.getElementById("r53_ttl").style.display='none';
				break;
			case "route53":
				document.getElementById("_resulttr").style.display = 'none';
				document.getElementById("_urltr").style.display = 'none';
				document.getElementById("_requestiftr").style.display = 'none';
				document.getElementById("_curloptions").style.display = 'none';
				document.getElementById("_hostnametr").style.display = '';
				document.getElementById("_mxtr").style.display = '';
				document.getElementById("_wildcardtr").style.display = '';
				document.getElementById("r53_zoneid").style.display='';
				document.getElementById("r53_ttl").style.display='';
				break;
			default:
				document.getElementById("_resulttr").style.display = 'none';
				document.getElementById("_urltr").style.display = 'none';
				document.getElementById("_requestiftr").style.display = 'none';
				document.getElementById("_curloptions").style.display = 'none';
				document.getElementById("_hostnametr").style.display = '';
				document.getElementById("_mxtr").style.display = '';
				document.getElementById("_wildcardtr").style.display = '';
				document.getElementById("r53_zoneid").style.display='none';
				document.getElementById("r53_ttl").style.display='none';
		}
	}
	//]]>
	</script>

	<section class="page-content-main">

		<div class="container-fluid">

			<div class="row">

				<?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
				<?php if (isset($savemsg)) print_info_box($savemsg); ?>

			    <section class="col-xs-12">

				<div class="content-box">

                        <form action="services_dyndns_edit.php" method="post" name="iform" id="iform">

				<div class="table-responsive">
					<table class="table table-striped table-sort">
					                <tr>
					                  <td colspan="2" valign="top" class="optsect_t">
									  <table border="0" cellspacing="0" cellpadding="0" width="100%" summary="title">
									  <tr><td class="optsect_s"><strong><?=gettext("Dynamic DNS client");?></strong></td></tr>
									  </table>
									  </td>
					                </tr>
					                <tr>
					                  <td width="22%" valign="top" class="vncell"><?=gettext("Disable");?></td>
									  <td width="78%" class="vtable">
									    <input name="enable" type="checkbox" id="enable" value="<?=gettext("yes");?>" <?php if ($pconfig['enable']) echo "checked=\"checked\""; ?> />
									  </td>
					                </tr>
					                <tr>
					                  <td width="22%" valign="top" class="vncellreq"><?=gettext("Service type");?></td>
					                  <td width="78%" class="vtable">
								<select name="type" class="formselect" id="type" onchange="_onTypeChange(this.options[this.selectedIndex].value);">
								<?php
									$types = services_dyndns_list();
									foreach ($types as $value => $type) {
								?>
					                      <option value="<?=$value;?>" <?php if ($value == $pconfig['type']) echo 'selected="selected"';?>><?=htmlspecialchars($type);?></option>
								<?php
									}
								?>
					                    </select></td>
									</tr>
									<tr>
									   <td width="22%" valign="top" class="vncellreq"><?=gettext("Interface to monitor");?></td>
									   <td width="78%" class="vtable">
									   <select name="interface" class="formselect" id="interface">
									<?php
										$iflist = get_configured_interface_with_descr();
										foreach ($iflist as $if => $ifdesc) {
											echo "<option value=\"{$if}\"";
											if ($pconfig['interface'] == $if)
												echo "selected=\"selected\"";
											echo ">{$ifdesc}</option>\n";
										}
										unset($iflist);
										$grouplist = return_gateway_groups_array();
										foreach ($grouplist as $name => $group) {
											echo "<option value=\"{$name}\"";
											if ($pconfig['interface'] == $name)
												echo "selected=\"selected\"";
											echo ">GW Group {$name}</option>\n";
										}
										unset($grouplist);
									?>
										</select>
										</td>
									</tr>
									<tr id="_requestiftr">
										<td width="22%" valign="top" class="vncellreq"><?=gettext("Interface to send update from");?></td>
										<td width="78%" class="vtable">
										<select name="requestif" class="formselect" id="requestif">
									<?php
										$iflist = get_configured_interface_with_descr();
										foreach ($iflist as $if => $ifdesc) {
											echo "<option value=\"{$if}\"";
											if ($pconfig['requestif'] == $if)
												echo "selected=\"selected\"";
											echo ">{$ifdesc}</option>\n";
										}
										unset($iflist);
										$grouplist = return_gateway_groups_array();
										foreach ($grouplist as $name => $group) {
											echo "<option value=\"{$name}\"";
											if ($pconfig['requestif'] == $name)
												echo "selected=\"selected\"";
											echo ">GW Group {$name}</option>\n";
										}
										unset($grouplist);
									?>
										</select>
										<br /><?= gettext("Note: This is almost always the same as the Interface to Monitor.");?>
										</td>
									</tr>
					                <tr id="_hostnametr">
					                  <td width="22%" valign="top" class="vncellreq"><?=gettext("Hostname");?></td>
					                  <td width="78%" class="vtable">
					                    <input name="host" type="text" class="formfld unknown" id="host" size="30" value="<?=htmlspecialchars($pconfig['host']);?>" />
					                    <br />
									    <span class="vexpl">
									    <span class="red"><strong><?=gettext("Note:");?><br /></strong>
									    </span>
										<?=gettext("Enter the complete host/domain name.  example:  myhost.dyndns.org");?><br />
										<?=gettext("For he.net tunnelbroker, enter your tunnel ID");?>
									    </span>
							          </td>
									</tr>
					                <tr id="_mxtr">
					                  <td width="22%" valign="top" class="vncell"><?=gettext("MX"); ?></td>
					                  <td width="78%" class="vtable">
					                    <input name="mx" type="text" class="formfld unknown" id="mx" size="30" value="<?=htmlspecialchars($pconfig['mx']);?>" />
					                    <br />
										<?=gettext("Note: With a dynamic DNS service you can only use a hostname, not an IP address.");?>
										<br />
					                    <?=gettext("Set this option only if you need a special MX record. Not".
					                   " all services support this.");?></td>
									</tr>
					                <tr id="_wildcardtr">
					                  <td width="22%" valign="top" class="vncell"><?=gettext("Wildcards"); ?></td>
					                  <td width="78%" class="vtable">
					                    <input name="wildcard" type="checkbox" id="wildcard" value="yes" <?php if ($pconfig['wildcard']) echo "checked=\"checked\""; ?> />
					                    <?=gettext("Enable ");?><?=gettext("Wildcard"); ?></td>
									</tr>
					                <tr id="_verboselogtr">
					                  <td width="22%" valign="top" class="vncell"><?=gettext("Verbose logging"); ?></td>
					                  <td width="78%" class="vtable">
					                    <input name="verboselog" type="checkbox" id="verboselog" value="yes" <?php if ($pconfig['verboselog']) echo "checked=\"checked\""; ?> />
					                    <?=gettext("Enable ");?><?=gettext("verbose logging"); ?></td>
									</tr>
									<tr id="_curloptions">
					                  <td width="22%" valign="top" class="vncell"><?=gettext("CURL options"); ?></td>
					                  <td width="78%" class="vtable">
					                    <input name="curl_ipresolve_v4" type="checkbox" id="curl_ipresolve_v4" value="yes" <?php if ($pconfig['curl_ipresolve_v4']) echo "checked=\"checked\""; ?> />
					                    <?=gettext("Force IPv4 resolving"); ?><br />
										<input name="curl_ssl_verifypeer" type="checkbox" id="curl_ssl_verifypeer" value="yes" <?php if ($pconfig['curl_ssl_verifypeer']) echo "checked=\"checked\""; ?> />
					                    <?=gettext("Verify SSL peer"); ?>
									  </td>
									</tr>
					                <tr id="_usernametr">
					                  <td width="22%" valign="top" class="vncellreq"><?=gettext("Username");?></td>
					                  <td width="78%" class="vtable">
					                    <input name="username" type="text" class="formfld user" id="username" size="20" value="<?=htmlspecialchars($pconfig['username']);?>" />
					                    <br /><?= gettext("Username is required for all types except Namecheap, FreeDNS and Custom Entries.");?>
							    <br /><?= gettext("Route 53: Enter your Access Key ID.");?>
							    <br /><?= gettext("Duck DNS: Enter your Token.");?>
							    <br /><?= gettext("For Custom Entries, Username and Password represent HTTP Authentication username and passwords.");?>
					                  </td>
					                </tr>
					                <tr>
					                  <td width="22%" valign="top" class="vncellreq"><?=gettext("Password");?></td>
					                  <td width="78%" class="vtable">
					                    <input name="password" type="password" class="formfld pwd" id="password" size="20" value="<?=htmlspecialchars($pconfig['password']);?>" />
					                    <br />
					                    <?=gettext("FreeDNS (freedns.afraid.org): Enter your \"Authentication Token\" provided by FreeDNS.");?>
							    <br /><?= gettext("Route 53: Enter your Secret Access Key.");?>
							    <br /><?= gettext("Duck DNS: Leave blank.");?>
					                  </td>
					                </tr>

					                <tr id="r53_zoneid" style="display:none">
					                  <td width="22%" valign="top" class="vncellreq"><?=gettext("Zone ID");?></td>
					                  <td width="78%" class="vtable">
					                    <input name="zoneid" type="text" class="formfld user" id="zoneid" size="20" value="<?=htmlspecialchars($pconfig['zoneid']);?>" />
					                    <br /><?= gettext("Enter Zone ID that you received when you created your domain in Route 53.");?>
					                  </td>
					                </tr>
					                <tr id="_urltr">
					                  <td width="22%" valign="top" class="vncell"><?=gettext("Update URL");?></td>
					                  <td width="78%" class="vtable">
					                    <input name="updateurl" type="text" class="formfld unknown" id="updateurl" size="60" value="<?=htmlspecialchars($pconfig['updateurl']);?>" />
					                    <br /><?= gettext("This is the only field required by for Custom Dynamic DNS, and is only used by Custom Entries.");?>
								<br />
								<?= gettext("If you need the new IP to be included in the request, put %IP% in its place.");?>
					                  </td>
					                </tr>
							<tr id="_resulttr">
					                  <td width="22%" valign="top" class="vncell"><?=gettext("Result Match");?></td>
					                  <td width="78%" class="vtable">
					                    <textarea name="resultmatch" class="formpre" id="resultmatch" cols="65" rows="7"><?=htmlspecialchars($pconfig['resultmatch']);?></textarea>
					                    <br /><?= gettext("This field is only used by Custom Dynamic DNS Entries.");?>
								<br />
								<?= gettext("This field should be identical to what your dynamic DNS Provider will return if the update succeeds, leave it blank to disable checking of returned results.");?>
								<br />
								<?= gettext("If you need the new IP to be included in the request, put %IP% in its place.");?>
								<br />
								<?= gettext("If you need to include multiple possible values, separate them with a |.  If your provider includes a |, escape it with \\|");?>
								<br />
								<?= gettext("Tabs (\\t), newlines (\\n) and carriage returns (\\r) at the beginning or end of the returned results are removed before comparison.");?>
					                  </td>
					                </tr>

					                <tr id="r53_ttl" style="display:none">
					                  <td width="22%" valign="top" class="vncellreq"><?=gettext("TTL");?></td>
					                  <td width="78%" class="vtable">
					                    <input name="ttl" type="text" class="formfld user" id="ttl" size="20" value="<?=htmlspecialchars($pconfig['ttl']);?>" />
					                    <br /><?= gettext("Choose TTL for your dns record.");?>
					                  </td>
					                </tr>


					                <tr>
					                  <td width="22%" valign="top" class="vncell"><?=gettext("Description");?></td>
					                  <td width="78%" class="vtable">
					                    <input name="descr" type="text" class="formfld unknown" id="descr" size="60" value="<?=htmlspecialchars($pconfig['descr']);?>" />
					                  </td>
					                </tr>
					                <tr>
					                  <td width="22%" valign="top">&nbsp;</td>
					                  <td width="78%">
					                    <input name="Submit" type="submit" class="btn btn-primary" value="<?=gettext("Save");?>" onclick="enable_change(true)" />
								<?php if (isset($id) && $a_dyndns[$id]): ?>
								<input name="id" type="hidden" value="<?=htmlspecialchars($id);?>" />
								<input name="force" type="submit" class="btn btn-primary" value="<?=gettext("Save & Force Update");?>" onclick="enable_change(true)" />
								<?php endif; ?>
								<a href="services_dyndns.php" class="btn btn-default"><?=gettext("Cancel");?></a>
					                  </td>
					                </tr>
					                <tr>
					                  <td width="22%" valign="top">&nbsp;</td>
					                  <td width="78%"><span class="vexpl"><span class="red"><strong><?=gettext("Note:");?><br />
					                    </strong></span><?php printf(gettext("You must configure a DNS server in %sSystem:
					                    General setup%s or allow the DNS server list to be overridden
					                    by DHCP/PPP on WAN for dynamic DNS updates to work."),'<a href="system_general.php">','</a>');?></span></td>
					                </tr>
					              </table>
				</div>
                        </form>
				</div>
			    </section>
			</div>
		</div>
	</section>

<script type="text/javascript">
//<![CDATA[
_onTypeChange("<?php echo $pconfig['type']; ?>");
//]]>
</script>
<?php include("foot.inc"); ?>
