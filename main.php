<?php
require(__DIR__ . '/config.php');
$override = getenv("TICKET_FILE");
$ticketFile = $override == null ? TICKET_FILE : $override;
if(!file_exists($ticketFile)) {
    echo "Ticket file not found: " . $ticketFile . "\n";
    exit(1);
}
if (!file_exists(__DIR__ . "/fields.yml")) {
    echo "Fields file not found\n";
    exit(1);
}
ini_set("yaml.decode_php", "0");
$config = yaml_parse_file($ticketFile);
$fieldConfig = yaml_parse_file(__DIR__ . "/fields.yml");
if(!isset($config["tickets"])) {
    echo "Cannot read tickets-file\n";
    exit(1);
}
if(!isset($fieldConfig["fields"])) {
    echo "Cannot read fields-file\n";
    exit(1);
}
$fields = $fieldConfig["fields"];


foreach($config["tickets"] as $ticket) {
    echo "Creating Ticket: " . $ticket["summary"] . "\n";
    $data = [
        "summary" => $ticket["summary"],
        "description" => $ticket["description"],
        "project" => [
            "id" => $ticket["project"]
        ],
        "customFields" => [
        ]
    ];
    foreach($ticket as $k => $v) {
        if($k == "summary" OR $k == "description" OR $k == "project") {
            continue;
        }
        $field = $fields[$k] ?? null;
        if($field == null) {
            echo "Field not found: " . $k . "\n";
            continue;
        }
        if(preg_match('/^{{[a-z]+}}$/i', $v)) {
            $replaceName = substr($v, 2, -2);
            if(!isset($config["dates"][$replaceName])) {
                echo "Date not found: " . $replaceName . "\n";
                continue;
            }
            $replacer = $config["dates"][$replaceName];
            $dateScript = $replacer["script"];
            $d = new DateTime();
            foreach($dateScript as $s) {
                $d->modify($s);
            }
            $f = $d->format($replacer["format"]);
            if($replacer["milliseconds"] ?? false) {
                $f = intval($f) * 1000;
            }
            $v = $f;
        }
        $v = deepReplace($field, "{{value}}", $v);
        $data["customFields"][] = $v;
    }
    createTicket($data);
}

function createTicket($data) {
    if(defined("DRY_RUN") AND DRY_RUN) {
        echo "DRY_RUN: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
        return;
    }
    $resp = apiCall("/api/issues", $data);
    return $resp;
}

function apiCall($path, $body = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, YOUTRACK_URL . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    if($body != null) {
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    if(defined("CURL_ALLOW_SELF_SIGNED") AND CURL_ALLOW_SELF_SIGNED) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . YOUTRACK_TOKEN,
        "Content-Type: application/json"
    ]);
    $result = curl_exec($ch);
    curl_close($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerror = curl_error($ch);
    if($cerror != null OR $code != 200) {
        throw new Exception("Failed to send call: " . $cerror . "\n\n". $result);
    }
    return json_decode($result, true);
}

function deepReplace($array, $search, $replace) {
    foreach($array as $k => $v) {
        if(is_array($v)) {
            $array[$k] = deepReplace($v, $search, $replace);
        } else {
            if($v == $search) {
                $array[$k] = $replace;
            }
        }
    }
    return $array;
}