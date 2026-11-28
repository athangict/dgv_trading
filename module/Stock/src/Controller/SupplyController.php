<?php
namespace Stock\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Authentication\AuthenticationService;
use DOMPDFModule\View\Model\PdfModel;
use Interop\Container\ContainerInterface;
use Acl\Model As Acl;
use Accounts\Model As Accounts; 
use Purchase\Model As Purchase;
use Stock\Model As Stock;
use Tender\Model As Tender;
use Administration\Model As Administration;

class SupplyController extends AbstractActionController
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
    protected $_userloc; 		// checking authentication
    
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
		$year ='';
		$month = '';
		if($this->getRequest()->isPost())
		{
			$form = $this->getRequest()->getPost();
			
			$year = $form['year'];
			$month = $form['month'];
			
			
			$month = ($month == 0)? date('m'):$month;
			$year = ($year == 0)? date('Y'):$year;
			$data = array(
					'year' => $year,
					'month' => $month,
					
			);
		}else{
			$month = ($month == 0)? date('m'):$month;
			$year = ($year == 0)? date('Y'):$year;
			
			
			$data = array(
					'year' => $year,
					'month' => $month,
					
			);
		}
		$supply = $this->getDefinedTable(Stock\SupplyTable::class)->getDateWise('supply_date',$year,$month);
		return new ViewModel(array(
			'supply' => $supply,
			'supply'           => $this->getDefinedTable(Stock\SupplyTable::class)->getAll(),
			'statusObj'        => $this->getDefinedTable(Acl\StatusTable::class),
			'minYear' 		   => $this->getDefinedTable(Stock\SupplyTable::class)->getMin('supply_date'),
			'data'             => $data,
			'partyObj'         => $this->getDefinedTable(Accounts\PartyTable::class),
			'tenderObj'         => $this->getDefinedTable(Tender\TenderTable::class),
		
		) );
	}
	
	
	/** 
	 * Add Purchase Order Action
	 */
	public function addsupplyAction()
	{
		$this->init();
		
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();						
			$location_prefix = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($form['destination'],'prefix');
			
			$data = array(
					'location'         	=> 1,
					'agency'         	=> $form['supplier'],
					'supply_date'          	=> $form['supply_date'],
					'tender'     		=> $form['tender'],
					// addsupplydtl
					'status' 			=> 2, 
					'author'           	=> $this->_author,
					'created'          	=> $this->_created,
					'modified'         	=> $this->_modified,
			);
			$data   = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Stock\SupplyTable::class)->save($data);
			//addressint_r($data);exit;
			if($result > 0){ 
				
				$this->flashMessenger()->addMessage("success^ Successfully added new Supply :". $supply_no);
				return $this->redirect()->toRoute('supply', array('action' =>'viewsupply', 'id' => $result));
			}
			else{
				$this->flashMessenger()->addMessage("error^ Failed to add new Supply");
				return $this->redirect()->toRoute('addsupply');
			}		
		}		
		return new ViewModel( array(
				'user_location' => $this->_userloc,
				'suppliers'   	=> $this->getDefinedTable(Accounts\PartyTable::class)->get(array('p.role'=>'2')),
				'partyObj'   	=> $this->getDefinedTable(Accounts\PartyTable::class),
				'activities'  	=> $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
				'regions'     	=> $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'locationObj'  	=> $this->getDefinedTable(Administration\LocationTable::class),
				'itemcls'    	=> $this->getDefinedTable(Stock\ItemClassTable::class)->getAll(),
			    'tender'        => $this->getDefinedTable(Tender\TenderTable::class)->getTenderS(),
				'source_locs'	=> $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'location'),	
		));		
	}
	
	 
	/**
	 * Edit Purchase Order Action
	 * Ammend Purchase Order Action
	 */
	public function editsupplyAction()
	{
		$this->init();
		
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();
			$data = array(
					'id'               => $this->_id,
					'location'         => 1,
					'agency'         => $form['agency'],
					'supply_date'       => $form['supply_date'], 
					'location'        => $form['destination'],
					'tender'     		=> $form['tender'],
					'note'              => $form['note'],
					'status' 			=> 2, 
					'author'           => $this->_author,
					'created'          => $this->_created,
					'modified'         => $this->_modified,
			);
			$data   = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Stock\SupplyTable::class)->save($data);
			if($result > 0){
				$this->flashMessenger()->addMessage("success^ Successfully updated Supply");
			}
			else{
				$this->flashMessenger()->addMessage("error^ Failed to update Supply");
			}
			return $this->redirect()->toRoute('supply', array('action' =>'viewsupply', 'id' => $this->_id));
		}
		
		$supply = $this->getDefinedTable(Stock\SupplyTable::class)->get($this->_id);
		
		foreach ($supply as $sup):
		     if( $sup['status'] == "3" || $sup['status'] == "4"){
                     $this->flashMessenger()->addMessage("notice^ Cannot edit a canceled/closed Purchase Order");
				     return $this->redirect()->toRoute('purorder');		     	  
		     }
		endforeach;
		
		return new ViewModel( array(
				'supply' => $supply,
		        'supply_details' => $this->getDefinedTable(Stock\SupplyDetailTable::class)->get(array('supply' => $this->_id)),
				'agency'   => $this->getDefinedTable(Accounts\PartyTable::class)->get(array('p.role'=>'2')),
				'tender'     => $this->getDefinedTable(Tender\TenderTable::class)->getAll(),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'partyObj'     => $this->getDefinedTable(Accounts\PartyTable::class),
				'activities'  => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
				'statusObj'      => $this->getDefinedTable(Acl\StatusTable::class),
				'uom'      => $this->getDefinedTable(Stock\UomTable::class)->getAll(),
				'item' => $this->getDefinedTable(Stock\ItemTable::class)->getAll(),
				'itemObj'		=>$this->getDefinedTable(Stock\ItemTable::class),
				'itemClassObj'		=>$this->getDefinedTable(Stock\ItemClassTable::class),
				'uomObj'=> $this->getDefinedTable(Stock\UomTable::class),
				'itemcls'    	=> $this->getDefinedTable(Stock\ItemClassTable::class)->getAll(),
				'source_locs'	=> $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'location'),
		) );	
	}

	public function editsupplydtlAction()
	{
		$this->init();
		
		if($this->getRequest()->isPost()){
			//print_r($this->_id);exit;
			
			$form = $this->getRequest()->getpost();
			$data = array(
					'id'               => $this->_id,
					'address'         => $form['address'],
					'date'       => $form['supply_date'], 
			);
			//$supplydata   = $this->_safedataObj->rteSafe($supplydata);
			$result = $this->getDefinedTable(Stock\SupplyAddressTable::class)->save($data);
			//print_r($result);exit;
			foreach($this->getDefinedTable(Stock\SupplyAddressTable::class)->get($result) as $sup);
			
				$id			  =	$form['id'];
				$item_class         = $form['item_class'];
				$item         = $form['item'];
				$tender_slNo        = $form['tender_slNo'];
				$batch_no         = $form['batch_no'];
				$uom          = $form['uom'];
				$quantity     = $form['quantity'];
				$rate         = $form['rate'];
				$gst_rate     = $form['gst_rate']; 
				$gst_amt      = $form['gst_amt'];
				$amount         = $form['amount'];
				$exp_date         = $form['exp_date'];
				$mftg_date         = $form['mftg_date'];
				//$total         = $form['total'];
				$remarks      = $form['remarks'];
				if(sizeof($id)>0){
				for($i=0; $i < sizeof($id); $i++):
					if(isset($item[$i]) && $item[$i] > 0):
					
						$supply_dtls = array(
								'id'			=> $id[$i],
									'supply'		 	=> $form['supply'],
									//'supply_address'	=> $this->_id,
									'tender_slNo'	=> $tender_slNo[$i],
									'item' 				=> $item[$i],
									//'code_no' 			=> $code_no[$i],
									'batch_no' 			=> $batch_no[$i],
									'item_class' 		=> $item_class[$i],
									'rate' 				=> $rate[$i],
									'gst_rate'      => $gst_rate[$i], 
									'gst_amt'           => $gst_amt[$i],
									'expiry_date' 		=> $exp_date[$i],
									'manufactured_date' => $mftg_date[$i],
									'quantity' 			=> $quantity[$i],
									'amount' 			=> $amount[$i],
									//'total' 			=> $total,
									'uom'				=>1,
									'remarks' 			=> $remarks[$i],
						);
		     		$supply_dtls   = $this->_safedataObj->rteSafe($supply_dtls);
			     	$this->getDefinedTable(Stock\SupplyDetailTable::class)->save($supply_dtls);		
				   	endif; 		     
				endfor;
				if(sizeof($id)!=sizeof($item)){
				for($i=sizeof($id); $i < sizeof($item); $i++):
					if(isset($item[$i]) && $item[$i] > 0):
					
						$supply_dtls = array(
		      					'supply'		 	=> $form['supply'],
									'supply_address'	=> $this->_id,
									'tender_slNo'	=> $tender_slNo[$i],
									'item' 				=> $item[$i],
									//'code_no' 			=> $code_no[$i],
									'batch_no' 			=> $batch_no[$i],
									'item_class' 		=> $item_class[$i],
									'rate' 				=> $rate[$i],
									'expiry_date' 		=> $exp_date[$i],
									'manufactured_date' => $mftg_date[$i],
									'quantity' 			=> $quantity[$i],
									'amount' 			=> $amount[$i],
									'gst_rate'          => $gst_rate[$i],  
                                    'gst_amt'           => $gst_amt[$i], 
									//'total' 			=> $total,
									'uom'				=>1,
									'remarks' 			=> $remarks[$i],
						);
		     		$supply_dtls   = $this->_safedataObj->rteSafe($supply_dtls);
			     	$this->getDefinedTable(Stock\SupplyDetailTable::class)->save($supply_dtls);		
				   	endif; 		     
				endfor;
				}
				}
				else{
					for($i=0; $i < sizeof($item); $i++):
					if(isset($item[$i]) && $item[$i] > 0):
					
						$supply_dtls = array(
		      					'supply'		 	=> $form['supply'],
									'supply_address'	=> $result,
									'tender_slNo'	=> $tender_slNo[$i],
									'item' 				=> $item[$i],
									//'code_no' 			=> $code_no[$i],
									'batch_no' 			=> $batch_no[$i],
									'item_class' 		=> $item_class[$i],
									'rate' 				=> $rate[$i],
									'gst_rate'          => $gst_rate[$i], 
									'gst_amt'           => $gst_amt[$i],
									'expiry_date' 		=> $exp_date[$i],
									'manufactured_date' => $mftg_date[$i],
									'quantity' 			=> $quantity[$i],
									'amount' 			=> $amount[$i],
									//'total' 			=> $total,
									'uom'				=>1,
									'remarks' 			=> $remarks[$i],
						);
		     		$supply_dtls   = $this->_safedataObj->rteSafe($supply_dtls);
			     	$this->getDefinedTable(Stock\SupplyDetailTable::class)->save($supply_dtls);		
				   	endif; 		     
				endfor;
				}
			//$data   = $this->_safedataObj->rteSafe($data);
			//$result1 = $this->getDefinedTable(Purchase\PurchaseOrderTable::class)->save($data);
			if($result > 0){
				$this->flashMessenger()->addMessage("success^ Successfully updated Supply Details");
			}
			else{
				$this->flashMessenger()->addMessage("error^ Failed to update Supply Details");
			}
			return $this->redirect()->toRoute('supply', array('action' =>'viewsupplydtl', 'id' => $this->_id));
		}
		
		$supply = $this->getDefinedTable(Stock\SupplyTable::class)->get($this->_id);
		
		foreach ($supply as $sup):
		     if( $sup['status'] == "3" || $sup['status'] == "4"){
                     $this->flashMessenger()->addMessage("notice^ Cannot edit a canceled/closed Purchase Order");
				     return $this->redirect()->toRoute('purorder');		     	  
		     }
 
			 //$po_details = $this->getDefinedTable(Purchase\PODetailsTable::class)->get(array('purchase_order' => $sup['id'])); 
		endforeach;
		
		return new ViewModel( array(
				'supply' => $supply,
		        'supply_details' => $this->getDefinedTable(Stock\SupplyDetailTable::class)->get(array('supply_address' => $this->_id)),
				'supply_address' => $this->getDefinedTable(Stock\SupplyAddressTable::class)->get($this->_id),
				'agency'   => $this->getDefinedTable(Accounts\PartyTable::class)->get(array('p.role'=>'2')),
				'tender'     => $this->getDefinedTable(Tender\TenderTable::class)->getAll(),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'partyObj'     => $this->getDefinedTable(Accounts\PartyTable::class),
				'clientobj'     => $this->getDefinedTable(Stock\SupplyAddressTable::class),
				'activities'  => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
				'statusObj'      => $this->getDefinedTable(Acl\StatusTable::class),
				'uom'      => $this->getDefinedTable(Stock\UomTable::class)->getAll(),
				'item' => $this->getDefinedTable(Stock\ItemTable::class)->getAll(),
				'itemObj'		=>$this->getDefinedTable(Stock\ItemTable::class),
				'itemClass'		=>$this->getDefinedTable(Stock\ItemClassTable::class)->getAll(),
				'itemClassObj'		=>$this->getDefinedTable(Stock\ItemClassTable::class),
				'uomObj'=> $this->getDefinedTable(Stock\UomTable::class),
				'source_locs'	=> $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'location'),
		) );	
	}

    /**
	 * View Individual Supply
	 *
	**/
	public function viewsupplyAction(){
		$this->init();	
		return new ViewModel( array(
				'supply'         => $this->getDefinedTable(Stock\SupplyTable::class)->get($this->_id),
		        'supplyaObj'      => $this->getDefinedTable(Stock\SupplyAddressTable::class),
    		    'userTable'      => $this->getDefinedTable(Administration\UsersTable::class),
				'locationObj'    => $this->getDefinedTable(Administration\LocationTable::class),
				'statusObj'      => $this->getDefinedTable(Acl\StatusTable::class),
				'partyObj'      => $this->getDefinedTable(Accounts\PartyTable::class),
				'subheadObj'      => $this->getDefinedTable(Accounts\SubheadTable::class),
				'itemObj'      => $this->getDefinedTable(Stock\ItemTable::class),
				'uomObj'      => $this->getDefinedTable(Stock\UomTable::class),
				'classObj'      => $this->getDefinedTable(Stock\ItemClassTable::class),
				'company'      => $this->getDefinedTable(Administration\CompanyDetailTable::class)->get(array('id'=>1)),
				'bankObj'		=> $this->getDefinedTable(Administration\BankTable::class),
		) );
	}
	 /**
	 * View Individual Supply
	 *
	**/
	public function viewsupplydtlAction(){
		$this->init();	
		return new ViewModel( array(
				'supply'         => $this->getDefinedTable(Stock\SupplyAddressTable::class)->get($this->_id),
				'supplyObj'         => $this->getDefinedTable(Stock\SupplyTable::class),
		        'supplydObj'      => $this->getDefinedTable(Stock\SupplyDetailTable::class),
    		    'userTable'      => $this->getDefinedTable(Administration\UsersTable::class),
				'locationObj'    => $this->getDefinedTable(Administration\LocationTable::class),
				'statusObj'      => $this->getDefinedTable(Acl\StatusTable::class),
				'partyObj'      => $this->getDefinedTable(Accounts\PartyTable::class),
				'subheadObj'      => $this->getDefinedTable(Accounts\SubheadTable::class),
				'itemObj'      => $this->getDefinedTable(Stock\ItemTable::class),
				'uomObj'      => $this->getDefinedTable(Stock\UomTable::class),
				'classObj'      => $this->getDefinedTable(Stock\ItemClassTable::class),
				'company'      => $this->getDefinedTable(Administration\CompanyDetailTable::class)->get(array('id'=>1)),
				'bankObj'		=> $this->getDefinedTable(Administration\BankTable::class),
		) );
	}
	public function deletesupplyAction()
	{
		$this->init();
		$id = $this->_id;
		$array_id = explode("_", $id);
		$id = $array_id[0];
		
		if ($this->getRequest()->isPost()) {
			$form = $this->getRequest()->getPost();
			
			// Check if the confirmation is "Yes" or "No"
			if ($form['confirm'] == 'Yes') {
				// Proceed with deletion
				$this->_connection->beginTransaction();
				try {
					// Delete the supply item
					$this->getDefinedTable(Stock\SupplyTable::class)->remove($id);
					$this->_connection->commit();
					$this->flashMessenger()->addMessage("success^ Successfully deleted Supply.");
				} catch (Exception $e) {
					$this->_connection->rollback();
					$this->flashMessenger()->addMessage("error^ Failed to remove Supply.");
				}
			} else {
				// If "No", just redirect without doing anything
				$this->flashMessenger()->addMessage("info^ Deletion canceled.");
			}
			
			return $this->redirect()->toRoute('supply', ['action' => 'index']);
		}
		
		$ViewModel = new ViewModel([
			'title' => 'Remove statue',
			'Supply' => $this->getDefinedTable(Stock\SupplyTable::class)->get($id),
		]);
		
		$ViewModel->setTerminal(true);
		return $ViewModel;
	}
	
	/**
	 * Commit Supply order
	 */
	
	public function commitsupplyAction(){
		$this->init();
		
		$sup_order = $this->getDefinedTable(Stock\SupplyTable::class)->get($this->_id);

		foreach ($sup_order as $sorder):
		$data = array(
				'id'			=>$this->_id,
				'status' 		=> 4,
				'author'	    => $this->_author,
				'modified'      => $this->_modified,
		);
		$data   = $this->_safedataObj->rteSafe($data);
		$result = $this->getDefinedTable(Stock\SupplyTable::class)->save($data);
		if($result):
			$this->flashMessenger()->addMessage("success^ Successfully commited Supply No".$porder['supply_no']);
			else:
			$this->flashMessenger()->addMessage("Failed^ Commit Supply.");
		endif;
		return $this->redirect()->toRoute('supply',array('action'=>'viewsupply','id'=>$this->_id));
		endforeach;
	}
	/**
	 * Commit Supply Detail
	 */
	public function commitsupplydtlAction(){
		$this->init();
		
		$sup_orderdtl = $this->getDefinedTable(Stock\SupplyDetailTable::class)->get(array('supply_address'=>$this->_id));
		
		foreach($this->getDefinedTable(Stock\SupplyAddressTable::class)->get($this->_id) as $supA);
		foreach($this->getDefinedTable(Stock\SupplyTable::class)->get($supA['supply']) as $sup);
		$pr=$sup['pr'];
		$agent=$sup['agency'];
		
		/**
			 * Generating voucher no
			 */
		$loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($sup['location'], 'prefix');
		$prefix = $this->getDefinedTable(Accounts\JournalTable::class)->getcolumn(4,'prefix');
		$date = date('ym',strtotime(date('Y-m-d')));
		$tmp_VCNo = $loc.'-'.$prefix.$date;
		$results = $this->getDefinedTable(Accounts\TransactionTable::class)->getSerial($tmp_VCNo);
		$pltp_no_list = array();
		foreach($results as $result):
			array_push($pltp_no_list, substr($result['voucher_no'], 13));
		endforeach;
		$next_serial = max($pltp_no_list) + 1;
			
		switch(strlen($next_serial)){
			case 1: $next_dc_serial = "0000".$next_serial; break;
			case 2: $next_dc_serial = "000".$next_serial;  break;
			case 3: $next_dc_serial = "00".$next_serial;   break;
			case 4: $next_dc_serial = "0".$next_serial;    break;
			default: $next_dc_serial = $next_serial;       break;
		}	
		$voucher_no = $tmp_VCNo.$next_dc_serial;
			/**
			 * Generating voucher no ended
			 */
		$region=$this->getDefinedTable(Administration\LocationTable::class)->getColumn($sup['location'],'region');
		$location=$sup['location'];
		$cost_price=$supA['cost_price'];
		
		foreach ($sup_orderdtl as $spd):
    foreach($this->getDefinedTable(Stock\SupplyAddressTable::class)->get($this->_id) as $supA);
    
    $price = $this->getDefinedTable(Purchase\PRDetailsTable::class)->getColumn(
        array('item' => $spd['item'], 'purchase_receipt' => $pr), 'rate'
    );
    $cost_price = $cost_price + ($price * $spd['quantity']);

    // ✅ Deduct from OpeningStockDtls (item + location level)
    $stockd_rows = $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)
                        ->get(array('item' => $spd['item'], 'location' => $location));
    foreach($stockd_rows as $stockd);

    $data1 = array(
        'id'       => $stockd['id'],
        'quantity' => $stockd['quantity'] - $spd['quantity'],  // ✅ ADD for supply (stock comes IN)
        'author'   => $this->_author,
        'modified' => $this->_modified,
    );
    $result1 = $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->save($data1);

    // ✅ Also deduct from OpeningStock (header level)
    $os_rows = $this->getDefinedTable(Stock\OpeningStockTable::class)
                    ->get(array('id' => $stockd['opening_stock']));
    foreach($os_rows as $os);

    $data2 = array(
        'id'       => $os['id'],
        'quantity' => $os['quantity'] - $spd['quantity'],  // ✅ ADD for supply
        'author'   => $this->_author,
        'modified' => $this->_modified,
    );
    $result2 = $this->getDefinedTable(Stock\OpeningStockTable::class)->save($data2);

