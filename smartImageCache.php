<?php
/**
 *
 * This plugin is intended to integrate cacheManager plugin for Zenphoto, with a smart function that introduces
 * a paginated version of cache creation for images.
 * You should have cacheManager extention to be activated to work.
 *
 * It provides:
 * <ul>
 * 		<li>New <i>Smart Cache</i> buttons within:
 * 			<ul>
 * 				<li>Admin dashboard, cache section. </li>
 * 				<li>Album edit pages</li>
 * 			</ul>
 * 		</li>
 * </ul>
 *
 * Functioning methods are completely similar to cacheManager extension's ones, apart from this improvements:
 * <ul>
 * 		<li>Segment the process of generating cache sizes in paged chunks with defined number of items every chunk.</li>
 *		<li>Enable an auto-advance process to easily provide batch operations</li>
 *		<li>Plugin options which can be set up:
 *			<ul>
 * 				<li>number of items per chunk,</li>
 *				<li>enable/disable auto-advance and its waiting time,</li>
 *				<li>set-timeout parameter for every caching process</li>
 *			</ul></li>
 *		<li>auto-advance process can be paused and restarted to easily verify things; a chunk can be reloaded as well</li>
 *		<li>auto-advance stops by itself if some cache size are recognized as failed</li>
 *		<li>number of items per chunk, as well as auto-advance method and timing, can be defined as plugin option, but also on the fly, directly in the process launch</li>
 *		<li>all the images (already cached and new ones) are thumbed with alegend icon beside, for a better control of the whole process</li>
 *		<li>thumbs showed are a bit bigger then in cacheManager experience (50px high) to give users the ability to better see the single cache size results</li>
 *		<li>passing mouse pointer on not loaded images, attempts reloading of cache size</li>
 *		<li>it has been tested and works both with GDlibrary and Imagick libraries,</li>
 *		<li>it has been tested and works both with Classic and cURL method</li>
  * </ul>
 *
 * @package plugins
 * @subpackage smartImageCache
 * @author Filippo Boscarino
 */
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
/* Definition																																			*/
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
$plugin_is_filter=5|ADMIN_PLUGIN;
$plugin_description=gettext("Smart Creation of cached images. It can be useful to avoid server surcharge if you work with many photos and/or very heavy HD files. cacheManager extension should be present and enabled to work.");
$plugin_version='1.0.1';
$plugin_author="Filippo Boscarino";
$plugin_category=gettext('Admin');

$option_interface='smartImageCache';

/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
/* Controls																																				*/
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
$plugin_disable=((!extensionEnabled('cacheManager'))?gettext('cacheManager plugin is required'):false);
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
/* Includes																																				*/
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
require_once(SERVERPATH.'/'.ZENFOLDER.'/classes/class-feed.php');
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
/* Filters																																				*/
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
zp_register_filter('admin_utilities_buttons','smartImageCache::overviewButton');
zp_register_filter('edit_album_utilities','smartImageCache::albumButton',-9998);
zp_register_filter('show_change','smartImageCache::published');

/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
/* Class																																				*/
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
class smartImageCache {

	public static $auto=NULL;
	public static $albums_tree=array();
	public static $albums_total=0;
	public static $images_total=0;
	public static $albums_cached=0;
	public static $images_cached=0;
	public static $images_counter=0;
	public static $starttime=0;
	public static $imagesizes_sizes=0;
	public static $imagesizes_total=0;
	public static $imagesizes_worked=0;
	public static $imagesizes_cached=0;
	public static $imagesizes_ok=0;
	public static $imagesizes_ko=0;
	public static $imagesizes_maybe=0;
	public static $imagesizes_media=0;

	public static $missingimages=NULL;

