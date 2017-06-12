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
 * @package  Core
 * @subpackage Views
 * @author  Indicia Team
 * @license  http://www.gnu.org/licenses/gpl.html GPL
 * @link   http://code.google.com/p/indicia/
 */

$disabled_input=html::initial_value($values, 'metaFields:disabled_input');
$enabled = ($disabled_input=='YES') ? 'disabled="disabled"' : '';
?>
<p>
<?php if ($disabled_input==='YES') : ?>
The attribute was created by another user so you don't have permission to change the attribute's specification, although you can 
change the attribute assignments at the bottom of the page. Please contact the warehouse owner to request changes.
<?php else : ?>
This page allows you to specify a new or edit an existing custom attribute for <?php echo strtolower($other_data['name']); ?> data.
<?php endif; ?>
</p>
<form class="cmxform"
  action="<?php echo url::site().$other_data['controllerpath']."/save"; ?>"
  method="post"><input type="hidden" name="<?php echo $model->object_name; ?>:id"
  value="<?php echo html::initial_value($values, $model->object_name.':id'); ?>" />
<input type="hidden" name="metaFields:disabled_input"
  value="<?php echo $disabled_input; ?>" />
<fieldset
<?php if ($disabled_input=='YES') echo ' class="ui-state-disabled"'; ?>>
<legend><?php echo $other_data['name']; ?> Attribute details</legend>
<ol>
  <li>
    <label for="caption">Caption</label>
    <input id="caption" name="<?php echo $model->object_name; ?>:caption"
        value="<?php echo html::initial_value($values, $model->object_name.':caption'); ?>"
    <?php echo $enabled; ?> /> <?php echo html::error_message($model->getError($model->object_name.':caption')); ?>
  </li>
  <?php if (array_key_exists('description', $this->model->as_array())) : ?>
    <li>
      <label for="description">Description:</label>
      <textarea id="description" name="<?php echo $model->object_name; ?>:description" <?php echo $enabled; ?>
        ><?php echo html::initial_value($values, $model->object_name.':description'); ?></textarea>
        <?php echo html::error_message($model->getError($model->object_name.':description')); ?>
    </li>
  <?php endif; ?>
  
  <?php if (method_exists($this->model, 'get_system_functions')) : ?>
  <li><label for="system_function">System function:</label>
    <select name="<?php echo $model->object_name; ?>:system_function" id="system_function">
      <option value="">-none-</option>
      <?php foreach($this->model->get_system_functions() as $function=>$def) {
        $selected=html::initial_value($values, $model->object_name.':system_function')==$function ? ' selected="selected"' : '';
        echo '<option title="'.$def['description']."\" value=\"$function\"$selected>".$def['title']."</option>\n";
      } ?>
    </select>
  </li>
  <?php endif; ?>
  <?php if (array_key_exists('source_id', $this->model->as_array()) && !empty($other_data['source_terms'])) : ?>
    <li><label for="source_id">Source of attribute:</label>
      <select name="<?php echo $model->object_name; ?>:source_id" id="source_id">
        <option value="">-none-</option>
        <?php foreach($other_data['source_terms'] as $id=>$term) {
          $selected=html::initial_value($values, $model->object_name.':source_id')==$id ? ' selected="selected"' : '';
          echo "<option value=\"$id\"$selected>$term</option>\n";
        } ?>
      </select>
    </li>
  <?php endif; ?>
  <li><label for="data_type">Data Type</label> <script
    type="text/javascript">
$(document).ready(function() {
  $('#quick_termlist_create').change(function (e) {
    if ($(e.currentTarget).attr('checked')) {
      $('#quick_termlist_terms-cntr').show();
      $('#termlist-picker').hide();
    } else {
      $('#quick_termlist_terms-cntr').hide();
      $('#termlist-picker').show();
    }
  });
});
function showHideTermlistLink() {
  $("#termlist-link").attr('href', '<?php echo url::site().'termlist/edit/'; ?>'+$('#termlist_id').val());
  if ($('#termlist_id').val()!=='' && $('#data_type').val()==='L') {
    $("#termlist-link").show();
  } else {
    $("#termlist-link").hide();
  }
}

