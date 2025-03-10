<?php

/*
    Copyright (C) 2014-2015 Deciso B.V.
    Copyright (C) 2010 Ermal Luçi
    Copyright (C) 2008 Shrew Soft Inc.
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
require_once("auth.inc");

$ldap_urltypes = array(
    'TCP - Standard' => 389,
    'SSL - Encrypted' => 636
);

$auth_server_types = array(
    'ldap' => "LDAP",
    'radius' => "Radius"
);

$ldap_scopes = array(
    'one' => "One Level",
    'subtree' => "Entire Subtree"
);

$ldap_protvers = array(2, 3);

$ldap_templates = array(
    'open' => array(
        'desc' => "OpenLDAP",
        'attr_user' => "cn"
    ),
    'msad' => array(
        'desc' => "Microsoft AD",
        'attr_user' => "samAccountName"
    ),
    'edir' => array(
        'desc' => "Novell eDirectory",
        'attr_user' => "cn"
    )
);

$radius_srvcs = array(
    'both' => "Authentication and Accounting",
    'auth' => "Authentication",
    'acct' => "Accounting"
);



$pgtitle = array(gettext('System'), gettext('Users'), gettext('Servers'));
$shortcut_section = "authentication";

if (isset($_GET['id']) && is_numericint($_GET['id'])) {
    $id = $_GET['id'];
}
if (isset($_GET['act'])) {
    $act = $_GET['act'];
} else {
    $act = null;
}

if (!isset($config['system']['authserver'])) {
    $config['system']['authserver'] = array();
}

$a_servers = auth_get_authserver_list();
foreach ($a_servers as $servers) {
    $a_server[] = $servers;
}

if (!is_array($config['ca'])) {
        $config['ca'] = array();
}
$a_ca =& $config['ca'];



if ($act == "del") {
    if (!$a_server[$_GET['id']]) {
        redirectHeader("system_authservers.php");
        exit;
    }

    /* Remove server from main list. */
    $serverdeleted = $a_server[$_GET['id']]['name'];
    foreach ($config['system']['authserver'] as $k => $as) {
        if ($config['system']['authserver'][$k]['name'] == $serverdeleted) {
            unset($config['system']['authserver'][$k]);
        }
    }

    /* Remove server from temp list used later on this page. */
    unset($a_server[$_GET['id']]);

    $savemsg = gettext("Authentication Server")." {$serverdeleted} ".
                gettext("deleted")."<br />";
    write_config($savemsg);
}

if ($act == "edit") {
    if (isset($id) && $a_server[$id]) {
        $pconfig['type'] = $a_server[$id]['type'];
        $pconfig['name'] = $a_server[$id]['name'];

        if ($pconfig['type'] == "ldap") {
            $pconfig['ldap_caref'] = $a_server[$id]['ldap_caref'];
            $pconfig['ldap_host'] = $a_server[$id]['host'];
            $pconfig['ldap_port'] = $a_server[$id]['ldap_port'];
            $pconfig['ldap_urltype'] = $a_server[$id]['ldap_urltype'];
            $pconfig['ldap_protver'] = $a_server[$id]['ldap_protver'];
            $pconfig['ldap_scope'] = $a_server[$id]['ldap_scope'];
            $pconfig['ldap_basedn'] = $a_server[$id]['ldap_basedn'];
            $pconfig['ldap_authcn'] = $a_server[$id]['ldap_authcn'];
            $pconfig['ldap_extended_query'] = $a_server[$id]['ldap_extended_query'];
            $pconfig['ldap_binddn'] = $a_server[$id]['ldap_binddn'];
            $pconfig['ldap_bindpw'] = $a_server[$id]['ldap_bindpw'];
            $pconfig['ldap_attr_user'] = $a_server[$id]['ldap_attr_user'];
            if (empty($pconfig['ldap_binddn']) || empty($pconfig['ldap_bindpw'])) {
                $pconfig['ldap_anon'] = true;
            }
        }

        if ($pconfig['type'] == "radius") {
            $pconfig['radius_host'] = $a_server[$id]['host'];
            $pconfig['radius_auth_port'] = $a_server[$id]['radius_auth_port'];
            $pconfig['radius_acct_port'] = $a_server[$id]['radius_acct_port'];
            $pconfig['radius_secret'] = $a_server[$id]['radius_secret'];
            $pconfig['radius_timeout'] = $a_server[$id]['radius_timeout'];

            if ($pconfig['radius_auth_port'] &&
                $pconfig['radius_acct_port'] ) {
                $pconfig['radius_srvcs'] = "both";
            }

            if ($pconfig['radius_auth_port'] &&
                !$pconfig['radius_acct_port'] ) {
                $pconfig['radius_srvcs'] = "auth";
                $pconfig['radius_acct_port'] = 1813;
            }

            if (!$pconfig['radius_auth_port'] &&
                 $pconfig['radius_acct_port'] ) {
                $pconfig['radius_srvcs'] = "acct";
                $pconfig['radius_auth_port'] = 1812;
            }

        }
    }
}

