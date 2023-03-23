CHANGELOG
--------------------------

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
		- a lot of strings displayed are not tranlated by the platform, because not included in languages libraries

v.1.0.0 - released 20/03/2023
	- Initial release