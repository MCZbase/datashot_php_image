<?php

/**
* image.php
* 
* To deliver images for the DataShot J2EE web application 
* using PHP for image manipulation.
* 
* Copyright © 2013 President and Fellows of Harvard College
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of Version 2 of the GNU General Public License
* as published by the Free Software Foundation.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program.  If not, see <http://www.gnu.org/licenses/>.
*
* Author: Paul J. Morris
*
* $Id$
*/

// Defines datashot_connect(), returns false or a simple database abstraction 
// which can contain an OCI8 or PDO handle.
include_once("connection.php");
// Contains supporting image delivery functions.  
include_once("imagelib.php"); 

define('DEFAULT_FILE', "/opt/glassfish/defaultimage.jpg");
define('BROKEN_IMAGE',"/opt/glassfish/images/brokenimage.jpg");
define('IMAGEROOT',"/opt/glassfish/images/");

// Hardcoded default templates
define('TEMPLATE_DEFAULT',"Default template");
define('TEMPLATE_TEST_1',"Small Template 1");
define('TEMPLATE_WHOLEIMAGE',"Whole Image Only");

$width = 675;
$path = "";
$filename = DEFAULT_FILE;

// image.php?imageid=1  
// image.php?imageid=1&width=300
// image.php?imageid=1&download=true
// image.php?imageid=1&region=Barcode
// image.php?imageid=1&region=Barcode&width=100

$download = $_GET['download'];   // flag, if true, return the image with headers to trigger download
$parwidth = preg_replace('[^0-9]', '', $_GET['width']);  // requested image width 
$imageid  = preg_replace('[^0-9]', '', $_GET['imageid']);  // requested image

// database specified crop
$region = $_GET['region'];  // name of the region (PositionTemplate.REGION_ constants) within the image to return.

// user specified crop
$top  = preg_replace('[^0-9]', '', $_GET['top']); // top of a cropped area from the image or a region
$left  = preg_replace('[^0-9]', '', $_GET['left']);  // left of a cropped from the image or a region
$width  = preg_replace('[^0-9]', '', $_GET['cwidth']);  // width of a cropped from the image or a region
$height  = preg_replace('[^0-9]', '', $_GET['height']);  // height of a cropped from the image or a region

if ($imageid==null) { 
    deliverErrorMessageImage("No image specified.");
    die;
}

$rescale = false;  // images is to be rescaled or not
$downloadLink = false;  // render image with cache and content headers for download as file rather than to render directly
if ($download != null && strlen($download) > 0) {
	if (strtolower($download)=="true") {
		$downloadLink = true;
	}
}

// test for an oddly small width request, increase to specified minimum pixel width if below that.
define('MIN_WIDTH',10);
if ($parwidth != null && $parwidth > 0) {
	$width = $parwidth;
	if ($width < MIN_WIDTH) {
		$width = MIN_WIDTH;
	}
	$rescale = true;
}

