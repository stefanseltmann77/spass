<?php
/**
 * PHP-Framework for rapid developement of single page applications
 *
 * @license   http://www.opensource.org/licenses/mit-license.html  MIT License
 * @author  Stefan Seltmann
 *
 */

namespace spass;

use ArrayObject;
use spass\database\SPAppDbHandle as SPAppDbHandle;

$spassCoreConf = parse_ini_file("spass_core_conf.php", TRUE);

/**
 * The following function and if-clause a just a precaution against malicios REQUEST-content.
 *
 * @param $value
 * @return array|string
 */
function stripslashes_deep($value){
    return is_array($value) ? array_map('stripslashes_deep', $value):stripslashes($value);
}
if (get_magic_quotes_gpc()) { //still included for legacy support
    $_REQUEST = array_map('stripslashes_deep', $_REQUEST);
    $_GET = array_map('stripslashes_deep', $_GET);
    $_POST = array_map('stripslashes_deep', $_POST);
    $_COOKIE = array_map('stripslashes_deep', $_COOKIE);
}
if (isset($_REQUEST['_SESSION'])) die("no way with register_globals");

/**
 * Function fo raise a customized error
 * @param $error_msg
 * @todo Rework as Error Class
 */
function SPA_Error($error_msg){
    $backtrace = debug_backtrace();
    foreach($backtrace as $row){
        echo $row['line']." in ".$row['function']."\tin\t".$row['file']."<BR />";
    }
    trigger_error($error_msg, E_USER_ERROR);
}

/**
 * Class HtmlObject
 *
 * A very basic object for html-tags or html-input elements.
 * @author Stefan Seltmann
 * @package spass
 */
trait HtmlObject{

    /**
     * Instance of the SPApp, that includes and displays the Html-elements
     * @var SPApp
     */
    protected $rootApp;

    /**
     * html ID of the html element
     * @var string
     */
    protected $htmlID = null;

    /**
     * Storage for all key value pairs of a html tag. It will be converted to a string during plotting.
     * @var array
     */
    public $tagContentArray = [];

    /**
     * CSS content of a tag as an array. It will be converted to a string during plotting.
     * @var array
     */
    public $cssStyleArray = [];

    /**
     * Sets the instance of an root application in which the html-code is displayed.
     * @param SPApp $rootApp
     * @return HtmlContainer|HtmlInput
     */
    function setRootApp(&$rootApp){
        $this->rootApp = $rootApp;
        return $this;
    }

    /**
     * Gets the instance of an root application in which the html code is displayed.
     * @return SPApp
     */
    function &getRootApp(){
        return $this->rootApp;
    }

    /**
     * Add content to the tag of the html-element
     *
     * Since the tag content is stored as an array, the function also only accepts arrays
     * @param array $tagContentInput
     * @return HtmlContainer|HtmlInput|HtmlTable
     */
    function addTagContent(array $tagContentInput){
        $this->tagContentArray = array_merge($this->tagContentArray, $tagContentInput);
        return $this;
    }

    /**
     * Add a css style element to the html object.
     *
     * Since the elements are stored as arrays and via key=>value pair, only arrays are allowed in the first place.
     * @param array $style
     * @return HtmlContainer|HtmlInput|HtmlTable
     */
    function addStyle(array $style){
        $this->cssStyleArray = array_merge($this->cssStyleArray, $style);
        return $this;
    }

    /**
     * Sets the css style and replaces all existing styles.
     * @param array $style
     * @return HtmlContainer|HtmlInput
     */
    function setStyle(array $style){
        $this->cssStyleArray = $style;
        return $this;
    }

    /**
     * Sets the css class and replaces all existing class.
     * @param string $htmlClass name of the class
     * @return HtmlContainer|HtmlInput
     */
    function setClass($htmlClass){
        $this->tagContentArray['class'] = $htmlClass;
        return $this;
    }

    /**
     * Add css class to element.
     * @param string $htmlClass
     * @return HtmlContainer|HtmlInput
     */
    function addClass($htmlClass){
        if(isset($this->tagContentArray['class'])){
            $this->tagContentArray['class'] .= ' '.$htmlClass;
        }else{
            $this->tagContentArray['class'] = $htmlClass;
        }
        return $this;
    }

    /**
     * Retrieve the name of the objects html class if set.
     * @return string|null name of html class
     */
    function getClass(){
        return isset($this->tagContentArray['class'])?$this->tagContentArray['class']:null;
    }

    /**
     * Sets the html id of the element
     * @param string $htmlID name of html id
     * @return HtmlContainer|HtmlInput
     */
    function setID($htmlID){
        $this->tagContentArray['id'] = $htmlID;
        $this->htmlID = $htmlID; //also as string for later convenience;
        return $this;
    }

    /**
     * Retrieve the name of the objects html id, if set
     * @return string|null name of html id
     */
    function getID(){
        return isset($this->tagContentArray['id'])?$this->tagContentArray['id']:null;
    }

}


/**
 * Class HtmlContainer
 * @author Stefan Seltmann
 *
 * Basic abstract class for all html elements that act as containers for others.
 */
abstract class HtmlContainer extends ArrayObject{

    use HtmlObject;

    /**
     * Variable for the content of a tag.
     * It is built and inserted as a string during plotting of the element from the tagContentArray
     * @var string
     */
    protected $tagContent = "";

    /**
     * Number of indentations for code of the element
     * @var int
     */
    public $indents = 0; //FIXME why public

    /**
     * Resolved string containing $this->indents x indentations.
     * @var string
     */
    public $indent = ''; //FIXME why public

    /**
     * Reference to parent container in which the element ist nested
     * @var HtmlContainer
     */
    protected $parentContainer;

    /**
     * container for all child content
     * @var array containing HtmlObject
     */
    public $content = [];

    /**
     * counter for modifications of duplicate element names
     * @var int
     */
    protected $contentNumerator = 0;

    /**
     * name of the object
     * @var string
     */
    protected $name = null;

    public function offsetSet($offset, $value) {
        if (is_null($offset)) {
            $this->content[] = $value;
        } else {
            $this->content[$offset] = $value;
        }
    }

    /**
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet($offset) {
        return isset($this->content[$offset]) ? $this->content[$offset] : false;
    }

    /**
     * Add a child content to the object
     * @param HtmlContainer|HtmlInput|string $content
     * @return HtmlContainer|HtmlInput
     */
    function add($content){
        if ($content instanceof HtmlContainer or $content instanceof HtmlInput){
            $content->indents = $this->indents + 1;
            $content->setRootApp($this->rootApp);
            $content->parentContainer = &$this;
            if($content instanceof HtmlContainer){
                $id = $content->getID();
                if(null !== $id){
                    if(isset($this->content[$id])){
                        trigger_error('Object-IDs can only be assigned once for a HtmlContainer! Duplicate-ID '.$id, E_USER_ERROR);
                    }else{
                        $this[$id]= $content;
                    }
                }else{
                    $this[]= $content;
                }
            }elseif($content instanceof HtmlInput){
                $content->resolveInput();
                $name = $content->getName();
                if(isset($this->content[$name])){//neccessary check to separate contents with the same name.
                    $this->contentNumerator += 1;
                    $this[$name.$this->contentNumerator]=$content;
                }else{
                    $this[$name]= $content;
                }
            }
            return $content;
        }else{
            $this[]= $content;
            return $this;
        }
    }

    /**
     * Retrieve a content from a HtmlContainer based on it's html-id
     * @param $id
     * @deprecated
     * @return HtmlContainer|HtmlInput
     */
    function getContentByID($id){
        return $this->contents[$id];
    }

    /**
     * Inserts horizontal line
     * @return HtmlContainer
     */
    function HR(){
        $this->add(str_repeat("\t", $this->indents)."<hr />\n");
        return $this;
    }

    /**
     * Inserts as number of line breaks in html
     * @param int $count: number of line breaks, default = 1
     * @return HtmlContainer
     */
    function BR($count = 1){
        $this->add(str_repeat("\t", $this->indents).str_repeat("<br />", $count)."\n");
        return $this;
    }

    /**
     * Add a H1 headline to the html structure
     * @param string $input: content to be displayed in the headline tag
     * @param string $htmlID: html-id for the headline tag
     * @return HtmlHeadline
     */
    function H1($input, $htmlID = null){
        $headline = new HtmlHeadline(1);
        if(null !== $htmlID){
            $headline->setID($htmlID);
        }
        $headline->add($input);
        $this->add($headline);
        return $headline;
    }

    /**
     * Add a H2 headline to the html structure
     * @param string $input: content to be displayed in the headline tag
     * @return HtmlHeadline
     */
    function H2($input){
        $this->add($headline = new HtmlHeadline(2));
        $headline->add($input);
        return $headline;
    }

    /**
     * Add a H3 headline to the html structure
     * @param string $input: content to be displayed in the headline tag
     * @return HtmlHeadline
     */
    function H3($input){
        $this->add($headline = new HtmlHeadline(3));
        $headline->add($input);
        return $headline;
    }

    /**
     * @param string|null $htmlID
     * @return HtmlDiv
     */
    function DIV($htmlID = null){
        $HtmlDiv = new HtmlDiv();
        if(null !== $htmlID){
            $HtmlDiv->setID($htmlID);
        }
        $this->add($HtmlDiv);
        return $HtmlDiv;
    }

    /**
     * @param string $title
     * @param string $action
     * @return HtmlForm
     */
    function FORM($title = null, $action = null){
        return $this->add(new HtmlForm($title, $action));
    }

    /**
     * @param string $input
     * @return HtmlP
     */
    function P($input){
        $this->add($p = new HtmlP());
        $p->add($input);
        return $p;
    }

    /**
     * @param string $input
     * @return HtmlHeader
     */
    function HEADER($input = ''){
        $this->add($p = new HtmlHeader());
        $p->add($input);
        return $p;
    }

    /**
     * @param string $content
     * @return HtmlSpan
     */
    function SPAN($content = ''){
        return $this->add(new HtmlSpan($content));
    }

    /**
     * @param $src
     * @return HtmlScript
     */
    function SCRIPT($src = ''){
        return $this->add(new HtmlScript($src));
    }

    /**
     * Short tag for inclusion of separate files containing scripts.
     *
     * It is assumed, that the script source is javascript
     * @param string $src
     * @return HtmlScript
     */
    function SCRIPTSOURCE($src){
        $script = new HtmlScript();
        $script->addTagContent(['src'=>$src]);
        return $this->add($script);
    }

    /**
     * @param string $input
     * @return HtmlLabel
     */
    function LABEL($input = ''){
        return $this->add(new HtmlLabel($input));
    }

    /**
     * @param string $destination
     * @param string $label
     * @param string $target
     * @return HtmlLink
     */
    function LINK($destination, $label = null, $target = "_blank"){
        return $this->add(new HtmlLink($destination, $label, $target));
    }

    /**
     * @param string $source
     * @return HtmlContainer|HtmlInput
     */
    function STYLESHEET($source){
        return $this->add('<link href="'.$source.'" rel="stylesheet">');
    }

    /**
     * @return HtmlTable
     */
    function TABLE(){
        return $this->add(new HtmlTable());
    }

