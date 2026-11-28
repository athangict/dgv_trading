<?php
namespace Application\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\View\Renderer\RendererInterface;


class PrintController extends AbstractActionController
{

    /**
     * @var \TCPDF
     */
    protected $tcpdf;

    /**
     * @var RendererInterface
     */
    protected $renderer;

    public function __construct($tcpdf, $renderer)
    {
        $this->tcpdf = $tcpdf;
        $this->renderer = $renderer;
    }

    public function indexAction()
   {
		if($this->getRequest()->isPost()):
			$data = $this->getRequest()->getPost();
		endif;
        //echo '<pre>'; print_r($data['dom']); exit;
        $html= '<style>
		.widget-header{text-align:center}
        ul.list-unstyled{list-style-type:none;}
        li.center{text-align:center;}
        table{width:100%; word-wrap: break-word;}
        td, th{border:1px solid #ddd; text-align: left;
            white-space: normal;
            border-collapse: collapse;} 
        h5{text-align:center;font-size:14px!important;margin:10px;}
        .table-primary{background-color:#d2e1f3;color:#1e293b;border-color:#c0cfe1}
        .table-success{background-color:#d5f0da;color:#1e293b;border-color:#c3dcca}
        .table-info{background-color:#d9ebf9;color:#1e293b;border-color:#c6d8e6}
        .table-warning{background-color:#fde1cd;color:#1e293b;border-color:#e7cfbe}
        .table-danger{background-color:#f7d7d7;color:#1e293b;border-color:#e1c6c7}
        .text-start{text-align:left!important}
        .text-end{text-align:right!important}
        .text-center{text-align:center!important}
        .fs-1{font-size:24px!important}
        .fs-2{font-size:20px!important}
        .fs-3{font-size:16px!important}
        .fs-4{font-size:14px!important}
        .fs-5{font-size:12px!important}
        .fs-6{font-size:10px!important}
        .fw-bold{font-weight:700}
        .text-wrap{white-space:normal!important}
        input{white-space:normal!important}
    </style>' . $data['dom'];
		 // Initialize TCPDF
		$pdf = $this->tcpdf;
		$pdf->SetTitle($data['title']);
		$pdf->SetFont('times', '', 11, '', false);
		
		$pdf->SetMargins(20, 10, 20); // Increase the values as needed
		$pdf->SetHeaderMargin(5);
		$pdf->SetFooterMargin(5);
        

		// Language settings
		$lg = [];
		$lg['a_meta_charset'] = 'UTF-8';
		$pdf->setLanguageArray($lg);

		// Add a page with the specified orientation and size
		$pdf->AddPage($data['orentation'], $data['size']);

		// Determine the absolute path to the public folder
		$publicPath = getcwd() . '/public/';

		// Set the header image path
        //if($data['orentation']=="P"){$headerImagePath = $publicPath . 'images/trading_print_header.jpg';}
        if($data['orentation']=="P"){$headerImagePath = $publicPath . 'images/trading_log.jpg';}
        else{$headerImagePath = $publicPath . 'images/header3.jpg';}
		
        
		// Check if the image file exists
		if (file_exists($headerImagePath)) {
            if($data['orentation']=="P"){$pdf->Image($headerImagePath, 10, 10, 190, 30, '', '', '', false, 300, '', false, false, 0, false, false, false);}
        else{$pdf->Image($headerImagePath, 10, 10, 270, 30, '', '', '', false, 300, '', false, false, 0, false, false, false);}
			// Add some space after the header image
			$pdf->Ln(30);
			 // Draw a line under the header image
			 
			//$pdf->Line(15, 45, 200, 45); 
		} else {
			// Handle the error if the image path is not found
			throw new Exception('Header image not found: ' . $headerImagePath);
		}

		// Write HTML content to the PDF
		$pdf->writeHTML($html, true, false, true, false, '');

		// Output the PDF
		$pdf->Output();
    }

    public function indexsAction()
    {
         if($this->getRequest()->isPost()):
             $data = $this->getRequest()->getPost();
         endif;
         //echo '<pre>'; print_r($data['dom']); exit;
         $html= '<style>
         .widget-header{text-align:center}
         ul.list-unstyled{list-style-type:none;}
         li.center{text-align:center;}
         table{width:100%; word-wrap: break-word;}
         td, th{border:1px solid #ddd; text-align: left;
             white-space: normal;
             border-collapse: collapse;} 
         h5{text-align:center;font-size:14px!important;margin:10px;}
         .table-primary{background-color:#d2e1f3;color:#1e293b;border-color:#c0cfe1}
         .table-success{background-color:#d5f0da;color:#1e293b;border-color:#c3dcca}
         .table-info{background-color:#d9ebf9;color:#1e293b;border-color:#c6d8e6}
         .table-warning{background-color:#fde1cd;color:#1e293b;border-color:#e7cfbe}
         .table-danger{background-color:#f7d7d7;color:#1e293b;border-color:#e1c6c7}
         .text-start{text-align:left!important}
         .text-end{text-align:right!important}
         .text-center{text-align:center!important}
         .fs-1{font-size:24px!important}
         .fs-2{font-size:20px!important}
         .fs-3{font-size:16px!important}
         .fs-4{font-size:14px!important}
         .fs-5{font-size:12px!important}
         .fs-6{font-size:10px!important}
         .fw-bold{font-weight:700}
         .text-wrap{white-space:normal!important}
         input{white-space:normal!important}
     </style>' . $data['dom'];
          // Initialize TCPDF
         $pdf = $this->tcpdf;
         $pdf->SetTitle($data['title']);
         $pdf->SetFont('times', '', 11, '', false);
         
         $pdf->SetMargins(20, 10, 2); // Increase the values as needed
         $pdf->SetHeaderMargin(5);
         $pdf->SetFooterMargin(5);
         
 
         // Language settings
         $lg = [];
         $lg['a_meta_charset'] = 'UTF-8';
         $pdf->setLanguageArray($lg);
 
         // Add a page with the specified orientation and size
         $pdf->AddPage($data['orentation'], $data['size']);
 
         // Determine the absolute path to the public folder
         $publicPath = getcwd() . '/public/';
 
         // Set the header image path
         //if($data['orentation']=="P"){$headerImagePath = $publicPath . 'images/trading_print_header.jpg';}
         if($data['orentation']=="P"){$headerImagePath = $publicPath . 'images/newlogo.jpg';}
         else{$headerImagePath = $publicPath . 'images/header3.jpg';}
         
         
         // Check if the image file exists
         if (file_exists($headerImagePath)) {
             if($data['orentation']=="P"){$pdf->Image($headerImagePath, 10, 10, 190, 30, '', '', '', false, 300, '', false, false, 0, false, false, false);}
         else{$pdf->Image($headerImagePath, 10, 10, 270, 30, '', '', '', false, 300, '', false, false, 0, false, false, false);}
             // Add some space after the header image
             $pdf->Ln(30);
              // Draw a line under the header image
              
             //$pdf->Line(15, 45, 200, 45); 
         } else {
             // Handle the error if the image path is not found
             throw new Exception('Header image not found: ' . $headerImagePath);
         }
 
         // Write HTML content to the PDF
         $pdf->writeHTML($html, true, false, true, false, '');
 
         // Output the PDF
         $pdf->Output();
     }
}