function toggleOptions(data_type)
{
  var enable_list = [];
  var disable_list = [];
  $('select#termlist_id').attr('disabled', 'disabled');
  $("#termlist-link").hide();
  $('#quick-termlist').hide();
  switch(data_type) {
    case "T": // text
      enable_list = ['valid_required','valid_length','valid_length_min','valid_length_max','valid_alpha','valid_email','valid_url','valid_alpha_numeric','valid_numeric','valid_standard_text','valid_decimal','valid_dec_format','valid_regex','valid_regex_format','valid_time'];
      disable_list = ['valid_digit','valid_integer','valid_min','valid_min_value','valid_max','valid_max_value','valid_date_in_past'];
      break;
    case "L": // Lookup List
      $('select#termlist_id').removeAttr('disabled');
      enable_list = ['valid_required'];
      disable_list = ['valid_length','valid_length_min','valid_length_max','valid_alpha','valid_email','valid_url','valid_alpha_numeric','valid_numeric','valid_digit','valid_integer','valid_standard_text','valid_decimal','valid_dec_format','valid_regex','valid_regex_format','valid_min','valid_min_value','valid_max','valid_max_value','valid_date_in_past','valid_time'];
<?php if (!html::initial_value($values, $model->object_name.':id')): ?>
      $('#quick-termlist').show();
<?php endif; ?>
      break;
    case "I": // Integer
        enable_list = ['valid_required','valid_digit','valid_integer','valid_decimal','valid_dec_format','valid_regex','valid_regex_format','valid_min','valid_min_value','valid_max','valid_max_value'];
        disable_list = ['valid_numeric','valid_length','valid_length_min','valid_length_max','valid_alpha','valid_email','valid_url','valid_alpha_numeric','valid_standard_text','valid_date_in_past','valid_time'];
        break;
    case "F": // Float
      enable_list = ['valid_required','valid_numeric','valid_decimal','valid_dec_format','valid_regex','valid_regex_format','valid_min','valid_min_value','valid_max','valid_max_value'];
      disable_list = ['valid_digit','valid_integer','valid_length','valid_length_min','valid_length_max','valid_alpha','valid_email','valid_url','valid_alpha_numeric','valid_standard_text','valid_date_in_past','valid_time'];
      break;
    case "D": // Specific Date
    case "V": // Vague Date
      enable_list = ['valid_required','valid_min','valid_min_value','valid_max','valid_max_value','valid_date_in_past'];
      disable_list = ['valid_length','valid_length_min','valid_length_max','valid_alpha','valid_email','valid_url','valid_alpha_numeric','valid_numeric','valid_standard_text','valid_decimal','valid_dec_format','valid_regex','valid_regex_format','valid_digit','valid_integer','valid_time'];
      break;
    case "B": // Boolean
      enable_list = ['valid_required'];
      disable_list = ['valid_length','valid_length_min','valid_length_max','valid_alpha','valid_email','valid_url','valid_alpha_numeric','valid_numeric','valid_standard_text','valid_decimal','valid_dec_format','valid_regex','valid_regex_format','valid_min','valid_min_value','valid_max','valid_max_value','valid_date_in_past','valid_digit','valid_integer','valid_time'];
      break;
    default:
      disable_list = ['valid_required','valid_length','valid_length_min','valid_length_max','valid_alpha','valid_email','valid_url','valid_alpha_numeric','valid_numeric','valid_standard_text','valid_decimal','valid_dec_format','valid_regex','valid_regex_format','valid_min','valid_min_value','valid_max','valid_max_value','valid_date_in_past','valid_digit','valid_integer','valid_time'];
      break;
  };
  $.each(enable_list, function(i, item) {
    $('#li_'+item).show();
  });
  $.each(disable_list, function(i, item) {
    $('#li_'+item).hide();
  });
  showHideTermlistLink();
};
<?php
  if ($disabled_input == 'NO') {
?>
$(document).ready(function() {
  toggleOptions($('select#data_type').attr('value'));
  $('#termlist_id').change(function(e) {
    showHideTermlistLink();
  });
});
<?php
  }
