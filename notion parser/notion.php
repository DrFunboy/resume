<?php
// ini_set('error_reporting', E_ALL);
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);

$mode = $_POST["mode"];
$cfg = getClubConfig("notion_help", true);
global $apiKey;
$apiKey = $cfg['apiKey'];
if ( empty($apiKey) ) dieJSON("empty apiKey");

global $notion_header;
$notion_header = array(
    "Authorization: Bearer {$apiKey}",
    "Content-Type: application/json",
    "Notion-Version: 2022-02-22"    
);

global $scrmFiles;
    $scrmFiles = $modx->getService('scrmfiles', 'scrmFiles', CRM_PATH);
    $scrmFiles->getS3();

function getChildren($pageId){
    global $apiKey;
    global $notion_header;
    global $scrmFiles;
    $list = [];
    
    if (!defined("PARENT")) define("PARENT", $pageId);
    
    $curl = curl_init("https://api.notion.com/v1/blocks/$pageId/children?page_size=100");
    curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => $notion_header
    ));
    $blocks = getClubJSON(curl_exec($curl));
    curl_close($curl);
    
    $i = 1;
    foreach($blocks["results"] as $result) {
        $result["menuindex"] = $i;
        $i++;
        if ($result["type"] == "image") {
            $tmpfname = tempnam(sys_get_temp_dir(), "notion_img");
            $dest_file = fopen($tmpfname, 'w');
            fwrite($dest_file, file_get_contents($result["image"]["file"]["url"]));
            $scrmFiles->putS3($tmpfname, "_help/".PARENT."/{$result['id']}.png");
            fclose($dest_file);
        } else if ($result["type"] == "link_to_page"){
            $curl = curl_init("https://api.notion.com/v1/pages/".$result["link_to_page"]["page_id"]);
            curl_setopt_array($curl, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_HTTPHEADER => $notion_header
            ));
            $result["extended"] = getClubJSON(curl_exec($curl));
            curl_close($curl);
        }
        
        if ($result["has_children"]){
            $deep = getChildren($result["id"]);
            $result["children"] = $deep;
        } 
        
        $list[ $result["id"] ] = $result;
    }
    
    return $list;
}

if ($mode == "css"){
    $css = $_POST["newCss"];
    $css = preg_replace('/#.*/', '', $css); // удаляем строки начинающиеся с #
    $css = preg_replace('#//.*#', '', $css); // удаляем строки начинающиеся с //
    $css = preg_replace('#/\*(?:[^*]*(?:\*(?!/))*)*\*/#', '', $css); // удаляем многострочные комментарии /* */
    $css = str_replace(array(
        "html,\r\nbody",
        "html",
        "body",
        "*",
        "teal",
    ), array(
        "#notion-page",
        "#notion-page",
        "#notion-page",
        "#notion-page *",
        "green",
    ), $css);
    $pattern = '[\n(?!\s)(?!\})(?!@)(?!\/)(?!#notion-page)(\w*)]';
    $replacement = "\n#notion-page $1";
    $css = preg_replace($pattern, $replacement, $css);
    //$css = str_replace(array("\r","\n", "\t"), "", $css);
    
    $tmpfname = tempnam(sys_get_temp_dir(), "notion_css");
    $dest_file = fopen($tmpfname, 'w');
    fwrite($dest_file, $css);
    fclose($dest_file);
    $scrmFiles->putS3($tmpfname, "_help/style.css", array(
        'ContentType'  => 'text/css'
    ));
    
    $json["answ"] = "OK";
} elseif ($mode == "sync") {
    $tableId = $cfg['tableId'];
    
    $curl = curl_init("https://api.notion.com/v1/databases/$tableId/query");
    curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_HTTPHEADER => $notion_header
    ));
    $tableData = getClubJSON(curl_exec($curl));
    curl_close($curl);
    
    if ($tableData["object"] == "error") dieJSON($tableData["message"]);
    foreach ($tableData["results"] as $result){
        $visibility = [];
        foreach($result["properties"]["Visibility"]["multi_select"] as $client){
            $visibility[] = $client["name"];
        }
        
        $page = array(
            "edited" => date("Y-m-d", strtotime($result["last_edited_time"])),
            "id" => $result["id"],
            "name" => $result["properties"]["Name"]["title"][0]["plain_text"],
            "sync" => $result["properties"]["Sync Date"]["date"]["start"],
            "visibility" => implode(", ",$visibility),
            "created" => $result["created_time"]
        );

        if ($page["sync"] < $page["edited"]) {
            $rawblocks = getChildren($page["id"]);
            $page["blocks"] = $rawblocks;
            
            $tmpfname = tempnam(sys_get_temp_dir(), "notion_json");
            $dest_file = fopen($tmpfname, 'w');
            fwrite($dest_file, json_encode(array(
                "name" => $page["name"],
                "blocks" => $page["blocks"]
            )));
            $scrmFiles->putS3($tmpfname, "_help/{$page['id']}/index.json");
            fclose($dest_file);
            
            $curl = curl_init();
            curl_setopt_array($curl, array(
            	CURLOPT_URL => 'https://api.notion.com/v1/pages/'.$result["id"],
            	CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
            	CURLOPT_CUSTOMREQUEST => 'PATCH',
            	CURLOPT_POSTFIELDS => json_encode(array(
            	  "properties" => array(
                        "Sync Date" => array(
                            "date" => array(
                                "start" => date("Y-m-d")
                            )
                        )
                    )
            	)),
            	CURLOPT_HTTPHEADER => $notion_header
            ));
            curl_exec($curl);
            curl_close($curl);
            $page["sync"] = date("Y-m-d");
        }
        $json["pages"][] = $page;
    }
} elseif (!empty($_GET["alias"])){
    $json = getClubJSON(clubTmpl(file_get_contents("сохраненный json на Ya.Cloud Storage")));
}