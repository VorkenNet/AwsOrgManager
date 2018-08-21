<?php
require_once("awsOrgSnapShot.inc.php");
/*
AwsSnapShot Instance
*/
$input=verifyInputInstance($argv);
snapShotInstance($input['accountId'],$input['region'],$input['instanceId'],$input['jobName']);
?>
