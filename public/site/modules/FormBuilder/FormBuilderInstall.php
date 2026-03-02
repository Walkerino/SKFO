<?php namespace ProcessWire;

/**
 * ProcessWire Form Builder Module Installer
 *
 * This is kept in a separate file since there's a lot of code and don't need it
 * taking up space when it's not needed on every request. 
 *
 * Note that the tables are installed/uninstalled by FormBuilderMain.
 *
 * Copyright (C) 2023 by Ryan Cramer Design, LLC
 * 
 * PLEASE DO NOT DISTRIBUTE
 * 
 */

class FormBuilderInstall extends Wire {

	protected $permissionArray = array(
		'form-builder' => 'Access Form Builder admin page',
		'form-builder-add' => 'Add new or import Form Builder forms',
	);

	/**
	 * @var array
	 * 
	 */
	protected $created = array();

	/**
	 * @var array
	 * 
	 */
	protected $removed = array();
	
	/*** UPGRADE ***************************************************************************************/
	
	/**
	 * Upgrade
	 *
	 * @param string|int $fromVersion
	 * @param string|int $toVersion
	 * @return bool
	 *
	 */
	public function upgrade($fromVersion = '', $toVersion = '') {
		if($fromVersion && $toVersion) {} // ignore
		require_once(__DIR__ . '/FormBuilderEntries.php');
		$verbose = $this->wire()->config->debug || $this->wire()->user->isSuperuser();
		$result = true;
		try {
			FormBuilderEntries::_upgrade($this->wire()->database, $verbose);
		} catch(\Exception $e) {
			if($verbose) $this->warning($e->getMessage());
			$result = false;
		}
		return $result;
	}

	
	/*** INSTALL ***************************************************************************************/

	/**
	 * Install the form builder 
	 * 
	 * return array
	 *
	 */
	public function install() {

		$this->installTables();
		$fieldgroup = $this->installFieldgroup();
		$template = $this->installTemplate($fieldgroup);
		$this->installPage($template);
		$this->installTemplateFile();
		$this->installPermissions();
		
		if(!empty($this->created['fieldgroup'])) {
			$this->message($this->_('Please click the “submit” button on this screen to complete the Form Builder installation.')); 
		}
		
		foreach($this->created as $name => $value) {
			if($value instanceof Page) $value = $value->path();
			$this->message(sprintf($this->_('Created %1$s - %2$s'), $name, $value)); 
		}
	}

	/**
	 * Install database tables
	 * 
	 */
	public function installTables() {
		
		$engine = $this->wire()->config->dbEngine;
		$charset = $this->wire()->config->dbCharset;
		$database = $this->wire()->database;

		$sql =
			"CREATE TABLE " . FormBuilderMain::formsTable . " (" .
			"id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, " .
			"name VARCHAR(128) NOT NULL, " .
			"data MEDIUMTEXT NOT NULL, " .
			"UNIQUE name (name)" .
			") ENGINE=$engine DEFAULT CHARSET=$charset ";

		try {
			$database->exec($sql);
			$this->created['forms table'] = FormBuilderMain::formsTable;
		} catch(\Exception $e) {
			$this->wire()->modules->error($e->getMessage());
		}

		require_once(__DIR__ . '/FormBuilderEntries.php'); 
		$table = FormBuilderEntries::_install($database);
		if($table) $this->created['entries table'] = $table;
	}

	/**
	 * Install form-builder fieldgroup
	 * 
	 * @return Fieldgroup
	 * 
	 */
	public function installFieldgroup() {
		// create the fieldgroup to be used by the form-builder template
		$fieldgroup = $this->wire()->fieldgroups->get(FormBuilderMain::name);
		if($fieldgroup) return $fieldgroup;
		$fieldgroup = new Fieldgroup();
		$fieldgroup->name = FormBuilderMain::name;
		$fieldgroup->add($this->wire()->fields->get('title'));
		$fieldgroup->save();
		$this->created['fieldgroup'] = $fieldgroup->name;
		return $fieldgroup;
	}

	/**
	 * Install form-builder template
	 * 
	 * @param Fieldgroup $fieldgroup
	 * @return Template
	 * 
	 */
	public function installTemplate(Fieldgroup $fieldgroup) {
		// create the template used by the form-builder page
		$template = $this->wire()->templates->get(FormBuilderMain::name);
		if($template) return $template;
		$template = new Template();
		$template->name = FormBuilderMain::name;
		$template->fieldgroup = $fieldgroup;
		$template->slashUrls = 1;
		$template->urlSegments = 1;
		$template->noGlobal = 1;
		$template->flags = Template::flagSystem;
		$template->save();
		$this->created['template'] = $template->name;
		return $template;
	}

	/**
	 * Install /site/templates/form-builder.php template file 
	 * 
	 * @return string
	 * @throws WireException
	 * 
	 */
	public function installTemplateFile() {
		// install the form-builder template file	
		$config = $this->wire()->config;
		$basename = FormBuilderMain::name . '.php';
		$destPath = $config->paths->templates;
		$destFile = $destPath . $basename;
		$destUrl = $config->urls->templates . $basename;
		
		if(file_exists($destFile)) return $destFile;

		$srcFile = dirname(__FILE__) . '/' . $basename;
		$srcUrl = str_replace($config->paths->root, '/', $srcFile);
		
		if(is_writable($destPath) && @copy($srcFile, $destFile)) {
			$this->created['template file'] = $basename;
		} else {
			$this->error(sprintf(
				$this->_('Unable to copy template file %1$s to %2$s - please copy this file manually'),
				$srcUrl, $destUrl
			));
		}
		
		return $destFile;
	}

