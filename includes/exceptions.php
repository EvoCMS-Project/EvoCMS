<?php
class Warning extends Exception
{
	private $title;

	public function __construct($title = '', $message = '')
	{
		$this->title = $title;
		parent::__construct($message);
	}

	public function getTitle()
	{
		return $this->title;
	}
}

class ModuleException extends Exception
{

}

class FileException extends Exception
{

}

class UploadException extends FileException
{

}

class HttpException extends Exception
{
	const HTTP_CODE = 200;
}

class PageNotFound extends HttpException
{
	const HTTP_CODE = 404;
}

class PermissionDenied extends HttpException
{
	const HTTP_CODE = 403;
}

class Banned extends HttpException
{
	const HTTP_CODE = 403;
}
