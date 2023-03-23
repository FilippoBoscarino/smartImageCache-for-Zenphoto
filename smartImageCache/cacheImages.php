<?php
/**
 * This template is used to generate cache images. Running it will process the entire gallery,
 * supplying an album name (ex: loadAlbums.php?album=newalbum) will only process the album named.
 * Passing clear=on will purge the designated cache before generating cache images
 * @package plugins
 * @subpackage smartImageCache
 */
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
/* Definition																																			*/
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
// force UTF-8 Ø
define('OFFSET_PATH', 3);
define('CR_LF',chr(13).chr(10));
require_once('../../zp-core/admin-globals.php');
require_once('../../zp-core/functions/functions-image.php');
require_once('../../zp-core/template-functions.php');
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
/* Controls																																				*/
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
admin_securityChecks((isset($_REQUEST['album'])?ALBUM_RIGHTS:NULL),$return=currentRelativeURL());

/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
/* Parameters																																			*/
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
if (isset($_REQUEST['action'])) {$action=sanitize($_REQUEST['action']);}
if (isset($_REQUEST['album'])) {
	$album_path=sanitize($_GET['album']);
} else if (isset($_POST['album'])) {
	$album_path=sanitize(urldecode($_POST['album']));
} else {
	$album_path='';
}
if ($album_path) {
	$folder=sanitize_path($album_path);
	$object=$folder;
	$tab='edit';
	$album=AlbumBase::newAlbum($folder);
	if (!$album->isMyItem(ALBUM_RIGHTS)) {
		if (!zp_apply_filter('admin_managed_albums_access',false,$return)) {redirectURL(FULLWEBPATH.'/'.ZENFOLDER.'/admin.php');}
	}
} else {
	$object='<em>'.gettext('Gallery').'</em>';
	$_zp_admin_menu['overview']['subtabs']=array(
			gettext('Cache images')=>FULLWEBPATH.'/'.USER_PLUGIN_FOLDER.'/smartImageCache/cacheImages.php?page=overview&tab=images'
	);
}

$chunk=(isset($_REQUEST['chunk'])?sanitize($_REQUEST['chunk']):0);
$process=(isset($_REQUEST['chunk'])&&($chunk>0)?true:false);
$quantity=(isset($_REQUEST['quantity'])?sanitize($_REQUEST['quantity']):getOption('smartImageCache_howmany'));
$auto=(isset($_REQUEST['auto'])?sanitize($_REQUEST['auto']):getOption('smartImageCache_autoadvance'));
$timer=(isset($_REQUEST['timer'])?sanitize($_REQUEST['timer']):getOption('smartImageCache_autopause'));

cacheManager::$sizes=cacheManager::getSizes('active');

if (isset($_GET['select'])&&isset($_POST['enable'])) {
	XSRFdefender('cacheImages');
	$enabled_sizes=sanitize($_POST['enable']);
	if(!is_array($enabled_sizes)||empty($enabled_sizes)) {
		$enabled_sizes=array();
	}
	cacheManager::$enabledsizes=$enabled_sizes;
} else {
	cacheManager::$enabledsizes=array();
}
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
/* Display																																				*/
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
printAdminHeader('overview','images');
echo '</head>'.CR_LF;
echo '<body>'.CR_LF;
printLogoAndLinks();
echo '	<div id="main">'.CR_LF;
			printTabs();
echo '		<div id="content">'.CR_LF;
				printSubtabs();