endforeach;
		// print_r($cost_price);
		// exit;
		$data=array(
			'id'=>$this->_id,
			'cost_price'=>$cost_price,
			'status'=>4,
		);
		$this->_connection->beginTransaction();
		$result = $this->getDefinedTable(Stock\SupplyAddressTable::class)->save($data);	
		$data3 = array(
			'voucher_date' => $supA['date'],
			'voucher_type' => 4,
			'region'   =>$region,
			'doc_id'   =>"Supply",
			'voucher_no' => $voucher_no,
			'remark' => $supA['supply_no'],
			'voucher_amount' => str_replace( ",", "",$supA['amount']),
			'status' => 3, // status initiated 
			'author' =>$this->_author,
			'created' =>$this->_created,  
			'modified' =>$this->_modified,
		);
		
		$resultt = $this->getDefinedTable(Accounts\TransactionTable::class)->save($data3);
		
		if(	$resultt){	
		foreach($this->getDefinedTable(Stock\SupplyAddressTable::class)->get($this->_id) as $supA);
		
		$inventory=$this->getDefinedTable(Purchase\PODetailsTable::class)->getColumn(array('purchase_order'=>$sup['po']),'subhead');
		
				$tdetailsdata = array(
					'transaction' => $resultt,
					'voucher_dates' => $supA['date'],
					'voucher_types' => 4,
					'location' => $location,
					'head' =>17,
					'sub_head' =>54,
					'bank_ref_type' => '',
					'debit' =>'0.000',
					'credit' =>$supA['amount'],
					'ref_no'=> '', 
					'type' => '1',//user inputted  data
					'status' => 3, // status initiated
					'activity'=>$location,
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
				);
				$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);

				$result1 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata);
					
					$subhead = $this->getDefinedTable(Accounts\SubheadTable::class)->getColumn(array('ref_id'=>$agent,'type'=>2),'id');
					//print_r($subhead);exit;
					$head = $this->getDefinedTable(Accounts\SubheadTable::class)->getColumn($subhead,'head');
				$tdetailsdata1 = array(
					'transaction' => $resultt,
					'voucher_dates' => $supA['date'],
					'voucher_types' => 4,
					'location' => $location,
					'head' => $head,
					'sub_head' => $subhead,
					'bank_ref_type' => '',
					'bank_trans_journal'=>'',
					'debit' =>$supA['amount'],
					'credit' => '0.00',
					'activity'=>$location,
					'ref_no'=> $ref_no, 
					'against'=>0,
					'type' => '1',//user inputted  data
					'status' => 3, // status initiated
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
				);
				$tdetailsdata1 = $this->_safedataObj->rteSafe($tdetailsdata1);
				$result2 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata1);
				/*$tdetailsdata2 = array(
					'transaction' => $resultt,
					'voucher_dates' => $supA['date'],
					'voucher_types' => 4,
					'location' => $location,
					'head' =>$this->getDefinedTable(Accounts\SubheadTable::class)->getColumn($inventory,'head'),
					'sub_head' =>$inventory,
					'bank_ref_type' => '',
					'debit' =>'0.000',
					'credit' =>$supA['cost_price'],
					'ref_no'=> $ref, 
					'activity'=>$location,
					'type' => '1',//user inputted  data
					'status' => 3, // status initiated
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
				);
				$tdetailsdata2 = $this->_safedataObj->rteSafe($tdetailsdata2);
				$result2 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata2);*/
				$this->_connection->commit();
				$this->flashMessenger()->addMessage("success^ Successfully commited Supply No".$porder['supply_no']);
				
			}	
			else{
				$this->_connection->rollback();
				$this->flashMessenger()->addMessage("Failed^ Successfully commited Supply No".$porder['supply_no']);
			}
			return $this->redirect()->toRoute('supply',array('action'=>'viewsupplydtl','id'=>$this->_id));		
		
	}
	/**
	 * Delete purchase order item action
	 */
	public function deleteAction() 
	{
		$this->init(); 
		foreach($this->getDefinedTable(Purchase\PODetailsTable::Class)->get($this->_id) as $podetails);
		//foreach($this->getDefinedTable(Sales\SalesTable::Class)->get($salesd['sales']) as $sales);
		$result = $this->getDefinedTable(Purchase\PODetailsTable::Class)->remove($this->_id);
		if($result > 0):

				$this->flashMessenger()->addMessage("success^ Item deleted successfully");
			else:
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to delete item");
			endif;
			//end			
		
			return $this->redirect()->toRoute('purorder',array('action' => 'editporder','id'=>$podetails['purchase_order']));	
	}
	/**
	 * Add Supply detail Action
	 */
	public function addsupplydtlAction()
	{
		$this->init();
	
		$tender = $this->getDefinedTable(Stock\SupplyTable::class)->getColumn($this->_id,'tender');
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();
			$getNo = $this->getDefinedTable(Stock\SupplyAddressTable::class)->getLastNo();
			if($getNo > 0){
				$Number = $getNo+1;
			}
			else{
				$Number =1;
			}
			$data = array(
				'supply' => $this->_id,
				'supply_no'	=>$Number,
				'amount' => $form['total'],
				'date'=>$form['supply_date'],
				'address'=>$form['address'],
				//'tender_slNo'=>$form['tender_slNo'],
				'status' 			=> 2, 
			);
			$result = $this->getDefinedTable(Stock\SupplyAddressTable::class)->save($data);

			$item_class=$form['item_class'];
			$item=$form['item'];
			$tender_slNo=$form['tender_slNo'];
			//$code_no=$form['code_no'];
			$rate=$form['rate'];
			$amount =$form['amount'];
			$quantity =$form['quantity'];
			$gst_rate = $form['gst_rate'];
			$gst_amt = $form['gst_amt'];
			$batch_no =$form['batch_no'];
			$mftg_date=$form['mftg_date'];
			$exp_date=$form['exp_date'];
			//$total=$form['total'];
			$remarks=$form['remarks'];
					if($result > 0){
						for($i=0; $i < sizeof($item); $i++):
							if(isset($item[$i]) && is_numeric($item[$i])):
								$data = array(
									'supply'		 	=> $this->_id,
									'supply_address'	=> $result,
									'tender_slNo'	=> $tender_slNo[$i],
									'item' 				=> $item[$i],
									//'code_no' 			=> $code_no[$i],
									'batch_no' 			=> $batch_no[$i],
									'item_class' 		=> $item_class[$i],
									'rate' 				=> $rate[$i],
									'expiry_date' 		=> $exp_date[$i],
									'manufactured_date' => $mftg_date[$i],
									'quantity' 			=> $quantity[$i],
									'gst_rate' => $gst_rate[$i],
									'gst_amt' => $gst_amt[$i],
									'amount' 			=> $amount[$i],
									//'total' 			=> $total,
									'uom'				=>1,
									'remarks' 			=> $remarks[$i],
									//'created' 			=>$this->_created,
									//'modified' 			=>$this->_modified,
								);
								$result1 = $this->getDefinedTable(Stock\SupplyDetailTable::class)->save($data);
							endif;
						endfor;
						$this->flashMessenger()->addMessage("success^ Added Supply Details successfully");
					}
					else{
						$this->flashMessenger()->addMessage("Failed^ Failed to add");
					}
					 return $this->redirect()->toRoute('supply', array('action'=> 'viewsupply', 'id'=>$this->_id));
			}
		  
		return new ViewModel( array(
				'itemObj'		=>$this->getDefinedTable(Stock\ItemTable::class),
				'itemClass'		=>$this->getDefinedTable(Stock\ItemClassTable::class)->getAll(),
				'purchasedObj'		=>$this->getDefinedTable(Purchase\PODetailsTable::class),
				'supply'=> $this->_id,
				'tender'=> $tender,
				
				
		));
	}
	/**
	 * Get Item List According to Activity
	 */
	public function getitemactivityAction()
	{	$this->init();
		
		$form = $this->getRequest()->getPost();
		$activity_id = $form['activity_id'];
		$supplier_id = $form['supplier_id'];
		if($activity_id == '1'):
			$items = $this->getDefinedTable(Stock\ItemTable::class)->get(array('i.activity' => $activity_id,'i.supplier' => $supplier_id));
		else:
			$items = $this->getDefinedTable(Stock\ItemTable::class)->get(array('i.activity' => $activity_id));
		endif;
		$stock_item.="<option value=''></option>";
		foreach($items as $item):
		      $item_id =  $item['id'];
				if($item_id != 1150002 || $item_id == 2600122 || $item_id == 102001 || $item_id != 387002):
				    $stock_item .="<option value='".$item_id."'>".$item['code']."</option>";
				endif;
		endforeach;
		echo json_encode(array(
				'stock_item' => $stock_item,
		));
		exit;
	}
	
	/**
	 * Get Item UOM According to Item
	*/
	public function getitemuomAction()
	{
		$this->init();
		
		$form = $this->getRequest()->getPost();
		$item_id = $form['item_id'];
		
		$items = $this->getDefinedTable(Stock\ItemTable::class)->get($item_id);
		foreach($items as $item);
		$item_uoms = $this->getDefinedTable(Stock\UomTable::class)->get($item['uom']);
		
		$uoms .="<option value=''></option>";
		foreach($items as $item):
			$uoms .="<option value='".$item['st_uom_id']."'>".$item['st_uom_code']."</option>";
		endforeach;
		foreach($item_uoms as $item_uom):
			$uoms .="<option value='".$item_uom['uom_id']."'>".$item_uom['uom_code']."</option>";
		endforeach;
		
		echo json_encode(array(
				'uoms' => $uoms,
		));
		exit;
	}
	/**
	 * Get Item UOM According to Item
	*/
	public function getitemAction()
{
    $this->init();
    
    $form = $this->getRequest()->getPost();
    $itemclass_id = $form['itemclassId'];
     
    $items = $this->getDefinedTable(Stock\ItemClassTable::class)
                  ->getitemByClass($itemclass_id);

    $itemlist = "<option value=''></option>";

   foreach($items as $item):

    $stockData = $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)
                      ->get(['item' => $item['id']]);

    $totalStock = 0;
    $locationCount = 0;

    foreach ($stockData as $row) {
        $totalStock += $row['quantity'];
        $locationCount++; // count locations
    }

    // ✅ FILTER CONDITION
    if ($totalStock > 0) {
        $itemlist .= "<option value='".$item['id']."' 
                        data-gst='".$item['gst_rate']."'
                        data-stock='".$totalStock."'>
                        ".$item['name']."
                      </option>";
    }

