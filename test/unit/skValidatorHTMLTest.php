<?php

require_once(dirname(__FILE__).'/../bootstrap/unit.php');

$t = new lime_test(227);
$v = new skValidatorHTML();

// allow protected methods to be called as public
function getMethod($name)
{
	$class = new ReflectionClass('skValidatorHTML');
	$method = $class->getMethod($name);
	$method->setAccessible(true);
	return $method;
}

// basics
$t->is('','');
$t->is($v->clean('hello'),'hello');

// balancing tags
$t->is($v->clean('<strong>hello'), '<strong>hello</strong>');
$t->is($v->clean('hello<strong>'), 'hello');
$t->is($v->clean('hello<strong>world'), 'hello<strong>world</strong>');
$t->is($v->clean('hello</strong>'), 'hello');
$t->is($v->clean('hello<strong/>'), 'hello');
$t->is($v->clean('hello<strong/>world'), 'hello<strong>world</strong>');
$t->is($v->clean('<strong><strong><strong>hello'), '<strong><strong><strong>hello</strong></strong></strong>');
$t->is($v->clean('</strong><strong>'), '');

// end slashes
$t->is($v->clean('<img>'), '<img />');
$t->is($v->clean('<img/>'), '<img />');
$t->is($v->clean('<strong/></strong>'), '');

// balancing angle brakets
$v->setOption('always_make_tags', true);
$t->is($v->clean('<img src="foo"'), '<img src="foo" />');
$t->is($v->clean('strong>'), '');
$t->is($v->clean('strong>hello'), '<strong>hello</strong>');
$t->is($v->clean('<img src="foo"/'), '<img src="foo" />');
$t->is($v->clean('>'), '');
$t->is($v->clean('hello<strong'), 'hello');
$t->is($v->clean('strong>foo'), '<strong>foo</strong>');
$t->is($v->clean('><strong'), '');
$t->is($v->clean('strong><'), '');
$t->is($v->clean('><strong>'), '');
$t->is($v->clean('foo bar>'), '');
$t->is($v->clean('foo>bar>baz'), 'baz');
$t->is($v->clean('foo>bar'), 'bar');
$t->is($v->clean('foo>bar>'), '');
$t->is($v->clean('>foo>bar'), 'bar');
$t->is($v->clean('>foo>bar>'), '');

$v->setOption('always_make_tags', false);
$t->is($v->clean('<img src="foo"'), '&lt;img src=&quot;foo&quot;');
$t->is($v->clean('strong>'), 'strong&gt;');
$t->is($v->clean('strong>hello'), 'strong&gt;hello');
$t->is($v->clean('<img src="foo"/'), '&lt;img src=&quot;foo&quot;/');
$t->is($v->clean('>'), '&gt;');
$t->is($v->clean('hello<strong'), 'hello&lt;strong');
$t->is($v->clean('strong>foo'), 'strong&gt;foo');
$t->is($v->clean('><strong'), '&gt;&lt;strong');
$t->is($v->clean('strong><'), 'strong&gt;&lt;');
$t->is($v->clean('><strong>'), '&gt;');
$t->is($v->clean('foo bar>'), 'foo bar&gt;');
$t->is($v->clean('foo>bar>baz'), 'foo&gt;bar&gt;baz');
$t->is($v->clean('foo>bar'), 'foo&gt;bar');
$t->is($v->clean('foo>bar>'), 'foo&gt;bar&gt;');
$t->is($v->clean('>foo>bar'), '&gt;foo&gt;bar');
$t->is($v->clean('>foo>bar>'), '&gt;foo&gt;bar&gt;');

// attributes
$t->is($v->clean('<img src=foo>'), '<img src="foo" />');
$t->is($v->clean('<img asrc=foo>'), '<img />');
$t->is($v->clean('<img src=test test>'), '<img src="test" />');

