<?php

/*
	Copyright (C) 2014-2015 Deciso B.V.
	Copyright (C) 2010 Seth Mos <seth.mos@dds.nl>.
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
require_once("services.inc");
require_once("interfaces.inc");

$referer = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/system_gateways.php');

$a_gateways = return_gateways_array(true, false, true);
$a_gateways_arr = array();
foreach ($a_gateways as $gw) {
    $a_gateways_arr[] = $gw;
}
$a_gateways = $a_gateways_arr;

if (!is_array($config['gateways'])) {
    $config['gateways'] = array();
}

if (!is_array($config['gateways']['gateway_item'])) {
    $config['gateways']['gateway_item'] = array();
}

$a_gateway_item = &$config['gateways']['gateway_item'];
$apinger_default = return_apinger_defaults();

if (is_numericint($_GET['id'])) {
    $id = $_GET['id'];
}
if (isset($_POST['id']) && is_numericint($_POST['id'])) {
    $id = $_POST['id'];
}

if (isset($_GET['dup']) && is_numericint($_GET['dup'])) {
    $id = $_GET['dup'];
}

if (isset($id) && $a_gateways[$id]) {
    $pconfig = array();
    $pconfig['name'] = $a_gateways[$id]['name'];
    $pconfig['weight'] = $a_gateways[$id]['weight'];
    $pconfig['interval'] = $a_gateways[$id]['interval'];
    $pconfig['avg_delay_samples'] = $a_gateways[$id]['avg_delay_samples'];
    $pconfig['avg_delay_samples_calculated'] = isset($a_gateways[$id]['avg_delay_samples_calculated']);
    $pconfig['avg_loss_samples'] = $a_gateways[$id]['avg_loss_samples'];
    $pconfig['avg_loss_samples_calculated'] = isset($a_gateways[$id]['avg_loss_samples_calculated']);
    $pconfig['avg_loss_delay_samples'] = $a_gateways[$id]['avg_loss_delay_samples'];
    $pconfig['avg_loss_delay_samples_calculated'] = isset($a_gateways[$id]['avg_loss_delay_samples_calculated']);
    $pconfig['interface'] = $a_gateways[$id]['interface'];
    $pconfig['friendlyiface'] = $a_gateways[$id]['friendlyiface'];
    $pconfig['ipprotocol'] = $a_gateways[$id]['ipprotocol'];
    if (isset($a_gateways[$id]['dynamic'])) {
        $pconfig['dynamic'] = true;
    }
    $pconfig['gateway'] = $a_gateways[$id]['gateway'];
    $pconfig['defaultgw'] = isset($a_gateways[$id]['defaultgw']);
    $pconfig['force_down'] = isset($a_gateways[$id]['force_down']);
    $pconfig['latencylow'] = $a_gateways[$id]['latencylow'];
    $pconfig['latencyhigh'] = $a_gateways[$id]['latencyhigh'];
    $pconfig['losslow'] = $a_gateways[$id]['losslow'];
    $pconfig['losshigh'] = $a_gateways[$id]['losshigh'];
    $pconfig['down'] = $a_gateways[$id]['down'];
    $pconfig['monitor'] = $a_gateways[$id]['monitor'];
    $pconfig['monitor_disable'] = isset($a_gateways[$id]['monitor_disable']);
    $pconfig['descr'] = $a_gateways[$id]['descr'];
    $pconfig['attribute'] = $a_gateways[$id]['attribute'];
    $pconfig['disabled'] = isset($a_gateways[$id]['disabled']);
} else {
    $pconfig['monitor_disable'] = true;
}

if (isset($_GET['dup']) && is_numericint($_GET['dup'])) {
    unset($id);
    unset($pconfig['attribute']);
}

if (isset($id) && $a_gateways[$id]) {
    $realid = $a_gateways[$id]['attribute'];
}

if ($_POST) {
    unset($input_errors);

    /* input validation */
    $reqdfields = explode(" ", "name interface");
    $reqdfieldsn = array(gettext("Name"), gettext("Interface"));

    do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

    if (! isset($_POST['name'])) {
        $input_errors[] = "A valid gateway name must be specified.";
    }
    if (! is_validaliasname($_POST['name'])) {
        $input_errors[] = gettext("The gateway name must not contain invalid characters.");
    }
    /* skip system gateways which have been automatically added */
    if (($_POST['gateway'] && (!is_ipaddr($_POST['gateway'])) && ($_POST['attribute'] !== "system")) && ($_POST['gateway'] != "dynamic")) {
        $input_errors[] = gettext("A valid gateway IP address must be specified.");
    }

    if ($_POST['gateway'] && (is_ipaddr($_POST['gateway'])) && !$_REQUEST['isAjax']) {
        if (is_ipaddrv4($_POST['gateway'])) {
            $parent_ip = get_interface_ip($_POST['interface']);
            $parent_sn = get_interface_subnet($_POST['interface']);
            if (empty($parent_ip) || empty($parent_sn)) {
                $input_errors[] = gettext("Cannot add IPv4 Gateway Address because no IPv4 address could be found on the interface.");
            } else {
                $subnets = array(gen_subnet($parent_ip, $parent_sn) . "/" . $parent_sn);
                $vips = link_interface_to_vips($_POST['interface']);
                if (is_array($vips)) {
                    foreach ($vips as $vip) {
                        if (!is_ipaddrv4($vip['subnet'])) {
                            continue;
                        }
                        $subnets[] = gen_subnet($vip['subnet'], $vip['subnet_bits']) . "/" . $vip['subnet_bits'];
                    }
                }

                $found = false;
                foreach ($subnets as $subnet) {
                    if (ip_in_subnet($_POST['gateway'], $subnet)) {
                        $found = true;
                        break;
                    }
                }

                if ($found === false) {
                    $input_errors[] = sprintf(gettext("The gateway address %1\$s does not lie within one of the chosen interface's subnets."), $_POST['gateway']);
                }
            }
        } elseif (is_ipaddrv6($_POST['gateway'])) {
            /* do not do a subnet match on a link local address, it's valid */
            if (!is_linklocal($_POST['gateway'])) {
                $parent_ip = get_interface_ipv6($_POST['interface']);
                $parent_sn = get_interface_subnetv6($_POST['interface']);
                if (empty($parent_ip) || empty($parent_sn)) {
                    $input_errors[] = gettext("Cannot add IPv6 Gateway Address because no IPv6 address could be found on the interface.");
                } else {
                    $subnets = array(gen_subnetv6($parent_ip, $parent_sn) . "/" . $parent_sn);
                    $vips = link_interface_to_vips($_POST['interface']);
                    if (is_array($vips)) {
                        foreach ($vips as $vip) {
                            if (!is_ipaddrv6($vip['subnet'])) {
                                continue;
                            }
                            $subnets[] = gen_subnetv6($vip['subnet'], $vip['subnet_bits']) . "/" . $vip['subnet_bits'];
                        }
                    }

                    $found = false;
                    foreach ($subnets as $subnet) {
                        if (ip_in_subnet($_POST['gateway'], $subnet)) {
                            $found = true;
                            break;
                        }
                    }

                    if ($found === false) {
                        $input_errors[] = sprintf(gettext("The gateway address %1\$s does not lie within one of the chosen interface's subnets."), $_POST['gateway']);
                    }
                }
            }
        }

        if (!empty($config['interfaces'][$_POST['interface']]['ipaddr'])) {
            if (is_ipaddr($config['interfaces'][$_POST['interface']]['ipaddr']) && (empty($_POST['gateway']) || $_POST['gateway'] == "dynamic")) {
                $input_errors[] = gettext("Dynamic gateway values cannot be specified for interfaces with a static IPv4 configuration.");
            }
        }
        if (!empty($config['interfaces'][$_POST['interface']]['ipaddrv6'])) {
            if (is_ipaddr($config['interfaces'][$_POST['interface']]['ipaddrv6']) && (empty($_POST['gateway']) || $_POST['gateway'] == "dynamic")) {
                $input_errors[] = gettext("Dynamic gateway values cannot be specified for interfaces with a static IPv6 configuration.");
            }
        }
    }
    if (($_POST['monitor'] <> "") && !is_ipaddr($_POST['monitor']) && $_POST['monitor'] != "dynamic") {
        $input_errors[] = gettext("A valid monitor IP address must be specified.");
    }
    /* only allow correct IPv4 and IPv6 gateway addresses */
    if (($_POST['gateway'] <> "") && is_ipaddr($_POST['gateway']) && $_POST['gateway'] != "dynamic") {
        if (is_ipaddrv6($_POST['gateway']) && ($_POST['ipprotocol'] == "inet")) {
            $input_errors[] = gettext("The IPv6 gateway address '{$_POST['gateway']}' can not be used as a IPv4 gateway'.");
        }
        if (is_ipaddrv4($_POST['gateway']) && ($_POST['ipprotocol'] == "inet6")) {
            $input_errors[] = gettext("The IPv4 gateway address '{$_POST['gateway']}' can not be used as a IPv6 gateway'.");
        }
    }
    /* only allow correct IPv4 and IPv6 monitor addresses */
    if (($_POST['monitor'] <> "") && is_ipaddr($_POST['monitor']) && $_POST['monitor'] != "dynamic") {
        if (is_ipaddrv6($_POST['monitor']) && ($_POST['ipprotocol'] == "inet")) {
            $input_errors[] = gettext("The IPv6 monitor address '{$_POST['monitor']}' can not be used on a IPv4 gateway'.");
        }
        if (is_ipaddrv4($_POST['monitor']) && ($_POST['ipprotocol'] == "inet6")) {
            $input_errors[] = gettext("The IPv4 monitor address '{$_POST['monitor']}' can not be used on a IPv6 gateway'.");
        }
    }

    if (isset($_POST['name'])) {
        /* check for overlaps */
        foreach ($a_gateways as $gateway) {
            if (isset($id) && ($a_gateways[$id]) && ($a_gateways[$id] === $gateway)) {
                if ($gateway['name'] != $_POST['name']) {
                    $input_errors[] = gettext("Changing name on a gateway is not allowed.");
                }
                continue;
            }
            if ($_POST['name'] <> "") {
                if (($gateway['name'] <> "") && ($_POST['name'] == $gateway['name']) && ($gateway['attribute'] !== "system")) {
                    $input_errors[] = sprintf(gettext('The gateway name "%s" already exists.'), $_POST['name']);
                    break;
                }
            }
            if (is_ipaddr($_POST['gateway'])) {
                if (($gateway['gateway'] <> "") && ($_POST['gateway'] == $gateway['gateway']) && ($gateway['attribute'] !== "system")) {
                    $input_errors[] = sprintf(gettext('The gateway IP address "%s" already exists.'), $_POST['gateway']);
                    break;
                }
            }
            if (is_ipaddr($_POST['monitor'])) {
                if (($gateway['monitor'] <> "") && ($_POST['monitor'] == $gateway['monitor']) && ($gateway['attribute'] !== "system")) {
                    $input_errors[] = sprintf(gettext('The monitor IP address "%s" is already in use. You must choose a different monitor IP.'), $_POST['monitor']);
                    break;
                }
            }
        }
    }

    /* input validation of apinger advanced parameters */
    if ($_POST['latencylow']) {
        if (! is_numeric($_POST['latencylow'])) {
            $input_errors[] = gettext("The low latency threshold needs to be a numeric value.");
        } else {
            if ($_POST['latencylow'] < 1) {
                $input_errors[] = gettext("The low latency threshold needs to be positive.");
            }
        }
    }

    if ($_POST['latencyhigh']) {
        if (! is_numeric($_POST['latencyhigh'])) {
            $input_errors[] = gettext("The high latency threshold needs to be a numeric value.");
        } else {
            if ($_POST['latencyhigh'] < 1) {
                $input_errors[] = gettext("The high latency threshold needs to be positive.");
            }
        }
    }

    if ($_POST['losslow']) {
        if (! is_numeric($_POST['losslow'])) {
            $input_errors[] = gettext("The low Packet Loss threshold needs to be a numeric value.");
        } else {
            if ($_POST['losslow'] < 1) {
                $input_errors[] = gettext("The low Packet Loss threshold needs to be positive.");
            }
            if ($_POST['losslow'] >= 100) {
                $input_errors[] = gettext("The low Packet Loss threshold needs to be less than 100.");
            }
        }
    }

    if ($_POST['losshigh']) {
        if (! is_numeric($_POST['losshigh'])) {
            $input_errors[] = gettext("The high Packet Loss threshold needs to be a numeric value.");
        } else {
            if ($_POST['losshigh'] < 1) {
                $input_errors[] = gettext("The high Packet Loss threshold needs to be positive.");
            }
            if ($_POST['losshigh'] > 100) {
                $input_errors[] = gettext("The high Packet Loss threshold needs to be 100 or less.");
            }
        }
    }

    if (($_POST['latencylow']) && ($_POST['latencyhigh'])) {
        if ((is_numeric($_POST['latencylow'])) && (is_numeric($_POST['latencyhigh']))) {
            if (($_POST['latencylow'] > $_POST['latencyhigh'])) {
                $input_errors[] = gettext("The high latency threshold needs to be higher than the low latency threshold");
            }
        }
    } else {
        if ($_POST['latencylow']) {
            if (is_numeric($_POST['latencylow'])) {
                if ($_POST['latencylow'] > $apinger_default['latencyhigh']) {
                    $input_errors[] = gettext(sprintf("The low latency threshold needs to be less than the default high latency threshold (%d)", $apinger_default['latencyhigh']));
                }
            }
        }
        if ($_POST['latencyhigh']) {
            if (is_numeric($_POST['latencyhigh'])) {
                if ($_POST['latencyhigh'] < $apinger_default['latencylow']) {
                    $input_errors[] = gettext(sprintf("The high latency threshold needs to be higher than the default low latency threshold (%d)", $apinger_default['latencylow']));
                }
            }
        }
    }

    if (($_POST['losslow']) && ($_POST['losshigh'])) {
        if ((is_numeric($_POST['losslow'])) && (is_numeric($_POST['losshigh']))) {
            if ($_POST['losslow'] > $_POST['losshigh']) {
                $input_errors[] = gettext("The high Packet Loss threshold needs to be higher than the low Packet Loss threshold");
            }
        }
    } else {
        if ($_POST['losslow']) {
            if (is_numeric($_POST['losslow'])) {
                if ($_POST['losslow'] > $apinger_default['losshigh']) {
                    $input_errors[] = gettext(sprintf("The low Packet Loss threshold needs to be less than the default high Packet Loss threshold (%d)", $apinger_default['losshigh']));
                }
            }
        }
        if ($_POST['losshigh']) {
            if (is_numeric($_POST['losshigh'])) {
                if ($_POST['losshigh'] < $apinger_default['losslow']) {
                    $input_errors[] = gettext(sprintf("The high Packet Loss threshold needs to be higher than the default low Packet Loss threshold (%d)", $apinger_default['losslow']));
                }
            }
        }
    }

    if ($_POST['interval']) {
        if (! is_numeric($_POST['interval'])) {
            $input_errors[] = gettext("The probe interval needs to be a numeric value.");
        } else {
            if ($_POST['interval'] < 1) {
                $input_errors[] = gettext("The probe interval needs to be positive.");
            }
        }
    }

    if ($_POST['down']) {
        if (! is_numeric($_POST['down'])) {
            $input_errors[] = gettext("The down time setting needs to be a numeric value.");
        } else {
            if ($_POST['down'] < 1) {
                $input_errors[] = gettext("The down time setting needs to be positive.");
            }
        }
    }

    if (($_POST['interval']) && ($_POST['down'])) {
        if ((is_numeric($_POST['interval'])) && (is_numeric($_POST['down']))) {
            if ($_POST['interval'] > $_POST['down']) {
                $input_errors[] = gettext("The probe interval needs to be less than the down time setting.");
            }
        }
    } else {
        if ($_POST['interval']) {
            if (is_numeric($_POST['interval'])) {
                if ($_POST['interval'] > $apinger_default['down']) {
                    $input_errors[] = gettext(sprintf("The probe interval needs to be less than the default down time setting (%d)", $apinger_default['down']));
                }
            }
        }
        if ($_POST['down']) {
            if (is_numeric($_POST['down'])) {
                if ($_POST['down'] < $apinger_default['interval']) {
                    $input_errors[] = gettext(sprintf("The down time setting needs to be higher than the default probe interval (%d)", $apinger_default['interval']));
                }
            }
        }
    }

    if ($_POST['avg_delay_samples']) {
        if (! is_numeric($_POST['avg_delay_samples'])) {
            $input_errors[] = gettext("The average delay replies qty needs to be a numeric value.");
        } else {
            if ($_POST['avg_delay_samples'] < 1) {
                $input_errors[] = gettext("The average delay replies qty needs to be positive.");
            }
        }
    }

    if ($_POST['avg_loss_samples']) {
        if (! is_numeric($_POST['avg_loss_samples'])) {
            $input_errors[] = gettext("The average packet loss probes qty needs to be a numeric value.");
        } else {
            if ($_POST['avg_loss_samples'] < 1) {
                $input_errors[] = gettext("The average packet loss probes qty needs to be positive.");
            }
        }
    }

    if ($_POST['avg_loss_delay_samples']) {
        if (! is_numeric($_POST['avg_loss_delay_samples'])) {
            $input_errors[] = gettext("The lost probe delay needs to be a numeric value.");
        } else {
            if ($_POST['avg_loss_delay_samples'] < 1) {
                $input_errors[] = gettext("The lost probe delay needs to be positive.");
            }
        }
    }

    if (!$input_errors) {
        $reloadif = "";
        $gateway = array();

        if (empty($_POST['interface'])) {
            $gateway['interface'] = $pconfig['friendlyiface'];
        } else {
            $gateway['interface'] = $_POST['interface'];
        }
        if (is_ipaddr($_POST['gateway'])) {
            $gateway['gateway'] = $_POST['gateway'];
        } else {
            $gateway['gateway'] = "dynamic";
        }
        $gateway['name'] = $_POST['name'];
        $gateway['weight'] = $_POST['weight'];
        $gateway['ipprotocol'] = $_POST['ipprotocol'];
        $gateway['interval'] = $_POST['interval'];

        $gateway['avg_delay_samples'] = $_POST['avg_delay_samples'];
        if ($_POST['avg_delay_samples_calculated'] == "yes" || $_POST['avg_delay_samples_calculated'] == "on") {
            $gateway['avg_delay_samples_calculated'] = true;
        }

        $gateway['avg_loss_samples'] = $_POST['avg_loss_samples'];
        if ($_POST['avg_loss_samples_calculated'] == "yes" || $_POST['avg_loss_samples_calculated'] == "on") {
            $gateway['avg_loss_samples_calculated'] = true;
        }

        $gateway['avg_loss_delay_samples'] = $_POST['avg_loss_delay_samples'];
        if ($_POST['avg_loss_delay_samples_calculated'] == "yes" || $_POST['avg_loss_delay_samples_calculated'] == "on") {
            $gateway['avg_loss_delay_samples_calculated'] = true;
        }

        $gateway['descr'] = $_POST['descr'];
        if ($_POST['monitor_disable'] == "yes") {
            $gateway['monitor_disable'] = true;
        }
        if ($_POST['force_down'] == "yes") {
            $gateway['force_down'] = true;
        }
        if (is_ipaddr($_POST['monitor'])) {
            $gateway['monitor'] = $_POST['monitor'];
        }

        /* NOTE: If monitor ip is changed need to cleanup the old static route */
        if ($_POST['monitor'] != "dynamic" && !empty($a_gateway_item[$realid]) && is_ipaddr($a_gateway_item[$realid]['monitor']) &&
            $_POST['monitor'] != $a_gateway_item[$realid]['monitor'] && $gateway['gateway'] != $a_gateway_item[$realid]['monitor']) {
            if (is_ipaddrv4($a_gateway_item[$realid]['monitor'])) {
                mwexec("/sbin/route delete " . escapeshellarg($a_gateway_item[$realid]['monitor']));
            } else {
                mwexec("/sbin/route delete -inet6 " . escapeshellarg($a_gateway_item[$realid]['monitor']));
            }
        }

        if ($_POST['defaultgw'] == "yes" || $_POST['defaultgw'] == "on") {
            $i = 0;
            /* remove the default gateway bits for all gateways with the same address family */
            foreach ($a_gateway_item as $gw) {
                if ($gateway['ipprotocol'] == $gw['ipprotocol']) {
                    unset($config['gateways']['gateway_item'][$i]['defaultgw']);
                    if ($gw['interface'] != $_POST['interface'] && $gw['defaultgw']) {
                        $reloadif = $gw['interface'];
                    }
                }
                $i++;
            }
            $gateway['defaultgw'] = true;
        }

        if ($_POST['latencylow']) {
            $gateway['latencylow'] = $_POST['latencylow'];
        }
        if ($_POST['latencyhigh']) {
            $gateway['latencyhigh'] = $_POST['latencyhigh'];
        }
        if ($_POST['losslow']) {
            $gateway['losslow'] = $_POST['losslow'];
        }
        if ($_POST['losshigh']) {
            $gateway['losshigh'] = $_POST['losshigh'];
        }
        if ($_POST['down']) {
            $gateway['down'] = $_POST['down'];
        }

        if (isset($_POST['disabled'])) {
            $gateway['disabled'] = true;
        } else {
            unset($gateway['disabled']);
        }

        /* when saving the manual gateway we use the attribute which has the corresponding id */
        if (isset($realid) && $a_gateway_item[$realid]) {
            $a_gateway_item[$realid] = $gateway;
        } else {
            $a_gateway_item[] = $gateway;
        }

        mark_subsystem_dirty('staticroutes');

        write_config();

        if ($_REQUEST['isAjax']) {
            echo $_POST['name'];
            exit;
        } elseif (!empty($reloadif)) {
            configd_run("interface reconfigure {$reloadif}");
        }

        header("Location: system_gateways.php");
        exit;
    } else {
        if ($_REQUEST['isAjax']) {
            header("HTTP/1.0 500 Internal Server Error");
            header("Content-type: text/plain");
            foreach ($input_errors as $error) {
                echo("$error\n");
            }
            exit;
        }

        $pconfig = $_POST;
        if (empty($_POST['friendlyiface'])) {
            $pconfig['friendlyiface'] = $_POST['interface'];
        }
    }
}