if ($act == "new") {
    $pconfig['ldap_protver'] = 3;
    $pconfig['ldap_anon'] = true;
    $pconfig['radius_srvcs'] = "both";
    $pconfig['radius_auth_port'] = "1812";
    $pconfig['radius_acct_port'] = "1813";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_errors = array();
    $pconfig = $_POST;
    if (isset($_POST['id']) && is_numericint($_POST['id'])) {
        $id = $_POST['id'];
    } else {
        $id = null;
    }

    /* input validation */

    if ($pconfig['type'] == "ldap") {
        $reqdfields = explode(" ", "name type ldap_host ldap_port ".
                        "ldap_urltype ldap_protver ldap_scope ".
                        "ldap_attr_user ldapauthcontainers");
        $reqdfieldsn = array(
            gettext("Descriptive name"),
            gettext("Type"),
            gettext("Hostname or IP"),
            gettext("Port value"),
            gettext("Transport"),
            gettext("Protocol version"),
            gettext("Search level"),
            gettext("User naming Attribute"),
            gettext("Authentication container"));

        if (!$pconfig['ldap_anon']) {
            $reqdfields[] = "ldap_binddn";
            $reqdfields[] = "ldap_bindpw";
            $reqdfieldsn[] = gettext("Bind user DN");
            $reqdfieldsn[] = gettext("Bind Password");
        }
    }

    if ($pconfig['type'] == "radius") {
        $reqdfields = explode(" ", "name type radius_host radius_srvcs");
        $reqdfieldsn = array(
            gettext("Descriptive name"),
            gettext("Type"),
            gettext("Hostname or IP"),
            gettext("Services"));

        if ($pconfig['radisu_srvcs'] == "both" ||
            $pconfig['radisu_srvcs'] == "auth") {
            $reqdfields[] = "radius_auth_port";
            $reqdfieldsn[] = gettext("Authentication port value");
        }

        if ($pconfig['radisu_srvcs'] == "both" ||
            $pconfig['radisu_srvcs'] == "acct") {
            $reqdfields[] = "radius_acct_port";
            $reqdfieldsn[] = gettext("Accounting port value");
        }

        if ($id == null) {
            $reqdfields[] = "radius_secret";
            $reqdfieldsn[] = gettext("Shared Secret");
        }
    }

    do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

    if (preg_match("/[^a-zA-Z0-9\.\-_]/", $_POST['host'])) {
        $input_errors[] = gettext("The host name contains invalid characters.");
    }

    if (auth_get_authserver($pconfig['name']) && $id == null) {
        $input_errors[] = gettext("An authentication server with the same name already exists.");
    }

    if (($pconfig['type'] == "radius") && isset($_POST['radius_timeout']) && !empty($_POST['radius_timeout']) && (!is_numeric($_POST['radius_timeout']) || (is_numeric($_POST['radius_timeout']) && ($_POST['radius_timeout'] <= 0)))) {
        $input_errors[] = gettext("RADIUS Timeout value must be numeric and positive.");
    }

    if (count($input_errors) == 0) {
        $server = array();
        $server['refid'] = uniqid();
        if ($id != null && isset($a_server[$id])) {
            $server = $a_server[$id];
        }

        $server['type'] = $pconfig['type'];
        $server['name'] = $pconfig['name'];

        if ($server['type'] == "ldap") {
            if (!empty($pconfig['ldap_caref'])) {
                $server['ldap_caref'] = $pconfig['ldap_caref'];
            }
            $server['host'] = $pconfig['ldap_host'];
            $server['ldap_port'] = $pconfig['ldap_port'];
            $server['ldap_urltype'] = $pconfig['ldap_urltype'];
            $server['ldap_protver'] = $pconfig['ldap_protver'];
            $server['ldap_scope'] = $pconfig['ldap_scope'];
            $server['ldap_basedn'] = $pconfig['ldap_basedn'];
            $server['ldap_authcn'] = $pconfig['ldapauthcontainers'];
            $server['ldap_extended_query'] = $pconfig['ldap_extended_query'];
            $server['ldap_attr_user'] = $pconfig['ldap_attr_user'];
            if (!$pconfig['ldap_anon']) {
                $server['ldap_binddn'] = $pconfig['ldap_binddn'];
                $server['ldap_bindpw'] = $pconfig['ldap_bindpw'];
            } else {
                unset($server['ldap_binddn']);
                unset($server['ldap_bindpw']);
            }
        } elseif ($server['type'] == "radius") {
            $server['host'] = $pconfig['radius_host'];

            if ($pconfig['radius_secret']) {
                $server['radius_secret'] = $pconfig['radius_secret'];
            }

            if ($pconfig['radius_timeout']) {
                $server['radius_timeout'] = $pconfig['radius_timeout'];
            } else {
                $server['radius_timeout'] = 5;
            }

            if ($pconfig['radius_srvcs'] == "both") {
                $server['radius_auth_port'] = $pconfig['radius_auth_port'];
                $server['radius_acct_port'] = $pconfig['radius_acct_port'];
            }

            if ($pconfig['radius_srvcs'] == "auth") {
                $server['radius_auth_port'] = $pconfig['radius_auth_port'];
                unset($server['radius_acct_port']);
            }

            if ($pconfig['radius_srvcs'] == "acct") {
                $server['radius_acct_port'] = $pconfig['radius_acct_port'];
                unset($server['radius_auth_port']);
            }
        }

        if ($id != null && isset($config['system']['authserver'][$id])) {
            $config['system']['authserver'][$id] = $server;
        } else {
            $config['system']['authserver'][] = $server;
        }

        write_config();

        redirectHeader("system_authservers.php");
    } else {
        $act = "edit";
    }
}

