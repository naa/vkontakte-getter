<?
/*
* ����� ��� ������ � ������������ ����� ����� �������
* ������� (c) ZlobnyGrif 05 aug 2005
*/
class uploaded_file {
	var $name = '';				//** ��� ������������ �����
	var $tmp_name = '';			//** ��� ���������� �����
	var $size = 0;				//** ������ ������������ �����
	var $error = 0;				//** ������ ��������� ��� �������� �����
	var $type = '';				//** ��� ������������ �����
	
	var $html_name = '';		//** ��� ����� � ���-�����
	var $destination_file = '';	//** ��� ��������� �����
	var $moved = false;			//** ���� �������������� �����
	var $empty = true;			//** ���� �������, ���������� flase, ���� ���� ��� � �� ��� ������ � �����
	var $errors = array();		//** ������, ��������� �� ����� ��������� �����
	
	var $_GLOBAL_ERRORS = true;	//** ��� ������ ���������� �����������
	
	var $upload_errors = array( //** �������� ������
		UPLOAD_ERR_OK			=> '���� ��� ������� ��������',
		UPLOAD_ERR_INI_SIZE		=> '���� �� ������� �������� ������������ ������, ������������� � php.ini',
		UPLOAD_ERR_FORM_SIZE	=> '���� �� ������� �������� ������������ ������, ������������ ����� �����',
		UPLOAD_ERR_PARTIAL		=> '���� ��� �������� ������ ��������',
		UPLOAD_ERR_NO_FILE		=> '��� ����� �� ���� ������� � ���-�����'
	);
	
	/**
	* @return uploaded_file
	* @param string $html_name ��� ����� � ���-�����
	* @desc �����������
	*/
	function uploaded_file ($html_name)
	{
		$this->html_name = $html_name;													//** ��������� ��� ������ ������
		
		if (preg_match('/\[\]/', $html_name)) 											//** ���� ��� �������� ������ ������, �������� ������ �����
			return $this->add_error("���� <B>$html_name</B> ����� ������������ ���", __FUNCTION__, __LINE__);
		
		preg_match('/^([\d\w]+)((?:\[.*\])?)/', $html_name, $arr);						//** ��������� ��� � ������� ������������ �����
		
		if ($arr[2]) $arr[2] = preg_replace('/([\s\w\d]+)/', '\'\\1\'', $arr[2]);		//** ���� ��� ������, �� ������������ ����� ��������
		
		$this->set_file_properties($arr[1], $arr[2]);									//** ������ ������ �� �������� ����� � $_FILES
		
		if ($this->size) $this->empty = false;											//** ���� ������ ���������� ����� ������ 0, ������ �� �� ������
	}
	
	/**
	* @return boolean
	* @param string $name ��� ������������ �����
	* @param string $keys ������ ����� �����
	* @desc ��������� �������, �������������� ������ �� $_FILES �� ����������� �������� ������
	*/
	function set_file_properties ($name, $keys)
	{
		if (!isset($_FILES[$name])) 													//** ���� ���� �� ���������� � $_FILES, �� �������
			return $this->add_error("���� � ������ <B>$name</B> �� ��� �������", __FUNCTION__, __LINE__);
		
		$eval = '';
		
		foreach ($_FILES[$name] as $prop_name => $value) 
			$eval .= "\$this->$prop_name = &\$_FILES['$name']['$prop_name']$keys;\r\n";	//** ���������� ��� �������� � �������������� eval
		
		eval($eval);																	//** ��������� ����������� ������
		return true;
	}
	
	/**
	* @return false
	* @param string $error ����� ������
	* @param string $function �������, ��������� ������
	* @param string $line ������, ��������� ������
	* @param boolean $raise_error ������������ ���������������� ������
	* @param E_TYPE $e_type ������� ���������������� ������
	* @desc ��������� ������
	*/
	function add_error($error, $function, $line, $raise_error = null, $e_type = E_USER_NOTICE)
	{
		if ($raise_error === null)						//** ���� ������������� �� ���������� ����� ������
			$raise_error = $this->_GLOBAL_ERRORS;		//** ������������� ����� ��-���������

		$this->errors[] = $error;						//** ��������� ����� ������ � ��������� ������
		
		if ($raise_error) 								//** ���� ����, ���������� ������
			user_error('Class: <B>' . __CLASS__ . '</B>, function: <B>' . $function . "</B>, line: <B>$line</B>, messsage: <P>" . $error . "</P>", $e_type);
		
		return false;									//** ���������� false ��� �������� ���������� ������
	}
	
