<?php
/**
 * Indicia, the OPAL Online Recording Toolkit.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see http://www.gnu.org/licenses/gpl.html.
 *
 * @package	Groups and individuals module
 * @subpackage Views
 * @author	Indicia Team
 * @license	http://www.gnu.org/licenses/gpl.html GPL
 * @link 	http://code.google.com/p/indicia/
 */

require_once(DOCROOT.'client_helpers/data_entry_helper.php');
if (isset($_POST))
  data_entry_helper::dump_errors(array('errors'=>$this->model->getAllErrors()));
?>
<form class="iform" action="<?php echo url::site(); ?>known_subject/save" method="post">
<?php  
echo $metadata; 
if (isset($values['known_subject:id'])) : ?>
  <input type="hidden" name="known_subject:id" value="<?php echo html::initial_value($values, 'known_subject:id'); ?>" />
<?php endif; ?>
<input type="hidden" name="website_id" value="<?php echo html::initial_value($values, 'website_id'); ?>" />
<fieldset>
<legend>Known subject details</legend>
<?php 
$readAuth = data_entry_helper::get_read_auth(0-$_SESSION['auth_user']->id, kohana::config('indicia.private_key'));

echo data_entry_helper::sub_list(array(
  'label' => 'Taxa',
  'fieldname' => 'joinsTo:taxa_taxon_list:id',
  //'default'=>html::initial_value($values, 'joinsTo:taxa_taxon_list:id'),
  'default' => array_key_exists('joinsTo:taxa_taxon_list:id', $values) ? $values['joinsTo:taxa_taxon_list:id'] : '',
  'table' => 'taxa_taxon_list',
  'view' => 'cache',
  'captionField' => 'taxon',
  'valueField' => 'id',
  'addToTable' => false,
  'extraParams' => $readAuth,
));

echo data_entry_helper::sub_list(array(
  'label' => 'Identifiers',
  'fieldname' => 'metaFields:identifiers',
  'default' => array_key_exists('metaFields:identifiers', $values) ? $values['metaFields:identifiers'] : '',
  'table' => 'identifier',
  'view' => 'cache',
  'captionField' => 'coded_value',
  'valueField' => 'id',
  'addToTable' => false,
  'extraParams' => $readAuth,
));

echo data_entry_helper::select(array(
  'label' => 'Subject Type',
  'fieldname' => 'known_subject:subject_type_id',
  'default'=>html::initial_value($values, 'known_subject:subject_type_id'),
  'lookupValues' => $other_data['subject_type_terms'],
  'blankText' => '<Please select>',
  'extraParams' => $readAuth,
));
echo data_entry_helper::select(array(
  'label' => 'Website',
  'fieldname' => 'known_subject:website_id',
  'table' => 'website',
  'captionField' => 'title',
  'valueField' => 'id',
  'default'=>html::initial_value($values, 'known_subject:website_id'),
  'extraParams' => $readAuth,
));
echo data_entry_helper::textarea(array(
  'label'=>'Description',
  'fieldname'=>'known_subject:description',
  'class'=>'control-width-6',
  'default'=>html::initial_value($values, 'known_subject:description')
));
?>
</fieldset>
<?php if (array_key_exists('attributes', $values) && count($values['attributes'])>0) : ?>
<fieldset>
<legend>Custom Attributes</legend>
<ol>
<?php
 foreach ($values['attributes'] as $attr) :
	$name = 'ksjAttr:'.$attr['known_subject_attribute_id'];
  // if this is an existing attribute, tag it with the attribute value record id so we can re-save it
  if ($attr['id']) $name .= ':'.$attr['id'];
	switch ($attr['data_type']) :
    case 'D':
    case 'V':
      echo data_entry_helper::date_picker(array(
        'label' => $attr['caption'],
        'fieldname' => $name,
        'default' => $attr['value']
      ));
      break;
    case 'L':
      echo data_entry_helper::select(array(
        'label' => $attr['caption'],
        'fieldname' => $name,
        'default' => $attr['raw_value'],
        'lookupValues' => $values['terms_'.$attr['termlist_id']],
        'blankText' => '<Please select>'
      ));
      break;
    case 'B':
      echo data_entry_helper::checkbox(array(
        'label' => $attr['caption'],
        'fieldname' => $name,
        'default' => $attr['value']
      ));
      break;
    default:
      echo data_entry_helper::text_input(array(
        'label' => $attr['caption'],
        'fieldname' => $name,
        'default' => $attr['value']
      ));
  endswitch;
	
endforeach;
 ?>
</ol>
</fieldset>
<?php 
endif;
echo html::form_buttons(html::initial_value($values, 'known_subject:id')!=null, false, false);
data_entry_helper::$dumped_resources[] = 'jquery';
data_entry_helper::$dumped_resources[] = 'json';
data_entry_helper::$dumped_resources[] = 'jquery_ui';
data_entry_helper::$dumped_resources[] = 'fancybox';
data_entry_helper::link_default_stylesheet();
echo data_entry_helper::dump_javascript();
?>
</form>