	function __construct() {
		setOptionDefault('smartImageCache_howmany',20);
		setOptionDefault('smartImageCache_howlast',3);
		setOptionDefault('smartImageCache_autoadvance',true);
		setOptionDefault('smartImageCache_autopause',10);
		setOptionDefault('smartImageCache_showcached',false);
	}
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
/* Function:	getOptionsSupported()																													*/
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
	function getOptionsSupported() {
		$options[gettext('How many items per cycle')]=array(
				'key'=>'smartImageCache_howmany',
				'type'=>OPTION_TYPE_CLEARTEXT,
				'order'=>1,
				'desc'=>gettext('How many items (images or media) have to be processed every cicle.<br>This number has to be multiplied for how many cache sizes per item should be processed.').
						'<div class="notebox">'.gettext('<strong>NOTE:</strong> Define the right number based on your server capacity and charge.<br><b>ATTENTION!!!</b> All the others caching parameters are inherited from the cacheManager\'s ones.<br><em>Value 0 (zero) means all images togheter in one chunk.</em>').'</div>'
		);
		$options[gettext('How long does process lasts')]=array(
				'key'=>'smartImageCache_howlast',
				'type'=>OPTION_TYPE_CLEARTEXT,
				'order'=>2,
				'desc'=>gettext('Process max execution time.').
						'<div class="notebox">'.gettext('<strong>NOTE:</strong> Defining too long periods can create problems to server availability.<br>Do not set periods higherer than 10 secs.').'</div>'
		);
		$options[gettext('Enable process auto advance')]=array(
				'key'=>'smartImageCache_autoadvance',
				'type'=>OPTION_TYPE_CHECKBOX,
				'order'=>3,
				'desc'=>gettext('Check to enable auto advance process for sizing cache production.').
						'<div class="notebox">'.gettext('<strong>NOTE:</strong> Process can easily be stopped anyway, via dedicated buttons showed on top and bottom of process display page.').'</div>'

		);
		$options[gettext('Pause before auto advance')]=array(
				'key'=>'smartImageCache_autopause',
				'type'=>OPTION_TYPE_CLEARTEXT,
				'order'=>4,
				'desc'=>gettext('How many seconds process has to wait before auto advance.').
						'<div class="notebox">'.gettext('<strong>NOTE:</strong> If not stopped within this period of secs, process proceed on next page.<br>If some sized image production goes wrong, process try to stop by itself.').'</div>'
		);
		$options[gettext('Show already cached thumbs')]=array(
				'key'=>'smartImageCache_showcached',
				'type'=>OPTION_TYPE_CHECKBOX,
				'order'=>5,
				'desc'=>gettext('Check to show already cached thumbs for verification purposes.<br>(Activate only if needed, or you overcharge client and server without an effective benefit.)')

		);
		return $options;
	}
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
/* Function:	getOptionsDisabled()																													*/
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
	function getOptionsDisabled() {
		return array();
	}
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
/* Function:	handleOption()																															*/
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
	function handleOption($option,$currentValue) {
	}
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
/* Function:	handleOptionSave()																														*/
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
	function handleOptionSave($themename,$themealbum) {
	}
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
/* Function:	published()																																*/
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
	static function published($obj) {
		global $_zp_html_cache, $_zp_cached_feeds;
		if (getOption('smartImageCache_' . $obj->table)) {
			$_zp_html_cache->clearHTMLCache();
			foreach ($_zp_cached_feeds as $feed) {
				$feeder = new smartImageCacheFeed($feed);
				$feeder->clearCache();
			}
		}
		return $obj;
	}
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
/* Function:	createAlbumsTree()																													*/
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
	static function createAlbumsTree($album_actual) {
		self::$albums_tree[$album_actual->getName()]=$album_actual->getNumImages();
		self::$albums_total++;

		self::$images_total+=self::$albums_tree[$album_actual->getName()];

		foreach ($album_actual->getAlbums() as $folder) {
			$subalbum=AlbumBase::newAlbum($folder);
			if (!$subalbum->isDynamic()) {
				self::createAlbumsTree($subalbum);
			}
		}
	}
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
/* Function:	workAlbumsTree()																														*/
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
	static function workAlbumsTree($chunk,$quantity,$method,$show=false) {
		global $_zp_gallery;
		$theme=$_zp_gallery->getCurrentTheme();
		$limit_achieved=false;

		foreach (self::$albums_tree as $album_key=>$album_value) {

			if ($limit_achieved) break;       // no sense to go on if already achieved the limit;

			$album_actual=AlbumBase::newAlbum($album_key);
			$parent=$album_actual->getUrAlbum();
			$albumtheme=$parent->getAlbumTheme();
			if (empty($albumtheme)) {
				$id=0;
			} else {
				$theme=$albumtheme;
				$id=$parent->getID();
			}
			loadLocalOptions($id,$theme);
			self::$albums_cached++;
			echo '. <strong>'.gettext('Album').' '.self::$albums_cached.' - '.html_encode($album_actual->getTitle()).'</strong> ('.html_encode($album_actual->getName()).') <b>'.$album_actual->getNumImages().' '.gettext('Items').'</b><br><hr>'.CR_LF;
			echo '<script>'.CR_LF;
			echo '	$(\'.imagecaching_albumcount\').text('.self::$albums_cached.');'.CR_LF;
			echo '</script>'.CR_LF;

			if ((self::$images_counter+$album_value)<(($chunk-1)*$quantity)) {
				$album_value=array();
				self::$images_counter=self::$images_counter+$album_value;
				self::$images_total=self::$images_total+$album_value;
			} else {
				$album_value=array();
				foreach ($album_actual->getImages(0) as $image) {
					$album_value[$image]=0;
					self::$images_total++;
				}
			}

			if (count($album_value)){
				foreach ($album_value as $image_key=>$image_value) {
					self::$images_counter++;
					if ((self::$images_counter>(($chunk-1)*$quantity))&&(self::$images_counter<=($chunk*$quantity))) {
						self::workItems($album_actual,$image_key,$method,$show);
					} else {
						if (self::$images_counter>($chunk*$quantity)) {
							$limit_achieved=true;
							break;
						} else {
							self::$imagesizes_worked=self::$images_counter*self::$imagesizes_sizes;
						}
					}
					self::$images_cached++;
					echo '<script>'.CR_LF;
					echo '	$(\'.imagecaching_imagecount\').text('.self::$images_cached.');'.CR_LF;
					echo '</script>'.CR_LF;
				}
			} else {
				echo TAB.'<p class="notebox"><em>'.gettext('This album does not have any images.').'</em></p>'.CR_LF;
			}
			echo '<br><br>'.CR_LF;
		}
		return $limit_achieved;
	}
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
/* Function:	workItems()																															*/
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
	static function workItems($album_actual,$image,$method,$show=false) {
		$sizes_count=0;
		$sizeuris=array();
		$doneuris=array();
		$results=array();
		$image_actual=Image::newImage($album_actual,$image);

		if ($image_actual->isPhoto()) {
			if (array_key_exists('*',cacheManager::$enabledsizes)) {
				$uri=getFullImageURL($image_actual);
				if (strpos($uri,'full-image.php?')!==false) {
					$sizes_count++;
					$sizeuris[]=$uri;
				}
			}
			if (array_key_exists('defaultthumb',cacheManager::$enabledsizes)) {
				$thumb=$image_actual->getThumb();
				if (strpos($thumb,'i.php?')!== false) {
					$sizes_count++;
					$sizeuris[]=$thumb;
				}
			}
			if (array_key_exists('defaultsizedimage',cacheManager::$enabledsizes)) {
				$defaultimage=$image_actual->getSizedImage(getOption('image_size'));
				if (strpos($defaultimage,'i.php?')!==false) {
					$sizes_count++;
					$sizeuris[]=$defaultimage;
				}
			}
			foreach (cacheManager::$sizes as $key=>$cacheimage) {
				if (array_key_exists($key,cacheManager::$enabledsizes)) {
					$size=(isset($cacheimage['image_size'])?$cacheimage['image_size']:NULL);
					$width=(isset($cacheimage['image_width'])?$cacheimage['image_width']:NULL);
					$height=(isset($cacheimage['image_height'])?$cacheimage['image_height']:NULL);
					$thumbstandin=(isset($cacheimage['thumb'])?$cacheimage['thumb']:NULL);

					if ($special=($thumbstandin===true)) {
						list($special,$cw,$ch,$cx,$cy)=$image_actual->getThumbCropping($size,$width,$height);
					}
					if (!$special) {
						$cw=(isset($cacheimage['crop_width'])?$cacheimage['crop_width']:NULL);
						$ch=(isset($cacheimage['crop_height'])?$cacheimage['crop_height']:NULL);
						$cx=(isset($cacheimage['crop_x'])?$cacheimage['crop_x']:NULL);
						$cy=(isset($cacheimage['crop_y'])?$cacheimage['crop_y']:NULL);
					}
					$effects=(isset($cacheimage['gray'])?$cacheimage['gray']:NULL);
					if (isset($cacheimage['wmk'])) {
						$passedWM=$cacheimage['wmk'];
					} else {
						if ($thumbstandin) {
							$passedWM=getWatermarkParam($image_actual,WATERMARK_THUMB);
						} else {
							$passedWM=getWatermarkParam($image_actual,WATERMARK_IMAGE);
						}
					}
					if (isset($cacheimage['maxspace'])) {
						getMaxSpaceContainer($width,$height,$image_actual,$thumbstandin);
					}
					$args=array($size,$width,$height,$cw,$ch,$cx,$cy,NULL,$thumbstandin,NULL,$thumbstandin,$passedWM,NULL,$effects);
					$args=getImageParameters($args,$album_actual->getName());
					if ($album_actual->isDynamic()) {
						$folder=$image_actual->album->getName();
					} else {
						$folder=$album_actual->getName();
					}
					$uri=getImageURI($args,$folder,$image_actual->filename,$image_actual->filemtime);
					if (strpos($uri,'i.php?')!==false) {
						$sizes_count++;
						$sizeuris[]=$uri;
					} else {
						$doneuris[]=$uri;
					}
				}
			}
			$imagetitle=html_encode($image_actual->getTitle()).' ('.html_encode($image_actual->filename).'): ';
			echo TAB.'<span>'.gettext('Item').' '.self::$images_counter.'. <b>'.$imagetitle.'</b></span><span>'.TAB.CR_LF;

			if ($sizes_count==0) {
				foreach ($doneuris as $doneuri) {
					self::$imagesizes_worked++;
					self::$imagesizes_cached++;
					echo '<span>'.CR_LF;
					echo '	<a href="'.html_encode(pathurlencode($doneuri)).'&amp;debug" target="_blank">'.CR_LF;
					echo '		<img class="icon-position-top4" src="'.FULLWEBPATH.'/'.USER_PLUGIN_FOLDER.'/smartImageCache/images/cache.png" height="20" alt="cached" title="'.html_encode($doneuri).'">'.CR_LF;
					if ($show) echo '		<img src="'.html_encode(pathurlencode($doneuri)).'" height="50" id="picID'.self::$imagesizes_worked.'" alt="cached" target="_blank" onmouseover="javascript:document.getElementById(\'picID'.self::$imagesizes_worked.'\').src=\''.html_encode(pathurlencode($doneuri)).'\';">'.CR_LF;
					echo '	</a>'.CR_LF;
					echo '</span>'.CR_LF;
					echo '<script>'.CR_LF;
					echo '	$(\'.imagecaching_sizecount\').text(\''.self::$imagesizes_worked.'\');'.CR_LF;
					echo '	$(\'.imagecaching_imagesizes_cached\').text(\''.self::$imagesizes_cached.'\');'.CR_LF;
					echo '</script>'.CR_LF;
				}
				echo '<em style="color:green;"> '.gettext('All already cached.').'</em>'.CR_LF;
			} else {
				foreach ($doneuris as $doneuri) {
					self::$imagesizes_worked++;
					self::$imagesizes_cached++;
					echo '<span>'.CR_LF;
					echo '	<a href="'.html_encode(pathurlencode($doneuri)).'&amp;debug" target="_blank">'.CR_LF;
					echo '		<img class="icon-position-top4" src="'.FULLWEBPATH.'/'.USER_PLUGIN_FOLDER.'/smartImageCache/images/cache.png" height="20" alt="cached" title="'.html_encode($doneuri).'">'.CR_LF;
					if ($show) echo '		<img src="'.html_encode(pathurlencode($doneuri)).'" height="50" id="picID'.self::$imagesizes_worked.'" alt="cached" target="_blank" onmouseover="javascript:document.getElementById(\'picID'.self::$imagesizes_worked.'\').src=\''.html_encode(pathurlencode($doneuri)).'\';">'.CR_LF;
					echo '	</a>'.CR_LF;
					echo '</span>'.CR_LF;
					echo '<script>'.CR_LF;
					echo '	$(\'.imagecaching_sizecount\').text(\''.self::$imagesizes_worked.'\');'.CR_LF;
					echo '	$(\'.imagecaching_imagesizes_cached\').text(\''.self::$imagesizes_cached.'\');'.CR_LF;
					echo '</script>'.CR_LF;
				}
				foreach ($sizeuris as $sizeuri) {
					self::generateImage($sizeuri,$method);
					echo '<script>'.CR_LF;
					echo '	$(\'.imagecaching_sizecount\').text(\''.self::$imagesizes_worked.'\');'.CR_LF;
					echo '	$(\'.imagecaching_imagesizes_ok\').text(\''.self::$imagesizes_ok.'\');'.CR_LF;
					echo '	$(\'.imagecaching_imagesizes_ko\').text(\''.self::$imagesizes_ko.'\');'.CR_LF;
					echo '	$(\'.imagecaching_imagesizes_maybe\').text(\''.self::$imagesizes_maybe.'\');'.CR_LF;
					echo '</script>'.CR_LF;
				}
			}
		} else {
			$imagetitle=html_encode($image_actual->getTitle()).' ('.html_encode($image_actual->filename).'): ';
			self::$imagesizes_worked=self::$imagesizes_worked+self::$imagesizes_sizes;
			self::$imagesizes_media=self::$imagesizes_media+self::$imagesizes_sizes;
			echo TAB.'<span>'.gettext('Item').' '.self::$images_counter.'. <b>'.$imagetitle.'</b></span>'.CR_LF;
			echo '<span>'.CR_LF;
			echo '	<em style="color:green;">'.gettext('Multimedia object. No cache needed').'</em>'.CR_LF;
			echo '</span><br>'.CR_LF;
			echo '<script>';
			echo '	$(\'.imagecaching_sizecount\').text(\''.self::$imagesizes_worked.'\');'.CR_LF;
			echo '	$(\'.imagecaching_imagesizes_media\').text(\''.self::$imagesizes_media.'\');'.CR_LF;
			echo '</script>'.CR_LF;
		}
		echo '<script>';
		echo '	$(\'.imagecaching_imagecount\').text(\''.self::$images_cached.'\');'.CR_LF;
		echo '	$(\'.imagecaching_sizecount\').text(\''.self::$imagesizes_worked.'\');'.CR_LF;
		echo '	$(\'.imagecaching_imagesizes_cached\').text(\''.self::$imagesizes_cached.'\');'.CR_LF;
		echo '	$(\'.imagecaching_imagesizes_ok\').text(\''.self::$imagesizes_ok.'\');'.CR_LF;
		echo '	$(\'.imagecaching_imagesizes_ko\').text(\''.self::$imagesizes_ko.'\');'.CR_LF;
		echo '	$(\'.imagecaching_imagesizes_maybe\').text(\''.self::$imagesizes_maybe.'\');'.CR_LF;
		echo '	$(\'.imagecaching_imagesizes_media\').text(\''.self::$imagesizes_media.'\');'.CR_LF;
		echo '	$(\'.imagecaching_time\').text(\''.gmdate('H:i:s',(time()-self::$starttime)).'\');'.CR_LF;
		echo '</script>'.CR_LF;
		echo '</span><br>'.CR_LF;
	}
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
/* Function:	generateImage()																															*/
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
	static function generateImage($imageuri,$method) {
		if ($method) {
			$success=generateImageCacheFile($imageuri);
			if ($success) {
				self::$imagesizes_worked++;
				self::$imagesizes_ok++;
				echo '<span>'.CR_LF;
				echo '	<a href="'.html_encode(pathurlencode($imageuri)).'&amp;debug" target="_blank">'.CR_LF;
				echo '		<img class="icon-position-top4" src="'.FULLWEBPATH.'/'.USER_PLUGIN_FOLDER.'/smartImageCache/images/ok.png" height="20" alt="OK" title="'.html_encode($imageuri).'">'.CR_LF;
			} else {
				self::$imagesizes_worked++;
				self::$imagesizes_ko++;
				echo '<span>'.CR_LF;
				echo '	<a href="'.html_encode(pathurlencode($imageuri)).'&amp;debug" target="_blank">'.CR_LF;
				echo '		<img src="'.FULLWEBPATH.'/'.USER_PLUGIN_FOLDER.'/smartImageCache/images/ko_red.png" height="20" alt="KO">'.CR_LF;
			}
			echo '		<img src="'.html_encode(pathurlencode($imageuri)).'" height="50" id="picID'.self::$imagesizes_worked.'" alt="reload" onmouseover="javascript:document.getElementById(\'picID'.self::$imagesizes_worked.'\').src=\''.html_encode(pathurlencode($imageuri)).'\';">'.CR_LF;
			echo '	</a>'.CR_LF;
			echo '</span>'.CR_LF;
		} else {
			self::$imagesizes_worked++;
			self::$imagesizes_maybe++;
			echo '<span>'.CR_LF;
			echo '	<a href="'.html_encode(pathurlencode($imageuri)).'&amp;debug" target="_blank">'.CR_LF;
			echo '		<img class="icon-position-top4" src="'.FULLWEBPATH.'/'.USER_PLUGIN_FOLDER.'/smartImageCache/images/unknown.png" height="20" alt="unknown" title="'.html_encode($imageuri).'">'.CR_LF;
			echo '		<img src="'.html_encode(pathurlencode($imageuri)).'" height="50" id="picID'.self::$imagesizes_worked.'" alt="tentative" onmouseover="javascript:document.getElementById(\'picID'.self::$imagesizes_worked.'\').src=\''.html_encode(pathurlencode($imageuri)).'\';">'.CR_LF;
			echo '	</a>'.CR_LF;
			echo '</span>'.CR_LF;
		}
	}
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
/* Function:	overviewButton()																														*/
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
	static function overviewButton($buttons) {
		global $_zp_db;
		if ($_zp_db->querySingleRow('SELECT * FROM '.$_zp_db->prefix('plugin_storage').' WHERE `type`="cacheManager" LIMIT 1')) {
			$enable=true;
			$title=gettext('Finds images that have not been cached and creates the cached versions in a Smart way, based on cacheManager parameters.');
		} else {
			$enable=false;
			$title=gettext('You must first set the plugin options for cacheManager parameters.');
		}

		$buttons[]=array(
				'category'=>gettext('Cache'),
				'enable'=>$enable,
				'button_text'=>gettext('Smart Cache manager'),
				'formname'=>'smartImageCache_button',
				'action'=>FULLWEBPATH.'/'.USER_PLUGIN_FOLDER.'/smartImageCache/cacheImages.php?page=overview&tab=images',
				'icon'=>FULLWEBPATH.'/'.USER_PLUGIN_FOLDER.'/smartImageCache/images/cache.png" height="16',
				'alt'=>'',
				'hidden'=>'',
				'rights'=>ADMIN_RIGHTS,
				'title'=>$title
		);
		return $buttons;
	}
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
/* Function:	albumButton()																															*/
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
	static function albumButton($html, $object, $prefix) {
		global $_zp_db;
		$html ='<hr />';
		if ($_zp_db->querySingleRow('SELECT * FROM '.$_zp_db->prefix('plugin_storage').' WHERE `type`="smartImageCache" LIMIT 1')) {
			$disable='';
			$title=gettext('Finds images that have not been cached and creates the cached versions in a Smart way.');
		} else {
			$disable='disabled="disabled"';
			$title=gettext("You must first set the plugin options for smartImageCache parameters.");
		}
		$html.='<div class="button buttons tooltip" title="'.$title.'"><a href="'.FULLWEBPATH.'/'.USER_PLUGIN_FOLDER.'/smartImageCache/cacheImages.php?album='.html_encode($object->name).'&amp;XSRFToken='.getXSRFToken('cacheImages').'"'.$disable.'><img src="'.FULLWEBPATH.'/'.USER_PLUGIN_FOLDER.'/smartImageCache/images/cache.png" height="20">'.gettext('Smart Cache album images').'</a><br class="clearall" /></div>';
		return $html;
	}
}
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
?>