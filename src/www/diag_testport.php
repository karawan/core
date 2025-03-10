<?php

/*
	Copyright (C) 2014 Deciso B.V.
	Copyright (C) 2013 Jim Pingle <jimp@pfsense.org>
	Copyright (C) 2003-2005 Bob Zoller (bob@kludgebox.com) and Manuel Kasper <mk@neon1.net>.
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

$pgtitle = array(gettext("Diagnostics"), gettext("Test Port"));
require_once("guiconfig.inc");
require_once("system.inc");
require_once("interfaces.inc");

define('NC_TIMEOUT', 10);
$do_testport = false;

if ($_POST || $_REQUEST['host']) {
	unset($input_errors);

	/* input validation */
	$reqdfields = explode(" ", "host port");
	$reqdfieldsn = array(gettext("Host"),gettext("Port"));
	do_input_validation($_REQUEST, $reqdfields, $reqdfieldsn, $input_errors);

	if (!is_ipaddr($_REQUEST['host']) && !is_hostname($_REQUEST['host'])) {
		$input_errors[] = gettext("Please enter a valid IP or hostname.");
	}

	if (!is_port($_REQUEST['port'])) {
		$input_errors[] = gettext("Please enter a valid port number.");
	}

	if (($_REQUEST['srcport'] != "") && (!is_numeric($_REQUEST['srcport']) || !is_port($_REQUEST['srcport']))) {
		$input_errors[] = gettext("Please enter a valid source port number, or leave the field blank.");
	}

	if (is_ipaddrv4($_REQUEST['host']) && ($_REQUEST['ipprotocol'] == "ipv6")) {
		$input_errors[] = gettext("You cannot connect to an IPv4 address using IPv6.");
	}
	if (is_ipaddrv6($_REQUEST['host']) && ($_REQUEST['ipprotocol'] == "ipv4")) {
		$input_errors[] = gettext("You cannot connect to an IPv6 address using IPv4.");
	}

	if (!$input_errors) {
		$do_testport = true;
		$timeout = NC_TIMEOUT;
	}

	/* Save these request vars even if there were input errors. Then the fields are refilled for the user to correct. */
	$host = $_REQUEST['host'];
	$sourceip = $_REQUEST['sourceip'];
	$port = $_REQUEST['port'];
	$srcport = $_REQUEST['srcport'];
	$showtext = isset($_REQUEST['showtext']);
	$ipprotocol = $_REQUEST['ipprotocol'];
}

include("head.inc"); ?>
<body>
<?php include("fbegin.inc"); ?>




<section class="page-content-main">
	<div class="container-fluid">
		<div class="row">

			<section class="col-xs-12">

                <?php echo gettext("This page allows you to perform a simple TCP connection test to determine if a host is up and accepting connections on a given port. This test does not function for UDP since there is no way to reliably determine if a UDP port accepts connections in this manner."); ?>
<br /><br />
<?php echo gettext("No data is transmitted to the remote host during this test, it will only attempt to open a connection and optionally display the data sent back from the server."); ?>
<br /><br /><br />

				<?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>

                <div class="content-box">

                    <header class="content-box-head container-fluid">
				        <h3><?=gettext("Test Port"); ?></h3>
				    </header>

				    <div class="content-box-main ">
					    <form action="<?=$_SERVER['REQUEST_URI'];?>" method="post" name="iform" id="iform">
					    <div class="table-responsive">
				        <table class="table table-striped __nomb">
					        <tbody>
						        <tr>
						          <td><?=gettext("Host"); ?></td>
						          <td><input name="host" type="text" class="form-control" id="host" value="<?=htmlspecialchars($host);?>" /></td>
						        </tr>
						        <tr>
						          <td><?= gettext("Port"); ?></td>
						          <td><input name="port" type="text" class="form-control" id="port" size="10" value="<?=htmlspecialchars($port);?>" /></td>
						        </tr>
						        <tr>
						          <td><?= gettext("Source Port"); ?></td>
						          <td><input name="srcport" type="text" class="form-control" id="srcport" size="10" value="<?=htmlspecialchars($srcport);?>" />
										<p class="text-muted"><em><small><?php echo gettext("This should typically be left blank."); ?></small></em></p>
									  </td>
						        </tr>
						        <tr>
						          <td><?= gettext("Show Remote Text"); ?></td>
						          <td><input name="showtext" type="checkbox" id="showtext" <?php if ($showtext) echo "checked=\"checked\"" ?> />
										<p class="text-muted"><em><small><?php echo gettext("Shows the text given by the server when connecting to the port. Will take 10+ seconds to display if checked."); ?></small></em></p>
									  </td>
						        </tr>
						        <tr>
						          <td><?=gettext("Source Address"); ?></td>
						          <td><select name="sourceip" class="form-control">
											<option value="">Any</option>
										<?php   $sourceips = get_possible_traffic_source_addresses(true);
											foreach ($sourceips as $sip):
												$selected = "";
												if (!link_interface_to_bridge($sip['value']) && ($sip['value'] == $sourceip))
													$selected = "selected=\"selected\"";
										?>
											<option value="<?=$sip['value'];?>" <?=$selected;?>>
												<?=htmlspecialchars($sip['name']);?>
											</option>
											<?php endforeach; ?>
										</select>
									  </td>
						        </tr>
						        <tr>
						          <td><?=gettext("IP Protocol"); ?></td>
						          <td>
							          <select name="ipprotocol" class="form-control">
											<option value="any" <?php if ("any" == $ipprotocol) echo "selected=\"selected\""; ?>>
												Any
											</option>
											<option value="ipv4" <?php if ($ipprotocol == "ipv4") echo "selected=\"selected\""; ?>>
												<?=gettext("IPv4");?>
											</option>
											<option value="ipv6" <?php if ($ipprotocol == "ipv6") echo "selected=\"selected\""; ?>>
												<?=gettext("IPv6");?>
											</option>
										</select>
										<p class="text-muted"><em><small><?php echo gettext("If you force IPv4 or IPv6 and use a hostname that does not contain a result using that protocol, <br />it will result in an error. For example if you force IPv4 and use a hostname that only returns an AAAA IPv6 IP address, it will not work."); ?></small></em></p>
									  </td>
						        </tr>
						        <tr>
						          <td>&nbsp;</td>
						          <td><input name="Submit" type="submit" class="btn btn-primary" value="<?=gettext("Test"); ?>" /></td>
						        </tr>
					        </tbody>
					    </table>
					    </div>
					    </form>
				    </div>

				</div>
			</section>




			<?php if ($do_testport): ?>
			<section class="col-xs-12">
                <script type="text/javascript">
					//<![CDATA[
					window.onload=function(){
						document.getElementById("testportCaptured").wrap='off';
					}
					//]]>
				</script>

                <div class="content-box">

                    <header class="content-box-head container-fluid">
				        <h3><?=gettext("Port Test Results"); ?></h3>
				    </header>

					<div class="content-box-main col-xs-12">
						<pre>

