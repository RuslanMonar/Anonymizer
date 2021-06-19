<?php
// vk_ full aHR0cHM6Ly92ay5jb20v
// vk_mobile aHR0cHM6Ly9tLnZrLmNvbQ==
include_once 'debug.php';
function encode_resource_url($path){
	global $data_url;
	$need  = (substr($path,0,4) !='http' ) ? "$data_url[scheme]://$data_url[host]" : "";
	return "$_SERVER[REQUEST_SCHEME]://$_SERVER[HTTP_HOST]$_SERVER[SCRIPT_NAME]?url=".base64_encode($need.$path);
}
//Підміняє ссилки в css
function fix_css($url) {
	$url = trim($url);
	$delim = strpos($url, '"') === 0 ? '"' : (strpos($url, "'") === 0 ? "'" : '');
	return $delim.preg_replace('#([\(\),\s\'"\\\])#', '\\$1', encode_resource_url(trim(preg_replace('#\\\(.)#', '$1', trim($url, $delim))))).$delim;
}
//Парсинг заголовків (не встановлений PECL модуль на сервері)
if (!function_exists('http_parse_headers')) {
	function http_parse_headers($raw_headers) {
		$headers = array();
		$key = '';
		foreach(explode("\n", $raw_headers) as $i => $h) {
			$h = explode(':', $h, 2);
			if (isset($h[1])) {
				if (!isset($headers[$h[0]]))
					$headers[$h[0]] = trim($h[1]);
				elseif (is_array($headers[$h[0]])) {
					$headers[$h[0]] = array_merge($headers[$h[0]], array(trim($h[1])));
				}
				else {
					$headers[$h[0]] = array_merge(array($headers[$h[0]]), array(trim($h[1])));
				}
				$key = $h[0];
			}
			else { 
				if (substr($h[0], 0, 1) == "\t")
					$headers[$key] .= "\r\n\t".trim($h[0]);
				elseif (!$key) 
					$headers[0] = trim($h[0]); 
			}
		}
		return $headers;
	}
}
//Парсинг cookie (не встановлений PECL модуль на сервері)
if(!function_exists('http_parse_cookie')){
	Class http_parse_cookie{
		public $cookies = array();
		public function __construct($some_cookies){
			foreach(explode('; ',$some_cookies) as $k => $v){
				preg_match('/^(.*?)=(.*?)$/i',trim($v),$matches);
				$this->cookies[trim($matches[1])] = urldecode($matches[2]);
			}
		}
	}
}

//Дані з url
if(!$_GET['url']){
	exit('URL не вказано');
}

//Розбиваю url на частини scheme , host , path , query
$data_url = parse_url(base64_decode($_GET['url']));
$data_url['path'] = (!$data_url['path']) ? '/' : "$data_url[path]";
$data_url['query'] = (!$data_url['query']) ? '' : "?$data_url[query]";
//Підключаюсь до сокета
$fp = ($data_url['scheme'] == 'http') ? fsockopen("tcp://$data_url[host]" , 80) : fsockopen("ssl://$data_url[host]" , 443);
if(!$fp){exit('Сервер не відповідає');}
//HTTP тайтли 
$out = "$_SERVER[REQUEST_METHOD] $data_url[path]$data_url[query] $_SERVER[SERVER_PROTOCOL]\r\n";
$out .= "Host: $data_url[host]\r\n";
$out .= "User-Agent: $_SERVER[HTTP_USER_AGENT]\r\n"; 
$out .= "Accept: $_SERVER[HTTP_ACCEPT]\r\n";

if($_COOKIE){
	$out .= sprintf('Cookie: %s' , http_build_query($_COOKIE , null , ';'))."\r\n" ;
}

