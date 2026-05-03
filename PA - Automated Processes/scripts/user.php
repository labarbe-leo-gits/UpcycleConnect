<!-- Regroups all automated processes for the users in UpcycleConnect -->
<?php
include_once __DIR__ . '/../config/base.php';
include_once __DIR__ . '/../config/db.php';

// Ban lifting
function liftBan($banId){
    askAPI('/ban/' . $banId, 'DELETE');
}

$bans = askAPI('/ban', 'GET');
$bans = json_decode($bans);

foreach($bans as $ban){
    if(strtotime($ban->end_date) < time()){
        liftBan($ban->id);
    }
}

// Reset quota LLM
$users = askAPI('/user', 'GET');
$users = json_decode($users);

foreach($users as $user){
    askAPI('/users/' . $user->id . '/llm', 'PATCH', ['usage_delta' => 0]);
}

?>