    /**
     * @param string $varName
     * @param mixed $varInput
     * @return HtmlHidden
     */
    function HIDDEN($varName, $varInput=null){
        return $this->add(new HtmlHidden($varName, $varInput));
    }

    /**
     * @param string $varName
     * @param string $value
     * @return HtmlSubmit
     */
    function SUBMIT($varName, $value='SUBMIT'){
        return $this->add(new HtmlSubmit($varName, $value));
    }

    /**
     * @param string $varName
     * @param string $value
     * @return HtmlButton
     */
    function BUTTON($varName, $value='BUTTON'){
        return $this->add(new HtmlButton($varName, $value));
    }

    /**
     * @param $varName
     * @param mixed $varInput
     * @param string $label
     * @return HtmlTextinput
     */
    function TEXT($varName, $varInput=null, $label=''){
        return $this->add(new HtmlTextinput($varName, $varInput, $label));
    }

    /**
     * @param string $id
     * @return HtmlAnchor
     */
    function ANCHOR($id){
        $this->add($anchor = new HtmlAnchor());
        $anchor->setID($id);
        $anchor->addTagContent(["name"=>$id]);
        return $anchor;
    }

    /**
     * @param string $varName
     * @param mixed $label
     * @param mixed $varInput
     * @return HtmlPassword
     */
    function PASSWORD($varName, $label=null, $varInput=null){
        return $this->add(new HtmlPassword($varName, $label, $varInput));
    }

    /**
     * @param string $varName
     * @param string $varInput
     * @param string $label
     * @return HtmlTextarea
     */
    function TEXTAREA($varName, $varInput=null, $label=null){
        return $this->add(new HtmlTextarea($varName, $varInput, $label));
    }

    /**
     * Add single checkbox
     * @param string $varName
     * @param string $value
     * @param string $label
     * @param string $varInput
     * @return HtmlCheckbox
     */
    function CHECKBOX($varName, $value='1', $label=null, $varInput=null){
        return $this->add(new HtmlCheckbox($varName, $varInput, $label, $value));
    }

    /**
     * Add single checkbox
     * @param string $varName
     * @param string $value
     * @param string $label
     * @param string $varInput
     * @return HtmlRadio
     */
    function RADIO($varName, $value, $label=null, $varInput=null){
        return $this->add(new HtmlRadio($varName, $value, $label, $varInput));
    }

    /**
     * @param string $varName
     * @param array $codeSource
     * @param mixed $varInput
     * @return HtmlDropdown
     */
    function DROPDOWN($varName, array $codeSource, $varInput=null){
        return $this->add(new HtmlDropdown($varName, $codeSource, $varInput));
    }

    /**
     * @param int $i
     */
    function WHITESPACE($i){
        $this->add(str_repeat('&#160', $i));
    }

    /**
     * @return string
     */
    function render(){
        $htmlString = null;

        foreach ($this->content as $content){
            if (is_object($content)){
                $tempArray = [];
                foreach($content->tagContentArray as $key=>$value){
                    $tempArray[]=$key.'="'.$value.'"';
                }
                $content->tagContent .= " ".implode(' ', $tempArray);
                $tempArray = [];
                if(count($content->cssStyleArray)>0){
                    foreach($content->cssStyleArray as $key=>$value){
                        $tempArray[]=$key.':'.$value;
                    }
                    $content->tagContent .= ' style="'.implode(';', $tempArray).'"';
                }
                $content->indent = str_repeat("\t", $content->indents);
                $htmlString .= $content->render();
            } else {
                if ($this instanceof HtmlCell or $this instanceof HtmlHeadline or $this instanceof  HtmlScript or $this instanceof  HtmlP or $this instanceof HtmlSpan){
                    $htmlString .= $content;
                } else {
                    $htmlString .= str_repeat("\t", $this->indents).$content;
                }
            }
        }
        return $htmlString;
    }

    /**
     * @param $input
     * @return HtmlTable
     * @deprecated
     */
    function resultTable($input){
        $table = $this->TABLE();
        $table->addResultSet($input);
        return $table;
    }

    /**
     * Add a tabular result display, based on a html table
     *
     * @param array $listContents result, to be displayed as a table
     * @param array $listMapping mapping of result fields to column labels for display, e.g. ['count'=>'Number of X']
     * @param bool $showAll
     * @return HtmlTable
     */
    function resultList(array $listContents, array $listMapping = null, $showAll = False){
        return $this->add(new SPA_ResultList($listContents, $listMapping, $showAll));
    }

    /**
     * @param array $listContents
     * @param array $rowIdentifier
     * @param array $selectedRow
     * @param array $listMapping
     * @return SPA_ResultChoice
     */
    function resultChoice(array $listContents, array $rowIdentifier, array $selectedRow = [], $listMapping = []){
        return $this->add(new SPA_ResultChoice($listContents, $rowIdentifier, $selectedRow, $listMapping));
    }

    /**
     * @param array $listContents
     * @param string $rowIdentifier
     * @param array $selectedRows
     * @param array $listMapping
     * @return SPA_ResultMultiChoice
     */
    function resultMultiChoice(array $listContents, $rowIdentifier, array $selectedRows = [], array $listMapping = []){
        return $this->add(new SPA_ResultMultiChoice($listContents, $rowIdentifier, $selectedRows, $listMapping));
    }

    /**
     * @param array $input
     * @param array $rowIdentifier
     * @param array $selectedRow
     * @param array $listMapping
     * @return SPA_ResultEditor
     */
    function resultEditor(array $input, array $rowIdentifier, array $selectedRow = [], $listMapping = []){
        return $this->add(new SPA_ResultEditor($input, $rowIdentifier, $selectedRow, $listMapping));
    }

    /**
     * Add set of radio buttons
     * @param $varName
     * @param $codePlan
     * @param null $varInput
     * @return HtmlContainer|HtmlInput
     */
    function RADIOSET($varName, $codePlan, $varInput=null){
        return $this->add(new HtmlRadioset($varName, $codePlan, $varInput));
    }
}

/**
 * Class HtmlBody
 * @author Stefan Seltmann
 * @package spass
 */
class HtmlBody extends HtmlContainer  {

    /**
     * @param SPApp $rootApp
     */
    function __construct(SPApp &$rootApp=null){
        $this->rootApp =& $rootApp;
    }

    /**
     * @return string
     */
    function render(){
        $tempArray=[];
        foreach($this->tagContentArray as $key=>$value){
            $tempArray[]=$key.'="'.$value.'"';
        }
        $this->tagContent .= " ".implode(' ', $tempArray);
        return "<body{$this->tagContent}>\n\n".parent::render()."\n</body>\n";
    }
}

/**
 * Class HtmlHead
 * @author Stefan Seltmann
 * @package spass
 */
class HtmlHead extends HtmlContainer  {

    public $meta;
    public $htmlTitle;

    /**
     * @param array $name
     */
    function addMeta(array $name){
        foreach($name as $key=>$value){
                $this->meta .= '<meta name="'.$key.'" content="'.$value."\" />\n";
        }
    }

    /**
     * @param string $sheetPath where stylesheet is stored
     * @return HtmlHead
     */
    function setStyleSheet($sheetPath){
        $this->styleSheet = $sheetPath;
        return $this;
    }

    /**
     * @param string $htmlTitle
     * @return HtmlHead
     */
    function setHtmlTitle($htmlTitle){
        $this->htmlTitle = $htmlTitle;
        return $this;
    }

    /**
     * @return string
     */
    function render(){
        $string =  "<head>\n\t<title>{$this->htmlTitle}</title>\n\t<meta http-equiv=\"content-type\" content=\"application/xhtml+xml;charset=utf-8\" />\n".$this->meta;
        if(isset($this->styleSheet)){$string.= "\t<link rel=\"stylesheet\" type=\"text/css\" href=\"{$this->styleSheet}\" />\n";}
        $string.=parent::render()."\n</head>\n";
        return $string;
    }
}

/**
 * Class HtmlHeader
 * @package spass
 */
class HtmlHeader extends HtmlContainer  {

    /**
     * @return string
     */
    function render(){
        return $this->indent.'<header'.$this->tagContent.">\n\n".parent::render().$this->indent.'</header>'.(($this->htmlID!==null)?'<!--'.$this->htmlID.'-->':null)."\n\n\n";
    }
}


/**
 *
 * Root object for the entire Website.
 * @author Stefan Seltmann
 *
 */
class HtmlSite {
    public $BODY;
    public $HEAD;
    public $rootApp;

    /**
     * @param SPApp $rootApp
     */
    function __construct(&$rootApp=null){
        $this->rootApp = $rootApp;
        $this->HEAD = new HtmlHead();
        $this->BODY = new HtmlBody($rootApp);
    }

    /**
     * @param $htmlTitel
     * @return $this
     */
    function setHtmlTitle($htmlTitel){
        $this->HEAD->setHtmlTitle($htmlTitel);
        return $this;
    }

    /**
     * @param $sheetPath
     * @return $this
     */
    function setStyleSheet($sheetPath){
        $this->HEAD->setStyleSheet($sheetPath);
        return $this;
    }

    /**
     * @param array $name
     * @return $this
     */
    function addMeta(array $name){
        $this->HEAD->addMeta($name);
        return $this;
    }

    /**
     * @return string
     */
    function render(){
        return
        "</?xml version=\"1.0\" encoding=\"utf-8\" ?>\n<!DOCTYPE html>\n".
        "<html xmlns=\"http://www.w3.org/1999/xhtml\" xml:lang=\"de\" lang=\"de\">\n".$this->HEAD->render().$this->BODY->render()."</html>\n";
    }
}

/**
 *
 * Html-Formular
 * @author Stefan Seltmann
 *
 */
class HtmlForm extends HtmlContainer {
    protected $formTitel = null;
    protected $formMethod = 'post';
    protected $formTarget = null;
    protected $includeAppid = true;
    protected $formAction = null;

    /**
     * @param null $titel
     * @param null $action
     */
    function __construct($titel = null, $action = null){
        parent::__construct();
        $this->tagContentArray["method"] = $this->formMethod;
        if ($titel !== null){
            $this->formTitel = $titel;
            $this->tagContentArray["name"] = $titel;
        }
        if ($action === null){
            $this->formAction = $_SERVER['PHP_SELF'];
        } else {
            $this->formAction = $action;
        }
        $this->tagContentArray["action"] = $this->formAction;
    }

    /**
     * @param $method
     * @return $this
     */
    function setMethod($method){
        $this->formMethod = $method;
        $this->tagContentArray["method"] = $this->formMethod;
        return $this;
    }

    /**
     * @param $target
     * @return $this
     */
    function setTarget($target){
        $this->formTarget = $target;
        $this->tagContentArray["target"] = $this->formTarget;
        return $this;
    }

    /**
     * @param string $action
     * @return HtmlForm
     */
    function setAction($action){
        $this->formAction = $action;
        $this->tagContentArray['action']=$action;
        return $this;
    }

    /**
     * @param bool|true $input
     * @return $this
     */
    function setIncludeAppid($input = true){
        $this->includeAppid = $input;
        return $this;
    }