?>
</script> <select id="data_type" name="<?php echo $model->object_name; ?>:data_type"
<?php echo $enabled; ?>
    onchange="toggleOptions(this.value);">
    <option value=''>&lt;Please Select&gt;</option>
    <?php
    $optionlist = array(
     'T' => 'Text'
    ,'L' => 'Lookup List'
    ,'I' => 'Integer'
    ,'F' => 'Float'
    ,'D' => 'Specific Date'
    ,'V' => 'Vague Date'
    ,'B' => 'Boolean'
    );
    foreach ($optionlist as $key => $option) {
      echo '  <option value="'.$key.'" ';
      if ($key==html::initial_value($values, $model->object_name.':data_type'))
      echo 'selected="selected" ';
      echo '>'.$option.'</option>';
    }
    ?>
  </select> <?php echo html::error_message($model->getError($model->object_name.':data_type')); ?>
  </li>
  <li id="quick-termlist" style="display: none;">
    <div>
      <label for="quick_termlist_create">Create a new termlist:</label>
      <input type="checkbox" id="quick_termlist_create" name="metaFields:quick_termlist_create" value="t" />
      <p class="helpText">Tick this box to create a new termlist with the same name as this
      attribute and populate it with a provided list of terms.</p>
    </div>
    <div id="quick_termlist_terms-cntr" style="display: none;">
      <label for="quick_termlist_terms">Terms:</label>
      <textarea id="quick_termlist_terms" name="metaFields:quick_termlist_terms" rows="10"></textarea>
      <p class="helpText">Enter terms into this box, one per line. A termlist with the same name as the attribute
      will be created and populated with this list of terms in the order provided.</p>
    </div>
  </li>

  <li id="termlist-picker"><label for="termlist_id">Termlist</label> <select id="termlist_id"
    name="<?php echo $model->object_name; ?>:termlist_id" <?php echo $enabled; ?>>
    <option value=''>&lt;Please Select&gt;</option>
    <?php
    if (!is_null($this->auth_filter))
    $termlists = ORM::factory('termlist')->where('deleted','f')->in('website_id',$this->auth_filter['values'])->orderby('title','asc')->find_all();
    else
    $termlists = ORM::factory('termlist')->where('deleted','f')->orderby('title','asc')->find_all();
    foreach ($termlists as $termlist) {
      echo '  <option value="'.$termlist->id.'" ';
      if ($termlist->id==html::initial_value($values, $model->object_name.':termlist_id'))
      echo 'selected="selected" ';
      echo '>'.$termlist->title.'</option>';
    }
    ?>
  </select> 
  <?php 
  echo html::error_message($model->getError($model->object_name.':termlist_id')); 
  echo '<a id="termlist-link" target="_blank" href="">edit in new tab</a>';
  ?>
  </li>
  <li><label class="wide" for="multi_value">Allow Multiple Values</label>
  <?php echo form::checkbox($model->object_name.':multi_value', TRUE, (html::initial_value($values, $model->object_name.':multi_value') == 't'), 'class="vnarrow" '.$enabled ) ?>
  </li>
  <li><label class="wide" for="public"><?php echo $other_data['publicFieldName']; ?></label>
  <?php echo form::checkbox($model->object_name.':public', TRUE, (html::initial_value($values, $model->object_name.':public') == 't'), 'class="vnarrow" '.$enabled ) ?>
  </li>
  <?php if ($model->object_name=='sample_attribute') : ?>
  <li><label class="wide" for="public">Applies to location</label>
  <?php echo form::checkbox($model->object_name.':applies_to_location', TRUE, (html::initial_value($values, $model->object_name.':applies_to_location') == 't'), 'class="vnarrow" '.$enabled ) ?>
  </li>
  <?php endif; ?>
  <?php if ($model->object_name=='person_attribute') : ?>
  <li><label class="wide" for="public">Synchronisable with client website user profiles:</label>
  <?php echo form::checkbox($model->object_name.':synchronisable', TRUE, (html::initial_value($values, $model->object_name.':synchronisable') == 't'), 'class="vnarrow" '.$enabled ) ?>
  </li>
  <?php endif; ?>
