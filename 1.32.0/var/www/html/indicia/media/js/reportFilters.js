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
 * @package Client
 * @author  Indicia Team
 * @license http://www.gnu.org/licenses/gpl.html GPL 3.0
 * @link    http://code.google.com/p/indicia/
 */

var loadFilter;
var loadFilterUser;
var refreshFilters;
var applyFilterToReports;

jQuery(document).ready(function ($) {
  'use strict';
  var saving = false;
  var loadingSites = false;
  var filterOverride = {};

  // override append and remove so we can track addition of sublist locations and draw them on the map
  var origAppend = $.fn.append;
  var origRemove = $.fn.remove;
  $.fn.append = function () {
    return origAppend.apply(this, arguments).trigger('append');
  };
  $.fn.remove = function () {
    return origRemove.apply(this, arguments).trigger('remove');
  };

  indiciaData.filter = {def: {}, id: null, title: null};

  function removeSite() {
    var idToRemove = $(this).find('input[name="location_list[]"]').val();
    var toRemove = [];
    var layer = indiciaData.mapdiv.map.editLayer;
    $.each(layer.features, function () {
      if (this.attributes.id == idToRemove) {
        toRemove.push(this);
      }
    });
    layer.removeFeatures(toRemove, {});
  }

  $('#location_list\\:sublist').bind('append', function () {
    if (!loadingSites) {
      loadSites($(this).find('li input[name="location_list[]"]').last().val(), false);
      // remove all non-site boundaries, i.e. grid squares
      indiciaData.mapdiv.removeAllFeatures(indiciaData.mapdiv.map.editLayer, 'boundary', true);
    }
    $('#location_list\\:sublist li:last-child').bind('remove', removeSite);
  });

  /**
   * If an of the supplied fields are defined in the supplied context filter, then disables
   * the list of controls given, otherwise re-enables them.
   * @param context
   * @param fields
   * @param ctrlIds
   */
  function disableIfPresent(context, fields, ctrlIds) {
    var disable = false;
    $.each(fields, function (idx, field) {
      if (context && context[field]) {
        disable = true;
      }
    });
    $.each(ctrlIds, function (idx, ctrlId) {
      if (disable) {
        $(ctrlId).attr('disabled', true);
      } else {
        $(ctrlId).removeAttr('disabled');
      }
    });
  }

  function disableSourceControlsForContext(type, context, childTypes) {
    var allRelevantCheckboxes;
    var allCheckboxesMatchingIds = [];
    var typeIds;
    if (context && context[type + '_list_op'] && context[type + '_list']) {
      typeIds = context[type + '_list'].split(',');
      $('#filter-' + type + 's-mode').attr('disabled', true);
      // Get all the checkboxes relevant to this level (e.g. for surveys, it includes surveys and input forms.
      allRelevantCheckboxes = $('#' + type + '-list-checklist input');
      $.each(childTypes, function () {
        allRelevantCheckboxes = $.merge(allRelevantCheckboxes, $('#' + this + '-list-checklist input'));
      });
      // Now get just the checkboxes that are linked to the ID of the items in the context. E.g. for a website, the
      // website checkbox, plus linked survey and form checkboxes.
      $.each(typeIds, function () {
        allCheckboxesMatchingIds = $.merge(allCheckboxesMatchingIds,
          $('#check-website-' + type + '-' + this + ',.vis-' + type + '-' + this + ' input'));
      });
      if (context[type + '_list_op'] === 'not in') {
        $(allRelevantCheckboxes).removeAttr('disabled');
        $(allCheckboxesMatchingIds).attr('disabled', true);
      } else {
        $(allRelevantCheckboxes).attr('disabled', true);
        $(allCheckboxesMatchingIds).removeAttr('disabled');
      }
      // Force uncheck the websites you can't access
      $('#' + type + '-list-checklist input:disabled').removeAttr('checked');
    } else {
      $('#filter-' + type + 's-mode').removeAttr('disabled');
    }
  }

  /**
   * Returns true if a site or grid reference are currently selected on the filter
   * @returns boolean
   */
  function siteOrGridRefSelected() {
    return $('input[name="location_list[]"]').length > 0 || ($('#imp-sref').val() !== '' && $('#imp-sref').val() !== null);
  }

  // functions that drive each of the filter panes, e.g. to obtain the description from the controls.
  var paneObjList = {
    what: {
      loadFilter: function () {
        // if list of ids defined but not group names, this is a taxon group list loaded from the user profile.
        // Hijack the names from indiciaData.myGroups.
        if (typeof indiciaData.filter.def.taxon_group_list !== 'undefined' && typeof indiciaData.filter.def.taxon_group_names === 'undefined') {
          indiciaData.filter.def.taxon_group_names = [];
          var foundIds = [], foundNames = [];
          // Loop the group IDs we are expected to load
          $.each(indiciaData.filter.def.taxon_group_list, function (idx, groupId) {
            // Use the myGroups list to look them up
            $.each(indiciaData.myGroups, function () {
              if (this[0] === parseInt(groupId)) {
                foundIds.push(this[0]);
                foundNames.push(this[1]);
              }
            });
          });
          indiciaData.filter.def.taxon_group_names = foundNames;
          indiciaData.filter.def.taxon_group_list = foundIds;
        }
      },
      getDescription: function () {
        var groups = [];
        var taxa = [];
        var designations = [];
        var r = [];
        var filterDef = indiciaData.filter.def;
        if (filterDef.taxon_group_list && filterDef.taxon_group_names) {
          $.each(filterDef.taxon_group_names, function (idx, group) {
            groups.push(group);
          });
        }
        if (filterDef.higher_taxa_taxon_list_list && filterDef.higher_taxa_taxon_list_names) {
          $.each(filterDef.higher_taxa_taxon_list_names, function (idx, taxon) {
            taxa.push(taxon);
          });
        }
        if (filterDef.taxa_taxon_list_list && filterDef.taxa_taxon_list_names) {
          $.each(filterDef.taxa_taxon_list_names, function (idx, taxon) {
            taxa.push(taxon);
          });
        }
        if (filterDef.taxon_designation_list && filterDef.taxon_designation_list_names) {
          $.each(filterDef.taxon_designation_list_names, function (idx, designation) {
            designations.push(designation);
          });
        }
        if (groups.length > 0) {
          r.push(groups.join(', '));
        }
        if (taxa.length > 0) {
          r.push(taxa.join(', '));
        }
        if (designations.length > 0) {
          r.push(designations.join(', '));
        }
        if (filterDef.taxon_rank_sort_order_combined) {
          r.push($('#level-label').text() + ' ' + $('#taxon_rank_sort_order_op').find('option:selected').text() + ' ' +
            $('#taxon_rank_sort_order_combined').find('option:selected').text());
        }
        if (filterDef.marine_flag && indiciaData.filter.def.marine_flag !== 'all') {
          r.push($('#marine_flag').find('option[value=' + filterDef.marine_flag + ']').text());
        }
        return r.join('<br/>');
      },
      applyFormToDefinition: function () {
        // don't send unnecessary stuff
        delete indiciaData.filter.def['taxon_group_list:search'];
        delete indiciaData.filter.def['taxon_group_list:search:q'];
        delete indiciaData.filter.def['higher_taxa_taxon_list_list:search'];
        delete indiciaData.filter.def['higher_taxa_taxon_list_list:search:searchterm'];
        delete indiciaData.filter.def['taxa_taxon_list_list:search'];
        delete indiciaData.filter.def['taxa_taxon_list_list:search:searchterm'];
        delete indiciaData.filter.def['taxon_designation_list:search'];
        delete indiciaData.filter.def['taxon_designation_list:search:title'];
        // reset the list of group names and species
        indiciaData.filter.def.taxon_group_names = {};
        indiciaData.filter.def.higher_taxa_taxon_list_names = {};
        indiciaData.filter.def.taxa_taxon_list_names = {};
        indiciaData.filter.def.taxon_designation_list_names = {};
        // if nothing selected, clean up the def
        if ($('input[name="taxon_group_list\\[\\]"]').length === 0) {
          indiciaData.filter.def.taxon_group_list = '';
        } else {
          // store the list of names in the def, though not used for the report they save web service hits later
          $.each($('input[name="taxon_group_list\\[\\]"]'), function (idx, ctrl) {
            indiciaData.filter.def.taxon_group_names[$(ctrl).val()] = $.trim($(ctrl).parent().text());
          });
        }
        if ($('input[name="higher_taxa_taxon_list_list\\[\\]"]').length === 0) {
          indiciaData.filter.def.higher_taxa_taxon_list_list = '';
        } else {
          // store the list of names in the def, though not used for the report they save web service hits later
          $.each($('input[name="higher_taxa_taxon_list_list\\[\\]"]'), function (idx, ctrl) {
            indiciaData.filter.def.higher_taxa_taxon_list_names[$(ctrl).val()] = $.trim($(ctrl).parent().text());
          });
        }
        if ($('input[name="taxa_taxon_list_list\\[\\]"]').length === 0) {
          indiciaData.filter.def.taxa_taxon_list_list = '';
        } else {
          // store the list of names in the def, though not used for the report they save web service hits later
          $.each($('input[name="taxa_taxon_list_list\\[\\]"]'), function (idx, ctrl) {
            indiciaData.filter.def.taxa_taxon_list_names[$(ctrl).val()] = $.trim($(ctrl).parent().text());
          });
        }
        if ($('input[name="taxon_designation_list\\[\\]"]').length === 0) {
          indiciaData.filter.def.taxon_designation_list = '';
        } else {
          // store the list of names in the def, though not used for the report they save web service hits later
          $.each($('input[name="taxon_designation_list\\[\\]"]'), function (idx, ctrl) {
            indiciaData.filter.def.taxon_designation_list_names[$(ctrl).val()] = $.trim($(ctrl).parent().text());
          });
        }
        // because the rank sort order key includes both the sort order and rank ID, clean this up for the actual filter
        if (typeof indiciaData.filter.def.taxon_rank_sort_order_combined !== 'undefined') {
          indiciaData.filter.def.taxon_rank_sort_order = indiciaData.filter.def.taxon_rank_sort_order_combined.split(':')[0];
        }
      },
      loadForm: function (context) {
        var firstTab = 0, disabled = [];
        // got a families or species level context. So may as well disable the less specific tabs as they won't be useful.
        if (context && context.higher_taxa_taxon_list_list) {
          firstTab = 1;
          disabled = [0];
          $('#families-tab').find('.context-instruct').show();
        }
        else if (context && context.taxa_taxon_list_list) {
          firstTab = 2;
          disabled = [0, 1];
          $('#species-tab').find('.context-instruct').show();
        }
        if (context && context.taxon_designation_list) {
          disabled.push(2);
        }
        if (context && context.marine_flag && context.marine_flag !== 'all') {
          $('#marine_flag').find('option[value=' + context.marine_flag + ']').attr('selected', 'selected');
          $('#marine_flag').attr('disabled', 'disabled');
          $('#flags-tab .context-instruct').show();
        } else {
          $('#marine_flag').removeAttr('disabled');
          $('#flags-tab .context-instruct').hide();
        }
        $('#what-tabs').tabs('option', 'disabled', disabled);
        indiciaFns.activeTab($('#what-tabs'), firstTab);
        if (context && context.taxon_group_list) {
          $('input#taxon_group_list\\:search\\:q').setExtraParams({'idlist': context.taxon_group_list});
          $('#species-group-tab .context-instruct').show();
        }
        else if ($('input#taxon_group_list\\:search\\:q').length > 0) {
          $('input#taxon_group_list\\:search\\:q').unsetExtraParams('query');
        }
        // need to load the sub list control for taxon groups.
        $('#taxon_group_list\\:sublist').children().remove();
        if (typeof indiciaData.filter.def.taxon_group_names !== 'undefined') {
          $.each(indiciaData.filter.def.taxon_group_names, function (id, name) {
            $('#taxon_group_list\\:sublist').append('<li class="ui-widget-content ui-corner-all"><span class="ind-delete-icon"> </span>' + name +
              '<input type="hidden" value="' + id + '" name="taxon_group_list[]"/></li>');
          });
        }
        $('#higher_taxa_taxon_list_list\\:sublist').children().remove();
        if (typeof indiciaData.filter.def.higher_taxa_taxon_list_names !== 'undefined') {
          $.each(indiciaData.filter.def.higher_taxa_taxon_list_names, function (id, name) {
            $('#higher_taxa_taxon_list_list\\:sublist').append('<li class="ui-widget-content ui-corner-all"><span class="ind-delete-icon"> </span>' + name +
              '<input type="hidden" value="' + id + '" name="higher_taxa_taxon_list_list[]"/></li>');
          });
        }
        $('#taxa_taxon_list_list\\:sublist').children().remove();
        if (typeof indiciaData.filter.def.taxa_taxon_list_names !== 'undefined') {
          $.each(indiciaData.filter.def.taxa_taxon_list_names, function (id, name) {
            $('#taxa_taxon_list_list\\:sublist').append('<li class="ui-widget-content ui-corner-all"><span class="ind-delete-icon"> </span>' + name +
              '<input type="hidden" value="' + id + '" name="taxa_taxon_list_list[]"/></li>');
          });
        }
        $('#taxon_designation_list\\:sublist').children().remove();
        if (typeof indiciaData.filter.def.taxon_designation_list_names !== 'undefined') {
          $.each(indiciaData.filter.def.taxon_designation_list_names, function (id, name) {
            $('#taxon_designation_list\\:sublist').append('<li class="ui-widget-content ui-corner-all"><span class="ind-delete-icon"> </span>' + name +
              '<input type="hidden" value="' + id + '" name="taxon_designation_list[]"/></li>');
          });
        }
        if (typeof hook_reportfilter_loadForm != 'undefined')
          hook_reportfilter_loadForm('what');
      }
    },
    when: {
      getDescription: function () {
        var r = [];
        var dateType = 'recorded';
        var dateFromField = 'date_from';
        var dateToField = 'date_to';
        var dateAgeField = 'date_age';
        if (typeof indiciaData.filter.def.date_type !== 'undefined') {
          dateType = indiciaData.filter.def.date_type;
          if (dateType !== 'recorded') {
            dateFromField = dateType + '_date_from';
            dateToField = dateType + '_date_to';
            dateAgeField = dateType + '_date_age';
          }
        }
        if (indiciaData.filter.def[dateFromField] && indiciaData.filter.def[dateToField]) {
          r.push('Records ' + dateType + ' between ' + indiciaData.filter.def[dateFromField] + ' and ' +
            indiciaData.filter.def[dateToField]);
        } else if (indiciaData.filter.def[dateFromField]) {
          r.push('Records ' + dateType + ' on or after ' + indiciaData.filter.def[dateFromField]);
        } else if (indiciaData.filter.def[dateToField]) {
          r.push('Records ' + dateType + ' on or before ' + indiciaData.filter[dateToField]);
        }
        if (indiciaData.filter.def[dateAgeField]) {
          r.push('Records ' + dateType + ' in last ' + indiciaData.filter.def[dateAgeField]);
        }
        return r.join('<br/>');
      },
      loadForm: function (context) {
        var dateTypePrefix = '';
        if (typeof indiciaData.filter.def.date_type !== 'undefined' && indiciaData.filter.def.date_type !== 'recorded') {
          dateTypePrefix = indiciaData.filter.def.date_type + '_';
        }
        if (context && (context.date_from || context.date_to || context.date_age ||
          context.input_date_from || context.input_date_to || context.input_date_age ||
          context.edited_date_from || context.edited_date_to || context.edited_date_age ||
          context.verified_date_from || context.verified_date_to || context.verified_date_age)) {
          $('#controls-filter_when .context-instruct').show();
        }
        if (dateTypePrefix) {
          // We need to load the default values for each control, as if prefixed then they won't autoload
          if (typeof indiciaData.filter.def[dateTypePrefix + 'date_from'] !== 'undefined') {
            $('#date_from').val(indiciaData.filter.def[dateTypePrefix + 'date_from']);
          }
          if (typeof indiciaData.filter.def[dateTypePrefix + 'date_age'] !== 'undefined') {
            $('#date_to').val(indiciaData.filter.def[dateTypePrefix + 'date_to']);
          }
          if (typeof indiciaData.filter.def[dateTypePrefix + 'date_age'] !== 'undefined') {
            $('#date_age').val(indiciaData.filter.def[dateTypePrefix + 'date_age']);
          }
        }
      },
      applyFormToDefinition: function () {
        var dateTypePrefix = '';
        if (typeof indiciaData.filter.def.date_type !== 'undefined' && indiciaData.filter.def.date_type !== 'recorded') {
          dateTypePrefix = indiciaData.filter.def.date_type + '_';
        }
        // make sure we clean up, especially if switching date filter type
        delete indiciaData.filter.def.input_date_from;
        delete indiciaData.filter.def.input_date_to;
        delete indiciaData.filter.def.input_date_age;
        delete indiciaData.filter.def.edited_date_from;
        delete indiciaData.filter.def.edited_date_to;
        delete indiciaData.filter.def.edited_date_age;
        delete indiciaData.filter.def.verified_date_from;
        delete indiciaData.filter.def.verified_date_to;
        delete indiciaData.filter.def.verified_date_age;
        // if the date filter type needs a prefix on the parameter field names, then copy the values from the
        // date controls into the proper parameter field names
        if (dateTypePrefix) {
          indiciaData.filter.def[dateTypePrefix + 'date_from'] = indiciaData.filter.def.date_from;
          indiciaData.filter.def[dateTypePrefix + 'date_to'] = indiciaData.filter.def.date_to;
          indiciaData.filter.def[dateTypePrefix + 'date_age'] = indiciaData.filter.def.date_age;
          // the date control values must NOT apply to the field record date in this case - we are doing a different
          // type filter.
          delete indiciaData.filter.def.date_from;
          delete indiciaData.filter.def.date_to;
          delete indiciaData.filter.def.date_age;
        }
      }
    },
    where: {
      getDescription: function () {
        if (indiciaData.filter.def.remembered_location_name) {
          return 'Records in ' + indiciaData.filter.def.remembered_location_name;
        } else if (indiciaData.filter.def['imp-location:name']) { // legacy
          return 'Records in ' + indiciaData.filter.def['imp-location:name'];
        } else if (indiciaData.filter.def.indexed_location_id) {
          // legacy location ID for the user's locality. In this case we need to hijack the site type drop down shortcuts to get the locality name
          return $('#site-type option[value=loc\\:' + indiciaData.filter.def.indexed_location_id + ']').text();
        } else if (indiciaData.filter.def.location_name) {
          return 'Records in places containing "' + indiciaData.filter.def.location_name + '"';
        } else if (indiciaData.filter.def.sref) {
          return 'Records in square ' + indiciaData.filter.def.sref;
        } else if (indiciaData.filter.def.searchArea) {
          return 'Records within a freehand boundary';
        } else {
          return '';
        }
      },
      applyFormToDefinition: function () {
        var geoms = [], geom;
        delete indiciaData.filter.def.location_id;
        delete indiciaData.filter.def.indexed_location_id;
        delete indiciaData.filter.def.location_list;
        delete indiciaData.filter.def.indexed_location_list;
        delete indiciaData.filter.def.remembered_location_name;
        delete indiciaData.filter.def.searchArea;
        delete indiciaData.filter.def['imp-location:name'];
        // if we've got a location name to search for, no need to do anything else as the where filters are exclusive.
        if (indiciaData.filter.def.location_name) {
          return;
        }
        if ($('#site-type').val() !== '') {
          if ($('#site-type').val().match(/^loc:[0-9]+$/)) {
            indiciaData.filter.def.indexed_location_list = $('#site-type').val().replace(/^loc:/, '');
            indiciaData.filter.def.remembered_location_name = $('#site-type :selected').text();
            return;
          } else if ($('input[name="location_list[]"]').length > 0) {
            var ids = [], names = [];
            $.each($('#location_list\\:sublist li'), function () {
              ids.push($(this).find('input[name="location_list[]"]').val());
              names.push($(this).text().trim());
            });
            if ($.inArray(parseInt($('#site-type').val()), indiciaData.indexedLocationTypeIds) !== -1) {
              indiciaData.filter.def.indexed_location_list = ids.join(',');
            } else {
              indiciaData.filter.def.location_list = ids.join(',');
            }
            indiciaData.filter.def.remembered_location_name = names.join(', ');
            return;
          }
        }

        $.each(indiciaData.mapdiv.map.editLayer.features, function (i, feature) {
          // ignore features with a special purpose, e.g. the selected record when verifying
          if (typeof feature.tag === "undefined" &&
            (typeof feature.attributes.type === "undefined" ||
            (feature.attributes.type !== "boundary" && feature.attributes.type !== "ghost"))) {
            if (feature.geometry.CLASS_NAME.indexOf('Multi') !== -1) {
              geoms = geoms.concat(feature.geometry.components);
            } else {
              geoms.push(feature.geometry);
            }
          }
        });
        if (geoms.length > 0) {
          if (geoms[0].CLASS_NAME === 'OpenLayers.Geometry.Polygon') {
            geom = new OpenLayers.Geometry.MultiPolygon(geoms);
          } else if (geoms[0].CLASS_NAME === 'OpenLayers.Geometry.LineString') {
            geom = new OpenLayers.Geometry.MultiLineString(geoms);
          } else if (geoms[0].CLASS_NAME === 'OpenLayers.Geometry.Point') {
            geom = new OpenLayers.Geometry.MultiPoint(geoms);
          }
          if (indiciaData.mapdiv.map.projection.getCode() !== 'EPSG:3857') {
            geom.transform(indiciaData.mapdiv.map.projection, new OpenLayers.Projection('EPSG:3857'));
          }
          if (indiciaData.filter.def.searchArea !== geom.toString()) {
            indiciaData.filter.def.searchArea = geom.toString();
            filterParamsChanged();
          }
        }
        // cleanup
        delete indiciaData.filter.def['sref:geom'];
      },
      preloadForm: function () {
        // max size the map
        $('#filter-map-container').css('width', $(window).width() - 160);
        $('#filter-map-container').css('height', $(window).height() - 380);
      },
      loadForm: function (context) {
        // legacy
        if (indiciaData.filter.def.location_id && !indiciaData.filter.def.location_list) {
          indiciaData.filter.def.location_list = indiciaData.filter.def.location_id;
          delete indiciaData.filter.def.location_id;
        }
        if (indiciaData.filter.def.indexed_location_id && !indiciaData.filter.def.indexed_location_list) {
          indiciaData.filter.def.indexed_location_list = indiciaData.filter.def.indexed_location_id;
          delete indiciaData.filter.def.indexed_location_id;
        }
        indiciaData.disableMapDataLoading = true;
        indiciaData.mapOrigCentre = indiciaData.mapdiv.map.getCenter();
        indiciaData.mapOrigZoom = indiciaData.mapdiv.map.getZoom();
        if (indiciaData.filter.def.indexed_location_list &&
          $("#site-type option[value='loc:" + indiciaData.filter.def.indexed_location_list + "']").length > 0) {
          $('#site-type').val('loc:' + indiciaData.filter.def.indexed_location_list);
        } else if (indiciaData.filter.def.indexed_location_list || indiciaData.filter.def.location_list) {
          var locationsToLoad = indiciaData.filter.def.indexed_location_list ?
            indiciaData.filter.def.indexed_location_list : indiciaData.filter.def.location_list, siteType;
          if (indiciaData.filter.def['site-type']) {
            siteType = indiciaData.filter.def['site-type'];
          } else {
            // legacy
            siteType = 'my';
          }
          if ($('#site-type').val() !== siteType) {
            $('#site-type').val(siteType);
            changeSiteType();
          }
        }
        if (siteOrGridRefSelected()) {
          // don't want to be able to edit a loaded site boundary or grid reference
          $('.olControlModifyFeatureItemInactive').hide();
        }
        // select the first draw... tool if allowed to draw on the map by permissions, else select navigate
        $.each(indiciaData.mapdiv.map.controls, function (idx, ctrl) {
          if (context && (((context.sref || context.searchArea) && ctrl.CLASS_NAME.indexOf('Control.Navigate') > -1) ||
            ((!context.sref && !context.searchArea) && ctrl.CLASS_NAME.indexOf('Control.Draw') > -1))) {
            ctrl.activate();
            return false;
          }
        });
        if (context && (context.location_id || context.indexed_location_id || context.location_name || context.searchArea)) {
          $('#controls-filter_where .context-instruct').show();
        }
        disableIfPresent(context, ['location_id', 'location_list', 'indexed_location_id', 'indexed_location_list', 'location_name'],
          ['#location_list\\:search\\:name', '#location_name']);
        disableIfPresent(context, ['sref', 'searchArea'], ['#imp-sref']);
        if (context && (context.sref || context.searchArea)) {
          $('#controls-filter_where legend').hide();
          $('.olControlDrawFeaturePolygonItemInactive').addClass('disabled');
          $('.olControlDrawFeaturePathItemInactive').addClass('disabled');
          $('.olControlDrawFeaturePointItemInactive').addClass('disabled');
        } else {
          $('#controls-filter_where legend').show();
        }
      },
      loadFilter: function () {
        if (typeof indiciaData.mapdiv !== "undefined") {
          var filter = indiciaData.filter.def, map = indiciaData.mapdiv.map;
          if (filter.searchArea) {
            var parser = new OpenLayers.Format.WKT(), feature = parser.read(filter.searchArea);
            if (map.projection.getCode() !== indiciaData.mapdiv.indiciaProjection.getCode()) {
              feature.geometry.transform(indiciaData.mapdiv.indiciaProjection, map.projection);
            }
            map.editLayer.addFeatures([feature]);
            map.zoomToExtent(map.editLayer.getDataExtent());
          } else if (filter.location_id || filter.location_list || filter.indexed_location_id || filter.indexed_location_list) {
            // need to load filter location boundaries onto map. Location_id variants are for legacy
            if (filter.location_id && !filter.location_list) {
              filter.location_list = filter.location_id;
            }
            if (filter.indexed_location_id && !filter.indexed_location_list) {
              filter.indexed_location_list = filter.indexed_location_id;
            }
            var locationsToLoad = filter.indexed_location_list ? filter.indexed_location_list : filter.location_list;
            loadSites(locationsToLoad);
          }
        }
      }
    },
    who: {
      getDescription: function () {
        if (indiciaData.filter.def.my_records) {
          return indiciaData.lang.MyRecords;
        } else {
          return '';
        }
      },
      loadForm: function (context) {
        if (context && context.my_records) {
          $('#my_records').attr('disabled', true);
          $('#controls-filter_who .context-instruct').show();
          $('#controls-filter_who button').hide();
        } else {
          $('#my_records').removeAttr('disabled');
          $('#controls-filter_who button').show();
        }
      }
    },
    occurrence_id: {
      getDescription: function () {
        var op;
        if (indiciaData.filter.def.occurrence_id) {
          op = typeof indiciaData.filter.def.occurrence_id_op === 'undefined' ?
            '=' : indiciaData.filter.def.occurrence_id_op.replace(/[<=>]/g, '\\$&');
          return $('#occurrence_id_op').find("option[value='" + op + "']").html()
            + ' ' + indiciaData.filter.def.occurrence_id;
        }
        return '';
      },
      loadForm: function () {
      }
    },
    sample_id: {
      getDescription: function () {
        var op;
        if (indiciaData.filter.def.sample_id) {
          op = typeof indiciaData.filter.def.sample_id === 'undefined' ? '=' : indiciaData.filter.def.sample_id.replace(/[<=>]/g, "\\$&");
          return $('#sample_id_op option[value=' + op + ']').html()
            + ' ' + indiciaData.filter.def.sample_id;
        }
        return '';
      },
      loadForm: function () {
      }
    },
    quality: {
      getDescription: function () {
        var r = [];
        var op;
        if (indiciaData.filter.def.quality !== 'all') {
          r.push($('#quality-filter option[value=' + indiciaData.filter.def.quality.replace('!', '\\!') + ']').html());
        }
        if (indiciaData.filter.def.autochecks === 'F') {
          r.push(indiciaData.lang.AutochecksFailed);
        } else if (indiciaData.filter.def.autochecks === 'P') {
          r.push(indiciaData.lang.AutochecksPassed);
        }
        if (indiciaData.filter.def.identification_difficulty) {
          op = typeof indiciaData.filter.def.identification_difficulty_op === 'undefined' ?
            '=' : indiciaData.filter.def.identification_difficulty_op.replace(/[<=>]/g, '\\$&');
          r.push(indiciaData.lang.IdentificationDifficulty + ' ' +
            $('#identification_difficulty_op').find("option[value='" + op + "']").html() +
            ' ' + indiciaData.filter.def.identification_difficulty);
        }
        if (indiciaData.filter.def.has_photos) {
          r.push(indiciaData.lang.HasPhotos);
        }
        return r.join('<br/>');
      },
      getDefaults: function () {
        return {
          quality: '!R'
        };
      },
      loadForm: function (context) {
        if (context && context.quality && context.quality !== 'all') {
          $('#quality-filter').attr('disabled', true);
        } else {
          $('#quality-filter').removeAttr('disabled');
        }
        if (context && context.autochecks) {
          $('#autochecks').attr('disabled', true);
        } else {
          $('#autochecks').removeAttr('disabled');
        }
        if (context && context.identification_difficulty) {
          $('#identification_difficulty').attr('disabled', true);
          $('#identification_difficulty_op').attr('disabled', true);
        } else {
          $('#identification_difficulty').removeAttr('disabled');
          $('#identification_difficulty_op').removeAttr('disabled');
        }
        if (context && context.has_photos) {
          $('#has_photos').attr('disabled', true);
        } else {
          $('#has_photos').removeAttr('disabled');
        }
        if (context && ((context.quality && context.quality !== 'all') ||
          context.autochecks || context.identification_difficulty || context.has_photos)) {
          $('#controls-filter_quality .context-instruct').show();
        }
      }
    },
    source: {
      getDescription: function () {
        var r = [];
        var list = [];
        if (indiciaData.filter.def.input_form_list) {
          $.each(indiciaData.filter.def.input_form_list.split(','), function (idx, id) {
            list.push($('#check-input_form-' + id).next('label').html());
          });
          r.push((indiciaData.filter.def.input_form_list_op === 'not in' ? 'Exclude ' : '') + list.join(', '));
        } else if (indiciaData.filter.def.survey_list) {
          $.each(indiciaData.filter.def.survey_list.split(','), function (idx, id) {
            list.push($('#check-survey-' + id).next('label').html());
          });
          r.push((indiciaData.filter.def.survey_list_op === 'not in' ? 'Exclude ' : '') + list.join(', '));
        } else if (indiciaData.filter.def.website_list) {
          $.each(indiciaData.filter.def.website_list.split(','), function (idx, id) {
            list.push($('#check-website-' + id).next('label').html());
          });
          r.push((indiciaData.filter.def.website_list_op === 'not in' ? 'Exclude ' : '') + list.join(', '));
        }
        return r.join('<br/>');
      },
      loadForm: function (context) {
        if (context && ((context.website_list && context.website_list_op) ||
          (context.survey_list && context.survey_list_op) || (context.input_form_list && context.input_form_list_op))) {
          $('#controls-filter_source .context-instruct').show();
        }
        if (indiciaData.filter.def.website_list) {
          $('#website-list-checklist input').attr('checked', false);
          $.each(indiciaData.filter.def.website_list.split(','), function (idx, id) {
            $('#check-website-' + id).attr('checked', true);
          });
          updateWebsiteSelection();
        }
        if (indiciaData.filter.def.survey_list) {
          $('#survey_list input').attr('checked', false);
          $.each(indiciaData.filter.def.survey_list.split(','), function (idx, id) {
            $('#check-survey-' + id).attr('checked', true);
          });
        }
        if (indiciaData.filter.def.input_form_list) {
          $('#input_form_list input').attr('checked', false);
          $.each(indiciaData.filter.def.input_form_list.split(','), function (idx, form) {
            $('#check-form-' + indiciaData.formsList[form]).attr('checked', true);
          });
        }
        $('#website-list-checklist,#survey-list-checklist,#input_form-list-checklist')
          .find('input').removeAttr('disabled');
        disableSourceControlsForContext('website', context, ['survey', 'input_form']);
        disableSourceControlsForContext('survey', context, ['input_form']);
        disableSourceControlsForContext('input_form', context, []);
      },
      applyFormToDefinition: function () {
        var websiteIds = [];
        var surveyIds = [];
        var inputForms = [];
        $.each($('#filter-websites input:checked').filter(':visible'), function (idx, ctrl) {
          websiteIds.push($(ctrl).val());
        });
        indiciaData.filter.def.website_list = websiteIds.join(',');
        $.each($('#filter-surveys input:checked').filter(':visible'), function (idx, ctrl) {
          surveyIds.push($(ctrl).val());
        });
        indiciaData.filter.def.survey_list = surveyIds.join(',');
        $.each($('#filter-input_forms input:checked').filter(':visible'), function (idx, ctrl) {
          inputForms.push("'" + $(ctrl).val() + "'");
        });
        indiciaData.filter.def.input_form_list = inputForms.join(',');
      }
    }
  };
  if (typeof hook_reportFilters_alter_paneObj != 'undefined') {
    paneObjList = hook_reportFilters_alter_paneObj(paneObjList);
  }
  // Event handler for a draw tool boundary being added which clears the other controls on the map pane.
  function addedFeature() {
    $('#controls-filter_where').find(':input').not('#imp-sref-system,:checkbox,[type=button],[name="location_list\[\]"]').val('');
    $('#controls-filter_where').find(':checkbox').attr('checked', false);
    // If a selected site but switching to freehand, we need to clear the site boundary.
    if (siteOrGridRefSelected()) {
      clearSites();
      $('#location_list\\:box').hide();
      indiciaData.mapdiv.map.updateSize();
      $('#imp-sref').val('');
    }
  }

  // Hook the addedFeature handler up to the draw controls on the map
  mapInitialisationHooks.push(function (mapdiv) {
    $.each(mapdiv.map.controls, function (idx, ctrl) {
      if (ctrl.CLASS_NAME.indexOf('Control.Draw') >- 1) {
        ctrl.events.register('featureadded', ctrl, addedFeature);
      }
    });
    // ensures that if part of a loaded filter description is a boundary, it gets loaded onto the map only when the map is ready
    updateFilterDescriptions();
  });

  // Ensure that pane controls that are exclusive of others are only filled in one at a time
  $('.filter-controls fieldset :input').change(function (e) {
    var formDiv = $(e.currentTarget).parents('.filter-popup');
    var thisFieldset = $(e.currentTarget).parents('fieldset')[0];
    $.each($(formDiv).find('fieldset.exclusive'), function (idx, fieldset) {
      if (fieldset !== thisFieldset) {
        $(fieldset).find(':input').not('#imp-sref-system,:checkbox,[type=button]').val('');
        $(fieldset).find(':checkbox').attr('checked', false);
      }
    });
  });

  // Ensure that only one of families, species and species groups are picked on the what filter
  var taxonSelectionMethods = ['higher_taxa_taxon_list_list', 'taxa_taxon_list_list', 'taxon_group_list'];
  var fieldname;
  var keep = function (toKeep) {
    $.each(taxonSelectionMethods, function () {
      if (this !== toKeep) {
        $('#' + this + '\\:sublist').children().remove();
      }
    });
  };
  $.each(taxonSelectionMethods, function (idx, method) {
    fieldname = this === 'taxon_group_list' ? 'q' : 'searchterm';
    $('#' + this + '\\:search\\:' + fieldname).keypress(function (e) {
      if (e.which === 13) {
        keep(method);
      }
    });
    $('#' + this + '\\:add').click(function () {
      keep(method);
    });
  });

  function clearSites(all) {
    var map = indiciaData.mapdiv.map;
    $('#location_list\\:sublist').children().remove();
    $('.ac_results').hide();
    $('input#location_list\\:search\\:name').val('');
    if (typeof all === 'undefined' || all === false) {
      indiciaData.mapdiv.removeAllFeatures(map.editLayer, 'boundary');
    } else {
      map.editLayer.removeAllFeatures();
    }
  }

  function loadSites(idsToSelect, doClear) {
    var idQuery;
    if (typeof doClear === 'undefined' || doClear) {
      clearSites(true);
    }
    if (typeof idsToSelect === 'undefined' || idsToSelect.length === 0) {
      return;
    }
    idQuery = '{"in":{"id":[' + idsToSelect + ']}}';
    loadingSites = true;
    $.ajax({
      dataType: 'json',
      url: indiciaData.read.url + 'index.php/services/data/location',
      data: 'mode=json&view=list&orderby=name&auth_token=' + indiciaData.read.auth_token +
      '&nonce=' + indiciaData.read.nonce + '&query=' + idQuery + '&view=detail&callback=?',
      success: function (data) {
        var features = [];
        var feature;
        var geomwkt;
        var parser;
        if (data.length) {
          $.each(data, function (idx, loc) {
            if ($('input[name="location_list[]"][value="' + loc.id + '"]').length === 0) {
              $('#location_list\\:sublist').append('<li class="ui-widget-content ui-corner-all"><span class="ind-delete-icon">' +
                '&nbsp;</span>' + loc.name + '<input type="hidden" name="location_list[]" value="' + loc.id + '"/></li>');
            }
            if (loc.boundary_geom || loc.centroid_geom) {
              geomwkt = loc.boundary_geom || loc.centroid_geom;
              parser = new OpenLayers.Format.WKT();
              if (indiciaData.mapdiv.map.projection.getCode() !== indiciaData.mapdiv.indiciaProjection.getCode()) {
                geomwkt = parser.read(geomwkt).geometry.transform(indiciaData.mapdiv.indiciaProjection, indiciaData.mapdiv.map.projection).toString();
              }
              feature = parser.read(geomwkt);
              feature.attributes.type = 'boundary';
              feature.attributes.id = loc.id;
              features.push(feature);
            }
          });
          indiciaData.mapdiv.map.editLayer.addFeatures(features);
          indiciaData.mapdiv.map.zoomToExtent(indiciaData.mapdiv.map.editLayer.getDataExtent());
        }
        loadingSites = false;
      }
    });
  }

  function changeSiteType() {
    clearSites();
    if ($('#site-type').val() === 'my') {
      // my sites
      $('#location_list\\:box').show();
      if (indiciaData.includeSitesCreatedByUser) {
        $('input#location_list\\:search\\:name').setExtraParams({
          view: 'detail',
          created_by_id: indiciaData.user_id
        });
        $('input#location_list\\:search\\:name').unsetExtraParams('location_type_id');
      }
    } else if ($('#site-type').val().match(/^[0-9]+$/)) {
      // a location_type_id selected
      $('#location_list\\:box').show();
      $('input#location_list\\:search\\:name').setExtraParams({
        view: 'list',
        location_type_id: $('#site-type').val()
      });
      $('input#location_list\\:search\\:name').unsetExtraParams('created_by_id');
    } else {
      // a shortcut site from the site-types list
      $('#location_list\\:box').hide();
      if ($('#site-type').val().match(/^loc:[0-9]+$/)) {
        indiciaData.mapdiv.locationSelectedInInput(indiciaData.mapdiv, $('#site-type').val().replace(/^loc:/, ''));
      }
    }
    indiciaData.mapdiv.map.updateSize();
  }

  $('#site-type').change(function () {
    changeSiteType();
  });

  function updateSurveySelection() {
    var surveys = [];
    $.each($('#filter-surveys input:checked'), function (idx, checkbox) {
      surveys.push('.vis-survey-' + $(checkbox).val());
    });
    if (surveys.length === 0) {
      // no websites picked, so can pick any survey
      $('#filter-input_forms li').show();
    } else if ($('#filter-surveys-mode').val() === 'in') {
      // list only the forms that can be picked
      $('#filter-input_forms li').filter(surveys.join(',')).removeClass('survey-hide');
      $('#filter-input_forms li').not(surveys.join(',')).addClass('survey-hide');
      $('#filter-input_forms li').not(surveys.join(',')).find('input').attr('checked', false);
    } else {
      // list only the forms that can be picked - based on an exclusion of surveys
      $('#filter-input_forms li').filter(surveys.join(',')).addClass('survey-hide');
      $('#filter-input_forms li').not(surveys.join(',')).removeClass('survey-hide');
      $('#filter-input_forms li').filter(surveys.join(',')).find('input').attr('checked', false);
    }
  }

  function updateWebsiteSelection() {
    var websites = [];
    var lis = $('#filter-surveys li, #filter-input_forms li');
    $.each($('#filter-websites input:checked'), function (idx, checkbox) {
      websites.push('.vis-website-' + $(checkbox).val());
    });

    if (websites.length === 0) {
      // no websites picked, so can pick any survey
      lis.removeClass('website-hide');
    } else if ($('#filter-websites-mode').val() === 'in') {
      // list only the surveys that can be picked
      lis.filter(websites.join(',')).removeClass('website-hide');
      lis.not(websites.join(',')).addClass('website-hide');
      lis.not(websites.join(',')).find('input').attr('checked', false);
    } else {
      // list only the surveys that can be picked - based on an exclusion of websites
      lis.filter(websites.join(',')).addClass('website-hide');
      lis.not(websites.join(',')).removeClass('website-hide');
      lis.filter(websites.join(',')).find('input').attr('checked', false);
    }
  }

  $('#filter-websites :input').change(updateWebsiteSelection);

  $('#filter-surveys :input').change(updateSurveySelection);

  $('#my_groups').click(function () {
    $.each(indiciaData.myGroups, function(idx, group) {
      if ($('#taxon_group_list\\:sublist input[value=' + group[0] + ']').length === 0) {
        $('#taxon_group_list\\:sublist').append('<li><span class="ind-delete-icon"> </span>' + group[1] +
          '<input type="hidden" value="' + group[0] + '" name="taxon_group_list[]"></li>');
      }
    });
  });

  // Event handler for selecting a filter from the drop down. Enables the apply filter button when appropriate.
  var filterChange = function () {
    if ($('#select-filter').val()) {
      $('#filter-apply').removeClass('disabled');
    } else {
      $('#filter-apply').addClass('disabled');
    }
  };

  // Hook the above event handler to the select filter dropdown.
  $('#select-filter').change(filterChange);

  /**
   * If a context is loaded, need to limit the filter to the records in the context
   */
  function applyContextLimits() {
    var context;
    // apply the selected context
    if ($('#context-filter').length) {
      context = indiciaData.filterContextDefs[$('#context-filter').val()];
      $.each(context, function (param, value) {
        if (value !== '') {
          indiciaData.filter.def[param + '_context'] = value;
        }
      });
    }
  }

  refreshFilters = function () {
    $.each(paneObjList, function (name, obj) {
      if (typeof obj.refreshFilter !== 'undefined') {
        obj.refreshFilter();
      }
    });
  };

  function codeToSharingTerm(code) {
    switch (code) {
      case 'R': return 'reporting';
      case 'V': return 'verification';
      case 'P': return 'peer review';
      case 'D': return 'data flow';
      case 'M': return 'moderation';
      default: return code;
    }
  }

  applyFilterToReports = function (doReload) {
    var filterDef;
    var reload = (typeof doReload === 'undefined') ? true : doReload;
    applyContextLimits();
    refreshFilters(); // make sure upto date.
    filterDef = $.extend({}, indiciaData.filter.def);
    delete filterDef.taxon_group_names;
    delete filterDef.taxa_taxon_list_names;
    delete filterDef.higher_taxon_list_names;
    delete filterDef.taxon_designation_list_names;
    delete filterDef.taxon_group_names_context;
    delete filterDef.taxa_taxon_list_names_context;
    delete filterDef.higher_taxon_list_names_context;
    delete filterDef.taxon_designation_list_names_context;
    if (indiciaData.reports) {
      // apply the filter to any reports on the page
      $.each(indiciaData.reports, function (i, group) {
        $.each(group, function () {
          var grid = this[0];
          // reset to first page
          grid.settings.offset = 0;
          if (typeof grid.settings.suppliedParams === 'undefined') {
            // First time - store a copy of the supplied default params before any reset, so we can revert.
            grid.settings.suppliedParams = $.extend({}, grid.settings.extraParams);
            // Remove context filter from the params since we always apply a new one afresh each time the filter changes
            $.each(grid.settings.suppliedParams, function (key) {
              if (key.match(/_context$/)) {
                delete grid.settings.suppliedParams[key];
              }
            });
          } else {
            // Subsequently - reset the default parameters for the grid
            grid.settings.extraParams = $.extend({}, grid.settings.suppliedParams);
          }
          // merge in the filter. Supplied filter overrides other location settings (since indexed_location_list and
          // location_list are logically the same filter setting.
          if ((typeof grid.settings.extraParams.indexed_location_list !== 'undefined' ||
              typeof grid.settings.extraParams.indexed_location_id !== 'undefined') &&
              typeof filterDef.location_list !== 'undefined') {
            delete grid.settings.extraParams.indexed_location_list;
            delete grid.settings.extraParams.indexed_location_id;
          } else if ((typeof grid.settings.extraParams.location_list !== 'undefined' ||
              typeof grid.settings.extraParams.location_id !== 'undefined') &&
              typeof filterDef.indexed_location_list !== 'undefined') {
            delete grid.settings.extraParams.location_list;
            delete grid.settings.extraParams.location_id;
          }
          grid.settings.extraParams = $.extend(grid.settings.extraParams, filterDef);
          if ($('#filter\\:sharing').length > 0) {
            grid.settings.extraParams.sharing = codeToSharingTerm($('#filter\\:sharing').val()).replace(' ', '_');
          }
          if (reload) {
            // reload the report grid
            this.ajaxload();
            if (grid.settings.linkFilterToMap && typeof indiciaData.mapdiv !== 'undefined') {
              this.mapRecords(grid.settings.mapDataSource, grid.settings.mapDataSourceLoRes);
            }
          }
        });
      });
    }
  };

  function applyDefaults() {
    $.each(paneObjList, function (name, obj) {
      if (typeof obj.getDefaults !== 'undefined') {
        $.extend(indiciaData.filter.def, obj.getDefaults());
      }
    });
  }

  function resetFilter() {
    indiciaData.filter.def={};
    applyDefaults();
    if (typeof indiciaData.filter.orig !== 'undefined') {
      indiciaData.filter.def = $.extend(indiciaData.filter.def, indiciaData.filter.orig);
    }
    indiciaData.filter.id = null;
    $('#filter\\:title').val('');
    $('#select-filter').val('');
    applyFilterToReports();
    // clear map edit layer
    clearSites();
    $('#site-type').val('');
    $('#location_list\\:box').hide();
    // clear any sublists
    $('.ind-sub-list li').remove();
    updateFilterDescriptions();
    $('#filter-build').html(indiciaData.lang.CreateAFilter);
    $('#filter-reset').addClass('disabled');
    $('#filter-delete').addClass('disabled');
    $('#filter-apply').addClass('disabled');
    // reset the filter label
    $('#active-filter-label').html('');
    $('#standard-params .header span.changed').hide();
  }

  function updateFilterDescriptions() {
    var description;
    var name;
    $.each($('#filter-panes .pane'), function (idx, pane) {
      name = pane.id.replace(/^pane-filter_/, '');
      description = paneObjList[name].getDescription();
      if (description === '') {
        description = indiciaData.lang['NoDescription' + name];
      }
      $(pane).find('span.filter-desc').html(description);
    });
  }

  function loadFilterOntoForms() {
    var name;
    var context = $('#context-filter').length ? indiciaData.filterContextDefs[$('#context-filter').val()] : null;
    $.each($('#filter-panes .pane'), function (idx, pane) {
      name = pane.id.replace(/^pane-filter_/, '');
      // Does the pane have any special code for loading the definition into the form?
      if (typeof paneObjList[name].loadForm !== 'undefined') {
        paneObjList[name].loadForm(context);
      }
    });
  }

  function filterLoaded(data) {
    indiciaData.filter.def = $.extend(JSON.parse(data[0].definition), filterOverride);
    indiciaData.filter.id = data[0].id;
    delete indiciaData.filter.filters_user_id;
    indiciaData.filter.title = data[0].title;
    $('#filter\\:title').val(data[0].title);
    applyFilterToReports();
    $('#filter-reset').removeClass('disabled');
    $('#filter-delete').removeClass('disabled');
    $('#active-filter-label').html('Active filter: ' + data[0].title);
    $.each($('#filter-panes .pane'), function (idx, pane) {
      var name = pane.id.replace(/^pane-filter_/, '');
      if (paneObjList[name].loadFilter) {
        paneObjList[name].loadFilter();
      }
    });
    updateFilterDescriptions();
    loadFilterOntoForms();
    $('#filter-build').html(indiciaData.lang.ModifyFilter);
    $('#standard-params .header span.changed').hide();
    // can't delete a filter you didn't create.
    if (data[0].created_by_id === indiciaData.user_id) {
      $('#filter-delete').show();
    } else {
      $('#filter-delete').hide();
    }
  }

  loadFilter = function (id, getParams) {
    var def;
    filterOverride = getParams;
    if ($('#standard-params .header span.changed:visible').length===0 || confirm(indiciaData.lang.ConfirmFilterChangedLoad)) {
      def = false;
      switch (id) {
        case 'my-records':
          def = '{"quality": "all", "my_records": 1}';
          break;
        case 'my-queried-records':
          def = '{"quality": "D", "my_records": 1}';
          break;
        case 'my-queried-or-not-accepted-records':
        case 'my-queried-rejected-records':
          def = '{"quality": "DR", "my_records": 1}';
          break;
        case 'my-not-reviewed-records':
        case 'my-pending-records':
          def = '{"quality": "P", "my_records": 1}';
          break;
        case 'my-accepted-records':
        case 'my-verified-records':
          def = '{"quality": "V", "my_records": 1}';
          break;
        case 'my-groups':
          def = '{"quality": "all", "my_records": 0, "taxon_group_list": ' + indiciaData.userPrefsTaxonGroups + '}';
          break;
        case 'my-locality':
          def = '{"quality": "all", "my_records": 0, "indexed_location_id": ' + indiciaData.userPrefsLocation + '}';
          break;
        case 'my-groups-locality':
          def = '{"quality": "all", "my_records": 0, "taxon_group_list": ' + indiciaData.userPrefsTaxonGroups +
            ', "indexed_location_id": ' + indiciaData.userPrefsLocation + '}';
          break;
        case 'queried-records':
          def = '{"quality": "D"}';
          break;
        case 'answered-records':
          def = '{"quality": "A"}';
          break;
        case 'accepted-records':
          def = '{"quality": "V"}';
          break;
        case 'not-accepted-records':
          def = '{"quality": "R"}';
          break;
      }
      if (def) {
        filterLoaded([{
          id: id,
          title: $('#select-filter option:selected').html(),
          definition: def
        }]);
      } else {
        $.ajax({
          dataType: 'json',
          url: indiciaData.read.url + 'index.php/services/data/filter/' + id,
          data: 'mode=json&view=list&auth_token=' + indiciaData.read.auth_token +
          '&nonce=' + indiciaData.read.nonce + '&callback=?',
          success: filterLoaded
        });
      }
    }
  };

  loadFilterUser = function (fu, getParams) {
    indiciaData.filter.def = $.extend(JSON.parse(fu.filter_definition), getParams);
    indiciaData.filter.id = fu.filter_id;
    indiciaData.filter.filters_user_id = fu.id;
    indiciaData.filter.title = fu.filter_title;
    $('#filter\\:title').val(fu.filter_title);
    $('#filter\\:description').val(fu.filter_description);
    $('#filter\\:sharing').val(fu.filter_sharing);
    $('#sharing-type-label').html(codeToSharingTerm(fu.filter_sharing));
    $('#filters_user\\:user_id\\:person_name').val(fu.person_name);
    $('#filters_user\\:user_id').val(fu.user_id);
    applyFilterToReports();
    $('#filter-reset').removeClass('disabled');
    $('#filter-delete').removeClass('disabled');
    $('#active-filter-label').html('Active filter: '+fu.filter_title);
    updateFilterDescriptions();
    $('#standard-params .header span.changed').hide();
    // can't delete a filter you didn't create.
    if (fu.filter_created_by_id===indiciaData.user_id) {
      $('#filter-delete').show();
    } else {
      $('#filter-delete').hide();
    }
  };

  function filterParamsChanged() {
    $('#standard-params .header span.changed').show();
    $('#filter-reset').removeClass('disabled');
  }

  // Applies the current loaded filter to the controls within the pane.
  function updateControlValuesToReflectCurrentFilter(pane) {
    var attrName;
    // regexp extracts the pane ID from the href. Loop through the controls in the pane
    $.each(pane.find(':input').not('#imp-sref-system,:checkbox,[type=button],[name="location_list[]"]'),
      function (idx, ctrl) {
        // set control value to the stored filter setting
        attrName = $(ctrl).attr('name');
        // Special case for dates where the filter value name is prefixed with the date type.
        if (attrName && attrName.substring(0, 5) === 'date_' && attrName !== 'date_type'
          && typeof indiciaData.filter.def.date_type !== 'undefined' && indiciaData.filter.def.date_type !== 'recorded') {
          attrName = indiciaData.filter.def.date_type + '_' + attrName;
        }
        $(ctrl).val(indiciaData.filter.def[attrName]);
      }
    );
    $.each(pane.find(':checkbox'), function (idx, ctrl) {
      var tokens;
      var type;
      var ids;
      if (ctrl.id.match(/^check-/)) {
        // source checkboxes map to a list of IDs
        tokens = ctrl.id.split('-');
        type = tokens[0];
        if (typeof indiciaData.filter.def[type + '_list'] !== 'undefined') {
          ids = indiciaData.filter.def[type + '_list'].split(',');
          $(ctrl).attr('checked', $.inArray($(ctrl).val(), ids) > -1);
        }
      } else {
        // other checkboxes are simple on/off flags for filter parameters.
        $(ctrl).attr('checked', typeof indiciaData.filter.def[$(ctrl).attr('name')] !== 'undefined'
          && indiciaData.filter.def[$(ctrl).attr('name')] === $(ctrl).val());
      }
    });
  }
  $('.fb-filter-link').fancybox({
    beforeLoad: function () {
      var pane = $(this.href.replace(/^[^#]+/, ''));
      var paneName = $(pane).attr('id').replace('controls-filter_', '');
      if (typeof paneObjList[paneName].preloadForm !== 'undefined') {
        paneObjList[paneName].preloadForm();
      }
      // reset
      pane.find('.fb-apply').data('clicked', false);
      updateControlValuesToReflectCurrentFilter(pane);
    },
    afterShow: function () {
      var pane = $(this.href.replace(/^[^#]+/, ''));
      var element;
      $('.context-instruct').hide();
      if (pane[0].id === 'controls-filter_where') {
        if (typeof indiciaData.linkToMapDiv !== 'undefined') {
          // move the map div to our container so it appears on the popup
          element = $('#' + indiciaData.linkToMapDiv);
          indiciaData.origMapParent = element.parent();
          indiciaData.origMapSize = {
            width: $(indiciaData.mapdiv).css('width'),
            height: $(indiciaData.mapdiv).css('height')
          };
          $(indiciaData.mapdiv).css('width', '100%');
          $(indiciaData.mapdiv).css('height', '100%');
          $('#filter-map-container').append(element);
          indiciaData.mapdiv.map.updateSize();
          indiciaData.mapdiv.settings.drawObjectType = 'queryPolygon';
        } else {
          indiciaData.mapdiv.map.updateSize();
        }
      }
      // these auto-disable on form submission
      $('#taxon_group_list\\:search\\:q').removeAttr('disabled');
      $('#higher_taxa_taxon_list_list\\:search\\:searchterm').removeAttr('disabled');
      $('#taxa_taxon_list_list\\:search\\:searchterm').removeAttr('disabled');
      $('#taxon_designation_list\\:search\\:title').removeAttr('disabled');
      $('#location_list\\:search\\:name').removeAttr('disabled');
    },
    afterClose: function () {
      var pane = $(this.href.replace(/^[^#]+/, ''));
      var element;
      if (pane[0].id === 'controls-filter_where' && typeof indiciaData.linkToMapDiv !== 'undefined') {
        element = $('#' + indiciaData.linkToMapDiv);
        $(indiciaData.mapdiv).css('width', indiciaData.origMapSize.width);
        $(indiciaData.mapdiv).css('height', indiciaData.origMapSize.height);
        $(indiciaData.origMapParent).append(element);
        indiciaData.mapdiv.map.setCenter(indiciaData.mapOrigCentre, indiciaData.mapOrigZoom);
        indiciaData.mapdiv.map.updateSize();
        indiciaData.mapdiv.settings.drawObjectType = 'boundary';
        indiciaData.disableMapDataLoading = false;
        $.each(indiciaData.mapdiv.map.controls, function () {
          if (this.CLASS_NAME === 'OpenLayers.Control.DrawFeature') {
            this.deactivate();
          }
        });
      }
    }
  });

  $('form.filter-controls :input').change(function () {
    filterParamsChanged();
  });

  $('#filter-apply').click(function () {
    loadFilter($('#select-filter').val(), {});
  });

  $('#filter-reset').click(function () {
    resetFilter();
  });

  $('#filter-build').click(function () {
    var desc;
    $.each(paneObjList, function (name, obj) {
      desc = obj.getDescription();
      if (desc === '') {
        desc = indiciaData.lang['NoDescription' + name];
      }
      $('#pane-filter_' + name + ' span.filter-desc').html(desc);
    });
    $('#filter-details').slideDown();
    $('#filter-build').addClass('disabled');
  });

  $('#filter-delete').click(function (e) {
    var filter;
    if ($(e.currentTarget).hasClass('disabled')) {
      return;
    }
    if (confirm(indiciaData.lang.ConfirmFilterDelete.replace('{title}', indiciaData.filter.title))) {
      filter = {
        id: indiciaData.filter.id,
        website_id: indiciaData.website_id,
        user_id: indiciaData.user_id,
        deleted: 't'
      };
      $.post(indiciaData.filterPostUrl,
        filter,
        function (data) {
          if (typeof data.error === 'undefined') {
            alert(indiciaData.lang.FilterDeleted);
            $('#select-filter').val('');
            $('#select-filter').find('option[value="' + indiciaData.filter.id + '"]').remove();
            resetFilter();
          } else {
            alert(data.error);
          }
        }
      );
    }
  });

  $('#filter-done').click(function () {
    $('#filter-details').slideUp();
    $('#filter-build').removeClass('disabled');
  });

  $('.fb-close').click(function () {
    $.fancybox.close();
  });

  // Select a named location - deactivate the drawFeature and hide modifyFeature controls.
  $('#location_list\\:search\\:name').change(function() {
    $.each(indiciaData.mapdiv.map.controls, function() {
      if (this.CLASS_NAME === 'OpenLayers.Control.DrawFeature') {
        this.deactivate();
      }
    });
    $('.olControlModifyFeatureItemInactive').hide();
  });

  mapInitialisationHooks.push(function(div) {
    // On initialisation of the map, hook event handlers to the draw feature control so we can link the modify feature
    // control visibility to it.
    $.each(div.map.controls, function() {
      if (this.CLASS_NAME==='OpenLayers.Control.DrawFeature' || this.CLASS_NAME==='OpenLayers.Control.ModifyFeature') {
        this.events.register('activate', '', function() {
          $('.olControlModifyFeatureItemInactive, .olControlModifyFeatureItemActive').show();
        });
        this.events.register('deactivate', '', function() {
          $('.olControlModifyFeatureItemInactive').hide();
        });
      }
    });
  });

  mapClickForSpatialRefHooks.push(function(data, mapdiv) {
    // on click to set a grid square, clear any other boundary data
    mapdiv.removeAllFeatures(mapdiv.map.editLayer, 'clickPoint', true);
    clearSites();
    $('#controls-filter_where').find(':input').not('#imp-sref,#imp-sref-system,:checkbox,[type=button],[name="location_list\[\]"]').val('');
  });

  $('form.filter-controls').submit(function(e){
    e.preventDefault();
    if (!$(e.currentTarget).valid() || $(e.currentTarget).find('.fb-apply').data('clicked')) {
      return false;
    }
    $(e.currentTarget).find('.fb-apply').data('clicked', true);
    var arrays = {};
    var arrayName;
    // persist each control value into the stored settings
    $.each($(e.currentTarget).find(':input[name]'), function(idx, ctrl) {
      if (!$(ctrl).hasClass('olButton')) { // skip open layers switcher
        if ($(ctrl).attr('type')!=='checkbox' || $(ctrl).attr('checked')) {
          // array control?
          if ($(ctrl).attr('name').match(/\[\]$/)) {
            // store array control data to handle later
            arrayName = $(ctrl).attr('name').substring(0, $(ctrl).attr('name').length-2);
            if (typeof arrays[arrayName]==='undefined') {
              arrays[arrayName] = [];
            }
            arrays[arrayName].push($(ctrl).val());
          } else {
            // normal control
            indiciaData.filter.def[$(ctrl).attr('name')]=$(ctrl).val();
          }
        }
        else {
          // an unchecked checkbox so clear it's value
          indiciaData.filter.def[$(ctrl).attr('name')]='';
        }
      }
    });
    // convert array values to comma lists
    $.each(arrays, function(name, arr) {
      indiciaData.filter.def[name] = arr.join(',');
    });
    var pane=e.currentTarget.parentNode.id.replace('controls-filter_', '');
    // Does the pane have any special code for applying it's settings to the definition?
    if (typeof paneObjList[pane].applyFormToDefinition!=='undefined') {
      paneObjList[pane].applyFormToDefinition();
    }
    applyFilterToReports();
    updateFilterDescriptions();
    $.fancybox.close();
  });

  var saveFilter = function () {
    if (saving) {
      return;
    }
    if ($.trim($('#filter\\:title').val())==='') {
      alert('Please provide a name for your filter.');
      $('#filter\\:title').focus();
      return;
    }
    if ($('#filters_user\\:user_id').length && $('#filters_user\\:user_id').val()==='') {
      alert('Please fill in who this filter is for.');
      $('#filters_user\\:user_id\\:person_name').focus();
      return;
    }
    saving = true;
    // TODO: Validate user control

    var adminMode = $('#filters_user\\:user_id').length === 1;
    var userId = adminMode ? $('#filters_user\\:user_id').val() : indiciaData.user_id;
    var sharing = adminMode ? $('#filter\\:sharing').val() : indiciaData.filterSharing;
    var url;
    var filter = {
      website_id: indiciaData.website_id,
      user_id: indiciaData.user_id,
      'filters_user:user_id': userId,
      'filter:title': $('#filter\\:title').val(),
      'filter:description': $('#filter\\:description').val(),
      'filter:definition': JSON.stringify(indiciaData.filter.def),
      'filter:sharing': sharing,
      'filter:defines_permissions': adminMode ? 't' : 'f'
    };
    // if existing filter and the title has not changed, or in admin mode, overwrite
    if (indiciaData.filter.id && ($('#filter\\:title').val() === indiciaData.filter.title || adminMode)) {
      filter['filter:id'] = indiciaData.filter.id;
    }
    // if existing filters_users then hook to same record
    if (typeof indiciaData.filter.filters_user_id !== 'undefined') {
      filter['filters_user:id'] = indiciaData.filter.filters_user_id;
    }
    // If a new filter or admin mode, then also need to create a filters_users record.
    url = (typeof indiciaData.filter.id === 'undefined' || indiciaData.filter.id === null || adminMode) ? indiciaData.filterAndUserPostUrl : indiciaData.filterPostUrl;
    $.post(url, filter,
      function (data) {
        var handled;
        if (typeof data.error === 'undefined') {
          alert(indiciaData.lang.FilterSaved);
          indiciaData.filter.id = data.outer_id;
          indiciaData.filter.title = $('#filter\\:title').val();
          indiciaData.filter.filters_user_id = data.struct.children[0].id;
          $('#active-filter-label').html('Active filter: ' + $('#filter\\:title').val());
          $('#standard-params .header span.changed').hide();
          $('#select-filter').val(indiciaData.filter.id);
          if ($('#select-filter').val() === '') {
            // this is a new filter, so add to the select list
            $('#select-filter').append('<option value="' + indiciaData.filter.id + '" selected="selected">' +
              indiciaData.filter.title + '</option>');
          }
          if (indiciaData.redirectOnSuccess !== '') {
            window.location = indiciaData.redirectOnSuccess;
          }
        } else {
          handled = false;
          if (typeof data.errors !== 'undefined') {
            $.each(data.errors, function (key, msg) {
              if (msg.indexOf('duplicate') > -1) {
                if (confirm(indiciaData.lang.FilterExistsOverwrite)) {
                  // need to load the existing filter to get it's ID, then resave
                  $.getJSON(indiciaData.read.url + 'index.php/services/data/filter?created_by_id=' +
                    indiciaData.user_id + '&title=' + encodeURIComponent($('#filter\\:title').val()) + '&sharing=' +
                    indiciaData.filterSharing + '&mode=json&view=list&auth_token=' + indiciaData.read.auth_token +
                    '&nonce=' + indiciaData.read.nonce + '&callback=?', function (response) {
                    indiciaData.filter.id = response[0].id;
                    indiciaData.filter.title = $('#filter\\:title').val();
                    saveFilter();
                  });
                }
                handled = true;
              }
            });
          }
          if (!handled) {
            alert(data.error);
          }
        }
        saving = false;
        $('#filter-build').html(indiciaData.lang.ModifyFilter);
        $('#filter-reset').removeClass('disabled');
      },
      'json'
    );
  };

  $('#location_list\\:box').hide();
  $('#filter-save').click(saveFilter);
  $('#context-filter').change(resetFilter);

  filterChange();
  applyDefaults();
  $('#imp-sref').change(function () {
    window.setTimeout(function () { clearSites(); }, 500);
  });
  $('form.filter-controls').validate();
});
