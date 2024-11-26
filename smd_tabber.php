<?php
// This is a PLUGIN TEMPLATE for Textpattern CMS.

// Copy this file to a new name like abc_myplugin.php.  Edit the code, then
// run this file at the command line to produce a plugin for distribution:
// $ php abc_myplugin.php > abc_myplugin-0.1.txt

// Plugin name is optional.  If unset, it will be extracted from the current
// file name. Plugin names should start with a three letter prefix which is
// unique and reserved for each plugin author ("abc" is just an example).
// Uncomment and edit this line to override:
$plugin['name'] = 'smd_tabber';

// Allow raw HTML help, as opposed to Textile.
// 0 = Plugin help is in Textile format, no raw HTML allowed (default).
// 1 = Plugin help is in raw HTML.  Not recommended.
# $plugin['allow_html_help'] = 1;

$plugin['version'] = '0.2.0';
$plugin['author'] = 'Stef Dawson';
$plugin['author_uri'] = 'https://stefdawson.com/';
$plugin['description'] = 'Add and manage Textpattern back-end tabs (for dashboards, etc)';

// Plugin load order:
// The default value of 5 would fit most plugins, while for instance comment
// spam evaluators or URL redirectors would probably want to run earlier
// (1...4) to prepare the environment for everything else that follows.
// Values 6...9 should be considered for plugins which would work late.
// This order is user-overrideable.
$plugin['order'] = '5';

// Plugin 'type' defines where the plugin is loaded
// 0 = public              : only on the public side of the website (default)
// 1 = public+admin        : on both the public and admin side
// 2 = library             : only when include_plugin() or require_plugin() is called
// 3 = admin               : only on the admin side (no AJAX)
// 4 = admin+ajax          : only on the admin side (AJAX supported)
// 5 = public+admin+ajax   : on both the public and admin side (AJAX supported)
$plugin['type'] = '5';

// Plugin "flags" signal the presence of optional capabilities to the core plugin loader.
// Use an appropriately OR-ed combination of these flags.
// The four high-order bits 0xf000 are available for this plugin's private use
if (!defined('PLUGIN_HAS_PREFS')) define('PLUGIN_HAS_PREFS', 0x0001); // This plugin wants to receive "plugin_prefs.{$plugin['name']}" events
if (!defined('PLUGIN_LIFECYCLE_NOTIFY')) define('PLUGIN_LIFECYCLE_NOTIFY', 0x0002); // This plugin wants to receive "plugin_lifecycle.{$plugin['name']}" events

$plugin['flags'] = '2';

// Plugin 'textpack' is optional. It provides i18n strings to be used in conjunction with gTxt().
// Syntax:
// ## arbitrary comment
// #@event
// #@language ISO-LANGUAGE-CODE
// abc_string_name => Localized String

$plugin['textpack'] = <<<EOT
#@language en, en-gb, en-us
#@admin-side
smd_tabber_tab => Manage tabs
#@smd_tabber
smd_tabber_all_pubs => All publishers
smd_tabber_area => Assign to area:
smd_tabber_cancel => [Cancel]
smd_tabber_choose => Select tab to edit:
smd_tabber_created => Tab created. Refresh to see it
smd_tabber_deleted => Tab deleted. Refresh to see changes
smd_tabber_exists => Tab already exists
smd_tabber_heading => Manage additional TXP tabs
smd_tabber_name => Tab name:
smd_tabber_need_area => Please supply a location for the tab
smd_tabber_need_name => Please supply a tab name
smd_tabber_new_area => New area >>
smd_tabber_new_tab => New tab
smd_tabber_page => Page template:
smd_tabber_parse_depth => Parse depth
smd_tabber_prefs => Preferences
smd_tabber_prefs_lbl => Tabber preferences
smd_tabber_prefs_some_explain => This is either a new installation or a different version of the plugin to one you had before.
smd_tabber_prefs_some_opts => Click "Install table" to add or update the table leaving all existing data untouched.
smd_tabber_prefs_some_tbl => Not all table info available.
smd_tabber_view_privs => View privileges:
smd_tabber_saved => Tab saved. Refresh to see changes
smd_tabber_sort_order => Sort order:
smd_tabber_style => Stylesheet:
smd_tabber_tab_prefix => Page/Style prefix
smd_tabber_tab_privs => Permit tabs to be managed by
smd_tabber_tbl_installed => Table installed
smd_tabber_tbl_install_lbl => Install table
smd_tabber_tbl_not_installed => Table NOT installed
smd_tabber_tbl_not_removed => Table NOT removed
smd_tabber_tbl_removed => Table removed
EOT;

if (!defined('txpinterface'))
        @include_once('zem_tpl.php');

