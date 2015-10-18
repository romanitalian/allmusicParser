<?php
/*******************************************************************************
 * Version: 1.11 ($Rev: 175 $)
 * Website: http://sourceforge.net/projects/simplehtmldom/
 * Author: S.C. Chen <me578022@gmail.com>
 * Acknowledge: Jose Solorzano (https://sourceforge.net/projects/php-html/)
 * Contributions by:
 * Yousuke Kumakura (Attribute filters)
 * Vadim Voituk (Negative indexes supports of "find" method)
 * Antcs (Constructor with automatically load contents either text or file/url)
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *******************************************************************************/
include('SimpleHtmlDomNode.php');
include('Singleton.php');
include('HDom.php');


/**
 * simple html dom parser
 * Class SimpleHtmlDom
 */
class SimpleHtmlDom extends Singleton
{
    public $root = null;
    public $nodes = array();
    public $callback = null;
    public $lowercase = false;
    protected $pos;
    protected $doc;
    protected $char;
    protected $size;
    protected $cursor;
    protected $parent;
    protected $noise = array();
    protected $tokenBlank = " \t\r\n";
    protected $tokenEqual = ' =/>';
    protected $tokenSlash = " />\r\n\t";
    protected $tokenAttr = ' >';
    // use isset instead of in_array, performance boost about 30%...
    protected $self_closing_tags = array('img' => 1, 'br' => 1, 'input' => 1, 'meta' => 1, 'link' => 1, 'hr' => 1, 'base' => 1, 'embed' => 1, 'spacer' => 1);
    protected $block_tags = array('root' => 1, 'body' => 1, 'form' => 1, 'div' => 1, 'span' => 1, 'table' => 1);
    protected $optionalClosingTags = array(
        'tr' => array('tr' => 1, 'td' => 1, 'th' => 1),
        'th' => array('th' => 1),
        'td' => array('td' => 1),
        'li' => array('li' => 1),
        'dt' => array('dt' => 1, 'dd' => 1),
        'dd' => array('dd' => 1, 'dt' => 1),
        'dl' => array('dd' => 1, 'dt' => 1),
        'p' => array('p' => 1),
        'nobr' => array('nobr' => 1),
    );

    protected function __construct(){
    }

    public function getHtml($str = null){
        if ($str) {
            if (preg_match("/^http:\/\//i", $str) || is_file($str)) {
                $this->loadFile($str);
            } else {
                $this->load($str);
            }
        }
        return $this;
    }

    function __destruct(){
        $this->clear();
    }

    /**
     * load html from string
     * @param $str
     * @param bool|true $lowercase
     */
    function load($str, $lowercase = true){
        // prepare
        $this->prepare($str, $lowercase);
        // strip out comments
        $this->removeNoise("'<!--(.*?)-->'is");
        // strip out cdata
        $this->removeNoise("'<!\[CDATA\[(.*?)\]\]>'is", true);
        // strip out <style> tags
        $this->removeNoise("'<\s*style[^>]*[^/]>(.*?)<\s*/\s*style\s*>'is");
        $this->removeNoise("'<\s*style\s*>(.*?)<\s*/\s*style\s*>'is");
        // strip out <script> tags
        $this->removeNoise("'<\s*script[^>]*[^/]>(.*?)<\s*/\s*script\s*>'is");
        $this->removeNoise("'<\s*script\s*>(.*?)<\s*/\s*script\s*>'is");
        // strip out preformatted tags
        $this->removeNoise("'<\s*(?:code)[^>]*>(.*?)<\s*/\s*(?:code)\s*>'is");
        // strip out server side scripts
        $this->removeNoise("'(<\?)(.*?)(\?>)'s", true);
        // strip smarty scripts
        $this->removeNoise("'(\{\w)(.*?)(\})'s", true);

        // parsing
        while ($this->parse()) {
            ;
        }
        // end
        $this->root->_[HDom::INFO_END] = $this->cursor;
    }

