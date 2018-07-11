<?php
require_once("include-aws/aws-autoloader.php");
use Aws\Ec2\Ec2Client;
use Aws\Sts\StsClient;
use Aws\Organizations\OrganizationsClient;


$myAccounts=getAwsOrganizationAccounts();
foreach($myAccounts as $myAccount){
  $string=implode("^",$myAccount);
  $switch="[[https://signin.aws.amazon.com/switchrole?account=".$myAccount["Id"]."&roleName=OrganizationAccountAccessRole&displayName=".$myAccount["Name"]."|Switch]]";
  echo "^".$string."^".$switch."^\n";
  $ec2Client=switchRoleClientEc2($myAccount["Id"],$myAccount["Name"]);
  $result = $ec2Client->describeInstances();
  $result=$result->toArray();
  echoIstanceList($result);
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

function echoIstanceList($result){
  foreach($result["Reservations"] as $reservation){
    foreach ($reservation["Instances"] as $instance){
      $myInstance["Name"]=getTagsname($instance["Tags"]);
      $myInstance["InstanceId"]=$instance["InstanceId"];
      $myInstance["ImageId"]=$instance["ImageId"];
      $myInstance["InstanceType"]=$instance["InstanceType"];
      if(isset($instance["PrivateIpAddress"])) $myInstance["PrivateIpAddress"]=$instance["PrivateIpAddress"];
      else $myInstance["PrivateIpAddress"]="None";
      if(isset($instance["PublicIpAddress"])) $myInstance["PublicIpAddress"]=$instance["PublicIpAddress"];
      else $myInstance["PublicIpAddress"]="None";
      $myInstance["State"]=$instance["State"]["Name"];
      $myInstance["VpcId"]=$instance["VpcId"];
      $myInstances[]=$myInstance;
    }
  }
  if (isset($myInstances)){
    foreach($myInstances as $myInstance){
      unset ($myInstance["ImageId"]);
      unset ($myInstance["PrivateIpAddress"]);
      unset ($myInstance["VpcId"]);
      $string=implode("|",$myInstance);
      echo "|".$string."|\n";
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
