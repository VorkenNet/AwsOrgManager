<?php
require_once("include/sdkAws/aws-autoloader.php");
#bio
use Aws\Sts\StsClient;
use Aws\Ec2\Ec2Client;
use Aws\Organizations\OrganizationsClient;

###################################################
# SNAPSHOT FUNCTION
#
function snapShotOrganization($region,$jobName){
  $myAccounts=getAwsOrganizationAccounts($region);
  foreach($myAccounts as $myAccount){
      snapShotAccount($myAccount["Id"],$region,$jobName);
  }
}

function snapShotAccount($accountId,$region,$jobName){
  $ec2Client=switchRoleClientEc2($accountId,$region,"awsBackUp");
  $volumes=getIstancesVolumeList($ec2Client);
  foreach($volumes as $volume){
      $res = createSnapShot($ec2Client,$volume,$jobName);
      $log = date("Y-m-d H:i:s")."\tAWSORG-BackUp\t".$volume['InstanceName']."\t".$res['SnapshotId']."\t".$res['VolumeSize']."\t".$jobName."\t".$res['Description'].":".$accountId."\n";
      logActivity($log);
      echo $log;
  }
}

function snapShotInstance($accountId,$region,$instanceId,$jobName){
  $ec2Client=switchRoleClientEc2($accountId,$region,"awsBackUp");
  //$instanceId=array("i-0d5dcb64f158c7161","i-0cc180d082ccdfb0c");
  $volumes=getIstancesVolumeList($ec2Client, $instanceId);
  foreach($volumes as $volume){
      $res = createSnapShot($ec2Client,$volume,$jobName);
      $log = date("Y-m-d H:i:s")."\tAWSORG-BackUp\t".$volume['InstanceName']."\t".$res['SnapshotId']."\t".$res['VolumeSize']."\t".$jobName."\t".$res['Description'].":".$accountId."\n";
      logActivity($log);
      echo $log;
  }
}

