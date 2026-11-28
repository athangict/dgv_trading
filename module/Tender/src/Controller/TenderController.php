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
class TenderController extends AbstractActionController
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
		if($this->getRequest()->isPost())
		{
			$form = $this->getRequest()->getPost();
			
			$year = $form['year'];
			$month = $form['month'];
			
		}else{
			$month = date('m');
			$year = date('Y');
			
		}
		
			$data = array(
					'year' => $year,
					'month' => $month,
					
			);
		$tender_results = $this->getDefinedTable(Tender\TenderTable::class)->getDateWise('date',$year,$month);
		//$numberword = self::convertNumberToWords(10200);
		//print_r($numberword);exit;
		return new ViewModel(array(
			'tender_results' => $tender_results,
			'tender_results'  => $this->getDefinedTable(Tender\TenderTable::class)->getAll(),
			'statusObj'        => $this->getDefinedTable(Acl\StatusTable::class),
			'tenderObj' => $this->getDefinedTable(Tender\TenderTable::class),
			'minYear' 		   => $this->getDefinedTable(Tender\TenderTable::class)->getMin('date'),
			'data'             => $data,
			'partyObj'         => $this->getDefinedTable(Accounts\PartyTable::class),
			'userObj'         => $this->getDefinedTable(Administration\UsersTable::class),
		) );
	}
	/** 
	 * Add Purchase Order Action
	 */
	public function addtenderAction()
	{
		$this->init();
		
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();						
			$getNo = $this->getDefinedTable(Tender\TenderTable::class)->getLastNo();
			if($getNo > 0){
				$Number = $getNo+1;
			}
			else{
				$Number =1;
			}
			$data = array(
					'tender_no'            => $Number, 
					'location'         => 1,
					'agency'         => $form['agency'],
					'date'          => $form['tender_date'],
					'amount'        => $form['amount'],
					'bid_date'          => $form['bid_date'],
					'bid_security'     		=> (!empty($form['bid']))?$form['bid']:0.00,
					'bid_exp_date'     		=> $form['bid_exp_date'],
					'note'              => $form['note'],
					'status' 			=> 2, 
					'author'           => $this->_author,
					'created'          => $this->_created,
					'modified'         => $this->_modified,
			);
			$data   = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Tender\TenderTable::class)->save($data);
			if($result > 0){ 
				$this->flashMessenger()->addMessage("success^ Successfully added new Tender No :". $Number);
				return $this->redirect()->toRoute('tender', array('action' =>'index'));
			}
			else{
				$this->flashMessenger()->addMessage("error^ Failed to add new Tender");
				return $this->redirect()->toRoute('tender', array('action' =>'addtender'));
			}		
		}		
		return new ViewModel( array(
				//'user_location' => $this->_userloc,
				'suppliers'   	=> $this->getDefinedTable(Accounts\PartyTable::class)->get(array('p.role'=>'2')),
				'activities'  	=> $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
				'regions'     	=> $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'locationObj' 	=> $this->getDefinedTable(Administration\LocationTable::class),
				'itemcls'    	=> $this->getDefinedTable(Stock\ItemClassTable::class)->getAll(),
				'source_locs'	=> $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'location'),
				
		));		
	}
	public function edittenderAction()
	{
		$this->init();
		
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();						
			$data = array(
					'id'					=> $this->_id,
					'location'         		=> 1,
					'agency'         		=> $form['agency'],
					'date'          		=> $form['tender_date'],
					'amount'        		=> $form['amount'],
					'bid_date'        		=> $form['bid_date'],
					'bid_security'     		=> $form['bid'],
					'bid_exp_date'     		=> $form['bid_exp_date'],
					'note'              	=> $form['note'],
					'status' 				=> 2, 
					'author'           		=> $this->_author,
					'created'          		=> $this->_created,
					'modified'         		=> $this->_modified,
			);
			$data   = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Tender\TenderTable::class)->save($data);
			if($result > 0){ 
				$this->flashMessenger()->addMessage("success^ Successfully Edited new Tender:");
				return $this->redirect()->toRoute('tender', array('action' =>'index', 'id' => $result));
			}
			else{
				$this->flashMessenger()->addMessage("error^ Failed to add new Tender");
				return $this->redirect()->toRoute('tender', array('action' =>'edittender','id'=>$this->_id));
			}		
		}		
		return new ViewModel( array(
				//'user_location' => $this->_userloc,
				'suppliers'   	=> $this->getDefinedTable(Accounts\PartyTable::class)->get(array('p.role'=>'2')),
				'tender'  	=> $this->getDefinedTable(Tender\TenderTable::class)->get($this->_id),
				'regions'     	=> $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'locationObj' 	=> $this->getDefinedTable(Administration\LocationTable::class),
				'itemcls'    	=> $this->getDefinedTable(Stock\ItemClassTable::class)->getAll(),
				'source_locs'	=> $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'location'),
				
		));		
	}
		/**
	 *  Edit Block action
	 */
	public function updatetenderAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(	
				'id'           				=> $this->_id,
				'status'       				=> $form['status'],
				// 'tender_award_amount'       => $form['tender_award_amount'],
				 'tender_award_amount'    => ($form['tender_award_amount'] === '' ? 0 : $form['tender_award_amount']),
				'psecurity_date'       				=> $form['psecurity_date'],
				'psecurity_exp_date'       				=> $form['psecurity_exp_date'],
				'performance_security'		=> (!empty($form['performance_security']))?$form['performance_security']:0.00,
				'author'       				=> $this->_author,
				'modified'     				=> $this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Tender\TenderTable::class)->save($data);	
			if($result):
				$this->flashMessenger()->addMessage("success^ Successfully Updated Tender.");
			else:
				$this->flashMessenger()->addMessage("error^ Failed to Updated Tender.");
			endif;
			return $this->redirect()->toRoute('tender', array('action' =>'index'));
		}
		$ViewModel = new ViewModel(array(
				'title'      => 'Update Tender',
				'id'  => $this->_id,	
				'status'     => $this->getDefinedTable(Acl\StatusTable::class)->get(array('id'=>[18,21,22])),
		));		 
		$ViewModel->setTerminal(True);
		return $ViewModel;	
	}
	public function deletetenderAction()
     {
    $this->init();
    $id = $this->_id;
    $array_id = explode("_", $id);
    $id = $array_id[0];

    if ($this->getRequest()->isPost()) {
        $form = $this->getRequest()->getPost();

        // Check the value of the "confirm" field
        if ($form['confirm'] === 'Yes') {
            // Start the deletion process
            $this->_connection->beginTransaction();
            try {
                $this->getDefinedTable(Tender\TenderTable::class)->remove($id);
                $this->_connection->commit();
                $this->flashMessenger()->addMessage("success^ Successfully deleted Tender.");
            } catch (\Exception $e) {
                $this->_connection->rollback();
                $this->flashMessenger()->addMessage("error^ Failed to remove Tender: " . $e->getMessage());
            }
        } else {
            // If "No" is selected, do not delete and redirect with a message
            $this->flashMessenger()->addMessage("info^ Deletion cancelled.");
        }

        return $this->redirect()->toRoute('tender', ['action' => 'index']);
    }

    // Load the view with the tender details
    $ViewModel = new ViewModel([
        'title' => 'Remove Tender',
        'Tender' => $this->getDefinedTable(Tender\TenderTable::class)->get($id),
    ]);
    $ViewModel->setTerminal(true);
    return $ViewModel;
    }
}


