<?php
require_once("include/sdkAws/aws-autoloader.php");
use Aws\Ec2\Ec2Client;
use Aws\Sts\StsClient;
use Aws\Organizations\OrganizationsClient;


$myAccounts=getAwsOrganizationAccounts();
foreach($myAccounts as $myAccount){

  $switch="[[https://signin.aws.amazon.com/switchrole?account=".$myAccount["Id"]."&roleName=OrganizationAccountAccessRole&displayName=".$myAccount["Name"]."|Switch]]";
  unset( $myAccount["Email"]);
  unset( $myAccount["Status"]);
  //unset( $myAccount["Id"]);
  $string=implode("^",$myAccount);

  echo "^".$string."^".$switch."^EBS^EC2^\n";
  $ec2Client=switchRoleClientEc2($myAccount["Id"],$myAccount["Name"]);
  getIstanceList($ec2Client);
  //die();
}

function switchRoleClientEc2($accountId, $accountName){
  $accountName=preg_replace('/\s+/', '', $accountName);
  $stsClient = StsClient::factory(array(
      'region' => 'eu-central-1',
      //'version' => '2011-06-15',
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
        'region' => 'eu-central-1',
        //'version' => '2016-11-15',
        'version' => 'latest',
        'credentials' => array(
            'key'    => $result['Credentials']['AccessKeyId'],
            'secret' => $result['Credentials']['SecretAccessKey'],
            'token'  => $result['Credentials']['SessionToken']
        )
    ]);
  }else {
    $ec2Client = new Ec2Client([
        'region' => 'eu-central-1',
         //'version' => '2016-11-15',
         'version' => 'latest',
        'profile' => 'default'
    ]);
  }
  return $ec2Client;
}

function getAwsOrganizationAccounts(){
  $orgClient=new OrganizationsClient([
    'region' => 'eu-central-1',
    'version' => 'latest',
    'profile' => 'default'
  ]);

  $result = $orgClient->listAccounts([
  ]);
  $result=$result->toArray();
  //print_r($result);

  foreach($result["Accounts"] as $account){
    $myAccount["Name"] = $account["Name"];
    $myAccount["Id"] = $account["Id"];// 975633923048
    $myAccount["Email"] = $account["Email"];
    $myAccount["Status"] = $account["Status"];
    $myAccounts[]=$myAccount;
  }
  return $myAccounts;
}


function parseIstanceInfo($ec2Client,$instance){
  $myInstance["State"]=$instance["State"]["Name"];
  $myInstance["InstanceId"]=$instance["InstanceId"];
  if($myInstance["State"]=="terminated") return   $myInstance;
  $myInstance["Name"]=getTagsname($instance["Tags"]);

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
  $sum=0;
  if (isset($instance["BlockDeviceMappings"])){

    foreach($instance["BlockDeviceMappings"] as $blockDevice){
        $volume = $ec2Client->describeVolumes([
                  'VolumeIds' => [
                          $blockDevice["Ebs"]["VolumeId"],
                ],
        ]);
        //$myInstance[$blockDevice["Ebs"]["VolumeId"]]= $blockDevice["DeviceName"] ."/".$volume["Volumes"][0]["Size"];
        //$myInstance[$blockDevice["Ebs"]["VolumeId"]]= $volume["Volumes"][0]["Size"]."G";
        $sum=$sum+intval($volume["Volumes"][0]["Size"]);
    }
  }
  $myInstance["sumVolumes"]=$sum;
  return($myInstance);
}

function getIstanceList($ec2Client){
  $result = $ec2Client->describeInstances();
  $result=$result->toArray();
  foreach($result["Reservations"] as $reservation){
    foreach ($reservation["Instances"] as $instance){
      //print_r($instance);

      $myInstances[]=parseIstanceInfo($ec2Client,$instance);

    }
  }
  //print_r($myInstances);
  //die();
  if (isset($myInstances)){
    foreach($myInstances as $myInstance){
      unset ($myInstance["ImageId"]);
      unset ($myInstance["PrivateIpAddress"]);
      unset ($myInstance["PublicIpAddress"]);
      unset ($myInstance["VpcId"]);
      unset ($myInstance["State"]);
      $myInstance["EBS"]=0.119*$myInstance["sumVolumes"];
      unset ($myInstance["sumVolumes"]);
      //https://aws.amazon.com/it/ec2/pricing/on-demand/
      switch ($myInstance["InstanceType"]){
         case "t2.xlarge":
         $myInstance["Ec2"]=round(0.2144*730.001,2);
         break;
         case "t2.large":
         $myInstance["Ec2"]=round(0.1072*730.001,2);
         break;
         case "t2.medium":
         $myInstance["Ec2"]=round(0.0536*730.001,2);
         break;
         case "t2.small":
         $myInstance["Ec2"]=round(0.0268 *730.001,2);
         break;
         case "t2.micro":
         $myInstance["Ec2"]=round(0.0134*730.001,2);
         break;
         case "m4.large":
         $myInstance["Ec2"]=round(0.12*730.001,2);
         break;
      }
      $string=implode("|",$myInstance);
      echo "|".$string."|\n";
      //print_r($myInstance);
    }
  }else{
    echo "|None|\n";
  }
}

function getTagsname($tags){
   foreach($tags as $tag){
     if ($tag["Key"]=="Name") return $tag["Value"];
   }
   return false;
}

 ?>