$pgtitle = array(gettext('System'), gettext('Gateway'), gettext('Edit'));
$shortcut_section = "gateways";

include("head.inc");

?>

<body>
<?php include("fbegin.inc"); ?>
<script type="text/javascript">
//<![CDATA[
function show_advanced_gateway() {
	document.getElementById("showadvgatewaybox").innerHTML='';
	aodiv = document.getElementById('showgatewayadv');
	aodiv.style.display = "block";
}
function monitor_change() {
	document.iform.monitor.disabled = document.iform.monitor_disable.checked;
}

function interval_change(interval_obj) {
	valid_value(interval_obj, 1, 86400);
	calculate_state_change();

	calculated_change(document.iform.avg_delay_samples_calculated, document.iform.avg_delay_samples);
	calculated_change(document.iform.avg_loss_samples_calculated, document.iform.avg_loss_samples);
	calculated_change(document.iform.avg_loss_delay_samples_calculated, document.iform.avg_loss_delay_samples);
}

function samples_change(calculated_obj, samples_obj) {
	calculated_change(calculated_obj, samples_obj);
}

function calculated_change(calculated_obj, samples_obj) {
	switch (samples_obj.name) {

	case 'avg_delay_samples':
		// How many replies should be used to compute average delay
		// for controlling "delay" alarms.
		// Calculate a reasonable value based on gateway probe interval and RRD 1 minute average graph step size (60).
		if (calculated_obj.checked && (document.iform.interval.value > 0))
			samples_obj.value = 60 * (1/6) / Math.pow(document.iform.interval.value, 0.333);	// Calculate & Round to Integer
		valid_value(samples_obj, 1, 100);
		break;

	case 'avg_loss_samples':
		// How many probes should be used to compute average loss.
		// Calculate a reasonable value based on gateway probe interval and RRD 1 minute average graph step size (60).
		if (calculated_obj.checked && (document.iform.interval.value > 0))
			samples_obj.value = 60 / document.iform.interval.value;	// Calculate & Round to Integer
		valid_value(samples_obj, 1, 1000);
		break;

	case 'avg_loss_delay_samples':
		// The delay (in samples) after which loss is computed
		// without this delays larger than interval would be treated as loss.
		// Calculate a reasonable value based on gateway probe interval and RRD 1 minute average graph step size (60).
		if (calculated_obj.checked && (document.iform.interval.value > 0))
			samples_obj.value = 60 * (1/3) / document.iform.interval.value;	// Calculate & Round to Integer
		valid_value(samples_obj, 1, 200);
		break;
	default:
	}

	calculate_state_change();
}

