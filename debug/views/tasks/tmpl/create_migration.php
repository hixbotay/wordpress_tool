<?php
$data = $_POST['data'];
if($data){
    $data = $_POST['data'];
    $file_name = $_POST['file_name'];
    if(!is_dir(JPATH_BASE.'/migrations/')){
        mkdir(JPATH_BASE.'/migrations/',755);
    }
    $path = JPATH_BASE.'/migrations/'.(new DateTime())->format('YmdHi').'-'.$file_name.'.php';
    $fh = fopen($path, 'w');		
    fwrite($fh, '<?php '.PHP_EOL.$data);
    fclose($fh);
    debug($data);
    debug($path);
}
?>
<h1>Create Migration file</h1>
<p>Enter your query you want to add to your database inside <b>$query</b></p>
<form method="Post" action="<?php echo JBHelper::route('index.php?option=com_jbdebug&view=tasks&layout=create_migration')?>" name="debug">
File name: <input type="text" name="file_name"><br>
Content
<textarea name="data" style="width:100%" rows="15">
global $wpdb;
$query = "";
$wpdb->get_results($query);
</textarea><br>
<input type="submit" value="create"/>
</form>
