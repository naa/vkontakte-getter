<?
/*
* Класс для работы с загруженными через форму файлами
* Написал (c) ZlobnyGrif 05 aug 2005
*/
class uploaded_file {
	var $name = '';				//** Имя загруженного файла
	var $tmp_name = '';			//** Имя временного файла
	var $size = 0;				//** Размер загруженного файла
	var $error = 0;				//** Ошибки возникшие при загрузке файла
	var $type = '';				//** Тип загруженного файла
	
	var $html_name = '';		//** Имя файла в веб-форме
	var $destination_file = '';	//** Имя конечного файла
	var $moved = false;			//** Флаг перемещённости файла
	var $empty = true;			//** Флаг пустоты, установлен flase, если файл так и не был выбран в форме
	var $errors = array();		//** Ошибки, возникшие во время обработки файла
	
	var $_GLOBAL_ERRORS = true;	//** Все ошибки становятся глобальными
	
	var $upload_errors = array( //** Описание ошибок
		UPLOAD_ERR_OK			=> 'Файл был успешно загружен',
		UPLOAD_ERR_INI_SIZE		=> 'Файл по размеру превысил максимальный размер, установленный в php.ini',
		UPLOAD_ERR_FORM_SIZE	=> 'Файл по размеру превысил максимальный размер, передаваемый через форму',
		UPLOAD_ERR_PARTIAL		=> 'Файл был загружен только частично',
		UPLOAD_ERR_NO_FILE		=> 'Имя файла не было указано в веб-форме'
	);
	
	/**
	* @return uploaded_file
	* @param string $html_name имя файла в веб-форме
	* @desc Конструктор
	*/
	function uploaded_file ($html_name)
	{
		$this->html_name = $html_name;													//** Сохраняем имя внутри класса
		
		if (preg_match('/\[\]/', $html_name)) 											//** Если имя содержит пустой индекс, вызываем ошибку имени
			return $this->add_error("Файл <B>$html_name</B> имеет неправильное имя", __FUNCTION__, __LINE__);
		
		preg_match('/^([\d\w]+)((?:\[.*\])?)/', $html_name, $arr);						//** Вычисляем имя и индексы загруженного файла
		
		if ($arr[2]) $arr[2] = preg_replace('/([\s\w\d]+)/', '\'\\1\'', $arr[2]);		//** Если был индекс, то заковычиваем имена индексов
		
		$this->set_file_properties($arr[1], $arr[2]);									//** Создаём ссылки на свойства файла в $_FILES
		
		if ($this->size) $this->empty = false;											//** Если размер загруженно файла больше 0, значит он не пустой
	}
	
	/**
	* @return boolean
	* @param string $name имя загружаемого файла
	* @param string $keys индекс имени файла
	* @desc Внутреняя функция, уставнавливает ссылки из $_FILES на аналогичные свойства класса
	*/
	function set_file_properties ($name, $keys)
	{
		if (!isset($_FILES[$name])) 													//** Если файл не установлен в $_FILES, то выходим
			return $this->add_error("Файл с именем <B>$name</B> не был передан", __FUNCTION__, __LINE__);
		
		$eval = '';
		
		foreach ($_FILES[$name] as $prop_name => $value) 
			$eval .= "\$this->$prop_name = &\$_FILES['$name']['$prop_name']$keys;\r\n";	//** Перебираем все свойства и подготавливаем eval
		
		eval($eval);																	//** Выполняем копирование ссылок
		return true;
	}
	
	/**
	* @return false
	* @param string $error текст ошибкм
	* @param string $function функция, вызвавшая ошибку
	* @param string $line строка, вызвавшая ошибку
	* @param boolean $raise_error генерировать пользовательскую ошибку
	* @param E_TYPE $e_type уровень пользовательской ошибки
	* @desc Добавляет ошибку
	*/
	function add_error($error, $function, $line, $raise_error = null, $e_type = E_USER_NOTICE)
	{
		if ($raise_error === null)						//** Если принудительно не установлен режим ошибок
			$raise_error = $this->_GLOBAL_ERRORS;		//** Устанавливаем режим по-умолчанию

		$this->errors[] = $error;						//** Добавляем текст ошибки в множество ошибок
		
		if ($raise_error) 								//** Если надо, генерируем ошибку
			user_error('Class: <B>' . __CLASS__ . '</B>, function: <B>' . $function . "</B>, line: <B>$line</B>, messsage: <P>" . $error . "</P>", $e_type);
		
		return false;									//** Возвращаем false для удобства вызывающей функии
	}
	