echo '			<div class="tabbox">'.CR_LF;
					zp_apply_filter('admin_note','cache','');
					$clear=sprintf(gettext('Refreshing cache for %s'),$object);

					if ($album_path) {
						$returnpage='/admin-edit.php?page=edit&album='.$album_path;
						echo "\n<h2>".$clear."</h2>";
					} else {
						$returnpage='/admin.php';
						echo "\n<h2>".$clear."</h2>";
					}

					$currenttheme=$_zp_gallery->getCurrentTheme();
					$themes=array();
					foreach ($_zp_gallery->getThemes() as $theme=>$data) {
						$themes[$theme]=$data['name'];
					}
					$last='';
					cacheManager::printJS();
					cacheManager::printCurlNote();
					if (empty(cacheManager::$enabledsizes)) {
echo '					<p>'.gettext('This tool searches uncached image sizes from your albums or within a theme or plugin if they are registered to the cacheManager properly. If uncached images sizes exist you can have this tool generate these. If you like to re-generate existing cache image sizes, you have to clear the image cache manually first.').'</p>'.CR_LF;
echo '					<p class="notebox">'.gettext('Note that this is a quite time and server power consuming measure depending on the number of images to pre-cache, their dimensions and the power of your server.<br>').CR_LF;
echo '						'.gettext('A Smart extension is activated and to prevent server surcharges.<br>').CR_LF;
echo '						'.gettext('Anyway, if your server is not able to process all albums and images try one album after another from each album edit page, and try a lower number of images per cycle.<br>Also remember that Zenphoto will create any size on the fly right when needed.<br>').CR_LF;
echo '					</p>'.CR_LF;
					}
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
/* Form																																					*/
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
echo '				<form class="dirty-check clearfix" name="size_selections" action="?select&album='.$album_path.'&chunk='.($chunk+1).'&quantity='.$quantity.($auto?'&auto='.$auto.'&timer='.$timer:'').'" method="post" autocomplete="off">'.CR_LF;
echo '					'.XSRFToken('cacheImages');
echo '					<ol class="no_bullets">'.CR_LF;
							$defaultsizes=array(	array(	'option'=>'cache_full_image',
															'key'=>'*',
															'text'=>gettext('Full Image')),
													array(	'option'=>'cacheManager_defaultthumb',
															'key'=>'defaultthumb',
															'text'=>gettext('Default thumb size (or manual crop)')),
													array(	'option'=>'cacheManager_defaultsizedimage',
															'key'=>'defaultsizedimage',
															'text'=>gettext('Default sized image size'))
							);
							foreach($defaultsizes as $defaultsize) {
								if (getOption($defaultsize['option'])&&(empty(cacheManager::$enabledsizes)||array_key_exists($defaultsize['key'],cacheManager::$enabledsizes))) {
									if (!empty(cacheManager::$enabledsizes)) {
										$checked=' checked="checked" disabled="disabled"';
									} else {
										if(in_array($defaultsize['key'],array('defaultthumb','defaultsizedimage'))) {
											$checked=' checked="checked"';
										} else {
											$checked='';
										}
									}
									smartImageCache::$imagesizes_sizes++;
									cacheManager::printSizesListEntry($defaultsize['key'],$checked,$defaultsize['text']);
								}
							}
							$seen=array();
							foreach (cacheManager::$sizes as $key=>$cacheimage) {
								if ((empty(cacheManager::$enabledsizes)||array_key_exists($key, cacheManager::$enabledsizes))) {
									$checked='';
									if (array_key_exists($key,cacheManager::$enabledsizes)) {
										$checked=' checked="checked" disabled="disabled"';
									} else {
										if ($currenttheme==$cacheimage['theme']||$cacheimage['theme']=='admin') {$checked=' checked="checked"';}
									}
									smartImageCache::$imagesizes_sizes++;
									$size=(isset($cacheimage['image_size'])?$cacheimage['image_size']:NULL);
									$width=(isset($cacheimage['image_width'])?$cacheimage['image_width']:NULL);
									$height=(isset($cacheimage['image_height'])?$cacheimage['image_height']:NULL);
									$cw=(isset($cacheimage['crop_width'])?$cacheimage['crop_width']:NULL);
									$ch=(isset($cacheimage['crop_height'])?$cacheimage['crop_height']:NULL);
									$cx=(isset($cacheimage['crop_x'])?$cacheimage['crop_x']:NULL);
									$cy=(isset($cacheimage['crop_y'])?$cacheimage['crop_y']:NULL);
									$thumbstandin=(isset($cacheimage['thumb'])?$cacheimage['thumb']:NULL);
									$effects=(isset($cacheimage['gray'])?$cacheimage['gray']:NULL);
									$passedWM=(isset($cacheimage['wmk'])?$cacheimage['wmk']:NULL);
									$args=array($size,$width,$height,$cw,$ch,$cx,$cy,NULL,$thumbstandin,NULL,$thumbstandin,$passedWM,NULL,$effects);
									$postfix=getImageCachePostfix($args);
									if (isset($cacheimage['maxspace'])&&$cacheimage['maxspace']) {
										if ($width && $height) {
											$postfix=str_replace('_w','_wMax',$postfix);
											$postfix=str_replace('_h','_hMax',$postfix);
										} else {
											$postfix='_'.gettext('invalid_MaxSpace');
											$checked.=' disabled="disabled"';
										}
									}
									$themeid=$theme=$cacheimage['theme'];
									if (isset($themes[$theme])) {$themeid=$themes[$theme];}
									if ($theme!=$last&&empty(cacheManager::$enabledsizes)) {
										if ($last) {echo '						</ol></span></li>';}
										$last=$theme;
echo '									<li>'.CR_LF;
echo '										<span class="icons" id="'.$theme.'_arrow">'.CR_LF;
echo '											<a href="javascript:showTheme(\''.$theme.'\');" title="'.gettext('Show').'">'.CR_LF;
echo '												<img src="'.FULLWEBPATH.'/'.USER_PLUGIN_FOLDER.'/smartImageCache/images/arrow_down.png" height="20" alt="">'.CR_LF;
echo '											</a>'.CR_LF;
echo '										</span>'.CR_LF;
echo '										<label>'.CR_LF;
echo '											<input type="checkbox" name="'.$theme.'" id="'.$theme.'" value="" onclick="checkTheme(\''.$theme.'\');"'.$checked.'/> '.sprintf(gettext('all sizes for <i>%1$s</i>'),$themeid).CR_LF;
echo '										</label>'.CR_LF;
echo '										<span id="'.$theme.'_list" style="display:none">'.CR_LF;
echo '											<ol class="no_bullets">'.CR_LF;
									}
									$show=true;
									if (!empty(cacheManager::$enabledsizes)) {
										if (array_key_exists($postfix,$seen)) {
											$show=false;
											unset(cacheManager::$sizes[$key]);
										}
										$seen[$postfix]=true;
									}
									if ($show) {cacheManager::printSizesListEntry($key,$checked,ltrim($postfix,'_'),$theme);}
								}
							}
							if (empty(cacheManager::$enabledsizes)) {echo '						</ol></span></li>'.CR_LF;}

