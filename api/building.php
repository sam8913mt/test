<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
$slug = trim((string)($_GET['slug'] ?? ''));
if ($slug === '' || mb_strlen($slug, 'UTF-8') > 160) { http_response_code(400); echo json_encode(['ok'=>false,'message'=>'شناسه ساختمان معتبر نیست.'], JSON_UNESCAPED_UNICODE); exit; }
try {
 $q=db()->prepare('SELECT * FROM projects WHERE slug=? OR CAST(id AS TEXT)=? LIMIT 1'); $q->execute([$slug,$slug]); $p=$q->fetch(PDO::FETCH_ASSOC);
 if(!$p){http_response_code(404);echo json_encode(['ok'=>false,'message'=>'ساختمان پیدا نشد.'],JSON_UNESCAPED_UNICODE);exit;}
 $images=[]; foreach(all_project_images($p) as $img) if(is_file(UPLOAD_DIR.$img)) $images[]=UPLOAD_URL.$img;
 echo json_encode(['ok'=>true,'site'=>['name'=>SITE_NAME,'url'=>site_url(),'logo'=>'assets/logo.webp'],'building'=>['id'=>(int)$p['id'],'title'=>(string)$p['title'],'slug'=>(string)($p['slug']?:$p['id']),'location'=>(string)$p['location'],'status'=>(string)$p['status'],'description'=>(string)$p['description'],'floors'=>(string)$p['floors'],'area'=>(string)$p['area'],'amenities'=>(string)$p['amenities'],'images'=>$images],'phone'=>setting('phone',SITE_PHONE)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT);
} catch(Throwable $e){http_response_code(500);echo json_encode(['ok'=>false,'message'=>'دریافت اطلاعات ساختمان ناموفق بود.'],JSON_UNESCAPED_UNICODE);}
