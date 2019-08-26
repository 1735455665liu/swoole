<?php

//建立数据库连接
$db_user = 'root';
$db_pass = 'root';
$dbh = new PDO('mysql:host=192.168.109.1;dbname=swoole', $db_user, $db_pass);

$server = new swoole_websocket_server("0.0.0.0",9502);
$server->on('open', function($server, $req) {
    echo "connection open: {$req->fd}\n";
    $send_data = [
        'type'      => 'open',
        'fd'        => $req->fd,
        'msg'    => 'blabla'
    ];
    $server->push($req->fd,json_encode($send_data));            //将当前 fd 回传至客户端
});

$server->on('message', function($server, $frame) use($dbh) {


    echo "received message: {$frame->data}\n";
    $rev_data = json_decode($frame->data,true);
    $time = time();
    $msg = $rev_data['msg'];
    $user = $rev_data['u'];

    $from_fd = $frame->fd;
    $to_fd = $rev_data['to'];
    $type = $rev_data['type'];          // 1公聊  2私聊


    if($type==2){
        echo '私聊 to '. $to_fd;echo "\n";
        $send_data = [
            'type'  => 'msg',
            'msg'   => $msg,
            'u'     => $user,
            't'     => date('Y-m-d H:i:s'),
        ];
        $server->push($to_fd, json_encode($send_data));
    }else{
        echo '公聊';echo "\n";
        //消息入库
        $sql = "insert into chat_message (`msg`,`add_time`,`nick_name`)  values ('{$msg}',$time,'{$user}')";
        $dbh->exec($sql);
        $errcode = $dbh->errorCode();
        $errinfo = $dbh->errorInfo();

        echo 'ErrCode: '.$errcode;echo "\n";

        //检测sql错误
        if($errcode !== '00000' ){
            echo 'Errcode:' . $errcode;echo "\n";
            echo 'Message: '.$errinfo[2];
            exit();
        }

        $send_data = [
            'type'  => 'msg',
            'msg'   => $msg,
            'u'     => $user,
            't'     => date('Y-m-d H:i:s'),
        ];

        //向所有人推送消息
        foreach($server->connections as $fds){
            $server->push($fds, json_encode($send_data));
        }
    }

});

$server->on('close', function($server, $fd) {
    echo "connection close: {$fd}\n";
});

$server->start();

