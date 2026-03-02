<?php namespace ProcessWire;
chdir('/var/www/html/public');
require 'index.php';

$users = wire('users');
$pages = wire('pages');
$super = $users->get('name=maximus|admin');
if($super && $super->id) $users->setCurrentUser($super);

$examples = [
  'hotel' => $pages->get('template=hotel, include=all'),
  'tour' => $pages->get('template=tour, include=all'),
  'article' => $pages->get('template=article, include=all'),
  'place' => $pages->get('template=place, include=all'),
];

foreach($examples as $k => $p) {
  if(!$p || !$p->id) { echo "[$k] not found\n"; continue; }
  echo "\n=== {$k} id={$p->id} title={$p->title} ===\n";
  foreach(['region','city','section','date','summary','content','images','hotel_info','hotel_description','hotel_amenities','tour','tour_price','tour_itinerary'] as $f) {
    if(!$p->hasField($f)) continue;
    $v = $p->getUnformatted($f);
    echo "-- {$f}: " . get_debug_type($v) . "\n";
    if($v instanceof Page) {
      echo "   page={$v->id} {$v->title}\n";
    } elseif($v instanceof PageArray) {
      $first = $v->count() ? $v->first() : null;
      echo "   count={$v->count()}";
      if($first) echo " first={$first->id} {$first->title}";
      echo "\n";
    } elseif($v instanceof Pageimages) {
      echo "   count={$v->count()}";
      if($v->count()) echo " first={$v->first()->basename}";
      echo "\n";
    } elseif(is_object($v)) {
      echo "   class=" . get_class($v) . "\n";
      try { echo "   json=" . json_encode($v, JSON_UNESCAPED_UNICODE) . "\n"; } catch(\Throwable $e) {}
    } elseif(is_array($v)) {
      echo "   " . json_encode($v, JSON_UNESCAPED_UNICODE) . "\n";
    } else {
      $s = (string)$v;
      $s = preg_replace('/\s+/', ' ', $s);
      echo "   " . mb_substr($s, 0, 200) . "\n";
    }
  }
}