function valid_value(object, min, max) {
	if (object.value) {
		object.value = Math.round(object.value);		// Round to integer
		if (object.value < min)  object.value = min;	// Min Value
		if (object.value > max)  object.value = max;	// Max Value
		if (isNaN(object.value)) object.value =  '';	// Empty Value
	}
}

function calculate_state_change() {
	if (document.iform.interval.value > 0) {
		document.iform.avg_delay_samples_calculated.disabled = false;
		document.iform.avg_loss_samples_calculated.disabled = false;
		document.iform.avg_loss_delay_samples_calculated.disabled = false;

		document.iform.avg_delay_samples.disabled = document.iform.avg_delay_samples_calculated.checked;
		document.iform.avg_loss_samples.disabled = document.iform.avg_loss_samples_calculated.checked;
		document.iform.avg_loss_delay_samples.disabled = document.iform.avg_loss_delay_samples_calculated.checked;
	}
	else {
		document.iform.avg_delay_samples_calculated.disabled = true;
		document.iform.avg_loss_samples_calculated.disabled = true;
		document.iform.avg_loss_delay_samples_calculated.disabled = true;
		document.iform.interval.value = '';

		document.iform.avg_delay_samples.disabled = false;
		document.iform.avg_loss_samples.disabled = false;
		document.iform.avg_loss_delay_samples.disabled = false;

		document.iform.avg_delay_samples_calculated.checked = false;
		document.iform.avg_loss_samples_calculated.checked = false;
		document.iform.avg_loss_delay_samples_calculated.checked = false;
	}
}

