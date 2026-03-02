<?php namespace ProcessWire;

/**
 * ProcessWire ProFields Combo: initialization file
 *
 * Part of the ProFields package.
 * Please do not distribute.
 *
 * Copyright (C) 2024 by Ryan Cramer
 *
 * https://processwire.com
 *
 * @method string render()
 *
 */

if(!defined("PROCESSWIRE")) die();

require_once(__DIR__ . '/ComboLanguagesValue.php');
require_once(__DIR__ . '/ComboSelectedValue.php'); 
require_once(__DIR__ . '/ComboSettings.php');
require_once(__DIR__ . '/ComboWireTrait.php');
require_once(__DIR__ . '/ComboWireDataTrait.php');
require_once(__DIR__ . '/ComboValue.php');
require_once(__DIR__ . '/ComboField.php');
require_once(__DIR__ . '/ComboSchema.php');
require_once(__DIR__ . '/ComboSubfield.php');