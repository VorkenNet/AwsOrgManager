<?php
// MYSQL FUNCTION BIO LIBRARY

function bioRealEscapeArray($array, $link){
   foreach($array as $element){
     $res[]=mysqli_real_escape_string($link,$element);
   }
   return $res;
}

function bioArrayToMysqlInsert($table,$insData,$link){
  $fields = bioRealEscapeArray(array_keys($insData), $link);
  $columns = implode(", ",$fields);
  $escaped_values = bioRealEscapeArray($insData, $link);
  $values  = implode(", ", AddApexArray($escaped_values));
  $sql = "Insert into `$table`($columns) VALUES ($values)";
  return $sql;
}

function bioArrayToMysqlReplaceInto($table,$insData,$link){
  $fields = bioRealEscapeArray(array_keys($insData), $link);
  $columns = implode(", ",$fields);
  $escaped_values = bioRealEscapeArray($insData, $link);
  $values  = implode(", ", AddApexArray($escaped_values));
  $sql = "Replace INTO `$table`($columns) VALUES ($values)";
  return $sql;
}

function AddApexArray($array){
  foreach ($array as $element){
    $result[]="'".$element."'";
  }
  return $result;
}

function bioMysqliConnect($ip, $username, $password, $db){
    $mysqli = new mysqli($ip, $username, $password, $db);
    if ($mysqli -> connect_errno){
        printf("ConnectionError: %s\n", $mysqli->connect_error);
        exit();
    }
    $mysqli->set_charset('utf8');
    return $mysqli;
}

function bioMysqliSelect($link,$query){
  $results=array();
  if ($res = mysqli_query($link,$query)) {
    if (mysqli_num_rows($res)){
      while($row=mysqli_fetch_array($res,MYSQLI_ASSOC)){
        $results[]=$row;
      }
    }
    return $results;
    mysqli_free_result($result);
  } else{
    echo("Mysql Error description: " . mysqli_error($link)."\n");
    return false;
  }
}

function bioMysqliQuery($link,$query){
  $results=array();
  if ($res = mysqli_query($link,$query)) return true;
  else {
    echo("Mysql Error description: " . mysqli_error($link)."\n");
    return false;
  }
}

function bioUpdateById($table,$insData,$search,$link){
    foreach ($insData as $key=>$value){
      $set[]= $key."='".mysqli_real_escape_string($link,$value)."'";
    }
    $sets=implode(", ",$set);
    $query="Update ".$table." set ".$sets." where ".key($search)."='".mysqli_real_escape_string($link,$search[key($search)])."'";
    return $query;
}

function bioSearchIdIfExists($table,$return,$search,$link){
  $table=mysqli_real_escape_string($link,$table);
  $return=mysqli_real_escape_string($link,$return);
  $key=mysqli_real_escape_string($link,key($search));
  $value=mysqli_real_escape_string($link,$search[key($search)]);

  $query="select ".$return." from ".$table." where ".$key."='".$value."'";
  //echo $query."\n";
  $result=bioMysqliSelect($link,$query);
  //print_r($result);
  if(isset($result[0][$return])) return $result[0][$return];
  else return false;
}
?>