include("head.inc");

$main_buttons = array(
    array('label'=>'Add server', 'href'=>'system_authservers.php?act=new'),
);

?>


<body>

<script type="text/javascript">
//<![CDATA[

function server_typechange(typ) {

	var idx = 0;
	if (!typ) {
		idx = document.getElementById("type").selectedIndex;
		typ = document.getElementById("type").options[idx].value;
	}

	switch (typ) {
		case "ldap":
			document.getElementById("ldap").style.display="";
			document.getElementById("radius").style.display="none";
			break;
		case "radius":
			document.getElementById("ldap").style.display="none";
			document.getElementById("radius").style.display="";
			break;
	}
}

function ldap_urlchange() {
    switch (document.getElementById("ldap_urltype").selectedIndex) {
<?php
    $index = 0;
foreach ($ldap_urltypes as $urltype => $urlport) :
?>
    case <?=$index;?>:
        document.getElementById("ldap_port").value = "<?=$urlport;?>";
        break;
<?php
    $index++;
endforeach;
?>
	}
}

function ldap_bindchange() {

	if (document.getElementById("ldap_anon").checked)
		document.getElementById("ldap_bind").style.display="none";
    else
		document.getElementById("ldap_bind").style.display="";
}

function ldap_tmplchange(){
    switch (document.getElementById("ldap_tmpltype").selectedIndex) {
<?php
    $index = 0;
foreach ($ldap_templates as $tmpldata) :
?>
    case <?=$index;?>:
        document.getElementById("ldap_attr_user").value = "<?=$tmpldata['attr_user'];?>";
        document.getElementById("ldap_attr_group").value = "<?=$tmpldata['attr_group'];?>";
        document.getElementById("ldap_attr_member").value = "<?=$tmpldata['attr_member'];?>";
        break;
<?php
    $index++;
endforeach;
?>
	}
}

