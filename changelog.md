CHANGELOG
--------------------------

v.1.1.0 - released 12/04/2023

	- Introduced a new secured runtime function to discard a single produced cache size in the launcher page
	  and directly delete its own file from file system.
	- Display dimensions (height) for cached sizes thumbs is now an option and can be configured in plugin setup.
	- Sidecar thumbs for Multimedia items are now included in generation process.
	- Some browser's cache controls have been added to ensure to have the real result showed in thumbs.
	- Albums and Items name are now a link and can open their relative user page in a new page.
	- Infos on folders not involved in the chunk process are now not shown anymore, to keep the results page
	  brief and easier to read in deep album trees.
	- Corrected controls on misleading message for already cached albums (once defined as without images).
	- How many items per chunk and Pause before reload input fields are now bounded ranges in the launcher page.
	- Fixed Reload button at top functioning.
	- Continue, Pause and Reload buttons now are disabled when they can act anymore.
	- Added a dedicated stand-alone style sheet.
	- 'cacheManager.php' has been renamed into 'smartImageCache.php' to better identify the plugin's functionalities
	- 'function.php' has been created to collect all the functions dedicated to the plugin.
	- 'deprecated-functions.php' has been eliminated.
	- Corrected some typos in messages.
	- Resized Logo in the plugins list.
	- Code reviewed and some cosmetic touch-ups to the launching page and to the outputs.

	Known issues:
		- a lot of strings displayed are not tranlated by the platform, because not included in language libraries

v.1.0.2 - released 23/03/2023

	- a bug in the album tree navigation that could stop the process, has been fixed
	- a message about the total number of the items cointained in the album tree has been added in the launcher page
	- some counters has been corrected in the result panel
	- some cosmetic touch-ups to the launching page and to the outputs

	Known issues:
		- source's comments are not omologate to docblocks standards
		- a lot of strings displayed are not tranlated by the platform, because not included in language libraries

v.1.0.1 - released 22/03/2023

	- Zenphoto's naming corrected
	- camelCase renaming of functions/methods
	- improvement of dependency control on cacheManager activation
	- Correction of problems when applying the process directly to the main Gallery (could be very heavy anyway on complex Album trees)
	- process option parameters are now all passed by POST method within the process
	- optimization of Albums tree navigation: system now avoid to browse unuseful branch and nodes in every chunks
	- adding an option to show or hide cache sizes produced in advance
	- adding an evidence in the control panel on which graphic libraries and which method are used to generate cache sizes
	- some cosmetic touch-ups to the outputs
	- some bug fixes
	- changelog and readme text files have been added to the package

	Known issues:
		- source's comments are not omologate to docblocks standards
		- a lot of strings displayed are not tranlated by the platform, because not included in language libraries

v.1.0.0 - released 20/03/2023
	- Initial release