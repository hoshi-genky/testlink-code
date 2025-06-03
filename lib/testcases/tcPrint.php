<?php
/** 
 * TestLink Open Source Project - http://testlink.sourceforge.net/
 * This script is distributed under the GNU General Public License 2 or later. 
 *
 * @internal  filename: tcPrint.php 
 * @package   TestLink
 * @author    Francisco Mancardi - francisco.mancardi@gmail.com
 * @copyright 2005-2022, TestLink community 
 * @link      http://www.testlink.org
 *
 */

require_once("../../config.inc.php");
require_once("../../cfg/const.inc.php"); 
require_once("../../cfg/reports.cfg.php"); 
require_once("print.inc.php"); 
require_once("common.php");
testlinkInitPage($db);
$templateCfg = templateConfiguration();

$tree_mgr = new tree($db);
$args = init_args($db);
$node = $tree_mgr->get_node_hierarchy_info($args->tcase_id);
$node['tcversion_id'] = $args->tcversion_id;
$gui = initializeGui($args,$node);


// Struture defined in printDocument.php  
$printingOptions = [
  'toc' => 0,
  'body' => 1,
  'summary' => 1, 
  'header' => 0,
  'headerNumbering' => 0,
  'passfail' => 0, 
  'author' => 1, 
  'notes' => 0, 
  'requirement' => 1, 
  'keyword' => 1, 
  'cfields' => 1, 
  'displayVersion' => 1, 
  'displayDates' => 1, 
  'docType' => SINGLE_TESTCASE, 
  'importance' => 1,
  'platform' => 1
];

$level = 0;
$tplanID = 0;
$prefix = null;
$text2print = '';

$basehref = $_SESSION['basehref'];
$smarty = new TLSmarty();
$text2print .= renderHTMLHeader($gui->page_title,$_SESSION['basehref'],
                                SINGLE_TESTCASE,
                                array('gui/javascript/testlink_library.js'
                                ,"third_party/jquery/jquery-3.4.1.min.js")
                              );
                              // <script type="text/javascript" 
                              // src="{$basehref}{$smarty.const.TL_JQUERY}" 
                              // language="javascript"></script>
$env = new stdClass();
$env->base_href = $_SESSION['basehref'];
$env->reportType = $printingOptions['docType'];

$text2print .= renderTestCaseForPrinting($db,$node,$printingOptions,$env,
                                         array('level' => $level,'tplan_id' => $tplanID,
                                               'tproject_id' => $args->tproject_id,'prefix' => $prefix),$level);
echo $text2print;
?>

<script type="text/javascript"><!--

$(function () {
  setTimeout(onInitClickCopy,300);
});

function onInitClickCopy(){
    if($('tr td div')[0]){
       //console.log("find");
       $('tr td div').click(onClickWikiPre).css("cursor","pointer");
         
    }
}
function onClickWikiPre(){
    console.log("click text: " + $(this).text());
    let copyText = $(this).text();
    let regex = /[ ]/ig;
    copyText = copyText.replace(regex,' ');
    regex = /[\r]/ig;
    copyText = copyText.replace(regex,'');
    regex = /[\n][\n]/ig;
    copyText = copyText.replace(regex,'\n');
    if (!navigator.clipboard) {
        // navigator.clipboardが利用的出来ない場合は、フォールバックなコードを実行
        copyTextFallback(copyText);
        dispMsg(this, `コピーしました。`);
        return;
    }
    // https環境で動作するコード
    navigator.clipboard.writeText(copyText).then(
        () => {
            dispMsg(this, `コピーしました。`);
        },
        () => {
            dispMsg(this, 'コピーに失敗しました。');
        }
    );
}

function dispMsg(target, txt){
   if(!$(target).parent().find(".ret-msg")[0]){
      $(target).parent().append("<span class='ret-msg'>" + txt + "</span>");
   }
   let pre = $(target);
   let pos = pre.position();
   let top = $(window).scrollTop() + ($(window).height()*0.4);

   let left = parseInt(pos.left) + 50;
   console.log("top: " + top + ", left" + left);

   let msg = $(target).parent().find(".ret-msg");
   msg.css("display", "block");
   msg.css("position", "absolute");
   msg.css("left", "30%");
   msg.css("top", top + "px");
   msg.css("opacity", ".7");
   msg.css("background-color", "#333");
   msg.css("color", "#fff");
   msg.css("font-size", "36px");
   msg.delay(2000).fadeOut("slow");
}


// http環境で動くコピーコード
function copyTextFallback(str){
    if (!str || typeof str !== 'string') {
        return '';
    }
    const textarea = document.createElement('textarea');
    textarea.id = 'tmp_copy';
    textarea.style.position = 'fixed';
    textarea.style.right = '100vw';
    textarea.style.fontSize = '16px';
    textarea.setAttribute('readonly', 'readonly');
    textarea.textContent = str;
    document.body.appendChild(textarea);
    const elm = document.getElementById('tmp_copy'); // as HTMLTextAreaElement;
    elm.select();
    const range = document.createRange();
    range.selectNodeContents(elm);
    const sel = window.getSelection();
    if (sel) {
        sel.removeAllRanges();
        sel.addRange(range);
    }
    elm.setSelectionRange(0, 999999);
    document.execCommand('copy');
    document.body.removeChild(textarea);

    return str;
}

--></script>

<?php
/*
  function: init_args

  args:
  
  returns: 

*/
function init_args($dbH)
{
  $_REQUEST = strings_stripSlashes($_REQUEST);

  list($args,$env) = initContext();
  $args->tcase_id = intval(isset($_REQUEST['testcase_id']) ? intval($_REQUEST['testcase_id']) : 0);
  $args->tcversion_id = intval(isset($_REQUEST['tcversion_id']) ? intval($_REQUEST['tcversion_id']) : 0);

  $args->tproject_name = testproject::getName($dbH,$args->tproject_id);

  $args->goback_url=isset($_REQUEST['goback_url']) ? $_REQUEST['goback_url'] : null;

  $ofd = array('HTML' => lang_get('format_html'),'ODT' => lang_get('format_odt'), 
               'MSWORD' => lang_get('format_msword'));
  $args->outputFormat = isset($_REQUEST['outputFormat']) ? $_REQUEST['outputFormat'] : null;
  $args->outputFormat = isset($ofd[$args->outputFormat]) ? $ofd[$args->outputFormat] : null;
  
  $args->outputFormatDomain = array('NONE' => '') + $ofd;
  return $args;
}

/**
 *
 */
function initializeGui(&$argsObj,&$node)
{
  $guiObj = new stdClass();
  $guiObj->outputFormatDomain = $argsObj->outputFormatDomain;
  $guiObj->object_name='';
  $guiObj->goback_url = !is_null($argsObj->goback_url) ? $argsObj->goback_url : ''; 
  $guiObj->object_name = $node['name'];
  $guiObj->page_title = sprintf(lang_get('print_testcase'),$node['name']);
  $guiObj->tproject_name=$argsObj->tproject_name;
  $guiObj->tproject_id=$argsObj->tproject_id;
  $guiObj->tcase_id=$argsObj->tcase_id; 
  $guiObj->tcversion_id=$argsObj->tcversion_id;

  return $guiObj;
}