<?php

							$result = "";
			$nc_base_cmd = "/usr/bin/nc";
			$nc_args = "-w " . escapeshellarg($timeout);
			if (!$showtext)
				$nc_args .= " -z ";
			if (!empty($srcport))
				$nc_args .= " -p " . escapeshellarg($srcport) . " ";

			/* Attempt to determine the interface address, if possible. Else try both. */
			if (is_ipaddrv4($host)) {
				$ifaddr = ($sourceip == "any") ? "" : get_interface_ip($sourceip);
				$nc_args .= " -4";
			} elseif (is_ipaddrv6($host)) {
				if ($sourceip == "any")
					$ifaddr = "";
				else if (is_linklocal($sourceip))
					$ifaddr = $sourceip;
				else
					$ifaddr = get_interface_ipv6($sourceip);
				$nc_args .= " -6";
			} else {
				switch ($ipprotocol) {
					case "ipv4":
						$ifaddr = get_interface_ip($sourceip);
						$nc_ipproto = " -4";
						break;
					case "ipv6":
						$ifaddr = (is_linklocal($sourceip) ? $sourceip : get_interface_ipv6($sourceip));
						$nc_ipproto = " -6";
						break;
					case "any":
						$ifaddr = get_interface_ip($sourceip);
						$nc_ipproto = (!empty($ifaddr)) ? " -4" : "";
						if (empty($ifaddr)) {
							$ifaddr = (is_linklocal($sourceip) ? $sourceip : get_interface_ipv6($sourceip));
							$nc_ipproto = (!empty($ifaddr)) ? " -6" : "";
						}
						break;
				}
				/* Netcat doesn't like it if we try to connect using a certain type of IP without specifying the family. */
				if (!empty($ifaddr)) {
					$nc_args .= $nc_ipproto;
				} elseif ($sourceip == "any") {
					switch ($ipprotocol) {
						case "ipv4":
							$nc_ipproto = " -4";
							break;
						case "ipv6":
							$nc_ipproto = " -6";
							break;
					}
					$nc_args .= $nc_ipproto;
				}
			}
			/* Only add on the interface IP if we managed to find one. */
			if (!empty($ifaddr)) {
				$nc_args .= " -s " . escapeshellarg($ifaddr) . " ";
				$scope = get_ll_scope($ifaddr);
				if (!empty($scope) && !strstr($host, "%"))
					$host .= "%{$scope}";
			}

			$nc_cmd = "{$nc_base_cmd} {$nc_args} " . escapeshellarg($host) . " " . escapeshellarg($port) . " 2>&1";
			exec($nc_cmd, $result, $retval);
			//echo "NC CMD: {$nc_cmd}\n\n";
			if (empty($result)) {
				if ($showtext)
					echo gettext("No output received, or connection failed. Try with \"Show Remote Text\" unchecked first.");
				else
					echo gettext("Connection failed (Refused/Timeout)");
			} else {
				if (is_array($result)) {
					foreach ($result as $resline) {
						echo htmlspecialchars($resline) . "\n";
					}
				} else {
					echo htmlspecialchars($result);
				}
			}

?>
						</pre>
					</div>
				</div>
			</section>
			<? endif; ?>
		</div>
	</div>
</section>

<?php include('foot.inc'); ?>
