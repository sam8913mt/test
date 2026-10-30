<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
$slug = trim((string)($_GET['slug'] ?? ''));
if ($slug === '' || mb_strlen($slug, 'UTF-8') > 160) { http_response_code(400); echo json_encode(['ok'=>false,'message'=>'شناسه ساختمان معتبر نیست.'], JSON_UNESCAPED_UNICODE); exit; }
try {
 $q=db()->prepare('SELECT * FROM projects WHERE slug=? OR CAST(id AS TEXT)=? ORDER BY (slug=?) DESC LIMIT 1'); $q->execute([$slug,$slug,$slug]); $p=$q->fetch(PDO::FETCH_ASSOC);
 if(!$p){http_response_code(404);echo json_encode(['ok'=>false,'message'=>'ساختمان پیدا نشد.'],JSON_UNESCAPED_UNICODE);exit;}
 if(!(int)($p['is_published']??1) && !is_admin()){http_response_code(404);echo json_encode(['ok'=>false,'message'=>'ساختمان پیدا نشد.'],JSON_UNESCAPED_UNICODE);exit;}
 $alts=json_decode((string)($p['images_alt']??'{}'),true); if(!is_array($alts)) $alts=[];
 $images=[]; $imageAlts=[]; foreach(all_project_images($p) as $img) if(is_file(UPLOAD_DIR.$img)) { $images[]=UPLOAD_URL.$img; $imageAlts[]=(string)($alts[$img]??''); }
 $building=building_export($p); unset($building['updated_at']); $building['images']=$images; $building['image_alts']=$imageAlts;
 if((int)($p['is_published']??1) && !is_admin()) header('Cache-Control: public, max-age=60');   // published pages may be cached briefly; drafts/admin previews never
 echo json_encode(['ok'=>true,'site'=>['name'=>SITE_NAME,'url'=>site_url(),'logo'=>'assets/logo.webp'],'building'=>$building,'phone'=>setting('phone',SITE_PHONE)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT);
} catch(Throwable $e){log_exception($e,'building.php');http_response_code(500);echo json_encode(['ok'=>false,'message'=>'دریافت اطلاعات ساختمان ناموفق بود.'],JSON_UNESCAPED_UNICODE);}
