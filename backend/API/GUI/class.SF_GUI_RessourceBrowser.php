<?php
// File: $Id: class.SF_GUI_RessourceBrowser.php 55 2008-07-28 15:30:47Z bjoern $
// +----------------------------------------------------------------------+
// | Version: Sefrengo $Name:  $
// +----------------------------------------------------------------------+
// | Copyright (c) 2005 - 2007 sefrengo.org <info@sefrengo.org>           |
// +----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify |
// | it under the terms of the GNU General Public License                 |
// |                                                                      |
// | This program is subject to the GPL license, that is bundled with     |
// | this package in the file LICENSE.TXT.                                |
// | If you did not receive a copy of the GNU General Public License      |
// | along with this program write to the Free Software Foundation, Inc., |
// | 59 Temple Place, Suite 330, Boston, MA  02111-1307 USA               |
// |                                                                      |
// | This program is distributed in the hope that it will be useful,      |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of       |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the        |
// | GNU General Public License for more details.                         |
// |                                                                      |
// +----------------------------------------------------------------------+
// + Autor: $Author: bjoern $
// +----------------------------------------------------------------------+
// + Revision: $Revision: 55 $
// +----------------------------------------------------------------------+
// + Description:
// +----------------------------------------------------------------------+
// + Changes:
// +----------------------------------------------------------------------+
// + ToDo:
// +----------------------------------------------------------------------+
class SF_GUI_RessourceBrowser extends SF_API_Object{
	
	var $_ressources = array();
	
	//the current dialog 'outer' or 'inner'
	var $_current_display = 'outer';
	
	var $_display_action = null;
	var $_item_position = null;
	
	// 'list' or 'detail'
	var $_current_detail_switch_mode = 'list';

	var $_extra_url_parameter_string = '';
	
	//common preparsed export array
	var $_common_export_list = array();
	var $_common_export_detail = array();
	
	// available icons
	var $_icon_store = array( 'cat_default' => 'rb_folder_default.gif',
								'cat_link' => 'rb_folder_cat.gif',
								'item_default' => 'rb_typ_generic.gif',
								'item_link' => 'rb_typ_link.gif',
								'item_link_startpage' => 'rb_typ_startpage.gif',
								'bmp' => 'rb_typ_bmp.gif',
								'css' => 'rb_typ_css.gif',
								'gif' => 'rb_typ_gif.gif',
								'htm' => 'rb_typ_htm.gif',
								'html' => 'rb_typ_htm.gif',
								'jpg' => 'rb_typ_jpg.gif',
								'jpeg' => 'rb_typ_jpg.gif',
								'mp3' => 'rb_typ_mp3.gif',
								'pdf' => 'rb_typ_pdf.gif',
								'png' => 'rb_typ_png.gif',
								'wav' => 'rb_typ_wav.gif',
								'zip' => 'rb_typ_zip.gif'
								);
	
	//js callback function
	var $_js_callback_func = 'rbDefaultCallback(ressource_type, picked_name, picked_value)';
	
	
	function __construct() {
		global $cms_lang;
		$this->lang =& $cms_lang ;
		$this->cfg =& $GLOBALS['cfg_cms'];
		$this->cfg_client =& $GLOBALS['cfg_client'];
	}
	
	/**
	 * The browser configuration travels in the url. It is signed (HMAC-SHA256, key bound
	 * to the current session) so it can not be forged or tampered with, and it is read
	 * without allowing any class to be instantiated.
	 */
	function _signatureKey() {
		$sess = isset($GLOBALS['sess']) ? $GLOBALS['sess'] : null;
		$id = (is_object($sess) && !empty($sess->id)) ? $sess->id : '';
		return hash('sha256', 'sf-rb|' . $id);
	}

	function _sign($blob) {
		return hash_hmac('sha256', (string) $blob, $this->_signatureKey());
	}

