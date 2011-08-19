<?php

/**
 * Clean an HTML string to ensure it is well-formed and restrict the allowed
 * tags and attributes
 *
 * @package skValidatorHTMLPlugin
 * @author Cal Henderson <cal@iamcal.com>
 * @author Jang Kim
 * @author Dan Bogan
 * @author Jaik Dean <jaik@studioskylab.com>
 **/
class skValidatorHTML extends sfValidatorBase
{

	/**
	 * Array of open tag counts
	 *
	 * @var array
	 **/
	protected $tag_counts = array();


	/**
	 * @param array $options
	 * @param array $messages
	 * @see sfValidatorBase
	 * @author Jaik Dean
	 */
	protected function configure($options = array(), $messages = array())
	{
		// allowed tags and their attributes
		$this->addOption('allowed', array(
			'a'      => array('href', 'target'),
			'strong' => array(),
			'img'    => array('src', 'width', 'height', 'alt'),
		));

		// tags which should always be self-closing (e.g. "<img />")
		$this->addOption('no_close', array(
			'img'
		));

		// tags which must always have seperate opening and closing tags (e.g. "<b></b>")
		$this->addOption('always_close', array(
			'a',
			'strong',
		));

		// attributes which should be checked for valid protocols
		$this->addOption('protocol_attributes', array(
			'src',
			'href',
		));

		// protocols which are allowed
		$this->addOption('allowed_protocols', array(
			'http',
			'https',
			'ftp',
			'mailto',
		));

		// tags which should be removed if they contain no content (e.g. "<b></b>" or "<b />")
		$this->addOption('remove_blanks', array(
			'a',
			'strong',
		));

		// should we remove comments?
		$this->addOption('strip_comments', true);

		// should we try and make a b tag out of "b>"
		$this->addOption('always_make_tags', true);

		// entity control options
		$this->addOption('allow_numbered_entities', true);

		$this->addOption('allowed_entities', array(
			'amp',
			'gt',
			'lt',
			'quot',
		));

		/**
		 * should we convert dec/hex entities in the general doc (not inside protocol attribute)
		 * into raw characters? this is important if you're planning on running autolink on
		 * the output, to make it easier to filter out unwanted spam URLs. without it, an attacker
		 * could insert a working URL you'd otherwise be filtering (googl&#65;.com would avoid
		 * a string-matching spam filter, for instance). this only affects character codes below
		 * 128 (that is, the ASCII characters).
		 *
		 * this options overrides allow_numbered_entities
		 **/

		$this->addOption('normalise_ascii_entities', false);
	}


	/**
	 * @see sfValidatorBase::doClean()
	 * @author Jaik Dean
	 **/
	protected function doClean($value)
	{
		$this->tag_counts = array();

		$value = $this->escapeComments($value);
		$value = $this->balanceHTML($value);
		$value = $this->checkTags($value);
		$value = $this->processRemoveBlanks($value);
		$value = $this->cleanupNonTags($value);

		return $value;
	}


	/**
	 * undocumented function
	 *
	 * @return string
	 * @author Cal Henderson <cal@iamcal.com>
	 **/
	protected function escapeComments($data)
	{
		$data = preg_replace("/<!--(.*?)-->/se", "'<!--'.htmlspecialchars(\$this->stripSingle('\\1')).'-->'", $data);

		return $data;
	}


	/**
	 * undocumented function
	 *
	 * @return string
	 * @author Cal Henderson <cal@iamcal.com>
	 **/
	protected function balanceHTML($data)
	{
		if ($this->getOption('always_make_tags')) {

			// try and form html
			$data = preg_replace('/>>+/', '>', $data);
			$data = preg_replace('/<<+/', '<', $data);
			$data = preg_replace('/^>/', '', $data);
			$data = preg_replace('/<([^>]*?)(?=<|$)/', '<$1>', $data);
			$data = preg_replace('/(^|>)([^<]*?)(?=>)/', '$1<$2', $data);

		} else {

			// escape stray brackets
			$data = preg_replace('/<([^>]*?)(?=<|$)/', '&lt;$1', $data);
			$data = preg_replace('/(^|>)([^<]*?)(?=>)/', '$1$2&gt;<', $data);

			/* the last regexp causes '<>' entities to appear
			   (we need to do a lookahead assertion so that the last bracket can
			   be used in the next pass of the regexp) */
			$data = str_replace('<>', '', $data);
		}

		return $data;
	}


