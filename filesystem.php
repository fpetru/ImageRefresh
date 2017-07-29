<?php
    /**
    * 
    * Remove the old images 
    * This function should be called only after most recent file has been processed
    * 
    */
   function remove_old_images($start_folder) {
        $arrayExtensions = array("jpg", "jpeg");

        if (file_exists($start_folder)) {
            $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($start_folder), 
                    RecursiveIteratorIterator::CHILD_FIRST);

            foreach ($iterator as $fileinfo) {
                if ($fileinfo->isFile()) {
                    $fullfilename = $fileinfo->getPathname();
                    $extension = (false === $pos = strrpos($fullfilename, '.')) ? '' : substr($fullfilename, $pos + 1);
                    if (in_array($extension, $arrayExtensions)) {
                        write_log(sprintf('Remove file found: %s', $fullfilename));
                        unlink($fullfilename);
                    }
                }
            }
        }        
    }    

    function get_unique_access() {
        $has_access = false;    

        $fp = fopen("./config/lock.txt", "r+");
        if (!flock($fp, LOCK_EX | LOCK_NB, $wouldblock)) {
            if ($wouldblock) {
                // echo " -> another process holds the lock";
            }
            else {
                // echo "-> couldn't lock for another reason, e.g. no such file - file created";
            }
        }
        else {
            // echo " -> lock obtained";
            $has_access = true;
        }

        return array('success' => $has_access, 'lock' => $fp);
    } 

    function release_unique_access($fp) {
        if ($fp) {
            // echo " -> lock release";
            fflush($fp);            // flush output before releasing the lock
            flock($fp, LOCK_UN);    // release the lock
            fclose($fp);        
        }
    }

    function remove_old_file($file_index, $default_file_name) {
        $file_to_remove = str_replace('.jpg', '.' . $file_index . '.jpg', $default_file_name);
        if (file_exists($file_to_remove)) {
            unlink($file_to_remove);
        }
    }

    function get_new_filename($imagefile, $index) {
        return str_replace('.jpg', '.' . $index . '.jpg', $imagefile);
    }

    function move_recent_file($start_folder, $default_file_name, $new_file_index, $last_modified) {
        $mostRecentFilePath = "";
        $mostRecentFileMTime = $last_modified;
        $new_file = "";
        $arrayExtensions = array("jpg", "jpeg");

        if (file_exists($start_folder)) {
            $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($start_folder), 
                    RecursiveIteratorIterator::CHILD_FIRST);

            foreach ($iterator as $fileinfo) {
                if ($fileinfo->isFile()) {
                    $fullfilename = $fileinfo->getPathname();
                    $extension = (false === $pos = strrpos($fullfilename, '.')) ? '' : substr($fullfilename, $pos + 1);
                    if (in_array($extension, $arrayExtensions)) {
                        if ($fileinfo->getMTime() > $mostRecentFileMTime) {
                            $mostRecentFileMTime = $fileinfo->getMTime();
                            $mostRecentFilePath = $fullfilename;
                        }
                    }
                }
            }

            if ($mostRecentFilePath <> "") {
                if (resize_copy_image($mostRecentFilePath, $default_file_name, 800, 80)) {
                    $new_file = get_new_filename($default_file_name, $new_file_index);
                    if (copy($default_file_name, $new_file)) {
                        write_log(sprintf('New file was processed: %s - Copied to: %s', $mostRecentFilePath, $new_file));
                        unlink($mostRecentFilePath);
                    }
                    else {
                        write_log(sprintf('File found: %s - Failed to copy new index file: %s', $mostRecentFilePath, $new_file));
                    }
                }
                else {
                    write_log(sprintf('File found: %s - Failed to resize / copy to a new location: %s', $mostRecentFilePath, $default_file_name));
                }
            }

            return array('success' => $mostRecentFilePath != "", 
                         'original_file'=> $mostRecentFilePath, 
                         'new_file'=> $new_file,
                         'modified_time'=> $mostRecentFileMTime);
        }        
    }

    /**
    * 
    * Try first to resize - if not successful, just copy the file.
    * 
    */
    function resize_copy_image($pic, $newpic, $setwidth, $quality = 80) {
        $result = resize_image($pic, $newpic, $setwidth, $quality); 
        if ($result === false)
            $result = copy($pic, $newpic);
        
        return $result;
    }

    function write_log($text) {
        if (!file_exists('./logs')) {
            mkdir('./logs', 0777, true);
        }
        
        $log  = date("F j, Y, g:i a"). ' - ' . $_SERVER['REMOTE_ADDR'].' - '. $text . PHP_EOL;
        file_put_contents('./logs/log_'.date("j.n.Y").'.txt', $log, FILE_APPEND);
    }

    function write_php_ini($array, $file) {
        $res = array();
        foreach($array as $key => $val) {
            if(is_array($val)) {
                $res[] = "[$key]";
                foreach($val as $skey => $sval) $res[] = "$skey = ".(is_numeric($sval) ? $sval : '"'.$sval.'"');
            }
            else $res[] = "$key = ".(is_numeric($val) ? $val : '"'.$val.'"');
        }
        safefilerewrite($file, implode("\r\n", $res));
    }

    function safefilerewrite($fileName, $dataToSave) {    
        if ($fp = fopen($fileName, 'w'))
        {
            $startTime = microtime(TRUE);
            do {            
                $canWrite = flock($fp, LOCK_EX | LOCK_NB);
                
                // If lock not obtained sleep for 0 - 100 milliseconds, to avoid collision and CPU load
                if (!$canWrite) 
                    usleep(round(rand(0, 100)*1000));
            } while ((!$canWrite)
                    and ((microtime(TRUE)-$startTime) < 5));

            //file was locked so now we can store information
            if ($canWrite) {            
                fwrite($fp, $dataToSave);
                flock($fp, LOCK_UN);
            }

            fclose($fp);
        }
    }

    function resize_image($pic, $newpic, $setwidth, $quality = 80) 
    {    
        ob_start();     

        $im1=ImageCreateFromJPEG($pic); 
        $info = @getimagesize($pic); 
        
        $width = $info[0]; 
         
        $w2=ImageSx($im1); 
        $h2=ImageSy($im1); 
        $w1 = ($setwidth <= $info[0]) ? $setwidth : $info[0]  ; 
         
        $h1=floor($h2*($w1/$w2)); 
        $im2=imagecreatetruecolor($w1,$h1); 
         
        imagecopyresampled ($im2,$im1,0,0,0,0,$w1,$h1,$w2,$h2); 
        $path=addslashes($newpic); 
        $returnValue = ImageJPEG($im2,$path,$quality); 
        ImageDestroy($im1); 
        ImageDestroy($im2); 

        ob_get_clean(); 
        return $returnValue;
    } 

    function main() {
        $ini_array = parse_ini_file("./config/camera.ini");
        $time_elapsed = 0;
        $file_display = "";
        $status = 0; // successs

        $access = get_unique_access();
        if ($access['success'] === true) {
            $current_time = time();
            if ($current_time - $ini_array['last_reading'] > $ini_array['refresh_time']) {
                $move_result = move_recent_file($ini_array['camera_dump_folder'], 
                                                $ini_array['load_first'], 
                                                $ini_array['last_index'] + 1,
                                                $ini_array['last_modified']);

                if ($move_result['success'] === true)
                {
                    remove_old_file($ini_array['last_index'], $ini_array['load_first']);
                    
                    $ini_array['last_reading'] = time();
                    $ini_array['last_processed'] = $move_result['original_file'];
                    $ini_array['last_index'] = $ini_array['last_index'] + 1;
                    $ini_array['last_modified'] = $move_result['modified_time'];
                    $file_display = $move_result['new_file'];

                    write_php_ini($ini_array, "./config/camera.ini");
                }
                else
                {
                    $file_display = $ini_array['camera_not_working'];
                    $status = 2; // could not find a recent file
                }

                remove_old_images($ini_array['camera_dump_folder']);                    
            }
            else {
                $time_elapsed = $current_time - $ini_array['last_reading'];
                $file_display = get_new_filename($ini_array['load_first'], $ini_array['last_index']);
                $status = 1; // No file processing was made. It's an early request. We still display the last processed file.
            }

            release_unique_access($access['lock']);
        }
        else
        {
            $status = 3; // system error; could not access lock file
        }

        return array('status' => $status,
                     'file_display' => $file_display, 
                     'time_elapsed'=> $time_elapsed);        
    }
?>    