</ol>
</fieldset>
<fieldset
<?php if ($disabled_input=='YES') echo ' class="ui-state-disabled"'; ?>>
<legend>Validation Rules</legend>
<ol>
  <li id="li_valid_required"><label class="narrow" for="valid_required">Required</label><?php echo form::checkbox('valid_required', TRUE, isset($model->valid_required) AND ($model->valid_required == 't'), 'class="vnarrow" '.$enabled ) ?></li>
  <li id="li_valid_length"><label class="narrow" for="valid_length">Length</label><?php echo form::checkbox('valid_length', TRUE, isset($model->valid_length) AND ($model->valid_length == 't'), 'class="vnarrow" '.$enabled ) ?>
    Between <input class="narrow" id="valid_length_min" name="valid_length_min"
    value="<?php echo html::specialchars($model->valid_length_min); ?>"
    <?php echo $enabled?> /> and <input class="narrow"
    id="valid_length_max" name="valid_length_max"
    value="<?php echo html::specialchars($model->valid_length_max); ?>"
    <?php echo $enabled?> /> <?php echo html::error_message($model->getError('valid_length')); ?>
  </li>
  <li id="li_valid_alpha"><label class="narrow" for="valid_alpha">Alphabetic</label><?php echo form::checkbox('valid_alpha', TRUE, isset($model->valid_alpha) AND ($model->valid_alpha == 't'), 'class="vnarrow" '.$enabled ) ?></li>
  <li id="li_valid_email"><label class="narrow" for="valid_email">Email
  Address</label><?php echo form::checkbox('valid_email', TRUE, isset($model->valid_email) AND ($model->valid_email == 't'), 'class="vnarrow" '.$enabled ) ?></li>
  <li id="li_valid_url"><label class="narrow" for="valid_url">URL</label><?php echo form::checkbox('valid_url', TRUE, isset($model->valid_url) AND ($model->valid_url == 't'), 'class="vnarrow" '.$enabled  ) ?></li>
  <li id="li_valid_alpha_numeric"><label class="narrow"
    for="valid_alpha_numeric">Alphanumeric</label><?php echo form::checkbox('valid_alpha_numeric', TRUE, isset($model->valid_alpha_numeric) AND ($model->valid_alpha_numeric == 't'), 'class="vnarrow" '.$enabled ) ?></li>
  <li id="li_valid_numeric"><label class="narrow" for="valid_numeric">Numeric</label><?php echo form::checkbox('valid_numeric', TRUE, isset($model->valid_numeric) AND ($model->valid_numeric == 't'), 'class="vnarrow" '.$enabled ) ?></li>
  <li id="li_valid_digit"><label class="narrow" for="valid_digit">Digits Only</label><?php echo form::checkbox('valid_digit', TRUE, isset($model->valid_digit) AND ($model->valid_digit == 't'), 'class="vnarrow" '.$enabled ) ?></li>
  <li id="li_valid_integer"><label class="narrow" for="valid_integer">Integer</label><?php echo form::checkbox('valid_integer', TRUE, isset($model->valid_integer) AND ($model->valid_integer == 't'), 'class="vnarrow" '.$enabled ) ?></li>
  <li id="li_valid_standard_text"><label class="narrow"
    for="valid_standard_text">Standard Text</label><?php echo form::checkbox('valid_standard_text', TRUE, isset($model->valid_standard_text) AND ($model->valid_standard_text == 't'), 'class="vnarrow" '.$enabled ) ?></li>
  <li id="li_valid_decimal"><label class="narrow" for="valid_decimal">Formatted
  Decimal</label><?php echo form::checkbox('valid_decimal', TRUE, isset($model->valid_decimal) AND ($model->valid_decimal == 't'), 'class="vnarrow" '.$enabled ) ?><input
    class="narrow" id="valid_dec_format" name="valid_dec_format"
    value="<?php echo html::specialchars($model->valid_dec_format); ?>"
    <?php echo $enabled?> /> <?php echo html::error_message($model->getError('valid_decimal')); ?>
  </li>
  <li id="li_valid_regex"><label class="narrow" for="valid_regex">Regular
  Expression</label><?php echo form::checkbox('valid_regex', TRUE, isset($model->valid_regex) AND ($model->valid_regex == 't'), 'class="vnarrow" '.$enabled ) ?><input
    class="narrow" id="valid_regex_format" name="valid_regex_format"
    value="<?php echo html::specialchars($model->valid_regex_format); ?>"
    <?php echo $enabled?> /> <?php echo html::error_message($model->getError('valid_regex')); ?>
  </li>
  <li id="li_valid_min"><label class="narrow" for="valid_min">Minimum
  value</label><?php echo form::checkbox('valid_min', TRUE, isset($model->valid_min) AND ($model->valid_min == 't'), 'class="vnarrow" '.$enabled ) ?><input
    class="narrow" id="valid_min_value" name="valid_min_value"
    value="<?php echo html::specialchars($model->valid_min_value); ?>"
    <?php echo $enabled?> /> <?php echo html::error_message($model->getError('valid_min')); ?>
  </li>
  <li id="li_valid_max"><label class="narrow" for="valid_max">Maximum
  value</label><?php echo form::checkbox('valid_max', TRUE, isset($model->valid_max) AND ($model->valid_max == 't'), 'class="vnarrow" '.$enabled ) ?><input
    class="narrow" id="valid_max_value" name="valid_max_value"
    value="<?php echo html::specialchars($model->valid_max_value); ?>"
    <?php echo $enabled?> /> <?php echo html::error_message($model->getError('valid_max')); ?>
  </li>
  <li id="li_valid_date_in_past">
    <label class="narrow" for="valid_date_in_past">Date is in past</label><?php
      echo form::checkbox('valid_date_in_past', TRUE, isset($model->valid_date_in_past) AND ($model->valid_date_in_past == 't'), 'class="vnarrow" '.$enabled );
      echo html::error_message($model->getError('valid_date_in_past')); ?>
  </li>
  <li id="li_valid_time"><label class="narrow" for="valid_integer">Time</label><?php echo form::checkbox('valid_time', TRUE, isset($model->valid_time) AND ($model->valid_time == 't'), 'class="vnarrow" '.$enabled ) ?></li>
</ol>
</fieldset>
<?php 
// Output the view that lets this custom attribute associate with websites, surveys, checklists
// or whatever is appropriate for the attribute type.
$this->associationsView->other_data=$other_data;
$this->associationsView->model=$model;
echo $this->associationsView;
echo $metadata;
echo html::form_buttons(html::initial_value($values, $model->object_name.':id')!=null);
?></form>
