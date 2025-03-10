<?php

/*
	Copyright (C) 2014-2015 Deciso B.V.
	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
	Copyright (C) 2010 Scott Ullrich
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
require_once("interfaces.inc");
require_once("pfsense-utils.inc");

$referer = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/system_routes.php');

if (!is_array($config['staticroutes'])) {
    $config['staticroutes'] = array();
}

if (!is_array($config['staticroutes']['route'])) {
    $config['staticroutes']['route'] = array();
}

$a_routes = &$config['staticroutes']['route'];
$a_gateways = return_gateways_array(true, true);

if (is_numericint($_GET['id'])) {
    $id = $_GET['id'];
}
if (isset($_POST['id']) && is_numericint($_POST['id'])) {
    $id = $_POST['id'];
}

if (isset($_GET['dup']) && is_numericint($_GET['dup'])) {
    $id = $_GET['dup'];
}

if (isset($id) && $a_routes[$id]) {
    list($pconfig['network'],$pconfig['network_subnet']) =
        explode('/', $a_routes[$id]['network']);
    $pconfig['gateway'] = $a_routes[$id]['gateway'];
    $pconfig['descr'] = $a_routes[$id]['descr'];
    $pconfig['disabled'] = isset($a_routes[$id]['disabled']);
}

if (isset($_GET['dup']) && is_numericint($_GET['dup'])) {
    unset($id);
}

if ($_POST) {
    global $aliastable;

    unset($input_errors);
    $pconfig = $_POST;

    /* input validation */
    $reqdfields = explode(" ", "network network_subnet gateway");
    $reqdfieldsn = explode(
        ",",
        gettext("Destination network") . "," .
        gettext("Destination network bit count") . "," .
        gettext("Gateway")
    );

    do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

    if (($_POST['network'] && !is_ipaddr($_POST['network']) && !is_alias($_POST['network']))) {
        $input_errors[] = gettext("A valid IPv4 or IPv6 destination network must be specified.");
    }
    if (($_POST['network_subnet'] && !is_numeric($_POST['network_subnet']))) {
        $input_errors[] = gettext("A valid destination network bit count must be specified.");
    }
    if (($_POST['gateway']) && is_ipaddr($_POST['network'])) {
        if (!isset($a_gateways[$_POST['gateway']])) {
            $input_errors[] = gettext("A valid gateway must be specified.");
        }
        if (!validate_address_family($_POST['network'], lookup_gateway_ip_by_name($_POST['gateway']))) {
            $input_errors[] = gettext("The gateway '{$a_gateways[$_POST['gateway']]['gateway']}' is a different Address Family as network '{$_POST['network']}'.");
        }
    }

    /* check for overlaps */
    $current_targets = get_staticroutes(true);
    $new_targets = array();
    if (is_ipaddrv6($_POST['network'])) {
        $osn = gen_subnetv6($_POST['network'], $_POST['network_subnet']) . "/" . $_POST['network_subnet'];
        $new_targets[] = $osn;
    }
    if (is_ipaddrv4($_POST['network'])) {
        if ($_POST['network_subnet'] > 32) {
            $input_errors[] = gettext("A IPv4 subnet can not be over 32 bits.");
        } else {
            $osn = gen_subnet($_POST['network'], $_POST['network_subnet']) . "/" . $_POST['network_subnet'];
            $new_targets[] = $osn;
        }
    } elseif (is_alias($_POST['network'])) {
        $osn = $_POST['network'];
        foreach (preg_split('/\s+/', $aliastable[$osn]) as $tgt) {
            if (is_ipaddrv4($tgt)) {
                $tgt .= "/32";
            }
            if (is_ipaddrv6($tgt)) {
                $tgt .= "/128";
            }
            if (!is_subnet($tgt)) {
                continue;
            }
            if (!is_subnetv6($tgt)) {
                continue;
            }
            $new_targets[] = $tgt;
        }
    }
    if (!isset($id)) {
        $id = count($a_routes);
    }
    $oroute = $a_routes[$id];
    $old_targets = array();
    if (!empty($oroute)) {
        if (is_alias($oroute['network'])) {
            foreach (filter_expand_alias_array($oroute['network']) as $tgt) {
                if (is_ipaddrv4($tgt)) {
                    $tgt .= "/32";
                } elseif (is_ipaddrv6($tgt)) {
                    $tgt .= "/128";
                }
                if (!is_subnet($tgt)) {
                    continue;
                }
                $old_targets[] = $tgt;
            }
        } else {
            $old_targets[] = $oroute['network'];
        }
    }

    $overlaps = array_intersect($current_targets, $new_targets);
    $overlaps = array_diff($overlaps, $old_targets);
    if (count($overlaps)) {
        $input_errors[] = gettext("A route to these destination networks already exists") . ": " . implode(", ", $overlaps);
    }

    if (is_array($config['interfaces'])) {
        foreach ($config['interfaces'] as $if) {
            if (is_ipaddrv4($_POST['network'])
                && isset($if['ipaddr']) && isset($if['subnet'])
                && is_ipaddrv4($if['ipaddr']) && is_numeric($if['subnet'])
                && ($_POST['network_subnet'] == $if['subnet'])
                && (gen_subnet($_POST['network'], $_POST['network_subnet']) == gen_subnet($if['ipaddr'], $if['subnet']))) {
                    $input_errors[] = sprintf(gettext("This network conflicts with address configured on interface %s."), $if['descr']);
            } elseif (is_ipaddrv6($_POST['network'])
                && isset($if['ipaddrv6']) && isset($if['subnetv6'])
                && is_ipaddrv6($if['ipaddrv6']) && is_numeric($if['subnetv6'])
                && ($_POST['network_subnet'] == $if['subnetv6'])
                && (gen_subnetv6($_POST['network'], $_POST['network_subnet']) == gen_subnetv6($if['ipaddrv6'], $if['subnetv6']))) {
                    $input_errors[] = sprintf(gettext("This network conflicts with address configured on interface %s."), $if['descr']);
            }
        }
    }

    if (!$input_errors) {
        $route = array();
        $route['network'] = $osn;
        $route['gateway'] = $_POST['gateway'];
        $route['descr'] = $_POST['descr'];
        if ($_POST['disabled']) {
            $route['disabled'] = true;
        } else {
            unset($route['disabled']);
        }

        if (file_exists('/tmp/.system_routes.apply')) {
            $toapplylist = unserialize(file_get_contents('/tmp/.system_routes.apply'));
        } else {
            $toapplylist = array();
        }
        $a_routes[$id] = $route;

        if (!empty($oroute)) {
            $delete_targets = array_diff($old_targets, $new_targets);
            if (count($delete_targets)) {
                foreach ($delete_targets as $dts) {
                    if (is_ipaddrv6($dts)) {
                        $family = '-inet6';
                    }
                    $toapplylist[] = "/sbin/route delete {$family} {$dts}";
                }
            }
        }
        file_put_contents('/tmp/.system_routes.apply', serialize($toapplylist));

        mark_subsystem_dirty('staticroutes');

        write_config();

        header("Location: system_routes.php");
        exit;
    }
}