	function importConfig($conf_string, $signature = null) {
		// check if conf string is urldecoded
		if (strstr($conf_string, '%2') && !strstr($conf_string, '/')) {
			$conf_string = urldecode($conf_string);
		}
		if ($signature === null || !hash_equals($this->_sign($conf_string), (string) $signature)) {
			header('HTTP/1.1 403 Forbidden');
			exit('Invalid or missing signature.');
		}
		$conf_string = base64_decode($conf_string);
		if (function_exists("gzcompress")) {
			$conf_string = @gzuncompress($conf_string);
		}
		$this->imp = $imp = @unserialize($conf_string, array('allowed_classes' => false));
		if (!is_array($imp)) {
			header('HTTP/1.1 400 Bad Request');
			exit('Invalid configuration.');
		}
		//print_r($this->imp['global']);
		
		//import global vars
		$this->_current_display = ($imp['global']['display'] == 'inner') 
									? 'inner': 'outer';
		$this->_display_action = $imp['global']['display_action'];
		$this->_item_position = $imp['global']['item_position'];
		$this->_current_detail_switch_mode = $imp['global']['detail_switch'];
		$callback = (string) $imp['global']['js_callback'];
		// only a plain function call like "Name.sub(arg1,arg2)" is allowed (the value ends up in javascript)
		if (!preg_match('/^[A-Za-z_$][A-Za-z0-9_$.]*\((?:[A-Za-z_][A-Za-z0-9_]*,)*[A-Za-z_][A-Za-z0-9_]*\)$/', $callback)) {
			$callback = 'rbDefaultCallback(ressource_type, picked_name, picked_value)';
		}
		$this->_js_callback_func = $callback;
		$this->_extra_url_parameter_string = $imp['global']['extra_url_parameter_string'];
		//import ressources
		if (is_array($imp['ressources'])) {
			foreach($imp['ressources'] AS $v){
				if (is_string($v['class_name']) && strpos($v['class_name'], 'SF_GUI_RESSOURCES_') === 0 && class_exists($v['class_name'])) {
					$res = new $v['class_name']();
					$res->importConfig($v);
					$this->addRessource($res);
				}
			}
		}
	}

	function setExtraUrlParmString($parm) {
		$this->_extra_url_parameter_string = $parm;
	} 
	
	function importConfigURL() {
		$this->importConfig(isset($_REQUEST['rb_conf']) ? $_REQUEST['rb_conf'] : '', isset($_REQUEST['rb_sig']) ? $_REQUEST['rb_sig'] : null);
	}
	
	function _getGlobalExportString($display, $detail_switch_mode, $custom_resource_config, $display_action, $item_position) {
		
		
		$exp = ($detail_switch_mode == 'detail') ? $this->_common_export_detail: $this->_common_export_list;
		$exp['global']['display'] = $display;
		$exp['global']['display_action'] = $display_action;
		$exp['global']['item_position'] = $item_position;
		$exp['global']['js_callback'] = $this->_js_callback_func;
		$exp['global']['extra_url_parameter_string'] = $this->_extra_url_parameter_string;
		$res = array();
		
		if (! is_array($custom_resource_config) ) {
			foreach ($this->_ressources AS $v) {
				//echo"<hr><pre>";print_r(get_class_methods($v));echo"</pre><hr>";
				sf_array_push($res, $v->exportConfig() );
			}
		} else {
			sf_array_push($res, $custom_resource_config );
		}
		

		$exp['ressources'] = $res;
		$extra = ( empty( $this->_extra_url_parameter_string ) ) ? '': '&'.$this->_extra_url_parameter_string;
		
		$cfg = serialize($exp);
		if (function_exists("gzcompress")) $cfg = gzcompress($cfg, 9);
		$blob = base64_encode($cfg);
		return urlencode($blob) . '&rb_sig=' . $this->_sign($blob) . $extra;
	}
		