    /**
     * @return string
     */
    function render(){
        $htmlString =  $this->indent."<form {$this->tagContent} >\n";
        if ($this->includeAppid === true and $this->formTarget !== '_blank'){
            if($this->rootApp->appid===null){
                trigger_error("Y U NO HAVE appid?", E_USER_ERROR);
            }else{
                $this->HIDDEN("appid",$this->rootApp->appid);
            }
        }
        return $htmlString.parent::render().$this->indent."</form>\n";
    }
}



/**
 *
 * Html-Div-Container
 * @author Stefan Seltmann
 *
 */
class HtmlDiv extends HtmlContainer {

    /**
     * @return string
     */
    function render(){
        return $this->indent.'<div'.$this->tagContent.">\n\n".parent::render().$this->indent.'</div>'.(($this->htmlID!==null)?'<!--'.$this->htmlID.'-->':null)."\n\n\n";
    }
}

/**
 * HTML Container that contains usually only text, therefore the constructor is adjusted.
 * @author s.seltmann
 */
abstract class HtmlTextContainer extends HtmlContainer{

    /**
     * string @var
     */
    protected $tag;

    /**
     * @param null $content
     */
    function __construct($content=null){
        $this->add($content);
    }

    /**
     * @return string
     */
    function render(){
        return $this->indent.'<'.$this->tag.$this->tagContent.">\n\n".parent::render().$this->indent.'</'.$this->tag.">\n\n\n";
    }
}

/**
 * P container
 * @author Stefan Seltmann
 */
class HtmlP extends HtmlTextContainer {
    protected $tag = "p";
}

/**
 * SPAN container
 * @author Stefan Seltmann
 */
class HtmlSpan extends HtmlTextContainer{
    protected $tag = "span";
}

/**
 * Class HtmlLabel
 *
 * LABEL container
 * @author Stefan Seltmann
 */
class HtmlLabel extends HtmlTextContainer {

    protected $tag = "label";

    /**
     * Links the label to a object ID
     * @param string $for object_id
     * @return HtmlLabel
     */
    function setFor($for){
        $this->tagContentArray['for']=$for;
        return $this;
    }
}

/**
 * Class HtmlScript
 * Used for scripts that are entered directly and not via src file.
 * @package spass
 */
class HtmlScript extends HtmlTextContainer {

    protected $tag = "script";
}

/**
 *
 * Html-H-Container
 * @author Stefan Seltmann
 *
 */
class HtmlHeadline extends HtmlContainer {

    protected $level;

    /**
     * @param int $level
     */
    function __construct($level){
        parent::__construct();
        $this->level = $level;
    }

    /**
     * @return string
     */
    function render(){
        return $this->indent."<h{$this->level}{$this->tagContent}>".parent::render()."</h{$this->level}>\n";
    }
}

/**
 *
 * Html-HRef-Container
 * @author s.seltmann
 *
 */
class HtmlLink extends HtmlContainer {

    /**
     * @var null
     */
    protected $target = null;

    /**
     * label for the link, inner text of tag
     * @var string
     */
    protected $label;

    /**
     * @param $destination
     * @param null $label
     * @param null $target
     */
    function __construct($destination, $label = null, $target = null){
        parent::__construct();
        $this->tagContentArray['href'] = $destination;
        $this->label = ($label !== null) ? $label: $destination;
        if($target!==null){
            $this->setTarget($target);
        }
    }

    /**
     * @param $target
     */
    function setTarget($target){
        $this->target = $target;
        $this->tagContentArray['target'] = $target;
    }

    /**
     * @param int $count
     * @return $this
     */
    function BR($count = 1){
        $this->parentContainer->BR($count);
        return $this;
    }

    /**
     * @return string
     */
    function render(){
        $htmlString = $this->indent.'<a'.$this->tagContent.">".$this->label."</a>\n";
        return $htmlString;
    }
}

/**
 * Class HtmlAnchor
 */
class HtmlAnchor extends HtmlContainer {

    /**
     * @return string
     */
    function render(){
        return "<a{$this->tagContent}/>";
    }
}

/**
 * Class HtmlTable
 *
 * Html-Table-Container
 * @author s.seltmann
 */
class HtmlTable extends HtmlContainer {

    /**
     * Listing of custom css for each column
     * @var array
     */
    protected $columnStyles = [];

    /**
     * @param string $alignments A String consisting of the letters l for left, c for center or r for right, e.g. "llrcl"
     * @return HtmlTable
     */
    function setColumnAlignments($alignments){
        $i = 0;
        $alignmentMap = ['r'=>'right', 'c'=>'center', 'l'=>'left'];
        foreach(str_split($alignments) as $alignment){
            if(!isset($this->columnStyles[$i])){
                $this->columnStyles[$i] = [];
            }
            $this->columnStyles[$i]["text-align"] = $alignmentMap[$alignment];
            $i++;
        }
        return $this;
    }

    /**
     * @return string
     */
    function render(){
        foreach ($this->content as $key=>$value){
            if($value instanceof HtmlRow and isset($this->columnStyles)){ 
                $this->content[$key]->columnStyles = $this->columnStyles;
            }
        }
        return "\n".$this->indent."<table{$this->tagContent}>\n".parent::render().$this->indent."</table>\n\n";
    }

    /**
     *
     * Add a row to the table
     * @return HtmlRow
     */
    function TR(){
        $row = new HtmlRow();
        $this->add($row);
        return $row;
    }

    /**
     * @return HtmlRow
     */
    function fillRow(){
        $row = $this->TR();
        $cells = func_get_args();
        if(is_array($cells[0])){
            $cells = $cells[0];
        }
        foreach($cells as $content){
            $row->TD($content);
        }
        return $row;
    }

    /**
     * @return HtmlRow
     */
    function fillHeaderrow(){
        $row = $this->TR();
        $cells = func_get_args();
        if(is_array($cells[0])){
            $cells = $cells[0];
        }
        foreach($cells as $content){
            $row->TH($content);
        }
        return $row;
    }

    /**
     * @param $resultSet
     */
    function addResultSet($resultSet){
        if ($resultSet){
            $row = $this->TR();
            $keys = array_keys($resultSet[0]);
            foreach ($keys as $key){
                $row->TH($key);
            }
            $lastValue = '';
            foreach ($resultSet as $dataRow){
                $this->add($row = new HtmlRow());
                if($dataRow) {
                    foreach ($dataRow as $key => $value) {
                        if ($key == $keys[0] and $lastValue == $value and !is_numeric($value)) {
                            $row->TD()->add('&nbsp;');
                        } else {
                            $row->TD()->add(htmlspecialchars($value));
                        }
                        if ($key == $keys[0]) {
                            $lastValue = $value;
                        }
                    }
                }
            }
        } else {
            $this->add("No results available!");
        }
    }
}

/**
 *
 * Html-Row-Container
 * @author Stefan Seltmann
 *
 */
class HtmlRow extends HtmlContainer {
    public $columnStyles = null; //XXXX fehl am platz!; muss von TAbelle abgeleitet werden;

    /**
     * @return $this
     */
    function fillRow(){
        $cells = func_get_args();
        if(is_array($cells[0])){
            $cells = $cells[0];
        }
        foreach($cells as $content){
            $this->TD($content);
        }
        return $this;
    }

    /**
     * @return $this
     */
    function fillHeaderRow(){
        $rowContent = func_get_args();
        foreach($rowContent as $cellContent){
            $this->TH($cellContent);
        }
        return $this;
    }

    /**
     * @param string $content
     * @return HtmlContainer|HtmlInput
     */
    function TD($content = null){
        return $this->add(new HtmlCell($content));
    }

    /**
     * @param string $content
     * @return HtmlContainer|HtmlInput
     */
    function TH($content = null){
        return $this->add(new HtmlHeadcell($content));
    }

    /**
     * @return string
     */
    function render(){
        if (null !== $this->columnStyles){
            $i = 0;
            foreach ($this->columnStyles as $value){
                if(isset($this[$i])){
                    $this[$i]->addStyle($value);
                }else{
                    break;
                }
                $i++;
            }
        }
        return $this->indent."<tr{$this->tagContent}>\n".parent::render().$this->indent."</tr>\n";
    }
}

/**
 *
 * Cell of a Html-Table
 * @author s.seltmann
 *
 */
class HtmlCell extends HtmlContainer {

    /**
     * @param string $content
     */
    function __construct($content = ''){
        $this->content[] = $content;
    }

    /**
     * @param int $width
     * @return HtmlCell
     */
    function setColspan($width){
        $this->addTagContent(['colspan'=>$width]);
        return $this;
    }

    /**
     * @param string $tag
     * @return HtmlCell
     */
    function render($tag = 'td'){
        $cellString =  $this->indent.'<'.$tag.$this->tagContent.'>';
        foreach($this->content as $content){
            if ($content instanceof HtmlContainer or $content instanceof HtmlInput){
                $tempArray = [];
                foreach($content->tagContentArray as $key=>$value){
                    $tempArray[]=$key.'="'.$value.'"';
                }
                $content->tagContent .= " ".implode(' ', $tempArray);
                $tempArray = [];
                if(count($content->cssStyleArray)>0){
                    foreach($content->cssStyleArray as $key=>$value){
                        $tempArray[]=$key.':'.$value;
                    }
                    $content->tagContent .= " style=\"".implode(';', $tempArray)."\"";
                }
                $content->indent = str_repeat("\t", $content->indents);
                $htmlString = $content->render();
                if($htmlString){
                    $cellString.=$htmlString;
                }else{
                    trigger_error('No string from child-element found', E_USER_ERROR);
                }
            }else if(is_array($content)){
                $cellString.=implode(',', $content);
            }else{
                $cellString.=$content;
            }
        }
            $cellString.="\n".$this->indent.'</'.$tag.">\n";
        return $cellString;
    }
}

/**
 * Class HtmlHeadcell
 */
class HtmlHeadcell extends HtmlCell {

    /**
     * @var string tag
     * @return HtmlHeadcell
     */
    function render($tag = 'th'){
        return parent::render($tag);
    }
}


/**
 * Class HtmlInput
 *
 * Abstract class for all html input elements
 * @author Stefan Seltmann
 */
abstract class HtmlInput{

    use HtmlObject;

    /**
     * Variable for the content of a tag.
     * It is built and inserted as a string during plotting of the element from the tagContentArray
     * @var string
     */
    public $tagContent = "";

    /**
     * Number of indentations for code of the element
     * @var int
     */
    public $indents = 0; //FIXME why public

    /**
     * Resolved string containing $this->indents x indentations.
     * @var string
     */
    public $indent = ''; //FIXME why public

    /**
     * Instance of the SPApp, that includes and displays the Html-elements
     * @var SPApp
     */
    protected $rootApp;

    /**
     * Reference to parent container in which the element ist nested
     * @var HtmlContainer
     */
    public $parentContainer;

    /**
     * By this varName the Input element is addressed and identified in a REQUEST
     * @var string
     */
    protected $varName;

    /**
     * The value that is given for the element, usually from a REQUEST.
     * @var mixed
     */
    protected $varInput = null;
    protected $label;

    protected $autoSubmit = False;

    /**
     * @param $varName
     * @param null $varInput
     */
    function __construct($varName, $varInput = null){
        $this->varName = $varName;
        $this->varInput = $varInput;
    }

