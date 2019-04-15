<?php

/**
 * The configuration parameters for TinyMCE 4.x.
 *
 * Comment form full-dark configuration
 * @author Stephen Billard (sbillard)
 */
$MCEcss = 'dark_content.css';
$MCEskin = "oxide-dark";
$MCEselector = "textarea.textarea_inputbox, textarea.texteditor_comments";
$MCEplugins = "advlist autolink lists link image charmap hr anchor pagebreak " .
				"searchreplace visualblocks visualchars code " .
				"insertdatetime media directionality " .
				"emoticons paste";
$MCEtoolbars[1] = "undo redo | styleselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image | searchreplace visualchars | emoticons | ltr rtl code";
$MCEstatusbar = false;
$MCEmenubar = false;
include(TINYMCE . '/config/config.js.php');