	function exportConfigURL($display='outer', $detail_switch_mode='list', $custom_resource_config = null, $display_action = null, $item_position=null) {
		
		
		$url = $this->cfg_client['htmlpath'].'cms/inc/inc.ressource_browser.php?rb_conf='. 
					$this->_getGlobalExportString($display, $detail_switch_mode, 
						$custom_resource_config, $display_action, $item_position);
		return $url;
	}
	
	function addRessource($ress_object) {
		sf_array_push($this->_ressources, $ress_object);
	}
	
	function parse() {
		//build config urls
		$this->_buildCommonExport();
	}
	
	function _buildCommonExport() {
		//full listview
		//full detailview
		//ressource 1-n listview
		//ressource 1-n detailview
		//position
		
		$ress_conf = array();
		foreach ($this->_ressources AS $k=>$v) {
			sf_array_push( $ress_conf, $v->exportConfig() );
		}
		$exp['global']['display'] = $this->_current_display;
		$exp['ressources'] = $ress_conf;
		
		$exp['global']['detail_switch'] = 'list';
		$this->_common_export_list = $exp;
		
		$exp['global']['detail_switch'] = 'detail';
		$this->_common_export_detail = $exp;
	}
	
	function run() {
		$this->parse();
		
		include_once ('HTML/Template/IT.php');
		$this->tpl = new HTML_Template_IT($this->cfg['html_path'] .'tpl/'.$this->cfg['skin'].'/');
		//print_r($this->tpl);
		switch ($this->_current_display) {
			case 'inner':
				$this->getInnerDialog();
				break;
			case 'outer':
			default:
				$this->getOuterDialog();
		}
		$this->tpl->parse();
		$this->tpl->show();
	}
	
