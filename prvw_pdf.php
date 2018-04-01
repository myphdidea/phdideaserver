<?php
session_start();
$xmin=$xmax=$ymin=$ymax="";
if(!empty($_GET['id'])) $id=test($_GET['id']);
if(!empty($_GET['xmin'])) $xmin=test($_GET['xmin']);
if(!empty($_GET['xmax'])) $xmax=test($_GET['xmax']);
if(!empty($_GET['ymin'])) $ymin=test($_GET['ymin']);
if(!empty($_GET['ymax'])) $ymax=test($_GET['ymax']);
$filename='user_data/tmp/'.$_SESSION['user'].'_'.$id.'.pdf';
//header("Content-Disposition:attachment;filename='downloaded.pdf'");
//readfile($filename);

//pdf_preview($filename,$xmin,$xmax,$ymin,$ymax);
if(empty($_GET['blanknames']))
{
	header("Content-type: application/pdf");
	header("filename='downloaded.pdf'");
	readfile($filename);
}
elseif(!empty($xmin) && !empty($xmax) && !empty($ymin) && !empty($ymax))
{
	pdf_preview($filename,$xmin/100.0,$xmax/100.0,$ymin/100.0,$ymax/100.0);
	header("Content-type: application/pdf");
	header("filename='downloaded.pdf'");
	readfile($filename.".rdc");
}
else echo "Please enter box coordinates or uncheck 'anonymize' (update link with preview button)!";
//header("Content-type: application/pdf");
//header("filename='downloaded.pdf'");
//readfile($filename.".rdc");

function pdf_redact($target, $x_min, $x_max, $y_min, $y_max, $pagecount)
{
/*	putenv('PATH=/usr/bin');
 	exec("gs -o ".$target.".misc"." -dNoOutputFonts -sDEVICE=pdfwrite ".$target);*/

//	putenv('PATH=C:/Program Files (x86)/PDFtk Server/bin/');
	putenv('PATH=/usr/bin');
	exec('pdftk '.$target.'.rdc'.' cat 1 output '.$target.'.uncm uncompress');
	remove_characters($target.".uncm", $x_min, $x_max, $y_min, $y_max);
	if($pagecount > 1)
		exec('pdftk A='.$target.'.uncm B='.$target.'.misc'.' cat A1 B2-end output '.$target.'.rdc compress');
	else exec('pdftk '.$target.'.uncm output '.$target.'.rdc compress');
//	unlink($target.".misc");
	unlink($target.".uncm");
}

function pdf_preview($source, $xmin, $xmax, $ymin, $ymax)
{
	require_once('includes/tcpdf/config/tcpdf_config.php');
	require_once('includes/tcpdf/tcpdf.php');
	require_once('includes/tcpdf/tcpdi.php');
	
	$pdf = new TCPDI(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
	$pdf->setPrintHeader(false);

	$pdfdata = file_get_contents($source.".misc"); 
	$pagecount = $pdf->setSourceData($pdfdata);

	for ($i = 1; $i <= 1/*$pagecount*/; $i++)
	{
    	$tplidx = $pdf->importPage($i);
    	$pdf->AddPage();

    	$pdf->useTemplate($tplidx, null, null, 0, 0, true);

		if($i==1)
		{
			$dim=$pdf->getPageDimensions();
			$llx=$dim['CropBox']['llx'];
			$lly=$dim['CropBox']['lly'];
			$urx=$dim['CropBox']['urx'];
			$ury=$dim['CropBox']['ury'];
			
			$style_border = array('width' => 0.25, 'dash' => 0, 'color' => array(255, 255, 0));
			$pdf->Rect($xmin*($urx-$llx)*0.35, (1.0-$ymax)*($ury-$lly)*0.35, ($xmax-$xmin)*($urx-$llx)*0.35, ($ymax-$ymin)*($ury-$lly)*0.35, 'C', array('all' => $style_border), array(255, 255, 0));
		}
	}

//	$pdf->Output('download.pdf','I');
	$pdf->Output($_SERVER['DOCUMENT_ROOT'].$source.".rdc",'F');
	pdf_redact($source, ($xmin*($urx-$llx)+$llx)*10, ($xmax*($urx-$llx)+$llx)*10, (-(1.0-$ymin)*($ury-$lly)+$ury)*10, (-(1.0-$ymax)*($ury-$lly)+$ury)*10,$pagecount);
}

function remove_characters($filename,$x_min,$x_max,$y_min,$y_max)
{
	$string=file_get_contents($filename);
	preg_match_all('/(?<=\n)[0-9 .]*(?= m\n)/', $string, $matches);

	$matches=reset($matches);
	foreach ($matches as $key => $match)
	{
		list($part1,$part2)=explode(" ",$match);
    	if($x_min < $part1 && $part1 < $x_max && $y_min < $part2 && $part2 < $y_max)
    	{
			$begin_pos=strpos($string,$match." m\n");
			$end_pos=strpos($string,"\nf\n",$begin_pos);
			if(!empty($begin_pos) && !empty($end_pos))
			{
				$text_to_delete=substr($string,$begin_pos,$end_pos+3-$begin_pos);
				$string=str_replace($text_to_delete,'',$string);
			}
    	}
	}
	file_put_contents($filename,$string);
}

function test($data) {
  $data = trim($data);
  $data = stripslashes($data);
  $data = htmlspecialchars($data);
  return $data;
}

function curl_get_contents($url)
{
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_URL, $url);

    $data = curl_exec($ch);
    curl_close($ch);

    return $data;
}



?>