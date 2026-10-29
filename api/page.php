<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store, max-age=0'); header('X-Content-Type-Options: nosniff');
$slug=trim((string)($_GET['slug']??'about'));
if(!in_array($slug,['about','services'],true)){http_response_code(404);echo json_encode(['ok'=>false,'message'=>'صفحه پیدا نشد.'],JSON_UNESCAPED_UNICODE);exit;}
try{$q=db()->prepare('SELECT * FROM pages WHERE slug=?');$q->execute([$slug]);$page=$q->fetch(PDO::FETCH_ASSOC);if(!$page){http_response_code(404);echo json_encode(['ok'=>false,'message'=>'صفحه پیدا نشد.'],JSON_UNESCAPED_UNICODE);exit;}$services=db()->query('SELECT title,description,icon FROM services ORDER BY sort_order,id')->fetchAll(PDO::FETCH_ASSOC);echo json_encode(['ok'=>true,'site'=>['name'=>SITE_NAME,'logo'=>'assets/logo.webp'],'page'=>['slug'=>$slug,'title'=>(string)$page['title'],'content'=>(string)$page['content'],'meta_title'=>(string)$page['meta_title'],'meta_description'=>(string)$page['meta_description']],'services'=>$slug==='services'?$services:[]],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT);}catch(Throwable $e){http_response_code(500);echo json_encode(['ok'=>false,'message'=>'دریافت محتوا ناموفق بود.'],JSON_UNESCAPED_UNICODE);}
