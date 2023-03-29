<?
$token = $_GET["token"];
if (empty ($token) ) die("Empty token");
$content = file_get_contents('php://input');
$tokens = json_decode( file_get_contents('tokens.json'), true);

$curl = curl_init();
curl_setopt_array($curl, array(
	CURLOPT_URL => $tokens[$token],
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_ENCODING => '',
	CURLOPT_TIMEOUT => 0,
	CURLOPT_CUSTOMREQUEST => 'POST',
	CURLOPT_POSTFIELDS => $content,
	CURLOPT_HTTPHEADER => getallheaders(),
));
$response = curl_exec($curl);
curl_close($curl);
echo ($response);