// disallowed tags
$t->is($v->clean('<script>'), '');
$t->is($v->clean('<script/>'), '');
$t->is($v->clean('</script>'), '');
$t->is($v->clean('<script woo=yay>'), '');
$t->is($v->clean('<script woo="yay">'), '');
$t->is($v->clean('<script woo="yay>'), '');

$v->setOption('always_make_tags', true);
$t->is($v->clean('<script'), '');
$t->is($v->clean('<script woo="yay<strong>'), '');
$t->is($v->clean('<script woo="yay<strong>hello'), '<strong>hello</strong>');
$t->is($v->clean('<script<script>>'), '');
$t->is($v->clean('<<script>script<script>>'), 'script');
$t->is($v->clean('<<script><script>>'), '');
$t->is($v->clean('<<script>script>>'), '');
$t->is($v->clean('<<script<script>>'), '');

$v->setOption('always_make_tags', false);
$t->is($v->clean('<script'), '&lt;script');
$t->is($v->clean('<script woo="yay<strong>'), '&lt;script woo=&quot;yay');
$t->is($v->clean('<script woo="yay<strong>hello'), '&lt;script woo=&quot;yay<strong>hello</strong>');
$t->is($v->clean('<script<script>>'), '&lt;script&gt;');
$t->is($v->clean('<<script>script<script>>'), '&lt;script&gt;');
$t->is($v->clean('<<script><script>>'), '&lt;&gt;');
$t->is($v->clean('<<script>script>>'), '&lt;script&gt;&gt;');
$t->is($v->clean('<<script<script>>'), '&lt;&lt;script&gt;');

// bad protocols
$t->is($v->clean('<a href="http://foo">bar</a>'), '<a href="http://foo">bar</a>');
$t->is($v->clean('<a href="ftp://foo">bar</a>'), '<a href="ftp://foo">bar</a>');
$t->is($v->clean('<a href="mailto:foo">bar</a>'), '<a href="mailto:foo">bar</a>');
$t->is($v->clean('<a href="javascript:foo">bar</a>'), '<a href="#foo">bar</a>');
$t->is($v->clean('<a href="java script:foo">bar</a>'), '<a href="#foo">bar</a>');
$t->is($v->clean('<a href="java'."\t".'script:foo">bar</a>'), '<a href="#foo">bar</a>');
$t->is($v->clean('<a href="java'."\n".'script:foo">bar</a>'), '<a href="#foo">bar</a>');
$t->is($v->clean('<a href="java'."\r".'script:foo">bar</a>'), '<a href="#foo">bar</a>');
$t->is($v->clean('<a href="java'.chr(1).'script:foo">bar</a>'), '<a href="#foo">bar</a>');
$t->is($v->clean('<a href="java'.chr(0).'script:foo">bar</a>'), '<a href="#foo">bar</a>');
$t->is($v->clean('<a href="jscript:foo">bar</a>'), '<a href="#foo">bar</a>');
$t->is($v->clean('<a href="vbscript:foo">bar</a>'), '<a href="#foo">bar</a>');
$t->is($v->clean('<a href="view-source:foo">bar</a>'), '<a href="#foo">bar</a>');
$t->is($v->clean('<a href="  javascript:foo">bar</a>'), '<a href="#foo">bar</a>');
$t->is($v->clean('<a href="jAvAsCrIpT:foo">bar</a>'), '<a href="#foo">bar</a>');

// bad protocols with entities (semicolons)
$t->is($v->clean('<a href="&#106;&#97;&#118;&#97;&#115;&#99;&#114;&#105;&#112;&#116;&#58;foo">bar</a>'), '<a href="#foo">bar</a>');
$t->is($v->clean('<a href="&#0000106;&#0000097;&#0000118;&#0000097;&#0000115;&#0000099;&#0000114;&#0000105;&#0000112;&#0000116;&#0000058;foo">bar</a>'), '<a href="#foo">bar</a>');
$t->is($v->clean('<a href="&#x6A;&#x61;&#x76;&#x61;&#x73;&#x63;&#x72;&#x69;&#x70;&#x74;&#x3A;foo">bar</a>'), '<a href="#foo">bar</a>');

