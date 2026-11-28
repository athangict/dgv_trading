<?php
namespace Tender\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Authentication\AuthenticationService;
use Interop\Container\ContainerInterface;
use Acl\Model As Acl;
use Stock\Model As Stock;
use Purchase\Model As Purchase;
use Accounts\Model As Accounts;
use Administration\Model As Administration;
use Tender\Model As Tender;

class TargetController extends AbstractActionController
{   
	private $_container;
	protected $_table; 		// database table 
    protected $_user; 		// user detail
    protected $_login_id; 	// logined user id
    protected $_login_role; // logined user role
    protected $_author; 	// logined user id
    protected $_created; 	// current date to be used as created dated
    protected $_modified; 	// current date to be used as modified date
    protected $_config; 	// configuration details
    protected $_dir; 		// default file directory
    protected $_id; 		// route parameter id, usally used by crude
    protected $_auth; 		// checking authentication
    protected $_safedataObj; //safedata controller plugin
    protected $_connection; //safedata controller plugin
    
	public function __construct(ContainerInterface $container)
    {
        $this->_container = $container;
    }
	/**
	 * Laminas Default TableGateway
	 * Table name as the parameter
	 * returns obj
	 */
	public function getDefaultTable($table)
	{
		$this->_table = new TableGateway($table, $this->_container->get('Laminas\Db\Adapter\Adapter'));
		return $this->_table;
	}

	/**
	 * User defined Model
	 * Table name as the parameter
	 * returns obj
	 */
	  public function getDefinedTable($table)
    {
        $definedTable = $this->_container->get($table);
        return $definedTable;
    }
    
