#!/usr/local/php/bin/php
<?
if (defined('STDIN'))                   //** Если скрипт запущен в режиме CGI
{
    include('cgi_adapter.class.php');   //** Подключаем класс cgi_adapter для адаптации работы в режиме CGI
    $CGI = & new cgi_adapter();         //** Создаём объект класса cgi_adapter
}

include('upload_file.class.php');
//$upload_files_dir = './upl_files';

?>
<?
if (isset($_POST['submit']))
{
	$idnum = $_POST['idnum'];
	$intv=(int)$idnum;
	if (!($intv.""==$idnum."") || ($intv<=0)) {
//		echo $idnum;
		exit;
	}
	$upload_files_dir = $idnum;

	$file = new uploaded_file("upload_file");
	
	if (!is_dir($upload_files_dir))
	{
		mkdir($upload_files_dir);
		chmod($upload_files_dir, 0755);
	}
	
	$file->move($upload_files_dir,"tmp");
	$head = fopen("head.html","r");
	$contents = fread($head, filesize("head.html"));
	fclose($head);
	$res = fopen($upload_files_dir."/index.html","w");
	fwrite($res,$contents);
	$tmp=fopen($upload_files_dir."/tmp","r");
	$contents = fread($tmp, filesize($upload_files_dir."/tmp"));
	fclose($tmp);
	fwrite($res,$contents);
	fclose($res);	
	unlink($upload_files_dir."/tmp");
}

//echo "Содрежимое папки <B>$upload_files_dir</B>:\r\n<PRE>";
//
//if (is_dir($upload_files_dir) && is_readable($upload_files_dir) && ($dh = opendir($upload_files_dir)))
//{
//	while ($file = readdir($dh))
//	{
//		if (is_file($upload_files_dir . '/' . $file)) echo "$file\r\n";
//	}
//}
//else 
//{
//	echo "(Пусто)\r\n";
//}
//
//echo "----------------------\r\n";
//echo "Массив \$_FILES\r\n";
//print_r($_FILES);
//
//echo "</PRE>\r\n";
//
?>

</HTML>
