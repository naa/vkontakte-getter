<?
/*
* Класс для адаптации пхп-скриптов для работы в CGI-режиме
* Написал (c) ZlobnyGrif 13 aug 2005
* 
* 
* Далее идут жуки
* --------------------------------------------------------
*
* Раньше был жук:
*     Описание       :  Функция cgi_adapter::setcookie() не работала должным образом! :(
*     Нашёл          :  Sarry (http://sarry.com.ru)
*     Дата сообщения :  22 Янв 2006 20:38 (личка http://forum.fatal.ru)
*     Статус         :  Испарвлен 23 Янв 2006 09:15 - 13:00 ZlobnyGrif
*
* Жук ещё жив:
*     Описание       :  Не полная обработка входящих данных (при больших размерах) переданных обычным методом POST
*     Нашёл          :  Sarry (http://sarry.com.ru)
*     Дата сообщения :  24 Янв 2006
*     Статус         :  Причина не установлена, рекомендуется использовать тип передачи multipart/form-data
*
* Нашёлся жук:
*     Описание       :  В функции setcookie время жизни переменной не передавалось браузеру
*     Нашёл          :  Sarry (http://sarry.com.ru)
*     Дата сообщения :  28 Янв 2006
*     Статус         :  Исправлен 2 Фев 2006
*/
if (!class_exists('cgi_adapter')) 
{
    class cgi_adapter
    {
        var $http_header = "";              //** Заголовок скрипта
        
        var $shutdown_functions = array();  //** Массив финальных функций
        
        var $cur_sid = null;                //** Идентификатор сессии, внутренняя переменная
        
        /**
        * @return cgi_adapter
        * @param boolean $emulate_magic_quotes - флаг эмуляции режима magic_quotes
        * @param stream $fp - входной поток, по-умолчанию STDIN
        * @desc Конструктор класса, создаёт объект cgi
        */
        function & cgi_adapter ($emulate_magic_quotes = true, $fp = STDIN)
        {
            ob_start(array(&$this, 'flush'));                          //** Включаем буфер вывода на экран. Защита от преждевременного вывода заголовка
            
            $this->header('Content-Type: text/html');                  //** Тип передаваемого содержимого страницы
            $this->header('X-Powered-By: PHP/' . phpversion());        //** Генератор страницы
            
            $this->parse_get();                                        //** Заполняем массив $_GET
            $this->parse_post($fp);                                    //** Заполняем массив $_POST и, по-возможности, $_FILES
            $this->parse_cookie();                                     //** Заполняем массив $_COOKIE
            
            if ($emulate_magic_quotes) $this->emulate_magic_quotes();  //** Эмуляция magic_quotes
            
            $HTTP_GET_VARS = & $_GET;                                  //** Копирование ссылки на на $_GET
            $HTTP_POST_VARS = & $_POST;                                //** Копирование ссылки на на $_POST
            $HTTP_POST_FILES = & $_FILES;                              //** Копирование ссылки на на $_FILES
            $HTTP_COOKIE_VARS = & $_COOKIE;                            //** Копирование ссылки на на $_COOKIE
            
            register_shutdown_function(array(&$this, 'shutdown'));     //** Регистрируем финальную функцию $this->shutdown
            
            $this->restore_session_id();                               //** Восстанавливаем идентификатор сессии
        }
        
        /**
        * @return void
        * @param stream $fp - входной поток
        * @desc Функция заполняет глобалный массив _POST
        */
        function parse_post($fp)
        {
            if (!$fp) return false;                                    //** Если входной поток пуст, выходим
            
            if ($_SERVER['REQUEST_METHOD'] == 'POST')                  //** Если форма была передана методом POST
            {
                $content_type = http_header::parse_params('value=' . $_SERVER['CONTENT_TYPE']);  //** Парсим тип переданного содержимого
                
                switch ($content_type['value'])                                                  //** На основании типа заголовка, выбираем метод парсинга
                {
                    case 'multipart/form-data':                                                  //** Форма передачи файлов, смешанный тип
                        $this->parse_multipart_form($fp, '--' . $content_type['boundary']);      //** Парсим то что пришло на $fp 
                        $this->add_shutdown_function(array(&$this, 'remove_tmp_files'));         //** По окончании выполнения скрипта, временные файлы надо удалить
                        break;
                    
                    default:                                                                     //** Заголовок по-умолчанию - строка разделённая &
                        if (isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'])     //** Если есть данные
                        {
                            $line = fread($fp, $_SERVER['CONTENT_LENGTH']);                      //** Считываем содержимое формы
                            $this->parse_str($line, $_POST);                                     //** Парсим данные в массив _POST стандартной функцией пхп
                        }
                }
            }
        }
        
        /**
        * @return void
        * @desc Функция заполняет глобалный массив _GET
        */
        function parse_get()
        {
            if (isset($_SERVER['QUERY_STRING']))                      //** Если были переданы параметры через запроса
            {
                $this->parse_str($_SERVER['QUERY_STRING'], $_GET);    //** Устанавливаем значения массива _GET
            }
        }
        
        /**
        * @return void
        * @desc Функция заполняет глобалный массив _COOKIE
        */
        function parse_cookie()
        {
            if (isset($_SERVER['HTTP_COOKIE']))                                 //** Если серверу были переданы куки
            {
                $_COOKIE = http_header::parse_params($_SERVER['HTTP_COOKIE']);  //** Парсим строку с куками функцией разбиения параметров заголовка
            }
        }
        
        /**
        * @return void
        * @param stream $fp - входной поток
        * @param string $boundary - разделитель
        * @desc Функция парсит данные переданные в виде multipart/form-data
        */
        function parse_multipart_form ($fp, $boundary)
        {
            $line = $this->fparse_part($fp, &$boundary);             //** Прасим первую часть
            
            while ($line && !preg_match("/^$boundary--/", $line))    //** Если часть переданной формы не является последней, то продолжаем цикл
            {
                $line = $this->fparse_part($fp, &$boundary);         //** Парсим следующую часть из потока
            }
        }
        
        /**
        * @return string
        * @param stream $fp - входной поток
        * @param string $boundary - разделитель
        * @desc Функция парсит часть пердеанной формы
        */
        function fparse_part ($fp, $boundary)
        {
            $line = fgets($fp);                                            //** Читаем первую строку
            
            if (preg_match("/^$boundary/", $line)) return $line;           //** Если строка является резделителем, то возвращаем её
            
            $header = new http_header($line . $this->fread_header($fp));   //** Считываем заголовок
            
            $line = $this->fread_part_content($fp, $content, $boundary);   //** Считываем содержимое данной части
            
            $content = preg_replace('/(\r?\n)$/', '', $content);           //** Убираем из содержимого последние \r\n - их подставил браузер
            
            if (isset($header->params['Content-Disposition']['filename'])) //** Если переданный параметр был файлом
            {
                $this->save_upload_file(                                   //** Сохраняем его как загруженный файл
                    $header->params['Content-Disposition']['name'],        //** Имя переменной
                    $header->params['Content-Disposition']['filename'],    //** Имя переданного файла вместе с путём
                    $header->params['Content-Type']['value'],              //** MIME-тип файла, на основании его расширения и заголовка
                    $content,                                              //** Содержимое файла
                    !$line                                                 //** Если заголовок кончился раньше времени, то значит файл не был передан полностью
                );
            }
            else 
            {                                                              //** Если переменная не является файлом
                $this->set_var(                                            //** Сохраняем переменную в массиве _POST
                    $header->params['Content-Disposition']['name'],        //** Имя переменной
                    $content,                                              //** Содержимое
                    '$_POST'                                               //** Глобальный массив
                );
            }
            
            return $line;                                                  //** Возвращаем разделитель.
        }
        
        /**
        * @return string
        * @param stream $fp - входной поток
        * @param string $content - содержимое
        * @param string $boundary - разделитель
        * @desc Функция считывает из потока содержимое, пока не встретиться разделитель или поток не кончиться
        */
        function & fread_part_content ($fp, &$content, $boundary)
        {
            while (($line = fgets($fp)))                                    //** Пока из потока читаются строки
            {
                if (preg_match("/^$boundary/", $line)) return trim($line);  //** Если строка является разделителем, то возвращаем его
                
                $content .= $line;                                          //** Добавляем строку из потока в содержимое
            }
            
            return false;                                                   //** Если поток кончился, то возвращаем ложь. Значит заголовок не был передан полностью :(
        }
        
        /**
        * @return string
        * @param string $fp - входной поток
        * @desc Функция возвращает заголовок, считанный из входного потока
        */
        function & fread_header ($fp)
        {
            $header = '';                                    //** Инициализация заголовка
            
            while (($line = fgets($fp)))                     //** Пока из потока читаются строки
            {
                if (preg_match('/^\r?\n$/', $line)) break;   //** Если строка является пустой, значит заголовок кончился, прерываем цикл
                
                $header .= $line;                            //** Добавляем строку к заголовку
            }
            
            return $header;                                  //** Возвращаем заголовок
        }
        
        /**
        * @return void
        * @param str $url
        * @param array $result
        * @desc Функция парсит урл в массив, аналог str_parse
        */
        function parse_str ($url, &$result)
        {
            $arr = explode('&', $url);                                         //** Разбиваем урл на параметр=значение
            
            if ($arr)                                                          //** Если массив не пуст
            {
                foreach ($arr as $value)                                       //** Перебираем все параметры
                {
                    $var_value = null;                                         //** Начальное значение параметра
                    
                    list($var_name, $var_value) = explode('=', $value, 2);     //** Разбиваем пару
                    
                    $var_name = urldecode($var_name);                          //** Декодируем имя переменной
                    $var_value = urldecode($var_value);                        //** Декодируем значение переменной
                    
                    list($name, $keys) = $this->get_valid_names($var_name);    //** Получаем допустимые имена
                    
                    if ($name) eval("\$result['$name']$keys = \$var_value;");  //** Сохраняем результат
                }
            }
        }
        
        /**
        * @return void
        * @param string $varname - имя переменной
        * @param string $value - значение переменной
        * @param string $global_array - глобальный массив, по-молчанию $_POST
        * @desc Функция устанавливеат значение переменной в глобальный массив
        */
        function set_var ($varname, $value, $global_array = '$_POST')
        {
            list($name, $keys) = $this->get_valid_names($varname);  //** Получаем имя и ключи переменной
            
            $eval = "{$global_array}['$name']$keys = \$value;";     //** Подготовка к копировнию
            
            eval($eval);                                            //** Копирование ссылки
        }
        
        /**
        * @return void
        * @param string $varname
        * @param string $filename
        * @param string $type
        * @param string $content
        * @param boolean $corrupted
        * @desc Функция сохраняет файл 
        */
        function save_upload_file ($varname, $filename, $type, &$content, $corrupted)
        {
            $error = 0;                                                    //** Начальное значения ошибок, возникших при загрузки файла - ошибок нет
            
            if ($corrupted) $error = UPLOAD_ERR_PARTIAL;                   //** Если заголовок был загружен частично, то устанавливаем ошибку загрузки
            
            $tmp_file = tempnam($_ENV['TEMP'], 'upl');                     //** Имя временного файла
            
            if ($fp = fopen($tmp_file, 'wb+'))                             //** Если удалось открыть временный файл
            {
                $size = strlen($content);                                  //** Вычисляем длину файла
                fputs($fp, &$content);                                     //** Записываем содерживое в файл
                fclose($fp);                                               //** Закрываем файл
                $GLOBALS['TMP_FILES'][] = $tmp_file;                       //** Регистрируем временный файл
            } 
            else                                                           //** Если же временный файл не был открыт
            {
                $error = UPLOAD_ERR_NO_FILE;                               //** Устанавливаем ошибку открытия файла
                $size = 0;                                                 //** Файл имеет нулевую длину
            }
            
            $content = null;                                               //** Очищаем буфер содержимого файла, что бы не занимать память
            $filename = $this->basename($filename);                        //** Получаем имя файла
            
            list($name, $keys) = $this->get_valid_names($varname);         //** Получаем имя переменной и её ключ
            
            $eval = "\$_FILES['$name']['name']{$keys} = '$filename';
                     \$_FILES['$name']['type']{$keys} = '$type';
                     \$_FILES['$name']['tmp_name']{$keys} = '$tmp_file';
                     \$_FILES['$name']['error']{$keys} = $error;
                     \$_FILES['$name']['size']{$keys} = $size;";           //** Подготовка к копированию
            
            @eval($eval);                                                  //** Копирование ссылок
        }
        
        /**
        * @return array
        * @param string $varname - имя переменной в html формате
        * @desc Функция возвращает имя и ключ переменной для использования их в функции eval()
        */
        function get_valid_names ($varname)
        {
            preg_match('/([^\[\]]+)\s*(.*)/', $varname, $arr);                   //** Отделяем имя и ключи
            
            $name = $arr[1];                                                     //** Имя переменной
            $keys = addcslashes($arr[2], '\\\'');                                //** Индексы переменной или ключи :)
            
            if ($keys)                                                           //** Если был задан индекс
            {
                if (preg_match('/^(\[[^\[\]]*\])+$/', $keys))                    //** Если индекс имеет правильный формат
                {
                    $keys = preg_replace('/\[([^\[]+)\]/', '[\'\\1\']', $keys);  //** Заковычиваем ключи
                }
                else                                                             //** Если индекс имеет неправильный формат
                {
                    $keys = "['$keys']";                                         //** То индексом становиться всё выражение :((((
                }
            }
            
            return array($name, $keys);                                          //** Возвращаем результат
        }
        
        /**
        * @return string
        * @param string $path
        * @desc Возвращает имя файла или папки из пути
        */
        function basename($path)
        {
            if (preg_match('!([^/\\\]+)$!', $path, $arr)) return $arr[1];  //** Возвращаем всё что в конце и не содержит \ и /
            
            return false;                                                  //** Иначе возвращаем ложь
        }
        
        
        /**
        * @return void
        * @desc Экранирует все значения массивов $_GET $_POST $_COOKIE
        */
        function emulate_magic_quotes()
        {
            if (get_magic_quotes_gpc())       //** Если в настройках пхп включён режим magic_quotes
            {
                $this->addslashes($_GET);     //** Экарнируем массив $_GET
                $this->addslashes($_POST);    //** Экарнируем массив $_POST
                $this->addslashes($_COOKIE);  //** Экарнируем массив $_COOKIE
            }
        }
        
        /**
        * @return void
        * @param mixed $value
        * @desc Рекурсивная функция, добавляющая слэши к ковычкам
        */
        function addslashes(&$value)
        {
            if (is_array($value))                                 //** Если занчение массив, то делаем рексрсию
            {
                array_walk($value, array(&$this, __FUNCTION__));  //** Перебираем все значения массива с помощью текущей функции
            }
            else                                                  //** Если значение не массив
            {
                $value = addcslashes($value, '\\\'"');            //** Экранируем ковычки
            }
        }
        
        /**
        * @return void
        * @desc Функция удаляет все зарегистрированные временные файлы
        */
        function remove_tmp_files ()
        {
            global $TMP_FILES;                                              //** Создаём ссылку на внешнюю переменную
            
            if (!isset($TMP_FILES) || !is_array($TMP_FILES)) return false;  //** Если зарегистрировнанных временных файлов нет, то выходим
            
            foreach ($TMP_FILES as $file)                                   //** Перебираем временные файлы
            {
                if (is_file($file)) @unlink($file);                         //** Если файл не был перемещён, удалаем его
            }
        }
        
        
        /**
        * @return void
        * @param value $header - строка, который надо добавить в заголовок
        * @param boolean $replace - фалг замены переданного параметра
        * @desc Функция добавлет строку в заголовок, аналог header()
        */
        function header ($header, $replace = true)
        {
            $header = trim($header);                                           //** Убираем лишние пробелы и переводы строк
            
            $sub_params = '';                                                  //** Инициализация подпараметров
            
            list($param_name, $sub_params) = explode(':', $header, 2);         //** Извлекаем имя параметра и его подпараметры
            
            $param_name = trim($param_name);                                   //** Убираем лишние пробелы из имени параметра
            
            if (!$param_name) return false;                                    //** Если имя параметра не задано, возвращаем ложь
            
            if ($replace && preg_match("/$param_name/i", $this->http_header))  //** Если требуется замена содержимого параметра и такй параметр уже есть в заголовке
            {
                $this->http_header = preg_replace("/$param_name(.*)/i", "$param_name:$sub_params", $this->http_header);    //** Заменяем содержимое параметра, на новое
            }
            else 
            {
                $this->http_header .= $header . "\n";                          //** Если замена не требуется или параметра с таким именем нет, то просто добавляем его к заголовку
            }
            
        }
        
        /**
        * @return void
        * @param string $name
        * @param string $value
        * @param string $expires
        * @param string $path
        * @param string $domain
        * @param boolean $secure
        * @desc Устанавливает кукис
        */
        function setcookie($name, $value = null, $expires = null, $path = null, $domain = null, $secure = null)
        {
            $cookie = "Set-Cookie: ". urlencode($name) .'=' . urlencode($value);  //** Установка праметра cookie
            
            if ($path !== null) $cookie .= "; path=". urlencode($path); 
            //else $cookie .= "; path=/";                                         //** Путь сохранения
            
            if ($domain) $cookie .= "; domain=". urlencode($domain);              //** Домен сохранения
            
            if ($expires) $cookie .= '; expires=' . gmdate('D, d-M-Y G:i:s ', $expires) . 'GMT';  //** Время жизни
            
            if ($secure) $cookie .= "; secure";                                   //** Защищённость
            
            $this->header($cookie, false);                                        //** Добавляем cookie параметр в заголовок
        }
        
        /**
        * @return void
        * @desc Функция восстанавливает идентификатор сессии
        */
        function restore_session_id ()
        {
            if (isset($_COOKIE['PHPSESSID']) && $_COOKIE['PHPSESSID'])  //** Если идентификатор сессии уже установлен
            {
                session_id($_COOKIE['PHPSESSID']);                      //** Устанавливаем идентификатор сессии из кукисов
                $this->cur_sid = $_COOKIE['PHPSESSID'];                 //** Запоминаем текущий идентификатор сессии, это надо для парвильного уничтожения сессии
            }
        }
        
        /**
        * @return void
        * @desc Функция сохраняет идентификатор сессии
        */
        function save_session_id()
        {
            $sid = session_id();                                                //** Идентификатор сессии
            
            if ($sid)                                                           //** Если сессия была запущена
            {
                $this->setcookie('PHPSESSID', $sid, time() + 3600);             //** Сохраняем идентификатор сессии
            }
            elseif ($this->cur_sid)                                             //** Если сессия была запущена, но входе выполнения уничтожена
            {
                $this->setcookie('PHPSESSID', md5(uniqid('')), time() + 3600);  //** Если сессия была уничтожена, тогда устанавливаем новый идентификатор сессии
            }
        }
        
        
        /**
        * @return string
        * @param string $content
        * @desc Функция буферизованного вывода, для того что бы избежать преждевременного вывода заголовка
        */
        function flush ($content)
        {
            $this->save_session_id();                                                   //** Сохраняем идентификатор сессии
            
            if (!preg_match('/Content-Type/i', $this->http_header))                     //** Если заголовок не содержит тип содержимого
            {
                $this->header('Content-Type: text/html');                               //** Добавляем тип вручную - text/html
            }
            
            $this->http_header = preg_replace('/(\r?\n)+/', "\n", $this->http_header);  //** Защита от переждевременного окончания заголовка
            
            return $this->http_header . "\n" . $content;                                //** Склеиваем заголовок с содержимым и отправлем страницу
        }
        
        /**
        * @return void
        * @param mixed $function - имя функции, которая будет вызвана по окончании скрипта
        * @desc Функция добовлет функцию в массив финальных функций
        */
        function add_shutdown_function ($function)
        {
            $this->shutdown_functions[] = $function;                                    //** Добавляем функцию
        }
        
        /**
        * @return void
        * @desc Финальная функция, которая вызывается по окончании выполнения скрипта
        */
        function shutdown ()
        {
            if ($this->shutdown_functions)                        //** Если массив финальных функций не пуст
            {
                foreach ($this->shutdown_functions as $function)  //** Перебираем функции
                {
                    if (is_array($function))                      //** Если функция находиться в объекте
                        $function[0]->$function[1]();             //** Вызываем функцию из объекта
                    else                                          //** Если же функция глобальная
                        $function();                              //** Вызываем глобальную функцию
                }
            }
        }
    }
}


/*
* Класс для обработки HTTP заголовка
* Написал (c) ZlobnyGrif 13 aug 2005
*/
if (!class_exists('http_header'))
{
    class http_header
    {
        var $header = '';       //** Текст заголовка
        var $params = array();  //** Параметры
        
        /**
        * @return header
        * @param string $header - HTTP заголовок
        * @desc Конструктор класса, на вход должен подаваться заголовок
        */
        function http_header ($header) 
        {
            $this->header = &$header;   //** Сохраняем заголовок
            $this->parse_header();      //** Парсим заголовок
        }
        
        /**
        * @return void
        * @desc Функция разбирает заголовок на параметры и их значения
        */
        function parse_header ()
        {
            $arr_header = & preg_split('/\r?\n/', &$this->header);     //** Разбиваем заголовок на строки
            
            if ($arr_header)                                           //** Если есть строки, будем перебирать их
            {
                foreach ($arr_header as $hd_line)                      //** Перебираем все строки заголовка
                {
                    if ($hd_line) $this->parse_header_line($hd_line);  //** Если строка содержит параметры, то парсим их
                }
            }
        }
        
        /**
        * @return bool
        * @param string $hd_line - строка заголовка, содержащая параметры
        * @desc Функция разбивает строку из заголовка на параметры и устанавливает их в массив $params
        */
        function parse_header_line ($hd_line)
        {
            if (!preg_match('/^([\w-]+)\s*:\s*([^;\r\n]+)(.*)?/', $hd_line, $arr)) return false;  //** Если строка имеет неправильный формат, возвращаем ложь
            
            $param_name = & $arr[1];                     //** Запоминаем имя параметра
            $value = & $arr[2];                          //** Значение параметра
            $params = array();                           //** Дополнительные параметры
            
            if ($arr[3])                                 //** Если есть дополнительные параметры
            {
                $params = $this->parse_params($arr[3]);  //** Прасим дополнительные параметры
            }
            
            $params['value'] = $value;                   //** Заносим значение главного параметра в общий массив
            
            $this->params[$param_name] = &$params;       //** Устанавливаем параметр объекта
            
            return true;
        }
        
        /**
        * @return array
        * @param string $params - параметры в виде строки
        * @desc Функция возвращает массив параметров из строки с параметрами
        */
        function & parse_params ($params)
        {
            $result = array();                                         //** Начальная установка резульата
            
            if (preg_match_all('~([^;\s\r\n=]+)(?:\s*=\s*(?:(?:([\'"”`])((?:(?!\\2).)*)\\2)|([^\n\r\t;]+)))?~', $params, $arr))
            {                                                          //** Если строка содержит параметры
                foreach ($arr[1] as $k => $name)                       //** Перебираем результат поиска
                {
                    $value = $arr[3][$k] ? $arr[3][$k] : $arr[4][$k];  //** Выбираем значение параметра
                    $result[urldecode($name)] = urldecode($value);     //** В результат заноситься декодированное занчение
                }
            }
            
            return $result;                                            //** Возвращаем результат
        }
        
        /**
        * @return string
        * @desc Функция возвращает заголовок в виде текста
        */
        function join_params()
        {
            if (!$this->params) return false;            //** Если параметры заголовка не были заданы, возвращаем ложь
            
            $result = '';                                //** Инициализация результата
            
            foreach ($this->params as $name => $params)  //** Перебираем массив параметров
            {
                $result .= "$name: $params[value]" . $this->join_sub_params($params) . "\n";  //** Добавлем параметр в результат
            }
            
            return $result;                              //** Возвращаем результат
        }
        
        /**
        * @return string
        * @param array $params - дополнительные параметры
        * @desc Функуция собирает из массива параметров строку
        */
        function join_sub_params ($params)
        {
            unset($params['value']);                                  //** Мы сами ввели эту переменную, для удобства, теперь её надо убрать
            
            $result = '';                                             //** Инициализация результата
            
            if ($params)                                              //** Если есть дополнительные параметры
            {
                foreach ($params as $name => $value)                  //** Перебираем параметры
                {
                    $result .= "; $name=" . urlencode($value) . "";   //** Склеиваем параметры, предварительно заменив специальные символы
                }
            }
            
            return $result;                                           //** Возвращаем результат
        }
    }
}
?>