if($_SERVER['REQUEST_METHOD'] == 'POST'){
	if($_FILES){ // масив з даними про вибраний файл
		$boundary = '----'.md5(time());
		foreach ($_FILES as $key => $file_info) { // перебераю массив з даними
			$post .= "--{$boundary}\r\n";
			$post .= "Content-Disposition: form-data; name=\"$key\"; filename=\"{$file_info['name']}\"\r\n";
			//application/octet-stream доїчний файл без вказаного формату
			$post .= 'Content-Type: '.(empty($file_info['type']) ? 'application/octet-stream' : $file_info['type']) . "\r\n\r\n";
			// tmp_name Тимчасове ім'я імя, з яким прийнятий файл був збережений на сервері.
			if (is_readable($file_info['tmp_name'])) { 
				//'r'	Открывает файл только для чтения; помещает указатель в начало файла.
				//Необходимо изменить "r" на "rb" если вы производите чтение из двоичных файлов для межплатформенной совместимости.
				$handle = fopen($file_info['tmp_name'], 'rb'); // вертає вказівник на файл
				$post .= fread($handle, filesize($file_info['tmp_name']));
				fclose($handle);
			}
			$post .= "\r\n";
		}

		$post .= "--{$boundary}--\r\n";
		$out .= "Content-Type: multipart/form-data; boundary={$boundary}\r\n";
	}
	else{
		$post = http_build_query($_POST); //Вигляд передачі даних variable=val&variable2=val
		$out .= "Content-Type: application/x-www-form-urlencoded\r\n";//Заголовок для передачі даних "variable=val&variable2=val"
	}
	$out .= "Content-Length: ".strlen($post)."\r\n";
	$out .= "Connection: Close\r\n\r\n";
	$out .= $post;
}
else{
	$out .= "Connection: Close\r\n\r\n";
}
fwrite($fp, $out); //Роблю HTTP запит(в копію сокета записується запит і виконується на стороні сервера)
while (!feof($fp)) { //Поки файл не закінчився
	$body .=  fgets($fp , 128); //Читаю відповідь з сайту
}
fclose($fp);
list($header , $body) = explode("\r\n\r\n", $body); //Відділяю заголовки від тіла відповіді

$header =  http_parse_headers($header); //Переписую заголовки у вигля асоціативного массива
list($content_type , $content_charset) = explode(";", $header['Content-Type']);

/*header('Content-Type: '.$header['Content-Type']);*/
foreach ($header as $key => $value) {
	//Встановлюю кукі
	if($key == 'Set-Cookie' and $header[$key]){
		if(is_array($header[$key])){
			foreach ($header[$key] as $cookie) {
				$cookie = new http_parse_cookie($cookie); //парсинг cookie
				$time = strtotime($cookie->cookies['expires']); //для cookie перетворю в Unix дату
				$key = key($cookie->cookies); //Получаю назву cookie
				setcookie($key , $cookie->cookies[$key] , $time, $cookie->cookies['path'] , $_SERVER['SERVER_NAME']);
			}
		}
	}
	//Переадресація
	else if ($key == 'Location') {
		header("$key: ".encode_resource_url($value));
	}
	//Тип контенту
	else{
		header("$key: $value");
	}
}
if($content_type == 'text/html'){
	//Обробка HTML
	libxml_use_internal_errors(TRUE);//Відключаю лог помилок в  консолі стосовно валідності даних
	set_error_handler(NULL, E_WARNING);
	$html = new DOMDocument;
	$html->loadHTML($body);
	$html_resource = array(
		'img' => 'src',
		'input' => 'src',
		'script' => 'src',
		'link' => 'href',
		'a' => 'href',
		'form' => 'action',
	);
	//Знаходжу і міняю силки
	foreach ($html_resource as $tag => $attribute) {
		foreach ($html->getElementsByTagName($tag) as  $element) {
			if($element->hasAttribute($attribute)){
				$element->setAttribute($attribute , encode_resource_url($element->getAttribute($attribute)));
			}
		}
	}
	$body = $html->saveHTML();
	//JS скріпти вк які зациклюється
	$body = str_replace('"hash_redirect":true', '"hash_redirect":fasle', $body); 
	$body = str_replace('if (/opera/i.test(_ua) || !/msie 6/i.test(_ua) || document.domain != locDomain) document.domain = locDomain', '', $body);
}
else if($content_type == 'text/css'){
	//Обробка CSS
	// /url\\(\\s*(?![\"\']?data:)(?!\%)([^\\)\\s]+)\\s*\\)? More Cases
	preg_match_all('/url\\(\\s*(?![\"\']?data:)([^)]+)\\)/', $body, $matches, PREG_SET_ORDER);
	for ($i = 0, $count = count($matches); $i < $count; ++$i) {
		$body = str_replace($matches[$i][0], 'url('.fix_css($matches[$i][1]).')', $body);
	}
}
echo $body;