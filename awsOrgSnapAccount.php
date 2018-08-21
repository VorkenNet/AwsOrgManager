<?php
require_once("awsOrgSnapShot.inc.php");
/*
AwsSnapShot Account
*/
$input=verifyInputAccount($argv);
snapShotAccount($input['accountId'],$input['region'],$input['jobName']);

?>
