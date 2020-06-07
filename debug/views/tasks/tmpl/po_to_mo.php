<?php
if(!$_POST['po_file'] && !$_POST['mo_file']){?>
    <form action="<?= JBHelper::route('index.php?option=com_jbdebug&view=tasks&layout=po_to_mo');?>" method="POST">
        PO file <input type="text" name="po_file" style="width:800px"><br>
        MO file <input type="text" name="mo_file" style="width:800px"><br>
        <input type="submit" value="Convert">
    </form>
<?php 

}else{
    echo phpmo_convert( ABSPATH.$_POST['po_file'], ABSPATH.$_POST['mo_file']);
}