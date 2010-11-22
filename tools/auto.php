<?php
# Start
require('class.php');
$site = new site();

echo "Checking Avisynth............. ";
if(!$site->avisynth()){echo 'Avisynth not found. Hit Enter to ignore. ';p();}else{
echo "done\n";}

echo "Reading files................. ";
$files = $site->read($site->in());
if(!$files){echo 'error! No input files found ';pd();}
echo "done\n";

if(count($files['video'])>0){
echo 'Checking for video files...... done';
	echo $br."\n";
	echo "I have found at least one video file in the dropped files.\n";
	echo "Enter \"y\" (or hit Enter) to rip audio from those video files, or\n";
	echo "enter \"n\" to ignore them and continue with audio+pic business as usual...\n";
	echo "\n";
	echo "Rip audio from discovered video files [y]? ";
	$y = fgetc(STDIN);
	if($y!='n'){
		echo $br;
		$site->rip($files['video']);
		die;
	}else{
		echo $br;
		unset($files['video']);
	}
}

echo "Validating files.............. ";
$files = $site->validate($files);
if(!$files){echo 'error! No supported files found ';pd();}
echo "done\n";

echo "Counting files................ ";
$files = $site->count($files);
if(!$files){echo 'error! Please drop 1 image & 1 audio file ';pd();}
echo "done\n";

echo "Encoding......................$br";
$site->work($files);
echo "Done! ";