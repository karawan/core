<?php

/*
	Copyright (C) 2014-2015 Deciso B.V.
	Copyright (C) 2008 Shrew Soft Inc.
	Copyright (C) 2005 Paul Taylor <paultaylor@winn-dixie.com>.
	Copyright (C) 2003-2005 Manuel Kasper <mk@neon1.net>.
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

$pgtitle = array(gettext('System'), gettext('Groups'));

if (!isset($config['system']['group'])) {
    $config['system']['group'] = array();
}

$a_group = &$config['system']['group'];

unset($id);
if (isset($_POST['groupid']) && is_numericint($_POST['groupid'])) {
    $id = $_POST['groupid'];
}

$act = (isset($_POST['act']) ? $_POST['act'] : '');

if ($act == "delgroup") {
    if (!isset($id) || !isset($_POST['groupname']) || !isset($a_group[$id]) || ($_POST['groupname'] != $a_group[$id]['name'])) {
        redirectHeader("system_groupmanager.php");
        exit;
    }

    local_group_del($a_group[$id]);
    $groupdeleted = $a_group[$id]['name'];
    unset($a_group[$id]);
    write_config();
    $savemsg = gettext("Group")." {$groupdeleted} ".
        gettext("successfully deleted")."<br />";
}

if ($act == "delpriv") {
    if (!isset($id) || !isset($a_group[$id])) {
        redirectHeader("system_groupmanager.php");
        exit;
    }

    $privdeleted = $priv_list[$a_group[$id]['priv'][$_POST['privid']]]['name'];
    unset($a_group[$id]['priv'][$_POST['privid']]);

    if (is_array($a_group[$id]['member'])) {
        foreach ($a_group[$id]['member'] as $uid) {
            $user = getUserEntryByUID($uid);
            if ($user) {
                local_user_set($user);
            }
        }
    }

    write_config();
    $act = "edit";
    $savemsg = gettext("Privilege")." {$privdeleted} ".
                gettext("successfully deleted")."<br />";
}

if ($act == "edit") {
    if (isset($id) && isset($a_group[$id])) {
        $pconfig['name'] = $a_group[$id]['name'];
        $pconfig['gid'] = $a_group[$id]['gid'];
        $pconfig['gtype'] = $a_group[$id]['scope'];
        $pconfig['description'] = $a_group[$id]['description'];
        $pconfig['members'] = $a_group[$id]['member'];
        $pconfig['priv'] = $a_group[$id]['priv'];
    }
}

if (isset($_POST['save'])) {
    unset($input_errors);
    $pconfig = $_POST;

    /* input validation */
    $reqdfields = explode(" ", "groupname");
    $reqdfieldsn = array(gettext("Group Name"));

    do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

    if (preg_match("/[^a-zA-Z0-9\.\-_ ]/", $_POST['groupname'])) {
        $input_errors[] = gettext("The group name contains invalid characters.");
    }

    if (strlen($_POST['groupname']) > 16) {
        $input_errors[] = gettext("The group name is longer than 16 characters.");
    }

    if (!$input_errors && !(isset($id) && $a_group[$id])) {
        /* make sure there are no dupes */
        foreach ($a_group as $group) {
            if ($group['name'] == $_POST['groupname']) {
                $input_errors[] = gettext("Another entry with the same group name already exists.");
                break;
            }
        }

        $sys_groups = file_get_contents('/etc/group');
        foreach (explode("\n", $sys_groups) as $line) {
            if (explode(":", $line)[0] ==  $_POST['groupname']) {
                $input_errors[] = gettext("That groupname is reserved by the system.");
            }
        }
    }

    if (!$input_errors) {
        $group = array();
        if (isset($id) && $a_group[$id]) {
            $group = $a_group[$id];
        }

        $group['name'] = $_POST['groupname'];
        $group['description'] = $_POST['description'];

        if (empty($_POST['members'])) {
            unset($group['member']);
        } else {
            $group['member'] = $_POST['members'];
        }

        if (isset($id) && $a_group[$id]) {
            $a_group[$id] = $group;
        } else {
            $group['gid'] = $config['system']['nextgid']++;
            $a_group[] = $group;
        }

        local_group_set($group);

        /* Refresh users in this group since their privileges may have changed. */
        if (is_array($group['member'])) {
            $a_user = &$config['system']['user'];
            foreach ($a_user as & $user) {
                if (in_array($user['uid'], $group['member'])) {
                    local_user_set($user);
                }
            }
        }

        write_config();

        header("Location: system_groupmanager.php");
        exit;
    }
}