$pgtitle = array(gettext('System'), gettext('Routes'), gettext('Edit'));
$shortcut_section = "routing";
include("head.inc");
?>

<body>
	<script type="text/javascript" src="/javascript/jquery.ipv4v6ify.js"></script>
	<script type="text/javascript" src="/javascript/autosuggest.js"></script>
	<script type="text/javascript" src="/javascript/suggestions.js"></script>

	<?php include("fbegin.inc"); ?>

	<section class="page-content-main">

		<div class="container-fluid">

			<div class="row">
				<?php if (isset($input_errors) && count($input_errors) > 0) {
                    print_input_errors($input_errors);
} ?>

			    <section class="col-xs-12">

				<div class="content-box">

                        <form action="system_routes_edit.php" method="post" name="iform" id="iform">

				<div class="table-responsive">
					<table class="table table-striped table-sort">
									<tr>
										<td colspan="2" valign="top" class="listtopic"><?=gettext("Edit route entry"); ?></td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncellreq"><?=gettext("Destination network"); ?></td>
										<td width="78%" class="vtable">
											<table>
												<tr>
													<td width="348px">
														<input name="network" type="text" class="formfldalias ipv4v6" id="network" size="20" value="<?=htmlspecialchars($pconfig['network']);?>" />
													</td>
													<td>
														<select name="network_subnet" class="selectpicker ipv4v6" id="network_subnet" data-width="auto">
														<?php for ($i = 128; $i >= 1; $i--) :
