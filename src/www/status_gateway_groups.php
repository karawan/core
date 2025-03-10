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

function lookup_gateway_monitor_ip_by_name($name) {

	$gateways_arr = return_gateways_array(false, true);
	if (!empty($gateways_arr[$name])) {
		$gateway = $gateways_arr[$name];
		if(!is_ipaddr($gateway['monitor']))
			return $gateway['gateway'];

		return $gateway['monitor'];
	}

	return (false);
}


if (!is_array($config['gateways'])) {
	$config['gateways'] = array();
}

if (!is_array($config['gateways']['gateway_group'])) {
	$config['gateways']['gateway_group'] = array();
}

$a_gateway_groups = &$config['gateways']['gateway_group'];
$changedesc = gettext("Gateway Groups") . ": ";

$gateways_status = return_gateways_status();

$pgtitle = array(gettext('System'), gettext('Gateways'), gettext('Group Status'));
$shortcut_section = "gateway-groups";
include("head.inc");

?>

<body>

<?php include("fbegin.inc"); ?>

	<section class="page-content-main">
		<div class="container-fluid">
			<div class="row">

				<?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>

			    <section class="col-xs-12">

				<?php
					        /* active tabs */
					        $tab_array = array();
					        $tab_array[] = array(gettext("Gateways"), false, "status_gateways.php");
					        $tab_array[] = array(gettext("Gateway Groups"), true, "status_gateway_groups.php");
					        display_top_tabs($tab_array);
					?>

				    <div class="tab-content content-box col-xs-12">


							<div class="responsive-table">


				              <table class="table table-striped table-sort">
				              <thead>
				                <tr>
				                  <td width="20%" class="listhdrr"><?=gettext("Group Name"); ?></td>
				                  <td width="50%" class="listhdrr"><?=gettext("Gateways"); ?></td>
				                  <td width="30%" class="listhdr"><?=gettext("Description"); ?></td>
							  </tr>
				              </thead>

				              <tbody>
							  <?php $i = 0; foreach ($a_gateway_groups as $gateway_group): ?>
				                <tr>
				                  <td class="listlr">
				                    <?php
							echo $gateway_group['name'];
							?>

				                  </td>
				                  <td class="listr">
							<table border='0'>
				                <?php
							/* process which priorities we have */
							$priorities = array();
							foreach($gateway_group['item'] as $item) {
								$itemsplit = explode("|", $item);
								$priorities[$itemsplit[1]] = true;
							}
							$priority_count = count($priorities);
							ksort($priorities);

							echo "<tr>";
							foreach($priorities as $number => $tier) {
								echo "<td width='120'>" . sprintf(gettext("Tier %s"), $number) . "</td>";
							}
							echo "</tr>\n";

							/* inverse gateway group to gateway priority */
							$priority_arr = array();
							foreach($gateway_group['item'] as $item) {
								$itemsplit = explode("|", $item);
								$priority_arr[$itemsplit[1]][] = $itemsplit[0];
							}
							ksort($priority_arr);
							$p = 1;
							foreach($priority_arr as $number => $tier) {
								/* for each priority process the gateways */
								foreach($tier as $member) {
									/* we always have $priority_count fields */
									echo "<tr>";
									$c = 1;
									while($c <= $priority_count) {
										$monitor = lookup_gateway_monitor_ip_by_name($member);
										if($p == $c) {
											$status = $gateways_status[$monitor]['status'];
											if (stristr($status, "down")) {
												$online = gettext("Offline");
												$bgcolor = "#F08080";  // lightcoral
											} elseif (stristr($status, "loss")) {
												$online = gettext("Warning, Packetloss");
												$bgcolor = "#F0E68C";  // khaki
											} elseif (stristr($status, "delay")) {
												$online = gettext("Warning, Latency");
												$bgcolor = "#F0E68C";  // khaki
											} elseif ($status == "none") {
												$online = gettext("Online");
												$bgcolor = "#90EE90";  // lightgreen
											} else {
												$online = gettext("Gathering data");
												$bgcolor = "#ADD8E6";  // lightblue
											}
											echo "<td bgcolor='$bgcolor'>&nbsp;". htmlspecialchars($member) .", $online&nbsp;</td>";
										} else {
											echo "<td>&nbsp;</td>";
										}
										$c++;
									}
									echo "</tr>\n";
								}
								$p++;
							}
						    ?>
							</table>
				                  </td>
				                  <td class="listbg">
				                    <?=htmlspecialchars($gateway_group['descr']);?>&nbsp;
				                  </td>
						</tr>
							  <?php $i++; endforeach; ?>
				              </tbody>
					</table>
					</div>
				</div>
			</section>
		</div>
	</div>
</section>

<?php include("foot.inc"); ?>
