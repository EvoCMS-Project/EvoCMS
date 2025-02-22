<?php
/*
 * BBCode parser
 */
namespace Evo;
use Exception;

class BBCode
{
	private $bbcodes = [
		'b'   => '<strong>$1</strong>',
		'u'   => '<ins>$1</ins>',
		's'   => '<del>$1</del>',
		'i'   => '<em>$1</em>',
		'h'   => '<h4>$1</h4>',
		'sub' => '<sub>$1</sub>',
		'sup' => '<sup>$1</sup>',

		'font=([-a-z\s,\']+)'   => '<span style="font-family: $1">$2</span>',
		'color=(#?[a-z0-9]+)'   => '<span style="color: $1">$2</span>',
		'bgcolor=(#?[a-z0-9]+)' => '<span style="background-color: $1">$2</span>',

		'size=1' => '<span style="font-size: x-small">$1</span>',
		'size=2' => '<span style="font-size: small">$1</span>',
		'size=3' => '<span style="font-size: medium">$1</span>',
		'size=4' => '<span style="font-size: large">$1</span>',
		'size=5' => '<span style="font-size: x-large">$1</span>',
		'size=6' => '<span style="font-size: xx-large">$1</span>',
		'size=7' => '<span style="font-size: 48px">$1</span>',
		'size=(\d+)px' => '<span style="font-size: $1px">$2</span>',

		'justify' => '<div style="text-align: justify;">$1</div>',
		'center'  => '<div style="text-align: center;">$1</div>',
		'left'    => '<div style="text-align: left;">$1</div>',
		'right'   => '<div style="text-align: right;">$1</div>',

		'youtube' => '<iframe src="https://www.youtube.com/embed/$1" width="600px" height="360px"></iframe>',
		'video'   => '<video src="$1" style="width:600px; height: 360px" controls></video>',
		'audio'   => '<audio src="$1" style="min-width:400px" controls></audio>',

		'spoiler'           => '<div class="spoiler"><label onclick="spoiler(this)">Spoiler:</label><div>$1</div></div>',
		'spoiler=([^\]]+)?' => '<div class="spoiler"><label onclick="spoiler(this)">$1</label><div>$2</div></div>',

		'quote'                 => '<blockquote>$1</blockquote>',
		'quote=([-a-z0-9_\.]+)' => '<blockquote>$1 a dit:<br>$2</blockquote>',

		'code'             => '<pre class="language-none"><code>$1</code></pre>',
		'code=([a-z0-9]+)' => '<pre class="language-$1"><code>$2</code></pre>',

		'img'                   => '<img src="$1">',
		'img=([0-9]+)x([0-9]+)' => '<img width="$1" height="$2" src="$3">',

		'url'                                             => '<a href="$1">$1</a>',
		'url=((https?://|irc://|mailto:|\?|\/)[^"\'\]]+)' => '<a href="$1">$3</a>',

		'tooltip=([^\]"]+)' => '<span title="$1">$2</span>',

		'list=([1AaIi\*])' => '<ol type="$1">$2</ol>',
		'list' => '<ul>$1</ul>',
		'ul'   => '<ul>$1</ul>',
		'ol'   => '<ol>$1</ol>',
		'li'   => '<li>$1</li>',
		'\*'   => '<li>',

		'table' => '<table>$1</table>',
		'th'    => '<th>$1</th>',
		'tr'    => '<tr>$1</tr>',
		'td'    => '<td>$1</td>',

		'hr' => '<hr>',
	];

	private $block = 'right|left|center|float|justify|h|youtube|spoiler|quote|hr|\*';
	private $notext = 'list|ul|ol|li|table|tr|td|code';

	private $filters = [
		'url' => '(https?://|irc://|mailto:|\?|\/)',
		'img' => '(https?://|\?|\/)',
		'video' => '(https?://|\?|\/)',
		'audio' => '(https?://|\?|\/)',
	];

	private $substitutions = [
		'@(^|[^\'"])(https?://[^\s<]+)@i' => '$1<a href="$2">$2</a>', // links
		"@$@m" => '$0<br>',
		// emojis
	];

	private $safe_tags = ['b', 'u', 'i', 's', 'sub', 'sup', 'color', 'spoiler'];


	public function __construct()
	{ }


	public function setSafeTags(array $allowed_tags)
	{
		$this->safe_tags = $allowed_tags;
	}


	public function addTag($tag, $replacement)
	{
		$this->bbcodes[$tag] = $replacement;
	}


	public function addSubstitution($match, $replacement)
	{
		$this->substitutions[$match] = $replacement;
	}


	public function addEmoji($emoji, $url)
	{
		$this->addSubstitution(
			'!([^a-z]|^)' . preg_quote(html_encode($emoji)) . '([^a-z]|$)!i',
			'$1<img class="emoticon" src="' . $url . '" alt="' . $emoji . '">$2'
		);
	}

	/**
	 *  BBCode parser
	 *
	 *  @param string $bbcode
	 *  @param array $safe_subset
	 *  @return string
	 */
	public function toHTML($bbcode, $safe_subset = false)
	{
		$bbcodes = $this->bbcodes;
		$codes = $regexes = [];

		if ($safe_subset) {
			$regex = '#^(' . implode('|', $this->safe_tags) . ')(=|$)#i';
			$bbcodes = array_filter($bbcodes, function ($key) use ($regex) {
				return preg_match($regex, $key) === 1;
			}, ARRAY_FILTER_USE_KEY);
		}

		foreach ($bbcodes as $bb => $html) {
			$code = explode('=', $bb, 2);
			$codes[] = $code[0];
			if (is_callable($html) || strpos($html, '$') !== false) {
				$regex = '!\[' . $bb . '\](' . (isset($this->filters[$bb]) ? $this->filters[$bb] : '') . '[^\]]*)\[/' . $code[0] . '\]!msui';
			} else {
				$regex = '!\[' . $bb . '\]!msui';
			}
			$regexes[$regex] = $html;
		}

		$bbcode = htmlspecialchars($bbcode, ENT_COMPAT, 'utf-8');

		$bbcode = preg_replace('@\[(/?(' . $this->block . ")(=[^\]]+)?)\][\r ]*\n@musi", '[$1]', $bbcode);
		$bbcode = preg_replace('@\s*\[(/?(' . $this->notext . ")(=[^\]]+)?)\]\s*@mui", '[$1]', $bbcode);
		$bbcode = preg_replace('@(?!\[/?(' . implode('|', $codes) . ')(=[^\]]+)?\])(\[([^\[\]]+)\])@msiu', '&#91;$4&#93;', $bbcode);
		$bbcode = preg_replace('@(?!\[/?(' . implode('|', $codes) . ')(=[^\]]+)?\])(\[([^\[\]]+)\])@msiu', '&#91;$4&#93;', $bbcode);

		do {
			$replacements = 0;
			foreach ($regexes as $regex => $replacement) {
				if (is_string($replacement)) {
					$bbcode = preg_replace($regex, $replacement, $bbcode, -1, $count);
				} else {
					$bbcode = preg_replace_callback($regex, $replacement, $bbcode, -1, $count);
				}
				$replacements += $count;
			}
		} while ($replacements !== 0);

		$bbcode = preg_replace(array_keys($this->substitutions), array_values($this->substitutions), $bbcode);

		return $bbcode;
	}


	public function toBBCode($html)
	{ }
}
