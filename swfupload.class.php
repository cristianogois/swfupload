<?php 
/** 
 *  
 *  Class Upload 
 * 
 * This class has the purpose of ease and secure the upload of files for SWFUpload and other applications. 
 * 
 * This script is based on the upload_save.php code of SWFUpload widget 
 * 
 * @author    Cristiano Gois <cristianogois@gmail.com> 
 * @version   1.8 
 * @copyright Freeware 
 * @package   Upload 
 * @since     03/2011 
**/ 

class Upload{ 

  private $file_name; 
   
  private $tmp_name; 
   
  private $file_extension;  
   
  private $succeed_files_track; 
   
  private $fail_files_track; 
   
  private $max_file_size_in_bytes; 
   
  private $save_path; 
   
  private $upload_name; 
   
  private $file_perm; 
   
  private $valid_chars_regex; 
   
  private $extension_whitelist; 
   
  private $errors; 
   
  private $errtype; // returning type of method formatErrorMsg. // 0: String; 1: JSON 
   
  const MAX_FILENAME_LENGTH = 260;  
   
   
  /** 
   * Constructor 
   * 
   * @access  public 
  **/ 
  public function __construct($filename="", $save_path="./", $file_ext="", $upload_name="Filedata"){ 
   
      $path_info = pathinfo($_FILES[$upload_name]['name']); 
       
      $this->file_name              = ((!isset($filename) || trim($filename)==='')) ?  
                                      basename($_FILES[$upload_name]['name'], '.'.$path_info["extension"]) : $filename;       
    $this->tmp_name               = $_FILES[$upload_name]["tmp_name"];     
    $this->file_extension         = (empty($file_ext)) ? $path_info["extension"] : $file_ext;    
    $this->succeed_files_track    = array(); 
    $this->fail_files_track       = array(); 
    $this->save_path               = $save_path; 
       $this->upload_name            = $upload_name;               
       $this->file_perm              = 0755; // don't use single quotes or double quotes       
       $this->max_file_size_in_bytes = 10485760; // 10MB in bytes 
       $this->valid_chars_regex      = '.A-Z0-9_ !@#$%^&()+={}\[\]\',~`-'; // Characters allowed in the file name (in a Regular Expression format) 
       $this->extension_whitelist    = array("jpg", "png", "jpeg", "pdf"); // Allowed file extensions 
       $this->errors                 = array(   0=>"There is no error, the file uploaded with success", 
                                             1=>"The uploaded file exceeds the upload_max_filesize directive in php.ini", 
                                               2=>"The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form",
                                               3=>"The uploaded file was only partially uploaded", 
                                               4=>"No file was uploaded", 
                                               6=>"Missing a temporary folder" 
                                           );     
    $this->errtype = 0; // 0: String; 1: JSON          
    } 
    // override magic method to retrieve properties 
  public function __get($field){ 
    if($field == 'filename'){ 
        return $this->file_name; 
    }else if($field == 'fullname'){ 
        return $this->file_name.".".$this->file_extension; 
    }else{ 
      return $this->$field; 
    } 
  } 
  // override magic method to set properties 
  public function __set($field, $value){     
    $this->$field = $value;   
  } 
   
  public function getPostMaxSize(){ 
      return trim(ini_get('post_max_size'));   
  }  
   
  /* 
   * Create directory structure 
   */   
   
  public function createDirectoryStructure(){       
      $path = $this->save_path;      
       
      return is_dir($path) || @mkdir($path, (int)$this->file_perm, TRUE);     
  } 
   
   
  /* 
   * Save file 
   * uses createDiretoryStructure(), getPostMaxSize() 
   * @todo  rearrange the conditionals to avoid the 'return $bool' repetition 
   */ 
   
