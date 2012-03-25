<?php/* WWW - PHP micro-frameworkIndex gateway image handlerImage handler is loaded for returning image files. Image mode adds proper cache headers and also has options for dynamic image resizing and cropping. Images run through this mode are files with extensions *.jpeg, *.jpg, *.png and *.ico.Author and support: Kristo Vaher - kristo@waher.net*/// Currently known location of the file$resource=str_replace(DIRECTORY_SEPARATOR.DIRECTORY_SEPARATOR,DIRECTORY_SEPARATOR,$_SERVER['DOCUMENT_ROOT'].$_SERVER['REDIRECT_URL']);// Getting information about current resource$fileInfo=pathinfo($resource);// Assigning file information$file=$fileInfo['basename'];// File extension in requested file$extension=$fileInfo['extension'];// Solving the folder that client is loading resource from$folder=$fileInfo['dirname'].DIRECTORY_SEPARATOR;// Web root is the subfolder on public site$webRoot=str_replace('index.php','',$_SERVER['PHP_SELF']);	// Web root is the subfolder on public site$systemRoot=str_replace('index.php','',$_SERVER['SCRIPT_FILENAME']);	// Dynamic resource loading can be turned off in configurationif(!isset($config['dynamic-image-loading']) || $config['dynamic-image-loading']==true){	// If filename includes & symbol, then system assumes it should be dynamically generated	$parameters=array_unique(explode('&',$file));} else {	$parameters=array();	$parameters[0]=$file;}// True filename is the last string in the string separated by |$file=array_pop($parameters);// Current true file position$resource=$folder.$file;// Files from /resources/ folder can be overriden if file with the same name is placed to /overrides/resources/if(preg_match('/^'.str_replace('/','\/',$webRoot).'resources/',$_SERVER['REDIRECT_URL'])){	//Checking if file of the same name exists in overrides folder	$overrideFolder=str_replace($webRoot.'resources'.DIRECTORY_SEPARATOR,$webRoot.'overrides'.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR,$folder);	if(file_exists($overrideFolder.$file)){		// System will use an override as a resource, since it exists		$resource=$overrideFolder.$file;	}	}// If file does not exist then 404 is thrownif(!file_exists($resource) && (!isset($config['404-image-placeholder']) || $config['404-image-placeholder']==true) && file_exists(__ROOT__.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'placeholder.jpg')){	// Placeholder image can be used instead of 404 text	$resource=__ROOT__.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'placeholder.jpg';	// 404 header	header('HTTP/1.1 404 Not Found');		// This variable is used by cache to calculate cache filename, but since system is returning a placeholder instead, it is overwritten	$_SERVER['REDIRECT_URL']=array_pop(explode('/',str_replace($file,'placeholder.jpg',$_SERVER['REDIRECT_URL'])));		// This variable is used by Logger in the end of the Index gateway to write the type of logging used	$_SERVER['QUERY_STRING']='404';	} elseif(!file_exists($resource)){	// Returning 404 data		require(__DIR__.DIRECTORY_SEPARATOR.'handler.404.php');	die();}// Default cache timeout of one month, unless timeout is setif(!isset($config['resource-cache-timeout'])){	$config['resource-cache-timeout']=31536000; // A year}// If robots setting is not defined in cache, then it is turned offif(!isset($config['robots'])){	header('X-Robots-Tag: noindex,nocache,nofollow,noarchive,noimageindex,nosnippet', true);} else {	header('X-Robots-Tag: '.$config['robots'], true);}// Last-modified time of the original resource$lastModified=filemtime($resource);// If file seems to carry additional configuration options, then it is generated or loaded from cacheif(!empty($parameters) && in_array($extension,array('png','jpg','jpeg'))){		// Solving cache folders and directory	$cacheFilename=md5($lastModified.$_SERVER['REDIRECT_URL']).'.tmp';	$cacheDirectory=__ROOT__.DIRECTORY_SEPARATOR.'filesystem'.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'images'.DIRECTORY_SEPARATOR.substr($cacheFilename,0,2).DIRECTORY_SEPARATOR;		// If cache file exists then cache modified is considered that time	if(file_exists($cacheDirectory.$cacheFilename)){		$lastModified=filemtime($cacheDirectory.$cacheFilename);	} else {		// Otherwise it is server request time		$lastModified=$_SERVER['REQUEST_TIME'];	}			// If resource cannot be found from cache, it is generated	if(in_array('nocache',$parameters) || ($lastModified==$_SERVER['REQUEST_TIME'] || $lastModified<($_SERVER['REQUEST_TIME']-$config['resource-cache-timeout']))){			// Get existing image size		$resolution=getimagesize($resource);			// Default settings for dynamically resized image		// This values will be changed based on if parameters are set		$width=$resolution[0];		$height=$resolution[1];		$algorithm=false;		$red=false;		$green=false;		$blue=false;		$top=false;		$left=false;		$quality=false;		$filters=array();		$filterSettings=array();				// Looping over the data bits to find additional parameters		foreach($parameters as $parameter){			switch($parameter){							case 'fitcrop':					// This is a resize algorithm flag					$algorithm='fitcrop';					break;									case 'crop':					// This is a resize algorithm flag					$algorithm='crop';					break;									case 'fitwithbackground':					// This is a resize algorithm flag					$algorithm='fitwithbackground';					break;									case 'fitwithoutbackground':					// This is a resize algorithm flag					$algorithm='fitwithoutbackground';					break;									case 'widthonly':					// This is a resize algorithm flag					$algorithm='widthonly';					break;									case 'heightonly':					// This is a resize algorithm flag					$algorithm='heightonly';					break;									default:					// If any of the resize algorithm and cache flags were not hit, the parameter is matched for other conditions					if(strpos($parameter,'filter(')!==false){											// Background color setting is assumed if rgb is present						$settings=str_replace(array('filter(',')'),'',$parameter);						$settings=explode(',',$settings);												// Storing data of new filter						$newFilter=array();												// First number is the filter type						if($settings[0]!=''){													// Filter type can also have parameters							$typeSettings=explode('@',$settings[0]);														// First parameter is the filter type							$newFilter['type']=$typeSettings[0];														// It is possible to 'layer' the effect by defining alpha level as the second parameter							if(isset($typeSettings[1])){								$newFilter['alpha']=$typeSettings[1];							} else {								// Filter effect is 100% if alpha was not defined								$newFilter['alpha']=100;							}													}												// Storing data of new filters settings						$newFilter['settings']=array();												// Storing other filter variables						for($i=1;isset($settings[$i]);$i++){							$newFilter['settings'][]=$settings[$i];						}												// Adding filter to list of filters						$filters[]=$newFilter;											} elseif(strpos($parameter,'rgb(')!==false){											// Background color setting is assumed if rgb is present						$colors=str_replace(array('rgb(',')'),'',$parameter);						$colors=explode(',',$colors);												// First number in parameter is red color amount						if($colors[0]!=''){							$red=$colors[0];						}						// Second number in parameter is green color amount						if(isset($colors[1]) && $colors[1]!=''){							$green=$colors[1];						}						// Third number in parameter is blue color amount						if(isset($colors[2]) && $colors[2]!=''){							$blue=$colors[2];						}											} elseif(strpos($parameter,'@')!==false){											// Quality setting is assumed if @ sign is present						$quality=str_replace('@','',$parameter);											} elseif(strpos($parameter,'-')!==false){											// Position setting is assumed if dash is present						$positions=explode('-',$parameter);						// First value is top position						// This can be 'top', 'center', 'bottom' or a number in pixels						if($positions[0]!=''){							$top=$positions[0];						}						// Second value is left position						// This can be 'left', 'center', 'right' or a number in pixels						if($positions[1]!=''){							$left=$positions[1];						}											} elseif(strpos($parameter,'x')!==false){											// It is assumed that the remaining parameter is for image dimensions						$dimensions=explode('x',$parameter);						// First number is width						if($dimensions[0]!=''){							$width=$dimensions[0];						}						// Second number, if defined, is height						if(isset($dimensions[1]) && $dimensions[1]!=''){							$height=$dimensions[1];						} else {							// If height is not defined then height is considered to be as long as width							$height=$width;						}												// If algorithm is still undefined, it is given a default value						if(!$algorithm){							$algorithm='fitcrop';						}											} elseif($parameter!='nocache'){											// Returning 404 data							echo $parameter;die();						require(__DIR__.DIRECTORY_SEPARATOR.'handler.404.php');						die();								}					break;			}		}				// System checks for legality of the entered values				// Whitelists allow to protect the server better from possible abuse and denial of service attacks		$allowed=true;				// Default maximum image dimension height/width		if(!isset($config['dynamic-max-size'])){			$config['dynamic-max-size']=1000; // Month		}				// If image dimensions are beyond allowed values		if($width>$config['dynamic-max-size'] || $height>$config['dynamic-max-size'] || $height==0 || $width==0){			$allowed=false;		}				// For size whitelist check		// If resolution has been changed and this resolution is not found in whitelist		if($allowed && isset($config['dynamic-size-whitelist']) && !empty($config['dynamic-size-whitelist']) && ($width!=$resolution[0] || $height!=$resolution[1]) && !in_array($width.'x'.$height,$config['dynamic-size-whitelist'])){			$allowed=false;		}				// For RGB whitelist check		// If RGB values are not defaults and this setting is not found in color whitelist		if($allowed && isset($config['dynamic-color-whitelist']) && !empty($config['dynamic-color-whitelist']) && ($red || $green || $blue) && !in_array($r.','.$g.','.$b,$config['dynamic-color-whitelist'])){			$allowed=false;		}				// For quality whitelist check		// If quality values are not defaults and this setting is not found in quality whitelist		if($allowed && isset($config['dynamic-quality-whitelist']) && !empty($config['dynamic-quality-whitelist']) && $quality && !in_array($quality,$config['dynamic-quality-whitelist'])){			$allowed=false;		}				// For position whitelist check		// If position values are not defaults and this setting is not found in position whitelist		if($allowed && isset($config['dynamic-position-whitelist']) && !empty($config['dynamic-position-whitelist']) && ($top || $left) && !in_array($top.'-'.$left,$config['dynamic-position-whitelist'])){			$allowed=false;		}				// For filter whitelist check		// If filters are not in filter whitelist then processing is canceled		if($allowed && isset($config['dynamic-filter-whitelist']) && !empty($config['dynamic-filter-whitelist']) && !empty($filters)){					// Checking each filter setting to make sure it is whitelisted			foreach($filters as $filter){				if($allowed && !in_array($filter['type'].'@'.$filter['alpha'].','.implode(',',$filter['settings']),$config['dynamic-filter-whitelist'])){					$allowed=false;				}			}					}				// If whitelist checks did not fail and image dimensions are good		if($allowed){						// If GD library should be used to process the image or not			if($algorithm || $quality || !empty($filters)){							// If cache folder does not exist, it is created				if(!is_dir($cacheDirectory)){					if(!mkdir($cacheDirectory,0777)){						trigger_error('Cannot create cache folder',E_USER_ERROR);					}				}							// This functionality only works if GD library is loaded				if(extension_loaded('gd')){										// If algorithm has not been set, it is defined here					if(!$algorithm){						$algorithm='fitcrop';					}										// If background color has not been set, it is defined here					if(!$red || !$green || !$blue){						$r=0;						$g=0;						$b=0;					}										// If position has not been set, it is defined here					if(!$top || !$left){						$top='center';						$left='center';					}										// If quality has not been set, it is defined here					if(!$quality){						$quality=90;					}										// If quality has not been set, it is defined here					if(!$quality){						$quality=85;					}									// Requiring WWW_Imager class that is used to do basic image manipulation					require(__ROOT__.DIRECTORY_SEPARATOR.'engine'.DIRECTORY_SEPARATOR.'class.www-imager.php');										// New imager object, this is a wrapper around GD or ImageMagick library					$picture=new WWW_Imager();										// Current image file is loaded into Imager					$picture->input($resource);									// Image is filtered through resize algorithm and saved in cache directory					switch($algorithm){											case 'fitcrop':							// Crop algorithm fits the image into set dimensions, cutting the edges that do not fit							$picture->resizeFitCrop($width,$height,$left,$top);							break;													case 'crop':							// Crop algorithm places image in new dimensions box cutting the edges that do not fit							$picture->resizeCrop($width,$height,$left,$top,$red,$green,$blue);							break;													case 'fitwithbackground':							// This fits image inside the box and gives it certain color background (if applicable)							$picture->resizeFit($width,$height,$left,$top,$red,$green,$blue);							break;													case 'fitwithoutbackground':							// This simply resizes the image to fit specific dimensions							$picture->resizeFitNoBackground($width,$height);							break;													case 'widthonly':							// This resizes the image to fixed width							$picture->resizeWidth($width);							break;													case 'heightonly':							// This resizes the image to fixed height							$picture->resizeHeight($height);							break;												}										// If filtering is also requested and system does not have it turned off					if((!isset($config['dynamic-image-filters']) || $config['dynamic-image-filters']==true)){											// As long as there are set filters						if(!empty($filters)){							// Each filter is applied, one by one							foreach($filters as $filter){								$picture->applyFilter($filter['type'],$filter['alpha'],$filter['settings']);							}						}										} else {											// Returning 404 data							require(__DIR__.DIRECTORY_SEPARATOR.'handler.404.php');						die();											}										// Resulting image is saved to cache					$picture->output($cacheDirectory.$cacheFilename,$quality);									} else {									// Without GD library the file is simply stored instead					if(!file_put_contents($cacheDirectory.$cacheFilename,file_get_contents($resource))){						trigger_error('Cannot generate dynamic image',E_USER_ERROR);					}									}						} else {							// Without needing to process the image the file is stimply stored in cache				if(!file_put_contents($cacheDirectory.$cacheFilename,file_get_contents($resource))){					trigger_error('Cannot create resource cache',E_USER_ERROR);				}						}				} else {					// Returning 404 data				require(__DIR__.DIRECTORY_SEPARATOR.'handler.404.php');			die();					}			} else {			// To notify logger that cache was used		if(isset($logger)){			$logger->cacheUsed=true;		}			}		// File URL is loaded from cache for dynamic files	$resource=$cacheDirectory.$cacheFilename;	}// Proper content-type is set based on file extensionswitch($extension){	case 'jpg':		header('Content-Type: image/jpeg;');		break;	case 'jpeg':		header('Content-Type: image/jpeg;');		break;	case 'png':		header('Content-Type: image/png;');		break;	case 'ico':		header('Content-Type: image/vnd.microsoft.icon;');		break;		}// If cache is used, then proper headers will be sentif(!in_array('nocache',$parameters)){		// Client is told to cache these results for set duration	header('Cache-Control: public,max-age='.$config['resource-cache-timeout'].',must-revalidate');	header('Expires: '.gmdate('D, d M Y H:i:s',($lastModified+$config['resource-cache-timeout'])).' GMT');	header('Last-Modified: '.gmdate('D, d M Y H:i:s',$lastModified).' GMT');} else {	// Client is told to cache these results for set duration	header('Cache-Control: public,max-age=0,must-revalidate');	header('Expires: '.gmdate('D, d M Y H:i:s',$_SERVER['REQUEST_TIME']).' GMT');	header('Last-Modified: '.gmdate('D, d M Y H:i:s',$lastModified).' GMT');	}// Pragma header removed should the server happen to set it automaticallyheader_remove('Pragma');// Content length is defined that can speed up website requests, letting client to determine file sizeheader('Content-Length: '.filesize($resource));  // Returning the file to clientreadfile($resource);// File is deleted if cache was requested to be offif(in_array('nocache',$parameters)){	unlink($resource);}?>