	function getOuterDialog() {
		$this->tpl->loadTemplatefile('ressource_browser_outer.tpl');
		
		$tpl_gvars['rbJsCallback'] = $this->_js_callback_func;
		$tpl_gvars['rbCssPath'] = $this->cfg['cms_html_path'] .'tpl/'.$this->cfg['skin'].'/css/';
		$tpl_gvars['rbImagePath'] = $this->cfg['cms_html_path'] .'tpl/'. $this->cfg['skin'].'/img/ressource_browser/';
		$tpl_gvars['rbLangHtmlTitle'] = $this->lang['rb_html_title'];
		$tpl_gvars['rbLangPathwayOneUp'] = $this->lang['rb_pathway_one_up'];
		$tpl_gvars['rbLangSwitchToListView'] = $this->lang['rb_switch_to_listview'];
		$tpl_gvars['rbLangSwitchToDetailView'] = $this->lang['rb_switch_to_detailview'];
		$tpl_gvars['rbLangOpen'] = $this->lang['rb_open'];
		$tpl_gvars['rbLangCancel'] = $this->lang['rb_cancel'];
		//build ressource choser
		//print_r($this->_ressources);
		$i=0;
		foreach ($this->_ressources AS $v) {
			if ($i == 0) {
				$tpl_gvars['rbStartRessourceId'] = 'ressourceItem'. $i;
				$tpl_gvars['rbStartRessourceURL'] = $this->exportConfigURL('inner', 'list', $v->exportConfigWithCustomPosition($v->getStartPosition()) );
			} 
			$this->tpl->setCurrentBlock('rbRessourceChoser');
			$tpl_vars['rbImagePath'] = $this->cfg['cms_html_path'] .'tpl/'. $this->cfg['skin'].'/img/ressource_browser/';
			$tpl_vars['rbRessourceChooserDisplayedName'] = $v->getRessourceChooserDisplayedName();
			$tpl_vars['rbRessourceChooserImage'] = $v->getRessourceChooserImage();
			$tpl_vars['rbRessourceID'] = 'ressourceItem'. $i;
			$tpl_vars['rbRessourceChooserListURL'] = $this->exportConfigURL('inner', 'list', $v->exportConfigWithCustomPosition($v->getStartPosition()) );
			$tpl_vars['rbRessourceChooserDetailURL'] = $this->exportConfigURL('inner', 'detail', $v->exportConfigWithCustomPosition($v->getStartPosition()) );
			$this->tpl->setVariable($tpl_vars);
			$this->tpl->parseCurrentBlock();
			$i++;
		}
		
		$this->tpl->setVariable($tpl_gvars);
	}
	
	
	function getInnerDialog() {
		$this->tpl->loadTemplatefile('ressource_browser_inner.tpl');
		//print_r($this->_ressources);
		$res =& $this->_ressources['0'];
		$res->generatePathwayReleatedData($res->_getPosition());
		
		//make cats
		if ($this->_display_action != 'format') {
			$res->generateCatData($res->_getPosition());
			$this->tpl->setCurrentBlock('rbCats');
			$raw_tpl = $res->_getRawTpl('cat_'.$this->_current_detail_switch_mode.'_row');
			
			for ($iter = new SF_UTILS_ArrayNumericIterator( $res->_getCatStack() );
					$iter->valid(); $iter->next() ) {
				$a = $iter->current();
				$tpl_vars['rbCatTpl'] = 
					$this->_getTpl($raw_tpl, $a, 
								 	$this->exportConfigURL('inner', $this->_getCurrentDeatailSwitchMode(), 
								 		$res->exportConfigWithCustomPosition($a['position']) 
									 ) 
							  	  );
							  	  
				$this->tpl->setVariable($tpl_vars);
				$this->tpl->parseCurrentBlock();
			}
			unset($tpl_vars);
		}

		//make items
		$this->tpl->setCurrentBlock('rbItems');
		$raw_tpl = $res->_getRawTpl('item_'.$this->_current_detail_switch_mode.'_row');
		if ($this->_display_action != 'format') {
			$res->generateItemData($res->_getPosition());
			$items = $res->_getItemStack();
		} else {
			$res->generateItemVariousFormatsData( $res->_getPosition(), $this->_item_position);
			$items = $res->_getVariousFormatsStack();
		}
		for ($iter = new SF_UTILS_ArrayNumericIterator( $items );
				$iter->valid(); $iter->next() ) {
			$a = $iter->current();
			if ($a['format_position']) {
				$url = $this->exportConfigURL('inner', $this->_getCurrentDeatailSwitchMode(), 
							 		$res->exportConfigWithCustomPosition($res->_getCurrentPosition(), $a['format_position']),
							 		'format',  $a['format_position']
						) ;
				//echo 'in'.$url.'<br />';
			} else {
				$url = '';
			}
			$tpl_vars['rbItemTpl'] = $this->_getTpl($raw_tpl, $a, $url);
			$this->tpl->setVariable($tpl_vars);
			$this->tpl->parseCurrentBlock();
		}
		
		//make pathway, pathwayup, detailswitch
		$pathway_names = array();
		$pathway_urls = array();
		//print_r($res->_getPathwayStack() );
		for ($iter = new SF_UTILS_ArrayNumericIterator( $res->_getPathwayStack() );
				$iter->valid(); $iter->next() ) {
			//name, position, level
			$a = $iter->current();
			if ($a['level'] > 0 ) {
				$arrow = str_repeat('-', $a['level']*2) .'> ';
			} else {
				$arrow = '';
			}
			
			sf_array_push($pathway_names, $arrow . $a['name']);
			sf_array_push($pathway_urls, $this->exportConfigURL(
										'inner', 
										$this->_getCurrentDeatailSwitchMode(), 
							 			$res->exportConfigWithCustomPosition($a['position']) ) );
		}
		
		if ($this->_display_action == 'format') {
			if (! $res->disable_format_selectbox)
			{
				sf_array_push($pathway_names, $arrow . $a['name'] . ' - '.$this->lang['rb_res_file_formatchoser']);
				sf_array_push($pathway_urls, $this->exportConfigURL(
											'inner', 
											$this->_getCurrentDeatailSwitchMode(), 
								 			$res->exportConfigWithCustomPosition($a['position']),
								 			'format',  $this->_item_position ) );
			}		
		}
		
		$tpl_global_vars['rbCssPath'] = $this->cfg['cms_html_path'] .'tpl/'.$this->cfg['skin'].'/css/';
		$tpl_global_vars['rbImagePath'] = $this->cfg['cms_html_path'] .'tpl/'. $this->cfg['skin'].'/img/ressource_browser/';
		$tpl_global_vars['rbCatTplStart'] = $res->_getRawTpl( 'cat_'.$this->_getCurrentDeatailSwitchMode().'_start');
		$tpl_global_vars['rbCatTplStart'] = str_replace(array('{lang_catname}', '{lang_catdesc}'), array($this->lang['rb_cat_name'], $this->lang['rb_cat_desc']), $tpl_global_vars['rbCatTplStart']);
		$tpl_global_vars['rbCatTplEnd'] = $res->_getRawTpl( 'cat_'.$this->_getCurrentDeatailSwitchMode().'_end' );
		$tpl_global_vars['rbItemTplStart'] = $res->_getRawTpl( 'item_'.$this->_getCurrentDeatailSwitchMode().'_start' );
		$tpl_global_vars['rbItemTplEnd'] = $res->_getRawTpl( 'item_'.$this->_getCurrentDeatailSwitchMode().'_end' );
		$tpl_global_vars['rbPathwayNames'] = "'" . sf_implode("','",$pathway_names) . "'";
		$tpl_global_vars['rbPathwayURLs'] = "'" . sf_implode("','",$pathway_urls) . "'";
		if ($this->_display_action == 'format') {
			$tpl_global_vars['rbPathwaySelectedURL'] = $this->exportConfigURL(
											'inner', 
											$this->_getCurrentDeatailSwitchMode(), 
								 			$res->exportConfigWithCustomPosition( $res->_getCurrentPosition() ),
							 				'format',  $this->_item_position );
			$tpl_global_vars['rbPathwayUpURL'] = $this->exportConfigURL(
											'inner', 
											$this->_getCurrentDeatailSwitchMode(),  
								 			$res->exportConfigWithCustomPosition( $res->_getCurrentPosition() ));
			$tpl_global_vars['rbDetailSwitchURL'] = $this->exportConfigURL(
											'inner', 
											$this->_getOppositeDeatailSwitchMode(), 
								 			$res->exportConfigWithCustomPosition( $res->_getCurrentPosition() ),
							 				'format',  $this->_item_position );
			
		} else {		
			$tpl_global_vars['rbPathwaySelectedURL'] = $this->exportConfigURL(
											'inner', 
											$this->_getCurrentDeatailSwitchMode(), 
								 			$res->exportConfigWithCustomPosition( $res->_getCurrentPosition() ) );
			$tpl_global_vars['rbPathwayUpURL'] = $this->exportConfigURL(
											'inner', 
											$this->_getCurrentDeatailSwitchMode(),  
								 			$res->exportConfigWithCustomPosition( $res->_getPathwayOneUpPosition() ) );
			$tpl_global_vars['rbDetailSwitchURL'] = $this->exportConfigURL(
											'inner', 
											$this->_getOppositeDeatailSwitchMode(), 
								 			$res->exportConfigWithCustomPosition( $res->_getCurrentPosition() ) );
		}
		
		$tpl_global_vars['rbCurrentDetailSwitchView'] = $this->_getCurrentDeatailSwitchMode();
		
		$tpl_global_vars['rbDetailSwitchIsActive'] = ($res->_getDetailswitchIsActive()) ? 'true':'false';
		$tpl_global_vars['rbPathwayOneUpIsActive'] = ($res->_getPathwayOneUpIsActive()) ? 'true':'false';
		$tpl_global_vars['rbRessourceName'] = $res->getRessourceName();
		$this->tpl->setCurrentBlock();
		$this->tpl->setVariable($tpl_global_vars);
		$this->tpl->parseCurrentBlock();
	}
	