echo '						<br>'.CR_LF;
echo '						<li><label>'.gettext('How many items has to be processed every cicle.').CR_LF;
echo '							<input type="number" size="4" name="quantity" id="quantity" value="'.$quantity.'" '.($process?'disabled="disabled"':'').'/> '.gettext('<em>Value 0 (zero) means all images togheter in one chunk.</em> It can be used to review and control all the sizes toghether, when they are already cached.').CR_LF;
echo '						</label></li>'.CR_LF;
echo '						<br>'.CR_LF;
echo '						<li><label>'.CR_LF;
echo '							<input type="checkbox" name="auto" id="auto" value="true" '.($auto?'checked':'').' onchange="document.getElementById(\'timer\').disabled=!this.checked" '.($process?'disabled="disabled"':'').'/> '.gettext('Enable process auto advance. It starts after ').' '.CR_LF;
echo '							<input type="number" size="4" name="timer" id="timer" value="'.$timer.'" '.(!$auto?'disabled="disabled"':'').'" '.($process?'disabled="disabled"':'').'/> '.gettext('secs').CR_LF;
echo '						</label></li>'.CR_LF;
echo '					</ol>'.CR_LF;
						$button=false;
						if (!empty(cacheManager::$enabledsizes)) {
							if (smartImageCache::$imagesizes_sizes) {
								$allalbums=array();
								if($album_path) {
									$allalbums[]=$album_path;
								} else {
									$allalbums=$_zp_gallery->getAlbums();
								}

								// general counts
								if ($album_path) {
									$album_actual=AlbumBase::newAlbum($album_path);
									smartImageCache::create_albums_tree($album_actual);
									smartImageCache::$imagesizes_total=smartImageCache::$images_total*smartImageCache::$imagesizes_sizes;
									unset($albobj);
								} else {
									smartImageCache::create_albums_tree($album_actual);
									smartImageCache::$imagesizes_total=$_zp_gallery->getNumImages()*smartImageCache::$imagesizes_sizes;
								}
								$quantity=(($quantity==0)?smartImageCache::$images_total:$quantity);
								$chunk_total=ceil(smartImageCache::$images_total/$quantity);

echo '							<p>'.sprintf(ngettext('%1$u cache size to apply for %2$u items (%3$u cache sizes items in total.*)','%1$u cache sizes to apply for %2$u items (%3$u cache sizes images in total.*)',smartImageCache::$imagesizes_total),smartImageCache::$imagesizes_sizes,$quantity,smartImageCache::$imagesizes_total).'<br>'.CR_LF;
echo '								<em>'.gettext('* Approximate number not counting already existing cache sizes and of the object which do not need caching.').'</em><br>'.CR_LF;
echo '								<em>'.gettext('* If some cache size appears not to be loaded below, try to pass mouse pointer on it, to attempt reloading.').'</em>'.CR_LF;
echo '							</p>'.CR_LF;
echo '							<hr>'.CR_LF;
echo '							<div class="imagecaching_progress">'.CR_LF;
echo '								<h2 class="imagecaching_headline">'.gettext('Image caching in progress.').'</h2>'.CR_LF;
echo '								<div class="notebox">'.CR_LF;
echo '									<p>'.gettext('Please be patient as this might take quite a while! It depends on the number of images to pre-cache, their dimensions and the power of your server.').'</p>'.CR_LF;
echo '									<p>'.gettext('If you move away from this page before this loader disapeared, the caching will be incomplete but you can re-start any time later.').'</p>'.CR_LF;
echo '								</div>'.CR_LF;
echo '								<img class="imagecaching_loader" src="'.WEBPATH.'/'.ZENFOLDER.'/images/ajax-loader.gif" alt="">'.CR_LF;

echo '								<ul>'.CR_LF;
echo '									<li>'.gettext('Automation:').' <b><span class="imagecaching_auto">'.($auto?'ON':'OFF').'</span></b> '.($auto?'(':'').'<span class="imagecaching_pause">'.($auto?$timer:'').'</span>'.($auto?' secs waiting)':'').'</li>'.CR_LF;
echo '									<li>'.gettext('Chunk processed:').' <b><span class="imagecaching_chunkcount">'.$chunk.'</span></b> / <b><span>'.$chunk_total.'</span></b></li>'.CR_LF;
echo '									<li>'.gettext('Albums processed:').' <b><span class="imagecaching_albumcount">-</span></b> / <b><span>'.smartImageCache::$albums_total.'</span></b></li>'.CR_LF;
echo '									<li>'.gettext('Items processed:').' <b><span class="imagecaching_imagecount">-</span></b> / <b><span>'.smartImageCache::$images_total.'</span></b> ('.$quantity.' images every chunk)</li>'.CR_LF;
echo '									<li>'.gettext('Cache sizes processed:').' <b><span class="imagecaching_sizecount">-</span></b> / <b><span>'.smartImageCache::$imagesizes_total.'</span></b> ('.($quantity*smartImageCache::$imagesizes_sizes).' cache sizes every chunk)'.CR_LF;
echo '										<br>'.gettext('Results for chunk ').'<b>'.$chunk.'</b>:<ul>'.CR_LF;
echo '											<li><img src="'.FULLWEBPATH.'/'.USER_PLUGIN_FOLDER.'/smartImageCache/images/cache.png" height="20" alt="cached" title="cached"> '.gettext('Already cached: ').'<span class="imagecaching_imagesizes_cached">'.smartImageCache::$imagesizes_cached.'</span> '.gettext('sizes').'</li>'.CR_LF;
echo '											<li><img src="'.FULLWEBPATH.'/'.USER_PLUGIN_FOLDER.'/smartImageCache/images/ok.png" height="20" alt="ok" title="ok"> '.gettext('Generated: ').'<span class="imagecaching_imagesizes_ok">'.smartImageCache::$imagesizes_ok.'</span> '.gettext('sizes').'</li>'.CR_LF;
echo '											<li><img src="'.FULLWEBPATH.'/'.USER_PLUGIN_FOLDER.'/smartImageCache/images/ko.png" height="20" alt="ko" title="ko"> '.gettext('Failed: ').'<span class="imagecaching_imagesizes_ko">'.smartImageCache::$imagesizes_ko.'</span> '.gettext('sizes').'</li>'.CR_LF;
echo '											<li><img src="'.FULLWEBPATH.'/'.USER_PLUGIN_FOLDER.'/smartImageCache/images/unknown.png" height="20" alt="tentative" title="tentative"> '.gettext('Tentative: ').'<span class="imagecaching_imagesizes_maybe">'.smartImageCache::$imagesizes_maybe.'</span> '.gettext('sizes').'</li>'.CR_LF;
echo '											<li><img src="'.FULLWEBPATH.'/'.USER_PLUGIN_FOLDER.'/smartImageCache/images/media.png" height="20" alt="media" title="media"> '.gettext('Multimedia: ').'<span class="imagecaching_imagesizes_media">'.smartImageCache::$imagesizes_media.'</span> '.gettext('sizes not required').'</li>'.CR_LF;
echo '											<br>'.CR_LF;
echo '											<li><img src="'.FULLWEBPATH.'/'.USER_PLUGIN_FOLDER.'/smartImageCache/images/time.png" height="20" alt="time" title="time"> '.gettext('Processing time: ').' <b><span class="imagecaching_time">-</span></b></li>'.CR_LF;
echo '										</ul>'.CR_LF;
echo '									</li>'.CR_LF;
echo '								</ul>'.CR_LF;
echo '							</div>'.CR_LF;

								if ($process&&($chunk<$chunk_total)) {
echo '								<p class="buttons buttons_cachefinished clearfix">'.CR_LF;
echo '									<button class="tooltip" type="submit" title="'.gettext('Continue').'">'.CR_LF;
echo '										<img src="'.FULLWEBPATH.'/'.USER_PLUGIN_FOLDER.'/smartImageCache/images/play.png" height="20" id="auto_reloader" alt="continue">'.CR_LF;
echo '									  	<strong>'.gettext("Continue job").'</strong>'.CR_LF;
echo '									</button>'.CR_LF;
										if ($auto) {
echo '										<button class="tooltip" type="button" id="pause_button1" title="'.gettext('Pause').'" onclick="stopper();">'.CR_LF;
echo '											<img src="'.FULLWEBPATH.'/'.USER_PLUGIN_FOLDER.'/smartImageCache/images/pause.png" height="20" alt="pause">'.CR_LF;
echo '										  	<strong>'.gettext("Pause job").'</strong>'.CR_LF;
echo '										</button>'.CR_LF;
										}
echo '									<button class="tooltip" type="button" title="'.gettext('Reload').'">'.CR_LF;
echo '										<img src="'.FULLWEBPATH.'/'.USER_PLUGIN_FOLDER.'/smartImageCache/images/reload.png" height="20" alt="reload" onclick="history.go(0);"/>'.CR_LF;
echo '									  	<strong>'.gettext("Reload chunk").'</strong>'.CR_LF;
echo '									</button>'.CR_LF;
echo '								</p>'.CR_LF;
								} else {
									if ($process) {smartImageCache::printButtons($returnpage,$album_path,false);}
								}
echo '							<hr>'.CR_LF;
echo '							<h2>'.gettext('Chunk caching log').'</h2>'.CR_LF;
echo '							<hr>'.CR_LF;
								smartImageCache::$starttime=time();

								@set_time_limit(getOption('smartImageCache_howlast')*1000);

								foreach ($allalbums as $album) {
									$album_actual=AlbumBase::newAlbum($album);
									if (!$album_actual->isDynamic()||count($allalbums)==1) {
										smartImageCache::work_albums_tree($chunk,$quantity);
									}
								}
echo '							<p><strong>'.gettext('Chunk caching done!').'</strong></p>'.CR_LF;
echo '							<script>'.CR_LF;
echo '								$(document).ready(function() {'.CR_LF;
echo '									$(\'.imagecaching_progress\').addClass(\'messagebox\');'.CR_LF;
echo '									$(\'.imagecaching_headline\').text(\''.gettext('Chunk caching done!').'\');'.CR_LF;
echo '									$(\'.imagecaching_progress .notebox,.imagecaching_loader\').remove();'.CR_LF;

echo '									$(\'.imagecaching_auto\').text(\''.($auto?'ON':'OFF').'\');'.CR_LF;
echo '									$(\'.imagecaching_pause\').text(\''.$timer.'\');'.CR_LF;
echo '									$(\'.imagecaching_albumcount\').text(\''.smartImageCache::$albums_cached.'\');'.CR_LF;
echo '									$(\'.imagecaching_imagecount\').text(\''.smartImageCache::$images_cached.'\');'.CR_LF;
echo '									$(\'.imagecaching_sizecount\').text(\''.smartImageCache::$imagesizes_worked.'\');'.CR_LF;
echo '									$(\'.imagecaching_imagesizes_cached\').text(\''.smartImageCache::$imagesizes_cached.'\');'.CR_LF;
echo '									$(\'.imagecaching_imagesizes_ok\').text(\''.smartImageCache::$imagesizes_ok.'\');'.CR_LF;
echo '									$(\'.imagecaching_imagesizes_ko\').text(\''.smartImageCache::$imagesizes_ko.'\');'.CR_LF;
echo '									$(\'.imagecaching_imagesizes_maybe\').text(\''.smartImageCache::$imagesizes_maybe.'\');'.CR_LF;
echo '									$(\'.imagecaching_imagesizes_media\').text(\''.smartImageCache::$imagesizes_media.'\');'.CR_LF;
echo '									$(\'.imagecaching_time\').text(\''.gmdate('H:i:s',(time()-smartImageCache::$starttime)).'\');'.CR_LF;
echo '									$(\'.buttons_cachefinished\').removeClass(\'hidden\');'.CR_LF;
echo '								});'.CR_LF;
echo '							</script>'.CR_LF;
							} else {
								$button=false;
echo '							<p>'.gettext('No cache sizes enabled.').'</p>'.CR_LF;
							}
						} else {
							$button=array('text'=>gettext("Cache the images"),'title'=>gettext('Executes the caching of the selected image sizes.'));
						}

						if ($process&&($chunk<$chunk_total)) {
							if ($auto && isset($_REQUEST['chunk'])&&(smartImageCache::$imagesizes_ko==0)&&(smartImageCache::$imagesizes_maybe==0)) {
echo '							<script>'.CR_LF;
echo '								var auto_id=setTimeout(next,'.($auto?($timer*1000):0).');'.CR_LF;
echo '								function next() {'.CR_LF;
echo '									size_selections.submit();'.CR_LF;
echo '								}'.CR_LF;
echo '								function stopper() {'.CR_LF;
echo '									clearTimeout(auto_id);'.CR_LF;
echo '									$(\'.imagecaching_auto\').text(\'PAUSED\');'.CR_LF;
echo '									$(\'.imagecaching_pause\').text(\' - \');'.CR_LF;
echo '									document.getElementById(\'pause_button1\').remove();'.CR_LF;
echo '									document.getElementById(\'pause_button2\').remove();'.CR_LF;
echo '								}'.CR_LF;
echo '							</script>'.CR_LF;
							}
echo '						<p class="buttons buttons_cachefinished clearfix">'.CR_LF;
echo '							<button class="tooltip" type="submit" title="'.gettext('Continue').'">'.CR_LF;
echo '								<img src="'.FULLWEBPATH.'/'.USER_PLUGIN_FOLDER.'/smartImageCache/images/play.png" height="20" alt="continue">'.CR_LF;
echo '							  	<strong>'.gettext("Continue job").'</strong>'.CR_LF;
echo '							</button>'.CR_LF;
								if ($auto) {
echo '								<button class="tooltip" type="button" id="pause_button2" title="'.gettext('Pause').'" onclick="stopper();">'.CR_LF;
echo '									<img src="'.FULLWEBPATH.'/'.USER_PLUGIN_FOLDER.'/smartImageCache/images/pause.png" height="20" alt="pause">'.CR_LF;
echo '								  	<strong>'.gettext("Pause job").'</strong>'.CR_LF;
echo '								</button>'.CR_LF;
								}
echo '							<button class="tooltip" type="button" title="'.gettext('Reload').'">'.CR_LF;
echo '								 <img src="'.FULLWEBPATH.'/'.USER_PLUGIN_FOLDER.'/smartImageCache/images/reload.png" height="20" alt="reload" onclick="history.go(0);"/>'.CR_LF;
echo '							  	<strong>'.gettext("Reload chunk").'</strong>'.CR_LF;
echo '							</button>'.CR_LF;
echo '						</p>';
						} else {
							if ($process) {smartImageCache::printButtons($returnpage,$album_path,false);}
						}

						if ($button) {
echo '						<p class="buttons clearfix">'.CR_LF;
echo '							<button class="tooltip" type="submit" title="'.$button['title'].'">'.CR_LF;
echo '								<img src="'.WEBPATH.'/'.ZENFOLDER.'/images/pass.png" alt=""/>'.CR_LF;
echo '								'.$button['text'].CR_LF;
echo '							</button>'.CR_LF;
echo '						</p>'.CR_LF;
						}
echo '				</form>'.CR_LF;

echo '			</div>'.CR_LF;
echo '		</div>'.CR_LF;
echo '	</div>'.CR_LF;
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
/* Footer																																				*/
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
printAdminFooter();
echo '</body>'.CR_LF;
echo '</html>'.CR_LF;
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
?>