?>
															<option value="<?=$i;?>" <?php if ($i == $pconfig['network_subnet']) {
                                                                echo "selected=\"selected\"";
} ?>>
																<?=$i;?>
															</option>
														<?php
endfor; ?>
														</select>
													</td>
												</tr>
											</table>
											<br /><span class="vexpl"><?=gettext("Destination network for this static route"); ?></span>
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncellreq"><?=gettext("Gateway"); ?></td>
										<td width="78%" class="vtable">
											<select name="gateway" id="gateway" class="selectpicker">
											<?php
                                            foreach ($a_gateways as $gateway) {
                                                ?>
                                                <option value="<?=$gateway['name'];?>" <?php if ($gateway['name'] == $pconfig['gateway']) {
                                                    echo "selected=\"selected\"";
} ?>>
                                                    <?=htmlspecialchars($gateway['name']) . " - " . htmlspecialchars($gateway['gateway']);?>
													</option>
													<?php
                                            }
                                            ?>
											</select> <br />
											<div id='addgwbox'>
												<?=gettext("Choose which gateway this route applies to or");
?> <a onclick="show_add_gateway();" href="#"><?=gettext("add a new one.");?></a>
											</div>
											<div id='notebox'>
											</div>
											<div style="display:none" id="status">
											</div>
											<div style="display:none" id="addgateway">
															<table class="table table-striped"  summary="addgateway">
																<tbody>
																<tr>
																	<td colspan="2" valign="top" class="listtopic"><b><?=gettext("Add new gateway:"); ?></b></td>
																</tr>
																<tr>
																	<td width="22%"><?=gettext("Default gateway:"); ?></td><td with="78%"><input class="form-control" type="checkbox" id="defaultgw" name="defaultgw" /></td>
																</tr>
																<tr>
																	<td width="22%"><?=gettext("Interface:"); ?></td>
																	<td with="78%">
																		<select name="addinterfacegw" id="addinterfacegw" class="selectpicker">
																		<?php $gwifs = get_configured_interface_with_descr();
                                                                        foreach ($gwifs as $fif => $dif) {
                                                                            echo "<option value=\"{$fif}\">{$dif}</option>\n";
                                                                        }
                                                                        ?>
																		</select>
																	</td>
																</tr>
																<tr>
																	<td with="22%"><?=gettext("Gateway Name:"); ?></td><td with="78%"><input class="form-control" id="name" name="name" value="GW" /></td>
																</tr>
																<tr>
																	<td with="22%"><?=gettext("Gateway IP:"); ?></td><td with="78%"><input class="form-control" id="gatewayip" name="gatewayip" /></td>
																</tr>
																<tr>
																	<td with="22%"><?=gettext("Description:"); ?></td><td with="78%"><input class="form-control" id="gatewaydescr" name="gatewaydescr" /></td>
																</tr>
																<tr>
																	<td with="22%"></td>
																	<td with="78%">
																		<div id='savebuttondiv'>
																			<input type="hidden" name="addrtype" id="addrtype" value="IPv4" />
																			<input class="btn btn-primary" id="gwsave" type="button" value="<?=gettext("Save Gateway"); ?>" onclick='hide_add_gatewaysave();' />
																			<input class="btn btn-default" id="gwcancel" type="button" value="<?=gettext("Cancel"); ?>" onclick='hide_add_gateway();' />
																		</div>
																	</td>
																</tr>
																</tbody>
															</table>
											</div>
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell"><?=gettext("Disabled");?></td>
										<td width="78%" class="vtable">
											<input name="disabled" type="checkbox" id="disabled" value="yes" <?php if ($pconfig['disabled']) {
                                                echo "checked=\"checked\"";
} ?> />
											<strong><?=gettext("Disable this static route");?></strong><br />
											<span class="vexpl"><?=gettext("Set this option to disable this static route without removing it from the list.");?></span>
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell"><?=gettext("Description"); ?></td>
										<td width="78%" class="vtable">
											<input name="descr" type="text" class="formfld unknown" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>" />
											<br /><span class="vexpl"><?=gettext("You may enter a description here for your reference (not parsed)."); ?></span>
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top">&nbsp;</td>
										<td width="78%">
											<input id="save" name="Submit" type="submit" class="btn btn-primary" value="<?=gettext("Save");?>" />
											<input id="cancel" type="button" class="btn btn-default" value="<?=gettext("Cancel");