    /**
     * 
     */
    function resolveInput(){
        if ($this->varInput === null and isset($this->rootApp)){
            // lookup of variable name in session if not set
            $this->varInput = $this->rootApp->REQUEST[$this->varName] ? $this->rootApp->REQUEST[$this->varName] : null;
        } elseif((is_array($this->varInput) and isset($this->varInput[$this->varName])) or is_object($this->varInput)) {
            // lookup of variable name in object or array given for input
            $this->varInput = $this->varInput[$this->varName];
        } elseif((is_array($this->varInput) and !isset($this->varInput[$this->varName]))) {
            $this->varInput = null;  //XXX block ueberarbeiten.
        } //TODO fehlende dritte bedinung!!
    }

    /**
     * @return string
     */
    function getName(){
        return $this->varName;
    }

    /**
     * enable this input element with html autofocus
     * @return HtmlInput
     */
    function setAutoFocus(){
        $this->tagContentArray["autofocus"]="autofocus";
        return $this;
    }

    /**
     * @param int $count
     * @todo why no return value?
     */
    function BR($count = 1){
        $this->parentContainer->BR($count);
    }

    function HR(){
        $this->parentContainer->HR();
    }
}

/**
 * Class HtmlRadio
 * @package spass
 */
class HtmlRadio extends HtmlInput{

    protected $labelClass = null; //todo rethink

    /**
     * value of radio button
     * @var string|integer
     */
    protected $value = null;

    /**
     * @param $varName
     * @param null $value
     * @param null $label
     * @param bool|false $varInput
     */
    function __construct($varName, $value, $label=null, $varInput=false){
        parent::__construct($varName, $varInput);
        $this->tagContentArray = ['type'=>'radio', 'name'=>$varName, 'id'=>$varName.$value, 'value'=>$value];
        $this->value = $value;
        $this->label = $label;
    }

    function resolveInput(){
        parent::resolveInput();
        if ($this->varInput == $this->value){
            $this->tagContentArray["checked"]="checked";
        }
    }

    /**
     * @param $labelClass
     * @return $this
     */
    function setLabelClass($labelClass){//XXXX aendern;
        $this->labelClass = $labelClass;
        return $this;
    }

    /**
     * @return string
     */
    function render(){
        $htmlString =  $this->indent."<input".$this->tagContent."/>";
        if ($this->label !== null) {
            $htmlString .= "<label for=\"".$this->varName.$this->value."\"";
            if ($this->labelClass !== null){
                $htmlString .= " class=\"".$this->labelClass."\"";
            }
            $htmlString .=  ">".$this->label."</label>\n";
        }
        return $htmlString;
    }
}

/**
 * Class HtmlRadioset
 * @package spass
 */
class HtmlRadioset extends HtmlContainer{

    protected $codePlan;

    protected $otherCode = 99;

    /**
     * @param string $varName
     * @param array $codePlan
     * @param bool|false $input
     */
    function __construct($varName, array $codePlan, $input = false){
        foreach($codePlan as $code=>$codeInfo){
            if ($code == $this->otherCode){ //break before special code
                $this->BR();
            }
            $this->RADIO($varName, $code, $codeInfo, $input);
            $this->BR();
        }
    }

    /**
     * @param $class
     * @return $this
     */
    function setLabelClass($class){
        foreach($this->content as $radioButton){
            $radioButton->setLabelClass($class);
        }
        return $this;
    }
}

/**
 * Class HtmlCheckbox
 * @package spass
 */
class HtmlCheckbox extends HtmlInput {

    protected $labelClass ='';

    /**
     * Value of checkbox
     * @var string|integer
     */
    protected $value;

    /**
     * @param $varName
     * @param bool|false $varInput
     * @param string $label
     * @param string $value
     */
    function __construct($varName, $varInput=false, $label='', $value='1'){ //XXXreihenfolge?
        parent::__construct($varName,$varInput);
        if(!$value){
            trigger_error("Assigned value for checkbox $varName cannot be 0 or Null or missing!");
        }
        $this->varInput = $varInput;
        $this->label = $label;
        $this->value = $value;
        $this->tagContentArray['type'] = 'checkbox';
        $this->tagContentArray['name'] = $varName;
        $this->tagContentArray['id'] = $varName;
        $this->tagContentArray['value'] = $this->value;
    }

    function resolveInput(){
        parent::resolveInput();
        if ($this->varInput == $this->value){
            $this->tagContentArray["checked"]="checked";
        }
    }

    /**
     * @return $this
     */
    function setAutoSubmit(){
        $this->tagContentArray["onclick"]="submit()";
        return $this;
    }

    /**
     * @return string
     */
    function render(){
        $htmlText =  "\t\t<input{$this->tagContent}/>\n";
        $htmlText .= "\t\t\t<label for=\"{$this->varName}\"{$this->labelClass}>{$this->label}</label>\n";
        return $htmlText;
    }

    /**
     * @param $labelClass
     * @return $this
     */
    function setLabelClass($labelClass){
        $this->labelClass = " class=\"$labelClass\"";
        return $this;
    }
}

/**
 * Class HtmlSubmit
 * @package spass
 */
class HtmlSubmit extends HtmlInput {

    /**
     * @param string $varName
     * @param string $value
     */
    function __construct($varName='', $value='Anfrage senden'){ //TODO add constant for text
        parent::__construct($varName, $value);
        $this->tagContentArray = ['type'=>'submit', 'name'=>$varName, 'value'=>$value];
    }

    /**
     * @return string
     */
    function render(){
        return $this->indent."<input".$this->tagContent."/>\n";
    }
}

/**
 * Class HtmlButton
 * @package spass
 */
class HtmlButton extends HtmlInput {

    /**
     * @param string $varName
     * @param string $value
     */
    function __construct($varName='', $value='Button'){
        parent::__construct($varName, $value);
        $this->tagContentArray = ['type'=>'button', 'name'=>$varName, 'value'=>$value];
    }

    /**
     * @return string
     */
    function render(){
        return $this->indent.'<input'.$this->tagContent."/>\n";
    }
}

/**
 * Class HtmlHidden
 * @package spass
 */
class HtmlHidden extends HtmlInput {

    /**
     * @return string
     */
    function render(){
        return $this->indent.'<input type="hidden" id="'.$this->varName.'" name="'.$this->varName.'" value="'.$this->varInput."\" />\n";
    }
}

/**
 * Class HtmlTextarea
 * @package spass
 */
class HtmlTextarea extends HtmlInput {

    /**
     * @var int
     */
    protected $cols=80;

    /**
     * @var int
     */
    protected $rows=8;

    /**
     * @param $varName
     * @param bool $varInput
     * @param string $label
     */
    function __construct($varName, $varInput = false, $label = null){
        parent::__construct($varName, $varInput);
        //$this->varInput = $varInput;
        $this->label = $label;
        $this->tagContentArray["name"]=$varName;
        $this->tagContentArray["id"]=$varName;
        $this->tagContentArray["cols"]=$this->cols;
        $this->tagContentArray["rows"]=$this->rows;
    }

    /**
     * @param int $rows
     * @param int $cols
     * @return HtmlTextarea
     */
    function setSize($rows=8, $cols=80){
        $this->cols = $cols;
        $this->rows = $rows;
        $this->tagContentArray["cols"]=$this->cols;
        $this->tagContentArray["rows"]=$this->rows;
        return $this;
    }

    /**
     * @return string
     */
    function render(){
        $htmlString = $this->indent."<textarea{$this->tagContent}>{$this->varInput}</textarea>\n";
        if($this->label !== null){
            $htmlString =  $this->indent."<label for=\"{$this->varName}\">{$this->label}</label><br />\n".$htmlString;
        }
        return $htmlString;
    }
}

/**
 * Class HtmlTextinput
 * @package spass
 */
class HtmlTextinput extends HtmlInput {

    /**
     * @var int
     */
    protected $size=20;

    /**
     * @var int
     */
    protected $maxlength=255;

    /**
     * @param string $varName
     * @param string $varInput
     * @param string $label
     */
    function __construct($varName, $varInput='', $label=''){
        parent::__construct($varName, $varInput);
        $this->label = $label;
        $this->tagContentArray = ['type'=>'text', 'name'=>$varName, 'id'=>$varName];
    }

    /**
     * @return string
     */
    function render(){
        $htmlText = $this->indent.'<input ';
        if ($this->label!=''){
            $htmlText .= ' id="'.$this->varName.'" ';
        }
        $htmlText .= $this->tagContent;
        $htmlText .= ' value="'.$this->varInput."\" />\n";
        if ($this->label!=''){
            $htmlText .= $this->indent."\t<label for=\"{$this->varName}\">{$this->label}</label>\n";
        }
        return $htmlText;
    }

    /**
     * @param int $size
     * @param int $maxLength
     * @return HtmlTextinput
     */
    function setSize($size=20, $maxLength=255){
        $this->size = $size;
        $this->maxlength = $maxLength;
        $this->tagContentArray['size']=$size;
        $this->tagContentArray['maxlength']=$maxLength;
        return $this;
    }

}

/**
 * Class HtmlPassword
 * @package spass
 */
class HtmlPassword extends HtmlTextinput {

    /**
     * @param string $varName
     * @param null $label
     * @param string $varInput
     */
    function __construct($varName, $label=null, $varInput=''){ // TODO warum label = '', reihenfolge
        parent::__construct($varName, $label, $varInput);
        $this->tagContentArray['type']='password';
        if ($label!==null){
            $this->tagContentArray['id']=$label;
        }
    }

    /**
     * @return string
     */
    function render(){
        $htmlText = $this->indent."<input {$this->tagContent} value=\"{$this->varInput}\" />\n";
        if ($this->label!==null){
            $htmlText .= $this->indent."\t<label for=\"{$this->varName}\">{$this->label}</label>\n";
        }
        return $htmlText;
    }
}

/**
 *
 * Klasse fuer html-Dropdown;
 * @author Stefan Seltmann
 *
 */
class HtmlDropdown extends HtmlInput {

    protected $codeSource;
    protected $autoSubmit = false;
    protected $noMissing  = false;
    protected $simpleCodeSource = false;
    protected $missingCode = "999";
    static $noEntryLabel = "no entry";

    /**
     * @param $varName
     * @param array $codeSource
     * @param bool|false $varInput
     */
    function __construct($varName, array $codeSource, $varInput=false){
        parent::__construct($varName, $varInput);
        $this->tagContentArray = ['id'=>$varName, 'name'=>$varName];
        $this->codeSource = $codeSource;
    }

    /**
     * @param $text
     */
    static function setNoEntryLabel($text){
        self::$noEntryLabel = $text;
    }

    /**
     * Enables the dropdown to hold multiple selections
     * @param int $size Number of visible entries
     * @return HtmlDropdown
     */
    function setMultiple($size = 4){
        $this->tagContentArray['multiple'] = 'multiple';
        $this->tagContentArray['name'] = $this->varName."[]";
        $this->tagContentArray['size'] = $size;
        return $this;
    }

    /**
     * Sets the number of entries visible in the dropdown
     * @param int $size
     * @return HtmlDropdown
     * @deprecated
     */
    function setSize($size = 4){
        $this->tagContentArray['size'] = $size;
        return $this;
    }

