<?php

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.");
}

$plugins->add_hook("postbit", "pdfthreads_postbit");
$plugins->add_hook("showthread_start", "pdfthreads_showthread");
$plugins->add_hook("misc_start", "pdfthreads_misc");

function pdfthreads_info()
{
	global $lang;
	$lang->load('pdfthreads');
	
	return array(
		"name"			=> $lang->pdf_name,
		"description"	=> $lang->pdf_description,
		"website"		=> "https://github.com/its-sparks-fly",
		"author"		=> "sparks fly",
		"authorsite"	=> "https://sparks-fly.info",
		"version"		=> "1.0",
		"compatibility" => "18*"
	);
}

function pdfthreads_install()
{
    global $db, $lang;
	
	$setting_group = [
		'name' => 'pdfthreads',
		'title' => $lang->pdf_settings,
		'description' => $lang->pdf_settings_description,
		'disporder' => 5,
		'isdefault' => 0
	];

	$gid = $db->insert_query("settinggroups", $setting_group);
	
	$setting_array = [
		'pdfthreads_forums' => [
			'title' => $lang->pdf_forums,
			'description' => $lang->pdf_forums_description,
			'optionscode' => 'forumselect',
			'value' => 'Blue', // Default
			'disporder' => 1
		]
	];

	foreach($setting_array as $name => $setting)
	{
		$setting['name'] = $name;
		$setting['gid'] = $gid;

		$db->insert_query('settings', $setting);
	}

	rebuild_settings();

}

function pdfthreads_is_installed()
{
	global $mybb;
	if(isset($mybb->settings['pdfthreads_forums']))
	{
		return true;
	}

	return false;
}

function pdfthreads_uninstall()
{
	global $db;

	$db->delete_query('settings', "name IN ('pdfthreads_forums')");
	$db->delete_query('settinggroups', "name = 'pdfthreads'");

	rebuild_settings();
}

