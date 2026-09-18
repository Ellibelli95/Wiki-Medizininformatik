<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
$configFile = dirname(__DIR__) . '/config.php';
if (!is_file($configFile)) { http_response_code(500); echo json_encode(['error' => 'config.php fehlt.'], JSON_UNESCAPED_UNICODE); exit; }
$config = require $configFile;
try {
 $pdo = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',$config['host'],$config['port'],$config['database'],$config['charset']),$config['username'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
} catch (PDOException $e) { http_response_code(500); echo json_encode(['error'=>'Datenbankverbindung fehlgeschlagen.'],JSON_UNESCAPED_UNICODE); exit; }
$q = trim((string)($_GET['q'] ?? ''));
if ($q === '') { echo json_encode(['results'=>[]],JSON_UNESCAPED_UNICODE); exit; }
if (mb_strlen($q)>100) $q=mb_substr($q,0,100);
$like='%'.$q.'%';
$stmt=$pdo->prepare('SELECT id,title,category,url,content FROM wiki_pages WHERE title LIKE :title OR category LIKE :category OR content LIKE :content ORDER BY CASE WHEN title LIKE :title_score THEN 0 ELSE 1 END,title ASC LIMIT 30');
$stmt->execute(['title'=>$like,'category'=>$like,'content'=>$like,'title_score'=>$like]);
$results=[];
foreach($stmt->fetchAll() as $row){
 $content=trim((string)$row['content']); $position=mb_stripos($content,$q);
 if($position!==false){$start=max(0,$position-90);$snippet=mb_substr($content,$start,220);if($start>0)$snippet='…'.$snippet;if($start+220<mb_strlen($content))$snippet.='…';}else{$snippet=mb_substr($content,0,220);if(mb_strlen($content)>220)$snippet.='…';}
 $results[]=['id'=>(int)$row['id'],'title'=>$row['title'],'category'=>$row['category'],'url'=>$row['url'],'snippet'=>$snippet];
}
echo json_encode(['query'=>$q,'count'=>count($results),'results'=>$results],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);