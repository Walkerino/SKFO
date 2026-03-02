<?php namespace ProcessWire;
chdir('/var/www/html/public');
require 'index.php';
$pages = wire('pages');
foreach(['/','/articles/','/hotel/','/hotels/','/reviews/','/tour/','/places/','/regions/'] as $path){
  $p=$pages->get($path);
  echo "=== {$path} id=".(int)$p->id." tpl=".($p->id?$p->template->name:'-')." ===\n";
  if(!$p->id) continue;
  try { $html=$p->render(); echo "ok bytes=".strlen($html)."\n"; }
  catch(\Throwable $e){ echo "ERR ".get_class($e).": ".$e->getMessage()." @ ".$e->getFile().":".$e->getLine()."\n"; }
}