include("head.inc");

?>

<body>
<?php include("fbegin.inc"); ?>
<script type="text/javascript">
//<![CDATA[

function setall_selected(id) {
	selbox = document.getElementById(id);
	count = selbox.options.length;
	for (index = 0; index<count; index++)
		selbox.options[index].selected = true;
}

function clear_selected(id) {
	selbox = document.getElementById(id);
	count = selbox.options.length;
	for (index = 0; index<count; index++)
		selbox.options[index].selected = false;
}

function remove_selected(id) {
	selbox = document.getElementById(id);
	index = selbox.options.length - 1;
	for (; index >= 0; index--)
		if (selbox.options[index].selected)
			selbox.remove(index);
}

function copy_selected(srcid, dstid) {
	src_selbox = document.getElementById(srcid);
	dst_selbox = document.getElementById(dstid);
	count = dst_selbox.options.length;
	for (index = count - 1; index >= 0; index--) {
		if (dst_selbox.options[index].value == '') {
			dst_selbox.remove(index);
		}
	}
	count = src_selbox.options.length;
	for (index = 0; index < count; index++) {
		if (src_selbox.options[index].selected) {
			option = document.createElement('option');
			option.text = src_selbox.options[index].text;
			option.value = src_selbox.options[index].value;
			dst_selbox.add(option, null);
		}
	}
}

function move_selected(srcid, dstid) {
	copy_selected(srcid, dstid);
	remove_selected(srcid);
}

