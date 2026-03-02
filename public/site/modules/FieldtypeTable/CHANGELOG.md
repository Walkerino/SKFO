Table 0.3.0 - ProcessWire 3.0.239+ recommended

Various updates for compatibility with PageEditChildren module.


Table 0.2.9 - ProcessWire 3.0.229+ recommended
-----------
Expand actions support so you can also execute actions directly on individual rows,
which is often more convenient than selecting rows and then applying actions. 
 
Hold down the SHIFT key before clicking the row actions select and it will convert
any actions that usually append rows to instead prepend rows. This works with the
the Add, Clone and Paste actions when clicked at the individual row-level. 
It also works with the Select action for selecting multiple rows at once.

Removed the Vex dialogs when copying or pasting and replaced them with 
other visual cues to indicate successful copy and/or paste.   

Addied a new config option for disabling the DELETE (trash) icon on 
individual rows when individual row actions are enabled. This is because the row 
actions already include a delete action, so the trash icon can be redundant for some. 

Fixed an issue where you couldn't enter a literal `0` for integer columns, as 
it would convert it to blank.

For all non-page selection column types, a new "Settings" option was added, 
named “formattedValueType” to indicate how the value should be represented when accessed 
from a formatted page: 
  
`formattedValueType=value`is the existing/original behavior.   

`formattedValueType=title` makes it use the optional title/label (when used) rather than the value.

`formattedValueType=assoc` to format as a PHP array like:  
```php
[ 'value' => 'title' ]
```

`formattedValueType=assoc2` to format as a PHP array like:
```php
[ 
  'value' => 'item value', 
  'title' => 'item title' 
]
```

For multi-selection types, either `value` or `title` returns an array of 
selected values or titles, `assoc` returns associative array, and `assoc2`
returns array of associative arrays.
  

Table 0.2.8 - ProcessWire 3.0.229+ recommended
-----------
- Add support for selecting multiple rows and moving as a group.
- Add support for copying and pasting rows, even across different pages.
- Add support for adding multiple rows at once. 
- Add support for deleting rows as a group. 
- Add support for move-to-top and move-to-bottom
- How to use row actions: https://www.youtube.com/watch?v=pgIrMAsIvKk
- Add partial support for required columns (specify `required=1` in Settings for column)
- Add support for small inputs (when AdminThemeUikit is active)

Table 0.2.6 - ProcessWire 3.0.220+ recommended
-----------
- Fix issue with Toggle field "no" selection not working properly

Table 0.2.5 - ProcessWire 3.0.210+ recommended
-----------
- Maintenance update
- PHP 8.x deprecation notice fixes
- Updates for jQuery 3.6+
- Adds support for TextTags columns

Table 0.2.4 - ProcessWire 3.0.200+ recommended
-----------
- Adds support for InputfieldTinyMCE (requires v6.0.8 or newer)
- Converts existing CSS file to SCSS file.
- Refactoring and minor improvements throughout.
- This is the current development version, please test thoroughly.

Table 0.2.3 - ProcessWire 3.0.200+ recommended
-----------
- Adds support for File and Image columns (beta). 
  Note that the value for File columns is a `Pagefile` object, and the 
  value for Image columns is a `Pageimage` object (like with other 
  1-file file/image fields).
- Various minor improvements and fixes. 

Table 0.2.2 - Requires ProcessWire 3.0.164+
-----------
- Adds support for row cloning feature (via Robin S). You can enable
  it in the field details tab "Settings & Actions" fieldset. 
- Add new "Toggle" column type (uses PW’s InputfieldToggle).  
- Fixes issues with date formats.
- Various minor code adjustments and fixes. 
- Improvements in InputfieldTable.js. 
- Minor improvements to Uikit admin theme output. 

Table 0.2.1 - Requires ProcessWire 3.0.164+
-----------
- Various minor code adjustments and improvements

Table 0.2.0 - Requires ProcessWire 3.0.123+
-----------
- Various minor fixes and adjustments
- Adds support for @group selector queries

Table 0.1.9 - Requires ProcessWire 3.0.62+
-----------
- Add support for use with AdminThemeUikit
- Bugfixes, tweaks and optimizations

Table 0.1.8 - Requires ProcessWire 3.0.42+
-----------
- Add support for export/import per ProcessWire 3.0.71+
- Convert to using PW3 namespace
- Bugfixes, tweaks and optimizations

Table 0.1.6 & 0.1.7 - Requires ProcessWire 2.8.27+ or 3.0.27+
-------------------
- Bugfixes, tweaks and optimizations

Table 0.1.5 - Requires ProcessWire 2.8.27+ or 3.0.27+
-----------
- Various bug fixes for features added in 0.1.4

Table 0.1.4 beta - Requires ProcessWire 2.8.27+ or 3.0.27+
-----------
- Add pagination support, enabling any quantity of rows in tables. 
- Add automatic sorting support (required when using pagination).
- Add "Find" support (filtering) in field editor (requires pagination).
- Add double-click to delete all option (see setting in field config).
- Make the "Add Row" link text customizable (multi-language supported).
- Documentation updates to cover all new features (bottom of README file).
- Complete refactoring of InputfieldTable.js.

Table 0.1.3 & 0.1.2 - ProcessWire 2.7.2 or newer
-----------
- Various minor bug fixes and updates for better compatibility with PW3. 

Table 0.1.1 - Requires ProcessWire 2.6.12 or newer
-----------
- Fix issue with autocomplete input use of labelField

Table 0.1.0 - Requires ProcessWire 2.6.12 or newer
-----------
- Minor bug fixes

Table 0.0.8/0.0.9 - Requires ProcessWire 2.6.12 or newer
-----------
- Add support for autocomplete (single and multiple) Page reference fields.
- Add support for AJAX editing of table fields (2.6.12+)
- Improved support for granular selection of Table properties for filtering
  in Lister/ListerPro. 

Table 0.0.7 - Requires ProcessWire 2.6.0 or newer
-----------
- Add support for single and multi Page-reference fields:
	* Single Page types: radios, select
	* Multi Page types: checkboxes, select multiple, asmSelect (sortable)
	* Choose which pages are selectable with InputfieldSelector configuration.
- Add support for unlimited columns:
	* When combined width of your columns exceeds 100%, it creates a new
	  row within your row, enabling you to support any number of columns
	  in a single row. When active, it displays the column headings with 
	  each input, rather than as a table header. 
- Add support for rich text (CKEditor) fields:
	* Specify a regular PW CKEditor field to pull settings from. 
	* Use of CKEditor fields requires ProcessWire 2.6.4 or newer. 
- Add support for multi-language text fields:
	* Types include multi-language: text, textarea, CKEditor
	* Requires that you have installed: LanguageSupport, LanguageTabs
- Add support for multi-language table column labels. 
- Add help text for most column settings properties. 
- Add support for select-multiple options type (as alternative to checkboxes).
- Expand support for selection of Table properties in Lister/ListerPro. 
- Fix bug that got a column definitions settings mixed up with others 
  when sorting in the field settings page. 
  
Table 0.0.6
-----------
- Various bug fixes. 
- Support for additional integer types. 

Table 0.0.5
-----------
- Fix issue when cloning a Table field. 

Table 0.0.4
-----------
- First public release (beta).