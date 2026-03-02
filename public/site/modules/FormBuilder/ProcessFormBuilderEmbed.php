<?php namespace ProcessWire;

/**
 * This view serves as the contents of the ProcessFormBuilder 'embed' tab. 
 *
 */

if(!defined("PROCESSWIRE")) throw new WireException("This file may not be accessed directly "); 

/** @var FormBuilderForm $form */
/** @var string $embedTag */
/** @var Sanitizer $sanitizer */
/** @var Config $config */

$forms = wire()->modules->get('FormBuilder');
$embedFields = $forms->embedFields;
$embedFieldsStr = '';

$fields = wire()->fields;
foreach($embedFields as $field_id) {
	$field = $fields->get((int) $field_id);
	if(!$field) continue; 
	$embedFieldsStr .= "<u>$field->label</u>, ";
}

$embedFieldsStr = rtrim($embedFieldsStr, ", "); 
$cacheNote = __('Please note: Avoid caching of form output (like with ProCache or template cache) when possible. If the output of your form (or page that it appears on) must be cached, check the box on the "Settings" tab to "Disable session tracking and CSRF protection."', __FILE__);
$dExportURL = wire()->pages->get("template=" . FormBuilderMain::name)->url . $form->id . "/?export_d=1";

function pwfbMarkupWithCode($o) {
	if(strpos($o, '<') !== false) $o = wire()->sanitizer->entities($o);
	return str_replace(array('&lt;', '&gt;'), array('<code>&lt;', '&gt;</code>'), $o);
}

?>

<div class='ProcessFormBuilderAccordion'>
	
	<h5><a href='#'><b><?php echo __('Important Notes', __FILE__); ?></b></a></h5>
	<div>
	<p>	
	<?php
	echo '<b>' . __('Which embed method should you use?') . '</b> ';
	echo __('We recommend using embed method C when/where possible, as it provides the best balance of flexibility, customization and features.') . ' ';
	echo __('If you need to embed a form somewhere without editing any template files, you should choose embed method A.') . ' ';
	echo __('Embed method D should be considered if you must have full markup control, but note that it may limit what form actions/plugins you can use, and can make it more difficult to modify your form later.');
	?>
	</p>	
	<p><b>
	<?php
	echo '<b>' . __('Regardless of which embed method you choose, be sure to thoroughly test your form on the front-end of your site before before assuming everything works.', __FILE__) . '</b>';
	?>
	</b>
	<?php	
	echo __('In particular, submit the form at least twice and verify the submitted form entry is saved (and sent) where you expect it to.', __FILE__);
	echo ' ' . __('Always thoroughly test any new forms or changes to existing forms. Likewise, test all forms any time a new FormBuilder or ProcessWire version has been introduced.', __FILE__);
	?>
	</p>
	<p class='detail'>	
	<?php echo __('To continue, please choose an embed method below.'); ?>
	</p>
	</div>
	
	<h5><a href='#'><b><?php echo __('Option A: Easy Embed', __FILE__); ?></b></a></h5>
	<div>
		<?php if(count($embedFields)): ?>

		<p>
		<b><?php echo __('Paste a tag into your text where you want the form to appear.', __FILE__); ?></b>
		<?php echo sprintf(__('This is the easiest method and requires you do nothing other than edit a page and paste in (or type) a tag. You can just copy and paste the following tag where you want your form to appear in %s.', __FILE__), $embedFieldsStr); ?>
		</p>

		<p><textarea class='code' rows='1'><?php echo $embedTag . '/' . $form->name; ?></textarea></p>

		<p>
		<?php echo __('Note that the tag above must be pasted (or typed) into a paragraph or a headline (p, h1, h2, h3, h4) and be the only thing in it.', __FILE__); ?>
		<?php echo __('Save the page and view it, and you should see your form.', __FILE__); ?>
		</p>

		<p class='detail'><?php echo __('If you want to support this easy embed option in other fields, you may add more from the Form Builder module settings.', __FILE__); ?></p>
		
		<?php else: ?>

		<p><?php echo __('This embed option cannot be used because no embed fields have been defined in your Form Builder module settings. Please edit the Form Builder module settings and check the box for at least one field.', __FILE__); ?></p>

		<?php endif; ?>
	</div>

	<h5><a href='#'><b><?php echo __('Option B: Template Embed', __FILE__); ?></b></a></h5>
	<div>
		<p>
		<b><?php echo __('Paste an embed code into your template file.', __FILE__); ?></b>
		<?php echo __('Use this option if you want the form to be loaded from a template file rather than from a field.', __FILE__); ?> 
		<?php echo __('This provides you with more defined placement options than option A, but requires editing a template file.', __FILE__); ?> 
		<?php echo __('Copy and paste the following directly into your template file(s) where you want the form to appear:', __FILE__); ?>
		</p>

		<p><textarea class='code' rows='1'>&lt;?php echo $forms->embed('<?php echo $form->name; ?>'); ?&gt;</textarea></p>
	</div>

	<h5><a href='#'><b><?php echo __('Option C: Preferred Embed', __FILE__); ?></b></a></h5>
	<div>
		<p>
		<b><?php echo __('Render the form markup directly from your template file (no iframe).', __FILE__); ?></b> 
		<?php echo __('This option is recommended for most cases (when/where possible) and provides the best balance of flexibility, customization and features. It is compatible with all FormBuilder actions and plugins.', __FILE__); ?>
		<?php echo __('It renders the form markup directly in the page, which means your site’s CSS styles can affect its appearance (which may be desirable, or not).', __FILE__); ?>
		</p>	
		<p>
		<?php echo __('If already using one of the compatible CSS frameworks (Uikit, Bootstrap, etc.) you may find embed method C to be ideal, as the markup will be ready for your framework.', __FILE__); ?>
		<?php echo __('If not using a CSS framework for your site, it’s best to choose “Basic” as the FormBuilder framework (on the “Output” tab). Note that you may have to work with your CSS stylesheet to optimize the form appearance.', __FILE__); ?>
		<?php echo __('To proceed, copy and paste the following code into your template file(s) where appropriate, and feel free to adjust as needed.'); ?>
		</p>
		<p><b>1. <?php echo __('Place the following somewhere before output begins (like in an _init.php file, or top of a template file).', __FILE__); ?></b></p>
		<p><textarea class='code' rows='1'>&lt;?php $form = $forms->render('<?php echo $form->name; ?>'); ?&gt;</textarea></p>
		<p><b>2. <?php echo pwfbMarkupWithCode(__('Place the following in your document <head></head> section, wherever you output CSS files (styles) and JS files (scripts).', __FILE__)); ?></b>
		<?php echo __('You may split these two lines as needed, or you may combine with the line mentioned above.', __FILE__); ?></b></p>
		<p><textarea class='code' rows='2'>&lt;?php echo $form->styles; ?&gt;
