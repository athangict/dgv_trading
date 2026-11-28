<?php
namespace Tender\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Authentication\AuthenticationService;
use Interop\Container\ContainerInterface;
use Accounts\Model As Accounts;
use Acl\Model As Acl;
use Administration\Model As Administration;
use Purchase\Model As Purchase;
use Stock\Model As Stock;
use Tender\Model As Tender;

class ReportController extends AbstractActionController
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
    protected $_userloc; //safedata controller plugin
    
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
		$this->_userloc = $this->_user->location;
		$this->_safedataObj = $this->safedata();
		$this->_connection = $this->_container->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();
	}
	public function indexAction()
{
    $this->init();

    
    $start_date = date('Y-m-d');
    $end_date = date('Y-m-d');
    $status = '-1'; 
    $agency = '-1'; 

    if ($this->getRequest()->isPost()) {
        $form = $this->getRequest()->getPost();
        $start_date = $form['start_date'] ?? $start_date;
        $end_date = $form['end_date'] ?? $end_date;
        $status = $form['status'] ?? $status;
        $agency = $form['agency'] ?? $agency;
    }

    $data = [
        'start_date' => $start_date,
        'end_date' => $end_date,
        'agency' => $agency,
        'status' => $status,
    ];
    $results = $this->getDefinedTable(Tender\TenderTable::class)->getTRDetails($data, $start_date, $end_date);

   

    return new ViewModel([
        'title' => 'Tender Report from ' . $start_date . ' to ' . $end_date,
        'results' => $results,
        'data' => $data,
        'agency' => $this->getDefinedTable(Accounts\PartyTable::class)->get(['p.role' => 2]),
        'agencyObj' => $this->getDefinedTable(Accounts\PartyTable::class),
        'purchaseObj' => $this->getDefinedTable(Purchase\PurchaseOrderTable::class),
        'status'     => $this->getDefinedTable(Acl\StatusTable::class)->get(array('id'=>[18,21,22])),
         
    ]);
    
}
}