    /**
     * Enables the automatic submit after a change in values
     * @return HtmlDropdown
     */
    function setAutoSubmit(){
        $this->autoSubmit = True;
        $this->tagContentArray["onchange"]="submit()";
        return $this;
    }

    /**
     * Supresses the code for "no entry"/"missing entry"
     * @return HtmlDropdown
     */
    function setNoMissing(){
        $this->noMissing = True;
        return $this;
    }

    /**
     * @param $missing
     * @return $this
     */
    function setMissing($missing){
        $this->missingCode = $missing;
        return $this;
    }


    /**
     *
     * Enables the usage of codesources without keys.
     *
     * For Dropdowns the CodeSource is expected to contain keys AND values. To handle the exception you can trigger
     * the usage of the values for keys by using this function.
     */
    function setSimpleCodeSource(){
        $this->simpleCodeSource = True;
        return $this;
    }

    /**
     * @return $this
     */
    function setSimpleCodes(){
        $this->simpleCodeSource = True;
        return $this;
    }

    function resolveInput(){ // TODO: mit uebergeordnetem integrieren.
        if ($this->varInput === null and isset($this->rootApp)){
            $this->varInput = $this->rootApp->REQUEST[$this->varName] ? $this->rootApp->REQUEST[$this->varName] : null;
        } elseif((is_array($this->varInput) and isset($this->varInput[$this->varName])) or is_object($this->varInput)) {
            $this->varInput = $this->varInput[$this->varName];
        } elseif(is_array($this->varInput) and !isset($this->varInput[$this->varName])) {
            $keys = array_keys($this->varInput);
            if($keys[0] != 0){//special Case for multiple Dropdowns;
                $this->varInput = null;  //XXX block ueberarbeiten.
            }
        }
    }

    /**
     * @return string
     */
    function render(){
        $codes = $this->codeSource;
        if ($this->varInput == "") {$this->varInput = $this->missingCode;} //XXX ID mit varname ersetzen.
        $dropdownString = "\n".$this->indent."<select".$this->tagContent.">\n";
        if (!$this->noMissing){
            $dropdownString .= $this->indent."\t<option value=\"{$this->missingCode}\">".self::$noEntryLabel."</option>\n";
        }
        $codeString ='';
        if($codes){
            if($this->simpleCodeSource){
                $codes=array_combine($codes, $codes);
            }
            if(isset($this->tagContentArray["multiple"])){
                foreach($codes as $code => $label) {
                    $codeString .=  $this->indent."\t<option value=\"".$code.'"';
                    if ($code==$this->varInput) { //XXXX unklar,ob das vorkommen kann.
                        $codeString .= " selected=\"selected\"";
                        //trigger_error('wie kann das sein', E_USER_ERROR);
                    }elseif(is_array($this->varInput) and in_array($code, $this->varInput)){ //XXX ersetzen mit schnittmenge?
                        $codeString .= " selected=\"selected\"";
                    }
                    $codeString .=  ">".$label."</option>\n";
                }
            }else{
                if(is_array($this->varInput)){
                    foreach($codes as $code => $label) {
                        $codeString .=  $this->indent."\t<option value=\"".$code.'"';
                        if (in_array($code, $this->varInput)) {$codeString .= " selected=\"selected\"";}
                        $codeString .=  ">".$label."</option>\n";
                    }
                }else{
                    $codesValues = array_values($codes);
                    if(count($codes)>1 and  is_array($codesValues[1])){
                        foreach($codes as $optgrp => $subCode) {
                            if(is_array($subCode)){
                                $codeString .=  $this->indent."\t<optgroup  label=\"".$optgrp.'"/>';
                                foreach($subCode as $code => $label) {
                                    $codeString .=  $this->indent."\t<option value=\"".$code.'"';
                                    if ($code == $this->varInput) {$codeString .= " selected=\"selected\"";}
                                    $codeString .=  ">".$label."</option>\n";
                                }
                            }else{
                                $codeString .=  $this->indent."\t<option value=\"".$optgrp.'"';
                                if ($optgrp == $this->varInput) {$codeString .= " selected=\"selected\"";}
                                $codeString .=  ">".$subCode."</option>\n";
                            }
                            $codeString .=  "</optgroup>\n";
                        }
                    }else{
                        foreach($codes as $code => $label) {
                            $codeString .=  $this->indent."\t<option value=\"".$code.'"';
                            if ($code == $this->varInput) {$codeString .= " selected=\"selected\"";}
                            $codeString .=  ">".$label."</option>\n";
                        }
                    }
                    unset($codesValues);
                }
            }
        }
        $dropdownString .= $codeString.$this->indent."</select>\n";
        return $dropdownString;
    }
}


function getDatelist($days, $start = 0, $options = 'noWeekend'){ /// xxxx hier muessen noch andere STartwerte nachgearbeitet werden.
    $dates = [];
    $i = $start;
    if ($days > 0){
        while ($i <$days){
            $date = mktime(0,0,0,date('m'), date('d')+$i, date('y'));
            if (($options == 'noWeekend' and date('w', $date) != 0 AND date('w', $date) != 6) OR $options != 'noWeekend'){ //bedingtes Filtern auf Wochentage
                $entryDate = date('Y-m-d', $date);
                $displayDate = date('d.m.Y   K\WW D', $date);
                $dates[$entryDate] = $displayDate;
            }
            $i++;

        }
    } elseif ($days < 0){
        while ($days+$i <0){
            $entryDate = date('Y-m-d', mktime(0,0,0,date('m'), date('d')-$i, date('y')));
            $displayDate = date('d.m.Y', mktime(0,0,0,date('m'), date('d')-$i, date('y')));
            $dates[$entryDate]=$displayDate ;
            $i++;
        }
    }
    return $dates;
}

class SPA_DataObject extends ArrayObject{

    protected $readOnly = False; //TODO not used
    protected $dataCache = [];

    /**
     * SPA_DataObject constructor.
     */
    function __construct(){
        $this->setFlags(ArrayObject::ARRAY_AS_PROPS);
    }

    /**
     * @param $varName
     * @return bool
     */
    function get($varName){
        return isset($this->dataCache[$varName]) ? $this->dataCache[$varName]:false;
    }
}

/**
 * Class SPAppDataCache
 *
 * Facade Object for $_SESSION with amended functionality
 * @package spass
 */
class SPAppDataCache extends SPA_DataObject{

    protected $dataSourceFunctions = [];

    /**
     * SPAppDataCache constructor.
     */
    function __construct(){
        parent::__construct();
        $this->dataCache = &$_SESSION;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value) {
        if(true === $this->readOnly){
            trigger_error("In SPASS, the REQUEST-object is considered as read-only! <br />KEY '$offset' and VALUE '$value' could not be set!", E_USER_ERROR);
        }else{
            $this->dataCache[$offset] = $value;
        }
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset) {
        return isset($this->dataCache[$offset]);
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetGet($offset) {
        return isset($this->dataCache[$offset]) ? $this->dataCache[$offset] : false;
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset) {
        if(true === $this->readOnly){
            trigger_error("In SPA, the REQUEST-object is considered as read-only! <br />KEY '$offset' could not be unset!", E_USER_ERROR);
        }else{
            unset($this->dataCache[$offset]);
        }
    }

    /**
     * Register a datasource by name to be cached in the session.
     *
     * $refresh forces the query to be committed in any case.
     * $execute = false & $refresh = false: only registration, not data provided
     * $execute = true & $refresh = false: lazy retrieval, that means you receive data immediately at registration and cached data will be used, if available
     * $execute = true & $refresh = true: fresh retrieval, that means you receive data immediately at registration and cached data will be ignored and refreshed
     * $execute = false & $refresh = true: don't do this, it doesn't make sense
     *
     * @param string $sourceName unique name of the datasource in the session
     * @param array $function array with the parameters of the function call, e.g. array(object, function)
     * @param bool $execute flag whether to retrieve the contents immediately
     * @param bool $refresh
     * @return array|null
     */
    public function registerDatasourceFunction($sourceName, array $function, $execute = false, $refresh = false){
        if(in_array($sourceName, array_keys($this->dataSourceFunctions))){
            trigger_error("ERROR: There is already a datasource '$sourceName' registered", E_USER_ERROR);
        }
        $this->dataSourceFunctions[$sourceName]=$function;
        if(true===$execute){
            return $this->fetchDatasource($sourceName, $refresh);
        }else{
            return null;
        }
    }

    /**
     * Register a datasource by name to be cached in the session.
     *
     * $refresh forces the query to be committed in any case.
     * $execute = false & $refresh = false: only registration, not data provided
     * $execute = true & $refresh = false: lazy retrieval, that means you receive data immediately at registration and cached data will be used, if available
     * $execute = true & $refresh = true: fresh retrieval, that means you receive data immediately at registration and cached data will be ignored and refreshed
     * $execute = false & $refresh = true: don't do this, it doesn't make sense
     *
     * @param string $sourceName unique name of the datasource in the session
     * @param array $function array with the parameters of the function call, e.g. array(object, function)
     * @param array $params
     * @param bool $execute flag whether to retrieve the contents immediately
     * @param bool $refresh
     * @return array|null
     */
    public function registerDatasourceFunctionWithParams($sourceName, array $function, array $params = [], $execute = false, $refresh = false){
        $this->dataSourceFunctions[$sourceName]=$function;
        if(true===$execute){
            return $this->fetchDatasourceWithParams($sourceName, $params, $refresh);
        }else{
            return null;
        }
    }

    /**
     * @param $datasourceName
     * @param array|null $params
     * @param bool|false $refresh
     * @return mixed
     */
    public function fetchDatasourceWithParams($datasourceName, array $params = null, $refresh = false){
        if(!isset($this->dataCache[$datasourceName]) or $refresh === true){
            $this->dataCache[$datasourceName] = call_user_func_array($this->dataSourceFunctions[$datasourceName], $params);
        }
        return $this->dataCache[$datasourceName];
    }

    /**
     * @param $datasourceName
     * @param bool|false $refresh
     * @return mixed
     */
    public function fetchDatasource($datasourceName, $refresh=false){
        if(!isset($this->dataCache[$datasourceName]) or $refresh === true){
            $this->dataCache[$datasourceName] = call_user_func($this->dataSourceFunctions[$datasourceName]);
        }
        return $this->dataCache[$datasourceName];
    }

    public function flushDatacache(){
        foreach($this->dataSourceFunctions as $key) {
            unset($this->dataCache[$key]);
        }
    }
}

/**
 * Class SPA_Request
 * @package spass
 */
class SPA_Request extends SPA_DataObject {

    protected $request;
    protected $post;
    protected $get;
    protected $mode;
    protected $getMode = 'strict';
    protected $readOnly = true;
    private $inertCache = [];

    /**
     * SPA_Request constructor.
     */
    function __construct(){
        parent::__construct();
        $this->mode = 'post';
        $this->request = $_REQUEST;
        $this->post    = $_POST;
        $this->get     = $_GET;
    }

    /**
     * @param $mode
     */
    function setMode($mode){
        $this->mode = $mode;
    }

