#!/usr/local/php/bin/php
<?
if (defined('STDIN'))                   //** Если скрипт запущен в режиме CGI
{
    include('cgi_adapter.class.php');   //** Подключаем класс cgi_adapter для адаптации работы в режиме CGI
    $CGI = & new cgi_adapter();         //** Создаём объект класса cgi_adapter
}

include('upload_file.class.php');

$upload_files_dir = './upl_files';

?>
<HTML>
<HEAD>
<TITLE>Загрузка файлов через веб-форму</TITLE>
</HEAD>
<BODY>
<H1>Загрузка файла через веб-форму</H1>
<FORM action="" method="POST" enctype="multipart/form-data">
	<INPUT type="hidden" name="submit" value="true">
	<INPUT type="file" name="upload_file" size="70"><BR>
	<INPUT type="submit" value="Загрузить файл">
</FORM>
<?
if (isset($_POST['submit']))
{
	$file = new uploaded_file("upload_file");
	
	if (!is_dir($upload_files_dir))
	{
		mkdir($upload_files_dir);
		chmod($upload_files_dir, 0755);
	}
	
	$file->move($upload_files_dir);
}

echo "Содрежимое папки <B>$upload_files_dir</B>:\r\n<PRE>";

if (is_dir($upload_files_dir) && is_readable($upload_files_dir) && ($dh = opendir($upload_files_dir)))
{
	while ($file = readdir($dh))
	{
		if (is_file($upload_files_dir . '/' . $file)) echo "$file\r\n";
	}
}
else 
{
	echo "(Пусто)\r\n";
}

echo "----------------------\r\n";
echo "Массив \$_FILES\r\n";
print_r($_FILES);

echo "</PRE>\r\n";

?>
</BODY>
</HTML>