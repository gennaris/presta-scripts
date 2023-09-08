<?php
    
//  Simple script to check ps_accounts alive connection at regular intervals
//  based on => https://github.com/PrestaShop/PrestaShop/discussions/33332#discussioncomment-6891763

//  Meant to be placed in ps root dir and launched with  
//  https://www.domain.com/check_ps_accounts_connection.php?token=xxx

define(MODULE_NAME, 'ps_accounts');
define(TOKEN, 'bV3sJtdN4NCjLu30jtZQY/8bODHamPX2e6bvmGjH5Q?Y9NzfVjEMXgSDyjxIS8nu');

if (!isset($_GET['token']) || $_GET['token'] != TOKEN) {
    response(false, 'Invalid / missing token');
}

include __dir__.'/config/config.inc.php';
if (!Module::isInstalled(MODULE_NAME)) {
    response(false, MODULE_NAME.' appears to be NOT installed');
}
include __dir__.'/modules/ps_accounts/ps_accounts.php';
$a = new Ps_accounts();
$accountsService = $a->getService(\PrestaShop\Module\PsAccounts\Service\PsAccountsService::class);
if ($accountsService->isAccountLinked()) {
    response(true, 'Connection OK');
} else {
    response(true, 'Connection OK');
}

function response($status, $message)
{
    die(json_encode(['status' => $status, 'message' => $message]));
}
