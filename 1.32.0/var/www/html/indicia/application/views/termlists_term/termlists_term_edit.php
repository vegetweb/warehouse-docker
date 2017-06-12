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
 * @package	Core
 * @subpackage Views
 * @author	Indicia Team
 * @license	http://www.gnu.org/licenses/gpl.html GPL
 * @link 	http://code.google.com/p/indicia/
 */

?>
<?php
require_once(DOCROOT.'client_helpers/data_entry_helper.php');
echo html::error_message($model->getError('termlist_id'));
$term_id=html::initial_value($values, 'termlists_term:term_id'); 
?>
<form class="cmxform" action="<?php echo url::site().'termlists_term/save' ?>" method="post">
<?php echo $metadata ?>
<fieldset>
<legend>Term Details</legend>
<input type="hidden" name="termlists_term:id" value="<?php echo html::initial_value($values, 'termlists_term:id'); ?>" />
<input type="hidden" name="termlists_term:termlist_id" value="<?php echo html::initial_value($values, 'termlists_term:termlist_id'); ?>" />
<input type="hidden" name="term:id" value="<?php echo html::initial_value($values, 'term:id'); ?>" />
<input type="hidden" name="meaning:id" id="meaning_id" value="<?php echo html::initial_value($values, 'meaning:id'); ?>" />
<input type="hidden" name="termlists_term:preferred" value="t" />
<ol>
<li>
<label for="term">Term Name</label>
<input id="term" name="term:term" value="<?php echo html::initial_value($values, 'term:term'); ?>"/>
<?php echo html::error_message($model->getError('term:term')); ?>
</li>
<li>
<label for="language_id">Language</label>
<select id="language_id" name="term:language_id">
  <option value=''>&lt;Please select&gt;</option>
<?php
  $language_id=html::initial_value($values, 'term:language_id');
  $languages = ORM::factory('language')->orderby('language','asc')->find_all();
  foreach ($languages as $lang) {
    echo '	<option value="'.$lang->id.'" ';
    if ($lang->id==$language_id) {
      echo 'selected="selected" ';
    }
    echo '>'.$lang->language.'</option>';
  }
?>
</select>
<?php echo html::error_message($model->getError('term:language_id')); ?>
</li>
<li>
<input type="hidden" name="termlists_term:parent_id" value="<?php echo html::initial_value($values, 'termlists_term:parent_id'); ?>" />
<label for="parent">Parent Term:</label>
<input id="parent" name="termlists_term:parent" readonly="readonly" value="<?php 
$parent_id = html::initial_value($values, 'termlists_term:parent_id'); 
echo ($parent_id != null) ? html::specialchars(ORM::factory('termlists_term', $parent_id)->term->term) : ''; 
?>" />
</li>
<li>
<label for="synonyms">Synonyms
<span class="ui-state-highlight ui-widget-content ui-corner-all" title="Enter synonyms one per line. Optionally follow each name by a | character then the 3 character code for the language, e.g. 'Countryside | eng'.">?</span></label>
<textarea rows="7" columns="40" id="synonyms" name="metaFields:synonyms"><?php echo html::initial_value($values, 'metaFields:synonyms'); ?></textarea>
</li>
<li>
<label for="sort_order">Sort Order in List</label>
<input id="sort_order" name="termlists_term:sort_order" class="narrow" value="<?php echo html::initial_value($values, 'termlists_term:sort_order'); ?>" />
<?php echo html::error_message($model->getError('termlists_term:sort_order')); ?>
</li>
<?php if (array_key_exists('source_id', $this->model->as_array()) && !empty($other_data['source_terms'])) : ?>
  <li><label for="source_id">Source of term:</label>
    <select name="<?php echo $model->object_name; ?>:source_id" id="source_id">
      <option value="">-none-</option>
      <?php foreach($other_data['source_terms'] as $id=>$term) {
        $selected=html::initial_value($values, $model->object_name.':source_id')==$id ? ' selected="selected"' : '';
        echo "<option value=\"$id\"$selected>$term</option>\n";
      } ?>
    </select>
  </li>
<?php endif; ?>
</fieldset>
  <fieldset>
    <legend>Term attributes</legend>
    <ol>
      <?php
      foreach ($values['attributes'] as $attr) {
        $name = 'trmAttr:'.$attr['termlists_term_attribute_id'];
        // if this is an existing attribute, tag it with the attribute value record id so we can re-save it
        if ($attr['id']) $name .= ':'.$attr['id'];
        switch ($attr['data_type']) {
          case 'D':
          case 'V':
            echo data_entry_helper::date_picker(array(
              'label' => $attr['caption'],
              'fieldname' => $name,
              'default' => $attr['value']
            ));
            break;
          case 'L':

            echo '<p>Check if this should be lookup_termlist_id</p>';

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
        }

      }
      ?>
    </ol>
  </fieldset>
<?php 
echo html::form_buttons(html::initial_value($values, 'termlists_term:id')!=null);
echo html::error_message($model->getError('deleted')); 
?>
</form>