?>" onclick="window.location.href='<?=$referer;?>'" />
											<?php if (isset($id) && $a_routes[$id]) :
?>
												<input name="id" type="hidden" value="<?=htmlspecialchars($id);?>" />
											<?php
endif; ?>
										</td>
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
		var gatewayip;
		var name;
		function show_add_gateway() {
			document.getElementById("addgateway").style.display = '';
			document.getElementById("addgwbox").style.display = 'none';
			//document.getElementById("gateway").style.display = 'none';
			jQuery('#gateway').selectpicker('hide');
			document.getElementById("save").style.display = 'none';
			document.getElementById("cancel").style.display = 'none';
			document.getElementById("gwsave").style.display = '';
			document.getElementById("gwcancel").style.display = '';
			//jQuery('.selectpicker').selectpicker('refresh');
			jQuery('#notebox').html("");
		}
		function hide_add_gateway() {
			document.getElementById("addgateway").style.display = 'none';
			document.getElementById("addgwbox").style.display = '';
			//document.getElementById("gateway").style.display = '';
			jQuery('#gateway').selectpicker('show');
			document.getElementById("save").style.display = '';
			document.getElementById("cancel").style.display = '';
			document.getElementById("gwsave").style.display = '';
			document.getElementById("gwcancel").style.display = '';
			//jQuery('.selectpicker').selectpicker('refresh');
		}
		function hide_add_gatewaysave() {
			document.getElementById("addgateway").style.display = 'none';
			var iface = jQuery('#addinterfacegw').val();
			name = jQuery('#name').val();
			var descr = jQuery('#gatewaydescr').val();
			gatewayip = jQuery('#gatewayip').val();
			addrtype = jQuery('#addrtype').val();
			var defaultgw = '';
			if (jQuery('#defaultgw').checked)
				defaultgw = 'yes';
			var url = "system_gateways_edit.php";
			var pars = 'isAjax=true&defaultgw=' + escape(defaultgw) + '&interface=' + escape(iface) + '&name=' + escape(name) + '&descr=' + escape(descr) + '&gateway=' + escape(gatewayip) + '&type=' + escape(addrtype);
			jQuery.ajax(
				url,
			{
					type: 'post',
					data: pars,
					error: report_failure,
					success: save_callback
			});
		}
		function addOption(selectbox,text,value)
		{
			var optn = document.createElement("OPTION");
			optn.text = text;
			optn.value = value;
			selectbox.append(optn);
			selectbox.prop('selectedIndex',selectbox.children('option').length-1);
			jQuery('#notebox').html("<p><strong><?=gettext("NOTE:");?><\/strong> <?php printf(gettext("You can manage Gateways %shere%s."), "<a target='_blank' href='system_gateways.php'>", "<\/a>");?> <\/strong><\/p>");
			jQuery('.selectpicker').selectpicker('refresh');
		}
		function report_failure() {
			alert("<?=gettext("Sorry, we could not create your gateway at this time."); ?>");
			hide_add_gateway();
		}
		function save_callback(response) {
			if (response) {
				document.getElementById("addgateway").style.display = 'none';
				hide_add_gateway();
				var gwtext = escape(name) + " - " + gatewayip;
				addOption(jQuery('#gateway'), gwtext, name);
				jQuery('.selectpicker').selectpicker('refresh');
			} else {
				report_failure();
			}
		}
		var addressarray = <?= json_encode(get_alias_list(array("host", "network"))) ?>;
		var oTextbox1 = new AutoSuggestControl(document.getElementById("network"), new StateSuggestions(addressarray));
	//]]>
	</script>
<?php include("foot.inc");