    /**
     * @param bool $input
     */
    function setReadOnly($input){
        if($input === true || $input === false){
            $this->readOnly = $input;
        }else{
            trigger_error('Wrong parameter input. Only boolean allowed!', E_USER_ERROR);
        }
    }

    /**
     * Getter for a variable stored in the REQUEST Object
     * @param string $varName
     * @param mixed $missingValue custom value that is regarded as a null value or missing value.
     * @return mixed|bool
     */
    function get($varName, $missingValue = false){
        return (isset($this->request[$varName]) and !($this->request[$varName] === $missingValue)) ? $this->request[$varName] : false;
    }

    /**
     * @param string $varName
     * @return bool|mixed
     */
    function getInert($varName){
        $requestVar = $this->get($varName);
        if($requestVar){
            $this->inertCache[$varName] = $requestVar;
            return $requestVar;
        }else if(isset($this->inertCache[$varName])){
            return $this->inertCache[$varName];
        }else{
            return false;
        }
    }

    /**
     *
     */
    function setInertCache(){
         $this->inertCache = &$_SESSION;
    }

    /**
     * @return array
     */
    function getArray(){
        $valueArray = [];
        if (func_num_args() == 1){
            $keys = func_get_arg(0);
        }else{
            $keys = func_get_args();
        }
        foreach($keys as $key){
            $valueArray[$key] = $this->get($key) ;
        }
        return $valueArray;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value) {
        if(true === $this->readOnly){
            trigger_error("In SPASS, the REQUEST-object is considered as read-only! <br />KEY '$offset' and VALUE '$value' could not be set!", E_USER_ERROR);
        }else{
            $this->request[$offset] = $value;
        }
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset) {
        return isset($this->request[$offset]);
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset) {
        if(true === $this->readOnly){
            trigger_error("In SPASS, the REQUEST-object is considered as read-only! <br />KEY '$offset' could not be unset!", E_USER_ERROR);
        }else{
            unset($this->request[$offset]);
        }
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetGet($offset) {
        return isset($this->request[$offset]) ? $this->request[$offset] : false;
    }
}

/**
 *
 * Framework-Klasse zur schnellen Entwicklung von PHP-Webseiten.
 * SPA steht für SinglePageApplication.
 * @author Stefan Seltmann
 *
 */

/**
 * Class SPApp
 *
 * Root class for SPA Applications
 * @author Stefan Seltmann
 */
class SPApp {

    /**
     * Title of the application, which is integrated in the header
     * @var String
     */
    public $appName;

    /**
     *
     * Database handle for sql-queries
     * @var SPAppDbHandle
     */
    public $db;

    /**
     *
     * Root element for all html-elements
     * @var HtmlSite
     */
    public $ROOT;

    /**
     * Enables possibility for login validation
     * @var bool
     */
    protected $enableLogin = false;

    /**
     *
     * proxy for userinfo.
     * @var array
     */
    protected $user = false;

    protected $userList = [];

    const BUTTON_LOGIN  = "button_SPAAppLogin";
    const BUTTON_LOGOUT = "button_SPAAppLogout";

    /**
     * @param string $appName
     */
    function __construct($appName = 'SPApp'){
        $this->REQUEST = new SPA_Request();
        $this->SESSION = new SPAppDataCache();
        $this->appName  = $appName;
        $this->ROOT = new HtmlSite($this);
        $this->setTitle($appName);

        // Appid identifiziert Anwendung! wenn nicht aus dem Post uebernommen dann neu generieren.
        if($this->REQUEST->appid){
            $this->appid = $this->REQUEST->appid;
        } else {
            $this->appid = uniqid();
        }
        HtmlDropdown::setNoEntryLabel("Keine Angabe");
    }

    /**
     * Setter for App title
     * @param string $title
     */
    function setTitle($title){
        $this->title = $title;
        $this->ROOT->setHtmlTitle($title);
    }

    /**
     * Getter for App title
     * @return String
     */
    function getTitle(){
        return $this->appName;
    }

    /**
     * Setter for database handle
     * @param SPAppDbHandle $db
     */
    function setDb($db){
        $this->db =& $db;
    }

    /**
     * @deprecated
     */
    function commitLogout(){
        //please overwrite similar to abstract method.
    }

    function setDebugger(){
    }

    /**
     * Generic form to query credentials for the SPApp
     */
    function displayLoginForm(){
        $div = $this->ROOT->BODY->DIV()->setID('LoginBox');
        $form = $div->FORM('form_login')->addTagContent(["autocomplete"=>"off"]);
        $form->H1("Login")->setClass('pageTitle');
        $table = $form->TABLE();
        $TR = $table->TR();
        $TR->TD()->LABEL()->setFor("user")->add("User");
        $TR->TD()->TEXT('user')->setID('user');
        $TR->TD()->LABEL("Kennwort")->setFor("password");
        $TR->TD()->PASSWORD('password')->setID('password');
        $TR->TD()->SUBMIT(self::BUTTON_LOGIN, 'login');
    }

    /**
     * @param bool|true $input
     */
    function enableLogin($input = true){
        $this->enableLogin = $input;
    }

    /**
     * Primitive Handling of simple login
     *
     * @param array $storageArray
     * @return bool: success of login
     */
    function handleLogin(&$storageArray){
        if ($this->REQUEST[self::BUTTON_LOGOUT]){
            unset($storageArray['loginSuccess']);
            $storageArray['loginSuccess'] = false;
        }

        if(!isset($storageArray['loginSuccess']) or (isset($storageArray['loginSuccess']) and !$storageArray['loginSuccess'] == true)){
            if($this->REQUEST[self::BUTTON_LOGIN]){
                if(isset($this->userList[$this->REQUEST->user]) and $this->userList[$this->REQUEST->user] == md5($this->REQUEST->password)) {
                    $storageArray['loginSuccess'] = true;
                    $storageArray['user'] = $this->REQUEST->user;
                    return true;
                }else{
                    $this->displayLoginForm();
                    return false;
                }
            }else{
                $this->displayLoginForm();
                return false;
            }
        }else {
            $this->user = $storageArray['user'];
            return true;
        }
    }

    /**
     * @param $storageArray
     * @return bool
     */
    function verifyLogin(&$storageArray){
        return true;
    }

    /**
     * @param bool|False $var
     * @return array
     */
    function getUser($var=False){
        if($var!==False and is_array($this->user)){
            return $this->user[$var];
        }else{
            return $this->user;
        }
    }

    /**
     *
     */
    function process(){
        $start_time = microtime(true);
        echo $this->ROOT->render();
        $end_time = microtime(true);
        $this->plotTime =  $end_time - $start_time;
    }

}

/**
 * Class SPA_ResultList
 *
 * The Resultlist is a table container that displays an array as a html-Table. No functionality beyond this is intended.
 * @package spass
 */
class SPA_ResultList extends HtmlContainer {

    /**
     *
     * resultsset or tablecontent, which is to be displayed
     * @var ArrayObject
     */
    protected $listContents = null;

    /**
     * @var ArrayObject
     */
    protected $listMapping = null;

    /**
     *
     * HtmlTable object.
     * @var HtmlTable
     */
    protected $listTable;

    /**
     * @var bool if true, all columns will be shown regardless whether included in listMapping or not
     */
    protected $showAll = false;

    /**
     *
     * @param array $listContents result, to be displayed as a table
     * @param array $listMapping  mapping of result fields to column labels for display,
     *                            e.g. ['count'=>'Number of X']
     * @param bool $showAll if true, all columns will be showed regardless whether included in listMapping or not
     */
    function __construct(array &$listContents = null, array &$listMapping = null, $showAll = false){
        parent::__construct();
        $this->listContents = &$listContents;
        $this->listMapping = &$listMapping;
        $table = $this->TABLE();
        $this->listTable = &$table;
        $this->showAll = $showAll;
    }

    /**
     * @return string
     */
    function render(){
        $resultSet = $this->listContents;
        if ($resultSet){
            $row = $this->listTable->TR();
            $keys = array_keys(array_values($resultSet[0]));
            $firstResultRow = reset($resultSet);
            if($this->listMapping) {
                if($this->showAll == true) {
                    $columnKeys = array_keys($firstResultRow);
                    foreach (array_keys($firstResultRow) as $key) {
                        if(in_array($key, array_keys($this->listMapping))){
                            $row->TH($this->listMapping[$key]);
                        }else{
                            $row->TH($key);
                        }
                    }
                }else{
                    $columnKeys = array_keys($this->listMapping);
                    foreach ($this->listMapping as $key=>$label) {
                        $row->TH($label);
                    }
                }
            }else {
                $columnKeys = array_keys($firstResultRow);
                foreach ($columnKeys as $key) {
                    $row->TH($key);
                }
            }
            $lastValue = '';
            foreach ($resultSet as $dataRow) {
                $row = $this->listTable->TR();
                foreach ($columnKeys as $key) {
                    $value = $dataRow[$key];
                    if ($key == $columnKeys[0] and $lastValue == $value and !is_numeric($value)) {
                        $row->TD()->add('.&nbsp;');
                    } else {
                        $row->TD()->add(htmlspecialchars($dataRow[$key]));
                    }
                    if ($key == $keys[0]) {
                        $lastValue = $value;
                    }
                }
            }
        } else {
            $this->add("No results available!");
        }
        return parent::render();
    }


    /**
     * @param string $input concatenations of column alignements like 'lcr'
     */
    function setColumnAlignments($input){ // TODO replace with inherited function
        $this->listTable->setColumnAlignments($input);
    }
}



/**
 * Class SPA_ResultChoice
 *
 * The ResultChoice extends the SPA_ResultList and grants the possibility to select rows by clicking on them.
 * It needs Java-Script to work properly.
 *
 * @package spass
 */
class SPA_ResultChoice extends SPA_ResultList {

    /**
     *
     * unique key to identify a row in the resultset
     * @var ArrayObject of strings, containing the column names
     *
     * In case of results that have a primary key, you only need one identifier of course, but in order
     * to keep it generic, the ResultList only accepts arrays of strings.
     */
    protected $rowIdentifier = [];

    protected $selectedRow = [];

    protected $selectedRowIdentifier = []; // TODO: klären, ob man das überhaupt bruacht.

    protected $grantSubmit = ""; // TODO: umbauen hook fuer eingabeprüufng.

    /**
     * @param array $listContents
     * @param array $rowIdentifier
     * @param array $selectedRow
     * @param array $listMapping
     */
    function __construct(array $listContents = null, array $rowIdentifier, array $selectedRow, array $listMapping){
        parent::__construct($listContents, $listMapping);
        $this->rowIdentifier = $rowIdentifier;
        $this->selectedRow = $selectedRow;
    }

    /**
     * @return bool|HtmlForm
     */
    function getParentForm(){
        if ($this->parentContainer instanceof HtmlForm){
            return $this->parentContainer;
        }else{
            $parentContainer = $this->parentContainer;
            while (!$parentContainer instanceof HtmlForm){
                if($parentContainer->parentContainer instanceof HtmlForm){
                    return $parentContainer->parentContainer;
                }elseif($parentContainer->parentContainer instanceof HtmlBody){
                    return false;
                }
                $parentContainer = $parentContainer->parentContainer;
            }
            return false;
        }
    }