    /**
     * load html from file
     */
    function loadFile($s){
        $content = @file_get_contents($s);
        $this->load($content, true);
    }

    /**
     * set callback function
     * @param $function_name
     */
    function set_callback($function_name){
        $this->callback = $function_name;
    }

    /**
     * remove callback function
     */
    function remove_callback(){
        $this->callback = null;
    }

    /**
     * save dom as string
     * @param string $filepath
     * @return mixed
     */
    function save($filepath = ''){
        $ret = $this->root->innertext();
        if ($filepath !== '') {
            @file_put_contents($filepath, $ret);
        }
        return $ret;
    }

    /**
     * find dom node by css selector
     * @param $selector
     * @param null $idx
     * @return int
     */
    function finded($selector, $idx = null){
        return count($this->find($selector, $idx));
    }

    function find($selector, $idx = null){
        return $this->root->find($selector, $idx);
    }

    /**
     * clean up memory due to php5 circular references memory leak...
     */
    function clear(){
        foreach ($this->nodes as $n) {
            $n->clear();
            $n = null;
        }
        if (isset($this->parent)) {
            $this->parent->clear();
            unset($this->parent);
        }
        if (isset($this->root)) {
            $this->root->clear();
            unset($this->root);
        }
        unset($this->doc);
        unset($this->noise);
    }

    function dump($show_attr = true){
        $this->root->dump($show_attr);
    }

    /**
     * prepare HTML data and init everything
     * @param $str
     * @param bool|true $lowercase
     */
    protected function prepare($str, $lowercase = true){
        $this->clear();
        $this->doc = $str;
        $this->pos = 0;
        $this->cursor = 1;
        $this->noise = array();
        $this->nodes = array();
        $this->lowercase = $lowercase;
        $this->root = new SimpleHtmlDomNode($this);
        $this->root->tag = 'root';
        $this->root->_[HDom::INFO_BEGIN] = - 1;
        $this->root->nodetype = HDom::TYPE_ROOT;
        $this->parent = $this->root;
        // set the length of content
        $this->size = strlen($str);
        if ($this->size > 0) {
            $this->char = $this->doc[0];
        }
    }

    /**
     * parse html content
     * @return bool
     */
    protected function parse(){
        if (($s = $this->copyUntilChar('<')) === '') {
            return $this->read_tag();
        }
        // text
        $node = new SimpleHtmlDomNode($this);
        ++ $this->cursor;
        $node->_[HDom::INFO_TEXT] = $s;
        $this->linkNodes($node, false);
        return true;
    }