$connection = datashot_connect();
// TODO: Refactor to allow either generic PDO (for MariaDB) or the vendor specific OCI8 extension.
if ($connection) { 
    // Look up the image filename, path, and templateid
    $sql = "SELECT imageid, filename, path, templateid FROM Image i WHERE i.imageId = :imageid ";
    if ($connection->type==TYPE_ORACLE) { 
    	$statement = oci_parse($connection->oracle_connection, $sql);
    	oci_bind_by_name($statement,":imageid",$imageid);
    	oci_execute($statement);
    	if ($row = oci_fetch_array($statement,OCI_NUM)) {
    		$filename = $row[1];
    		$path = $row[2];
    		$templateid = $row[3];
    	} else {
    		deliverErrorMessageImage("ImageID [$imageid] not found.");
    		die;
    	}    	
    	oci_free_statement($statement);
    } elseif ($connection->type==TYPE_PDO) { 
    	$statement = $connection->dbo_connection->prepare($sql);
	    $statement->bindParam(":imageid",$imageid);
    	$statement->execute();
    	if ($row = $statement->fetch(PDO::FETCH_NUM)) { 
    		$filename = $row[1];
	    	$path = $row[2];
    		$templateid = $row[3];
    	} else { 
        	deliverErrorMessageImage("ImageID [$imageid] not found.");
    	    die;
	    }
    }
    
    // Location of the image file on the local filesystem 
    $systemfilename = IMAGEROOT . "$path/$filename";
    // Name of the file to be provided to the client in response to a download request.
    $downloadfilename = "$filename";
    if(file_exists($systemfilename) && is_readable($systemfilename)) {
    	$imageinfo = getimagesize($systemfilename);  
    	$imageSizeX = $imageinfo[0];  	
    	$imageSizeY = $imageinfo[1];  	
    }
    
    if ($region==null || strlen($region) == 0) { 
    	$region = "Full";
    }
    
    // If a region was specified, look up its coordinates from the template for this image 
	switch ($region) { 
		case "Barcode": 
 	   		$isRegion = true;
            $sql = "SELECT templateid, template_name, imageSizeX, imageSizeY, barcodePositionX, barcodePositionY, barcodeSizeX, barcodeSizeY from Template t where t.templateid = :templateid ";
			break;
		case "Specimen": 
 	   		$isRegion = true;
            $sql = "SELECT templateid, template_name, imageSizeX, imageSizeY, specimenPositionX, specimenPositionY, specimenSizeX, specimenSizeY from Template t where t.templateid = :templateid ";
			break;
		case "CurrentIDLabel": 
 	   		$isRegion = true;
            $sql = "SELECT templateid, template_name, imageSizeX, imageSizeY, textPositionX, textPositionY, textSizeX, textSizeY from Template t where t.templateid = :templateid ";
			break;
		case "PinLabels": 
 	   		$isRegion = true;
            $sql = "SELECT templateid, template_name, imageSizeX, imageSizeY, labelPositionX, labelPositionY, labelSizeX, labelSizeY from Template t where t.templateid = :templateid ";
			break;
		case "LooseUTLabels": 
 	   		$isRegion = true;
            $sql = "SELECT templateid, template_name, imageSizeX, imageSizeY, utlabelPositionX, utlabelPositionY, utlabelSizeX, utlabelSizeY from Template t where t.templateid = :templateid ";
			break;
		case "Full": 
		default:
 	   		$isRegion = false;
			$region = "Full";
            $sql = "SELECT templateid, template_name, imageSizeX, imageSizeY from Template t where t.templateid = :templateid ";
	}
	
	// Find the coordinates associated with the relevant template.
	switch ($templateid) {
		case TEMPLATE_WHOLEIMAGE: 
			$region = "Full";
			$positionX = 0;
			$positionY = 0;
			$sizeX = $imageSizeX;
			$sizeY = $imageSizeY;
		    break;
		case TEMPLATE_DEFAULT:
			// hardcoded template
			$imageSizeX = 2848;
			$imageSizeY = 4272;
			switch ($region) {
				case "Barcode":
					$positionX = 2490;
					$positionY = 90;
					$sizeX = 300;
					$sizeY = 300;
					break;
				case "Specimen":
					$positionX = 0;
					$positionY = 2200;
					$sizeX = 2048;
					$sizeY = 1900;
					break;
				case "CurrentIDLabel":
					$positionX = 110;
					$positionY = 105;
					$sizeX = 1720;
					$sizeY = 700;
					break;
				case "PinLabels":
					$positionX = 1300;
					$positionY = 700;
					$sizeX = 1500;
					$sizeY = 1300;
					break;
				case "LooseUTLabels":
					$positionX = 0;
					$positionY = 850;
					$sizeX = 1500;
					$sizeY = 1161;
					break;
				case "Full":
				default:
					$region = "Full";
					$positionX = 0;
					$positionY = 0;
					$sizeX = $imageSizeX;
					$sizeY = $imageSizeY;
			}
			break;
		case TEMPLATE_TEST_1:
			// hardcoded template
			$imageSizeX = 2848;
			$imageSizeY = 4272;
			switch ($region) {
				case "Barcode":
					$positionX = 2280;
					$positionY = 0;
					$sizeX = 550;
					$sizeY = 310;
					break;
				case "Specimen":
					$positionX = 0;
					$positionY = 2140;
					$sizeX = 2847;
					$sizeY = 2130;
					break;
				case "CurrentIDLabel":
					$positionX = 110;
					$positionY = 105;
					$sizeX = 1720;
					$sizeY = 700;
					break;
				case "PinLabels":
					$positionX = 1500;
					$positionY = 780;
					$sizeX = 1348;
					$sizeY = 1360;
					break;
				case "LooseUTLabels":
					$positionX = 0;
					$positionY = 780;
					$sizeX = 1560;
					$sizeY = 1360;
					break;
				case "Full":
				default:
					$region = "Full";
					$positionX = 0;
					$positionY = 0;
					$sizeX = $imageSizeX;
					$sizeY = $imageSizeY;
			}
			break;
		default:
			// lookup the coordinates of of the templated region
			if ($connection->type==TYPE_ORACLE) {
				$statement = oci_parse($connection->oracle_connection, $sql);
				$oci_bind_by_name($statement,":templateid", $templateid);
				oci_execute($statement);
				if ($row = oci_fetch_arrya($statement, OCI_NUM)) {
					$imageSizeX = $row[2];
					$imageSizeY = $row[3];
					if ($region=="Full") {
						$positionX = 0;
						$positionY = 0;
						$sizeX = $imageSizeX;
						$sizeY = $imageSizeY;
					} else {
						$positionX = $row[4];
						$positionY = $row[5];
						$sizeX = $row[6];
						$sizeY = $row[7];
					}
				} else {
					deliverErrorMessageImage("Template [$templateid] not found.");
					die;
				}				
			} elseif ($connection->type==TYPE_PDO) {
				$statement = $connection->dbo_connection->prepare($sql);
				$statement->bindParam(":templateid", $templateid);
				$statement->execute();
				if ($row = $statement->fetch(PDO::FETCH_NUM)) {
					$imageSizeX = $row[2];
					$imageSizeY = $row[3];
					if ($region=="Full") {
						$positionX = 0;
						$positionY = 0;
						$sizeX = $imageSizeX;
						$sizeY = $imageSizeY;
					} else {
						$positionX = $row[4];
						$positionY = $row[5];
						$sizeX = $row[6];
						$sizeY = $row[7];
					}
				} else {
					deliverErrorMessageImage("Template [$templateid] not found.");
					die;
				}
			} 
			break;
	} // end case
	

    $crop_region_width = $sizeX;
    $crop_region_height = $sizeY;
    $source_startx =  $positionX;
    $source_starty = $positionY;

    // determine if the request is for a cropped part of the image.
    $hasUserCrop = false;
    $ctop = 0;
    $cleft = 0;
    $cwidth = 0;
    $cheight = 0;
    if ($top != null && strlen($top) > 0) {
    	if ($left != null && strlen($left) > 0) {
    		if ($width != null && strlen($width) > 0) {
    			if ($height != null && strlen($height) > 0) {
    				$hasUserCrop = true;
    				$rescale = false;
    	            $ctop = $top;
    		        $cleft = $left;
    			    $cwidth = $width;
    				$cheight = $height;
    			}
    		}
    	}
    }
    
    // determine parameters to extract a cropped region
    if ($hasUserCrop) {
    	if ($cwidth > 0 & $cheight > 0) {
    		$source_startx = $source_startx + $cleft;
    		$source_starty = $source_starty + $ctop;
    		$crop_region_width = $cwidth;
    		$crop_region_height = $cheight;
    	} else {
    		//TODO: Figure out how to set renderable to true for crop display areas.
    		// return a 1x1 pixel image
    		$crop_region_width = 1;
    		$crop_region_height = 1;
    	}
    }
    
    // determine scaling parameters 
    $target_width = $crop_region_width;
    $target_height = $crop_region_height;
    if ($rescale) {
    	$scale = $width / $crop_region_width;
    	$target_width = $target_width * $scale;
    	$target_height = $target_height * $scale;
    }        
    
    if ($downloadLink) { 
       header('Content-Disposition: attachment; filename="'.$downloadfilename.'"');
       deliverFileForDownload($systemfilename, $downloadfilename);
    } else { 
   	   if ($rescale || $hasUserCrop || $isRegion) { 
           $image = createImageFromFile($systemfilename);
           // apply rescaling or cropping
           $deliveryimage = imagecreatetruecolor($target_width, $target_height) ;
           imagecopyresized($deliveryimage, $image, 0, 0, $source_startx, $source_starty, $target_width, $target_height, $crop_region_width, $crop_region_height);
           deliverAsJpeg($deliveryimage);
   	   } else { 
   	   	   deliverFileDirect($systemfilename);
   	   }
   }

}

?>
