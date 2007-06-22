<?
/*
* ����� ��� ��������� ���-�������� ��� ������ � CGI-������
* ������� (c) ZlobnyGrif 13 aug 2005
* 
* 
* ����� ���� ����
* --------------------------------------------------------
*
* ������ ��� ���:
*     ��������       :  ������� cgi_adapter::setcookie() �� �������� ������� �������! :(
*     �����          :  Sarry (http://sarry.com.ru)
*     ���� ��������� :  22 ��� 2006 20:38 (����� http://forum.fatal.ru)
*     ������         :  ��������� 23 ��� 2006 09:15 - 13:00 ZlobnyGrif
*
* ��� ��� ���:
*     ��������       :  �� ������ ��������� �������� ������ (��� ������� ��������) ���������� ������� ������� POST
*     �����          :  Sarry (http://sarry.com.ru)
*     ���� ��������� :  24 ��� 2006
*     ������         :  ������� �� �����������, ������������� ������������ ��� �������� multipart/form-data
*
* ������� ���:
*     ��������       :  � ������� setcookie ����� ����� ���������� �� ������������ ��������
*     �����          :  Sarry (http://sarry.com.ru)
*     ���� ��������� :  28 ��� 2006
*     ������         :  ��������� 2 ��� 2006
*/
if (!class_exists('cgi_adapter')) 
{
    class cgi_adapter
    {
        var $http_header = "";              //** ��������� �������
        
        var $shutdown_functions = array();  //** ������ ��������� �������
        
        var $cur_sid = null;                //** ������������� ������, ���������� ����������
        
        /**
        * @return cgi_adapter
        * @param boolean $emulate_magic_quotes - ���� �������� ������ magic_quotes
        * @param stream $fp - ������� �����, ��-��������� STDIN
        * @desc ����������� ������, ������ ������ cgi
        */
        function & cgi_adapter ($emulate_magic_quotes = true, $fp = STDIN)
        {
            ob_start(array(&$this, 'flush'));                          //** �������� ����� ������ �� �����. ������ �� ���������������� ������ ���������
            
            $this->header('Content-Type: text/html');                  //** ��� ������������� ����������� ��������
            $this->header('X-Powered-By: PHP/' . phpversion());        //** ��������� ��������
            
            $this->parse_get();                                        //** ��������� ������ $_GET
            $this->parse_post($fp);                                    //** ��������� ������ $_POST �, ��-�����������, $_FILES
            $this->parse_cookie();                                     //** ��������� ������ $_COOKIE
            
            if ($emulate_magic_quotes) $this->emulate_magic_quotes();  //** �������� magic_quotes
            
            $HTTP_GET_VARS = & $_GET;                                  //** ����������� ������ �� �� $_GET
            $HTTP_POST_VARS = & $_POST;                                //** ����������� ������ �� �� $_POST
            $HTTP_POST_FILES = & $_FILES;                              //** ����������� ������ �� �� $_FILES
            $HTTP_COOKIE_VARS = & $_COOKIE;                            //** ����������� ������ �� �� $_COOKIE
            
            register_shutdown_function(array(&$this, 'shutdown'));     //** ������������ ��������� ������� $this->shutdown
            
            $this->restore_session_id();                               //** ��������������� ������������� ������
        }
        
        /**
        * @return void
        * @param stream $fp - ������� �����
        * @desc ������� ��������� ��������� ������ _POST
        */
        function parse_post($fp)
        {
            if (!$fp) return false;                                    //** ���� ������� ����� ����, �������
            
            if ($_SERVER['REQUEST_METHOD'] == 'POST')                  //** ���� ����� ���� �������� ������� POST
            {
                $content_type = http_header::parse_params('value=' . $_SERVER['CONTENT_TYPE']);  //** ������ ��� ����������� �����������
                
                switch ($content_type['value'])                                                  //** �� ��������� ���� ���������, �������� ����� ��������
                {
                    case 'multipart/form-data':                                                  //** ����� �������� ������, ��������� ���
                        $this->parse_multipart_form($fp, '--' . $content_type['boundary']);      //** ������ �� ��� ������ �� $fp 
                        $this->add_shutdown_function(array(&$this, 'remove_tmp_files'));         //** �� ��������� ���������� �������, ��������� ����� ���� �������
                        break;
                    
                    default:                                                                     //** ��������� ��-��������� - ������ ���������� &
                        if (isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'])     //** ���� ���� ������
                        {
                            $line = fread($fp, $_SERVER['CONTENT_LENGTH']);                      //** ��������� ���������� �����
                            $this->parse_str($line, $_POST);                                     //** ������ ������ � ������ _POST ����������� �������� ���
                        }
                }
            }
        }
        
        /**
        * @return void
        * @desc ������� ��������� ��������� ������ _GET
        */
        function parse_get()
        {
            if (isset($_SERVER['QUERY_STRING']))                      //** ���� ���� �������� ��������� ����� �������
            {
                $this->parse_str($_SERVER['QUERY_STRING'], $_GET);    //** ������������� �������� ������� _GET
            }
        }
        
        /**
        * @return void
        * @desc ������� ��������� ��������� ������ _COOKIE
        */
        function parse_cookie()
        {
            if (isset($_SERVER['HTTP_COOKIE']))                                 //** ���� ������� ���� �������� ����
            {
                $_COOKIE = http_header::parse_params($_SERVER['HTTP_COOKIE']);  //** ������ ������ � ������ �������� ��������� ���������� ���������
            }
        }
        
        /**
        * @return void
        * @param stream $fp - ������� �����
        * @param string $boundary - �����������
        * @desc ������� ������ ������ ���������� � ���� multipart/form-data
        */
        function parse_multipart_form ($fp, $boundary)
        {
            $line = $this->fparse_part($fp, &$boundary);             //** ������ ������ �����
            
            while ($line && !preg_match("/^$boundary--/", $line))    //** ���� ����� ���������� ����� �� �������� ���������, �� ���������� ����
            {
                $line = $this->fparse_part($fp, &$boundary);         //** ������ ��������� ����� �� ������
            }
        }
        
        /**
        * @return string
        * @param stream $fp - ������� �����
        * @param string $boundary - �����������
        * @desc ������� ������ ����� ���������� �����
        */
        function fparse_part ($fp, $boundary)
        {
            $line = fgets($fp);                                            //** ������ ������ ������
            
            if (preg_match("/^$boundary/", $line)) return $line;           //** ���� ������ �������� ������������, �� ���������� �
            
            $header = new http_header($line . $this->fread_header($fp));   //** ��������� ���������
            
            $line = $this->fread_part_content($fp, $content, $boundary);   //** ��������� ���������� ������ �����
            
            $content = preg_replace('/(\r?\n)$/', '', $content);           //** ������� �� ����������� ��������� \r\n - �� ��������� �������
            
            if (isset($header->params['Content-Disposition']['filename'])) //** ���� ���������� �������� ��� ������
            {
                $this->save_upload_file(                                   //** ��������� ��� ��� ����������� ����
                    $header->params['Content-Disposition']['name'],        //** ��� ����������
                    $header->params['Content-Disposition']['filename'],    //** ��� ����������� ����� ������ � ����
                    $header->params['Content-Type']['value'],              //** MIME-��� �����, �� ��������� ��� ���������� � ���������
                    $content,                                              //** ���������� �����
                    !$line                                                 //** ���� ��������� �������� ������ �������, �� ������ ���� �� ��� ������� ���������
                );
            }
            else 
            {                                                              //** ���� ���������� �� �������� ������
                $this->set_var(                                            //** ��������� ���������� � ������� _POST
                    $header->params['Content-Disposition']['name'],        //** ��� ����������
                    $content,                                              //** ����������
                    '$_POST'                                               //** ���������� ������
                );
            }
            
            return $line;                                                  //** ���������� �����������.
        }
        
        /**
        * @return string
        * @param stream $fp - ������� �����
        * @param string $content - ����������
        * @param string $boundary - �����������
        * @desc ������� ��������� �� ������ ����������, ���� �� ����������� ����������� ��� ����� �� ���������
        */
        function & fread_part_content ($fp, &$content, $boundary)
        {
            while (($line = fgets($fp)))                                    //** ���� �� ������ �������� ������
            {
                if (preg_match("/^$boundary/", $line)) return trim($line);  //** ���� ������ �������� ������������, �� ���������� ���
                
                $content .= $line;                                          //** ��������� ������ �� ������ � ����������
            }
            
            return false;                                                   //** ���� ����� ��������, �� ���������� ����. ������ ��������� �� ��� ������� ��������� :(
        }
        
        /**
        * @return string
        * @param string $fp - ������� �����
        * @desc ������� ���������� ���������, ��������� �� �������� ������
        */
        function & fread_header ($fp)
        {
            $header = '';                                    //** ������������� ���������
            
            while (($line = fgets($fp)))                     //** ���� �� ������ �������� ������
            {
                if (preg_match('/^\r?\n$/', $line)) break;   //** ���� ������ �������� ������, ������ ��������� ��������, ��������� ����
                
                $header .= $line;                            //** ��������� ������ � ���������
            }
            
            return $header;                                  //** ���������� ���������
        }
        
        /**
        * @return void
        * @param str $url
        * @param array $result
        * @desc ������� ������ ��� � ������, ������ str_parse
        */
        function parse_str ($url, &$result)
        {
            $arr = explode('&', $url);                                         //** ��������� ��� �� ��������=��������
            
            if ($arr)                                                          //** ���� ������ �� ����
            {
                foreach ($arr as $value)                                       //** ���������� ��� ���������
                {
                    $var_value = null;                                         //** ��������� �������� ���������
                    
                    list($var_name, $var_value) = explode('=', $value, 2);     //** ��������� ����
                    
                    $var_name = urldecode($var_name);                          //** ���������� ��� ����������
                    $var_value = urldecode($var_value);                        //** ���������� �������� ����������
                    
                    list($name, $keys) = $this->get_valid_names($var_name);    //** �������� ���������� �����
                    
                    if ($name) eval("\$result['$name']$keys = \$var_value;");  //** ��������� ���������
                }
            }
        }
        
        /**
        * @return void
        * @param string $varname - ��� ����������
        * @param string $value - �������� ����������
        * @param string $global_array - ���������� ������, ��-�������� $_POST
        * @desc ������� ������������� �������� ���������� � ���������� ������
        */
        function set_var ($varname, $value, $global_array = '$_POST')
        {
            list($name, $keys) = $this->get_valid_names($varname);  //** �������� ��� � ����� ����������
            
            $eval = "{$global_array}['$name']$keys = \$value;";     //** ���������� � ����������
            
            eval($eval);                                            //** ����������� ������
        }
        
        /**
        * @return void
        * @param string $varname
        * @param string $filename
        * @param string $type
        * @param string $content
        * @param boolean $corrupted
        * @desc ������� ��������� ���� 
        */
        function save_upload_file ($varname, $filename, $type, &$content, $corrupted)
        {
            $error = 0;                                                    //** ��������� �������� ������, ��������� ��� �������� ����� - ������ ���
            
            if ($corrupted) $error = UPLOAD_ERR_PARTIAL;                   //** ���� ��������� ��� �������� ��������, �� ������������� ������ ��������
            
            $tmp_file = tempnam($_ENV['TEMP'], 'upl');                     //** ��� ���������� �����
            
            if ($fp = fopen($tmp_file, 'wb+'))                             //** ���� ������� ������� ��������� ����
            {
                $size = strlen($content);                                  //** ��������� ����� �����
                fputs($fp, &$content);                                     //** ���������� ���������� � ����
                fclose($fp);                                               //** ��������� ����
                $GLOBALS['TMP_FILES'][] = $tmp_file;                       //** ������������ ��������� ����
            } 
            else                                                           //** ���� �� ��������� ���� �� ��� ������
            {
                $error = UPLOAD_ERR_NO_FILE;                               //** ������������� ������ �������� �����
                $size = 0;                                                 //** ���� ����� ������� �����
            }
            
            $content = null;                                               //** ������� ����� ����������� �����, ��� �� �� �������� ������
            $filename = $this->basename($filename);                        //** �������� ��� �����
            
            list($name, $keys) = $this->get_valid_names($varname);         //** �������� ��� ���������� � � ����
            
            $eval = "\$_FILES['$name']['name']{$keys} = '$filename';
                     \$_FILES['$name']['type']{$keys} = '$type';
                     \$_FILES['$name']['tmp_name']{$keys} = '$tmp_file';
                     \$_FILES['$name']['error']{$keys} = $error;
                     \$_FILES['$name']['size']{$keys} = $size;";           //** ���������� � �����������
            
            @eval($eval);                                                  //** ����������� ������
        }
        
        /**
        * @return array
        * @param string $varname - ��� ���������� � html �������
        * @desc ������� ���������� ��� � ���� ���������� ��� ������������� �� � ������� eval()
        */
        function get_valid_names ($varname)
        {
            preg_match('/([^\[\]]+)\s*(.*)/', $varname, $arr);                   //** �������� ��� � �����
            
            $name = $arr[1];                                                     //** ��� ����������
            $keys = addcslashes($arr[2], '\\\'');                                //** ������� ���������� ��� ����� :)
            
            if ($keys)                                                           //** ���� ��� ����� ������
            {
                if (preg_match('/^(\[[^\[\]]*\])+$/', $keys))                    //** ���� ������ ����� ���������� ������
                {
                    $keys = preg_replace('/\[([^\[]+)\]/', '[\'\\1\']', $keys);  //** ������������ �����
                }
                else                                                             //** ���� ������ ����� ������������ ������
                {
                    $keys = "['$keys']";                                         //** �� �������� ����������� �� ��������� :((((
                }
            }
            
            return array($name, $keys);                                          //** ���������� ���������
        }
        
        /**
        * @return string
        * @param string $path
        * @desc ���������� ��� ����� ��� ����� �� ����
        */
        function basename($path)
        {
            if (preg_match('!([^/\\\]+)$!', $path, $arr)) return $arr[1];  //** ���������� �� ��� � ����� � �� �������� \ � /
            
            return false;                                                  //** ����� ���������� ����
        }
        
        
        /**
        * @return void
        * @desc ���������� ��� �������� �������� $_GET $_POST $_COOKIE
        */
        function emulate_magic_quotes()
        {
            if (get_magic_quotes_gpc())       //** ���� � ���������� ��� ������� ����� magic_quotes
            {
                $this->addslashes($_GET);     //** ���������� ������ $_GET
                $this->addslashes($_POST);    //** ���������� ������ $_POST
                $this->addslashes($_COOKIE);  //** ���������� ������ $_COOKIE
            }
        }
        
        /**
        * @return void
        * @param mixed $value
        * @desc ����������� �������, ����������� ����� � ��������
        */
        function addslashes(&$value)
        {
            if (is_array($value))                                 //** ���� �������� ������, �� ������ ��������
            {
                array_walk($value, array(&$this, __FUNCTION__));  //** ���������� ��� �������� ������� � ������� ������� �������
            }
            else                                                  //** ���� �������� �� ������
            {
                $value = addcslashes($value, '\\\'"');            //** ���������� �������
            }
        }
        
        /**
        * @return void
        * @desc ������� ������� ��� ������������������ ��������� �����
        */
        function remove_tmp_files ()
        {
            global $TMP_FILES;                                              //** ������ ������ �� ������� ����������
            
            if (!isset($TMP_FILES) || !is_array($TMP_FILES)) return false;  //** ���� ������������������� ��������� ������ ���, �� �������
            
            foreach ($TMP_FILES as $file)                                   //** ���������� ��������� �����
            {
                if (is_file($file)) @unlink($file);                         //** ���� ���� �� ��� ���������, ������� ���
            }
        }
        
        
        /**
        * @return void
        * @param value $header - ������, ������� ���� �������� � ���������
        * @param boolean $replace - ���� ������ ����������� ���������
        * @desc ������� �������� ������ � ���������, ������ header()
        */
        function header ($header, $replace = true)
        {
            $header = trim($header);                                           //** ������� ������ ������� � �������� �����
            
            $sub_params = '';                                                  //** ������������� �������������
            
            list($param_name, $sub_params) = explode(':', $header, 2);         //** ��������� ��� ��������� � ��� ������������
            
            $param_name = trim($param_name);                                   //** ������� ������ ������� �� ����� ���������
            
            if (!$param_name) return false;                                    //** ���� ��� ��������� �� ������, ���������� ����
            
            if ($replace && preg_match("/$param_name/i", $this->http_header))  //** ���� ��������� ������ ����������� ��������� � ���� �������� ��� ���� � ���������
            {
                $this->http_header = preg_replace("/$param_name(.*)/i", "$param_name:$sub_params", $this->http_header);    //** �������� ���������� ���������, �� �����
            }
            else 
            {
                $this->http_header .= $header . "\n";                          //** ���� ������ �� ��������� ��� ��������� � ����� ������ ���, �� ������ ��������� ��� � ���������
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
        * @desc ������������� �����
        */
        function setcookie($name, $value = null, $expires = null, $path = null, $domain = null, $secure = null)
        {
            $cookie = "Set-Cookie: ". urlencode($name) .'=' . urlencode($value);  //** ��������� �������� cookie
            
            if ($path !== null) $cookie .= "; path=". urlencode($path); 
            //else $cookie .= "; path=/";                                         //** ���� ����������
            
            if ($domain) $cookie .= "; domain=". urlencode($domain);              //** ����� ����������
            
            if ($expires) $cookie .= '; expires=' . gmdate('D, d-M-Y G:i:s ', $expires) . 'GMT';  //** ����� �����
            
            if ($secure) $cookie .= "; secure";                                   //** ������������
            
            $this->header($cookie, false);                                        //** ��������� cookie �������� � ���������
        }
        
        /**
        * @return void
        * @desc ������� ��������������� ������������� ������
        */
        function restore_session_id ()
        {
            if (isset($_COOKIE['PHPSESSID']) && $_COOKIE['PHPSESSID'])  //** ���� ������������� ������ ��� ����������
            {
                session_id($_COOKIE['PHPSESSID']);                      //** ������������� ������������� ������ �� �������
                $this->cur_sid = $_COOKIE['PHPSESSID'];                 //** ���������� ������� ������������� ������, ��� ���� ��� ����������� ����������� ������
            }
        }
        
        /**
        * @return void
        * @desc ������� ��������� ������������� ������
        */
        function save_session_id()
        {
            $sid = session_id();                                                //** ������������� ������
            
            if ($sid)                                                           //** ���� ������ ���� ��������
            {
                $this->setcookie('PHPSESSID', $sid, time() + 3600);             //** ��������� ������������� ������
            }
            elseif ($this->cur_sid)                                             //** ���� ������ ���� ��������, �� ����� ���������� ����������
            {
                $this->setcookie('PHPSESSID', md5(uniqid('')), time() + 3600);  //** ���� ������ ���� ����������, ����� ������������� ����� ������������� ������
            }
        }
        
        
        /**
        * @return string
        * @param string $content
        * @desc ������� ��������������� ������, ��� ���� ��� �� �������� ���������������� ������ ���������
        */
        function flush ($content)
        {
            $this->save_session_id();                                                   //** ��������� ������������� ������
            
            if (!preg_match('/Content-Type/i', $this->http_header))                     //** ���� ��������� �� �������� ��� �����������
            {
                $this->header('Content-Type: text/html');                               //** ��������� ��� ������� - text/html
            }
            
            $this->http_header = preg_replace('/(\r?\n)+/', "\n", $this->http_header);  //** ������ �� ����������������� ��������� ���������
            
            return $this->http_header . "\n" . $content;                                //** ��������� ��������� � ���������� � ��������� ��������
        }
        
        /**
        * @return void
        * @param mixed $function - ��� �������, ������� ����� ������� �� ��������� �������
        * @desc ������� �������� ������� � ������ ��������� �������
        */
        function add_shutdown_function ($function)
        {
            $this->shutdown_functions[] = $function;                                    //** ��������� �������
        }
        
        /**
        * @return void
        * @desc ��������� �������, ������� ���������� �� ��������� ���������� �������
        */
        function shutdown ()
        {
            if ($this->shutdown_functions)                        //** ���� ������ ��������� ������� �� ����
            {
                foreach ($this->shutdown_functions as $function)  //** ���������� �������
                {
                    if (is_array($function))                      //** ���� ������� ���������� � �������
                        $function[0]->$function[1]();             //** �������� ������� �� �������
                    else                                          //** ���� �� ������� ����������
                        $function();                              //** �������� ���������� �������
                }
            }
        }
    }
}


/*
* ����� ��� ��������� HTTP ���������
* ������� (c) ZlobnyGrif 13 aug 2005
*/
if (!class_exists('http_header'))
{
    class http_header
    {
        var $header = '';       //** ����� ���������
        var $params = array();  //** ���������
        
        /**
        * @return header
        * @param string $header - HTTP ���������
        * @desc ����������� ������, �� ���� ������ ���������� ���������
        */
        function http_header ($header) 
        {
            $this->header = &$header;   //** ��������� ���������
            $this->parse_header();      //** ������ ���������
        }
        
        /**
        * @return void
        * @desc ������� ��������� ��������� �� ��������� � �� ��������
        */
        function parse_header ()
        {
            $arr_header = & preg_split('/\r?\n/', &$this->header);     //** ��������� ��������� �� ������
            
            if ($arr_header)                                           //** ���� ���� ������, ����� ���������� ��
            {
                foreach ($arr_header as $hd_line)                      //** ���������� ��� ������ ���������
                {
                    if ($hd_line) $this->parse_header_line($hd_line);  //** ���� ������ �������� ���������, �� ������ ��
                }
            }
        }
        
        /**
        * @return bool
        * @param string $hd_line - ������ ���������, ���������� ���������
        * @desc ������� ��������� ������ �� ��������� �� ��������� � ������������� �� � ������ $params
        */
        function parse_header_line ($hd_line)
        {
            if (!preg_match('/^([\w-]+)\s*:\s*([^;\r\n]+)(.*)?/', $hd_line, $arr)) return false;  //** ���� ������ ����� ������������ ������, ���������� ����
            
            $param_name = & $arr[1];                     //** ���������� ��� ���������
            $value = & $arr[2];                          //** �������� ���������
            $params = array();                           //** �������������� ���������
            
            if ($arr[3])                                 //** ���� ���� �������������� ���������
            {
                $params = $this->parse_params($arr[3]);  //** ������ �������������� ���������
            }
            
            $params['value'] = $value;                   //** ������� �������� �������� ��������� � ����� ������
            
            $this->params[$param_name] = &$params;       //** ������������� �������� �������
            
            return true;
        }
        
        /**
        * @return array
        * @param string $params - ��������� � ���� ������
        * @desc ������� ���������� ������ ���������� �� ������ � �����������
        */
        function & parse_params ($params)
        {
            $result = array();                                         //** ��������� ��������� ���������
            
            if (preg_match_all('~([^;\s\r\n=]+)(?:\s*=\s*(?:(?:([\'"�`])((?:(?!\\2).)*)\\2)|([^\n\r\t;]+)))?~', $params, $arr))
            {                                                          //** ���� ������ �������� ���������
                foreach ($arr[1] as $k => $name)                       //** ���������� ��������� ������
                {
                    $value = $arr[3][$k] ? $arr[3][$k] : $arr[4][$k];  //** �������� �������� ���������
                    $result[urldecode($name)] = urldecode($value);     //** � ��������� ���������� �������������� ��������
                }
            }
            
            return $result;                                            //** ���������� ���������
        }
        
        /**
        * @return string
        * @desc ������� ���������� ��������� � ���� ������
        */
        function join_params()
        {
            if (!$this->params) return false;            //** ���� ��������� ��������� �� ���� ������, ���������� ����
            
            $result = '';                                //** ������������� ����������
            
            foreach ($this->params as $name => $params)  //** ���������� ������ ����������
            {
                $result .= "$name: $params[value]" . $this->join_sub_params($params) . "\n";  //** �������� �������� � ���������
            }
            
            return $result;                              //** ���������� ���������
        }
        
        /**
        * @return string
        * @param array $params - �������������� ���������
        * @desc �������� �������� �� ������� ���������� ������
        */
        function join_sub_params ($params)
        {
            unset($params['value']);                                  //** �� ���� ����� ��� ����������, ��� ��������, ������ � ���� ������
            
            $result = '';                                             //** ������������� ����������
            
            if ($params)                                              //** ���� ���� �������������� ���������
            {
                foreach ($params as $name => $value)                  //** ���������� ���������
                {
                    $result .= "; $name=" . urlencode($value) . "";   //** ��������� ���������, �������������� ������� ����������� �������
                }
            }
            
            return $result;                                           //** ���������� ���������
        }
    }
}
?>