# --- BEGIN PLUGIN CODE ---
if (!defined('SMD_TABBER')) {
    define("SMD_TABBER", 'smd_tabber');
}

if (class_exists('\Textpattern\Tag\Registry')) {
    Txp::get('\Textpattern\Tag\Registry')
        ->register('smd_tabber_edit_page')
        ->register('smd_tabber_edit_style');
}

if (txpinterface === 'admin') {
    global $textarray, $smd_tabber_event, $smd_tabber_styles, $txp_user, $smd_tabber_callstack, $smd_tabber_uprivs, $smd_tabber_prevs;

    $smd_tabber_event = 'smd_tabber';
    $smd_tabber_prevs = '1';
    $smd_tabber_styles = array(
        'edit' => '#smd_tabber_wrapper { margin:0 auto; width:520px; }
.smd_tabber_equal { display:table; border-collapse:separate; margin:10px auto; border-spacing:8px; }
.smd_tabber_row { display:table-row; }
.smd_tabber_row div { display:table-cell; }
.smd_tabber_row .smd_tabber_label { width:150px; text-align:right; padding:2px 0 0 0; }
.smd_tabber_row .smd_tabber_value { width:350px; vertical-align:middle; }
#smd_tabber_select { margin-bottom:-20px; }
.smd_tabber_save { float:right; margin:10px 50px 0 0!important;}',
        'prefs' => '.smd_label { text-align: right!important; vertical-align: top; }
        ',
    );

    register_callback('smd_tabber_welcome', 'plugin_lifecycle.'.$smd_tabber_event);

    $ulist = get_pref('smd_tabber_tab_privs', '');
    $allowed = ($ulist) ? explode(',', $ulist) : array();
    $levs = ($allowed) ? '1,2,3,4,5,6' : '1';

    if (empty($allowed) || in_array($txp_user, $allowed)) {
        add_privs($smd_tabber_event, $levs);
    }

    // Grab this here so that the privs are known immediately after manual install
    $smd_tabber_uprivs = safe_field('privs', 'txp_users', "name = '".doSlash($txp_user)."'");

    register_tab('admin', $smd_tabber_event, gTxt('smd_tabber_tab'));
    register_callback('smd_tabber_dispatch', $smd_tabber_event);

    // Do the tabbing deed
    if (smd_tabber_table_exist(1)) {
        register_callback('smd_tabber_css_link', 'admin_side', 'head_end');
        $smd_tabs = safe_rows('*', SMD_TABBER, '1=1 ORDER BY area, sort_order, name');

        // Yuk but no other way to get these.
        // NB: 'start' missing on purpose as it has no privs by default, so needs them adding
         $smd_areas = array('content', 'presentation', 'admin', 'extensions');

        foreach ($smd_tabs as $idx => $tab) {
            $name = $tab['name'];
            $area = $tab['area'];
            $areaname = strtolower(sanitizeForUrl($area));
            $area_privname = 'tab.' . $areaname;
            $create_top = (!in_array($areaname, $smd_areas));
            $tabname = strtolower(sanitizeForUrl($name));

            $eprivs = explode(',', $tab['view_privs']);
            $rights = in_array($smd_tabber_uprivs, $eprivs);

            if ($rights) {
                if ($create_top) {
                    add_privs($area_privname, $smd_tabber_uprivs);
                    $smd_areas[] = $areaname;
                    if ($areaname != 'start') {
                        $textarray['tab_'.$areaname] = $area;
                    }
                }

                add_privs($tabname, $smd_tabber_uprivs);
                register_tab($areaname, $tabname, $name);
                register_callback('smd_tabber_render_tab', $tabname);
                $smd_tabber_callstack[$tabname] = array('name' => $name, 'page' => $tab['page'], 'style' => $tab['style']);
            }
        }
    }
}

// ------------------------
function smd_tabber_render_tab($evt, $stp) {
    global $smd_tabber_callstack, $pretext;

    $tab_info = $smd_tabber_callstack[$evt];

    // Allow multiple parse calls for any nested {replaced} content
    $parse_depth = intval(get_pref('smd_tabber_parse_depth', 1));

    pagetop($tab_info['name']);

    $html = safe_field('user_html', 'txp_page', "name='".doSlash($tab_info['page'])."'");
    if (!$html) {
        $html = '<txp:smd_tabber_edit_page />'.n.'<txp:smd_tabber_edit_style />';
    }

    // Hand over control to the Page code
    include_once txpath.'/publish.php';
    for ($idx = 0; $idx < $parse_depth; $idx++) {
        $html = parse($html);
    }

    echo $html;
}

// ------------------------
function smd_tabber_dispatch($evt, $stp) {
    if(!$stp or !in_array($stp, array(
            'smd_tabber_table_install',
            'smd_tabber_table_remove',
            'smd_tabber_css',
            'smd_tabber_prefs',
            'smd_tabber_prefsave',
            'smd_tabber_save',
            'smd_tabber_delete',
        ))) {
        smd_tabber('');
    } else $stp();
}