    /**
     * read tag info
     * @return bool
     */
    protected function read_tag(){
        if ($this->char !== '<') {
            $this->root->_[HDom::INFO_END] = $this->cursor;
            return false;
        }
        $begin_tag_pos = $this->pos;
        $this->char = (++ $this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
        // end tag
        if ($this->char === '/') {
            $this->char = (++ $this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
            $this->skip($this->token_blank_t);
            $tag = $this->copyUntilChar('>');
            // skip attributes in end tag
            if (($pos = strpos($tag, ' ')) !== false) {
                $tag = substr($tag, 0, $pos);
            }
            $parent_lower = strtolower($this->parent->tag);
            $tag_lower = strtolower($tag);
            if ($parent_lower !== $tag_lower) {
                if (isset($this->optionalClosingTags[$parent_lower]) && isset($this->block_tags[$tag_lower])) {
                    $this->parent->_[HDom::INFO_END] = 0;
                    $org_parent = $this->parent;
                    while (($this->parent->parent) && strtolower($this->parent->tag) !== $tag_lower) {
                        $this->parent = $this->parent->parent;
                    }
                    if (strtolower($this->parent->tag) !== $tag_lower) {
                        $this->parent = $org_parent; // restore origonal parent
                        if ($this->parent->parent) {
                            $this->parent = $this->parent->parent;
                        }
                        $this->parent->_[HDom::INFO_END] = $this->cursor;
                        return $this->asTextNode($tag);
                    }
                } else {
                    if (($this->parent->parent) && isset($this->block_tags[$tag_lower])) {
                        $this->parent->_[HDom::INFO_END] = 0;
                        $org_parent = $this->parent;
                        while (($this->parent->parent) && strtolower($this->parent->tag) !== $tag_lower) {
                            $this->parent = $this->parent->parent;
                        }
                        if (strtolower($this->parent->tag) !== $tag_lower) {
                            $this->parent = $org_parent; // restore origonal parent
                            $this->parent->_[HDom::INFO_END] = $this->cursor;
                            return $this->asTextNode($tag);
                        }
                    } else {
                        if (($this->parent->parent) && strtolower($this->parent->parent->tag) === $tag_lower) {
                            $this->parent->_[HDom::INFO_END] = 0;
                            $this->parent = $this->parent->parent;
                        } else {
                            return $this->asTextNode($tag);
                        }
                    }
                }
            }
            $this->parent->_[HDom::INFO_END] = $this->cursor;
            if ($this->parent->parent) {
                $this->parent = $this->parent->parent;
            }
            $this->char = (++ $this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
            return true;
        }
        $node = new SimpleHtmlDomNode($this);
        $node->_[HDom::INFO_BEGIN] = $this->cursor;
        ++ $this->cursor;
        $tag = $this->copyUntil($this->tokenSlash);
        // doctype, cdata & comments...
        if (isset($tag[0]) && $tag[0] === '!') {
            $node->_[HDom::INFO_TEXT] = '<'.$tag.$this->copyUntilChar('>');
            if (isset($tag[2]) && $tag[1] === '-' && $tag[2] === '-') {
                $node->nodetype = HDom::TYPE_COMMENT;
                $node->tag = 'comment';
            } else {
                $node->nodetype = HDom::TYPE_UNKNOWN;
                $node->tag = 'unknown';
            }
            if ($this->char === '>') {
                $node->_[HDom::INFO_TEXT] .= '>';
            }
            $this->linkNodes($node, true);
            $this->char = (++ $this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
            return true;
        }
        // text
        if ($pos = strpos($tag, '<') !== false) {
            $tag = '<'.substr($tag, 0, - 1);
            $node->_[HDom::INFO_TEXT] = $tag;
            $this->linkNodes($node, false);
            $this->char = $this->doc[-- $this->pos]; // prev
            return true;
        }
        if (!preg_match("/^[\w-:]+$/", $tag)) {
            $node->_[HDom::INFO_TEXT] = '<'.$tag.$this->copyUntil('<>');
            if ($this->char === '<') {
                $this->linkNodes($node, false);
                return true;
            }
            if ($this->char === '>') {
                $node->_[HDom::INFO_TEXT] .= '>';
            }
            $this->linkNodes($node, false);
            $this->char = (++ $this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
            return true;
        }
        // begin tag
        $node->nodetype = HDom::TYPE_ELEMENT;
        $tag_lower = strtolower($tag);
        $node->tag = ($this->lowercase) ? $tag_lower : $tag;

        // handle optional closing tags
        if (isset($this->optionalClosingTags[$tag_lower])) {
            while (isset($this->optionalClosingTags[$tag_lower][strtolower($this->parent->tag)])) {
                $this->parent->_[HDom::INFO_END] = 0;
                $this->parent = $this->parent->parent;
            }
            $node->parent = $this->parent;
        }
        $guard = 0; // prevent infinity loop
        $space = array($this->copySkip($this->tokenBlank), '', '');
        // attributes
        do {
            if ($this->char !== null && $space[0] === '') {
                break;
            }
            $name = $this->copyUntil($this->tokenEqual);
            if ($guard === $this->pos) {
                $this->char = (++ $this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
                continue;
            }
            $guard = $this->pos;

            // handle endless '<'
            if ($this->pos >= $this->size - 1 && $this->char !== '>') {
                $node->nodetype = HDom::TYPE_TEXT;
                $node->_[HDom::INFO_END] = 0;
                $node->_[HDom::INFO_TEXT] = '<'.$tag.$space[0].$name;
                $node->tag = 'text';
                $this->linkNodes($node, false);
                return true;
            }

            // handle mismatch '<'
            if ($this->doc[$this->pos - 1] == '<') {
                $node->nodetype = HDom::TYPE_TEXT;
                $node->tag = 'text';
                $node->attr = array();
                $node->_[HDom::INFO_END] = 0;
                $node->_[HDom::INFO_TEXT] = substr($this->doc, $begin_tag_pos, $this->pos - $begin_tag_pos - 1);
                $this->pos -= 2;
                $this->char = (++ $this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
                $this->linkNodes($node, false);
                return true;
            }

            if ($name !== '/' && $name !== '') {
                $space[1] = $this->copySkip($this->tokenBlank);
                $name = $this->restoreNoise($name);
                if ($this->lowercase) {
                    $name = strtolower($name);
                }
                if ($this->char === '=') {
                    $this->char = (++ $this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
                    $this->parseAttr($node, $name, $space);
                } else {
                    //no value attr: nowrap, checked selected...
                    $node->_[HDom::INFO_QUOTE][] = HDom::QUOTE_NO;
                    $node->attr[$name] = true;
                    if ($this->char != '>') {
                        $this->char = $this->doc[-- $this->pos];
                    } // prev
                }
                $node->_[HDom::INFO_SPACE][] = $space;
                $space = array($this->copySkip($this->tokenBlank), '', '');
            } else {
                break;
            }
        } while ($this->char !== '>' && $this->char !== '/');

        $this->linkNodes($node, true);
        $node->_[HDom::INFO_ENDSPACE] = $space[0];

        // check self closing
        if ($this->copyUntilCharEscape('>') === '/') {
            $node->_[HDom::INFO_ENDSPACE] .= '/';
            $node->_[HDom::INFO_END] = 0;
        } else {
            // reset parent
            if (!isset($this->self_closing_tags[strtolower($node->tag)])) {
                $this->parent = $node;
            }
        }
        $this->char = (++ $this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
        return true;
    }

    /**
     * parse attributes
     * @param $node
     * @param $name
     * @param $space
     */
    protected function parseAttr($node, $name, &$space){
        $space[2] = $this->copySkip($this->tokenBlank);
        switch ($this->char) {
            case '"':
                $node->_[HDom::INFO_QUOTE][] = HDom::QUOTE_DOUBLE;
                $this->char = (++ $this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
                $node->attr[$name] = $this->restoreNoise($this->copyUntilCharEscape('"'));
                $this->char = (++ $this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
            break;
            case '\'':
                $node->_[HDom::INFO_QUOTE][] = HDom::QUOTE_SINGLE;
                $this->char = (++ $this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
                $node->attr[$name] = $this->restoreNoise($this->copyUntilCharEscape('\''));
                $this->char = (++ $this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
            break;
            default:
                $node->_[HDom::INFO_QUOTE][] = HDom::QUOTE_NO;
                $node->attr[$name] = $this->restoreNoise($this->copyUntil($this->tokenAttr));
        }
    }

    /**
     * link node's parent
     * @param $node
     * @param $is_child
     */
    protected function linkNodes(&$node, $is_child){
        $node->parent = $this->parent;
        $this->parent->nodes[] = $node;
        if ($is_child) {
            $this->parent->children[] = $node;
        }
    }

    /**
     * as a text node
     * @param $tag
     * @return bool
     */
    protected function asTextNode($tag){
        $node = new SimpleHtmlDomNode($this);
        ++ $this->cursor;
        $node->_[HDom::INFO_TEXT] = '</'.$tag.'>';
        $this->linkNodes($node, false);
        $this->char = (++ $this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
        return true;
    }

    protected function skip($chars){
        $this->pos += strspn($this->doc, $chars, $this->pos);
        $this->char = ($this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
    }

    protected function copySkip($chars){
        $pos = $this->pos;
        $len = strspn($this->doc, $chars, $pos);
        $this->pos += $len;
        $this->char = ($this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
        if ($len === 0) {
            return '';
        }
        return substr($this->doc, $pos, $len);
    }

    protected function copyUntil($chars){
        $pos = $this->pos;
        $len = strcspn($this->doc, $chars, $pos);
        $this->pos += $len;
        $this->char = ($this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
        return substr($this->doc, $pos, $len);
    }

    protected function copyUntilChar($char){
        if ($this->char === null) {
            return '';
        }
        if (($pos = strpos($this->doc, $char, $this->pos)) === false) {
            $ret = substr($this->doc, $this->pos, $this->size - $this->pos);
            $this->char = null;
            $this->pos = $this->size;
            return $ret;
        }
        if ($pos === $this->pos) {
            return '';
        }
        $pos_old = $this->pos;
        $this->char = $this->doc[$pos];
        $this->pos = $pos;
        return substr($this->doc, $pos_old, $pos - $pos_old);
    }

    protected function copyUntilCharEscape($char){
        if ($this->char === null) {
            return '';
        }
        $start = $this->pos;
        while (1) {
            if (($pos = strpos($this->doc, $char, $start)) === false) {
                $ret = substr($this->doc, $this->pos, $this->size - $this->pos);
                $this->char = null;
                $this->pos = $this->size;
                return $ret;
            }
            if ($pos === $this->pos) {
                return '';
            }
            if ($this->doc[$pos - 1] === '\\') {
                $start = $pos + 1;
                continue;
            }
            $pos_old = $this->pos;
            $this->char = $this->doc[$pos];
            $this->pos = $pos;
            return substr($this->doc, $pos_old, $pos - $pos_old);
        }
    }

    /**
     * remove noise from html content
     * @param $pattern
     * @param bool|false $remove_tag
     */
    protected function removeNoise($pattern, $remove_tag = false){
        $count = preg_match_all($pattern, $this->doc, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        for ($i = $count - 1; $i > - 1; -- $i) {
            $key = '___noise___'.sprintf('% 3d', count($this->noise) + 100);
            $idx = ($remove_tag) ? 0 : 1;
            $this->noise[$key] = $matches[$i][$idx][0];
            $this->doc = substr_replace($this->doc, $key, $matches[$i][$idx][1], strlen($matches[$i][$idx][0]));
        }
        // reset the length of content
        $this->size = strlen($this->doc);
        if ($this->size > 0) {
            $this->char = $this->doc[0];
        }
    }

    /**
     * Restore noise to html content
     * @param $text
     * @return string
     */
    function restoreNoise($text){
        while (($pos = strpos($text, '___noise___')) !== false) {
            $key = '___noise___'.$text[$pos + 11].$text[$pos + 12].$text[$pos + 13];
            if (isset($this->noise[$key])) {
                $text = substr($text, 0, $pos).$this->noise[$key].substr($text, $pos + 14);
            }
        }
        return $text;
    }

    /**
     * @return mixed
     */
    function __toString(){
        return $this->root->innertext();
    }

    function __get($name){
        switch ($name) {
            case 'outertext':
                return $this->root->innertext();
            case 'innertext':
                return $this->root->innertext();
            case 'plaintext':
                return $this->root->text();
        }
    }

    function childNodes($idx = - 1){
        return $this->root->childNodes($idx);
    }

    function firstChild(){
        return $this->root->first_child();
    }

    function lastChild(){
        return $this->root->last_child();
    }

    function getElementById($id){
        return $this->find("#$id", 0);
    }

    function getElementsById($id, $idx = null){
        return $this->find("#$id", $idx);
    }

    function getElementByTagName($name){
        return $this->find($name, 0);
    }

    function getElementsByTagName($name, $idx = - 1){
        return $this->find($name, $idx);
    }

    private function __clone(){
    }

    private function __wakeup(){
    }
}