	/**
	* @return string
	* @desc Возвращает последнюю ошибку
	*/
	function last_error()
	{
		if (!$this->errors) return false;				//** Если ошибок нет, возвращаем false
		return $this->errors[count($this->errors) - 1];	//** Возвращаем текст последней ошибки
	}
	
	/**
	* @return boolean
	* @param string $path путь, куда будет загружен файл
	* @param string $new_name новое имя файла
	* @param boolean $replace замена уже существующего файла
	* @desc Перемещает загруженный файл
	*/
	function move ($path = false, $new_name = false, $replace = true) 
	{
		if ($this->moved)								//** Если файл уже был перемещён раньше
			return $this->add_error("Загружаемый файл <B>$this->name</B> уже был перемещён в <B>$this->destination_file</B>", __FUNCTION__, __LINE__);
			
		if ($this->empty)								//** Если файл не был загружен на сервер
			return $this->add_error("Загружаемый файл <B>$this->html_name</B> не был загружен на сервер или он имеет нулевую длину", __FUNCTION__, __LINE__);
		
		if ($this->size == 0)							//** Если файл нулевой длины, то это ошибка
			return $this->add_error("Загружаемый файл <B>$this->name</B> имеет нулевую длину, он не может быть загружен", __FUNCTION__, __LINE__);
		
		if (!$path)	$path = './';						//** Путь по-умолчанию
		
		if (!preg_match('~(\\|/)$~', $path)) 			//** Добавляем разделитель пути, если его нет
			$path .= '/';
		
		if (!$new_name && $this->destination_file)		//** Если новое имя не было задано, но утсановлено имя конечного файла
			$path = $this->destination_file;			//** Задаём имя конченого файла
		else
		{
			if (!$new_name) $new_name = $this->name;	//** Если новое имя не задано, то берём имя исходного файла
			
			$real_path = realpath($path);				//** Вычисляем реальный путь к папке с файлом
			
			if (!$real_path)							//** Если такой папки нет, то вызываем ошибку
				return $this->add_error("Путь <B>$path</B> для перемещения загруженного файла <B>$this->name</B> не существует", __FUNCTION__, __LINE__);
			
			$path .= $new_name;							//** Имя конечного файла
		}
		
		$this->destination_file = $path;				//** Запоминаем конечный файл
		
		if (is_file($path)) 							//** Если конечный файл уже существует
		{
			if ($replace)								//** Если можно заменить файл, то удаляем его, что бы не мешал
			{
				if (!@unlink($path)) $this->add_error("Не удалось удалить файл <B>$path</B>, его должен был заменить загруженный файл <B>$this->name</B>", __FUNCTION__, __LINE__);
			}
			else										//** Если нельзя заменять, то вызываем ошибку загрузки
			{
				return $this->add_error("Не удалось переместить файл <B>$this->name</B>, файл с таким именем уже существует <B>$path</B>", __FUNCTION__, __LINE__);
			}
		}
		
		if (!@rename($this->tmp_name, $path))			//** Попытка переместить загруженный файл в конечный файл
			return $this->add_error("Не удалось переместить загруженный файл <B>$this->name</B> в <B>$path</B>", __FUNCTION__, __LINE__);
		
		$this->moved = true;							//** Устанавливаем флаг перемещённости
		
		return true;
	}
	
	/**
	* @return string
	* @desc Возвращает ошибку загрузки файла в виде текстового сообщения
	*/
	function errorm() {
		return $this->upload_errors[$this->error];		//** Возвращаем описание ошибки из массива, по её номеру
	}
}
?>