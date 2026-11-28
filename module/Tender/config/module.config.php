<?php
namespace Tender;

use Laminas\Router\Http\Segment;

return array(
    'router' => array(
        'routes' => array(  
        		'index' => array(
        				'type'    => 'Segment',
        				'options' => array(
        						'route'       => '/tender[/:action[/:id]]',
        						'constraints' => array(
        								'action'  => '[a-zA-Z][a-zA-Z0-9_-]*',
        								'id'      => '[a-zA-Z0-9_-]*',
        						),
        		
        						'defaults' => array(
        								'controller' => Controller\IndexController::class,
        								'action'     => 'index',
        						),
        				),
        		),
        		
        		'target' => array(
        				'type'    => 'Segment',
        				'options' => array(
        						'route'       => '/target[/:action[/:id]]',
        						'constraints' => array(
        								'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
        								'id'     	 => '[a-zA-Z0-9_-]*',
        						),
        						'defaults' => array(
        								'controller' => Controller\TargetController::class,
        								'action'     => 'index',
        						),
        				),
        		),
        		
        		'tender' => array(
        				'type'    => 'Segment',
        				'options' => array(
        						'route'       => '/tender[/:action[/:id]]',
        						'constraints' => array(
        								'action'  => '[a-zA-Z][a-zA-Z0-9_-]*',
        								'id'      => '[a-zA-Z0-9_-]*',
        						),
        		
        						'defaults' => array(
        								'controller' => Controller\TenderController::class,
        								'action'     => 'index',
        						),
        				),
        		),		
	            'treport' => array(
	            		'type'    => 'Segment',
	            		'options' => array(
	            				'route'       => '/treport[/:action[/:id]]',
	            				'constraints' => array(
	            						'action'   => '[a-zA-Z][a-zA-Z0-9_-]*',
	            						'id'       => '[a-zA-Z0-9_-]*',
	            				),
	            				
	            				'defaults' => array(
	            						'controller' => Controller\ReportController::class,
	            						'action'     => 'index',
	            				),
	            		),
	            ),     					
		),
	),	
	'view_manager' => array(
        'template_path_stack' => array(
            'purchase'=>__DIR__ . '/../view/',
        ),
		'display_exceptions' => true,
    ),
);