// bad protocols with entities (no semicolons)
$t->is($v->clean('<a href="&#106&#97&#118&#97&#115&#99&#114&#105&#112&#116&#58;foo">bar</a>'), '<a href="#foo">bar</a>');
$t->is($v->clean('<a href="&#0000106&#0000097&#0000118&#0000097&#0000115&#0000099&#0000114&#0000105&#0000112&#0000116&#0000058foo">bar</a>'), '<a href="#foo">bar</a>');
$t->is($v->clean('<a href="&#x6A&#x61&#x76&#x61&#x73&#x63&#x72&#x69&#x70&#x74&#x3A;foo">bar</a>'), '<a href="#foo">bar</a>');

// self-closing tags
$t->is($v->clean('<img src="a">'), '<img src="a" />');
$t->is($v->clean('<img src="a">foo</img>'), '<img src="a" />foo');
$t->is($v->clean('</img>'), '');

// typos
$t->is($v->clean('<strong>test<strong/>'), '<strong>test</strong>');
$t->is($v->clean('<strong/>test<strong/>'), '<strong>test</strong>');
$t->is($v->clean('<strong/>test'), '<strong>test</strong>');

// empty tags
$t->is($v->clean('woo<strong></strong>'), 'woo');
$t->is($v->clean('<strong></strong>woo<strong></strong>'), 'woo');
$t->is($v->clean('<strong></strong>woo<a></a>'), 'woo');
$t->is($v->clean('woo<a/>'), 'woo');
$t->is($v->clean('woo<a/><strong></strong>'), 'woo');
$t->is($v->clean('woo<a><strong></strong></a>'), 'woo');

// case conversion
$fix_case = getMethod('fixCase');
$t->is($fix_case->invokeArgs($v, array('hello world')), 'hello world');
$t->is($fix_case->invokeArgs($v, array('Hello world')), 'Hello world');
$t->is($fix_case->invokeArgs($v, array('Hello World')), 'Hello World');
$t->is($fix_case->invokeArgs($v, array('HELLO World')), 'HELLO World');
$t->is($fix_case->invokeArgs($v, array('HELLO WORLD')), 'Hello world');
$t->is($fix_case->invokeArgs($v, array('<strong>HELLO WORLD')), '<strong>Hello world');
$t->is($fix_case->invokeArgs($v, array('<B>HELLO WORLD')), '<B>Hello world');
$t->is($fix_case->invokeArgs($v, array('HELLO. WORLD')), 'Hello. World');
$t->is($fix_case->invokeArgs($v, array('HELLO<strong> WORLD')), 'Hello<strong> World');
$t->is($fix_case->invokeArgs($v, array("DOESN'T")), "Doesn't");
$t->is($fix_case->invokeArgs($v, array('COMMA, TEST')), 'Comma, test');
$t->is($fix_case->invokeArgs($v, array('SEMICOLON; TEST')), 'Semicolon; test');
$t->is($fix_case->invokeArgs($v, array('DASH - TEST')), 'Dash - test');

// comments
$v->setOption('strip_comments', false);
$t->is($v->clean('hello <!-- foo --> world'), 'hello <!-- foo --> world');
$t->is($v->clean('hello <!-- <foo --> world'), 'hello <!-- &lt;foo --> world');
$t->is($v->clean('hello <!-- foo> --> world'), 'hello <!-- foo&gt; --> world');
$t->is($v->clean('hello <!-- <foo> --> world'), 'hello <!-- &lt;foo&gt; --> world');

$v->setOption('strip_comments', true);
$t->is($v->clean('hello <!-- foo --> world'), 'hello  world');
$t->is($v->clean('hello <!-- <foo --> world'), 'hello  world');
$t->is($v->clean('hello <!-- foo> --> world'), 'hello  world');
$t->is($v->clean('hello <!-- <foo> --> world'), 'hello  world');