function presubmit() {
	clear_selected('notmembers');
	setall_selected('members');
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

							<?php
                            if ($act == "new" || $act == "edit") :
                            ?>
                    <form action="system_groupmanager.php" method="post" name="iform" id="iform" onsubmit="presubmit()">
                        <input type="hidden" id="act" name="act" value="" />
                        <input type="hidden" id="groupid" name="groupid" value="<?=(isset($id) ? $id : '');?>" />
                        <input type="hidden" id="privid" name="privid" value="" />

                        <table class="table table-striped table-sort">
                            <?php
                                $ro = "";
                            if ($pconfig['gtype'] == "system") {
                                $ro = "readonly=\"readonly\"";
                            }
                            ?>
                                <tr>
                                    <td width="22%" valign="top" class="vncell"><?=gettext("Defined by");?></td>
                                    <td width="78%" class="vtable">
                                        <strong><?=strtoupper($pconfig['gtype']);?></strong>
                                        <input name="gtype" type="hidden" value="<?=htmlspecialchars($pconfig['gtype'])?>"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td width="22%" valign="top" class="vncellreq"><?=gettext("Group name");?></td>
                                    <td width="78%" class="vtable">
                                        <input name="groupname" type="text" class="formfld group" id="groupname" size="20" maxlength="16" value="<?=htmlspecialchars($pconfig['name']);
?>" <?=$ro;?> />
                                    </td>
                                </tr>
                                <tr>
                                    <td width="22%" valign="top" class="vncell"><?=gettext("Description");?></td>
                                    <td width="78%" class="vtable">
                                        <input name="description" type="text" class="formfld unknown" id="description" size="20" value="<?=htmlspecialchars($pconfig['description']);?>" />
                                        <br />
                                        <?=gettext("Group description, for your own information only");?>
                                    </td>
                                </tr>
                        <tr>
                            <td width="22%" valign="top" class="vncell"><?=gettext("Group Memberships");?></td>
                            <td width="78%" class="vtable" align="center">
                                <table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0" summary="membership">
                                    <tr>
                                        <td align="center" width="50%">
                                            <strong><?=gettext("Not Members");?></strong><br />
                                            <br />
                                                <select size="10" style="width: 75%" name="notmembers[]" class="formselect" id="notmembers" onchange="clear_selected('members')" multiple="multiple">
					<?php
                                            $rowIndex = 0;
                    foreach ($config['system']['user'] as $user) :
                        if (is_array($pconfig['members']) && in_array($user['uid'], $pconfig['members'])) {
                            continue;
                        }
                        $rowIndex++;
                    ?>
                        <option value="<?=$user['uid'];
?>" <?=$selected;?>>
                            <?=htmlspecialchars($user['name']);?>
                        </option>
<?php
                    endforeach;
                    if ($rowIndex == 0) {
                        echo "<option></option>";
                    }
                    ?>
                                            </select>
                                            <br />
                                        </td>
                                        <td>
                                            <br />
                                            <a href="javascript:move_selected('notmembers','members')" class="btn btn-default btn-xs">
                                                <span class="glyphicon glyphicon-arrow-right"></span>
                                            </a>
                                            <br /><br />
                                            <a href="javascript:move_selected('members','notmembers')" class="btn btn-default btn-xs">
                                                <span class="glyphicon glyphicon-arrow-left"></span>
                                            </a>
                                        </td>
                                        <td align="center" width="50%">
                                            <strong><?=gettext("Members");?></strong><br />
                                            <br />
                                            <select size="10" style="width: 75%" name="members[]" class="formselect" id="members" onchange="clear_selected('notmembers')" multiple="multiple">
					<?php
                                            $rowIndex = 0;
                    foreach ($config['system']['user'] as $user) :
                        if (!(is_array($pconfig['members']) && in_array($user['uid'], $pconfig['members']))) {
                            continue;
                        }
                        $rowIndex++;
                    ?>
                        <option value="<?=$user['uid'];?>">
                            <?=htmlspecialchars($user['name']);?>
                        </option>
					<?php
                    endforeach;
                    if ($rowIndex == 0) {
                        echo "<option></option>";
                    }
                    ?>
                                            </select>
                                            <br />
                                        </td>
                                    </tr>
                                </table>
                                <?=gettext("Hold down CTRL (pc)/COMMAND (mac) key to select multiple items");?>
                            </td>
                        </tr>
					<?php
                        if ($act != "new") :
                    ?>
                            <th colspan="2" valign="top" class="vncell"><?=gettext("Assigned Privileges");?></th>
                            <tr>
                                <td colspan="2" class="vtable">
                                    <table class="tabcont table table-striped" width="100%" border="0" cellpadding="0" cellspacing="0" summary="privileges">
                                        <tr>
                                            <td width="40%" class="listhdrr"><b><?=gettext("Name");?></b></td>
                                            <td width="60%" class="listhdrr"><b><?=gettext("Description");?></b></td>
                                            <td class="list"></td>
                                        </tr>
					<?php
                    if (is_array($pconfig['priv'])) :
                        $i = 0;
                        foreach ($pconfig['priv'] as $priv) :
                    ?>
                            <tr>
                                <td class="listr">
                                    <?=htmlspecialchars($priv_list[$priv]['name']);?>
                                </td>
                                <td class="listbg">
                                    <?=htmlspecialchars($priv_list[$priv]['descr']);?>
                                </td>
                                <td valign="middle" class="list nowrap">
                                    <button type="submit" name="delpriv[]"
                                        class="btn btn-default btn-xs"
                                        onclick="document.getElementById('privid').value='<?=$i;?>';
                                            document.getElementById('groupid').value='<?=$id;?>';
                                            document.getElementById('act').value='<?php echo "delpriv";?>';
                                            return confirm('<?=gettext("Do you really want to delete this privilege?");?>');"
                                        title="<?=gettext("delete privilege");?>" data-toggle="tooltip" data-placement="left" ><span class="glyphicon glyphicon-remove"></span></button>
                                </td>
                            </tr>
					<?php
                            $i++;
                        endforeach;
                    endif;
                    ?>
                                        <tr>
                                            <td class="list" colspan="2"></td>
                                            <td class="list">
                                                <a href="system_groupmanager_addprivs.php?groupid=<?=htmlspecialchars($id)?>" class="btn btn-default btn-xs">
                                                    <span class="glyphicon glyphicon-plus"></span>
                                                </a>

                                            </td>
                                        </tr>

                                    </table>
                                </td>
                            </tr>
					<?php
                        endif;
                    ?>
                                <tr>
                                    <td width="22%" valign="top">&nbsp;</td>
                                    <td width="78%">
                                        <input name="save" type="submit" class="btn btn-primary" value="<?=gettext("Save");?>" />
                                        <input type="button" class="btn btn-default" value="<?=gettext("Cancel");?>" onclick="window.location.href='/system_groupmanager.php'" />
                                        <?php if (isset($id) && $a_group[$id]) :
?>
													<input name="id" type="hidden" value="<?=htmlspecialchars($id);?>" />
													<input name="gid" type="hidden" value="<?=htmlspecialchars($pconfig['gid']);?>" />
													<?php
endif; ?>
                                    </td>
                                </tr>
                            </table>
                    </form>
                    <?php
                            else :
                                ?>

								<form action="system_groupmanager.php" method="post" name="iform2" id="iform2">
									<input type="hidden" id="act" name="act" value="" />
									<input type="hidden" id="groupid" name="groupid" value="<?=(isset($id) ? $id : '');?>" />
									<input type="hidden" id="groupname" name="groupname" value="" />

									<table class="table table-striped table-sort">
											<thead>
												<tr>
													<th width="25%" class="listhdrr"><?=gettext("Group name");?></th>
													<th width="25%" class="listhdrr"><?=gettext("Description");?></th>
													<th width="30%" class="listhdrr"><?=gettext("Member Count");?></th>
													<th width="10%" class="list"></th>
												</tr>
											</thead>
											<tfoot>
												<tr>
													<td class="list" colspan="3"></td>
													<td class="list">
														<button type="submit" name="addcert"
															class="btn btn-default btn-xs"
															onclick="document.getElementById('act').value='<?php echo "new";?>';"
															title="<?=gettext("add group");?>"><span class="glyphicon glyphicon-plus"></span></button>
													</td>
												</tr>
												<tr>
													<td colspan="4">
														<p class="col-xs-12 col-sm-10">
															<?=gettext("Additional webConfigurator groups can be added here.
															Group permissions can be assigned which are inherited by users who are members of the group.
															An icon that appears grey indicates that it is a system defined object.
															Some system object properties can be modified but they cannot be deleted.");?>
														</p>
													</td>
												</tr>
											</tfoot>
											<tbody>
<?php
                        $i = 0;
foreach ($a_group as $group) :
    if ($group['scope'] == "system") {
        $grpimg = "glyphicon glyphicon-user text-mute";
    } else {
        $grpimg = "glyphicon glyphicon-user";
    }
    $groupcount = count($group['member']);
    if ($group["name"] == "all") {
        $groupcount = count($config['system']['user']);
    }
?>
    <tr ondblclick="document.getElementById('act').value='<?php echo "edit";?>';
        document.getElementById('groupid').value='<?=$i;?>';
        document.iform2.submit();">
        <td class="listlr">
            <table border="0" cellpadding="0" cellspacing="0" summary="">
                <tr>
                    <td align="left" width="30px">
                        <span class="<?=$grpimg;?>"></span>
                    </td>
                    <td align="left">
                        <?=htmlspecialchars($group['name']); ?>&nbsp;
                    </td>
                </tr>
            </table>
        </td>
        <td class="listr">
            <?=htmlspecialchars($group['description']);?>&nbsp;
        </td>
        <td class="listbg">
            <?=$groupcount;?>
        </td>
        <td valign="middle" class="list nowrap">
            <button type="submit" name="editgroup[]"
                class="btn btn-default btn-xs"
                onclick="document.getElementById('groupid').value='<?=$i;?>';
                    document.getElementById('act').value='<?php echo "edit";?>';"
                title="<?=gettext("edit group");?>"><span class="glyphicon glyphicon-pencil"></span></button>
            &nbsp;
<?php
if ($group['scope'] != "system") :
?>
    <button type="submit" name="delgroup[]"
        class="btn btn-default btn-xs"
        onclick="document.getElementById('groupid').value='<?=$i;?>';
            document.getElementById('groupname').value='<?=$group['name'];?>';
            document.getElementById('act').value='<?php echo "delgroup";?>';
            return confirm('<?=gettext("Do you really want to delete this group?");?>');"
        title="<?=gettext("delete group");?>"><span class="glyphicon glyphicon-remove"></span></button>
<?php
endif;
?>
        </td>
    </tr>
<?php
    $i++;
endforeach;
?>
						</tbody>
					</table>
								</form>

<?php
                            endif;
?>

						</div>
			    </section>
			</div>
		</div>
	</section>

<?php include("foot.inc");
