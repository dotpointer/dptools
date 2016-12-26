#!/usr/bin/php
<?php

	if (!function_exists('getimagesize')) {
		die('Fatal error: PHP-GD not installed');
	}

	if (!function_exists('finfo_open')) {
		die('Fatal error: PHP Fileinfo not installed');
	}

	# BEWARE, this deletes code files - php, cpp, js etc.
	# GIF, PNG will also be deleted
	# 2015-05-25 23:44:03 - first version
	# 2016-03-16 15:38:08 - cleanup, addition of checks and various improvements
	# 2016-03-16 17:29:56 - bugfix
	# 2016-03-16 18:13:49 - adding audio size filter
	# 2016-03-16 20:02:09 - adding video size filter

	# file extensions to delete
	$ext_remove = array(
'cig', 
'sqlitedb', 
'sidf', 
'sidv', 
'xhtml', 
'ncx', 
'opf', 
'aside', 
'ithmb', 
'cloudphotodb', 
'albumlistmetadata', 
'foldermetadata', 
'streamingunzipresumptiondata', 
'dfu', 
'img3', 
'n94',
'cert',
'cod',
'cvd',
'dbc',
'dos',
'frag',
'i01',
'i02',
'i06',
'i08',
'i11',
'i12',
'i13',
'i14',
'i15',
'i16',
'i17',
'i18',
'i19',
'i20',
'i21',
'i22',
'i23',
'i24',
'i25',
'i26',
'i30',
'i31',
'i33',
'i34',
'i36',
'i37',
'i38',
'i42',
'i45',
'i46',
'i47',
'i52',
'i53',
'i59',
'i60',
'i61',
'i62',
'i63',
'i64',
'i66',
'i67',
'i68',
'i69',
'i70',
'i71',
'i76',
'index',
'ivd',
'lan',
'let',
'lua',
'mar',
'mf',
'mzz',
'ntd',
'nut',
'orsp',
'orspheap',
'orspstats',
'ram',
'rv3',
'rv5',
'rv8',
'session',
'smc',
'tib',
'x86',
'acrodata', 'xdb', 'wk4', 'shw', 'wb2', 'wpd', 'wab', 'dbx', 'se', 'json', 'axd', 'xdc', 'prc', 'wpc', 'sdf', 'spd', 'skin', 'bof', 'adf','vsl', 'hhk', 'acs', 'tsk', 'job', 'xdr', 'crmlog', 'nt', 'asms', 'query', 'dlg', 'mmf', 'lrc', 'cpi', 'evt', 'mpf', 'pro', 'ref', 'esn', 'trm', 'dbl', 'nsf', 'chq', 'wcl',
'7z', 
'adml', 
'admx', 
'am', 
'amx', 
'ascx', 
'bcf', 
'bcm', 
'browser', 
'bud', 
'camp', 
'cap', 
'cdmp', 
'cjstyles', 
'clb', 
'clt', 
'cmb', 
'cmf', 
'comments', 
'contact', 
'cov', 
'cpa', 
'cpf', 
'cpt', 
'csm', 
'ctt', 
'cw', 
'dcf', 
'diagcab', 
'diagpkg', 
'dir', 
'dlm', 
'dls', 
'ebd', 
'efi', 
'ev1', 
'ev2', 
'ev3', 
'evm', 
'evtx', 
'fbx', 
'fcl', 
'fe', 
'fxo', 
'fx',
'p5m', 
'gmmp', 
'gpd', 
'grl', 
'grm', 
'h1c', 
'h1h', 
'h1k', 
'h1q', 
'h1s', 
'h1t', 
'h1w', 
'hit', 
'iec', 
'imd', 
'ime', 
'ird', 
'jnt', 
'jse', 
'kc', 
'kmd', 
'kpz', 
'loc', 
'lpl', 
'lpt', 
'lpz', 
'lxa', 
'master', 
'mbk', 
'mib', 
'mni', 
'mrf', 
'msk', 
'msstyles', 
'mum', 
'ncz', 
'ngr', 
'nlp', 
'nlt', 
'ntf', 
'p2s', 
'p3k', 
'p5p', 
'p5x', 
'pc2', 
'pcl2', 
'pcm', 
'phn', 
'pin', 
'pkms', 
'ps1', 
'ps1xml', 
'psc1', 
'ptxml', 
'pyc', 
'pyd', 
'res', 
'resx', 
'rom', 
'rpo', 
'rs', 
'scd', 
'sdi', 
'sim', 
'smp', 
'spc', 
'spm', 
'stl', 
't4', 
'tbl', 
'thl', 
'tsm', 
'ttc', 
'uaq', 
'uce', 
'uni',
'vbs', 
'wc1', 
'vdf', 
'wim', 
'win32manifest', 
'wlt', 
'vp', 
'wsc', 
'wsf', 
'wtv', 
'xbap', 
'xex', 
'wtl', 'mht', 'xps', 'isn', 'ibt', 'wcd', 'policy', 'xtr', 'aw', 'wwp', 'wws', 'wpj', 'wwd', 'sbc', 'sbs', 'sbt', 'ibd', 'eit',
'aum', '0 prefs', 'zip', 'nki', 'qml', 'spa', 'id', 'gam', 'sep', 'sem', 'cst', 'clm', 'package', 'mdmp', 'srm', 'pxo', 'sac', 'tudb', 'sessiondata', 'eninfo', 'exb', 'activitylog', 'snippets', 'thumbnails', 'hxd', 'etl', 'ldb', 'sqlite3', 'md5', 'usage', 'bad', 'heu', 'swz', 'sol', 'bdic', 'h1d', 'wmdb', 'scf', 'dt2', 'id2', 'xsslog', 'xss', 'xssr', 'stg', 'crl', 'resp', 'wer', 'ics', 'jrs', 'oeaccount', 'msmessagestore', 'wlmi', 'ed1', 'fd1', 'pd6', 'fol', 'eml', 'dtd', 'state', 'pd4', 'qtp', 'fb', 'profile', 'lck', 'certs', 'pds', 'tdb', 'ist', 'irs', 'act', 'abdata', 'workspace', 'mcdb', 'ims', 'cfa', 'pek', 'irx', 'lcd', 'prmdc', 'pref', 'ipcc', 'cidb', 'binarycookies', 'crash', 'gz', 'mbdb', 'mbdx', 'xa02000', 'xa03264', 'xa04352', 'xa05436', 'xa06768', 'xa06772', 'xa07308', 'xa07916', 'syncdb', 'changedb', 'synciddb', 'syncconflict', 'adminarchive', 'rcb', 'prefs', 'sbu', 'benc', 'btapp', 'chw', 'pcb', 'cch', 'rar', 'asd', 'wfc', 'dotm', 'bridgesort', 'mswmm', 'sfk', 'docx', 'itc2', 'itdb', 'itl',
'log1',
'log2',
'jsm',
'xspf',
'upp',
'ezlog',
'toc',
'hxw',
'hxc',
'hxh',
'pset',
'sqlite',
'little',
'camproj',
'bnk',
'localstorage',
'file',
'cflog',
'erpt',
'user',
'pmp',
'ytf',
'hl1',
'hl2',
'hl3',
'sav',
'drm',
'blf',
'sdp',
'qss',
'eps',
'shp',
'fxh',
'dpc',
'xll',
'acctb',
'xcd',
'bpl',
'snu',
'wlms',
'rct',
'spp',
'aup',
'x3d',
'mpp',
'8li',
'8bf',
'aco',
'tpl',
'pat',
'p3e',
'zvt',
'fon',
'dbrsb',
'dbrush',
'atn',
'p3m',
'csh',
'aco',
'grd',
'asl',
'dae',
'epr',
'iros',
'alu',
'aht',
'hdt',
'ado',
'acv',
'aco',
'csh',
'blw',
'cha',
'atn',
'look',
'exp',
'kys',
'mnu',
'setting',
'zxp',
'qm',
'ui',
'8bx',
'accdt',
'oft',
'lib',
'catalog',
'vlt',
'tts',
'lts',
'idx',
'nop',
'prf',
'icxs',
'vch',
'gpl',
'rdf',
'lgpl',
'jsxinc',
'xslt',
'pdef',
'avgdx',
'sig',
'xpt',
'tvp',
'pdb',
'eot',
'jsa',
'bfc',
'xul',
'accdb',
'propdesc',
'ceb',
'pak',
'nexe',
'crx',
'fmt',
'sfx',
'wmz',
'dub',
'wlmx',
'mct',
'jtp',
'snippet',
'hxa',
'ldf',
'trc',
'hxt',
'ppt',
'wts',
'gta',
'xls',
'mmw',
'acc',
'odc',
'lns',
'nlc',
'msg',
'osl',
'hxt',
'psp',
'mof',
'hxq',
'emf',
'8bi',
'afm',
'pfb',
'avc',
'clx',
'ths',
'mof',
'md3',
'md8',
'n5m',
'nkx',
'mm9',
'ofs',
'dct',
'jsx',
'helpcfg',
'csf',
'qtxs',
'qpa',
'0',
'1',
'2',
'3',
'4',
'5',
'6',
'7',
'8',
'9',
'nvi',
'nvx',
'forms',
'ach',
'ttf',
'net',
'eftx',
'odt',
'mml',
'thmx',
'icm',
'nla',
'crt',
'udt',
'unt',
'csd',
'apl',
'wih',
'spl',
'msu',
'mst',
'its',
'lex',
'elm',
'odf',
'xsd',
'flt',
'frm',
'fnt',
'cgm',
'wpg',
'hxs',
'syncschema',
'svg',
'icc',
'bitmap',
'otf',
'prm',
'mmm',
'pfm',
'doc',
'zdct',
'utxt',
'pimx',
'hyp',
'env',
'fca',
'hsp',

		'accfl',
		'bdr',
		'cer',
		'cnv',
		'data',
		'dotx',
		'dpv',
		'fdt',
		'luac',
		'mapping',
		'olb',
		'one',
		'onepkg',
		'pip',
		'poc',
		'potx',
		'tmx',
		'xlam',
		'xltx',
		'xsl',
		'xsn',
		'fae',
		'sam',
		'wmf',
		'accdu',
		'accde',
		'accda',
		'hxk',
		'hxc',
		'opg',
		'acl',

		'rtf',
		'itxib',
		'plist',
		'mo',
		'po',
		'lproj',
		'2jp',
		'acb',
		'acm',
		'alt',
		'ani',
		'api',
		'ara',
		'asp',
		'asp',
		'aspx',
		'ass',
		'asx',
		'asx',
		'ata',
		'ax',
		'b21',
		'b71',
		'bak',
		'bat',
		'bav',
		'bin',
		'bom',
		'bto',
		'btr',
		'ca',
		'cab',
		'cab',
		'cache',
		'cat',
		'cfg',
		'chk',
		'chm',
		'chs',
		'cht',
		'class',
		'cmd',
		'cmp',
		'cnt',
		'com',
		'com',
		'config',
		'cpl',
		'cpp',
		'css',
		'csy',
		'cur',
		'dan',
		'dat',
		'datold',
		'db',
		'dbf',
		'default',
		'dep',
		'deu',
		'dft',
		'dft',
		'dic',
		'dll',
		'dmp',
		'dot',
		'drv',
		'ds',
		'dwg',
		'dwr',
		'ect',
		'edb',
		'ell',
		'eng',
		'ent',
		'enu',
		'ese',
		'esp',
		'exe',
		'exe',
		'fin',
		'fla',
		'flg',
		'flv',
		'for',
		'fra',
		'fs',
		'gadget',
		'gif',
		'h',
		'hdr',
		'heb',
		'hhc',
		'hiv',
		'hlp',
		'hlx',
		'hta',
		'htc',
		'htm',
		'htm',
		'html',
		'html',
		'htt',
		'htx',
		'hun',
		'ico',
		'ico',
		'ied',
		'ies',
		'ilg',
		'inf',
		'ini',
		'inx',
		'ion',
		'ipp',
		'iss',
		'ita',
		'jar',
		'jpn',
		'js',
		'js',
		'jsp',
		'key',
		'ko',
		'kor',
		'lic',
		'LiveUpdate',
		'lng',
		'lnk',
		'lnk',
		'log',
		'log',
		'lst',
		'man',
		'manifest',
		'map',
		'mdb',
		'mde',
		'mdf',
		'mdt',
		'me',
		'mfl',
		'mgc',
		'min',
		'mnf',
		'mod',
		'msc',
		'msi',
		'msp',
		'mui',
		'ned',
		'nib',
		'nld',
		'nls',
		'nor',
		'nt4',
		'och',
		'ocx',
		'ocx',
		'old',
		'osd',
		'pdf',
		'pf',
		'php',
		'pif',
		'pk',
		'ple',
		'plk',
		'plt',
		'pne',
		'pnf',
		# 'png',
		'prop',
		'properties',
		'psm',
		'pst',
		'ptb',
		'qtr',
		'qtx',
		'rbf',
		'rch',
		'reg',
		'rll',
		'rpt',
		'rpw',
		'rq0',
		'rqe',
		'rra',
		'rsd',
		'rsq',
		'rsr',
		'rst',
		'rus',
		'scr',
		'sdb',
		'ser',
		'sif',
		'sqm',
		'srs',
		'sst',
		'stc',
		'stp',
		'strings',
		'sve',
		'swf',
		'syd',
		'sys',
		'sys',
		'syx',
		'tha',
		'thd',
		'thm',
		'tlb',
		'tmp',
		'trk',
		'tsp',
		'tta',
		'txt',
		'ull',
		'url',
		'war',
		'ver',
		'WindowsLiveContact',
		'vir',
		'vir0',
		'vir1',
		'wpe',
		'wpl',
		'vxd',
		'xib',
		'xis',
		'xml'
	);

	# image file extensions, used when checking image dimensions
	$ext_images = array(
		'jpg',
		'jpeg',
		'gif',
		'png',
		'tiff',
		'tga',
		'mng'
	);

	$filesize_audio_kb_limit = 100 * 1024; #kB
	
	$filesize_video_kb_limit = 1000 * 1024; #kB

	# output function
	function cl($s, $newline=true) {
		echo date('Y-m-d H:i:s').' '.$s.($newline ? "\n" : '');
	}

	# set the execution time limit to unlimited as this takes time
	set_time_limit(0);

	# default parameters
	$path = false;
	$delete = false;
	$yes = false;
	$collect_exts = false;

	$ext_collected = array();

	# get and walk arguments
	foreach (getopt('p:dcy', array('path', 'delete:', 'yes', 'collect')) as $k => $v) {
		switch ($k) {
			case 'c':
			case 'collect':
				$collect_exts = true;
				break;

			case 'd':
			case 'delete':
				$delete = true;
				break;
			case 'p':
			case 'path':
				$path = trim($v);
				break;
			case 'y':
			case 'yes':
				$yes = true;
				break;
			case 'h':
			case 'help':
?>
 __dotpointers _       _
|     |___ ___| |_ ___| |_ ___
| | | | . |  _|   | -_|  _| -_|
|_|_|_|__,|___|_|_|___|_| |___|
	an effective media mining script

Usage: ./<php echo __SCRIPT_FILENAME__?> <parameters>

	--path <target dir>, -p <target dir>
		Used to set the root directory to delete from

	--delete, -d
		Actually perform the deletions, by default it will only simulate

	--help, -h
		Print this help
	--yes, -y
		Override confirmation.
	--collect, -c
		Collect extensions.
<?php
				die();
		}
	}

	# check path
	if (!$path || !strlen($path) || $path === '/' || !file_exists($path)) {
		cl('FATAL: path not set or invalid.');
		exit(1);
	}

	# get the real path of this location
	$path = realpath($path).'/';

	# check path again
	if (!$path || !strlen($path) || $path === '/' || !file_exists($path)) {
		cl('FATAL: path not set or invalid.');
		exit(1);
	}

	# confirm execution
	cl('Will run on this path: '.$path);
	cl('Will run for real deletion: '.($delete ? 'yes' : 'no'));
	cl('Will collect extensions: '.($collect_exts ? 'yes' : 'no'));
	if (!$yes) {
		cl('Please confirm this action, type "y" to continue: ', false);
		$handle = fopen ("php://stdin","r");
		$line = fgets($handle);
		if(trim($line) != 'y'){
			cl('Action aborted.');
			exit(1);
		}
		fclose($handle);
	}

	cl('Switching working directory to '.$path);
	chdir($path);

	cl('Finding files...');
	# get a list of files
	$cmd = 'find '.escapeshellarg($path).' -type f';
	$files = shell_exec($cmd);
	if ($files === false) {
		cl('Could not do find.');
		exit(1);
	}
	$files = explode("\n", $files);
	cl('Finding files completed, '.count($files).' files found.');

	$n=0;
	$deleted = 0;
	$mimes = array();

	cl('Walking files...');
	# walk files
	foreach ($files as $file) {
		$n++;
		$is_image = false;

		$fullpath = $file;
		#cl('Checking '.$n.': '.$fullpath);

		# not found - get out
		if (!file_exists($fullpath)) {
		#	cl('Nonexistant - skipping');
			continue;
		}

		# is it a dir?
		if (is_dir($fullpath)) {
		#	cl('Directory - skipping');
			continue;
		}

		$filesize = filesize($fullpath);

		# empty files
		if ($filesize === 0) {
				$deleted++;
				cl('DELETING '.$deleted.' EMPTY: '.$file);
				if ($delete) {
					unlink($fullpath);
				}
				continue;
		}

		$ext = basename($fullpath);
		$ext = strrpos($ext, '.') !== false ? strtolower(trim(substr($ext, strrpos($ext, '.') + 1))) : false;

		# is there an extension?
		if ($ext !== false) {

			# is in array of crap extensions
			if (in_array($ext, $ext_remove)) {
				$deleted++;
				cl('DELETING '.$deleted.' REMOVE EXT '.$ext.': '.$file);
				if ($delete) {
					unlink($fullpath);
				}
				continue;
			}
			
			# numeric extension - .001 etc
			if (is_numeric($ext)) {
				$deleted++;
				cl('DELETING '.$deleted.' NUMERIC EXT: '.$file);
				if ($delete) {
					unlink($fullpath);
				}
				continue;
			}

			/*# extension has an underscore - .HL_
			if (strpos($ext, '_')) {
				$deleted++;
				cl('DELETING '.$deleted.' NUMERIC EXT: '.$file);
				if ($delete) {
					unlink($fullpath);
				}
				continue;

			}*/

			# walk bad characters for an ext extension and delete
			foreach (array('[', ']', '=', '~', '@', '{', '}', '(', ')', '!', '<', '>', '+', '-', '&', '$', '_', ':', ';') as $v) {
				if (strpos($ext, $v) !== false) {
					$deleted++;
					cl('DELETING '.$deleted.' BAD EXT CHARS: '.$file);
					if ($delete) {
						unlink($fullpath);
					}
					continue 2;
				}
			}

			# extension with only 1 char
			if (strlen($ext) < 2) {
					$deleted++;
					cl('DELETING '.$deleted.' EXT TOO SHORT: '.$file);
					if ($delete) {
						unlink($fullpath);
					}
					continue;
			}

		# no extension - no dot? piece of crap!
		} else {
			$deleted++;
			cl('DELETING '.$deleted.' NO EXTENSION: '.$file);
			if ($delete) {
				unlink($fullpath);
			}
			continue;
		}

		# find mime
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$mime = finfo_file($finfo, $fullpath);
		finfo_close($finfo);
		if ($mime === false) continue;

		# is image?
		if (strpos($mime, "image/") !== false) {
			# get the image dimensions
			$x = @getimagesize($fullpath);
			# failed getting dimensions, no dimensions or mime data or not an image
			if ( $x !== false && isset($x[0], $x[1], $x['mime']) && strpos($x['mime'], 'image/') !== false) {
				# this is an image
				$is_image = true;
				# is the image too small?
				if (($x[0] > 0 && $x[0] < 150) || ($x[1] > 0 && $x[1] < 150)) {
					$deleted++;
					cl('DELETING '.$deleted.' '.$x[0].'x'.$x[1].' '.$x['mime'].': '.$file);
					if ($delete) {
						unlink($fullpath);
					}
					continue;
				}
			}
		# is it audio and the file is smaller than 1024b * 500 = 500kB?
		} else if (strpos($mime, "audio/") !== false && $filesize < $filesize_audio_kb_limit) {
		
			$deleted++;
			cl('DELETING '.$deleted." AUDIO FILE TOO SMALL (".(round($filesize / 1024,2))." kB): ".$file);
			if ($delete) {
				unlink($fullpath);
			}
			continue;		
		# is it video and the file is smaller than 1024b * 500 = 500kB?
		} else if (strpos($mime, "video/") !== false && $filesize < $filesize_video_kb_limit) {
		
			$deleted++;
			cl('DELETING '.$deleted." VIDEO FILE TOO SMALL (".(round($filesize / 1024,2))." kB): ".$file);
			if ($delete) {
				unlink($fullpath);
			}
			continue;		
		}

		# not an image, but it has the extension of one?
		if (!$is_image && in_array($ext, $ext_images)) {

			$deleted++;
			cl('DELETING '.$deleted." NOT IMAGE: ".$file);
			if ($delete) {
				unlink($fullpath);
			}
			continue;
		}

		# is this in the list of mime to delete?
		if (in_array($mime, array('text/plain'))) {
			$deleted++;
			cl('DELETING '.$deleted." BAD MIME: ".$file);
			if ($delete) {
				unlink($fullpath);
			}
			continue;

		}

		#if (strpos($mime, "image/") === false && strpos($mime, "audio/") === false && strpos($mime, "video/") === false && strpos($mime, "ms") === false ) {
		#	$deleted++;
		#	cl('DELETING '.$deleted." ".$mime.": ".$file);
		#	if ($delete) {
		#		unlink($fullpath);
		#	}
		#}


		# are we collecting extensions?
		if ($collect_exts && !$is_image && !in_array($ext, $ext_images) && !in_array($ext, $ext_remove) && !in_array($ext, $ext_collected)) {
			$ext_collected[] = $ext;
		}
	}

	$deleteddirs = 0;
	# do {
		$deleted_a_dir = false;
		# get a list of files
		$cmd = 'find '.escapeshellarg($path).' -type d -empty';
		$dirs = shell_exec($cmd);
		if ($dirs === false) die('Could not do find.');
		$dirs = explode("\n", $dirs);
		$dirs = array_reverse($dirs);
		foreach ($dirs as $dir) {
			if (file_exists($dir) && !in_array(basename($dir), array('.', '..'))) {

				$deleteddirs++;
				cl('DELETING '.$deleteddirs.' EMPTY DIR: '.$dir);
				if ($delete) {
					rmdir($dir);
				}
				$deleted_a_dir = true;
				continue;

			}
		}
	# } while ($deleted_a_dir);

	cl('Checked: '.$n);
	cl('Deleted: '.$deleted);
	if ($collect_exts) {
		foreach ($ext_collected as $k => $v) {
			$ext_collected[$k] = "'".$v."'";
		}
		
		cl('Collected exts: '.implode(', ', $ext_collected));
	}
?>
