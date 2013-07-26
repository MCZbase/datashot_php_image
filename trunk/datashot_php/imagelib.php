<?php

/**
* imagelib.php
*
* Supporting functions for image delivery.
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

/**
 * Create an image containing the text of an error message.
 * 
 * @param string $message
 * @param int $height
 * @param int $width
 * @return image containing error message text.
 */
function createErrorMessageImage($message, $height=100, $width=300) { 
	
	$image = imagecreatetruecolor($width, $height);
	$backgroundcolor = imagecolorallocate($image, 90,20,10);
	$textcolor = imagecolorallocate($image, 255, 255, 255);
	
	imagefilledrectangle($image, 0, 0, $width, $height, $backgroundcolor);
	imagestring($image, 1, 5, 5, $message, $textcolor);
	
	return $image;
}

/**
 * Create an image object from a filename, or an image object
 * containing an error message.
 * 
 * @param string $filename
 * @return image found at $filename or an image containing an error
 * message if the file was not found or was not readable.
 */
function createImageFromFile($filename) {
    if (file_exists($filename) && is_readable($filename)) { 
		$imageinfo = getimagesize($filename);
		switch($imageinfo["mime"]){
			case "image/jpeg":
				$image = imagecreatefromjpeg($filename); //jpeg file
				break;
			case "image/png":
				$image = imagecreatefrompng($filename); //png file
				break;
			default:
        	    $image = createErrorMessageImage("Unsupported mime type"); 
			    break;
		}
     } else {
        $image = createErrorMessageImage("Unable to read [$filename]"); 
     }     
	return $image;
}

/**
 *  Create a jpeg image containing the text of the error message and
 *  return it, with a content-type header to the browser.
 *
 * @param string $message
 */
function deliverErrorMessageImage($message) {
	$image = createErrorMessageImage($message);
	deliverAsJpeg($image);
} 

/**
 *  Deliver the provided image, with a content-type header to the browser 
 *  then destroy the image.
 *
 * @param imageresource $image the image to deliver, assumed to be a jpeg.
 */
function deliverAsJpeg($image) { 
	header("Content-type: image/jpeg");
	imagejpeg($image);
	imagedestroy($image);
}

/**
 * Stream the file at the provided path directly to the browser, with a 
 * content-type header appended.
 * 
 * @param string $filename the filename with path do deliver, assumed to be a jpeg
 */
function deliverFileDirect($filename) { 
	if(file_exists($filename) && is_readable($filename)) {
		header("Content-type: image/jpeg");
		ob_clean();
		flush();
		readfile($filename);
	} else { 
		deliverErrorMessageImage("Unable to read [$filename]");
	}
}

/**
 * Stream the file at the provided path directly to the browser, with a 
 * content-type header and additional headers indicating that the file is 
 * to be downloaded appended.
 * 
 * @param string $filename the filename with path do deliver, assumed to be a jpeg
 * @param string $deliveryname the name that the user will be provided for the downloaded file
 */
function deliverFileForDownload($filename, $deliveryname) { 
	if(file_exists($filename) && is_readable($filename)) {
		$imageinfo = getimagesize($filename);
       // provide headers for download of the image file.
		header('Content-Description: File Transfer');
		header('Content-Type: ' . $imageinfo['mime']);
		header("Content-Disposition: attachment; filename=$deliveryname");
		header('Content-Transfer-Encoding: binary');
		header('Expires: 0');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Pragma: public');
		header('Content-Length: ' . filesize($filename));
		ob_clean();
		flush();
		readfile($filename);
	} else  {
		deliverErrorMessageImage("Unable to read [$filename]");
	}	
}


?>