function radius_srvcschange(){
    switch (document.getElementById("radius_srvcs").selectedIndex) {
		case 0: // both
			document.getElementById("radius_auth").style.display="";
			document.getElementById("radius_acct").style.display="";
			break;
		case 1: // authentication
			document.getElementById("radius_auth").style.display="";
			document.getElementById("radius_acct").style.display="none";
			break;
		case 2: // accounting
			document.getElementById("radius_auth").style.display="none";
			document.getElementById("radius_acct").style.display="";
			break;
	}
}

function select_clicked() {
	if (document.getElementById("ldap_port").value == '' ||
	    document.getElementById("ldap_host").value == '' ||
	    document.getElementById("ldap_scope").value == '' ||
	    document.getElementById("ldap_basedn").value == '' ) {
		alert("<?=gettext("Please fill the required values.");?>");
		return;
	}
	if (!document.getElementById("ldap_anon").checked) {
		if (document.getElementById("ldap_binddn").value == '' ||
		    document.getElementById("ldap_bindpw").value == '') {
				alert("<?=gettext("Please fill the bind username/password.");?>");
			return;
		}
	}
        var url = 'system_usermanager_settings_ldapacpicker.php?';
        url += 'port=' + document.getElementById("ldap_port").value;
        url += '&host=' + document.getElementById("ldap_host").value;
        url += '&scope=' + document.getElementById("ldap_scope").value;
        url += '&basedn=' + document.getElementById("ldap_basedn").value;
        url += '&binddn=' + document.getElementById("ldap_binddn").value;
        url += '&bindpw=' + document.getElementById("ldap_bindpw").value;
        url += '&urltype=' + document.getElementById("ldap_urltype").value;
        url += '&proto=' + document.getElementById("ldap_protver").value;
	url += '&authcn=' + document.getElementById("ldapauthcontainers").value;
	<?php if (count($a_ca) > 0) :
?>
		url += '&cert=' + document.getElementById("ldap_caref").value;
	<?php
else :
?>
		url += '&cert=';
	<?php
endif; ?>

        var oWin = window.open(url,"OPNsense","width=620,height=400,top=150,left=150");
        if (oWin==null || typeof(oWin)=="undefined")
			alert("<?=gettext('Popup blocker detected.  Action aborted.');?>");
}
//]]>
</script>

<?php include("fbegin.inc");?>

	<section class="page-content-main">
		<div class="container-fluid">
			<div class="row">

				<?php
                if (isset($input_errors) && count($input_errors) > 0) {
                    print_input_errors($input_errors);
                }
                if (isset($savemsg)) {
                    print_info_box($savemsg);
                }
                ?>

			    <section class="col-xs-12">

						<div class="tab-content content-box col-xs-12 table-responsive">

								<?php if ($act == "new" || $act == "edit") :
?>
								<form id="iform" name="iform" action="system_authservers.php" method="post">
									<table class="table table-striped table-sort">
											<tr>
												<td width="22%" valign="top" class="vncellreq"><?=gettext("Descriptive name");?></td>
												<td width="78%" class="vtable">
												<?php if (!isset($id)) :
?>
													<input name="name" type="text" class="formfld unknown" id="name" size="20" value="<?=htmlspecialchars($pconfig['name']);?>"/>
												<?php
else :
?>
					                                                                <strong><?=htmlspecialchars($pconfig['name']);?></strong>
					                                                                <input name='name' type='hidden' id='name' value="<?=htmlspecialchars($pconfig['name']);?>"/>
					                                                                <?php
endif; ?>
												</td>
											</tr>
											<tr>
												<td width="22%" valign="top" class="vncellreq"><?=gettext("Type");?></td>
												<td width="78%" class="vtable">
													<?php if (!isset($id)) :
?>
													<select name='type' id='type' class="formselect selectpicker" data-style="btn-default" onchange='server_typechange()'>
													<?php
                                                    foreach ($auth_server_types as $typename => $typedesc) :
                                                        $selected = "";
                                                        if ($pconfig['type'] == $typename) {
                                                            $selected = "selected=\"selected\"";
                                                        }
                                                    ?>
                                                    <option value="<?=$typename;
