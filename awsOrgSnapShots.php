<?php
require_once("include/sdkAws/aws-autoloader.php");

use Aws\Sts\StsClient;
use Aws\Ec2\Ec2Client;
use Aws\Organizations\OrganizationsClient;



$options = getopt("ha:r:j:");
if (isset($options['h'])){
  echo "\n>> USAGE: ".$argv[0]." -a AccountId -r Region -j JobName\n\n";
  die();
} else {
  if(!isset($options['a'])) die("Missing AccountId!\n>> USAGE: ".$argv[0]." -a AccountId -r Region -j JobName\n");
  else $accountId=$options['a']; //NEED TO BE SANITAZED
  if(!isset($options['r'])) die("Missing Region!\n>> USAGE: ".$argv[0]." -a AccountId -r Region -j JobName\n");
  else $region=$options['r'];  //NEED TO BE SANITAZED
  if(!isset($options['j'])) die("Missing JobName!\n>> USAGE: ".$argv[0]." -a AccountId -r Region -j JobName\n");
  else $jobName=$options['j'];  //NEED TO BE SANITAZED
}
//print_r($options);

//die();
/*

-a 815604229474 -r eu-central-1 -j 15DayBackUp
$accountId="815604229474";
$region="eu-central-1";
$jobName="15DayBackUp";
*/

snapShotAccount($accountId,$region,$jobName);


function snapShotAccount($accountId,$region,$jobName){
  $ec2Client=switchRoleClientEc2($accountId,$region,"awsBackUp");
  $volumes=getIstancesVolumeList($ec2Client);
  foreach($volumes as $volume){
      $res = createSnapShot($ec2Client,$volume,$jobName);
      $log = date("Y-m-d H:i:s")."\tAWSORG-BackUp\t".$res['SnapshotId']."\t".$res['VolumeSize']."\t".$res['Description']."\t".$jobName."\n";
      logActivity($log);
      echo $log;
  }
}

function logActivity($string){
  $file="/var/log/awsorg.log";
  file_put_contents($file, $string, FILE_APPEND);
}

function createSnapShot($ec2Client,$volume,$name){
  $description= "'".$volume['DeviceName']." for ".$volume['InstanceId']."'";
  //var_export($arg);
  $result = $ec2Client->createSnapshot([
      'Description' => $description,
      'TagSpecifications' => [
          [
              'ResourceType' => 'snapshot',
              'Tags' => [
                  [
                      'Key' => 'Name',
                      'Value' => $name,
                  ],
                  [
                      'Key' => 'InstanceId',
                      'Value' => $volume['InstanceId'],
                  ],
                  [
                      'Key' => 'DeviceName',
                      'Value' => $volume['DeviceName'],
                  ],
              ],
          ],
      ],
      'VolumeId' => $volume['VolumeId'], // REQUIRED
  ]);
  $result=$result->toArray();
  $res['Description'] = $result['Description'];
  $res['SnapshotId'] = $result['SnapshotId'];
  $res['State'] = $result ['State'];
  $res['VolumeId'] = $result ['VolumeId'];
  $res['VolumeSize'] = $result['VolumeSize'];
  return $res;
}

function getIstancesVolumeList($ec2Client){
  $result = $ec2Client->describeInstances();
  $result=$result->toArray();
  foreach($result["Reservations"] as $reservation){
    foreach ($reservation["Instances"] as $instance){
      if($instance["State"]["Name"]=="running"){
        foreach($instance["BlockDeviceMappings"] as $device){
          $myInstance["InstanceId"]=$instance["InstanceId"];
          $myInstance["DeviceName"]=$device["DeviceName"];
          $myInstance["VolumeId"]=$device["Ebs"]["VolumeId"];
          $myInstances[]=$myInstance;
        }
      }
    }
  }
  return $myInstances;
}

function switchRoleClientEc2($accountId, $region, $accountName){
  $accountName=preg_replace('/\s+/', '', $accountName);
  $stsClient = StsClient::factory(array(
      'region' => $region,
      'version' => 'latest',
      'profile' => 'default'
  ));

  $result = $stsClient->getCallerIdentity([]);
  $result=$result->toArray();

  if($result["Account"]!=$accountId){
    $result = $stsClient->assumeRole([
        'DurationSeconds' => 3600,
        'RoleArn' => 'arn:aws:iam::'.$accountId.':role/OrganizationAccountAccessRole',
        'RoleSessionName' => 'AwsWebNextMove-cli-'.$accountName,
    ]);

    $ec2Client = new Ec2Client([
        'region' => $region,
        'version' => 'latest',
        'credentials' => array(
            'key'    => $result['Credentials']['AccessKeyId'],
            'secret' => $result['Credentials']['SecretAccessKey'],
            'token'  => $result['Credentials']['SessionToken']
        )
    ]);
  }else {
    $ec2Client = new Ec2Client([
        'region' => $region,
         'version' => 'latest',
        'profile' => 'default'
    ]);
  }
  return $ec2Client;
}
?>
