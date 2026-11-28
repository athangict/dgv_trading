<?php
namespace Acl\Controller\Plugin;

use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Laminas\View\Model\ViewModel;
use Laminas\Db\Adapter\Adapter;    
use Laminas\Authentication\AuthenticationService;
use Laminas\Mail;
use Laminas\Mime;
use Laminas\Mail\Transport\Smtp as SmtpTransport;
use Laminas\Mail\Transport\SmtpOptions;

class EmailPlugin extends AbstractPlugin{

	public function sendmail($mail)
	{	
		$this->sendEmail($mail); 
	}
	
	private function sendEmail($mail)
	{
		$view       = new \Laminas\View\Renderer\PhpRenderer();
		$resolver   = new \Laminas\View\Resolver\TemplateMapResolver();
		
		$resolver->setMap(array(
			'mailTemplate' => __DIR__ . '/../../../view/acl/index/mailtemplate.phtml'
		));

		$view->setResolver($resolver);
	 
		$viewModel  = new ViewModel();
		$viewModel->setTemplate('mailTemplate')->setVariables(array(
			'mail'          => $mail,
		));
		
		$bodyPart = new Mime\Message();
		$bodyMessage    = new Mime\Part($view->render($viewModel));
		$bodyMessage->type = 'text/html';
		$bodyPart->setParts(array($bodyMessage));
	 
		$message = new Mail\Message();
		$message->addTo($mail['email'],$mail['name'])
				->setSubject($mail['subject'])
				->setBody($bodyPart)
				->setFrom('singye@athang.com', 'DGV-ERP')
				->setEncoding('UTF-8');
		if(sizeof($mail['cc_array'])>0){
			foreach($mail['cc_array'] as $cc_recipient):
				$message->addCc($cc_recipient['email'],$cc_recipient['name']);
			endforeach;
		}

		// Setup SMTP transport using LOGIN authentication
		$transport = new SmtpTransport();
		$options   = new SmtpOptions([
			'host' => 'smtp.sendgrid.net',
            'port' => 587,
			'connection_class'  => 'login',
			'connection_config' => [
				'username' => 'apikey',
				'password' => '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',
				'ssl'       => 'tls'
			], 
		]); 			
		$transport->setOptions($options);
// 		var_dump([
//     'host' => $options->getHost(),
//     'port' => $options->getPort(),
//     'connection_class' => $options->getConnectionClass()
// ]);
// exit;
			
		$transport->send($message);	
		
	}
}