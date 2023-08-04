<?php

$ini = parse_ini_file("./alertsys.ini");

$log_files = glob($ini['LOG_PATH'] . "/*.log");

$now_filename_arr = [];
foreach ($log_files as $filename) {
    $now_filename_arr[$filename] = [
        "line" => count(file($filename)),
        "size" => filesize($filename)
    ];
}

if (!file_exists($ini['STATUS_FILE'])) {
    echo "check file exists ...".PHP_EOL;
    file_put_contents($ini['STATUS_FILE'], serialize($now_filename_arr));
    echo "created file status and exit";
    exit;
}

$past_filename_arr = unserialize(file_get_contents($ini['STATUS_FILE']));

foreach ($now_filename_arr as $filename => $status) {

    echo "check if have new logs ...".PHP_EOL;

    if ($status['size'] > $past_filename_arr[$filename]['size']) {

        // read file && slack
        $fp = @fopen($filename, "r");
        if ($fp) {
            fseek($fp, $past_filename_arr[$filename]['size'] + 1);
            while (($buffer = fgets($fp, 4096)) !== false) {
                echo "post slack message".PHP_EOL;
                slack($buffer, $ini['SLACK_CHANNEL']);
            }
            if (!feof($fp)) {
                fclose($fp);
            }
            fclose($fp);
        }
    } else {
        echo "doesnt have any new message ...".PHP_EOL;
    }
}

file_put_contents($ini['STATUS_FILE'], serialize($now_filename_arr));

function slack($message, $channel)
{
    $ch = curl_init("https://hooks.slack.com/services/$channel");
    $data = ['payload' => json_encode(
        [
            "text" => "```\n".$message."\n```",
        ]
    )];
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}