	function setJSCallbackFunction($fnc_name = 'rbDefaultCallback', 
									$parms =array('ressource_type', 'picked_name', 'picked_value') ) {
		
		$this->_js_callback_func = $fnc_name . '('. sf_implode(',', $parms) .')';
		//rbDefaultCallback(ressource_type, picked_name, picked_value)
	}
	
	function _getCurrentDeatailSwitchMode() {
		return $this->_current_detail_switch_mode;
	}
	
	function _getOppositeDeatailSwitchMode() {
		return ($this->_current_detail_switch_mode == 'detail') ? 'list' : 'detail';
	}
	
	/**
	 * Returns a single row template of a RessourceBrowser item or cat
	 * 
	 * @param string view - can be 'list' or 'detail'
	 * @param string type - possible values are 'cat' or 'item'
	 * @param array data - specific data array - see return values of the
	 * methods _addItem and _addCat
	 * @param string exporturl - the exporturl is only set for cats and contains
	 * all configdata to generate the browserdialog one catlevel up
	 * 
	 * @return string template of one single row
	 * 
	 */	
	function _getTpl($tpl, $data, $exporturl = null) {
		
		if (! is_array($data)) {
			return false;
		}

		$data['exporturl'] = $exporturl;
		$trouble_chars = array("'", '"');
		$trouble_chars_solved = array("\'", '&quot;');
		$data['js_name'] = str_replace($trouble_chars, $trouble_chars_solved, $data['picked_name_prefix'] . $data['name']);
		$data['js_value'] = str_replace($trouble_chars, $trouble_chars_solved, $data['value']);
		$data['icon'] = $this->_getIcon($data['icon_type'], $exporturl == null ? 'item': 'cat');
		return $this->_arrayStrReplaceRecursive($tpl, $data);
	}
	