	/**
	 * Install /form-builder/ page
	 * 
	 * @param Template $template
	 * @return NullPage|Page
	 * @throws WireException
	 * 
	 */
	public function installPage(Template $template) {
		// create the form-builder page
		$page = $this->wire()->pages->get("template=$template");
		if($page->id) return $page;
		
		$noParents = (int) $template->noParents;
		if($noParents > 0) $template->noParents = 0; // temporarily
		
		$page = new Page();
		$page->template = $template;
		$page->name = FormBuilderMain::name;
		$page->parent = '/';
		$page->addStatus(Page::statusHidden);
		$page->title = 'Form Builder';
		$page->save();
		$this->created['page'] = $page->path;
		
		// specify that this template may no longer be used for new pages
		if(!$noParents) {
			$template->noParents = 1;
			$template->save();
		}

		return $page;
	}

	/**
	 * Install permissions 
	 * 
	 * @return array
	 *
	 */
	public function installPermissions() {

		$permissions = $this->wire()->permissions;
		$added = array();

		foreach($this->permissionArray as $name => $title) {
			$permission = $permissions->get($name);
			if($permission && $permission->id) {
				// permission already exists
				if($name === 'form-builder' && $permission->title != $title) {
					$permission->title = $title;
					$permission->save();
				}
			} else try {
				// create new permission
				$permission = $permissions->add($name);
				$permission->title = $title;
				$permission->save();
				$added[] = $name;
			} catch(\Exception $e) {
				$this->warning($e->getMessage());
			}
		}

		if(count($added)) {
			$this->created['permissions'] = implode(', ', $added);
		}
		
		return $added;
	}

	/*** UNINSTALL ******************************************************************************/

	/**
	 * Uninstall form builder
	 *
	 */
	public function uninstall() {
		$this->uninstallPage();
		$this->uninstallTemplate();
		$this->uninstallFieldgroup();
		$this->uninstallTemplateFile();
		$this->uninstallPermissions();
		$this->uninstallCache();
		$this->uninstallTables();
		
		foreach($this->removed as $name => $value) {
			$this->message(sprintf($this->_('Removed %1$s - %2$s'), $name, $value));
		}
	}

	/**
	 * Uninstall the tables
	 *
	 */
	public function uninstallTables() {
		$database = $this->wire()->database;
		try {
			$database->exec("DROP TABLE " . FormBuilderMain::formsTable);
			$this->removed['forms table'] = FormBuilderMain::formsTable;
		} catch(\Exception $e) {
			// just catch, no need to do anything else
		}
		
		$table = FormBuilderEntries::_uninstall($database);
		if($table) $this->removed['entries table'] = $table;
	}
	/**
	 * Uninstall template
	 *
	 */
	public function uninstallTemplate() {
		$template = $this->wire()->templates->get(FormBuilderMain::name);
		if(!$template) return;
		$template->flags = Template::flagSystemOverride;
		$template->flags = 0;
		$this->wire()->templates->delete($template);
		$this->removed['template'] = $template->name;
	}

	/**
	 * Uninstall template file
	 *
	 */
	public function uninstallTemplateFile() {
		$templateFile = $this->wire()->config->paths->templates . FormBuilderMain::name . '.php';
		if(!is_file($templateFile)) return;
		if(@unlink($templateFile)) {
			$this->removed['template file'] = basename($templateFile);
		} else {
			$this->warning("Unable to delete template file $templateFile - please delete it manually");
		}
	}

	/**
	 * Uninstall fieldgroup
	 *
	 */
	public function uninstallFieldgroup() {
		$fieldgroup = $this->wire()->fieldgroups->get(FormBuilderMain::name);
		if(!$fieldgroup) return;
		$this->wire()->fieldgroups->delete($fieldgroup);
		$this->removed['fieldgroup'] = $fieldgroup->name;
	}

	/**
	 * Uninstall page
	 *
	 */
	public function uninstallPage() {
		$page = $this->wire()->pages->get("template=" . FormBuilderMain::name);
		if(!$page->id) return;
		$page->delete();
		$this->removed['page'] = $page->path;
	}

	/**
	 * Uninstall permissions
	 *
	 */
	public function uninstallPermissions() {
		// remove permissions
		$deletedPermissions = array();
		foreach($this->permissionArray as $name => $title) {
			$permission = $this->permissions->get($name);
			if(!$permission || !$permission->id) continue;
			$permission->delete();
			$deletedPermissions[] = $name;
		}
		if(count($deletedPermissions)) {
			$this->removed['permissions'] = implode(', ', $deletedPermissions);
		}
	}

	/**
	 * Uninstall cache directories
	 * 
	 */
	public function uninstallCache() {
		$dirs = array(
			'cache path' => $this->wire()->config->paths->cache . 'FormBuilder/',
		);

		foreach($dirs as $name => $dir) {
			if(is_dir($dir)) {
				wireRmdir($dir, true);
				$this->removed[$name] = $dir;
			}
		}

		// Note: we do not remove /site/templates/FormBuilder/* or /site/assets/cache/form-builder/
		// since they may contain site-specific files that the user should determine whether or not
		// they want to delete themselves. 
	}
}