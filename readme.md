smartImageCache plugin for Zenphoto
Version 1.0.0 Released 2023-03-20
Author Filippo Boscarino
Platform Zenphoto CMS v.1.6.x and higher
Dependancies cacheManager ACTIVE (zp_extension)

INSTALLATION
Copy those files in the plugins folder of your server iinstance of Zenphoto.

smartImageCache.php

smartImageCachecacheImages.php
smartImageCachedeprecated-functions.php
smartImageCachelogo.png

smartImageCacheimagesarrow_down.png
smartImageCacheimagesarrow_up.png
smartImageCacheimagesback.png
smartImageCacheimagescache.png
smartImageCacheimagesclose.png
smartImageCacheimagesdown.png
smartImageCacheimagesforward.png
smartImageCacheimagesinfo.png
smartImageCacheimagesko.png
smartImageCacheimagesko_red.png
smartImageCacheimagesmedia.png
smartImageCacheimagesok.png
smartImageCacheimagesopen.png
smartImageCacheimagespause.png
smartImageCacheimagesplay.png
smartImageCacheimagesreload.png
smartImageCacheimagesstop.png
smartImageCacheimagessuccess.png
smartImageCacheimagestime.png
smartImageCacheimagesunknown.png
smartImageCacheimagesup.png
Now browse your Plugins tab in the Admin section of your Zenphoto installation, search smartImageCache v.x.x in the plugin list and make it ACTIVE. ATTENTION!!! You should have the Zenphoto's cacheManager extension active, to be able to activate this plugin.

Apply the changes and enjoy!

PLUGIN OPTIONS
Plugin options are - number of items per chunk, - enabledisable auto-advance - waiting time before launching next chunk in auto-advance process, - php set timeout for processes - enabledisable sowing of already cached sizes in the result page

FEATURES
This plugin is an evolution of the similar image cache sizes production process, provided by cacheManager

It adds two buttons to generate image cache sizes in a more controlled way

one in the Admin dashboard, under Cache section, labelled Smart Cache manager
one in Album's edit page, labelled Smart Cache album images
They both launch the image creation process.

Those are the features provided - segment the process of generating chache sizes in paged chunks with defined number of items every chunk. - enable an auto-advance process to easily provide batch operations - auto-advance process can be paused and restarted to easily verify things; - a chunk can be reloaded - auto-advance stops by itself if some cache size are failed (limitation of okko results are the same of the actual Zenphoto system) - number of items per chunk, as well as auto-advance method, timing and show already cached images, can be defined as plugin option, but also on the fly, directly in the process launch - all the images (already cached and new ones) can be thumbed in page, for a better control of the whole process (if the relative option is activated during the process) - thumbs showed are a quite large (50px high) to give users the rought ability to control the single cache size results - if some image is not loaded correctly by your browser, passing mouse pointer on it, lets attempt reloading of cache size - plugin can produce image cache sizes, both with GDlibrary or Imagick libraries, and both with Classic and cURL method, based on configuration of your server

Enjoy )

END -
