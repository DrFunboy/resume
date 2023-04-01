<?php

ignore_user_abort(true);
set_time_limit(0);

$modx->setOption('bitConsumerKey', 'XXX');
$modx->setOption('bitConsumerSecret', 'XXX');
$modx->setOption('bitHref', 'https://api.bitbucket.org/2.0/repositories/scrm-custom/scrmcustom/src');

$json = array(
    'post' => $data = $_POST, //json_decode( file_get_contents('php://input'),true );
);
if ( $data["folder"] ) {
    $folder = $data["folder"];
    $handle = fopen($folder, "r");
    $content = fread($handle, filesize($folder));
    fclose($handle);
} else if ( $data["resid"] ) {
    $res = $modx->getObject('modResource', $data["resid"]);
    $folder = $res->get("uri");
    if ( substr($folder,-1,1) == "/"){die();}
    $folder = "resources/".$folder;
    $content = $res->get("content");
} else if ( $data["tmplid"] ) {
    $tmpl = $modx->getObject('modTemplate', $data["tmplid"]);
    $folder = $tmpl->get("templatename");
    $folder = "templates/" . $folder . ".html";
    $content = $tmpl->get("content");
} else if ( $data["pluginid"]){
    $res = $modx->getObject('modPlugin', $data["pluginid"]);
    $folder = $res->get("name");
    $folder = "plugins/" . $folder . ".php";
    $content = $res->get("plugincode");
} else if ( $data["chunkid"]){
    $res = $modx->getObject('modChunk', $data["chunkid"]);
    $folder = $res->get("name");
    $folder = "chunks/" . $folder . ".html";
    $content = $res->get("snippet");
}

if ( $_POST["folder"] && $_POST["commit"] && $_POST["content"] ) {
    $folder = $_POST["folder"];
    $data["commit"] = $_POST["commit"];
    $content = $_POST["content"]; //json_decode($_POST["content"]);
}
$json['folder'] = $folder = $_SERVER['HTTP_HOST']."/".$folder;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://bitbucket.org/site/oauth2/access_token');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
curl_setopt($ch, CURLOPT_USERPWD, $modx->getOption('bitConsumerKey') . ':' . $modx->getOption('bitConsumerSecret') );

$headers = array();
$headers[] = 'Content-Type: application/x-www-form-urlencoded';
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$result = json_decode(curl_exec($ch));
curl_close($ch);
$access = $result->access_token;

$json['content'] = $content;
$curl = curl_init();

curl_setopt_array($curl, array(
    CURLOPT_URL => $modx->getOption('bitHref'),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => array(
        $folder => $content,
        'files' => $folder,
        'message' => $data["commit"]    
      ),
      CURLOPT_HTTPHEADER => array(
        'Authorization: Bearer ' . $access
      ),
));
$gitData = curl_exec($curl);
$json['bitbacket'] = json_decode($gitData, true);
if ($json['bitbacket']['type'] == 'error') {
    $modx->log(modX::LOG_LEVEL_ERROR, $gitData);
}
curl_close($curl);
