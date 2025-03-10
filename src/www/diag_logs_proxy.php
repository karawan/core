<?php

/*
	Copyright (C) 2015 Deciso B.V.
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
require_once("system.inc");

if (empty($config['syslog']['nentries'])) {
        $nentries = 50;
} else {
        $nentries = $config['syslog']['nentries'];
}

$type = 'cache';
if (isset($_GET['type']) && $_GET['type'] === 'access') {
	$type = $_GET['type'];
}

$logfile = "/var/log/squid/{$type}.log";

if (isset($_GET['clear'])) {
	clear_log($logfile);
}

$pgtitle = array(gettext("Status"),gettext("System logs"),gettext("Proxy"));

include("head.inc");

?>

<body>
<?php include("fbegin.inc"); ?>
<section class="page-content-main">
    <div class="container-fluid">
        <div class="row">
            <section class="col-xs-12">
                <?php include('diag_logs_tabs.inc'); ?>
                <div class="tab-content content-box col-xs-12">
                    <div class="container-fluid">
                        <?php $tab_group = 'proxy'; include('diag_logs_pills.inc'); ?>
                        <p><?php printf(gettext("Last %s Proxy log entries"), $nentries);?></p>
                        <div class="table-responsive">
                            <table class="table table-striped table-sort">
                                <?php dump_log($logfile, $nentries); ?>
                            </table>
                        </div>
                        <form method="get">
                            <input name="clear" type="submit" class="btn" value="<?= gettext("Clear log");?>" />
                            <input name="type" type="hidden" value="<?= $type ?>" />
                        </form>
                    </div>
                </div>
            </section>
        </div>
    </div>
</section>

<?php include("foot.inc"); ?>