// ------------------------
function smd_tabber_welcome($evt, $stp) {
    $msg = '';
    switch ($stp) {
        case 'installed':
            smd_tabber_table_install();
            $msg = 'Supertabs are go!';
            break;
        case 'deleted':
            smd_tabber_table_remove();
            break;
    }
    return $msg;
}

// ------------------------
function smd_tabber_css() {
    global $event;
    $name = doSlash(gps('name'));
    $css = safe_field('css', 'txp_css', "name='$name'");
    unset($name);

    if ($css) {
        header('Content-type: text/css');
        echo $css;
        exit();
    }
}

// ------------------------
function smd_tabber_css_link() {
    global $event, $smd_tabber_event;

    // Annoyingly, we need an extra test here in case the plugin has been deleted.
    // This callback is registered before plugin deletion but by the time it runs the table is gone
    if (smd_tabber_table_exist(1)) {
        $smd_tab = safe_field('style', SMD_TABBER, "name = '".doSlash($event)."'");
        echo ($smd_tab) ? n.'<link href="?event='.$smd_tabber_event.a.'step=smd_tabber_css'.a.'name='.$smd_tab.'" rel="stylesheet" type="text/css" />'.n : '';
    }
}

// ------------------------
function smd_tabber($msg='') {
    global $smd_tabber_event, $smd_tabber_uprivs, $smd_tabber_prevs, $smd_tabber_styles;

    pagetop(gTxt('smd_tabber_tab'), $msg);
    $pref_rights = in_array($smd_tabber_prevs, explode(',', $smd_tabber_uprivs));

    if (smd_tabber_table_exist(1)) {
        $tab_name = $tab_new_name = gps('smd_tabber_name');
        $area = $curr_page = $curr_style = $sort_order = '';
        $view_privs = array();
        $tablist = $smd_areas = array();
        $tab_prefix = get_pref('smd_tabber_tab_prefix', 'tabber_', 1);

        // Can't use the smd_tabs and smd_areas lists in the global scope 'cos they're stale / strtolower()ed
        $smd_tabs = safe_rows('*', SMD_TABBER, '1=1 ORDER BY area, sort_order, name');

        if ($smd_tabs) {
            foreach ($smd_tabs as $idx => $tab) {
                $tablist[$tab['area']][$tab['name']] = $tab['name'];
                $smd_areas[$tab['area']] = strtolower(sanitizeForUrl($tab['area']));

                if ($tab['name'] == $tab_name) {
                    $sort_order = $tab['sort_order'];
                    $area = $tab['area'];
                    $view_privs = explode(',', $tab['view_privs']);
                    $curr_page = $tab['page'];
                    $curr_style = $tab['style'];
                }
            }
        }

        // Default to the current user level's privs for new items
        if ($tab_name == '') {
            $view_privs[] = $smd_tabber_uprivs;
        }

        // Build a select list of tab names, injecting optgroups above any areas
        $optgroups = (count($tablist) > 1); // Only add optgroups if there are tabs in more than one area
        $tabSelector = '';

        if ($tablist) {
            $tabSelector .= '<select name="smd_tabber_name" id="smd_tabber_name" onchange="submit(this.form);">';
            $tabSelector .= '<option value="">' . gTxt('smd_tabber_new_tab') . '</option>';
            $lastArea = '';
            $inGroups = false; // Is set to true when the first area is reached

            foreach ($tablist as $theArea => $theTabs) {
                if ($optgroups && $lastArea != $theArea) {
                    $tabSelector .= ($inGroups) ? '</optgroup>' : '';
                    $tabSelector .= '<optgroup label="'.$theArea.'">';
                    $inGroups = true;
            }

            foreach($theTabs as $theTab => $tabName) {
                $tabSelector .= '<option value="'.$theTab.'"'.(($theTab == $tab_name) ? ' selected="selected"' : '').'>' . $tabName . '</option>';
                }
            }

            $tabSelector .= (($optgroups) ? '</optgroup>' : '') . '</select>';
        }

        $areas = areas();
        $areas = array_merge($areas, $smd_areas);
        $area_list = array('' => gTxt('smd_tabber_new_area'));

        foreach ($areas as $idx => $alist) {
            $key = array_search($idx, $smd_areas);

            if ($key === false) {
                $area_list[$idx] = $idx;
            } else {
                $area_list[$key] = $key;
            }
        }

        $privs = get_groups();
        $privsel = smd_tabber_multi_select('smd_tabber_view_privs', $privs, $view_privs);

        $pages = safe_column('name', 'txp_page', "name like '".doSlash($tab_prefix)."%'");

        foreach ($pages as $idx => $page) {
            $pages[$idx] = str_replace($tab_prefix, '', $page);
        }

        $styles = safe_column('name', 'txp_css', "name like '".doSlash($tab_prefix)."%'");

        foreach ($styles as $idx => $style) {
            $styles[$idx] = str_replace($tab_prefix, '', $style);
        }

        $editcell = (($smd_tabs)
                ? '<div class="smd_tabber_label">'
                    . gTxt('smd_tabber_choose')
                    . '</div><div class="smd_tabber_value">'
                    . $tabSelector
                    . (($tab_name != '')
                        ? sp . eLink(strtolower(sanitizeForUrl($tab_name)), '', '','', gTxt('View'))
                        : '')
                    . eInput($smd_tabber_event)
                    .'</div>'
                : '');

        $pref_link = $pref_rights ? sp . eLink($smd_tabber_event, 'smd_tabber_prefs', '', '', '['.gTxt('smd_tabber_prefs').']') : '';

        // Edit form
        echo '<style type="text/css">' . $smd_tabber_styles['edit'] . '</style>';
        echo '<div id="smd_tabber_wrapper">';
        echo hed(gTxt('smd_tabber_heading'), 2);
        echo '<div class="smd_tabber_preflink">' . $pref_link . '</div>';
        echo '<form id="smd_tabber_select" action="index.php" method="post">';
        echo '<div class="smd_tabber_equal">';
        echo '<div class="smd_tabber_row">' . $editcell . '</div><!-- end row -->';
        echo '</div></form>';

        echo '<form name="smd_tabber_form" id="smd_tabber_form" action="index.php" method="post">';
        echo '<div class="smd_tabber_equal">';
        echo '<div class="smd_tabber_row">';
        echo '<div class="smd_tabber_label">' . gTxt('smd_tabber_name') . '</div>';
        echo '<div class="smd_tabber_value">'
                . fInput('text', 'smd_tabber_new_name', $tab_new_name)
                . hInput('smd_tabber_name', $tab_name)
                . (($tab_name == '')
                    ? ''
                    : sp . '<a href="?event='.$smd_tabber_event. a.'step=smd_tabber_delete' . a . 'smd_tabber_name='.urlencode($tab_name).'" class="smallerbox" onclick="return confirm(\''.gTxt('confirm_delete_popup').'\');">[x]</a>'
                )
                . '</div>'
                . '</div><!-- end row -->';
        echo '<div class="smd_tabber_row">';
        echo '<div class="smd_tabber_label">' . gTxt('smd_tabber_sort_order') . '</div>';
        echo '<div class="smd_tabber_value">'
                . fInput('text', 'smd_tabber_sort_order', $sort_order)
                . '</div>'
                . '</div><!-- end row -->';
        echo '<div class="smd_tabber_row">';
        echo '<div class="smd_tabber_label">' . gTxt('smd_tabber_area') . '</div>';
        echo '<div class="smd_tabber_value">'
                . selectInput('smd_tabber_area', $area_list, $area)
                . sp . fInput('text', 'smd_tabber_new_area', '')
                . '</div>'
                . '</div><!-- end row -->';
        echo '<div class="smd_tabber_row">';
        echo '<div class="smd_tabber_label">' . gTxt('smd_tabber_view_privs') . '</div>';
        echo '<div class="smd_tabber_value">'
                . $privsel
                . '</div>'
                . '</div><!-- end row -->';
        echo '<div class="smd_tabber_row">';
        echo '<div class="smd_tabber_label">' . gTxt('smd_tabber_page') . '</div>';
        echo '<div class="smd_tabber_value">'
                . selectInput('smd_tabber_page', $pages, $curr_page, true)
                . sp . (($curr_page) ? eLink('page', '', 'name', $curr_page, gTxt('edit')) : eLink('page', 'page_new', '', '', gTxt('create')) )
                . '</div>'
                . '</div><!-- end row -->';
        echo '<div class="smd_tabber_row">';
        echo '<div class="smd_tabber_label">' . gTxt('smd_tabber_style') . '</div>';
        echo '<div class="smd_tabber_value">'
                . selectInput('smd_tabber_style', $styles, $curr_style, true)
                . sp. (($curr_style) ? eLink('css', '', 'name', $curr_style, gTxt('edit')) : eLink('css', 'pour', '', '', gTxt('create')) )
                . '</div>'
                . '</div><!-- end row -->';

        echo '<div class="smd_tabber_row">';
        echo '<div class="smd_tabber_label">&nbsp;</div>';
        echo '<div class="smd_tabber_value">'
                . fInput('submit', 'submit', gTxt('save'), 'smd_tabber_save publish')
                . eInput($smd_tabber_event)
                . sInput('smd_tabber_save')
                . '</div>'
                . '</div><!-- end row -->';

        echo '</div><!-- end smd_tabber_equal -->';
        echo '</form>';
        echo '</div><!-- end smd_tabber_wrapper -->';
    } else {
        // Table not installed
        $btnInstall = '<form method="post" action="?event='.$smd_tabber_event.a.'step=smd_tabber_table_install" style="display:inline">'.fInput('submit', 'submit', gTxt('smd_tabber_tbl_install_lbl'), 'smallerbox').'</form>';
        $btnStyle = ' style="border:0;height:25px"';
        echo startTable('list');
        echo tr(tda(strong(gTxt('smd_tabber_prefs_some_tbl')).br.br
                .gTxt('smd_tabber_prefs_some_explain').br.br
                .gTxt('smd_tabber_prefs_some_opts'), ' colspan="2"')
        );
        echo tr(tda($btnInstall, $btnStyle));
        echo endTable();
    }
}

