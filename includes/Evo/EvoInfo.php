<?php
namespace Evo;

class EvoInfo
{
	public $name;
	public $description;
	public $version;
	public $exports = [];
	public $time;
	public $author;
	public $contributors = [];
	public $authors = [];
	public $homepage;
	public $download;
	public $manifest;
	public $permissions = [];
	public $changelog = [];
	public $settings = [];

	public function __construct(array $properties = [])
	{
		foreach ($properties as $key => $value) {
			$this->$key = $value;
		}

		$this->exports = $this->exports ?: ['plugin'];
		$this->authors = $this->normalizeAuthors();
	}

	public function getAuthors(): array
	{
		return $this->authors;
	}

	protected function normalizeAuthors(): array
	{
		if (!empty($this->authors)) {
			return is_array($this->authors) ? $this->authors : [$this->authors];
		}

		$authors = [];

		if (!empty($this->author)) {
			$authors[] = $this->author;
		}

		if (!empty($this->contributors)) {
			$authors = array_merge($authors, is_array($this->contributors) ? $this->contributors : [$this->contributors]);
		}

		return $authors;
	}

	public function checkForUpdates(): ?self
	{
		if ($this->manifest && $remote = self::fromFile($this->manifest)) {
			if ($remote->version != $this->version) {
				return $remote;
			}
		}
		return null;
	}

	public static function fromJSON($json)
	{
		if (is_array($json = json_decode($json, true))) {
			return new self($json);
		}
		return null;
	}

	public static function fromFile($path)
	{
		return self::fromJSON(@file_get_contents($path));
	}
}
