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
<script type='text/javascript'>
$(document).ready(function(){
  $('div#issues').hide();
  $('#issues_toggle').show();
  $('#issues_toggle').click(function(){
    $('div#issues').toggle('slow');
  });
});
</script>
<h2>Welcome to the Indicia Warehouse!</h2>
<?php if ($db_version<$app_version) : ?>
<div class="ui-state-error ui-corner-all page-notice"><p>Your database needs to be upgraded as the application version is <?php echo $app_version; ?> but the database version is <?php echo $db_version; ?>.
<a class="ui-state-default ui-corner-all button" href="<?php echo url::base();?>index.php/home/upgrade">Run Upgrade</a></p></div>
<?php 
endif;
$problems = config_test::check_config(true, true);
if (count($problems)>0) : ?>
<div class="ui-state-error ui-corner-all page-notice">
<p>There are configuration issues on this server.
<span id="issues_toggle" class="ui-state-default ui-corner-all button" style="margin-left: 1em;">Show/Hide Details</span>
</p>
<div id='issues'>
<?php
foreach($problems as $problem) { 
  echo '<div class="page-notice ui-widget-content ui-corner-all"><div>'.$problem['title'].'</div><div>'.$problem['description'].'</div></div>';
}
?>
</div>
</div>
<?php endif; ?>
<p>Indicia is a toolkit that simplifies the construction of new websites which allow data entry, mapping and reporting
of wildlife records. Indicia is an Open Source project managed by the <a href="http://www.brc.ac.uk/">Biological
Records Centre</a>, within the <a href="http://www.ceh.ac.uk/">NERC Centre for Ecology & Hydrology<a/>.</p>
<ul>
  <li><a href="http://www.indicia.org.uk">Indicia project website<a/></li>
  <li><a href="https://github.com/Indicia-Team">Indicia on GitHub<a/></li>
</ul>

<?php 
if (count($notifications)!==0) {
  /**
   * @todo. This code should use notifications loaded from report grid, with new capability to 
   * decode the json into grid columns.
   */
  $html = '';
  foreach($notifications as $notification) {
    $struct = json_decode($notification->data, true);
    if (isset($struct['headings'])) {
      $html .= "<h2>Notifications from $notification->source</h2>";
      $html .= "<table><thead>\n<tr><th>";
      $html .= implode('</th><th>', $struct['headings']);
      $html .= "</th></tr>\n</thead>\n";
      foreach ($struct['data'] as $recordGroup) {
        $html .= "<tbody>\n";
        foreach ($recordGroup as $record) {
          $html .= '<tr><td>';
          $html .= implode('</td><td>', $record);
          $html .= "</td><td></tr>\n";
        }
        $html .= "</tbody>\n";
      }
      $html .= "</tbody>\n</table>\n";
    }
  }
  if (!empty($html)) : ?>
    <div class="notifications ui-widget-content ui-corner-all">
    <div class="ui-widget-header ui-corner-all">Here are your new notifications:</div>
<?php 
    echo $html.'</div>';
  endif; 
}
?>