// ------------------------
function smd_tabber_save() {
    extract(doSlash(gpsa(array(
        'smd_tabber_name',
        'smd_tabber_new_name',
        'smd_tabber_area',
        'smd_tabber_new_area',
        'smd_tabber_page',
        'smd_tabber_style',
        'smd_tabber_sort_order',
    ))));

    $vu = gps('smd_tabber_view_privs');
    $smd_tabber_view_privs = $vu ? doSlash(join(',', $vu)) : '';

    $msg = '';
    $theArea = ($smd_tabber_new_area == '') ? $smd_tabber_area : $smd_tabber_new_area;

    if ($smd_tabber_new_name == '') {
        $msg = array(gTxt('smd_tabber_need_name'), E_WARNING);
    } else {
        if ($theArea == '') {
            $msg = array(gTxt('smd_tabber_need_area'), E_WARNING);
        } else {
            $exists = safe_field('name', SMD_TABBER, "name='$smd_tabber_new_name'");
            $same = ($smd_tabber_name != $smd_tabber_new_name) && $exists;

            if ($same == false) {
                $_POST['smd_tabber_name'] = $smd_tabber_new_name;

                if ($smd_tabber_name == '') {
                    safe_insert(SMD_TABBER, "name='$smd_tabber_new_name', sort_order='$smd_tabber_sort_order', area='".doSlash($theArea)."', page='$smd_tabber_page', style='$smd_tabber_style', view_privs='$smd_tabber_view_privs'");
                    $msg = gTxt('smd_tabber_created');
                } else {
                    safe_update(SMD_TABBER, "name='$smd_tabber_new_name', sort_order='$smd_tabber_sort_order', area='".doSlash($theArea)."', page='$smd_tabber_page', style='$smd_tabber_style', view_privs='$smd_tabber_view_privs'", "name='$smd_tabber_name'");
                    $msg = gTxt('smd_tabber_saved');
                }
            } else {
                $msg = array(gTxt('smd_tabber_exists'), E_WARNING);
            }
        }
    }

    smd_tabber($msg);
}