function enable_change() {
	document.iform.avg_delay_samples.disabled = false;
	document.iform.avg_loss_samples.disabled = false;
	document.iform.avg_loss_delay_samples.disabled = false;
}
//]]>
</script>

	<section class="page-content-main">
		<div class="container-fluid">
			<div class="row">

				<?php if (isset($input_errors) && count($input_errors) > 0) {
                    print_input_errors($input_errors);
} ?>
				<div id="inputerrors"></div>


			    <section class="col-xs-12">

				<div class="content-box">

					 <header class="content-box-head container-fluid">
				        <h3><?=gettext("Edit gateway");?></h3>
				    </header>

				    <div class="content-box-main col-xs-12">

						<form action="system_gateways_edit.php" method="post" name="iform" id="iform">
							<?php
                                /* If this is a system gateway we need this var */
                            if (($pconfig['attribute'] == "system") || is_numeric($pconfig['attribute'])) {
                                echo "<input type='hidden' name='attribute' id='attribute' value=\"" . htmlspecialchars($pconfig['attribute']) . "\" />\n";
                            }
                                echo "<input type='hidden' name='friendlyiface' id='friendlyiface' value=\"" . htmlspecialchars($pconfig['friendlyiface']) . "\" />\n";
                                ?>

			                        <table class="table table-striped table-sort">
										<tr>
											<td width="22%" valign="top" class="vncellreq"><?=gettext("Disabled");?></td>
											<td width="78%" class="vtable">
												<input name="disabled" type="checkbox" id="disabled" value="yes" <?php if ($pconfig['disabled']) {
                                                    echo "checked=\"checked\"";
} ?> />
												<strong><?=gettext("Disable this gateway");?></strong><br />
												<span class="vexpl"><?=gettext("Set this option to disable this gateway without removing it from the list.");?></span>
											</td>
										</tr>
										<tr>
											<td width="22%" valign="top" class="vncellreq"><?=gettext("Interface"); ?></td>
											<td width="78%" class="vtable">
												<select name='interface' class="selectpicker" data-style="btn-default" data-live-search="true">
												<?php
                                                    $interfaces = get_configured_interface_with_descr(false, true);
                                                foreach ($interfaces as $iface => $ifacename) {
                                                    echo "<option value=\"{$iface}\"";
                                                    if ($iface == $pconfig['friendlyiface']) {
                                                        echo " selected='selected'";
                                                    }
                                                    echo ">" . htmlspecialchars($ifacename) . "</option>";
                                                }
                                                ?>
												</select><br />
												<span class="vexpl"><?=gettext("Choose which interface this gateway applies to."); ?></span>
											</td>
										</tr>
										<tr>
											<td width="22%" valign="top" class="vncellreq"><?=gettext("Address Family"); ?></td>
											<td width="78%" class="vtable">
												<select name='ipprotocol' class="selectpicker" data-style="btn-default" >
												<?php
                                                    $options = array("inet" => "IPv4", "inet6" => "IPv6");
                                                foreach ($options as $name => $string) {
                                                    echo "<option value=\"{$name}\"";
                                                    if ($name == $pconfig['ipprotocol']) {
                                                        echo " selected='selected'";
                                                    }
                                                    echo ">" . htmlspecialchars($string) . "</option>\n";
                                                }
                                                ?>
												</select><br />
												<span class="vexpl"><?=gettext("Choose the Internet Protocol this gateway uses."); ?></span>
											</td>
										</tr>
										<tr>
											<td width="22%" valign="top" class="vncellreq"><?=gettext("Name"); ?></td>
											<td width="78%" class="vtable">
												<input name="name" type="text" class="formfld unknown" id="name" size="20" value="<?=htmlspecialchars($pconfig['name']);?>" />
												<br /><span class="vexpl"><?=gettext("Gateway name"); ?></span>
											</td>
										</tr>
										<tr>
											<td width="22%" valign="top" class="vncellreq"><?=gettext("Gateway"); ?></td>
											<td width="78%" class="vtable">
												<input name="gateway" type="text" class="formfld host" id="gateway" size="28" value="<?php if ($pconfig['dynamic']) {
                                                    echo "dynamic";

} else {
    echo htmlspecialchars($pconfig['gateway']);
} ?>" />
												<br /><span class="vexpl"><?=gettext("Gateway IP address"); ?></span>
											</td>
										</tr>
										<tr>
											<td width="22%" valign="top" class="vncell"><?=gettext("Default Gateway"); ?></td>
											<td width="78%" class="vtable">
												<input name="defaultgw" type="checkbox" id="defaultgw" value="yes" <?php if ($pconfig['defaultgw'] == true) {
                                                    echo "checked=\"checked\"";
} ?> />
												<strong><?=gettext("Default Gateway"); ?></strong><br />
												<?=gettext("This will select the above gateway as the default gateway"); ?>
											</td>
										</tr>
										<tr>
											<td width="22%" valign="top" class="vncell"><?=gettext("Disable Gateway Monitoring"); ?></td>
											<td width="78%" class="vtable">
												<input name="monitor_disable" type="checkbox" id="monitor_disable" value="yes" <?php if ($pconfig['monitor_disable'] == true) {
                                                    echo "checked=\"checked\"";
} ?> onclick="monitor_change()" />
												<strong><?=gettext("Disable Gateway Monitoring"); ?></strong><br />
												<?=gettext("This will consider this gateway as always being up"); ?>
											</td>
										</tr>
										<tr>
											<td width="22%" valign="top" class="vncell"><?=gettext("Monitor IP"); ?></td>
											<td width="78%" class="vtable">
												<?php
                                                if ($pconfig['gateway'] == $pconfig['monitor']) {
                                                    $monitor = "";
                                                } else {
                                                    $monitor = htmlspecialchars($pconfig['monitor']);
                                                }
                                                ?>
												<input name="monitor" type="text" id="monitor" value="<?php echo htmlspecialchars($monitor); ?>" size="28" />
												<strong><?=gettext("Alternative monitor IP"); ?></strong> <br />
												<?=gettext("Enter an alternative address here to be used to monitor the link. This is used for the " .
                                                "quality RRD graphs as well as the load balancer entries. Use this if the gateway does not respond " .
                                                "to ICMP echo requests (pings)"); ?>.
												<br />
											</td>
										</tr>
										<tr>
											<td width="22%" valign="top" class="vncell"><?=gettext("Mark Gateway as Down"); ?></td>
											<td width="78%" class="vtable">
												<input name="force_down" type="checkbox" id="force_down" value="yes" <?php if ($pconfig['force_down'] == true) {
                                                    echo "checked=\"checked\"";
} ?> />
												<strong><?=gettext("Mark Gateway as Down"); ?></strong><br />
												<?=gettext("This will force this gateway to be considered Down"); ?>
											</td>
										</tr>
										<tr>
											<td width="22%" valign="top" class="vncell"><?=gettext("Advanced");?></td>
											<td width="78%" class="vtable">
												<?php $showbutton = (!empty($pconfig['latencylow']) || !empty($pconfig['latencyhigh']) || !empty($pconfig['losslow']) || !empty($pconfig['losshigh']) || (isset($pconfig['weight']) && $pconfig['weight'] > 1) || (isset($pconfig['interval']) && ($pconfig['interval'] > $apinger_default['interval'])) || (isset($pconfig['down']) && !($pconfig['down'] == $apinger_default['down']))); ?>
												<div id="showadvgatewaybox" <?php if ($showbutton) {
                                                    echo "style='display:none'";
} ?>>
													<input type="button" onclick="show_advanced_gateway()" value="Advanced" class="btn btn-default btn-xs"/><?=gettext(" - Show advanced option"); ?>
												</div>
												<div id="showgatewayadv" <?php if (!$showbutton) {
                                                    echo "style='display:none'";
} ?>>
													<table class="tabcont" width="100%" border="0" cellspacing="0" cellpadding="6" summary="advanced options">
														<tr>
															<td width="22%" valign="top" class="vncellreq"><?=gettext("Weight");?></td>
															<td width="78%" class="vtable">
																<select name='weight' class='formfldselect selectpicker' id='weight' data-width="auto">
																<?php
                                                                for ($i = 1; $i < 6; $i++) {
                                                                    $selected = "";
                                                                    if ($pconfig['weight'] == $i) {
                                                                        $selected = "selected='selected'";
                                                                    }
                                                                    echo "<option value='{$i}' {$selected} >{$i}</option>";
                                                                }
                                                                ?>
																</select>
																<br /><?=gettext("Weight for this gateway when used in a Gateway Group.");?> <br />
															</td>
														</tr>
														<tr>
															<td width="22%" valign="top" class="vncellreq"><?=gettext("Latency thresholds");?></td>
															<td width="78%" class="vtable">
																<?=gettext("From");?>
																<input name="latencylow" type="text" class="formfld unknown" id="latencylow" size="2"
																	value="<?=htmlspecialchars($pconfig['latencylow']);?>" />
																<?=gettext("To");?>
																<input name="latencyhigh" type="text" class="formfld unknown" id="latencyhigh" size="2"
																	value="<?=htmlspecialchars($pconfig['latencyhigh']);?>" />
																<br /><span class="vexpl"><?=gettext(sprintf("Low and high thresholds for latency in milliseconds. Default is %d/%d.", $apinger_default['latencylow'], $apinger_default['latencyhigh']));?></span>
															</td>
														</tr>
														<tr>
															<td width="22%" valign="top" class="vncellreq"><?=gettext("Packet Loss thresholds");?></td>
															<td width="78%" class="vtable">
																<?=gettext("From");?>
																<input name="losslow" type="text" class="formfld unknown" id="losslow" size="2"
																	value="<?=htmlspecialchars($pconfig['losslow']);?>" />
																<?=gettext("To");?>
																<input name="losshigh" type="text" class="formfld unknown" id="losshigh" size="2"
																	value="<?=htmlspecialchars($pconfig['losshigh']);?>" />
																<br /><span class="vexpl"><?=gettext(sprintf("Low and high thresholds for packet loss in %%. Default is %d/%d.", $apinger_default['losslow'], $apinger_default['losshigh']));?></span>
															</td>
														</tr>
														<tr>
															<td width="22%" valign="top" class="vncellreq"><?=gettext("Probe Interval");?></td>
															<td width="78%" class="vtable">
																<input name="interval" type="text" class="formfld unknown" id="interval" size="2"
																	value="<?=htmlspecialchars($pconfig['interval']);?>" onchange="interval_change(this)" />
																<br /><span class="vexpl">
																	<?=gettext(sprintf("How often that an ICMP probe will be sent in seconds. Default is %d.", $apinger_default['interval']));?><br /><br />
																	<?=gettext("NOTE: The quality graph is averaged over seconds, not intervals, so as the probe interval is increased the accuracy of the quality graph is decreased.");?>
																</span>
															</td>
														</tr>
														<tr>
															<td width="22%" valign="top" class="vncellreq"><?=gettext("Down");?></td>
															<td width="78%" class="vtable">
																<input name="down" type="text" class="formfld unknown" id="down" size="2"
																	value="<?=htmlspecialchars($pconfig['down']);?>" />
																<br /><span class="vexpl"><?=gettext(sprintf("The number of seconds of failed probes before the alarm will fire. Default is %d.", $apinger_default['down']));?></span>
															</td>
														</tr>
														<tr>
															<td width="22%" valign="top" class="vncellreq"><?=gettext("Average Delay Replies Qty");?></td>
															<td width="78%" class="vtable">
																<input name="avg_delay_samples" type="text" class="formfld unknown" id="avg_delay_samples" size="2"
																	value="<?=htmlspecialchars($pconfig['avg_delay_samples']);?>" onchange="samples_change(document.iform.avg_delay_samples_calculated, this)" />
																<input name="avg_delay_samples_calculated" type="checkbox" id="avg_delay_samples_calculated" value="yes" <?php if ($pconfig['avg_delay_samples_calculated'] == true) {
                                                                    echo "checked=\"checked\"";
} ?> onclick="calculated_change(this, document.iform.avg_delay_samples)" />
																	<?=gettext("Use calculated value."); ?>
																<br /><span class="vexpl"><?=gettext(sprintf("How many replies should be used to compute average delay for controlling \"delay\" alarms?  Default is %d.", $apinger_default['avg_delay_samples']));?><br /><br /></span>
															</td>
														</tr>
														<tr>
															<td width="22%" valign="top" class="vncellreq"><?=gettext("Average Packet Loss Probes Qty");?></td>
															<td width="78%" class="vtable">
																<input name="avg_loss_samples" type="text" class="formfld unknown" id="avg_loss_samples" size="2"
																	value="<?=htmlspecialchars($pconfig['avg_loss_samples']);?>" onchange="samples_change(document.iform.avg_loss_samples_calculated, this)" />
																<input name="avg_loss_samples_calculated" type="checkbox" id="avg_loss_samples_calculated" value="yes" <?php if ($pconfig['avg_loss_samples_calculated'] == true) {
                                                                    echo "checked=\"checked\"";
} ?> onclick="calculated_change(this, document.iform.avg_loss_samples)" />
																	<?=gettext("Use calculated value."); ?>
																<br /><span class="vexpl"><?=gettext(sprintf("How many probes should be useds to compute average packet loss?  Default is %d.", $apinger_default['avg_loss_samples']));?><br /><br /></span>
															</td>
														</tr>
														<tr>
															<td width="22%" valign="top" class="vncellreq"><?=gettext("Lost Probe Delay");?></td>
															<td width="78%" class="vtable">
																<input name="avg_loss_delay_samples" type="text" class="formfld unknown" id="avg_loss_delay_samples" size="2"
																	value="<?=htmlspecialchars($pconfig['avg_loss_delay_samples']);?>" onchange="samples_change(document.iform.avg_loss_delay_samples_calculated, this)" />
																<input name="avg_loss_delay_samples_calculated" type="checkbox" id="avg_loss_delay_samples_calculated" value="yes" <?php if ($pconfig['avg_loss_delay_samples_calculated'] == true) {
                                                                    echo "checked=\"checked\"";
} ?> onclick="calculated_change(this, document.iform.avg_loss_delay_samples)" />
																	<?=gettext("Use calculated value."); ?>
																<br /><span class="vexpl"><?=gettext(sprintf("The delay (in qty of probe samples) after which loss is computed.  Without this, delays longer than the probe interval would be treated as packet loss.  Default is %d.", $apinger_default['avg_loss_delay_samples']));?><br /><br /></span>
															</td>
														</tr>
														<tr>
															<td colspan="2">
																<?= gettext("The probe interval must be less than the down time, otherwise the gateway will seem to go down then come up again at the next probe."); ?><br /><br />
																<?= gettext("The down time defines the length of time before the gateway is marked as down, but the accuracy is controlled by the probe interval. For example, if your down time is 40 seconds but on a 30 second probe interval, only one probe would have to fail before the gateway is marked down at the 40 second mark. By default, the gateway is considered down after 10 seconds, and the probe interval is 1 second, so 10 probes would have to fail before the gateway is marked down."); ?><br />
															</td>
														</tr>
													</table>
												</div>
											</td>
										</tr>
										<tr>
											<td width="22%" valign="top" class="vncell"><?=gettext("Description"); ?></td>
											<td width="78%" class="vtable">
												<input name="descr" type="text" class="formfld unknown" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>" />
												<br /><span class="vexpl"><?=gettext("You may enter a description here for your reference (not parsed)"); ?>.</span>
											</td>
										</tr>
										<tr>
											<td width="22%" valign="top">&nbsp;</td>
											<td width="78%">
												<input name="Submit" type="submit" class="btn btn-primary" value="<?=gettext("Save");?>" onclick="enable_change()" />
												<input type="button" class="btn btn-default" value="<?=gettext("Cancel");
?>" onclick="window.location.href='<?=$referer;?>'" />
												<?php if (isset($id) && $a_gateways[$id]) :
?>
												<input name="id" type="hidden" value="<?=htmlspecialchars($id);?>" />
												<?php
endif; ?>
											</td>
										</tr>
									</table>
						</form>
				    </div>
				</div>
			    </section>
			</div>
		</div>
	</section>

<script type="text/javascript">
//<![CDATA[
monitor_change();
calculate_state_change();
//]]>
</script>
<?php include("foot.inc");
