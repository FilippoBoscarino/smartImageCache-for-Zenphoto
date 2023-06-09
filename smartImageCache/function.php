<?php
/**
 * This module is intended to collect all the functions needed to smartCacheImage plugin.
 * @package plugins
 * @subpackage smartImageCache
 */


/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
/* Function:	getCacheImageURI()																														*/
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
function getCacheImageURI($args,$album,$image) {
	return imgSrcURI(getImageCacheFilename($album,$image,$args));
}
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
/* Function:	getToken()																																*/
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
function getToken($param,$debug=false) {
	return ($debug?$param.'-':'').sha1($param);
}
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
/* Function:	checkToken()																															*/
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
function checkToken($token,$param) {
	return ($token==getToken($param));
}
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
?>