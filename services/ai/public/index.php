<?php
declare(strict_types=1); require __DIR__ . '/../../shared/bootstrap.php';
$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/'; if($path==='/health'||$path==='/ready')svc_health('ai');
$userId=svc_bearer_user();
if (!in_array($path,['/ask','/summarize','/quiz','/flashcards','/search'],true) || $_SERVER['REQUEST_METHOD']!=='POST') svc_json(404,['error'=>'ROUTE_NOT_FOUND']);
$data=svc_input(); $message=trim((string)($data['message']??$data['content']??'')); if($message==='')svc_json(422,['error'=>'MESSAGE_REQUIRED']);
// The Python agent is the single AI orchestration point: it applies prompt
// safety, RAG hints, provider fallback and the common 17-tool registry.
$instruction=match($path){'/summarize'=>'Tóm tắt ngắn gọn bằng tiếng Việt: ','/quiz'=>'Tạo 5 câu hỏi trắc nghiệm bằng tiếng Việt từ: ','/flashcards'=>'Tạo flashcard hỏi đáp bằng tiếng Việt từ: ','/search'=>'Tìm ghi chú liên quan: ',default=>''};
$payload=['messages'=>[['role'=>'user','content'=>$instruction.$message]],'context'=>['user_id'=>$userId,'source'=>'microservice_gateway']];
$ch=curl_init(rtrim((string)svc_env('AI_AGENT_URL','http://ai_agent:8765'),'/').'/v1/chat');
curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_HTTPHEADER=>['Content-Type: application/json','X-Notezy-Agent-Secret: '.svc_env('AI_AGENT_SHARED_SECRET','')],CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE)]);
$raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);$result=json_decode((string)$raw,true);
if($raw===false||$status<200||$status>=300||empty($result['ok']))svc_json(502,['error'=>'AI_AGENT_UNAVAILABLE']);
svc_json(200,['data'=>$result['data'],'orchestrator'=>'ai_agent','user_id'=>$userId]);
