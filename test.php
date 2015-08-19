<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);


class Tepl {
    public $xslt;
    public $xml;
    function __construct($src) {
        $this->xslt = new XSLTProcessor();
        $this->xslt->registerPHPFunctions();
        $this->xslt->importStylesheet(
            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?>
                <xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:php="http://php.net/xsl">
                <xsl:output method="text" encoding="UTF-8"/>
                <xsl:template match="/template-arguments">'
                .$this->compileFragment(strtr(
                        $src, 
                        array(
                            "<" => "&lt;",
                            ">" => "&gt;",
                            '"' => "&quot;",
                            "'" => "&apos;",
                            "&" => "&amp;",
                        )
                    )
                )
                .'</xsl:template></xsl:stylesheet>'
            )
        );
        print($xml->asXml());
    }
    function apply($args) {
        $this->xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><template-arguments/>');
        $this->array_to_xml($args, $this->xml);
        return $this->xslt->transformToXML($this->xml);
        
    }
    protected function matched($m) {
        if(isset($m['variable']) && $m['variable']!="") {
            // outputs argument
            return 
                '<xsl:value-of select="'.$m['variable_exp'].'" />';
        } else if(isset($m['repeat']) && $m['repeat']!="") {
            // iterates, descends into item
            return 
                '<xsl:for-each select="'.$m['repeat_exp'].'">'
                .$this->compileFragment($m['repeat_inside'])
                .'</xsl:for-each>';
        } else if(isset($m['if']) && $m['if']!="") {
            // shows if argument
            return 
                '<xsl:if test="'.$m['if_exp'].'">'
                .$this->compileFragment($m['if_inside'])
                .'</xsl:if>';
        } else if(isset($m['else']) && $m['else']!="") {
            // shows if not argument
            return 
                '<xsl:if test="not('.$m['else_exp'].')">'
                .$this->compileFragment($m['else_inside'])
                .'</xsl:if>';
        } else if(isset($m['block']) && $m['block']!="") {
            // descend into argument
            return 
                '<xsl:for-each select="('.$m['block_exp'].')[1]">'
                .$this->compileFragment($m['block_inside'])
                .'</xsl:for-each>';
        } else {
            return '<xsl:text>'.$m[0].'</xsl:text>';
        }
    }
    protected $exp = "[^}`]+";
    protected function compileFragment($src) {
        return preg_replace_callback(array(
            "/(?(R)(?:[^{}]*{(?R)})*[^{}]*|(?:
                (?P<variable>{(?P<variable_exp>$this->exp)})|
                (?P<repeat>{\\*(?P<repeat_exp>$this->exp)`(?P<repeat_inside>(?R))})|
                (?P<if>{\\?(?P<if_exp>$this->exp)`(?P<if_inside>(?R))})|
                (?P<else>{!(?P<else_exp>$this->exp)`(?P<else_inside>(?R))})|
                (?P<block>{(?P<block_exp>$this->exp)`(?P<block_inside>(?R))})|
                \\s+
            ))/xs",
        ), array($this, 'matched'), $src);
    }

    protected function array_to_xml($arr, &$xml, $this_key = "", $parent_node = NULL) {
        $no_numeric_key_yet = true;
        foreach($arr as $key => $value) {
            if(is_array($value)) {
                if(is_numeric($key)) {
                    if($no_numeric_key_yet) {
                        $child = $xml;
                        $no_numeric_key_yet = false;
                    } else {
                        $child = $parent_node->addChild($this_key);
                    }
                    $key = $this_key;
                } else {
                    $child = $xml->addChild($key);
                }
                $this->array_to_xml($value, $child, $key, $xml);
            } else {
                $xml->addChild($key, htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'));
            }
        }
    }
}


$tepl = new Tepl(<<<TEPL
{*entry`<a href="{url}">{changed`<b class="{../label}">}{label} {php:functionString('ucfirst', ancestor::*/title)}{?changed`</b>}</a>
}
TEPL
);

$args = array(
    "title" => "boss",
    "entry" => array(
        array(
        "changed" => true,
            "url" => "http://google.com/1",
            "label" => "Google-1"
        ),
        array(
            "url" => "http://google.com/2",
            "label" => "Google-2"
        ),
        array(
            "url" => "http://google.com/3",
            "label" => "Google-3"
        ),
    )
);

echo $tepl->apply($args);
    