endforeach;

    echo json_encode([
        'item' => $itemlist,
    ]);  
    exit;
}
	
	
		/**
	 * Get Item UOM Purchase Receipt According to purchase order
	*/
	public function getprAction()
	{
		$this->init();
		
		$form = $this->getRequest()->getPost();
		$po_id =$form['poId'];
		
		//$pr = $this->getDefinedTable(Purchase\PurchaseReceiptTable::class)->getPurchaseReceipt(array('pr.purchase_order'=>$po_id,'pr.status'=>4,));
		//$pr = $this->getDefinedTable(Purchase\PurchaseReceiptTable::class)->getPurchaseReceipt(array('pr.purchase_order' => $po_id));
		$pr = $this->getDefinedTable(Purchase\PurchaseReceiptTable::class)->getAll();

		//print_r($item);exit;
		$prlist ="<option value=''>Select</option>";
		foreach($pr as $row):
			$prlist .="<option value='".$row['id']."'>".$row['prn_no']."</option>";
		endforeach;
		
		echo json_encode(array(
				'prlist' => $prlist,
		));
		exit;
	}
	/**
	 * cancel purchase order Action
	 * 
	 */
	public function cancelporderAction(){
		$this->init();
		
		$pur_order = $this->getDefinedTable(Purchase\PurchaseOrderTable::class)->get($this->_id);
		
		foreach ($pur_order as $porder):
			$data = array(
					'id'			=>$this->_id,
					'status' 		=> 4,
					'author'	    => $this->_author,
					'modified'      => $this->_modified,
			);
			$data   = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Purchase\PurchaseOrderTable::class)->save($data);
			if($result):
			$this->flashMessenger()->addMessage("success^ Successfully cancelled PO No.".$porder['po_no']);
			return $this->redirect()->toRoute('purorder',array('action'=>'viewporder','id'=>$this->_id));
			endif;
		endforeach;
	}
	public function getuomAction()
	{
		$form = $this->getRequest()->getPost();
		$itemId =$form['itemId'];
		$item = $this->getDefinedTable(Stock\ItemTable::class)->get(array('i.id'=>$itemId));
		$selectedUomId = $item[0]['uom'];
		$uom = $this->getDefinedTable(Stock\UomTable::class)->get(array('id' => $selectedUomId));
		$uomop = "<option value=''></option>";
		foreach($item as $items);
		$uom = $this->getDefinedTable(Stock\UomTable::class)->get(array('id'=>$items['uom']));
		foreach($uom as $uoms):
			$selected = ($uoms['id'] == $selectedUomId) ? "selected" : "";
        $uomop .= "<option value='" . $uoms['id'] . "' $selected>" . $uoms['code'] . "</option>";
		endforeach;
		echo json_encode(array(
				'uom' => $uomop,
		));
		exit;
	}
	public function getsupplierAction()
	{
		$form = $this->getRequest()->getPost();
		$tender = $form['tender'];

		$supply = $this->getDefinedTable(Tender\TenderTable::class)->get($tender);
		$selectedsup = $supply[0]['agency'];
		$sup = $this->getDefinedTable(Accounts\PartyTable::class)->get($selectedsup);
		$supplier = "<option value=''></option>";
		
		$po = $this->getDefinedTable(Purchase\PurchaseOrderTable::class)->get(array('tender'=>$tender));
		//print_r($item);exit;
		$polist ="<option value=''>Select</option>";
		foreach($po as $row):
			$polist .="<option value='".$row['id']."'>".$row['po_no']."</option>";
		endforeach;
		foreach ($sup as $sup) {
			$isSelected = ($sup['id'] == $selectedsup) ? ' selected' : '';
			$supplier .= "<option value='" . $sup['id'] . "'" . $isSelected . ">" . $sup['name'] . "</option>";
		}
			echo json_encode(array(
					'supplier' => $supplier,
					'polist'	=> $polist,
			));
			exit;
	}
	
}