	function _arrayStrReplaceRecursive($tpl, $data) {
		
		foreach ($data AS $k => $v) {			
			$tpl = (is_array($v)) ? $this->_arrayStrReplaceRecursive($tpl, $v) :
										str_replace('{'.$k.'}', $v, $tpl);	
		}

		return $tpl;
	}
	
	function _getIcon($identifier, $type = null) {
		$image_path = $this->cfg['cms_html_path'] .'tpl/'. $this->cfg['skin'].'/img/ressource_browser/';
		$im = '<img src="%s" width="16" height="16" alt="" />';
		if (sf_array_key_exists($identifier, $this->_icon_store) ) {
			return sprintf($im, $image_path. 'icons/'.$this->_icon_store[$identifier]);
		} else {
			if ($type != 'cat' && $type != 'item') {
				$type == 'item';
			}
			return sprintf($im, $image_path. 'icons/'.$this->_icon_store[$type.'_default']);
		}
		
		
	}
	
}

class SF_UTILS_ArrayNumericIterator {
	var $arr = array();
	var $pos_current = 0;
	var $pos_max = 0;
	
	function __construct($arr) {
		$this->arr = $arr;
		$this->pos_max = sf_count($this->arr);
	}
	
	 /**
	* setzt den Iterator zurueck auf das erste Element
	*/
	function rewind(){
		$this->pos_current = 0;
	}
	
	/**
	* validieren, dass es das aktuelle Element gibt
	*/
	function valid(){
		return sf_array_key_exists($this->pos_current, $this->arr);
	}
	
	/**
	* zum naechsten Element springen
	*/
	function next(){
		++$this->pos_current;
	}
	
	/**
	* gibt den Wert des aktuellen Elements zurueck
	*/
	function current(){
		return $this->arr[$this->pos_current];
	}
	
	/**
	* gibt den Schluessel des aktuellen Elements zurueck
	*/
	function key(){
		return $this->pos_current;
	}
}
?>