  public function save($force=false, $overwrite=false){       
    $upload_name = $this->upload_name; 
    $file_name = $this->file_name; 
    $file_ext =    $this->file_extension; 
    $save_path = $this->save_path; 
    $full_name = $this->fullname; 
    $file_path = $save_path."/".$full_name; 
    $bool = false;     
     
    $unit = strtoupper(substr($this->getPostMaxSize(), -1)); 
    $multiplier = ($unit == 'M' ? 1048576 : ($unit == 'K' ? 1024 : ($unit == 'G' ? 1073741824 : 1))); 

    if((int)$_SERVER['CONTENT_LENGTH'] > $multiplier*(int)$this->getPostMaxSize() && $this->getPostMaxSize()) { 
     //  header("HTTP/1.1 500 Internal Server Error"); // This will trigger an uploadError event in SWFUpload 
       $this->formatErrorMsg(2,  "POST exceeded maximum allowed size"); 
       return $bool;        
    } 
    // Validate the upload 
    if(!isset($_FILES[$upload_name])){ 
        print $this->formatErrorMsg (4, "No upload found in \$_FILES for $upload_name"); 
        return $bool;         
    }else if(isset($_FILES[$upload_name]["error"]) && $_FILES[$upload_name]["error"] != 0){ 
        $this->formatErrorMsg($_FILES[$upload_name]["error"], $this->errors[$_FILES[$upload_name]["error"]]);                 
        return $bool;         
    }else if(!isset($_FILES[$upload_name]["tmp_name"]) || !@is_uploaded_file($_FILES[$upload_name]["tmp_name"])){ 
        $this->formatErrorMsg(7, "Upload failed is_uploaded_file test"); 
        return $bool;         
    }else if(!isset($_FILES[$upload_name]['name'])){ 
        $this->formatErrorMsg(8, "File has no name"); 
        return $bool;         
    } 
     
    clearstatcache(); 
    // Validate the file size (Warning: the largest files supported by this code is 2GB) 
    $file_size = @filesize($_FILES[$upload_name]["tmp_name"]); 
    if (!$file_size || $file_size > $this->max_file_size_in_bytes) { 
        //header("HTTP/1.1 500 Internal Server Error"); 
        $this->formatErrorMsg (2, "File exceeds the maximum allowed size"); 
        return $bool;         
    } 
     
    if ($file_size <= 0) { 
        $this->formatErrorMsg(9, "File size outside allowed lower bound"); 
        return $bool;         
    }     
     
     // Validate file extension 
       $is_valid_extension = false; 
    foreach ($this->extension_whitelist as $extension) { 
        if (strcasecmp($file_ext, $extension) == 0) { 
            $is_valid_extension = true; 
            break; 
        } 
    } 
     
    if (!$is_valid_extension) { 
        $this->formatErrorMsg(10, "Invalid file extension: $file_ext"); 
        return $bool;         
    }    
     
    // Validate file name (for our purposes we'll just remove invalid characters) 
    $file_name = preg_replace('/[^'.$this->valid_chars_regex.']|\.+$/i', "", $file_name);     
    if (strlen($file_name) == 0 || strlen($file_name) > self::MAX_FILENAME_LENGTH) { 
        $this->formatErrorMsg(11, "Invalid file name");  
          return $bool;         
    } 
     
    // Validate that we won't overwrite an existing file 
    if (file_exists($file_path) && !$overwrite) {         
        $this->formatErrorMsg (12, "File with this name already exists"); 
        return $bool;         
    }   

    if($force){         
        if(!$this->createDirectoryStructure()){ 
            $this->formatErrorMsg (13, "Destination folder does not exist and could not be created"); 
            return $bool;             
        }     
    } 
   
    if(!@move_uploaded_file($_FILES[$upload_name]["tmp_name"], $file_path)){ 
        $this->formatErrorMsg (14, "File could not be saved"); 
        return $bool;         
    }else{ 
      move_uploaded_file($file_path, "./".$full_name);       
      $bool = true;       
    } 

    return $bool; 
  } 
   
  /** 
   * Error handling 
   *  
   * @access  public 
  **/ 
  public function formatErrorMsg($errno, $errstr){ 
   
      if($this->errtype == 0){ //plain text 
          print $errstr;   
      }else if($this->errtype == 1){ // JSON Format 
          print "{ \"errno\": $errno, \"errmsg\": \"$errstr\" }";           
      }   
  } 

   /** 
       * Deconstructor 
       *   
       * @access  public 
   **/ 
  public function __destruct() {} 
} 

?>
