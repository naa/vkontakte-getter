#!/usr/local/php/bin/php
<?
if (defined('STDIN'))                   //** ���� ������ ������� � ������ CGI
{
    include('cgi_adapter.class.php');   //** ���������� ����� cgi_adapter ��� ��������� ������ � ������ CGI
    $CGI = & new cgi_adapter();         //** ������ ������ ������ cgi_adapter
}

include('upload_file.class.php');

$upload_files_dir = './upl_files';

?>
<HTML>
<HEAD>
<TITLE>�������� ������ ����� ���-�����</TITLE>
</HEAD>
<BODY>
<H1>�������� ����� ����� ���-�����</H1>
<FORM action="" method="POST" enctype="multipart/form-data">
	<INPUT type="hidden" name="submit" value="true">
	<INPUT type="file" name="upload_file" size="70"><BR>
	<INPUT type="submit" value="��������� ����">
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

echo "���������� ����� <B>$upload_files_dir</B>:\r\n<PRE>";

if (is_dir($upload_files_dir) && is_readable($upload_files_dir) && ($dh = opendir($upload_files_dir)))
{
	while ($file = readdir($dh))
	{
		if (is_file($upload_files_dir . '/' . $file)) echo "$file\r\n";
	}
}
else 
{
	echo "(�����)\r\n";
}

echo "----------------------\r\n";
echo "������ \$_FILES\r\n";
print_r($_FILES);

echo "</PRE>\r\n";

?>
</BODY>
</HTML>