function createSnapShot($ec2Client,$volume,$name){
  $description= "'".$volume['DeviceName']." for ".$volume['InstanceId']."'";
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
                      'Key' => 'InstanceName',
                      'Value' => $volume['InstanceName'],
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


function logActivity($string){
  $file="/var/log/awsorg.log";
  file_put_contents($file, $string, FILE_APPEND);
}

###################################################
# EC2 LIST
#

function ec2ListAccount($accountId,$region,$jobName){
  $ec2Client=switchRoleClientEc2($accountId,$region,"awsBackUp");
  $result = $ec2Client->describeInstances();
  $result=$result->toArray();
  $list["AccountId"]=$accountId;
  $list["Region"]=$region;
  $list["Instances"]=parseListInstances($ec2Client,$result);

  return $list;
}

###################################################
# EC2 Parse Info
#

function parseListInstances($ec2Client,$result){
  $myInstances=array();
  foreach($result["Reservations"] as $reservation){
    foreach ($reservation["Instances"] as $instance){
      $myInstances[]=parseIstanceInfo($ec2Client,$instance);

    }
  }
  return $myInstances;
}

function parseIstanceInfo($ec2Client,$instance){
  $myInstance["State"]=$instance["State"]["Name"];
  $myInstance["InstanceId"]=$instance["InstanceId"];
  if($myInstance["State"]=="terminated") return   $myInstance;
  $myInstance["Name"]=getTagsname($instance["Tags"]);
  $myInstance["AvailabilityZone"]=$instance["Placement"]["AvailabilityZone"];
  $myInstance["ImageId"]=$instance["ImageId"];
  $myInstance["InstanceType"]=$instance["InstanceType"];
  if(isset($instance["PrivateIpAddress"])) $myInstance["PrivateIpAddress"]=$instance["PrivateIpAddress"];
  else $myInstance["PrivateIpAddress"]="None";
  if(isset($instance["PublicIpAddress"])) $myInstance["PublicIpAddress"]=$instance["PublicIpAddress"];
  else $myInstance["PublicIpAddress"]="None";

  $myInstance["VpcId"]=$instance["VpcId"];
  /*if($myInstance["Name"]=="web10.nextmove.it"){
    print_r($instance["BlockDeviceMappings"]);
    die();
  }*/
  if (isset($instance["BlockDeviceMappings"])){
    foreach($instance["BlockDeviceMappings"] as $blockDevice){
        $volume = $ec2Client->describeVolumes([
                  'VolumeIds' => [
                          $blockDevice["Ebs"]["VolumeId"],
                ],
        ]);
        //$myInstance[$blockDevice["Ebs"]["VolumeId"]]= $blockDevice["DeviceName"] ."/".$volume["Volumes"][0]["Size"];
        $myInstance['Volumes'][$blockDevice["Ebs"]["VolumeId"]]["Size"]= $volume["Volumes"][0]["Size"]."G";
        $myInstance['Volumes'][$blockDevice["Ebs"]["VolumeId"]]["DeviceName"]= $blockDevice["DeviceName"];
    }
  }
  return($myInstance);
}

function getTagsname($tags){
   foreach($tags as $tag){
     if ($tag["Key"]=="Name") return $tag["Value"];
   }
   return false;
}

###################################################
# Volumi
#
function getIstancesVolumeList($ec2Client, $instanceId = null){
  if(!is_null($instanceId)){
    if (is_array($instanceId)) $param=array('InstanceIds' => $instanceId);
    else $param=array('InstanceIds' => array($instanceId));
    $result = $ec2Client->describeInstances($param);
  } else $result = $ec2Client->describeInstances();
  $result=$result->toArray();
  $myInstances=array();
  foreach($result["Reservations"] as $reservation){
    foreach ($reservation["Instances"] as $instance){
      if($instance["State"]["Name"]=="running"){
        $myInstance["InstanceName"]=getTagsname($instance["Tags"]);
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

function getAwsOrganizationAccounts($region){
  $orgClient=new OrganizationsClient([
    'region' => $region,
    'version' => 'latest',
    'profile' => 'default'
  ]);

  $result = $orgClient->listAccounts([]);
  $result=$result->toArray();

  foreach($result["Accounts"] as $account){
    $myAccount["Name"] = $account["Name"];
    $myAccount["Id"] = $account["Id"];
    $myAccount["Email"] = $account["Email"];
    $myAccount["Status"] = $account["Status"];
    $myAccounts[]=$myAccount;
  }
  return $myAccounts;
}

###################################################
# Switch Role
#
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

###################################################
# Verify Input
#

function verifyInputInstance($argv){
  $options = getopt("ha:r:i:j:");
  if (isset($options['h'])){
    echo "\n>> USAGE: ".$argv[0]." \n\t-a AccountId \n\t-r Region \n\t-i InstanceId \n\t-j JobName\n\n";
    die();
  } else {
    if(!isset($options['a'])) die("Missing AccountId!\n>> USAGE: ".$argv[0]." -a AccountId -r Region -i InstanceId -j JobName\n");
    else $input['accountId']=$options['a']; //NEED TO BE SANITAZED
    if(!isset($options['r'])) die("Missing Region!\n>> USAGE: ".$argv[0]." -a AccountId -r Region -i InstanceId -j JobName\n");
    else $input['region']=$options['r'];  //NEED TO BE SANITAZED
    if(!isset($options['i'])) die("Missing InstanceId!\n>> USAGE: ".$argv[0]." -a AccountId -r Region -i InstanceId -j JobName\n");
    else $input['instanceId']=$options['i'];  //NEED TO BE SANITAZED
    if(!isset($options['j'])) die("Missing JobName!\n>> USAGE: ".$argv[0]." -a AccountId -r Region -i InstanceId -j JobName\n");
    else $input['jobName']=$options['j'];  //NEED TO BE SANITAZED
  }
  return $input;
}

function verifyInputAccount($argv){
  $options = getopt("ha:r:j:");
  if (isset($options['h'])){
    echo "\n>> USAGE: ".$argv[0]." \n\t-a AccountId \n\t-r Region \n\t-j JobName\n\n";
    die();
  } else {
    if(!isset($options['a'])) die("Missing AccountId!\n>> USAGE: ".$argv[0]." -a AccountId -r Region -j JobName\n");
    else $input['accountId']=$options['a']; //NEED TO BE SANITAZED
    if(!isset($options['r'])) die("Missing Region!\n>> USAGE: ".$argv[0]." -a AccountId -r Region -j JobName\n");
    else $input['region']=$options['r'];  //NEED TO BE SANITAZED
    if(!isset($options['j'])) die("Missing JobName!\n>> USAGE: ".$argv[0]." -a AccountId -r Region -j JobName\n");
    else $input['jobName']=$options['j'];  //NEED TO BE SANITAZED
  }
  return $input;
}

function verifyInputOrganization($argv){
  $options = getopt("hr:j:");
  if (isset($options['h'])){
    echo "\n>> USAGE: ".$argv[0]." \n\t-r Region \n\t-j JobName\n\n";
    die();
  } else {
    if(!isset($options['r'])) die("Missing Region!\n>> USAGE: ".$argv[0]." -a AccountId -r Region -j JobName\n");
    else $input['region']=$options['r'];  //NEED TO BE SANITAZED
    if(!isset($options['j'])) die("Missing JobName!\n>> USAGE: ".$argv[0]." -a AccountId -r Region -j JobName\n");
    else $input['jobName']=$options['j'];  //NEED TO BE SANITAZED
  }
  return $input;
}
?>