	/**
	* @return string
	* @desc ���������� ��������� ������
	*/
	function last_error()
	{
		if (!$this->errors) return false;				//** ���� ������ ���, ���������� false
		return $this->errors[count($this->errors) - 1];	//** ���������� ����� ��������� ������
	}
	
	/**
	* @return boolean
	* @param string $path ����, ���� ����� �������� ����
	* @param string $new_name ����� ��� �����
	* @param boolean $replace ������ ��� ������������� �����
	* @desc ���������� ����������� ����
	*/
	function move ($path = false, $new_name = false, $replace = true) 
	{
		if ($this->moved)								//** ���� ���� ��� ��� ��������� ������
			return $this->add_error("����������� ���� <B>$this->name</B> ��� ��� ��������� � <B>$this->destination_file</B>", __FUNCTION__, __LINE__);
			
		if ($this->empty)								//** ���� ���� �� ��� �������� �� ������
			return $this->add_error("����������� ���� <B>$this->html_name</B> �� ��� �������� �� ������ ��� �� ����� ������� �����", __FUNCTION__, __LINE__);
		
		if ($this->size == 0)							//** ���� ���� ������� �����, �� ��� ������
			return $this->add_error("����������� ���� <B>$this->name</B> ����� ������� �����, �� �� ����� ���� ��������", __FUNCTION__, __LINE__);
		
		if (!$path)	$path = './';						//** ���� ��-���������
		
		if (!preg_match('~(\\|/)$~', $path)) 			//** ��������� ����������� ����, ���� ��� ���
			$path .= '/';
		
		if (!$new_name && $this->destination_file)		//** ���� ����� ��� �� ���� ������, �� ����������� ��� ��������� �����
			$path = $this->destination_file;			//** ����� ��� ��������� �����
		else
		{
			if (!$new_name) $new_name = $this->name;	//** ���� ����� ��� �� ������, �� ���� ��� ��������� �����
			
			$real_path = realpath($path);				//** ��������� �������� ���� � ����� � ������
			
			if (!$real_path)							//** ���� ����� ����� ���, �� �������� ������
				return $this->add_error("���� <B>$path</B> ��� ����������� ������������ ����� <B>$this->name</B> �� ����������", __FUNCTION__, __LINE__);
			
			$path .= $new_name;							//** ��� ��������� �����
		}
		
		$this->destination_file = $path;				//** ���������� �������� ����
		
		if (is_file($path)) 							//** ���� �������� ���� ��� ����������
		{
			if ($replace)								//** ���� ����� �������� ����, �� ������� ���, ��� �� �� �����
			{
				if (!@unlink($path)) $this->add_error("�� ������� ������� ���� <B>$path</B>, ��� ������ ��� �������� ����������� ���� <B>$this->name</B>", __FUNCTION__, __LINE__);
			}
			else										//** ���� ������ ��������, �� �������� ������ ��������
			{
				return $this->add_error("�� ������� ����������� ���� <B>$this->name</B>, ���� � ����� ������ ��� ���������� <B>$path</B>", __FUNCTION__, __LINE__);
			}
		}
		
		if (!@rename($this->tmp_name, $path))			//** ������� ����������� ����������� ���� � �������� ����
			return $this->add_error("�� ������� ����������� ����������� ���� <B>$this->name</B> � <B>$path</B>", __FUNCTION__, __LINE__);
		
		$this->moved = true;							//** ������������� ���� ��������������
		
		return true;
	}
	
	/**
	* @return string
	* @desc ���������� ������ �������� ����� � ���� ���������� ���������
	*/
	function errorm() {
		return $this->upload_errors[$this->error];		//** ���������� �������� ������ �� �������, �� � ������
	}
}
?>