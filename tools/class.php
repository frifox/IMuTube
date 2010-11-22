<?php
class site{
	function in(){
		# get
		$in = file_get_contents('in');unlink('in');
		$in = mb_convert_encoding($in, 'UTF-8', 'UCS-2LE');
		$in = explode("\r\n",$in);
		
		# unset empty & return
		if(count($in)>1){
			foreach($in as $key => $value) if(empty($value)){unset($in[$key]);}else{$in[$key]=str_replace('"','',$value);};
			return array_values($in);
		}else{
			return false;
		}
	}
	function avisynth(){
		# vars
		$content = 'BlankClip(length=1, width=32, height=32)';
		$avs = "avs.avs";
		$mp4 = "avs.mp4";
		
		# write avs
		$fh = fopen($avs, 'w');
		fwrite($fh, $content);
		fclose($fh);
		
		# test
		exec('tools\x264 '.$avs.' --output '.$mp4.' 2> NUL');
		exec('tools\mediainfo --Inform=Video;%Width%x%Height% '.$mp4, $i);
		
		# clean
		$i = implode($i,"\n");
		
		unlink($avs);
		unlink($mp4);
		
		# decide
		if($i == '32x32'){
			return true;
		}else{
			return false;
		}
	}
	function read($x=false){
		if(!$x){
			return false;
		}else{
			foreach($x as $key => $file){
				$tools = new tools();
				
				# file path info
				$file = pathinfo($x[$key]);
				$file = array(
					'id' => '!'.md5($f['source'].$file['extension']),
					'source' => $x[$key],
					'path' => $file['dirname'],
					'filename' => $file['filename'],
					'ext' => $file['extension'],
					'filename_ext' => $file['filename'].'.'.$file['extension']
				);
				
				# if unicode - use hardlink
				//if(false){ /// NOTE: AviSynth hates symbols in filenames, so use "if(false)" to force hardlinks on all files
				if(mb_check_encoding($file['source'], 'ASCII')){
					$file['utf']=false;
					$file['link'] = $file['source'];
				}else{
					$file['utf']=true;
					#prep & run
					$file['link'] = $file['path'].'\\'.$file['id'].'.'.$file['ext'];
					unlink($file['link']);
					
					
					$cmd = 'fsutil hardlink create "'.$file['link'].'" "'.$file['source'].'"';
					$cmd = $tools->uexec($cmd);
					
					# die if failed
					if(!preg_match('/Hardlink created.*/',$cmd)){
						if(preg_match('/Usage : fsutil hardlink create.*/',$cmd) OR
						   preg_match('/Error:  The system cannot find the file specified.*/',$cmd) OR
						   preg_match('/Error:  Cannot create a file when that file already exists.*/',$cmd)){
							echo "Error! Can't create hardlink.\n\nTry renaming the source files only using a-z & 0-9\n\n";
							pd('Press any key to continue . . . ');
						}else{
							echo "Error! Can't create hardlink.\n\nError message:\n$link\n";
							pd('Press any key to continue . . . ');
						}
					}
					unset($cmd);
				}
				
				# get info
				$cmd = 'tools\mediainfo -f --Output=XML "'.$file['link'].'"';
				if($file['utf']){
					$xml = $tools->uexec($cmd);
				}else{
					exec($cmd,$xml);
					$xml = implode($xml,"\n");
					$xml = mb_convert_encoding($xml, 'UTF-8', 'ASCII');
					$xml = preg_replace("/[^(\x20-\x7F)]*/", '', $xml);
				}
				
				$xml = new SimpleXMLElement($xml);
				$xml = $xml->File;
				
				$xml->id = $file['id'];
				$xml->source = $file['source'];
				$xml->path = $file['path'];
				$xml->filename = $file['filename'];
				$xml->ext = $file['ext'];
				$xml->filename_ext = $file['filename_ext'];
				$xml->link = $file['link'];
				if($file['utf']) $xml->utf = $file['utf'];
				unset($file);
				
				# save
				$type = strval($xml->track[1]->Kind_of_stream[0]);
				$type = strtolower($type);
				$y[$type][]=$xml;
				unset($xml,$type);
			}
			return $y;
		}
	}
	function validate($x=false){
		foreach($x as $type => $files){
			# keep audio/video only
			if($type!='audio' and $type!='image' and $type!='video'){
				unset($x[$type]);
				if($file->utf){
					unlink($file->link);
				}
			}
		}
		return $x;
	}
	function count($x=false){
		if(count($x['audio'])==1 && count($x['image'])==1){
			return $x;
		}else{
			foreach($x as $type){
				foreach($type as $file){
					if($file->utf){
						unlink($file->link);
					}
				}
			}
			return false;
		}
	}
	function work($x=false){
		$tools = new tools();
		$duration = intval($x['audio'][0]->track[0]->Duration[0]);
		
		# prep video
		foreach($x['image'] as $file){
			# if failed to get duration, try to repair & get duration again
			if($duration<1){
				$cmd = 'tools\vbrfix --removedId3v1 --removeId3v2 --removeUnknown --removeLame "'.$x['audio'][0]->link.'" "'.$x['audio'][0]->id.'.mp3"';
				exec($cmd);
				$cmd = 'tools\mediainfo --Inform=General;%Duration% "'.$x['audio'][0]->id.'.mp3"';
				exec($cmd,$duration);
				unlink($x['audio'][0]->id.'.mp3');
				$duration = $duration[0];
			}
			# make slice
			$fps = 1;
		
			$avs = $tools->avs($file);
			$cmd = 'tools\x264 --fps '.$fps.' --frames '.($fps*10).' --keyint '.($fps*10).' --qp 20 --8x8dct --me umh --threads auto --output "'.$file->id.'.h264" "'.$avs.'"';
			exec($cmd);
			unset($cmd);
			unlink($avs);
			
			# combine slices
			$cmd = 'tools\mp4box -fps '.$fps;
			
			for($i = ceil($duration / 10000); $i>0; $i--){
				$cmd.= ' -cat "'.$file->id.'.h264"';
			}
			$cmd.= ' -new "'.$file->id.'.mp4"';
			exec($cmd);
			unset($cmd);
			unlink($file->id.'.h264');
			
			# delete link, if there was one
			if($file->utf){
				unlink($file->link);
			}
			
			# set video filename for later use
			$video = $file->id.'.mp4';
		}
		
		
		# mux
		foreach($x['audio'] as $file){
			if($file->utf){
				$cmd = 'tools\ffmpeg -i "'.$file->link.'" -itsoffset 1 -acodec copy -i "'.$video.'" -vcodec copy "'.$file->id.'.mkv" -shortest';
			}else{
				$cmd = 'tools\ffmpeg -i "'.$file->link.'" -itsoffset 1 -acodec copy -i "'.$video.'" -vcodec copy "'.$file->filename.' (uTubeHD).mkv" -shortest';
			}
			exec($cmd);
			unset($cmd);
			
			# rename if needed
			if($file->utf){
				$cmd = 'rename "'.$file->id.'.mkv" "'.$file->filename.' (uTubeHD).mkv" > NUL 2>&1';
				$fh = fopen($file->id.'.bat', "w");
				fwrite($fh, $cmd);
				fclose($fh);
				
				# if output file exists, delete it
				if(file_exists($file->filename.' (uTubeHD).mkv')){
					unlink($file->filename.' (uTubeHD).mkv');
				}
				while(file_exists($file->id.'.mkv')){
					exec($file->id.'.bat');
					if(file_exists($file->id.'.mkv')){
						echo "\nCant rename! File to be renamed is in use\nor renamed file already exists. Trying to\nunlock input file & attempt renaming agian.\n";
						sleep(5);
						exec('tools\unlocker "'.$file->id.'.mkv" /s');
					}
				}
				unlink($file->id.'.bat');
			}
			
			# delete link, if there was one
			if($file->utf){
				unlink($file->link);
			}
		}
		unlink($video);
	}
	function rip($files=false){
		# check for non-mp3 streams
		foreach($files as $file){
			$audio = intval($file->track[0]->Count_of_video_streams) + 1;
			$file->audio = $audio;
			$audio = strval($file->track[$audio]->Format);
			if($audio!='MPEG Audio'){
				$convert = true;
			}
		}
		foreach($files as $file){
			if($convert){
				echo "There is at least one non-mp3 audio stream in found video files.\n";
				echo "Enter \"y\" (or hit Enter) to auto-convert all non-mp3 audio streams to mp3, or\n";
				echo "enter \"n\" to keep audio streams as they are without any conversion...\n";
				echo "\n";
				echo "Auto-convert to mp3 with minimal quality loss [y]? ";
				$y = fgets(STDIN);
				prd($y);
				if($y!='n'){
					echo "$br RIP w/ AUTOCONVERT. Not implemented yet, wait for next vesion :)";
					die;
					//////////////////////////////////////////////
					$format = strval($file->track[intval($file->audio)]->Format[0]);
					prd($format);
				}else{
					echo "$br RIP w/out AUTOCONVERT. Not implemented yet, wait for next vesion :)";
					die;
					//////////////////////////////////////////////
					$format = strval($file->track[intval($file->audio)]->Format[0]);
					prd($format);
				}
			}else{
				if($file->utf){
					# if VBR - rip & fix, else - just rip
					if(strval($file->track[intval($file->audio)]->Bit_rate_mode[0])=='VBR'){
						exec('tools\ffmpeg -i "'.$file->link.'" -vn -acodec copy "'.$file->id.'_rename.mp3"');
						exec('tools\vbrfix "'.$file->id.'_rename.mp3" "'.$file->id.'.mp3"');
						unlink($file->id.'_rename.mp3');
					}else{
						exec('tools\ffmpeg -i "'.$file->link.'" -vn -acodec copy "'.$file->id.'.mp3"');
					}
					exec($cmd);
					unset($cmd);
					
					# rename
					$cmd = 'rename "'.$file->id.'.mp3" "'.$file->filename.' (RIP).mp3" > NUL 2>&1';
					$fh = fopen($file->id.'.bat', "w");
					fwrite($fh, $cmd);
					fclose($fh);
					
					# if output file exists, delete it
					if(file_exists($file->filename.' (RIP).mp3')){
						unlink($file->filename.' (RIP).mp3');
					}
					while(file_exists($file->id.'.mp3')){
						exec($file->id.'.bat');
						if(file_exists($file->id.'.mp3')){
							echo "\nCant rename! File to be renamed is in use\nor renamed file already exists. Trying to\nunlock input file & attempt renaming agian.\n";
							sleep(5);
							exec('tools\unlocker "'.$file->id.'.mp3" /s');
						}
					}
					unlink($file->id.'.bat');
					
				}else{
					# if VBR - rip & fix, else - just rip
					if(strval($file->track[intval($file->audio)]->Bit_rate_mode[0])=='VBR'){
						exec('tools\ffmpeg -i "'.$file->link.'" -vn -acodec copy "'.$file->id.'.mp3"');
						exec('tools\vbrfix "'.$file->id.'.mp3" "'.$file->filename.' (RIP).mp3"');
						unlink($file->id.'.mp3');
					}else{
						exec('tools\ffmpeg -i "'.$file->link.'" -vn -acodec copy "'.$file->filename.' (RIP).mp3"');
					}
				}
			}
		}
	}
}
class tools{
	function uexec($x=null){
		if($x){
			# set
			$bat = 'tmp.bat';
			
			# pre-clean
			unlink($bat);
			
			# write
			$fh = fopen($bat, 'w');
			fwrite($fh, $x);
			fclose($fh);
			
			# execute (WinXP force closes w/ chcp = can't use chcp & loose unicode support)
			if(preg_match('/Version 5.1/',exec('ver'))){
				exec("cmd /u /c \"@echo off && $bat>out\"");
			}else{
				exec("chcp 65001>NUL && cmd /u /c \"@echo off && $bat>out\"");
			}
			unlink($bat);
			
			# retrieve & clean
			$out = file_get_contents('out'); unlink('out');
			$out = mb_convert_encoding($out, 'UTF-8', 'ASCII');
			
			# return
			return $out;
		}else{
			return false;
		}
	}
	function avs($file=null){
		
		# resize?
		$res = intval($file->track[1]->Width[0]).'x'.intval($file->track[1]->Height[0]);
		
		if(
			$res!='1280x720' &&
			$res!='960x720' &&
			(intval($file->track[1]->Width[0])>1280 or intval($file->track[1]->Height[0])>720) 
		){
			echo "Your source picture is not 1280x720 (its $res)\n";
			echo "and thats not recommended. Youtube HD works best with 1280x720.\n";
			echo "\n";
			echo "Enter \"y\" (or hit Enter) to resize to 1280x720 on the fly, or\n";
			echo "enter \"n\" if you still insist on continuing without resizing...\n";
			echo "\n";
			echo "Auto resize to 1280x720 [y]? ";
			$y = fgetc(STDIN);
			if($y!='n'){
				$source = array(
					'width' => intval($file->track[1]->Width[0]),
					'height' => intval($file->track[1]->Height[0]),
					'ratio' => intval($file->track[1]->Width[0])/intval($file->track[1]->Height[0])
				);
				$base = array(
					'width' => 1280,
					'height' => 720,
					'ratio' => 1280/720,
				);
				
				
				// SCALE (will be overwritte by RESIZE next. need to add an option for user to choose)
				if($source['width']>$base['width'] or $source['height']>$base['height']){
					if($source['ratio']<$base['ratio']){
						$new = array(
							'width' => floore($base['height']*$source['ratio']),
							'height' => $base['height'],
						);
						//prd($new);
					}else{
						$new = array(
							'width' => $base['width'],
							'height' => floore($base['width']/$source['ratio']),
						);
					}
				}
				$file->track[1]->Width[0] = $new['width'];
				$file->track[1]->Height[0] = $new['height'];
				
				// RESIZE
				$file->track[1]->Width[0] = 1280;
				$file->track[1]->Height[0] = 720;
			}
		}
		// prd($file);
		
		# avs
		$fps = 1;
		$avs = 'ImageSource("'.$file->link.'", end='.($fps*15*60).', fps='.$fps.').LanczosResize('.intval($file->track[1]->Width[0]).','.intval($file->track[1]->Height[0]).').ConvertToYV12()';
		
		# write
		$avs_file = $file->id.'.avs';
		$fh = fopen($avs_file, 'w');
		fwrite($fh, $avs);
		fclose($fh);
		
		# return it
		return $avs_file;
	}
}

function prd($x){echo PHP_EOL,print_r($x,1),PHP_EOL;die;}
function p($x){echo $x;fgets(STDIN);}
function pd($x){echo $x;fgets(STDIN);die;}
function floore($x=false){return floor($x/2)*2;}
$br = "\n- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -\n";