    /**
     * @return null|string
     */
    function render(){
        global $spassCoreConf;
        $prefix = $spassCoreConf['prefix']['prefixResultChoiceSelection'];

        $parentForm = $this->getParentForm();
        $parentFormHtmlID = ($parentForm)?$parentForm->getID():null;
        if(!$parentFormHtmlID){
            SPA_Error('SPA-Error: A SimpleEntryChoice can only be embedded in a form that possesses an unique HTML-ID!'); // TODO: Rework Error
        }
        if(count($this->listContents) > 0 and is_array($this->listContents[0])){  // TODO catch case with no 0 vector but higher ones.
            //if listMapping not set, derivation of content from first row of data
            if (!$this->listMapping){//if empty or not set
                $this->listMapping = array_combine(array_keys($this->listContents[0]),array_keys($this->listContents[0]));
            }
            $ownHtmlID = $this->getID();
            if($ownHtmlID){
                $this->ANCHOR($ownHtmlID);
                $js_actionInsert = ";document.forms['$parentFormHtmlID'].action='".$_SERVER['PHP_SELF']."#$ownHtmlID'";
            }else{
                $js_actionInsert = "";
            }
            $table = $this->listTable;
            $row = $table->TR();
            foreach($this->listMapping as $varName=>$label){ //plotting head
                $row->TH()->add($label)->addTagContent(["onclick"=>"if(".$this->grantSubmit."){document.getElementById('".$ownHtmlID."_selectedColumn"."').value='$varName'$js_actionInsert;document.forms['$parentFormHtmlID'].submit()}"])->setClass('SPA_EntryChoice_Headcell');
            }
            foreach ($this->listContents as $entry){ //plotting data
                $row = $table->TR();
                $jsonArray = [];
                foreach($this->rowIdentifier as $rowIdentifier){
                    $elementValue = (isset($entry[$rowIdentifier]))?$entry[$rowIdentifier]:'';
                    $jsonArray[] = "'$rowIdentifier':'$elementValue'";
                }
                $row->addTagContent(["onclick"=>'entryChoiceSetSelection(\''.$parentFormHtmlID.'\', {'.implode(',', $jsonArray).'});']);
                $row_is_selected = true;
                if([] == $this->selectedRow or $this->selectedRow == false){
                    foreach($this->rowIdentifier as $i){
                        if(!isset($entry[$i]) or $entry[$i] != $this->rootApp->REQUEST[$prefix.$i]){
                            $row_is_selected = false;
                            break;
                        }
                    }
                }else{
                    foreach($this->rowIdentifier as $i){
                        if(!(isset($this->selectedRow[$i]) and isset($entry[$i])  and ($entry[$i] == $this->selectedRow[$i]))){
                            $row_is_selected = false;
                            break;
                        }
                    }
                }
                if($row_is_selected){
                    $row->setStyle(["background"=>"grey", "color"=>"white"]);
                }
                foreach($this->listMapping as $varName=>$label){
                    if(isset($entry[$varName])){
                        $row->TD()->add($entry[$varName]);
                    }else{
                        $row->TD()->add("");
                    }
                }
            }
            $this->HIDDEN($this->getID()."_selectedColumn"); //  FIXME does not work;
            foreach($this->rowIdentifier as $identifier){
                $elementValue = (isset($this->selectedRow[$identifier]))?$this->selectedRow[$identifier]:'';
                $this->HIDDEN($prefix.$identifier, $elementValue);
            }
            $this->SCRIPTSOURCE('/spass/js/spass_forms.js');
        }
        return HtmlContainer::render();
    }
}


/**
 * Class SPA_ResultChoice
 *
 * The ResultChoice extends the SPA_ResultList and grants the possibility to select rows by clicking on them.
 * It needs Java-Script to work .
 *
 * @package spass
 */
class SPA_ResultMultiChoice extends SPA_ResultList {

    protected $rowIdentifier = null;

    protected $selectedRows = [];

    protected $listMapping = [];

    protected $selectedRowIdentifier = []; // TODO: klären, ob man das überhaupt bruacht.

    protected $grantSubmit = ""; // TODO: umbauen hook fuer eingabeprüufng.

    /**
     * @param array $listContents
     * @param string $rowIdentifier
     * @param array $selectedRows
     * @param array $listMapping
     */
    function __construct(array $listContents = null, $rowIdentifier, array $selectedRows, array $listMapping){
        parent::__construct($listContents);
        $this->rowIdentifier = $rowIdentifier;
        $this->selectedRows = $selectedRows;
        $this->listMapping = $listMapping;
    }

    /**
     * @return bool|HtmlForm
     */
    function getParentForm(){ //TODO -auslagern
        if ($this->parentContainer instanceof HtmlForm){
            return $this->parentContainer;
        }else{
            $parentContainer = $this->parentContainer;
            while (!$parentContainer instanceof HtmlForm){
                if($parentContainer->parentContainer instanceof HtmlForm){
                    return $parentContainer->parentContainer;
                }elseif($parentContainer->parentContainer instanceof HtmlBody){
                    return false;
                }
                $parentContainer = $parentContainer->parentContainer;
            }
            return false;
        }
    }

    /**
     * @return null|string
     */
    function render(){
        global $spassCoreConf;
        $prefix = $spassCoreConf['prefix']['prefixResultChoiceSelection'];

        $parentForm = $this->getParentForm();
        $parentFormHtmlID = ($parentForm)?$parentForm->getID():null;
        if(!$parentFormHtmlID){
            trigger_error('SPA-Error: A SimpleEntryChoice can only be embedded in a form that possesses an unique HTML-ID!', E_USER_ERROR);
        }
        if(count($this->listContents) > 0 and is_array($this->listContents[0])){  // TODO catch case with no 0 vector but higher ones.
            //if listMapping not set, derivation of content from first row of data
            if ($this->listMapping === []){//if empty or not set
                $this->listMapping = array_combine(array_keys($this->listContents[0]),array_keys($this->listContents[0]));
            }
            $ownHtmlID = $this->getID();
            if($ownHtmlID){
                $this->ANCHOR($ownHtmlID);
                $js_actionInsert = ";document.forms['$parentFormHtmlID'].action='".$_SERVER['PHP_SELF']."#$ownHtmlID'";
            }else{
                $js_actionInsert = "";
            }
            $table = $this->listTable;
            $row = $table->TR();
            foreach($this->listMapping as $varName=>$label){ //plotting head
                $row->TH()->add($label)->addTagContent(["onclick"=>"if(".$this->grantSubmit."){document.getElementById('".$ownHtmlID."_selectedColumn"."').value='$varName'$js_actionInsert;document.forms['$parentFormHtmlID'].submit()}"])->setClass('SPA_EntryChoice_Headcell');
            }

            $rowIdentifier = $this->rowIdentifier;

            $selectedRows = $this->selectedRows;

            foreach ($this->listContents as $entry){ //plotting data
                $row = $table->TR();
                $elementValue = (isset($entry[$rowIdentifier]))?$entry[$rowIdentifier]:'';
                $row->addTagContent(["onclick"=>"entryMultiChoiceSetSelection('$parentFormHtmlID', '$rowIdentifier', '$elementValue');"]);
                if($selectedRows && isset($entry[$rowIdentifier]) && in_array($entry[$rowIdentifier], $selectedRows)){
                    $row->setStyle(["background"=>"grey", "color"=>"white"]);
                }else{
                    if(!(isset($this->selectedRows[$rowIdentifier]) and isset($entry[$rowIdentifier])  and ($entry[$rowIdentifier] == $this->selectedRows[$rowIdentifier]))){
                    }else{
                        $row->setStyle(["background"=>"grey", "color"=>"white"]);
                    }
                }
                foreach($this->listMapping as $varName=>$label){
                    if(isset($entry[$varName])){
                        $row->TD()->add($entry[$varName]);
                    }else{
                        $row->TD()->add("");
                    }
                }
            }
            $this->HIDDEN($this->getID()."_selectedColumn");
            $this->HIDDEN($prefix.$rowIdentifier, implode(';', $selectedRows));
            $this->SCRIPTSOURCE('/spass/js/spass_forms.js');
        }
        return HtmlContainer::render();
    }
}


/**
 * Class SPA_ResultEditor
 * @package spass
 */
class SPA_ResultEditor extends SPA_ResultChoice{

    /**
     * List of columns that are not allowed to be edited.
     *
     * @var array
     */
    protected $protectedColumns = [];

    /**
     * Container for additional configurations for each column
     *
     * @var array
     */
    protected $columnConfigs = [];

    /**
     * @param array $listContents
     * @param array $rowIdentifier
     * @param array $selectedRow
     * @param array $listMapping
     */
    function __construct(array $listContents = null, array $rowIdentifier, array $selectedRow, array $listMapping){
        parent::__construct($listContents, $rowIdentifier, $selectedRow, $listMapping);
        $this->rowIdentifier = $rowIdentifier;
        $this->selectedRow = $selectedRow;
        $this->listMapping = $listMapping;
        $this->protectedColumns = $rowIdentifier; //$rowIdentifiers can't be modified;
    }

    /**
     * Define columns, that cannot be edited.
     *
     * Listing of columns as arguments required
     */
    function setProtectedColumns(){
        $this->protectedColumns = array_merge($this->protectedColumns, func_get_args());
        return $this;
    }


    /**
     * Redefine a column for a selected row as a Dropdown
     *
     * @param string $columnName
     * @param array $entryCodes
     * @param bool $multiplesAllowed
     * @param int $displaySize
     *
     * @return $this
     */
    function setEntryCodes($columnName, array $entryCodes, $multiplesAllowed = False, $displaySize = 1)
    {
        if(!isset($this->columnConfigs[$columnName])){
            $this->columnConfigs[$columnName] = [];
        }
        $this->columnConfigs[$columnName]['entryCodes'] = $entryCodes; // todo use constants
        $this->columnConfigs[$columnName]['multiplesAllowed'] = $multiplesAllowed; // todo use constants
        $this->columnConfigs[$columnName]['displaySize'] = $displaySize; // todo use constants
        return $this;
    }

