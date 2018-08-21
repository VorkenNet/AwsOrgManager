<?php
require_once("awsOrgSnapShot.inc.php");
/*
AwsSnapShot Account
*/
$input=verifyInputOrganization($argv);
snapShotOrganization($input['region'],$input['jobName']);

?>
