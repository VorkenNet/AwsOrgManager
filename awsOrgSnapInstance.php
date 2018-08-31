<?php
require_once("awsOrgManager.inc.php");
/*
AwsSnapShot Instance
*/
$input=verifyInputInstance($argv);
snapShotInstance($input['accountId'],$input['region'],$input['instanceId'],$input['jobName']);
?>
