<?php namespace ProcessWire;

/**
 * Class RepeaterMatrixPage
 *
 * ProcessWire ProFields Repeater Matrix Page, a Page class used by Repeater Matrix items.
 *
 * THIS IS PART OF A COMMERCIAL MODULE: DO NOT DISTRIBUTE.
 * This file should NOT be uploaded to GitHub or available for download on any public site.
 *
 * Copyright 2023 by Ryan Cramer Design, LLC
 * ryan@processwire.com
 *
 * PWPFRMPA
 *
 */

class RepeaterMatrixPageArray extends RepeaterPageArray {

	/**
	 * @todo Coming soon
	 * 
	 * @param RepeaterMatrixPage $parent
	 * @return PageArray
	 * 
	 */
	public function depthChildren(RepeaterMatrixPage $parent) {
		$items = new PageArray();
		$recording = false;
		$findDepth = $parent->depth+1;
		foreach($this as $item) {
			/** @var RepeaterMatrixPage $item */
			if($item->id === $parent->id) {
				$recording = true;
			} else if($recording) {
				if($item->depth == $findDepth) {
					$items->add($item);
				} else {
					break;
				}
			}
		}
		return $items;
	}

	/**
	 * @todo Coming soon
	 * 
	 * @param RepeaterMatrixPage $child
	 * @return null|NullPage
	 * 
	 */
	public function depthParent(RepeaterMatrixPage $child) {
		$parent = new NullPage();
		$findDepth = $child->depth-1;
		if($findDepth < 1) return $parent;
		$lastParent = null;
		foreach($this as $item) {
			/** @var RepeaterMatrixPage $item */
			if($item->depth == $findDepth) {
				$lastParent = $item;
			} else if($child->id == $item->id) {
				if($child->depth == ($lastParent->depth+1) && $lastParent) {
					$parent = $lastParent;
					break;
				}
			}
		}
		return $parent;
	}
}