	/**
	 * undocumented function
	 *
	 * @return string
	 * @author Cal Henderson <cal@iamcal.com>
	 **/
	protected function checkTags($data)
	{
		$data = preg_replace('/<(.*?)>/se', "\$this->processTag(\$this->stripSingle('\\1'))", $data);

		foreach (array_keys($this->tag_counts) as $tag) {
			for ($i = 0; $i < $this->tag_counts[$tag]; $i++) {
				$data .= '</'.$tag.'>';
			}
		}

		return $data;
	}


	/**
	 * undocumented function
	 *
	 * @return string
	 * @author Cal Henderson <cal@iamcal.com>
	 **/
	protected function processTag($data)
	{
		// ending tags
		if (preg_match('/^\/([a-z0-9]+)/si', $data, $matches)) {
			$name = mb_strtolower($matches[1]);

			if (!in_array($name, array_keys($this->getOption('allowed')))) {
				return '';
			}

			if (!in_array($name, $this->getOption('no_close'))) {
				if (isset($this->tag_counts[$name]) && $this->tag_counts[$name]) {
					$this->tag_counts[$name]--;
					return '</'.$name.'>';
				}
			}
		}

		// starting tags
		if (preg_match('/^([a-z0-9]+)(.*?)(\/?)$/si', $data, $matches)) {
			$name   = mb_strtolower($matches[1]);
			$body   = $matches[2];
			$ending = $matches[3];

			if (!in_array($name, array_keys($this->getOption('allowed')))) {
				return '';
			}

			$params = '';
			preg_match_all('/([a-z0-9]+)=(["\'])(.*?)\\2/si',       $body, $matches_2, PREG_SET_ORDER); // <foo a="b" />
			preg_match_all('/([a-z0-9]+)(=)([^"\s\']+)/si',         $body, $matches_1, PREG_SET_ORDER); // <foo a=b />
			preg_match_all('/([a-z0-9]+)=(["\'])([^"\']*?)\s*$/si', $body, $matches_3, PREG_SET_ORDER); // <foo a="b />
			$matches = array_merge($matches_1, $matches_2, $matches_3);

			$allowed = $this->getOption('allowed');

			foreach ($matches as $match) {
				$pname = mb_strtolower($match[1]);
				if (in_array($pname, $allowed[$name])) {
					$value = $match[3];
					if (in_array($pname, $this->getOption('protocol_attributes'))) {
						$value = $this->processParamProtocol($value);
					}
					$params .= ' '.$pname.'="'.$value.'"';
				}
			}

			if (in_array($name, $this->getOption('always_close'))) {
				$ending = '';
			} elseif (in_array($name, $this->getOption('no_close'))) {
				$ending = ' /';
			}

			if (!$ending) {
				if (isset($this->tag_counts[$name])) {
					$this->tag_counts[$name]++;
				} else {
					$this->tag_counts[$name] = 1;
				}
			}

			if ($ending) {
				$ending = ' /';
			}

			return '<'.$name.$params.$ending.'>';
		}

		// comments
		if (preg_match('/^!--(.*)--$/si', $data)) {
			if ($this->getOption('strip_comments')) {
				return '';
			}

			return '<'.$data.'>';
		}

		// garbage, ignore it
		return '';
	}


	/**
	 * undocumented function
	 *
	 * @return string
	 * @author Cal Henderson <cal@iamcal.com>
	 **/
	protected function processParamProtocol($data)
	{
		$data = $this->validateEntities($data, true);

		if (preg_match('/^([^:]+)\:/si', $data, $matches)) {
			if (!in_array($matches[1], $this->getOption('allowed_protocols'))) {
				$data = '#'.substr($data, strlen($matches[1])+1);
			}
		}

		return $data;
	}


