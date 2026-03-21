<?php namespace ProcessWire;
chdir('/var/www/html/public');
require 'index.php';

$users = wire('users');
$pages = wire('pages');
$super = $users->get('name=maximus|admin');
if($super && $super->id) $users->setCurrentUser($super);

$targets = [
  'hotel' => $pages->get('template=hotel, include=all, status<8192'),
  'tour' => $pages->get('template=tour, include=all, status<8192'),
  'article' => $pages->get('template=article, include=all, status<8192'),
  'place' => $pages->get('template=place, include=all, status<8192'),
];

foreach($targets as $name => $p) {
  if(!$p || !$p->id) continue;
  echo "\n=== {$name} id={$p->id} title={$p->title} template={$p->template->name} ===\n";
  foreach($p->template->fieldgroup as $f) {
    $fname = $f->name;
    $val = $p->getUnformatted($fname);
    $type = get_debug_type($val);
    echo "{$fname}: {$type}";
    if($val instanceof Page) echo " [{$val->id} {$val->title}]";
    if($val instanceof PageArray) echo " [count={$val->count()}]";
    if($val instanceof Pageimages) echo " [count={$val->count()}]";
    if($val instanceof TableRows) echo " [count={$val->count()}]";
    echo "\n";

    if($val instanceof ComboValue) {
      foreach(['i1','i2','i3','i4','i5','i6','i7','i8','i9','i10','i11','i12','i13','i14','i15','i16','i17'] as $k) {
        $sv = $val->get($k);
        if($sv === null || $sv === '' || $sv === [] || $sv === false) continue;
        if(is_array($sv)) echo "  {$k}: " . json_encode($sv, JSON_UNESCAPED_UNICODE) . "\n";
        else echo "  {$k}: " . (is_scalar($sv) ? (string)$sv : get_debug_type($sv)) . "\n";
      }
    }

    if(is_scalar($val) && trim((string)$val) !== '') {
      $s = preg_replace('/\s+/u', ' ', (string)$val);
      echo "  value: " . mb_substr($s, 0, 140) . "\n";
    }
  }
}