// ------------------------
function smd_tabber_delete() {
    global $smd_tabber_event;

    $name = doSlash(gps('smd_tabber_name'));

    $ret = safe_delete(SMD_TABBER, "name='$name'");
    $msg = gTxt('smd_tabber_deleted');

    $_GET['smd_tabber_name'] = '';

    smd_tabber($msg);
}

// ------------------------
function smd_tabber_prefs($msg='') {
    global $smd_tabber_event, $smd_tabber_styles;

    pagetop(gTxt('smd_tabber_prefs_lbl'), $msg);

    $users = safe_rows('*', 'txp_users', '1=1 ORDER BY RealName');
    $privs = array('' => gTxt('smd_tabber_all_pubs'));

    foreach ($users as $idx => $user) {
        $privs[$user['name']] = $user['RealName'];
    }

    $curr_privs = explode(',', get_pref('smd_tabber_tab_privs', ''));
    $parse_depth = get_pref('smd_tabber_parse_depth', '1');
    $tab_prefix = get_pref('smd_tabber_tab_prefix', 'tabber_');

    echo '<style type="text/css">' . $smd_tabber_styles['prefs'] . '</style>';
    echo '<form action="index.php" method="post" name="smd_tabber_prefs_form">';
    echo startTable('list');
    echo tr(tda(hed(gTxt('smd_tabber_prefs_lbl'), 2), ' colspan="3"'));
    echo tr(
        tda(gTxt('smd_tabber_tab_privs'), ' class="smd_label"')
        . tda(smd_tabber_multi_select('smd_tabber_tab_privs', $privs, $curr_privs, 10))
    );
    echo tr(
        tda(gTxt('smd_tabber_tab_prefix'), ' class="smd_label"')
        . tda(fInput('text', 'smd_tabber_tab_prefix', $tab_prefix))
    );
    echo tr(
        tda(gTxt('smd_tabber_parse_depth'), ' class="smd_label"')
        . tda(fInput('text', 'smd_tabber_parse_depth', $parse_depth))
    );
    echo tr(tda(eLink($smd_tabber_event, '', '', '', gTxt('smd_tabber_cancel')), ' class="noline"') . tda(fInput('submit', '', gTxt('save'), 'publish'). eInput($smd_tabber_event).sInput('smd_tabber_prefsave'), ' class="noline"'));
    echo endTable();
    echo '</form>';
}

