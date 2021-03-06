<?php
/**
 * Groups configuration for default Minify implementation
 * @package Minify
 */

/** 
 * You may wish to use the Minify URI Builder app to suggest
 * changes. http://yourdomain/min/builder/
 **/
 
	$min_assets = array('js'=>array(),'css'=>array());
	
	$min_assets['js'][] = '../js/jquery-1.8.1.min.js';
	$min_assets['js'][] = '../js/jquery-ui-1.10.2.js';
	$min_assets['js'][] = '../js/jqmanageList.js';
	$min_assets['js'][] = '../js/chosen.jquery.min.js';
	$min_assets['js'][] = '../js/jquery.inlineclick.js';
	$min_assets['js'][] = '../js/jquery.qtip.min.js';
	$min_assets['js'][] = '../js/jquery.tablesorter.js';
	$min_assets['js'][] = '../js/func.js';
	$min_assets['js'][] = '../js/parsley.js';
	$min_assets['js'][] = '../js/jquery.jqEasyCharCounter.min.js';
	$min_assets['js'][] = '../js/jquery.mtz.monthpicker.js';
	$min_assets['js'][] = '../js/erp.js';
	$min_assets['js'][] = '../js/gen_validatorv4.js';
	$min_assets['js'][] = '../js/jquery.tablesorter.js';
	$min_assets['js'][] = '../js/jquery.print-objects.js';
        
	$min_assets['css'][] = '../css/jquery-ui-lib/jquery-ui-1.10.2.custom.min.css';
	$min_assets['css'][] = '../css/chosen.css';
	$min_assets['css'][] = '../css/jquery.qtip.min.css';
	$min_assets['css'][] = '../css/admin.css';
	$min_assets['css'][] = '../css/buttons.css';
	$min_assets['css'][] = '../css/erp.css';
	

return array(
	'js'=>array("../js/jquery.js","../js/jquery.ui.js","../js/cookie.js","../js/func.js","../js/jquery.easing.js","../js/fanb.js","../js/common.js","../js/jquery.pngFix.js","../js/countdown.js","../js/cloud-zoom.1.0.2.min.js","../js/chosen.jquery.min.js","../js/gen_validatorv4.js"),
	
	'livefeed'=>array("../js/livefeed.js"),
	
	'css'=>array("../css/common.css","../css/jquery.ui.css","../css/fancyb/fancy.css","../css/cloud-zoom.css","../css/chosen.css"),
	
	'erp_js' =>$min_assets['js'],
	'erp_css' =>$min_assets['css'],
	);