?>" <?=$selected;
?>><?=$typedesc;?></option>
													<?php
                                                    endforeach; ?>
													</select>
													<?php
else :
?>
													<strong><?=$auth_server_types[$pconfig['type']];?></strong>
													<input name='type' type='hidden' id='type' value="<?=htmlspecialchars($pconfig['type']);?>"/>
													<?php
endif; ?>
												</td>
											</tr>
										</table>

										<table class="table table-striped table-sort" id="ldap" style="display:none" summary="">

		                                    <thead>
		                                        <tr>
									<th colspan="2" class="listtopic"><?=gettext("LDAP Server Settings");?></th>
		                                        </tr>
		                                    </thead>

										<tbody>

											<tr>
												<td width="22%" valign="top" class="vncellreq"><?=gettext("Hostname or IP address");?></td>
												<td width="78%" class="vtable">
													<input name="ldap_host" type="text" class="formfld unknown" id="ldap_host" size="20" value="<?=htmlspecialchars($pconfig['ldap_host']);?>"/>
													<br /><?= gettext("NOTE: When using SSL, this hostname MUST match the Common Name (CN) of the LDAP server's SSL Certificate."); ?>
												</td>
											</tr>
											<tr>
												<td width="22%" valign="top" class="vncellreq"><?=gettext("Port value");?></td>
												<td width="78%" class="vtable">
													<input name="ldap_port" type="text" class="formfld unknown" id="ldap_port" size="5" value="<?=htmlspecialchars($pconfig['ldap_port']);?>"/>
												</td>
											</tr>
											<tr>
												<td width="22%" valign="top" class="vncellreq"><?=gettext("Transport");?></td>
												<td width="78%" class="vtable">
													<select name='ldap_urltype' id='ldap_urltype' class="formselect selectpicker" data-style="btn-default" onchange='ldap_urlchange()'>
													<?php
                                                    foreach ($ldap_urltypes as $urltype => $urlport) :
                                                        $selected = "";
                                                        if ($pconfig['ldap_urltype'] == $urltype) {
                                                            $selected = "selected=\"selected\"";
                                                        }
                                                    ?>
                                                    <option value="<?=$urltype;
?>" <?=$selected;
?>><?=$urltype;?></option>
													<?php
                                                    endforeach; ?>
													</select>
												</td>
											</tr>
											<tr id="tls_ca">
												<td width="22%" valign="top" class="vncell"><?=gettext("Peer Certificate Authority"); ?></td>
					                                                        <td width="78%" class="vtable">
					                                                        <?php if (count($a_ca)) :
?>
													<select id='ldap_caref' name='ldap_caref' class="formselect selectpicker" data-style="btn-default">
					                                                        <?php
                                                                            foreach ($a_ca as $ca) :
                                                                                    $selected = "";
                                                                                if ($pconfig['ldap_caref'] == $ca['refid']) {
                                                                                        $selected = "selected=\"selected\"";
                                                                                }
                                                                            ?>
														<option value="<?=$ca['refid'];
?>" <?=$selected;
?>><?=$ca['descr'];?></option>
					                                                        <?php
                                                                            endforeach; ?>
													</select>
													<br /><span><?=gettext("This option is used if 'SSL Encrypted' option is choosen.");?> <br />
													<?=gettext("It must match with the CA in the AD otherwise problems will arise.");?></span>
					                                                        <?php
else :
?>
					                                                                <b>No Certificate Authorities defined.</b> <br />Create one under <a href="system_camanager.php">System: Certificates</a>.
					                                                        <?php
endif; ?>
					                                                        </td>
											</tr>
											<tr>
												<td width="22%" valign="top" class="vncellreq"><?=gettext("Protocol version");?></td>
												<td width="78%" class="vtable">
													<select name='ldap_protver' id='ldap_protver' class="formselect selectpicker" data-style="btn-default">
													<?php
                                                    foreach ($ldap_protvers as $version) :
                                                        $selected = "";
                                                        if ($pconfig['ldap_protver'] == $version) {
                                                            $selected = "selected=\"selected\"";
                                                        }
                                                    ?>
                                                    <option value="<?=$version;
