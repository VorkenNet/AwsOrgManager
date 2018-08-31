<?php
require_once("awsOrgManager.inc.php");
require_once("bioMysql.inc.php");
require_once("config.inc.php");
/*
AwsSnapShot Account
*/
$input=verifyInputAccount($argv);

$link=bioMysqliConnect($dbip, $dbusername, $dbpassword, $db);
if (!$link) die("--NO DB CONNECTION\n");

$result=ec2ListAccount($input['accountId'],$input['region'],$input['jobName']);
print_r($result);
if (count($result["Instances"])){
  foreach ($result["Instances"] as $myInstance){
    $myInstance["AccountId"]=$result["AccountId"];
    $myInstance["Region"]=$result["Region"];
    unset($myInstance["Volumes"]);
    $string=implode($myInstance,"\t");
    echo $string."\n";
    $mysql=bioArrayToMysqlReplaceInto("ec2List",$myInstance,$link);
    echo $mysql;
    bioMysqliQuery($link,$mysql);
  }
}

?>