	/**
	 * undocumented function
	 *
	 * @return string
	 * @author Cal Henderson <cal@iamcal.com>
	 **/
	protected function processRemoveBlanks($data)
	{
		if (count($this->getOption('remove_blanks'))) {
			$tags = implode('|', $this->getOption('remove_blanks'));

			while (1) {
				$len  = strlen($data);
				$data = preg_replace("/<({$tags})(\s[^>]*)?(><\\/\\1>|\\/>)/", '', $data);

				if ($len == strlen($data)) {
					break;
				}
			}
		}

		return $data;
	}


	/**
	 * given some HTML input, find out if the non-HTML part is too 
	 * shouty. that is, does it solely consist of capital letters.
	 * if so, make it less shouty.
	 *
	 * @return string
	 * @author Cal Henderson <cal@iamcal.com>
	 **/
	protected function fixCase($data)
	{
		// extract only the (latin) characters in the string
		$data_notags = strip_tags($data);
		$data_notags = preg_replace('/[^a-zA-Z]/', '', $data_notags);

		// if there are fewer than 5, just allow as-is
		if (strlen($data_notags) < 5) {
			return $data;
		}

		// if there are lowercase characters somewhere, allow as-is
		if (preg_match('/[a-z]/', $data_notags)) {
			return $data;
		}

		/* we have more than 5 characters and they're all capitals. we want to
		   case-normalise */
		return preg_replace_callback(
			"/(>|^)([^<]+?)(<|$)/s",
			array($this, 'fixCaseInner'),
			$data
		);
	}


	/**
	 * given a block of non-HTML, filter it for shoutyness by lowercasing
	 * the whole thing and then capitalizing the first letter of each 
	 * 'sentence'.
	 *
	 * @return string
	 * @author Cal Henderson <cal@iamcal.com>
	 **/
	protected function fixCaseInner($m)
	{
		$data = mb_strtolower($m[2]);

		$data = preg_replace_callback(
			'/(^|[^\w\s\';,\\-])(\s*)([a-z])/',
			create_function(
				'$m',
				'return $m[1].$m[2].mb_strtoupper($m[3]);'
			),
			$data
		);

		return $m[1].$data.$m[3];
	}


	/**
	 * this function is called in two places - inside of each href-like
	 * attributes and then on the whole document. it's job is to make
	 * sure that anything that looks like an entity (starts with an 
	 * ampersand) is allowed, else corrects it.
	 *
	 * @return void
	 * @author 
	 **/
	function validateEntities($data, $in_attribute)
	{
		/* turn ascii characters into their actual characters, if requested.
		   we need to always do this inside URLs to avoid people using
		   entities or URL escapes to insert 'javascript:' or something like
		   that. outside of attributes, we optionally filter entities to
		   stop people from inserting text that they shouldn't (since it might
		   make it into a clickable URL via lib_autolink). */
		if ($in_attribute || $this->getOption('normalise_ascii_entities')) {
			$data = $this->decodeEntities($data, $in_attribute);
		}

		/* find every remaining ampersand in the string and check if it looks
		   like it's an entity (then validate it) or if it's not (then escape
		   it). */
		$data = preg_replace(
			'!&([^&;]*)(?=(;|&|$))!e',
			"\$this->checkEntity(\$this->stripSingle('\\1'), \$this->stripSingle('\\2'))",
			$data
		);

		return $data;
	}


	/**
	 * this function comes last in processing, to clean up data outside of tags.
	 *
	 * @return string
	 * @author Cal Henderson <cal@iamcal.com>
	 **/
	protected function cleanupNonTags($data)
	{
		return preg_replace_callback(
			"/(>|^)([^<]+?)(<|$)/s",
			array($this, 'cleanupNonTagsInner'),
			$data
		);
	}


	/**
	 * undocumented function
	 *
	 * @return void
	 * @author 
	 **/
	protected function cleanupNonTagsInner($m)
	{
		// first, deal with the entities
		$m[2] = $this->validateEntities($m[2], false);

		/* find any literal quotes outside of tags and replace them 
		   with &quot;. we call it last thing before returning. */
		$m[2] = str_replace('"', '&quot;', $m[2]);

		return $m[1].$m[2].$m[3];
	}