?>" <?=$selected;
?>><?=$version;?></option>
													<?php
                                                    endforeach; ?>
													</select>
												</td>
											</tr>
											<tr>
												<td width="22%" valign="top" class="vncell"><?=gettext("Bind credentials");?></td>
												<td width="78%" class="vtable">
													<table border="0" cellspacing="0" cellpadding="2" summary="bind credentials">
														<tr>
															<td>
																<input name="ldap_anon" type="checkbox" id="ldap_anon" value="yes" <?php if ($pconfig['ldap_anon']) {
                                                                    echo "checked=\"checked\"";
} ?> onclick="ldap_bindchange()" />
															</td>
															<td>
																<?=gettext("Use anonymous binds to resolve distinguished names");?>
															</td>
														</tr>
													</table>
													<table border="0" cellspacing="0" cellpadding="2" id="ldap_bind" summary="bind">
														<tr>
															<td colspan="2"></td>
														</tr>
														<tr>
															<td><?=gettext("User DN:");?> &nbsp;</td>
															<td>
																<input name="ldap_binddn" type="text" class="formfld unknown" id="ldap_binddn" size="40" value="<?=htmlspecialchars($pconfig['ldap_binddn']);?>"/><br />
															</td>
														</tr>
														<tr>
															<td><?=gettext("Password:");?> &nbsp;</td>
															<td>
																<input name="ldap_bindpw" type="password" class="formfld pwd" id="ldap_bindpw" size="20" value="<?=htmlspecialchars($pconfig['ldap_bindpw']);?>"/><br />
															</td>
														</tr>
													</table>
												</td>
											</tr>
											<tr>
												<td width="22%" valign="top" class="vncell"><?=gettext("Search scope");?></td>
												<td width="78%" class="vtable">
													<table border="0" cellspacing="0" cellpadding="2" summary="search scope">
														<tr>
															<td><?=gettext("Level:");?></td>
															<td>
																<select name='ldap_scope' id='ldap_scope' class="formselect selectpicker" data-style="btn-default">
																<?php
                                                                foreach ($ldap_scopes as $scopename => $scopedesc) :
                                                                    $selected = "";
                                                                    if ($pconfig['ldap_scope'] == $scopename) {
                                                                        $selected = "selected=\"selected\"";
                                                                    }
                                                                ?>
                                                                <option value="<?=$scopename;
?>" <?=$selected;
?>><?=$scopedesc;?></option>
																<?php
                                                                endforeach; ?>
																</select>
															</td>
														</tr>
														<tr>
															<td><?=gettext("Base DN:");?></td>
															<td>
																<input name="ldap_basedn" type="text" class="formfld unknown" id="ldap_basedn" size="40" value="<?=htmlspecialchars($pconfig['ldap_basedn']);?>"/>
															</td>
														</tr>
													</table>

												</td>
											</tr>
											<tr>
												<td width="22%" valign="top" class="vncellreq"><?=gettext("Authentication containers");?></td>
												<td width="78%" class="vtable">
													<table border="0" cellspacing="0" cellpadding="2" summary="auth containers">
														<tr>
															<td valign="top"><?=gettext("Containers:");?> &nbsp;</td>
															<td>
																<ul class="list-inline">
																<li><input name="ldapauthcontainers" type="text" class="formfld unknown" id="ldapauthcontainers" size="40" value="<?=htmlspecialchars($pconfig['ldap_authcn']);?>"/></li>
																<li><input type="button" onclick="select_clicked();" class="btn btn-default" value="<?=gettext("Select");?>" /></li>
																</ul>
															</td>

														</tr>
														<tr>
														<td colspan="2">
																<br /><?=gettext("Note: Semi-Colon separated. This will be prepended to the search base dn above or you can specify full container path containing a dc= component.");?>
																<br /><?=gettext("Example:");?> CN=Users;DC=example,DC=com
																<br /><?=gettext("Example:");?> OU=Staff;OU=Freelancers
														</td>
														</tr>
													</table>
												</td>
											</tr>
											<tr>
												<td width="22%" valign="top" class="vncell"><?=gettext("Extended Query");?></td>
												<td width="78%" class="vtable">
													<table border="0" cellspacing="0" cellpadding="2" summary="query">
														<tr>
															<td>

																<input name="ldap_extended_query" type="text" class="formfld unknown" id="ldap_extended_query" size="40" value="<?=htmlspecialchars($pconfig['ldap_extended_query']);?>"/>
																<br /><?=gettext("Example:");?> &amp;(objectClass=inetOrgPerson)(mail=*@example.com)
															</td>
														</tr>
													</table>
												</td>
											</tr>
											<?php if (!isset($id)) :