    /**
     * initial set up
     * general variables are defined here
     */
	public function init()
	{
		$this->_auth = new AuthenticationService;
		if(!$this->_auth->hasIdentity()):
			$this->flashMessenger()->addMessage('error^ You dont have right to access this page!');
			$this->redirect()->toRoute('auth', array('action' => 'login'));
		endif;
		
		$this->_config = $this->_container->get('Config');
		
		$this->_user = $this->identity();		
		$this->_login_id = $this->_user->id;  
		$this->_login_role = $this->_user->role;  
		$this->_author = $this->_user->id;  
		
		$this->_id = $this->params()->fromRoute('id');
		
	    $this->_created = date('Y-m-d H:i:s');
		$this->_modified = date('Y-m-d H:i:s');
		//$this->_safedataObj = $this->SafeDataPlugin();
		//$this->_userloc = $this->_user->location;
		$this->_safedataObj = $this->safedata();
		$this->_connection = $this->_container->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();
	}
	public function indexAction()
	{
		$this->init();
		$year ='';
		if($this->getRequest()->isPost())
		{
			$form = $this->getRequest()->getPost();
			
			$year = $form['year'];
			$year = ($year == 0)? date('Y'):$year;
			$data = array(
					'year' => $year,
					
			);
		}else{
			$year = ($year == 0)? date('Y'):$year;
			$data = array(
					'year' => $year,
					
					
			);
		}
		$target_results = $this->getDefinedTable(Tender\TargetTable::class)->get(array('year'=>$year));
		return new ViewModel(array(
			'target_results' => $target_results,
			'statusObj'        => $this->getDefinedTable(Acl\StatusTable::class),
			'minYear' 		   => $this->getDefinedTable(Purchase\PurchaseOrderTable::class)->getMin('po_date'),
			'data'             => $data,
			'partyObj'         => $this->getDefinedTable(Accounts\PartyTable::class),
			'periodObj'        => $this->getDefinedTable(Tender\TargetPeriodTable::class),
			'typeObj'        => $this->getDefinedTable(Tender\TenderTypeTable::class),
		) );
	}
	/** 
	 * Add Purchase Order Action
	 */
	public function addtargetAction()
	{
		$this->init();
		
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();						
			$data = array(
					'year'          => $form['year'],
					'period'          => $form['period'],
					'sub_period'          => (empty($form['subperiod']))?-1:$form['subperiod'],
					'type'          => $form['type'],
					'amount'        => $form['amount'],
					'number'        => $form['number'],
					'note'              => $form['note'],
					'status' 			=> 2, 
					'author'           => $this->_author,
					'created'          => $this->_created,
					'modified'         => $this->_modified,
			);
			$data   = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Tender\TargetTable::class)->save($data);
			if($result > 0){ 
				$this->flashMessenger()->addMessage("success^ Successfully added new Target :");
				return $this->redirect()->toRoute('target', array('action' =>'viewtarget', 'id' => $result));
			}
			else{
				$this->flashMessenger()->addMessage("error^ Failed to add new Target");
				return $this->redirect()->toRoute('target', array('action' =>'addtarget'));
			}		
		}		
		return new ViewModel( array(
				//'user_location' => $this->_userloc,
				'suppliers'   	=> $this->getDefinedTable(Accounts\PartyTable::class)->get(array('p.role'=>'1')),
				'activities'  	=> $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
				'regions'     	=> $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'locationObj' 	=> $this->getDefinedTable(Administration\LocationTable::class),
				'itemcls'    	=> $this->getDefinedTable(Stock\ItemClassTable::class)->getAll(),
				'source_locs'	=> $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'location'),
				'tender_type'	=> $this->getDefinedTable(Tender\TenderTypeTable::class)->getAll(),
				'target_period'	=> $this->getDefinedTable(Tender\TargetPeriodTable::class)->getAll(),
				
		));		 
	}
	public function edittargetAction()
	{
		$this->init();
		
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();						
			$data = array(
					'id'					=> $this->_id,
					'year'          => $form['year'],
					'period'          => $form['period'],
					'sub_period'          => (empty($form['subperiod']))?-1:$form['subperiod'],
					'type'          => $form['type'],
					'amount'        => $form['amount'],
					'number'        => $form['number'],
					'note'              => $form['note'],
					'status' 			=> 2, 
					'author'           => $this->_author,
					'created'          => $this->_created,
					'modified'         => $this->_modified,
			);
			$data   = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Tender\TargetTable::class)->save($data);
			if($result > 0){ 
				$this->flashMessenger()->addMessage("success^ Successfully edited new Tender:". $Number);
				return $this->redirect()->toRoute('target', array('action' =>'viewtarget', 'id' => $result));
			}
			else{
				$this->flashMessenger()->addMessage("error^ Failed to add new Tender");
				return $this->redirect()->toRoute('target', array('action' =>'edittarget','id'=>$this->_id));
			}		
		}		
		return new ViewModel( array(
				//'user_location' => $this->_userloc,
				'suppliers'   	=> $this->getDefinedTable(Accounts\PartyTable::class)->get(array('p.role'=>'1')),
				'target'  	=> $this->getDefinedTable(Tender\TargetTable::class)->get($this->_id),
				'target_period'	=> $this->getDefinedTable(Tender\TargetPeriodTable::class)->getAll(),
				'target_type'	=> $this->getDefinedTable(Tender\TenderTypeTable::class)->getAll(),
				'regions'     	=> $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'locationObj' 	=> $this->getDefinedTable(Administration\LocationTable::class),
				'itemcls'    	=> $this->getDefinedTable(Stock\ItemClassTable::class)->getAll(),
				'source_locs'	=> $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'location'),
				
		));		
	}
	public function viewtargetAction()
	{
		$this->init();
		$target = $this->getDefinedTable(Tender\TargetTable::class)->get($this->_id);
		return new ViewModel(array(
			'target' => $target,
			'statusObj'        => $this->getDefinedTable(Acl\StatusTable::class),
			'periodObj'        => $this->getDefinedTable(Tender\TargetPeriodTable::class),
			'typeObj'        => $this->getDefinedTable(Tender\TenderTypeTable::class),
			
		) );
	}
		/**
	 * commit Action
	 * 
	 */
	public function commitAction(){
		$this->init();
		
		$data = array(
				'id'			=>$this->_id,
				'status' 		=> 4,
				'author'	    => $this->_author,
				'modified'      => $this->_modified,
		);
		$data   = $this->_safedataObj->rteSafe($data);
		$result = $this->getDefinedTable(Tender\TargetTable::class)->save($data);
		if($result):
			$this->flashMessenger()->addMessage("success^ Successfully commited ");
		else:
			$this->flashMessenger()->addMessage("error^ Failed to Commit");
		endif;
		return $this->redirect()->toRoute('target',array('action'=>'viewtarget','id'=>$this->_id));
		
	}
	/**
	 * getsubtype - Get sub type based on type
	 * **/
	public function getsubperiodAction()
	{
		$form = $this->getRequest()->getPost();
		$id =$form['id'];
		if($id==1){
			$list= $this->getDefinedTable(Tender\QuaterlyTable::class)->getAll();
		}
		elseif($id==2){
			$list= $this->getDefinedTable(Tender\HalfyearTable::class)->getAll();	
		
		}
		$sub = "<option value=''>Select</option>";
		foreach($list as $lists):
			if($id==3){
				$sub = "<option value='0'></option>";
			}
			
			else{
				$sub.="<option value='".$lists['id']."'>".$lists['name']."</option>";
			}
		endforeach;
		echo json_encode(array(
				'subperiod' => $sub,
		));
		exit;
	}
}


