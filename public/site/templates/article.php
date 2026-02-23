<?php namespace ProcessWire;

$articleSlug = rawurlencode((string) $page->name);
$session->redirect('/articles/?article=' . $articleSlug);