?>
											<tr>
												<td width="22%" valign="top" class="vncell"><?=gettext("Initial Template");?></td>
												<td width="78%" class="vtable">
													<select name='ldap_tmpltype' id='ldap_tmpltype' class="formselect selectpicker" data-style="btn-default" onchange='ldap_tmplchange()'>
													<?php
                                                    foreach ($ldap_templates as $tmplname => $tmpldata) :
                                                        $selected = "";
                                                        if ($pconfig['ldap_template'] == $tmplname) {
                                                            $selected = "selected=\"selected\"";
                                                        }
                                                    ?>
                                                    <option value="<?=$tmplname;
?>" <?=$selected;
?>><?=$tmpldata['desc'];?></option>
													<?php
                                                    endforeach; ?>
													</select>
												</td>
											</tr>
											<?php
endif; ?>
											<tr>
												<td width="22%" valign="top" class="vncell"><?=gettext("User naming attribute");?></td>
												<td width="78%" class="vtable">
													<input name="ldap_attr_user" type="text" class="formfld unknown" id="ldap_attr_user" size="20" value="<?=htmlspecialchars($pconfig['ldap_attr_user']);?>"/>
												</td>
											</tr>
										</table>

										<table class="table table-striped table-sort" id="radius" style="display:none" summary="">
											<tr>
												<td colspan="2" class="list" height="12"></td>
											</tr>
											<tr>
												<td colspan="2" valign="top" class="listtopic"><?=gettext("Radius Server Settings");?></td>
											</tr>
											<tr>
												<td width="22%" valign="top" class="vncellreq"><?=gettext("Hostname or IP address");?></td>
												<td width="78%" class="vtable">
													<input name="radius_host" type="text" class="formfld unknown" id="radius_host" size="20" value="<?=htmlspecialchars($pconfig['radius_host']);?>"/>
												</td>
											</tr>
											<tr>
												<td width="22%" valign="top" class="vncellreq"><?=gettext("Shared Secret");?></td>
												<td width="78%" class="vtable">
													<input name="radius_secret" type="password" class="formfld pwd" id="radius_secret" size="20" value="<?=htmlspecialchars($pconfig['radius_secret']);?>"/>
												</td>
											</tr>
											<tr>
												<td width="22%" valign="top" class="vncellreq"><?=gettext("Services offered");?></td>
												<td width="78%" class="vtable">
													<select name='radius_srvcs' id='radius_srvcs' class="formselect selectpicker" data-style="btn-default" onchange='radius_srvcschange()'>
													<?php
                                                    foreach ($radius_srvcs as $srvcname => $srvcdesc) :
                                                        $selected = "";
                                                        if ($pconfig['radius_srvcs'] == $srvcname) {
                                                            $selected = "selected=\"selected\"";
                                                        }
                                                    ?>
                                                    <option value="<?=$srvcname;