// br - shouldn't get caught by the empty 'b' tag remover
$v->setOption('allowed', array_merge($v->getOption('allowed'), array('br' => array())));
$v->setOption('no_close', array_merge($v->getOption('no_close'), array('br')));
$t->is($v->clean('foo<br>bar'), 'foo<br />bar');
$t->is($v->clean('foo<br />bar'), 'foo<br />bar');

// stray quotes
$t->is($v->clean('foo"bar'), 'foo&quot;bar');
$t->is($v->clean('foo"'), 'foo&quot;');
$t->is($v->clean('"bar'), '&quot;bar');
$t->is($v->clean('<a href="foo"bar">baz</a>'), '<a href="foo">baz</a>');
$t->is($v->clean('<a href=foo"bar>baz</a>'), '<a href="foo">baz</a>');

// correct entities should not be touched
$t->is($v->clean('foo&amp;bar'), 'foo&amp;bar');
$t->is($v->clean('foo&quot;bar'), 'foo&quot;bar');
$t->is($v->clean('foo&lt;bar'), 'foo&lt;bar');
$t->is($v->clean('foo&gt;bar'), 'foo&gt;bar');

// bare ampersands should be fixed up
$t->is($v->clean('foo&bar'), 'foo&amp;bar');
$t->is($v->clean('foo&'), 'foo&amp;');

// numbered entities
$v->setOption('allow_numbered_entities', true);
$t->is($v->clean('foo&#123;bar'), 'foo&#123;bar');
$t->is($v->clean('&#123;bar'), '&#123;bar');
$t->is($v->clean('foo&#123;'), 'foo&#123;');

$v->setOption('allow_numbered_entities', false);
$t->is($v->clean('foo&#123;bar'), 'foo&amp;#123;bar');
$t->is($v->clean('&#123;bar'), '&amp;#123;bar');
$t->is($v->clean('foo&#123;'), 'foo&amp;#123;');

// other entities
$t->is($v->clean('foo&bar;baz'), 'foo&amp;bar;baz');	
$v->setOption('allowed_entities', array_merge($v->getOption('allowed_entities'), array('bar')));
$t->is($v->clean('foo&bar;baz'), 'foo&bar;baz');

// entity decoder - '<'
$entities = explode(' ', '%3c %3C &#60 &#0000060 &#60; &#0000060; &#x3c &#x000003c &#x3c; &#x000003c; &#X3c &#X000003c &#X3c; &#X000003c; &#x3C &#x000003C &#x3C; &#x000003C; &#X3C &#X000003C &#X3C; &#X000003C;');
$decode_entities = getMethod('decodeEntities');

foreach ($entities as $entity) {
	$t->is($decode_entities->invokeArgs($v, array($entity)), '&lt;');
}

$t->is($decode_entities->invokeArgs($v, array('%3c&#256;&#x100;')), '&lt;&#256;&#256;');
$t->is($decode_entities->invokeArgs($v, array('%3c&#250;&#xFA;')), '&lt;&#250;&#250;');
$t->is($decode_entities->invokeArgs($v, array('%3c%40%aa;')), '&lt;@%aa');


// character checks
$t->is($v->clean('\\'), '\\');
$t->is($v->clean('/'), '/');
$t->is($v->clean("'"), "'");
$t->is($v->clean('a'.chr(0).'b'), 'a'.chr(0).'b');
$t->is($v->clean('\\/\'!@#'), '\\/\'!@#');
$t->is($v->clean('$foo'), '$foo');

// this test doesn't contain &"<> since they get changed
$all_chars = ' !#$%\'()*+,-./0123456789:;=?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\\]^_`abcdefghijklmnopqrstuvwxyz{|}~';
$t->is($all_chars, $all_chars);

// single quoted entities
$t->is($v->clean("<img src=foo.jpg />"), '<img src="foo.jpg" />');
$t->is($v->clean("<img src='foo.jpg' />"), '<img src="foo.jpg" />');
$t->is($v->clean("<img src=\"foo.jpg\" />"), '<img src="foo.jpg" />');