	/**
	 * this function gets passed the 'inside' and 'end' of a suspected 
	 * entity. the ampersand is not included, but must be part of the 
	 * return value. $term is a look-ahead assertion, so don't return 
	 * it.
	 *
	 * @return string
	 * @author Cal Henderson <cal@iamcal.com>
	 **/
	protected function checkEntity($preamble, $term)
	{
		/* if the terminating character is not a semi-colon, treat
		   this as a non-entity */
		if ($term != ';') {
			return '&amp;'.$preamble;
		}

		// if it's an allowed entity, go for it
		if ($this->isValidEntity($preamble)) {
			return '&'.$preamble;
		}

		// not an allowed antity, so escape the ampersand
		return '&amp;'.$preamble;
	}


	/**
	 * this function determines whether the body of an entity (the
	 * stuff between '&' and ';') is valid.
	 *
	 * @return bool
	 * @author Cal Henderson <cal@iamcal.com>
	 **/
	protected function isValidEntity($entity)
	{
		// numeric entity. over 127 is always allowed, else it's a pref
		if (preg_match('!^#([0-9]+)$!i', $entity, $m)) {
			return ($m[1] > 127) ? true : $this->getOption('allow_numbered_entities');
		}

		// hex entity. over 127 is always allowed, else it's a pref
		if (preg_match('!^#x([0-9a-f]+)$!i', $entity, $m)) {
			return (hexdec($m[1]) > 127) ? true : $this->getOption('allow_numbered_entities');
		}

		if (in_array($entity, $this->getOption('allowed_entities'))){
			return true;
		}

		return false;
	}


	/**
	 * within attributes, we want to convert all hex/dec/url escape sequences into
	 * their raw characters so that we can check we don't get stray quotes/brackets
	 * inside strings
	 *
	 * @return void
	 * @author Cal Henderson <cal@iamcal.com>
	 **/
	protected function decodeEntities($data, $in_attribute = true)
	{
		$data = preg_replace_callback('!(&)#(\d+);?!',         array($this, 'decodeDecEntity'), $data);
		$data = preg_replace_callback('!(&)#x([0-9a-f]+);?!i', array($this, 'decodeHexEntity'), $data);

		if ($in_attribute) {
			$data = preg_replace_callback('!(%)([0-9a-f]{2});?!i', array($this, 'decodeHexEntity'), $data);
		}

		return $data;
	}


	/**
	 * undocumented function
	 *
	 * @return void
	 * @author Cal Henderson <cal@iamcal.com>
	 **/
	function decodeHexEntity($m)
	{
		return $this->decodeNumEntity($m[1], hexdec($m[2]));
	}


	/**
	 * undocumented function
	 *
	 * @return void
	 * @author Cal Henderson <cal@iamcal.com>
	 **/
	protected function decodeDecEntity($m)
	{
		return $this->decodeNumEntity($m[1], intval($m[2]));
	}


	/**
	 * given a character code and the starting escape character (either '%' or '&'),
	 * return either a hex entity (if the character code is non-ascii), or a raw 
	 * character. remeber to escape XML characters!
	 *
	 * @return string
	 * @author Cal Henderson <cal@iamcal.com>
	 **/
	protected function decodeNumEntity($orig_type, $d)
	{
		if ($d < 0) {
			// treat control characters as spaces
			$d = 32; // space
		} elseif ($d > 127) {
			/* don't mess with high characters - what to replace them with is
			   character-set independant, so we leave them as entities. besides,
			   you can't use them to pass 'javascript:' etc (at present) */
			switch ($orig_type) {
				case '%': return '%'.dechex($d);
				case '&': return "&#$d;";
			}
		}

		return htmlspecialchars(chr($d));
	}


	/**
	 * undocumented function
	 *
	 * @return string
	 * @author Cal Henderson <cal@iamcal.com>
	 **/
	protected function stripSingle($data)
	{
		return str_replace(array('\\"', "\\0"), array('"', chr(0)), $data);
	}

}