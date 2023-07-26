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
 * 				<li>how long does PHP process can last,</li>
 *				<li>enable/disable cache sizes produced in advance</li>
 *				<li>enable/disable auto-advance and its waiting time,</li>
 *				<li>set-timeout parameter for every caching process</li>
 *			</ul></li>
 *		<li>auto-advance process can be paused and restarted to easily verify things</li>
 *		<li>a chunk can be reloaded</li>
 *		<li>auto-advance stops by itself if some cache size are failed (limitation of ok/ko results are the same of the actual Zenphoto system)</li>
 *		<li>number of items per chunk, as well as auto-advance method, timing and show already cached images, can be defined as plugin option, but also on the fly, directly in the process launch</li>
 *		<li>all the images (already cached and new ones) can be thumbed in page, for a better control of the whole process (if the relative option is activated during the process)</li>
 *		<li>thumbs showed are a quite large (50px high) to give users the rought ability to control the single cache size results</li>
 *		<li>if some image is not loaded correctly by your browser, passing mouse pointer on it, lets attempt reloading of cache size</li>
 *		<li>plugin can produce image cache sizes, both with GDlibrary or Imagick libraries, and both with Classic and cURL method, based on configuration of your server</li>
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
$plugin_version='1.1.1';
$plugin_author='Filippo Boscarino';
$plugin_URL="https://github.com/FilippoBoscarino/smartImageCache-for-Zenphoto";
$plugin_category=gettext('Admin');
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
/* Controls																																				*/
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
$option_interface='smartImageCache';
$plugin_disable=((!extensionEnabled('cacheManager'))?gettext('cacheManager plugin is required'):false);
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
/* Includes																																				*/
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
require_once(SERVERPATH.'/'.ZENFOLDER.'/classes/class-feed.php');
require_once(SERVERPATH.'/'.USER_PLUGIN_FOLDER.'/smartImageCache/function.php');
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
/* Filters																																				*/
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
zp_register_filter('admin_utilities_buttons','smartImageCache::overviewButton');
zp_register_filter('edit_album_utilities','smartImageCache::albumButton',10);
// zp_register_filter('show_change','smartImageCache::published');

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
		setOptionDefault('smartImageCache_showcached',false);
		setOptionDefault('smartImageCache_thumbheight',50);
	}
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
/* Function:	getOptionsSupported()																													*/
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
	function getOptionsSupported() {
		$options[gettext('Number of items per chunk')]=array(
				'key'=>'smartImageCache_howmany',
				'type'=>OPTION_TYPE_CLEARTEXT,
				'order'=>1,
				'desc'=>gettext('How many items (images or media) have to be processed every cicle.<br>This number has to be multiplied for how many cache sizes per item should be processed.').
						'<div class="notebox">'.gettext('<strong>NOTE:</strong> Define the right number based on your server capacity and charge.<br><b>ATTENTION!!!</b> All the others caching parameters are inherited from the cacheManager\'s ones.<br><em>Value 0 (zero) means all images together in one chunk.</em>').'</div>'
		);
		$options[gettext('Enable process auto advance')]=array(
				'key'=>'smartImageCache_autoadvance',
				'type'=>OPTION_TYPE_CHECKBOX,
				'order'=>2,
				'desc'=>gettext('Check to enable auto advance process for sizing cache production.').
						'<div class="notebox">'.gettext('<strong>NOTE:</strong> Process can easily be stopped anyway, via dedicated buttons showed on top and bottom of process display page.').'</div>'
		);
		$options[gettext('Pause before auto advance')]=array(
				'key'=>'smartImageCache_autopause',
				'type'=>OPTION_TYPE_CLEARTEXT,
				'order'=>3,
				'desc'=>gettext('How many seconds process has to wait before auto advance.').
						'<div class="notebox">'.gettext('<strong>NOTE:</strong> If not stopped within this period of seconds, process proceeds on the next page.<br>If some sized image production goes wrong, process tries to stop by itself.').'</div>'
		);
		$options[gettext('Show already cached thumbs')]=array(
				'key'=>'smartImageCache_showcached',
				'type'=>OPTION_TYPE_CHECKBOX,
				'order'=>4,
				'desc'=>gettext('Check to show already cached thumbs for verification purposes..').
						'<div class="notebox">'.gettext('<strong>NOTE:</strong> Activate only if needed, otherwise you can overcharge client and server without a real benefit.').'</div>'
		);
		$options[gettext('Thumbs display height')]=array(
				'key'=>'smartImageCache_thumbheight',
				'type'=>OPTION_TYPE_CLEARTEXT,
				'order'=>5,
				'desc'=>gettext('Define the fixed height in pixels for cached sizes thumb when displayed during generation process (not so far from 50 is suggested).')
		);
		$options[gettext('How long does process last')]=array(
				'key'=>'smartImageCache_howlast',
				'type'=>OPTION_TYPE_CLEARTEXT,
				'order'=>6,
				'desc'=>gettext('Process max execution time.').
						'<div class="notebox">'.gettext('<strong>NOTE:</strong> Defining too long of periods can create problems to server availability.<br>Do not set periods higher than 10 secs.').'</div>'
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
/* Function:	createAlbumsTree()																														*/
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

			if ((self::$images_counter+$album_value)<(($chunk-1)*$quantity)) {
				$display=false;
				self::$images_counter=self::$images_counter+$album_value;
				self::$images_cached=self::$images_cached+$album_value;
				self::$images_total=self::$images_total+$album_value;
				$album_value=array();
			} else {
				$display=true;
				$album_value=array();
				foreach ($album_actual->getImages(0) as $image) {
					$album_value[$image]=0;
					self::$images_total++;
				}
			}

			if ($display) {
				echo '<span class="sICalbum" style="height:'.getOption('smartImageCache_thumbheight').'px;"><img src="'.FULLWEBPATH.'/'.USER_PLUGIN_FOLDER.'/smartImageCache/images/album.png" height="20" alt="album" title="album" class="sICalbumIcon"> <big>'.gettext('Album').' '.self::$albums_cached.' - <a href="'.$album_actual->getLink().'" alt="'.gettext('Item').'" title="'.gettext('Item').'" target="_blank"><b>'.html_encode($album_actual->getTitle()).'</b> ('.html_encode($album_actual->getName()).'</a>) <b>'.$album_actual->getNumImages().' '.gettext('Items').'</b></big></span>'.CR_LF;
				echo '<script>'.CR_LF;
				echo '	$(\'.imagecaching_albumcount\').text('.self::$albums_cached.');'.CR_LF;
				echo '</script>'.CR_LF;
			}

			if (count($album_value)){
				foreach ($album_value as $image_key=>$image_value) {
					self::$images_counter++;
					if ((self::$images_counter>(($chunk-1)*$quantity))&&(self::$images_counter<=($chunk*$quantity))) {
						self::workItems($album_actual,$image_key,$method,$show);
					} else {
						if (self::$images_counter>($chunk*$quantity)) {
							$limit_achieved=true;
							break 2;
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
				if ($display) {
					if ($album_actual->getNumImages()) {
						echo '<span class="sICalbumMessage"><em> '.gettext('All already cached.').'</em></span>'.CR_LF;
					} else {
						echo '<span class="sICalbumMessage">'.gettext('This album does not have any images.').'</span>'.CR_LF;
					}
				}
			}
			if ($display) {echo '<br><br>'.CR_LF;}
		}
		return $limit_achieved;
	}
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
/* Function:	workItems()																																*/
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
	static function workItems($album_actual,$image,$method,$show=false) {
		$sizes_count=0;
		$sizeuris=array();
		$doneuris=array();
		$results=array();
		$seed=rand(1000,100000);
		$sidecar=false;
		$image_actual=Image::newImage($album_actual,$image);

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
		if (!$image_actual->isPhoto()) {
			$sidecar=$image_actual->objectsThumb;
			$mediatitle='<b>'.html_encode($image_actual->getTitle()).'</b> ('.html_encode($image_actual->filename).')';
			$medialink=$image_actual->getLink();
			if ($image_actual->objectsThumb) {
				$image_actual=Image::newImage($album_actual,$image_actual->objectsThumb);
			}
		}
		foreach (cacheManager::$sizes as $key=>$cacheimage) {
			if (array_key_exists($key,cacheManager::$enabledsizes)) {

				$size=(isset($cacheimage['image_size'])?$cacheimage['image_size']:NULL);
				$width=(isset($cacheimage['image_width'])?$cacheimage['image_width']:NULL);
				$height=(isset($cacheimage['image_height'])?$cacheimage['image_height']:NULL);
				$thumbstandin=(isset($cacheimage['thumb'])?($cacheimage['thumb']?$cacheimage['thumb']:0):NULL);

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
					$width=(int) $width;
					$height=(int) $height;
					$thumbstandin=(int) $thumbstandin;
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
				$cacheuri=getCacheImageURI($args,$folder,$image_actual->filename);
				if (strpos($uri,'i.php?')!==false) {
					$sizes_count++;
					$sizeuris[$uri]=$cacheuri;
				} else {
					$doneuris[$uri]=$cacheuri;
				}
			}
		}
		echo'<span class="sICitemLine">'.CR_LF;
		echo'	<span class="sICitemName">'.CR_LF;
		if (!$sidecar) {
			if ($image_actual->isPhoto()) {
				echo'		<img src="'.FULLWEBPATH.'/'.USER_PLUGIN_FOLDER.'/smartImageCache/images/image.png" height="20" alt="image" title="image" class="sICitemIcon">'.gettext('Item').' '.self::$images_counter.'. <a href="'.$image_actual->getLink().'" alt="'.gettext('Item').'" title="'.gettext('Item').'" target="_blank"><b>'.html_encode($image_actual->getTitle()).'</b> ('.html_encode($image_actual->filename).')</a>'.CR_LF;
			} else {
				echo'		<img src="'.FULLWEBPATH.'/'.USER_PLUGIN_FOLDER.'/smartImageCache/images/media.png" height="20" alt="media" title="media" class="sICitemIcon">'.gettext('Item').' '.self::$images_counter.'. <a href="'.$medialink.'" alt="'.gettext('Item').'" title="'.gettext('Item').'" target="_blank">'.$mediatitle.'</a>'.CR_LF;
				echo'		<br>'.gettext('Sidecar image').': -'.CR_LF;
			}
		} else {
			echo'		<img src="'.FULLWEBPATH.'/'.USER_PLUGIN_FOLDER.'/smartImageCache/images/media.png" height="20" alt="media" title="media" class="sICitemIcon">'.gettext('Item').' '.self::$images_counter.'. <a href="'.$medialink.'" alt="'.gettext('Item').'" title="'.gettext('Item').'" target="_blank">'.$mediatitle.'</a>'.CR_LF;
			echo'		<br>'.gettext('Sidecar image').': '.$sidecar.CR_LF;
		}
		echo'	</span>'.CR_LF;

		if ($image_actual->isPhoto()||$sidecar) {
			echo'	<span class="sICitemSizes">'.CR_LF;
			if ($sizes_count==0) {
				foreach ($doneuris as $doneuri=>$cacheuri) {
					self::$imagesizes_worked++;
					self::$imagesizes_cached++;
					echo '		<span id="spanID'.self::$imagesizes_worked.'" class="sICitemSize" style="height:'.getOption('smartImageCache_thumbheight').'px;">'.CR_LF;
					if ($show) {
						echo '			<a href="'.$doneuri.'&amp;debug" target="_blank" class="sICitemSizeImage" onmouseover="javascript:document.getElementById(\'picID'.self::$imagesizes_worked.'\').src=\''.$doneuri.'?mtime='.microtime(true).'\';">'.CR_LF;
						echo '				<img src="'.$doneuri.'?mtime='.microtime(true).'" height="'.getOption('smartImageCache_thumbheight').'" id="picID'.self::$imagesizes_worked.'" alt="cached">'.CR_LF;
						echo '			</a>'.CR_LF;
					}
					echo '			<a href="'.$doneuri.'&amp;debug" target="_blank">'.CR_LF;
					echo '				<img src="'.FULLWEBPATH.'/'.USER_PLUGIN_FOLDER.'/smartImageCache/images/cache.png" height="20" alt="cached" title="'.$doneuri.'">'.CR_LF;
					echo '			</a>'.CR_LF;
					if ($show) {
						echo '			<br><a href="javascript:void(0)" onclick="javascript:retry(\''.self::$images_counter.'\',\''.self::$imagesizes_worked.'\',\''.$seed.'\',\''.($show?'1':'0').'\',\''.getOption('smartImageCache_thumbheight').'\',\''.$doneuri.'\',\''.SERVERCACHE.$cacheuri.'\',\''.getToken('picID'.self::$imagesizes_worked.'-'.$seed).'\');">'.CR_LF;
						echo '				<img src="'.FULLWEBPATH.'/'.USER_PLUGIN_FOLDER.'/smartImageCache/images/trash.png" height="20" alt="trash" title="Discard">'.CR_LF;
						echo '			</a>'.CR_LF;
					}
					echo '		</span>'.CR_LF;
				}
				echo '		<span id="messageID'.self::$images_counter.'" class="sICitemMessage"><em> '.gettext('All already cached.').'</em></span>'.CR_LF;
			} else {
				$counter=0;
				foreach ($doneuris as $doneuri=>$cacheuri) {
					self::$imagesizes_worked++;
					self::$imagesizes_cached++;
					echo '		<span id="spanID'.self::$imagesizes_worked.'" class="sICitemSize" style="height:'.getOption('smartImageCache_thumbheight').'px;">'.CR_LF;
					if ($show) {
						echo '			<a href="'.$doneuri.'&amp;debug" target="_blank" class="sICitemSizeImage" onmouseover="javascript:document.getElementById(\'picID'.self::$imagesizes_worked.'\').src=\''.$doneuri.'?mtime='.microtime(true).'\';">'.CR_LF;
						echo '				<img src="'.$doneuri.'?mtime='.microtime(true).'" height="'.getOption('smartImageCache_thumbheight').'" id="picID'.self::$imagesizes_worked.'" alt="cached">'.CR_LF;
						echo '			</a>'.CR_LF;
					}
					echo '			<a href="'.$doneuri.'&amp;debug" target="_blank">'.CR_LF;
					echo '				<img src="'.FULLWEBPATH.'/'.USER_PLUGIN_FOLDER.'/smartImageCache/images/cache.png" height="20" alt="cached" title="'.$doneuri.'">'.CR_LF;
					echo '			</a>'.CR_LF;
					if ($show) {
						echo '			<br><a href="javascript:void(0)" onclick="javascript:retry(\''.self::$images_counter.'\',\''.self::$imagesizes_worked.'\',\''.$seed.'\',\''.($show?'1':'0').'\',\''.getOption('smartImageCache_thumbheight').'\',\''.$doneuri.'\',\''.SERVERCACHE.$cacheuri.'\',\''.getToken('picID'.self::$imagesizes_worked.'-'.$seed).'\');">'.CR_LF;
						echo '				<img src="'.FULLWEBPATH.'/'.USER_PLUGIN_FOLDER.'/smartImageCache/images/trash.png" height="20" alt="trash" title="Discard">'.CR_LF;
						echo '			</a>'.CR_LF;
					}
					echo '		</span>'.CR_LF;
				}
				foreach ($sizeuris as $sizeuri=>$cacheuri) {
					echo self::generateImage($sizeuri,$cacheuri,$method,$seed,$show);
				}
				echo '		<span id="messageID'.self::$images_counter.'" class="sICitemMessage"></span>'.CR_LF;
			}
			echo '	</span>'.CR_LF;
		} else {
			self::$imagesizes_worked=self::$imagesizes_worked+self::$imagesizes_sizes;
			self::$imagesizes_media=self::$imagesizes_media+self::$imagesizes_sizes;
			echo '		<span id="spanID'.self::$imagesizes_worked.'" class="sICitemSize" style="height:'.getOption('smartImageCache_thumbheight').'px;">'.CR_LF;
			echo '		</span>'.CR_LF;
			echo '		<span id="messageID'.self::$images_counter.'" class="sICitemMessage"><em>'.gettext('Multimedia object without Sidecars. No cache produced').'</em></span>'.CR_LF;
		}

		echo '</span>'.CR_LF;

		echo '<script>'.CR_LF;
		echo '	$(\'.imagecaching_imagecount\').text(\''.self::$images_cached.'\');'.CR_LF;
		echo '	$(\'.imagecaching_sizecount\').text(\''.self::$imagesizes_worked.'\');'.CR_LF;
		echo '	$(\'.imagecaching_imagesizes_cached\').text(\''.self::$imagesizes_cached.'\');'.CR_LF;
		echo '	$(\'.imagecaching_imagesizes_ok\').text(\''.self::$imagesizes_ok.'\');'.CR_LF;
		echo '	$(\'.imagecaching_imagesizes_ko\').text(\''.self::$imagesizes_ko.'\');'.CR_LF;
		echo '	$(\'.imagecaching_imagesizes_maybe\').text(\''.self::$imagesizes_maybe.'\');'.CR_LF;
		echo '	$(\'.imagecaching_imagesizes_media\').text(\''.self::$imagesizes_media.'\');'.CR_LF;
		echo '	$(\'.imagecaching_time\').text(\''.gmdate('H:i:s',(time()-self::$starttime)).'\');'.CR_LF;
		echo '</script>'.CR_LF;

		echo '<script>'.CR_LF;
		echo '	function retry(row,id,seed,show,height,image,cache,token) {'.CR_LF;
		echo '		document.getElementById("picID"+id).src="'.FULLWEBPATH.'/'.USER_PLUGIN_FOLDER.'/smartImageCache/images/unknown.png";'.CR_LF;
		echo '		document.getElementById("picID"+id).onmouseover="";'.CR_LF;
		echo '		var procedure=window.open("'.FULLWEBPATH.'/'.USER_PLUGIN_FOLDER.'/smartImageCache/fileHandler.php?id="+id+"&key="+seed+"&cache="+cache+"&enable="+token,"picID"+id,"titlebar=no,toolbar=no,menubar=no,location=no,scrollbars=no,status=no,resizable=no,top=500,left=500,width=500,height=200");'.CR_LF;
		echo '		document.getElementById("picID"+id).src="'.FULLWEBPATH.'/'.ZENFOLDER.'/images_errors/err-imagenotfound.png";'.CR_LF;
		echo '		document.getElementById("messageID"+row).innerHTML="Reload chunk to regenerate deleted cache sizes.";'.CR_LF;
//		echo '		document.getElementById("picID"+id).src=image+"&mtime='.microtime(true).'";'.CR_LF;
		echo '	}'.CR_LF;
		echo '</script>'.CR_LF;
	}
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
/* Function:	generateImage()																															*/
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
	static function generateImage($imageuri,$cacheuri,$method,$seed,$show) {
		$html ='';
		if ($method) {
			$success=generateImageCacheFile($imageuri);
			if ($success) {
				self::$imagesizes_worked++;
				self::$imagesizes_ok++;
				$html.='<span id="spanID'.self::$imagesizes_worked.'" style="display:inline-block;margin-right:10px;height:'.getOption('smartImageCache_thumbheight').'px;">'.CR_LF;
				$html.='	<a href="'.$imageuri.'&mtime='.microtime(true).'&amp;debug" target="_blank" onmouseover="javascript:document.getElementById(\'picID'.self::$imagesizes_worked.'\').src=\''.$imageuri.'&mtime='.microtime(true).'\';">'.CR_LF;
				$html.='		<img src="'.$imageuri.'&mtime='.microtime(true).'" height="'.getOption('smartImageCache_thumbheight').'" id="picID'.self::$imagesizes_worked.'" alt="reload" style="float:left;">'.CR_LF;
				$html.='	</a>'.CR_LF;
				$html.='	<a href="'.$imageuri.'&mtime='.microtime(true).'&amp;debug" target="_blank">'.CR_LF;
				$html.='		<img src="'.FULLWEBPATH.'/'.USER_PLUGIN_FOLDER.'/smartImageCache/images/ok.png" height="20" alt="OK" title="'.$imageuri.'">'.CR_LF;
				$html.='	</a><br>'.CR_LF;
				$html.='	<a href="javascript:void(0)" onclick="javascript:retry(\''.self::$images_counter.'\',\''.self::$imagesizes_worked.'\',\''.$seed.'\',\''.($show?'1':'0').'\',\''.getOption('smartImageCache_thumbheight').'\',\''.$imageuri.'\',\''.SERVERCACHE.$cacheuri.'\',\''.getToken('picID'.self::$imagesizes_worked.'-'.$seed).'\');">'.CR_LF;
				$html.='		<img src="'.FULLWEBPATH.'/'.USER_PLUGIN_FOLDER.'/smartImageCache/images/trash.png" height="20" alt="trash" title="Discard">'.CR_LF;
				$html.='	</a>'.CR_LF;
				$html.='</span>'.CR_LF;
			} else {
				self::$imagesizes_worked++;
				self::$imagesizes_ko++;
				$html.='<span id="spanID'.self::$imagesizes_worked.'" style="display:inline-block;margin-right:10px;;height:'.getOption('smartImageCache_thumbheight').'px;">'.CR_LF;
				$html.='	<a href="'.$imageuri.'&mtime='.microtime(true).'&amp;debug" target="_blank" onmouseover="javascript:document.getElementById(\'picID'.self::$imagesizes_worked.'\').src=\''.$imageuri.'&mtime='.microtime(true).'\';">'.CR_LF;
				$html.='		<img src="'.$imageuri.'&mtime='.microtime(true).'" height="'.getOption('smartImageCache_thumbheight').'" id="picID'.self::$imagesizes_worked.'" alt="reload" style="float:left;">'.CR_LF;
				$html.='	</a>'.CR_LF;
				$html.='	<a href="'.$imageuri.'&mtime='.microtime(true).'&amp;debug" target="_blank">'.CR_LF;
				$html.='		<img src="'.FULLWEBPATH.'/'.USER_PLUGIN_FOLDER.'/smartImageCache/images/ko_red.png" height="20" alt="KO" title="'.$imageuri.'">'.CR_LF;
				$html.='	</a><br>'.CR_LF;
				$html.='	<a href="javascript:void(0)" onclick="javascript:retry(\''.self::$images_counter.'\',\''.self::$imagesizes_worked.'\',\''.$seed.'\',\''.($show?'1':'0').'\',\''.getOption('smartImageCache_thumbheight').'\',\''.$imageuri.'\',\''.SERVERCACHE.$cacheuri.'\',\''.getToken('picID'.self::$imagesizes_worked.'-'.$seed).'\');">'.CR_LF;
				$html.='		<img src="'.FULLWEBPATH.'/'.USER_PLUGIN_FOLDER.'/smartImageCache/images/trash.png" height="20" alt="trash" title="Discard">'.CR_LF;
				$html.='	</a>'.CR_LF;
				$html.='</span>'.CR_LF;
			}
		} else {
			self::$imagesizes_worked++;
			self::$imagesizes_maybe++;
			$html.='<span id="spanID'.self::$imagesizes_worked.'" style="display:inline-block;margin-right:10px;;height:'.getOption('smartImageCache_thumbheight').'px;">'.CR_LF;
			$html.='	<a href="'.$imageuri.'&mtime='.microtime(true).'&amp;debug" target="_blank" onmouseover="javascript:document.getElementById(\'picID'.self::$imagesizes_worked.'\').src=\''.$imageuri.'&mtime='.microtime(true).'\';">'.CR_LF;
			$html.='		<img src="'.$imageuri.'&mtime='.microtime(true).'" height="'.getOption('smartImageCache_thumbheight').'" id="picID'.self::$imagesizes_worked.'" alt="tentative" style="float:left;">'.CR_LF;
			$html.='	</a>'.CR_LF;
			$html.='	<a href="'.$imageuri.'&mtime='.microtime(true).'&amp;debug" target="_blank">'.CR_LF;
			$html.='		<img src="'.FULLWEBPATH.'/'.USER_PLUGIN_FOLDER.'/smartImageCache/images/unknown.png" height="20" alt="unknown" title="'.$imageuri.'">'.CR_LF;
			$html.='	</a><br>'.CR_LF;
			$html.='	<a href="javascript:void(0)" onclick="javascript:retry(\''.self::$images_counter.'\',\''.self::$imagesizes_worked.'\',\''.$seed.'\',\''.($show?'1':'0').'\',\''.getOption('smartImageCache_thumbheight').'\',\''.$imageuri.'\',\''.SERVERCACHE.$cacheuri.'\',\''.getToken('picID'.self::$imagesizes_worked.'-'.$seed).'\');">'.CR_LF;
			$html.='		<img src="'.FULLWEBPATH.'/'.USER_PLUGIN_FOLDER.'/smartImageCache/images/trash.png" height="20" alt="trash" title="Discard">'.CR_LF;
			$html.='	</a>'.CR_LF;
			$html.='</span>'.CR_LF;

		}
		return $html;
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
			$title=gettext('You must first set the plugin options for smartImageCache parameters.');
		}

		$buttons[]=array(
				'category'=>gettext('Cache'),
				'enable'=>$enable,
				'button_text'=>gettext('Smart Cache manager'),
				'formname'=>'smartImageCache_button',
				'action'=>FULLWEBPATH.'/'.USER_PLUGIN_FOLDER.'/smartImageCache/smartImageCache.php?page=overview&tab=images',
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
			$title=gettext('Finds images that have not been cached and creates the cached versions in a Smart way, based on cacheManager parameters.');
		} else {
			$disable='disabled="disabled"';
			$title=gettext("You must first set the plugin options for smartImageCache parameters.");
		}
		$html.='<div class="button buttons tooltip" title="'.$title.'"><a href="'.FULLWEBPATH.'/'.USER_PLUGIN_FOLDER.'/smartImageCache/smartImageCache.php?album='.html_encode($object->name).'&amp;XSRFToken='.getXSRFToken('cacheImages').'"'.$disable.'><img src="'.FULLWEBPATH.'/'.USER_PLUGIN_FOLDER.'/smartImageCache/images/cache.png" height="20">'.gettext('Smart Cache album images').'</a><br class="clearall" /></div>';
		return $html;
	}
}
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
/* END OF CLASS																																			*/
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */

/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
/* ---------------------------------------------------------------------------------------------------------------------------------------------------- */
?>