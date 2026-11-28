<?php
namespace Purchase\Controller;

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

class PurchaseController extends AbstractActionController
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
		$purchase_results = $this->getDefinedTable(Purchase\PurchaseOrderTable::class)->getDateWise('po_date',$year,$month);
		return new ViewModel(array(
			'purchase_results' => $purchase_results,
			'statusObj'        => $this->getDefinedTable(Acl\StatusTable::class),
			'minYear' 		   => $this->getDefinedTable(Purchase\PurchaseOrderTable::class)->getMin('po_date'),
			'data'             => $data,
			'partyObj'         => $this->getDefinedTable(Accounts\PartyTable::class),
		) );
	}
	
	/** 
	 * Add Purchase Order Action
	 */
	public function addporderAction()
	{
		$this->init();
		
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();						
			$location_prefix = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($form['destination'],'prefix');
			$machine_no = '0';
			print_r($form['item']);
    		$date = date('ym',strtotime($form['po_date']));
			$tmp_SLNo = $location_prefix."PO".$machine_no.$date;
						$results = $this->getDefinedTable(Purchase\PurchaseOrderTable::class)->getMonthlyPO($tmp_SLNo);
						if(sizeof($results) < 1 ):
							$next_serial = "0001";
						else:
							$po_no_list = array();
							foreach($results as $result):
								array_push($po_no_list, substr($result['po_no'], 12));
							endforeach;
							$next_serial = max($po_no_list) + 1;
						endif;
						
						switch(strlen($next_serial)){
							case 1: $next_po_serial = "000".$next_serial; break;
							case 2: $next_po_serial = "00".$next_serial;  break;
							case 3: $next_po_serial = "0".$next_serial;   break;
							default: $next_po_serial = $next_serial;      break;
						}            			
						$po_no = $tmp_SLNo.$next_po_serial;
			$data = array(
					'po_no'            => $po_no, 
					'location'         => 1,
					'supplier'         => $form['supplier'],
					'po_date'          => $form['po_date'],
					'po_amount'        => $form['po_amount'],
					'tender'     		=> $form['tender'],
					'note'              => $form['note'],
					// 'gst'              => $form['gst'],
					'destination'		=> $form['destination'],
					'subject'		=> $form['subject'],
					'status' 			=> 2, 
					'author'           => $this->_author,
					'created'          => $this->_created,
					'modified'         => $this->_modified,
			);
			$data   = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Purchase\PurchaseOrderTable::class)->save($data);

            $data1 = array(
						'id'     => $form['tender'],
						'suppliers'             => $form['suppliers'],
						'modified'           => $this->_modified
			   );
 			 $result1 = $this->getDefinedTable(Tender\TenderTable::class)->save($data1);
			if($result > 0){ 
				$item         = $form['item'];
				$item_class   = $form['item_class'];
				$tender_sl_no   = $form['tender_sl_no'];
				$subhead   	= $form['subhead'];
				$specification =$form['specification'];
				$uom          = $form['uom'];
				$quantity     = $form['quantity'];
				$rate         = $form['rate'];
				$gst        = $form['gst_charge'];
				$remarks      = $form['remarks'];
				for($i=0; $i < sizeof($item); $i++):
					$po_details = array(
		      					'purchase_order' => $result,
					     		'item'           => $item[$i],
								'item_class'     => $item_class[$i],
								'tender_sl_no'     => $tender_sl_no[$i],
								'subhead'     => $subhead[$i],
								'specification'  => $specification[$i],
					     		'uom'            => $uom[$i],
					     		'quantity'  	 => $quantity[$i],
					     		'rate'      	 => $rate[$i],  
								'gst'      	 => $gst,   
								'remarks' 	 	 => $remarks[$i],
					      		'author'    	 => $this->_author,
					      		'created'   	 => $this->_created,
					      		'modified'  	 => $this->_modified
						);
		     		$po_details   = $this->_safedataObj->rteSafe($po_details);
			     	$this->getDefinedTable(Purchase\PODetailsTable::class)->save($po_details);				     
				endfor;
				$this->flashMessenger()->addMessage("success^ Successfully added new Purchase Order :". $po_no);
				return $this->redirect()->toRoute('purorder', array('action' =>'viewporder', 'id' => $result));
			}
			else{
				$this->flashMessenger()->addMessage("error^ Failed to add new Purchase Order");
				return $this->redirect()->toRoute('addporder');
			}		
		}		
		return new ViewModel( array(
				'user_location' => $this->_userloc,
				'suppliers'   	=> $this->getDefinedTable(Accounts\PartyTable::class)->get(array('p.role'=>'1')),
				'activities'  	=> $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
				'regions'     	=> $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'locationObj' 	=> $this->getDefinedTable(Administration\LocationTable::class),
				'itemcls'    	=> $this->getDefinedTable(Stock\ItemClassTable::class)->getAll(),
				'source_locs'	=> $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'location'),
				//'purorder' => $this->getDefinedTable(Purchase\PurchaseOrderTable::class)->getpo(array('po.destination'=>$source_locs,'po.status'=>4,'po.partial'=>1)),
				'tender'		=> $this->getDefinedTable(Tender\TenderTable::class)->getTender(array('t.status'=>21,'t.suppliers'=>1)),
				'sub_head'		=> $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('sh.head'=>[35,34])),
				'gst'     	=> $this->getDefinedTable(Purchase\GstTable::class)->getAll(),
				
		));		
	}
	
	public function deleteporderAction()
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
                $this->getDefinedTable(Purchase\PurchaseOrderTable::class)->remove($id);
                $this->_connection->commit();
                $this->flashMessenger()->addMessage("success^ Successfully deleted Purchase Order.");
            } catch (\Exception $e) {
                $this->_connection->rollback();
                $this->flashMessenger()->addMessage("error^ Failed to remove Purchase Order: " . $e->getMessage());
            }
        } else {
            // If "No" is selected, do not delete and redirect with a message
            $this->flashMessenger()->addMessage("info^ Deletion cancelled.");
        }

        return $this->redirect()->toRoute('purorder', ['action' => 'index']);
    }

    // Load the view with the purchase order details
    $ViewModel = new ViewModel([
        'title' => 'Remove Purchase Order',
        'purchaseOrder' => $this->getDefinedTable(Purchase\PurchaseOrderTable::class)->get($id),
    ]);
    $ViewModel->setTerminal(true);
    return $ViewModel;
}


	/**
	 * Edit Purchase Order Action
	 * Ammend Purchase Order Action
	 */
	public function editporderAction()
	{
		$this->init();
		
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();
			$data = array(
					'id'               => $this->_id,
					'location'         => 1,
					'supplier'         => $form['supplier'],
					'po_date'          => $form['po_date'], 
					'po_amount'        => $form['po_amount'],
					'order_no'     		=> $form['order_no'],
					'note'              => $form['note'],
					'destination'		=> $form['destination'],
					'subject'		=> $form['subject'],
					'tender'     		=> $form['tender'],
					'status' 			=> 2, 
					'author'           => $this->_author,
					'created'          => $this->_created,
					'modified'         => $this->_modified,
			);
			$data   = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Purchase\PurchaseOrderTable::class)->save($data);
				$id			  =	$form['id'];
				$item         = $form['item'];
				$uom          = $form['uom'];
				$quantity     = $form['quantity'];
				$rate         = $form['rate'];
				$remarks      = $form['remarks'];
				$specification      =$form['specification'];
				for($i=0; $i < sizeof($id); $i++):
					if(isset($item[$i]) && $item[$i] > 0):
					
						$po_details = array(
								'id'			=> $id[$i],
		      					'purchase_order' => $result,
					     		'item'           => $item[$i],
					     		'uom'            => $uom[$i],
					     		'quantity'  	 => $quantity[$i],
					     		'rate'      	 => $rate[$i],
								'specification'  => $specification[$i],
								'remarks' 	 	 => $remarks[$i],
					      		'author'    	 => $this->_author,
					      		'created'   	 => $this->_created,
					      		'modified'  	 => $this->_modified
						);
		     		$po_details   = $this->_safedataObj->rteSafe($po_details);
			     	$this->getDefinedTable(Purchase\PODetailsTable::class)->save($po_details);		
				   	endif; 		     
				endfor;
				if(sizeof($id)!=sizeof($item)){
				for($i=sizeof($id); $i < sizeof($item); $i++):
					if(isset($item[$i]) && $item[$i] > 0):
					
						$po_details = array(
		      					'purchase_order' => $result,
					     		'item'           => $item[$i],
					     		'uom'            => $uom[$i],
					     		'quantity'  	 => $quantity[$i],
					     		'rate'      	 => $rate[$i],
								'remarks' 	 	 => $remarks[$i],
								'specification'  => $specification[$i],
					      		'author'    	 => $this->_author,
					      		'created'   	 => $this->_created,
					      		'modified'  	 => $this->_modified
						);
		     		$po_details   = $this->_safedataObj->rteSafe($po_details);
			     	$this->getDefinedTable(Purchase\PODetailsTable::class)->save($po_details);		
				   	endif; 		     
				endfor;
				}
			
			$data   = $this->_safedataObj->rteSafe($data);
			$result1 = $this->getDefinedTable(Purchase\PurchaseOrderTable::class)->save($data);
			if($result > 0){
				$this->flashMessenger()->addMessage("success^ Successfully updated Purchase order no. ". $form['po_no']);
			}
			else{
				$this->flashMessenger()->addMessage("error^ Failed to update Purchase Order");
			}
			return $this->redirect()->toRoute('purorder', array('action' =>'viewporder', 'id' => $this->_id));
		}
		
		$pur_order = $this->getDefinedTable(Purchase\PurchaseOrderTable::class)->get($this->_id);
		
		foreach ($pur_order as $porder):
		     if( $porder['status'] == "3" || $porder['status'] == "4"){
                     $this->flashMessenger()->addMessage("notice^ Cannot edit a canceled/closed Purchase Order");
				     return $this->redirect()->toRoute('purorder');		     	  
		     }
 
			 $po_details = $this->getDefinedTable(Purchase\PODetailsTable::class)->get(array('purchase_order' => $porder['id'])); 
		endforeach;
		
		return new ViewModel( array(
				'purchase_order' => $pur_order,
		        'po_details' => $po_details,
				'suppliers'   => $this->getDefinedTable(Accounts\PartyTable::class)->get(array('p.role'=>'1')),
				'regions'     => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'activities'  => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
				'statusObj'      => $this->getDefinedTable(Acl\StatusTable::class),
				'uoms'      => $this->getDefinedTable(Stock\UomTable::class)->getAll(),
				'itemsObj' => $this->getDefinedTable(Stock\ItemTable::class),
				'item' => $this->getDefinedTable(Stock\ItemTable::class)->getAll(),
				'uomObj'=> $this->getDefinedTable(Stock\UomTable::class),
				'itemcls'    	=> $this->getDefinedTable(Stock\ItemClassTable::class)->getAll(),
				'tender'		=> $this->getDefinedTable(Tender\TenderTable::class)->getAll(),
				'sub_head'		=> $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('sh.head'=>[35,34])),
		) );	
	}



	

    /**
	 * View Individual Purchase Order
	 *
	**/
	public function viewporderAction(){
		$this->init();	
		return new ViewModel( array(
				'purchase_order' => $this->getDefinedTable(Purchase\PurchaseOrderTable::class)->get($this->_id),
		        'PODObject'      => $this->getDefinedTable(Purchase\PODetailsTable::class),
    		    'userTable'      => $this->getDefinedTable(Administration\UsersTable::class),
				'locationObj'    => $this->getDefinedTable(Administration\LocationTable::class),
				'statusObj'      => $this->getDefinedTable(Acl\StatusTable::class),
				'partyObj'      => $this->getDefinedTable(Accounts\PartyTable::class),
				'subheadObj'      => $this->getDefinedTable(Accounts\SubheadTable::class),
				'itemObj'      => $this->getDefinedTable(Stock\ItemTable::class),
				'uomObj'      => $this->getDefinedTable(Stock\UomTable::class),
				'classObj'      => $this->getDefinedTable(Stock\ItemClassTable::class),
				// 'detailRow'     	=> $this->getDefinedTable(Purchase\PODetailsTable::class),
				
		) );
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
		$itemclass_id =$form['itemclassId'];
		
		$item = $this->getDefinedTable(Stock\ItemClassTable::class)->getitemByClass($itemclass_id);
		//print_r($item);exit;
		$itemlist ="<option value=''></option>";
		foreach($item as $items):
			$itemlist .="<option value='".$items['id']."'>".$items['name']."</option>";
		endforeach;
		
		echo json_encode(array(
				'item' => $itemlist,
		));
		exit;
	}
	/**
	 * Get Item UOM According to Item
	*/
	public function getsubheadAction()
	{
		$this->init();
		
		$form = $this->getRequest()->getPost();
		$itemclass_id =$form['itemclassId'];
		if($itemclass_id==33){
			$sub_head = $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('sh.head'=>1));
		}
		else{
			$sub_head = $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('sh.head'=>9));
		}
		
		//print_r($item);exit;
		$subheadlist ="<option value=''></option>";
		foreach($sub_head as $row):
			$subheadlist .="<option value='".$row['id']."'>".$row['name']."</option>";
		endforeach;
		
		echo json_encode(array(
				'subheadlist' => $subheadlist,
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
	
	/**
	 * commit purchase order Action
	 * 
	 */
	public function commitporderAction(){
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
			$this->flashMessenger()->addMessage("success^ Successfully commited PO No.".$porder['po_no']);
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
}
