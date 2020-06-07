<?php
if(!defined('DS')){
	define('DS',DIRECTORY_SEPARATOR);
}
error_reporting(E_ERROR);
ini_set('display_errors', 1);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', 20000);

function EXPORT_DATABASE($host,$user,$pass,$name,$tables=false, $backup_name=false)
{ 
		
	set_time_limit(3000); $mysqli = new mysqli($host,$user,$pass,$name); $mysqli->select_db($name); $mysqli->query("SET NAMES 'utf8'");
	$queryTables = $mysqli->query('SHOW TABLES'); while($row = $queryTables->fetch_row()) { $target_tables[] = $row[0]; }	if($tables !== false) { $target_tables = array_intersect( $target_tables, $tables); } 
	$content = "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\r\nSET time_zone = \"+00:00\";\r\n\r\n\r\n/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\r\n/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\r\n/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\r\n/*!40101 SET NAMES utf8 */;\r\n--\r\n-- Database: `".$name."`\r\n--\r\n\r\n\r\n";
	foreach($target_tables as $table){
		if (empty($table)){ continue; } 
		$result	= $mysqli->query('SELECT * FROM `'.$table.'`');  	$fields_amount=$result->field_count;  $rows_num=$mysqli->affected_rows; 	$res = $mysqli->query('SHOW CREATE TABLE '.$table);	$TableMLine=$res->fetch_row(); 
		$content .= "\n\n".$TableMLine[1].";\n\n";   $TableMLine[1]=str_ireplace('CREATE TABLE `','CREATE TABLE IF NOT EXISTS `',$TableMLine[1]);
		for ($i = 0, $st_counter = 0; $i < $fields_amount;   $i++, $st_counter=0) {
			while($row = $result->fetch_row())	{ //when started (and every after 100 command cycle):
				if ($st_counter%100 == 0 || $st_counter == 0 )	{$content .= "\nINSERT INTO ".$table." VALUES";}
					$content .= "\n(";    for($j=0; $j<$fields_amount; $j++){ $row[$j] = str_replace("\n","\\n", addslashes($row[$j]) ); if (isset($row[$j])){$content .= '"'.$row[$j].'"' ;}  else{$content .= '""';}	   if ($j<($fields_amount-1)){$content.= ',';}   }        $content .=")";
				//every after 100 command cycle [or at last line] ....p.s. but should be inserted 1 cycle eariler
				if ( (($st_counter+1)%100==0 && $st_counter!=0) || $st_counter+1==$rows_num) {$content .= ";";} else {$content .= ",";}	$st_counter=$st_counter+1;
			}
		} $content .="\n\n\n";
	}
	$content .= "\r\n\r\n/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\r\n/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\r\n/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;";
	$backup_name = $backup_name ? $backup_name : JPATH_BASE.'/database.sql';
	file_put_contents($backup_name,$content);
}
	
$sourcefolder = JPATH_BASE.DS;
$sourcePath = $sourcefolder.'website.zip';
if(is_file($sourcePath)){
	unlink($sourcePath);
}
if(is_file($sourcefolder.'database.sql')){
	unlink($sourcefolder.'database.sql');
}
	

$rootPath = JPATH_BASE;
echo $rootPath.'<br>';		
// Initialize archive object
//start zip
$sourcefolder = $rootPath.DS;
EXPORT_DATABASE(DB_HOST,DB_USER,DB_PASSWORD,DB_NAME);
//copy installer file
copy(JPATH_BASE.DS.'/debug/installer.php',$sourcefolder.'installer.php');
$strLen = strlen($sourcefolder);
$zip = new ZipArchive();		
$zip->open($sourcePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

// Create recursive directory iterator
/** @var SplFileInfo[] $files */
$exclude = array('.git', '.htaccess');
$filter = function ($file, $key, $iterator) use ($exclude) {
    if ($iterator->hasChildren() && !in_array($file->getFilename(), $exclude)) {
        return true;
    }
    return $file->isFile();
};
$innerIterator = new RecursiveDirectoryIterator(
    $sourcefolder,
    RecursiveDirectoryIterator::SKIP_DOTS
);
$files = new RecursiveIteratorIterator(
	new RecursiveCallbackFilterIterator($innerIterator, $filter),
	RecursiveIteratorIterator::LEAVES_ONLY
);

// $files = new RecursiveIteratorIterator(
// 	new RecursiveDirectoryIterator($sourcefolder),
// 	RecursiveIteratorIterator::LEAVES_ONLY
// );

foreach ($files as $file)
{
	// Skip directories (they would be added automatically)
	if (!$file->isDir())
	{
		// Get real and relative path for current file
		$filePath = $file->getRealPath();
		$relativePath = substr($filePath, $strLen);

		// Add current file to archive
		$zip->addFile($filePath, str_replace("\\","/",$relativePath));
	}
}
$zip->close();
echo 'Zip download:<a href="'.get_option( 'siteurl' ).'/website.zip">Download</a><br>';