    /**
     * @return string
     */
    function render()
    {
        global $spassCoreConf;
        $prefix = $spassCoreConf['prefix']['prefixResultChoiceSelection'];
        $parentForm = $this->getParentForm();
        $parentFormHtmlID = ($parentForm)?$parentForm->getID():null;
        if(!$parentFormHtmlID){
            trigger_error('SPA-Error: SPA_ResultEditor can only be embedded in a form that possesses an unique HTML-ID!', E_USER_ERROR);
        }
        if(count($this->listContents) > 0 and is_array($this->listContents[0])){
            //if listMapping not set, derivation of content from first row of data
            if ($this->listMapping === []){//if empty or not set
                $this->listMapping = array_combine(array_keys($this->listContents[0]),array_keys($this->listContents[0]));
            }
            $ownHtmlID = $this->getID();
            if($ownHtmlID){
                $this->ANCHOR($ownHtmlID);
                $js_actionInsert = ";document.forms['$parentFormHtmlID'].action='".$_SERVER['PHP_SELF']."#$ownHtmlID'";
            }else{
                $js_actionInsert = "";
            }
            $table = $this->listTable;
            $row = $table->TR();
            foreach($this->listMapping as $varName=>$label){ //plotting head
                $row->TH()->add($label)->addTagContent(["onclick"=>"if(".$this->grantSubmit."){document.getElementById('".$ownHtmlID."_selectedColumn"."').value='$varName'$js_actionInsert;document.forms['$parentFormHtmlID'].submit()}"])->setClass('SPA_EntryChoice_Headcell');
            }
            foreach ($this->listContents as $entry){ //plotting data
                $row = $table->TR();
                $tmpArray = [];
                $jsonArray = [];
                foreach($this->rowIdentifier as $rowIdentifier){
                    $elementValue = (isset($entry[$rowIdentifier]))?$entry[$rowIdentifier]:'';
                    $tmpArray[] = "document.getElementById($prefix$rowIdentifier').value='$elementValue'";
                    $jsonArray[] = "'$rowIdentifier':'$elementValue'";
                }
                $row_is_selected = true;
                if([] == $this->selectedRow){
                    foreach($this->rowIdentifier as $i){
                        if(!isset($entry[$i]) or $entry[$i] != $this->rootApp->REQUEST[$prefix.$i]){
                            $row_is_selected = false;
                            break;
                        }
                    }
                }else{
                    foreach($this->rowIdentifier as $i){
                        if(!(isset($entry[$i]) and isset($this->selectedRow[$i]) and $entry[$i] == $this->selectedRow[$i])){
                            $row_is_selected = false;
                            break;
                        }
                    }
                }
                if($row_is_selected){
                    $row->setStyle(["background"=>"grey", "color"=>"white"]); //todo stylesheet
                    foreach($this->listMapping as $columnName=>$label){
                        if(!in_array($columnName, $this->protectedColumns)){
                            if(!isset($this->columnConfigs[$columnName])){
                                $row->TD()->TEXT($columnName, $entry[$columnName]);
                            }else{
                                if($this->columnConfigs[$columnName]['multiplesAllowed']===true){
                                    $row->TD()->DROPDOWN($columnName, $this->columnConfigs[$columnName]['entryCodes'], $entry[$columnName])->setNoMissing()->setMultiple($this->columnConfigs[$columnName]['displaySize']);
                                }else{
                                    $row->TD()->DROPDOWN($columnName, $this->columnConfigs[$columnName]['entryCodes'], $entry[$columnName])->setNoMissing();
                                }
                            }
                        }else{
                            $row->TD()->add($entry[$columnName]);
                        }
                    }
                    $row->TD()->SUBMIT($spassCoreConf['submits']['submitSaveResultEditor'], "save")->setStyle(['width'=>'120px'])->addTagContent(["onclick"=>'entryChoiceSetSelection(\''.$parentFormHtmlID.'\', {'.implode(',', $jsonArray).'});']);
                }else{
                    $row->addTagContent(["onclick"=>'entryChoiceSetSelection(\''.$parentFormHtmlID.'\', {'.implode(',', $jsonArray).'});']);
                    foreach($this->listMapping as $columnName=>$label){
                        if(isset($entry[$columnName]) and !is_array($columnName)){
                            if(isset($this->columnConfigs[$columnName])
                                and isset($this->columnConfigs[$columnName]['entryCodes'])
                                and !is_array($entry[$columnName])){
                                $row->TD()->add($this->columnConfigs[$columnName]['entryCodes'][$entry[$columnName]]);
                            }else{
                                $row->TD()->add($entry[$columnName]);
                            }
                        }else{
                            $row->TD()->add("");
                        }
                    }
                }
            }
            $this->HIDDEN($this->getID()."_selectedColumn"); //  FIXME does not work;
            foreach($this->rowIdentifier as $identifier){
                $elementValue = (isset($this->selectedRow[$identifier]))?$this->selectedRow[$identifier]:'';
                $this->HIDDEN($prefix.$identifier, $elementValue);
            }
            $this->SCRIPTSOURCE('/spass/js/spass_forms.js');
        }
        return HtmlContainer::render();
    }


}

function SPA_ErrorHandler($errno, $errstr, $errfile, $errline, $errcontext){
    switch ($errno) {
    default:
        echo "<br /><span style='color:red;font-weight:bold'>ERROR</span> [$errno] $errstr<br />\n";
        echo "  Fatal error in line $errline in file $errfile";
        echo ", PHP " . PHP_VERSION . " (" . PHP_OS . ")<br />\n";
        echo "<span style='color:red;font-weight:bold'>ABORT...</span><br /><br />\n";
        echo "BACKTRACE:<br />";
        $backtrace = debug_backtrace();
        foreach($backtrace as $row){
            if(isset($row['file'])){
                echo $row['line']." in ".$row['function']."\tin\t".$row['file']."<br />";
            }
        }
        die();
    }
    return false;
}

$customErrorHandler = set_error_handler("spass\SPA_ErrorHandler");


/**
 * Class SPA_Portal
 * @deprecated
 * @package spass
 */
class SPA_Portal extends SPApp{

    /**
     *
     * HtmlObject zur Darstellung des Portals. Besteht aus einem Menue links und der Centerbox.
     * @var SPA_MenuFrame
     */
    public $menuFrame;

    /**
     * @param string $title
     * @param array $navButtons
     */
    function __construct($title = "", array $navButtons = []){
        $this->appName = __CLASS__.$title;
        parent::__construct($title);
        $this->menuFrame = new SPA_MenuFrame($this, $navButtons);
        if(!isset($_SESSION[$this->appName])){ //XXX fixme
            $_SESSION[$this->appName][$this->appid] = "";
        }
    }

    function process(){
        $this->menuFrame->process($_SESSION[$this->appName][$this->appid]);
        parent::process();
    }
}

/**
 * Class SPA_MenuFrame
 * @package spass
 */
class SPA_MenuFrame {

    /**
     *
     * Root application to which it belongs, should be passed by reference
     * @var SPApp
     */
    protected $rootApp;

    /**
     * Left area with navbuttons
     * @var HtmlDiv
     */
    protected $naviBox;

    /**
     * central area for display of contents
     * @var HtmlDiv
     */
    protected $centerBox;

    /**
     * available buttons in display
     * @var array
     */
    protected $navButtons = [];

    /**
     *
     * default function to be called
     * @var String
     */
    protected $defaultFunction = NULL;

    /**
     *
     * function packages that contain availabe functions
     * @var array
     */
    protected $funcPackages = [];

    /**
     *
     * short title
     * @var string
     */
    protected $titleShort = Null;

    /**
     * @param $rootApp
     * @param array $navButtons
     */
    function __construct(&$rootApp, array $navButtons){
        $this->rootApp =& $rootApp;
        $this->navButtons = $navButtons;
        $this->centerBox = $this->rootApp->ROOT->BODY->DIV('centerBox')->addStyle(['width'=>'1000px']);
        $this->centerBox->H1($this->rootApp->getTitle(), 'centerBoxTitle');
        $this->naviBox = $this->rootApp->ROOT->BODY->DIV('naviBox');
    }

    /**
     * @param $titleShort
     */
    function setTitleShort($titleShort){
        $this->titleShort = $titleShort;
    }

    /**
     * @param null $storageArray
     */
    function process(&$storageArray = null){
        $form = $this->naviBox->FORM();
        $form->H1('Navigation');
        foreach($this->navButtons as $key=>$value){
            $form->SUBMIT($key, $value)->setClass('navButton');
        }
        $form->BR(2);
        $form->SUBMIT('button_SPAAppLogout', 'abmelden')->setClass('navButton');
        if($this->rootApp->getUser('lastname')){
            $form->P("Sie sind angemeldet als:<br /><strong style=\"color:red\">".$this->rootApp->getUser('lastname')."</strong>");
        }
        $this->handleNavigation($storageArray);
    }

    /**
     * @param $default
     */
    function setDefaultFunction($default){
        $this->defaultFunction = $default;
    }

    /**
     * @return HtmlContainer|HtmlDiv|HtmlInput|HtmlTable
     */
    function getCenterBox(){
        return $this->centerBox;
    }

    /**
     * @param $navButtons
     */
    function setNavButtons($navButtons){
        $this->navButtons = $navButtons;
    }

    /**
     * @param FuncPackage $package
     */
    function addFuncPackage(FuncPackage $package){
        $chunks = explode('\\',get_class($package));
        $this->funcPackages[array_pop($chunks)] = $package->setRootApp($this->rootApp);
    }

    /**
     * @param null $storageArray
     */
    function handleNavigation(&$storageArray = null){
        //TODO hoch zum Portal
        if($storageArray == null){
            if(!isset($_SESSION[$this->rootApp->getTitle()][$this->rootApp->appid])){
                $_SESSION[$this->rootApp->getTitle()][$this->rootApp->appid] = array();
            }
            $storageArray = $_SESSION[$this->rootApp->getTitle()][$this->rootApp->appid];
        }

        foreach($this->navButtons as $key=>$value){
            $navKey = '';
            if ($this->rootApp->REQUEST[$key]){
                $navKey = $key;
                $storageArray['navbutton'] = $key;
            } elseif(isset($storageArray['navbutton'])) {
                $navKey = $storageArray['navbutton'];
            }
            if ($navKey=='' and $this->defaultFunction != ''){
                $navKey = $this->defaultFunction;
            }
            $funcArray = explode('@', $navKey);  //fixme
        }
        if ($funcArray[0]){
            if(isset($this->navButtons[$navKey])){
                if (!$this->titleShort){
                    $this->rootApp->setTitle(substr($this->rootApp->getTitle(), 0, 5).": ".$this->navButtons[$navKey]);
                }else{
                    $this->rootApp->setTitle($this->titleShort.": ".$this->navButtons[$navKey]);
                }
            }
            if(!isset($funcArray[1])){
                $this->centerBox->SPAN("Das f&uuml;r die Funktion $funcArray[0] konnte nicht gefunden werden.")->addStyle(array('color'=>'red'));
            }elseif($funcArray[1]==="root"){
                $funcname = $funcArray[0];
                $this->rootApp->$funcname($this->centerBox);
            }elseif(!isset($this->funcPackages[$funcArray[1]])){
                $this->centerBox->SPAN("Das f&uuml;r die Funktion $funcArray[0] ben&ouml;tigte Funktionspaket $funcArray[1] konnte nicht gefunden werden.")->addStyle(array('color'=>'red'));
            }else{
                $this->funcPackages[$funcArray[1]]->$funcArray[0]($this->centerBox);
            }
            $storageArray['navbutton'] = $navKey;
        }
    }

}


/**
 * Class FuncPackage
 *
 * Containter class for collection of functions of a portal. You can adress the parent Object for the function
 * via rootApp
 * @package spass
 */
class FuncPackage{

    /**
     *
     * Root SPA-Application
     * @var SPApp
     */
    protected $rootApp;

    /**
     * db handle
     * @var
     */
    protected $db;

    /**
     * FuncPackage constructor.
     * @param SPApp $rootApp
     */
    function __construct(SPApp &$rootApp=NULL){
        $this->setRootApp($rootApp);
    }
    function setRootApp(&$app){
        $this->rootApp =& $app;
        if(isset($app->db)){
            $this->db =& $app->db;
        }
        return $this;
    }
}