&lt;?php echo $form->scripts; ?&gt;</textarea></p>
		<p><b>3. <?php echo pwfbMarkupWithCode(__('Place the following somewhere later in your document <body>, where you want your form to be rendered:', __FILE__)); ?></b></p>
		<p><textarea class='code' rows='1'>&lt;?php echo $form; ?&gt;</textarea></p>
		<p class='detail'><?php echo $cacheNote; ?></p>
	</div>
	
	<h5><a href='#'><b><?php echo __('Option D: Custom Markup Embed', __FILE__); ?></b></a></h5>
	<div>
		<?php
		$targetFile = $config->urls->templates . "FormBuilder/form-$form->name.php";
		$sourceFile = $config->urls->cache . "FormBuilder/form-$form->name.php";
		?>
		<p><b><?php echo __('This option lets you have full control over the markup in your form and it outputs directly in your template file(s).', __FILE__); ?></b>
		<?php echo __('Though note that it may limit what actions/plugins you can use, and create more work for you when/if you ever need to make future changes to the form.', __FILE__)?>
		<?php echo __('It is best use this option only after your form is "final", as you will have to apply any further changes to your form markup manually after using this option.', __FILE__); ?></p>
		<p>1. <?php echo "<a href='$dExportURL' class='pw-modal pw-modal-small'>" . 
				__('Click here to export a copy of the form markup.') . '</a> ' . 
				__('It will export a copy of the form markup to this file:', __FILE__); ?></p>
		<p><textarea class='code' rows='1'><?php echo $sourceFile; ?></textarea></p>
		<p>2. <?php echo __('Copy the file mentioned above to this file:'); ?>
		<p><textarea class='code' rows='1'><?php echo $targetFile; ?></textarea></p>
		<p>3. <?php echo __('Edit the file you copied above and follow the instructions provided in the comments of the file.'); ?>
		<p>4. <?php echo __('Place the following in a template file where you would like to output the form:', __FILE__); ?></p>
		<p><textarea class='code' rows='1'>&lt;?php echo $forms->render('<?php echo $form->name; ?>'); ?&gt;</textarea></p>
		<p class='detail'><?php echo $cacheNote; ?></p>
	</div>
	
</div>
<script type='text/javascript'>$("textarea.code").click(function() { $(this).select()});</script>

<?php
$fingerprint = wire()->config->sessionFingerprint;
if(!$form->skipSessionKey || ($fingerprint && $fingerprint != 8)) {
echo "<p class='notes'>" . 
		__('Note: A security feature called session fingerprint is enabled in your ProcessWire installation.') . ' ' .  
		sprintf(
			__('If users of your form(s) have IP addresses that can change while they fill out a form, you may want to disable session tracking/CSRF protection for this form (see settings tab) or add a different %s setting in /site/config.php file.'),
			'<a target="_blank" href="https://processwire.com/api/ref/config/#api-sessionFingerprint">$config->sessionFingerprint</a>'
		) . 
		"</p>";
}
?>