$ch = curl_init("https://raw.githubusercontent.com/varhan1/sim_spanla_backend/main/public/logo.ico");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$icoData = curl_exec($ch);
file_put_contents("logo.ico", $icoData);
curl_close($ch);
