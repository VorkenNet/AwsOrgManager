<?php
require_once("awsOrgManager.inc.php");
/*
AwsSnapShot Account
*/
$input=verifyInputOrganization($argv);
snapShotOrganization($input['region'],$input['jobName']);

?>
