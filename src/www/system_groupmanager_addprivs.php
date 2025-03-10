<?php

/*
	Copyright (C) 2014-2015 Deciso B.V.
	Copyright (C) 2006 Daniel S. Haischt.
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

function cpusercmp($a, $b)
{
    return strcasecmp($a['name'], $b['name']);
}

function admin_groups_sort()
{
        global $config;

    if (!is_array($config['system']['group'])) {
            return;
    }

        usort($config['system']['group'], "cpusercmp");
}

require_once("guiconfig.inc");

$pgtitle = array(gettext('System'), gettext('Groups'), gettext('Privileges'));

if (is_numericint($_GET['groupid'])) {
    $groupid = $_GET['groupid'];
}
if (isset($_POST['groupid']) && is_numericint($_POST['groupid'])) {
    $groupid = $_POST['groupid'];
}

$a_group = & $config['system']['group'][$groupid];

if (!is_array($a_group)) {
    redirectHeader("system_groupmanager.php?id={$groupid}");
    exit;
}

if (!is_array($a_group['priv'])) {
    $a_group['priv'] = array();
}

if ($_POST) {
    unset($input_errors);
    $pconfig = $_POST;

    /* input validation */
    $reqdfields = explode(" ", "sysprivs");
    $reqdfieldsn = array(gettext("Selected priveleges"));

    do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

    /* if this is an AJAX caller then handle via JSON */
    if (isAjax() && is_array($input_errors)) {
        input_errors2Ajax($input_errors);
        exit;
    }

    if (!$input_errors) {
        if (!is_array($pconfig['sysprivs'])) {
            $pconfig['sysprivs'] = array();
        }

        if (!count($a_group['priv'])) {
            $a_group['priv'] = $pconfig['sysprivs'];
        } else {
            $a_group['priv'] = array_merge($a_group['priv'], $pconfig['sysprivs']);
        }

        if (is_array($a_group['member'])) {
            foreach ($a_group['member'] as $uid) {
                $user = getUserEntryByUID($uid);
                if ($user) {
                    local_user_set($user);
                }
            }
        }

        admin_groups_sort();

        $retval = write_config();
        $savemsg = get_std_save_message();

        redirectHeader("system_groupmanager.php?act=edit&amp;id={$groupid}");
        exit;
    }
}

/* if ajax is calling, give them an update message */
if (isAjax()) {
    print_info_box_np($savemsg);
}

include("head.inc");
?>

<body>
<?php include("fbegin.inc"); ?>
<script type="text/javascript">
//<![CDATA[

<?php

if (is_array($priv_list)) {
    $id = 0;

    $jdescs = "var descs = new Array();\n";
    foreach ($priv_list as $pname => $pdata) {
        if (in_array($pname, $a_group['priv'])) {
            continue;
        }
        $desc = addslashes($pdata['descr']);
        $jdescs .= "descs[{$id}] = '{$desc}';\n";
        $id++;
    }

    echo $jdescs;
}

?>

function update_description() {
	var index = document.iform.sysprivs.selectedIndex;
	document.getElementById("pdesc").innerHTML = descs[index];
}

//]]>
</script>

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
						<div class="tab-content content-box col-xs-12">

						<form action="system_groupmanager_addprivs.php" method="post" name="iform" id="iform">
							<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="main area" class="table table-striped">
								<tr>
									<td width="22%" valign="top" class="vncellreq"><?=gettext("System Privileges");?><br /><a href='#' onClick="selectAll();">Select all</a></td>
									<td width="78%" class="vtable">
										<select name="sysprivs[]" id="sysprivs" class="formselect" onchange="update_description();" multiple="multiple" size="35">
											<?php
                                            foreach ($priv_list as $pname => $pdata) :
                                                if (in_array($pname, $a_group['priv'])) {
                                                    continue;
                                                }
                                            ?>
											<option value="<?=$pname;
?>"><?=$pdata['name'];?></option>
											<?php
                                            endforeach; ?>
										</select>
										<br />
										<?=gettext("Hold down CTRL (pc)/COMMAND (mac) key to select multiple items");?>
										</td>

										<script type="text/javascript">
										//<![CDATA[
											function selectAll() {
												var options = jQuery('select#sysprivs option');
												var len = options.length;
												for (var i = 0; i < len; i++) {
												    options[i].selected = true;
												}
											}
											//selectAll();
										//]]>
										</script>
								</tr>
								<tr height="60">
									<td width="22%" valign="top" class="vncell"><?=gettext("Description");?></td>
									<td width="78%" valign="top" class="vtable" id="pdesc">
										<em><?=gettext("Select a privilege from the list above for a description");?></em>
									</td>
								</tr>
								<tr>
									<td width="22%" valign="top">&nbsp;</td>
									<td width="78%">
										<input id="submitt"  name="Submit" type="submit" class="formbtn btn btn-primary" value="<?=gettext("Save");?>" />
										<input id="cancelbutton" class="formbtn btn btn-default" type="button" value="<?=gettext("Cancel");?>" onclick="history.back()" />
										<?php if (isset($groupid)) :
?>
										<input name="groupid" type="hidden" value="<?=htmlspecialchars($groupid);?>" />
										<?php
endif; ?>
									</td>
								</tr>
							</table>
						</form>
					</div>
			</section>
		</div>
	</div>
</section>

<?php include("foot.inc"); ?>
</body>
</html>