?>" <?=$selected;
?>><?=$srvcdesc;?></option>
													<?php
                                                    endforeach; ?>
													</select>
												</td>
											</tr>
											<tr id="radius_auth">
												<td width="22%" valign="top" class="vncellreq"><?=gettext("Authentication port value");?></td>
												<td width="78%" class="vtable">
													<input name="radius_auth_port" type="text" class="formfld unknown" id="radius_auth_port" size="5" value="<?=htmlspecialchars($pconfig['radius_auth_port']);?>"/>
												</td>
											</tr>
											<tr id="radius_acct">
												<td width="22%" valign="top" class="vncellreq"><?=gettext("Accounting port value");?></td>
												<td width="78%" class="vtable">
													<input name="radius_acct_port" type="text" class="formfld unknown" id="radius_acct_port" size="5" value="<?=htmlspecialchars($pconfig['radius_acct_port']);?>"/>
												</td>
											</tr>
											<tr>
												<td width="22%" valign="top" class="vncellreq"><?=gettext("Authentication Timeout");?></td>
												<td width="78%" class="vtable">
													<input name="radius_timeout" type="text" class="formfld unknown" id="radius_timeout" size="20" value="<?=htmlspecialchars($pconfig['radius_timeout']);?>"/>
													<br /><?= gettext("This value controls how long, in seconds, that the RADIUS server may take to respond to an authentication request.") ?>
													<br /><?= gettext("If left blank, the default value is 5 seconds.") ?>
													<br /><br /><?= gettext("NOTE: If you are using an interactive two-factor authentication system, increase this timeout to account for how long it will take the user to receive and enter a token.") ?>
												</td>
											</tr>
										</table>

										<table class="table table-striped table-sort">
											<tr>
												<td width="22%" valign="top">&nbsp;</td>
												<td width="78%">
													<input id="submit" name="save" type="submit" class="btn btn-primary" value="<?=gettext("Save");?>" />
													<?php if (isset($id) && $a_server[$id]) :
?>
													<input name="id" type="hidden" value="<?=htmlspecialchars($id);?>" />
													<?php
endif;?>
												</td>
											</tr>
										</table>
									</form>

				<?php
else :
?>

									<table class="table table-striped table-sort">

											<thead>
												<tr>
													<th width="25%" class="listhdrr"><?=gettext("Server Name");?></th>
													<th width="25%" class="listhdrr"><?=gettext("Type");?></th>
													<th width="35%" class="listhdrr"><?=gettext("Host Name");?></th>
													<th width="10%" class="list"></th>
												</tr>
											</thead>
											<tfoot>

												<tr>
													<td colspan="4">
														<p>
															<?=gettext("Additional authentication servers can be added here.");?>
														</p>
													</td>
												</tr>
											</tfoot>
											<tbody>
												<?php
                                                    $i = 0;
                                                foreach ($a_server as $server) :
                                                    $name = htmlspecialchars($server['name']);
                                                    $type = htmlspecialchars($auth_server_types[$server['type']]);
                                                    $host = htmlspecialchars($server['host']);
                                                ?>
												<tr <?php if ($i < (count($a_server) - 1)) :
?> ondblclick="document.location='system_authservers.php?act=edit&amp;id=<?=$i;?>'" <?php
endif; ?>>
                                                <td class="listlr"><?=$name?>&nbsp;</td>
                                                <td class="listr"><?=$type;?>&nbsp;</td>
                                                <td class="listr"><?=$host;?>&nbsp;</td>
                                                <td valign="middle" class="list nowrap">
                                                <?php if ($i < (count($a_server) - 1)) :
?>
														<a href="system_authservers.php?act=edit&amp;id=<?=$i;?>" class="btn btn-default btn-xs">
															<span class="glyphicon glyphicon-pencil"></span>
														</a>
														&nbsp;
														<a href="system_authservers.php?act=del&amp;id=<?=$i;
?>" onclick="return confirm('<?=gettext("Do you really want to delete this Server?");?>')" class="btn btn-default btn-xs">
															<span class="glyphicon glyphicon-remove"></span>
														</a>
													<?php
endif; ?>
                                                </td>
												</tr>
												<?php
                                                $i++;

                                                endforeach;
                                                ?>
											</tbody>
										</table>

							<?php
endif; ?>
							</div>
						</section>
					</div>
				</div>
			</section>

<script type="text/javascript">
    //<![CDATA[
    $( document ).ready(function() {
        server_typechange('<?=htmlspecialchars($pconfig['type']);?>');
        if (document.getElementById("ldap_port").value == "") ldap_urlchange();
        <?php
        if ($pconfig['type'] == "ldap") {
            echo "     ldap_bindchange();\n";
            echo "     if (document.getElementById(\"ldap_port\").value == \"\") ldap_urlchange();\n";
            if (!isset($id)) {
                echo "    ldap_tmplchange();\n";
            }
        } else {
            echo "     radius_srvcschange();\n";
        }
        ?>
    });
    //]]>
</script>

<?php include("foot.inc");
