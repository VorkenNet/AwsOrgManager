<?php
require_once("awsOrgManager.inc.php");
/*
AwsSnapShot Account
*/
$input=verifyInputAccount($argv);
snapShotAccount($input['accountId'],$input['region'],$input['jobName']);

?>