// unbalanced quoted entities
$t->is($v->clean("<img src=\"foo.jpg />"), '<img src="foo.jpg" />');
$t->is($v->clean("<img src='foo.jpg />"), '<img src="foo.jpg" />');
$t->is($v->clean("<img src=foo.jpg\" />"), '<img src="foo.jpg" />');
$t->is($v->clean("<img src=foo.jpg' />"), '<img src="foo.jpg" />');

// url escape sequences
$t->is($v->clean('<a href="woo.htm%22%20bar=%22#">foo</a>'), '<a href="woo.htm&quot; bar=&quot;#">foo</a>');
$t->is($v->clean('<a href="woo.htm%22%3E%3C/a%3E%3Cscript%3E%3C/script%3E%3Ca%20href=%22#">foo</a>'), '<a href="woo.htm&quot;&gt;&lt;/a&gt;&lt;script&gt;&lt;/script&gt;&lt;a href=&quot;#">foo</a>');
$t->is($v->clean('<a href="woo.htm%aa">foo</a>'), '<a href="woo.htm%aa">foo</a>');


/**
 * this set of tests shows the differences between the different combinations
 * of entity options
 **/
$v->setOption('allow_numbered_entities', false);
$v->setOption('normalise_ascii_entities', false);
$t->is($v->clean('&#x3b;'), '&amp;#x3b;');
$t->is($v->clean('&#x3B;'), '&amp;#x3B;');
$t->is($v->clean('&#59;'), '&amp;#59;');
$t->is($v->clean('%3B'), '%3B');
$t->is($v->clean('&#x26;'), '&amp;#x26;');
$t->is($v->clean('&#38;'), '&amp;#38;');
$t->is($v->clean('&#xcc;'), '&#xcc;');
$t->is($v->clean('<a href="http://&#x3b;>x</a>'), '<a href="http://;">x</a>');
$t->is($v->clean('<a href="http://&#x3B;>x</a>'), '<a href="http://;">x</a>');
$t->is($v->clean('<a href="http://&#59;>x</a>'), '<a href="http://;">x</a>');

$v->setOption('allow_numbered_entities', true);
$v->setOption('normalise_ascii_entities', false);
$t->is($v->clean('&#x3b;'), '&#x3b;');
$t->is($v->clean('&#x3B;'), '&#x3B;');
$t->is($v->clean('&#59;'), '&#59;');
$t->is($v->clean('%3B'), '%3B');
$t->is($v->clean('&#x26;'), '&#x26;');
$t->is($v->clean('&#38;'), '&#38;');
$t->is($v->clean('&#xcc;'), '&#xcc;');
$t->is($v->clean('<a href="http://&#x3b;>x</a>'), '<a href="http://;">x</a>');
$t->is($v->clean('<a href="http://&#x3B;>x</a>'), '<a href="http://;">x</a>');
$t->is($v->clean('<a href="http://&#59;>x</a>'), '<a href="http://;">x</a>');

for ($i=0; $i<=1; $i++) {
	$v->setOption('allow_numbered_entities', (bool) $i);
	$v->setOption('normalise_ascii_entities', true);
	$t->is($v->clean('&#x3b;'), ';');
	$t->is($v->clean('&#x3B;'), ';');
	$t->is($v->clean('&#59;'), ';');
	$t->is($v->clean('%3B'), '%3B');
	$t->is($v->clean('&#x26;'), '&amp;');
	$t->is($v->clean('&#38;'), '&amp;');
	$t->is($v->clean('&#xcc;'), '&#204;');
	$t->is($v->clean('<a href="http://&#x3b;>x</a>'), '<a href="http://;">x</a>');
	$t->is($v->clean('<a href="http://&#x3B;>x</a>'), '<a href="http://;">x</a>');
	$t->is($v->clean('<a href="http://&#59;>x</a>'), '<a href="http://;">x</a>');
}