// ------------------------
function smd_tabber_prefsave() {
    $depth = intval(gps('smd_tabber_parse_depth'));
    $prefix = gps('smd_tabber_tab_prefix');
    $items = gps('smd_tabber_tab_privs');
    $privs = ($items) ? join(',', $items) : '';
    set_pref('smd_tabber_tab_privs', $privs, 'smd_tabber', PREF_HIDDEN, 'text_input');
    set_pref('smd_tabber_tab_prefix', $prefix, 'smd_tabber', PREF_HIDDEN, 'text_input');
    set_pref('smd_tabber_parse_depth', $depth, 'smd_tabber', PREF_HIDDEN, 'text_input');

    $msg = gTxt('preferences_saved');
    smd_tabber($msg);
}

// ------------------------
function smd_tabber_multi_select($name, $items, $sel=array(), $size='7') {
    $out = '<select name="'.$name.'[]" multiple="multiple" size="'.$size.'">'.n;

    foreach ($items as $idx => $item) {
        $out .= '<option value="'.$idx.'"'.((in_array($idx, $sel)) ? ' selected="selected"' : '').'>'.$item.'</option>'.n;
    }

    $out .= '</select>'.n;

    return $out;
}

// ------------------------
// Add tabber table if not already installed
function smd_tabber_table_install() {
    safe_create('smd_tabber', "
        name         VARCHAR(32) NOT NULL DEFAULT '',
        sort_order   VARCHAR(32)     NULL DEFAULT '',
        area         VARCHAR(32)     NULL DEFAULT '',
        view_privs   VARCHAR(32) NOT NULL DEFAULT '',
        page         VARCHAR(32)     NULL DEFAULT '',
        style        VARCHAR(32)     NULL DEFAULT '',

        PRIMARY KEY (`name`)
    ");
}

// ------------------------
// Drop table if in database
function smd_tabber_table_remove() {
    safe_drop('smd_tabber');
}

// ------------------------
function smd_tabber_table_exist($all='') {
    if (function_exists('safe_exists')) {
        if (safe_exists('smd_tabber')) {
            return true;
        };
    } else {
        if ($all) {
            $tbls = array(SMD_TABBER => 6);
            $out = count($tbls);
            foreach ($tbls as $tbl => $cols) {
                if (gps('debug')) {
                    echo "++ TABLE ".$tbl." HAS ".count(safe_show('columns', $tbl))." COLUMNS; REQUIRES ".$cols." ++".br;
                }

                if (count(safe_show('columns', $tbl)) == $cols) {
                    $out--;
                }
            }

            return ($out===0) ? 1 : 0;
        } else {
            if (gps('debug')) {
                echo "++ TABLE ".SMD_TABBER." HAS ".count(safe_show('columns', SMD_tabber))." COLUMNS;";
            }

            return(safe_show('columns', SMD_TABBER));
        }
    }
}

// ***********
// PUBLIC TAGS
// ***********

/**
 * Public tag for making a link to edit the Page content associated with the current tab
 *
 * Stub function.
 *
 * @param  array $atts Tag attributes
 * @return string      HTML link
 */
function smd_tabber_edit_page($atts) {
	return smd_tabber_edit($atts, 'page');
}

/**
 * Public tag for making a link to edit the Stylesheet content associated with the current tab
 *
 * Stub function.
 *
 * @param  array $atts Tag attributes
 * @return string      HTML link
 */
function smd_tabber_edit_style($atts) {
	return smd_tabber_edit($atts, 'style');
}

/**
 * Create a link to edit the Page/CSS content associated with the current tab
 *
 * @param  array  $atts Tag attributes
 * @param  string $type Type of content to link to ('page' or 'style')
 * @return string       HTML link
 */

function smd_tabber_edit($atts, $type) {
    global $smd_tabber_callstack, $event;

    $friendlyName = ($type === 'style') ? 'CSS' : $type;

    extract(lAtts(array(
        'name'    => $event,
        'title'   => 'Edit '.$friendlyName,
        'class'   => '',
        'html_id' => '',
        'wraptag' => '',
    ), $atts));

    $tab_prefix = get_pref('smd_tabber_tab_prefix', 'tabber_');

    if (isset($atts['name'])) {
        $item = (strpos($name, $tab_prefix) !== false) ? $name : $tab_prefix.$name;
    } else {
        // Lookup the name in use
        $ev = (strpos($name, $tab_prefix) !== false) ? str_replace($tab_prefix, '', $name) : $name;
        $item = (isset($smd_tabber_callstack[$ev])) ? $smd_tabber_callstack[$ev][$type] : '';
    }

    $idx = 'name';
    $step = '';

    if (!$item) {
        $item = $idx = '';
        $step = ($type === 'page' ? 'page_new' : 'pour');
    }

    $endpoint = ($type === 'style') ? 'css' : 'page';

    $lnk = eLink($endpoint, $step, $idx, $item, $title);

    return ($wraptag) ? doTag($lnk, $wraptag, $class, '', $html_id) : $lnk;
}

# --- END PLUGIN CODE ---
if (0) {
?>
<!--
# --- BEGIN PLUGIN HELP ---
h1(#smd_tabber_top). smd_tabber

Create and manage your own Textpattern back-end tabs/sub-tabs, populating them with any content you wish for your users. Content and CSS are controlled by regular Textpattern Pages/Stylesheets. Acts like a multi-user, multi-tab dashboard for your admin-side users.

h2(#smd_tabber_feat). Features:

* Define new primary or secondary tabs in your menu hierarchy
* Assign a Page and a Style to menu items
* Two convenience tags allow you to add Edit links in your markup for quick access to edit the tab's content

h2. Installation / uninstallation

p(important). Requires TXP 4.4.0+

Download the plugin from either "GitHub":https://github.com/Bloke/smd_tabber/releases, or the "software page":https://stefdawson.com/sw, paste the code into the TXP _Admin -&gt; Plugins_ pane, install and enable the plugin. To uninstall, delete from the _Admin -&gt; Plugins_ page. The table containing the extra tab definitions will be removed but your tab Pages and Stylesheets will remain.

Visit the "forum thread":https://forum.textpattern.com/viewtopic.php?id=35882 for more info or to report on the success or otherwise of the plugin.

h2(#smd_tabber_usage). Usage

Visit _Admin-&gt;Manage tabs_. When there are some custom tabs defined, the dropdown beneath the heading contains a list of all your tabs, grouped by their area. Choose one of the tabs to load it into the boxes below for editing. The boxes are:

* %Tab name% : name of your tab as it appears to your users (case sensitive)
* %Sort order% : an optional value you can assign to this tab that dictates the position it will occupy in your tab sequence. Tabs are sorted by this value before being slotted into the menu
* %Assign to area% : select which primary-level tab (a.k.a. the "top row" in the Classic theme) your new tab will appear under. If you want to create a new one, type it in the adjacent box (case sensitive)
* %View privileges% : select the user privilege levels that are allowed to see this tab. Default: current level of logged-in user. Note that 'none' is an option. This allows you to remove a menu from operation and 'park' it while it is being created/edited, without fear of anyone being able to see what you are doing. When you are ready to put it into production, simply reassign it to a regular user level
* %Page template% : if you have defined at least one TXP Page (in _Presentation-&gt;Pages_) with the designated prefix (@tabber_@ by default) then you will see those pages listed here. Choose one to assign it to this tab. Click the adjacent _Create_ or _Edit_ link as a shortcut to _Presentation-&gt;Pages_
* %Stylesheet% : if you have defined at least one TXP Stylesheet (in _Presentation-&gt;Style_) with the designated prefix (@tabber_@ by default) then you will see those sheets listed here. Choose one to assign it to this tab. Click the adjacent _Create_ or _Edit_ link as a shortcut to _Presentation-&gt;Style_

h2(#smd_tabber_notes). Interface notes

h3. General

* You need to reload the admin side after saving to see any changes
* Area and Tab names are case sensitive: name them as you want your users to see the tabs
* Publisher level accounts also have a "[Preferences]":#smd_tabber_prefs link below the header
* Using smd_faux_role is a good way to quickly switch user level so you can see the tab structures you have created

h3. Tabs

* Alongside the tab selector of any tab you are editing is a _View_ link. Click to jump straight to the selected tab
* Alongside the _Tab name_ box of any tab you are editing is an [x] link. Click to delete the tab
* The _Sort order_ allows you to arbitrarily order the tabs without having to rely on inventive tab naming strategies. If all the sort boxes are left empty, the order is determined by the alphabetic order of the tab names
* Tab names must be unique -- even if they occupy different areas

h3. Areas

* Areas will appear to the right of the _Extensions_ tab, in alphabetical order; they (currently) cannot be positioned. If you want something to stand out, attach it to the 'start' tab
* Areas MUST have at least one sub-tab assigned to them to become visible

h3. Pages and Styles

* Pages and Styles must begin with your chosen prefix (Default: @tabber_@) to be selectable by the plugin. Change the prefix via the plugin's "Preferences":#smd_tabber_prefs
* If you view the content of a tab that has no Page assigned, a default page will be used which contains 'Edit Page' and 'Edit Style' links
* Pages and Styles can contain TXP tags and are parsed as if on the public side. But you don't need a DTD/head/body/footer element here because they are supplied by the admin interface: you are just filling in the content between the menu and the footer
* If you are using tags that automatically detect their context, you will probably have to manually specify the context when using those tags in admin-side pages

h2(#smd_tabber_prefs). Preferences

*Permit tabs to be managed by*

By default the tab manager is only available to any user with a Publisher account. Sometimes you may want to strictly control who can or cannot add/edit tabs. If you wish to do this, select user accounts from the _Permit tabs to be managed by_ list and hit Save. From that point on, only those explicit user accounts will be allowed access to the tab manager. %(important)Be careful not to lock yourself out% :-)

Note that no restrictions are placed on who can edit the tab Pages and Styles: they are subject to TXP's usual permissions structure so if you want to restrict access to these elements, choose suitable accounts for your users and tie them to the plugin's prefs.

*Page/Style prefix*

In order to be assignable to a tab, your custom pages / stylesheets must begin with a defined prefix. Set the prefix here. Default: @tabber_@.

*Parse depth*

Most of the time, TXP's parser takes care of nested tags nicely but in some rare instances you may have, say, @{replacement}@ strings inside tags inside tags and the parser might not be replacing everything. In these cases you can increase the parse depth of smd_tabber so it can dive deeper into the nested tag tree. The default is one pass, but if you wish to increase it, do so using this value.

h2(#smd_tabber_tags). Public tags

h3. @<txp:smd_tabber_edit_page>@ and @<txp:smd_tabber_edit_style>@

Renders a link to the _Presentation-&gt;Pages_ or _Presentation-&gt;Style_ tabs, respectively, with the current page/stylesheet loaded ready for editing. If the resource doesn't yet exist the link will take you to an empty document. You must begin the name of your page/stylesheet with your chosen prefix (see "prefs":#smd_tabber_prefs) for it to be picked up by the plugin.

Optional arguments:

* %name%: override the default name. If you want to force the link to edit a particular Page/Stylesheet, specify it here, either with or without the prefix
* %title% : the title of the link. Default: @Edit page@ / @Edit CSS@, respectively
* %wraptag% : the (X)HTML element (without angle brackets) to surround the link, e.g. @wraptag="span"@. Without this attribute set, @class@ and @html_id@ will do nothing. Default: unset.
* %class% : the CSS classname to apply to the wraptag. Default: unset
* %html_id% : the HTML id attribute to apply to the wraptag. Default: unset

h2(#smd_tabber_dashboards). Dashboards and compatibility

There is nothing to stop you using other dashboard or menu management plugins with smd_tabber. But the open architecture of smd_tabber means that, with a bit of planning, you can probably replicate most dashboard/menu functionality and perhaps do even more with it. Here are some common things that other plugins provide and how smd_tabber can be used to deliver similar functionality:

* lum_user_menu
** although not as point n' click, you could create a Page with a grid of shortcuts on it. You could define an smd_macro called 'lum_cell' that could take parameters to specify the icon, destination URL, etc. Calling that multiple times in your tab's Page would render the menu grid. You could even wrap some cells in rvm_privileged tags to control who could see which icons
* jmd_dashboard / sed_dashboard / aro_myAdmin
** you can add your own tabs under the start tab
** you can recreate the edit link functionality via an smd_macro, rsx_frontend_edit, chh_admin_tags, rss_article_edit, or even a TXP form with txp:yield
** employ Textile for fine-grained Textile processing inside your Pages (it's more robust than having to escape tags-in-tags with @notextile.@ or @==@). Alternatively, write your content in Articles and import them into your Pages with txp:article_custom
* esq_admin_splash
** again, smd_tabber can be told to reside on its own tab and you can write your help text in a tab Page with optional Textile support

If none of the above appeals, you can of course mix smd_tabber with other tab-making plugins but please be aware that each plugin does things in their own way and methods of manipulating menus vary greatly. Therefore, if you experience odd menu bar behaviour you may consider switching off various combinations of such plugins to track down what's going on.

h2. Author / credits

Written by "Stef Dawson":https://stefdawson.com/contact. Spawned from an idea by maverick, with thanks. Kudos to the feedback from the beta test team -- maruchan and maverick -- who put up with endless new versions a day until I got it right.

# --- END PLUGIN HELP ---
-->
<?php
}
?>