function pdfthreads_activate()
{
	global $db;
	
	include MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("postbit_classic", "#".preg_quote('{$post[\'button_edit\']}')."#i", '{$post[\'pdfthreads\']}{$post[\'button_edit\']}');
	find_replace_templatesets("postbit", "#".preg_quote('{$post[\'button_edit\']}')."#i", '{$post[\'pdfthreads\']}{$post[\'button_edit\']}');
	find_replace_templatesets("showthread", "#".preg_quote('{$printthread}')."#i", '{$pdfthreads}{$printthread}');
	
	$insert_array = array(
		'title'		=> 'pdfthreads_postbit',
		'template'	=> $db->escape_string('<a href="misc.php?action=pdfthreads&pid={$post[\'pid\']}" title="{$lang->pdfthreads}" class="postbit_edit"><span>{$lang->pdfthreads_button}</span></a>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);
	
	$insert_array = array(
		'title'		=> 'pdfthreads_showthread',
		'template'	=> $db->escape_string('<li class="printable"><a href="misc.php?action=pdfthreads&tid={$thread[\'tid\']}">{$lang->pdfthreads_button}</a></li>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

}

function pdfthreads_deactivate()
{
	global $db;

	include MYBB_ROOT."/inc/adminfunctions_templates.php";
    find_replace_templatesets("postbit_classic", "#".preg_quote('{$post[\'pdfthreads\']}')."#i", '', 0);
    find_replace_templatesets("postbit", "#".preg_quote('{$post[\'pdfthreads\']}')."#i", '', 0);
	find_replace_templatesets("showthread", "#".preg_quote('{$pdfthreads}')."#i", '', 0);

	$db->delete_query("templates", "title LIKE '%pdfthreads%'");
}

function pdfthreads_postbit(&$post) {
	global $lang, $templates, $mybb, $forum;
	$lang->load('pdfthreads');	
	
	// insert button
	$forum['parentlist'] = ",".$forum['parentlist'].",";
	$selectedforums = explode(",", $mybb->settings['pdfthreads_forums']);
	foreach($selectedforums as $selected) {
		if(preg_match("/,{$selected},/i", $forum['parentlist']) || $mybb->settings['pdfthreads_forums'] == "-1") {
			eval("\$post['pdfthreads'] = \"".$templates->get("pdfthreads_postbit")."\";");
			return $post;	
		}
	}
}

function pdfthreads_showthread() {
	global $lang, $templates, $mybb, $forum, $thread, $pdfthreads;
	$lang->load('pdfthreads');	
	
	// insert button
	$forum['parentlist'] = ",".$forum['parentlist'].",";
	$selectedforums = explode(",", $mybb->settings['pdfthreads_forums']);
	foreach($selectedforums as $selected) {
		if(preg_match("/,{$selected},/i", $forum['parentlist']) || $mybb->settings['pdfthreads_forums'] == "-1") {
			eval("\$pdfthreads = \"".$templates->get("pdfthreads_showthread")."\";");
		}
	}
}

function pdfthreads_misc() {
	global $mybb, $db;
	require(MYBB_ROOT.'inc/3rdparty/tfpdf.php');
	
	$mybb->input['action'] = $mybb->get_input('action');
	if($mybb->input['action'] == "pdfthreads") {
		
		class finalPDF extends tFPDF
		{		
			// Page header
			function Header()
			{
				$this->SetFont('Courier','',9);
				$this->Cell(80);
				$this->Cell(30,10,$this->title,0,0,'C');
				$this->Ln(20);
			}

			// Page footer
			function Footer()
			{
				$this->SetY(-15);
				$this->SetFont('Arial','',8);
				$this->Cell(0,10,'Seite '.$this->PageNo().'/{nb}',0,0,'R');
			}
		}
		
		$tid = (int)$mybb->input['tid'];
		$thread = get_thread($tid);
		
		// generate pdf
		$pdf = new finalPDF();
		$pdf->AliasNbPages();
		
		// front page view
		if($thread) {
			$pdf->AddPage();
			$pdf->SetFont('Arial','B',14);
			$pdf->SetY(90);
			$pdf->MultiCell(185,10,$thread['subject'],0,'C');
			$pdf->SetFont('Courier','',10);
			$partners = explode(",", $thread['partners']);
			$list = [];
			foreach($partners as $partner) {
				$user = get_user($partner);
				$list[] = $user['username'];
			}
			$pdf->MultiCell(185,10,implode(" & ", $list),0,'C');
			$pdf->MultiCell(185,10,date("d.m.Y",$thread['ipdate']),0,'C');
			$pdf->MultiCell(185,10,$thread['iport'],0,'C');

		}
		
		// content pages
		$pdf->title = $thread['subject'];
		$pdf->AddPage();
		$pdf->AddFont('DejaVu','','DejaVuSansCondensed.ttf',true);
		$pdf->SetFont('DejaVu','',9);
		$pdf->SetX(30);
		
		if($thread) {	
			$pdf->title = $thread['subject'];		
			$sql = "SELECT message, username FROM ".TABLE_PREFIX."posts WHERE tid = '{$tid}' ORDER BY pid ASC";
			$query = $db->query($sql);
			while($post = $db->fetch_array($query)) {
				
				$pdf->author = $post['username'];
				
				// author
				$pdf->SetFont('Arial','B',14);
				$pdf->SetX(30);
				$pdf->Cell(40,10,$post['username']);
				
				$pdf->ln();
				
				// post
				$pdf->SetFont('DejaVu','',9);
				// Strip BBCode from Message
				$pattern = '|[[\/\!]*?[^\[\]]*?]|si';
				$replace = '';
				$post['message'] = preg_replace($pattern, $replace, $post['message']);
				$pdf->SetX(30);
				$pdf->MultiCell(150,5,strip_tags($post['message']));
				
				$pdf->ln();
				
			}
			
			$title = $thread['subject'];
			
		}
		// no tid? generate pdf from post...	
		else {
			
			$pid = (int)$mybb->input['pid'];
			
			$post = get_post($pid);
			$title = $post['subject'];
						
			// Strip BBCode from Message
			$pattern = '|[[\/\!]*?[^\[\]]*?]|si';
			$replace = '';
			$post['message'] = preg_replace($pattern, $replace, $post['message']);
			
			$pdf->MultiCell(150,5,strip_tags($post['subject'].' // '.$post['message']));
			
		}
		$pdf->Output('I', $title.'.pdf');
	}
}

?>