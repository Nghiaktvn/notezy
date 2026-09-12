<?php
declare(strict_types=1); require __DIR__ . '/../../shared/bootstrap.php';
$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/'; if($path==='/health'||$path==='/ready') svc_health('user',true);
$id=svc_bearer_user(); $db=svc_db();
if($path==='/me'&&$_SERVER['REQUEST_METHOD']==='GET'){ $q=$db->prepare('SELECT id,username,firstname,lastname,email,avatar,theme,language FROM users WHERE id=?');$q->execute([$id]);$user=$q->fetch();if(!$user)svc_json(404,['error'=>'USER_NOT_FOUND']);svc_json(200,['data'=>$user,'timezone'=>'Asia/Ho_Chi_Minh']); }
svc_json(404,['error'=>'ROUTE_NOT_FOUND']);
