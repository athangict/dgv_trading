<?php
namespace Purchase\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Authentication\AuthenticationService;
use Interop\Container\ContainerInterface;
use Purchase\Model As Purchase;
use Accounts\Model As Accounts;
use Asset\Model As Asset;
use Acl\Model As Acl;
use Administration\Model As Administration;
use Stock\Model As Stock;
class ReceiptController extends AbstractActionController
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
			$location = $form['location'];

			$month = ($month == 0)? date('m'):$month;
			$year = ($year == 0)? date('Y'):$year;
			$data = array(
					'year' => $year,
					'month' => $month,
					'location' => $location,
			);
		}else{
			$month = ($month == 0)? date('m'):$month;
			$year = ($year == 0)? date('Y'):$year;
			$location = '-1';

			$data = array(
					'year' => $year,
					'month' => $month,
					'location' => $location,
			);
		}
		$receipts_results = $this->getDefinedTable(Purchase\PurchaseReceiptTable::class)->getDateWise('prn_date',$year,$month,$location);
		
		return new ViewModel( array(
               'receipts_results' => $receipts_results,
			   'minYear' 		  => $this->getDefinedTable(Purchase\PurchaseReceiptTable::class)->getMin('prn_date'),
			   'data'             => $data,
			   'partyObj'         => $this->getDefinedTable(Accounts\PartyTable::class),
			   'locationObj'      => $this->getDefinedTable(Administration\LocationTable::class),
			   'poObj'            => $this->getDefinedTable(Purchase\PurchaseOrderTable::class),
		));
	}

	/**
	 * Add Receipt Action
	 */
	public function addreceiptAction()
	{
		$this->init();
	
		$source_locs = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'location');
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();
		
					$location_prefix = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($form['location'],'prefix');
					$date = date('ym',strtotime($form['prn_date']));
					$tmp_PRNo = $location_prefix."PR".$date;
					$results = $this->getDefinedTable(Purchase\PurchaseReceiptTable::class)->getMonthlyPR($tmp_PRNo);
					if(sizeof($results) < 1 ):
							$next_serial = "0001";
						else:
				    $prn_no_list = array();
	                foreach($results as $result):
				        array_push($prn_no_list, substr($result['prn_no'], 11));
				    endforeach;
	                $next_serial = max($prn_no_list) + 1;
					endif;

					switch(strlen($next_serial)){
						case 1: $next_prn_serial = "000".$next_serial; break;
					    case 2: $next_prn_serial = "00".$next_serial;  break;
					    case 3: $next_prn_serial = "0".$next_serial;   break;
					    default: $next_prn_serial = $next_serial;       break;
					}
					
					$prn_no = $tmp_PRNo.$next_prn_serial;
					//print_r($prn_no);exit;
					$data = array(
							 'prn_no'              => $prn_no,
							 'purchase_order'     => $form['po_no'],
							 'prn_date'			  => $form['prn_date'],
							 'location'           => $form['location'],
							 'supplier'           => $form['supplier'], 
							 'note'               => $form['note'],
							 'status'             => 2,
							 'author'             => $this->_author,
							 'created'            => $this->_created,
							 'modified'           => $this->_modified
					);
					$data   = $this->_safedataObj->rteSafe($data);
					$result = $this->getDefinedTable(Purchase\PurchaseReceiptTable::class)->save($data);
					$data1 = array(
						'id'     => $form['po_no'],
						'partial'             => $form['partail'],
						'modified'           => $this->_modified
			   );
			   $result1 = $this->getDefinedTable(Purchase\PurchaseOrderTable::class)->save($data1);
					if($result){
						
						 $this->flashMessenger()->addMessage("success^ Successfully added new Purchase Receipt :". $prn_no);
					}
					else{
						$this->flashMessenger()->addMessage("Failed^ Failed to add new Purchase Receipt");
					}
					 return $this->redirect()->toRoute('receipt', array('action'=> 'viewreceipt', 'id'=>$result));
			}
		  
		return new ViewModel( array(
				'regions'     => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'items'  	  => $this->getDefinedTable(Stock\ItemTable::class)->getAll(),
				'itemObj'     => $this->getDefinedTable(Stock\ItemTable::class),
				'uomObj'	  => $this->getDefinedTable(Stock\UomTable::class),
				'itemuomObj'  => $this->getDefinedTable(Stock\ItemUomTable::class),
				'pur_ordersObj' => $this->getDefinedTable(Purchase\PurchaseOrderTable::class),
				'purorder' => $this->getDefinedTable(Purchase\PurchaseOrderTable::class)->getpo(array('destination'=>$source_locs,'status'=>4,'partial'=>1)),
				'suppliers'   => $this->getDefinedTable(Accounts\PartyTable::class)->getAll(),
				'source_locs'=>$source_locs,
		));
	}
	public function deletepreceiptAction()
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
                $this->getDefinedTable(Purchase\PurchaseReceiptTable::class)->remove($id);
                $this->_connection->commit();
                $this->flashMessenger()->addMessage("success^ Successfully deleted Purchase Receipt.");
            } catch (\Exception $e) {
                $this->_connection->rollback();
                $this->flashMessenger()->addMessage("error^ Failed to remove Purchase Receipt: " . $e->getMessage());
            }
        } else {
            // If "No" is selected, do not delete and redirect with a message
            $this->flashMessenger()->addMessage("info^ Deletion cancelled.");
        }

        return $this->redirect()->toRoute('receipt', ['action' => 'index']);
    }

    // Load the view with the purchase receipt details
    $ViewModel = new ViewModel([
        'title' => 'Remove Receipt',
        'purchaseReceipt' => $this->getDefinedTable(Purchase\PurchaseReceiptTable::class)->get($id),
    ]);
    $ViewModel->setTerminal(true);
    return $ViewModel;
}

		/**
	 * Edit Purchase Receipt Action
	**/
	public function editreceiptAction()
	{
		$this->init();

		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();
			$data = array(
					 'id'			      => $form['id'],
					 'purchase_order'     => $form['po_no'],
					 'prn_date'			  => $form['prn_date'],
					 'location'           => $form['location'],
					 'supplier'           => $form['supplier'],
					 'note'               => $form['note'],
					 'status'             => 2,
					 'author'             => $this->_author,
					 'created'            => $this->_created,
					 'modified'           => $this->_modified
			);
			$data   = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Purchase\PurchaseReceiptTable::class)->save($data);

			if($result > 0){
				$this->flashMessenger()->addMessage("success^ Successfully updated Purchase Receipt no. ". $form['prn_no']);
				return $this->redirect()->toRoute('receipt', array('action' =>'viewreceipt', 'id' => $result));
			}
			else{
				$this->flashMessenger()->addMessage("error^ Failed to add new Purchase Receipt");
				return $this->redirect()->toRoute('receipt');
			}
		}

		return new ViewModel( array(
				'pur_receipt' 		=> $this->getDefinedTable(Purchase\PurchaseReceiptTable::class)->get($this->_id),
				'statusObj'        => $this->getDefinedTable(Acl\StatusTable::class),
				'regions'          => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'locationObj'      => $this->getDefinedTable(Administration\LocationTable::class),
				'po_detailsObj'    => $this->getDefinedTable(Purchase\PODetailsTable::class),
				'pur_ordersObj'    => $this->getDefinedTable(Purchase\PurchaseOrderTable::class),
                'suppliers'   => $this->getDefinedTable(Accounts\PartyTable::class)->get(array('p.role'=>'1')),
				'source_locs' => $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'location'),
		) );
	}

	/**
	 * Get Purchase Order for Purchase Receipt
	 **/
	public function getporderAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();
			$porder_id = $form['porder'];
		}
		 return $this->redirect()->toRoute('receipt', array('action'=> 'addreceipt', 'id'=>$porder_id));
		$ViewModel = new ViewModel(array(
				'suppliers' => $this->getDefinedTable(Accounts\PartyTable::class)->get(array('p.role' => '1')),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}

	/**
	 * Get Purchase Order according to supplier
	 */
	public function getpurchaseorderAction()
	{
		$this->init();
		$form = $this->getRequest()->getPost();
		$supplier_id = $form['supplier_id'];

		$purchase_orders = $this->getDefinedTable(Purchase\PurchaseOrderTable::class)->get(array('status' => 2, 'supplier' => $supplier_id));

		$po="<option value=''></option>";
		foreach($purchase_orders as $purchase_order):
			$po .="<option value='".$purchase_order['id']."'>".$purchase_order['po_no']."</option>";
		endforeach;

		echo json_encode(array(
				'po' => $po,
		));
		exit;
	}

	/**
	 * Get Item Uoms, Po_uom, Po_qty and balance_qty by item_id and po_id
	 */
	public function getuomAction()
	{
		$this->init();

		$form = $this->getRequest()->getPost();
		$item_id = $form['item_id'];
		$po_id = $form['po_id'];
		//$po_details_id = $form['po_details_id'];

		$items = $this->getDefinedTable(Stock\ItemTable::class)->get($item_id);
		$item_uoms = $this->getDefinedTable(Stock\ItemUomTable::class)->get(array('ui.item'=>$item_id));

		$uoms .="<option value=''></option>";
		foreach($items as $item):
			$uoms .="<option value='".$item['st_uom_id']."'>".$item['st_uom_code']."</option>";
		endforeach;
		foreach($item_uoms as $item_uom):
			$uoms .="<option value='".$item_uom['uom_id']."'>".$item_uom['uom_code']."</option>";
		endforeach;
		//if($po_details_id > 0):
			//$po_details = $this->getDefinedTable(Purchase\PODetailsTable::class)->get($po_details_id);
		//else:
			$po_details = $this->getDefinedTable(Purchase\PODetailsTable::class)->get(array('purchase_order'=>$po_id,'item'=>$item_id));
		//endif;
		foreach($po_details as $po_detail);
		$balance_qty = $po_detail['quantity'] - $po_detail['received_qty'];
		$balance_qty = ($balance_qty < 0)?'0.00':$balance_qty;

		$po_qty = number_format($po_detail['quantity'], 3, '.', '');
		$balance_qty = number_format($balance_qty, 3, '.', '');

		echo json_encode(array(
				'uoms' => $uoms,
				'po_uom' => $po_detail['uom_id'],
				'po_qty' => $po_detail['quantity'],
				'balance_qty' => $balance_qty,
				'po_details_id' => $po_detail['id'],
		));
		exit;
	}

	/**
	 * View Purchase Receipt
	 *
	 **/
	public function viewreceiptAction()
	{
		$this->init();
		return new ViewModel( array(
				'purchase_receipt' => $this->getDefinedTable(Purchase\PurchaseReceiptTable::class)->get($this->_id),
				'PRDetailsObj'     => $this->getDefinedTable(Purchase\PRDetailsTable::class),
				'purchase_orderObj'=> $this->getDefinedTable(Purchase\PurchaseOrderTable::class),
				'userTable'        => $this->getDefinedTable(Administration\UsersTable::class),
				'locationObj'      => $this->getDefinedTable(Administration\LocationTable::class),
				'statusObj'        => $this->getDefinedTable(Acl\StatusTable::class),
				'itemObj' => $this->getDefinedTable(Stock\ItemTable::class),
				'party' => $this->getDefinedTable(Accounts\PartyTable::class),
				'itemuomObj'  => $this->getDefinedTable(Stock\ItemUomTable::class),
				'po_detailsObj'   => $this->getDefinedTable(Purchase\PODetailsTable::class),
				'company'      => $this->getDefinedTable(Administration\CompanyDetailTable::class)->get(array('id'=>1)),
				'bankObj'		=> $this->getDefinedTable(Administration\BankTable::class),
		) );
	}
	/**
	 * Add Receipt detail Action
	 */
	public function addreceiptdtlAction()
	{
		$this->init();
	
		$pon = $this->getDefinedTable(Purchase\PurchaseReceiptTable::class)->getColumn($this->_id,'purchase_order');
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();
			$item=$form['item'];
			$rate=$form['rate'];
			$accepted_qty =$form['accepted_qty'];
			$po_quantity=$form['po_quantity'];
			//$batch=$form['batch'];
			$damage_qty=$form['damage_qty'];
			//$discount=$form['discount'];
			//$ref_no=$form['ref_no'];
			//$freight_party=$form['freight_party'];
			//$freight=$form['freight'];
			//$freight_ref=$form['freight_ref'];
			//$tax_party=$form['tax_party'];
			//$tax=$form['tax'];
			//$tax_ref=$form['tax_ref'];
			//$loading_party=$form['loading_party'];
			//$loading=$form['loading'];
			//$load_ref=$form['load_ref'];
			//$other_expense_party=$form['other_expense_party'];
			//$other_expenses=$form['other_expenses'];
			//$other_expense_ref=$form['other_expense_ref'];
			$total=$form['total'];
			for($i=0; $i < sizeof($accepted_qty); $i++):
				//if(isset($accepted_qty[$i]) && is_numeric($accepted_qty[$i])):
					if(isset($accepted_qty[$i]) && is_numeric($accepted_qty[$i]) && $accepted_qty[$i] > 0):

					$data = array(
						'purchase_receipt' => $this->_id,
						'item' => $item[$i],
						'uom' => 1,
						'rate' => $rate[$i],
						//'batch' => $batch[$i],
						'accept_qty' => $accepted_qty[$i],
						'po_quantity' => $po_quantity[$i],
						'damage_qty' => $damage_qty[$i],
						//'discount' => $discount[$i],
						//'ref_no' => $form['ref_no'],
						//'freight_party' => $form['freight_party'],
						//'freight' => $form['freight'],
						//'freight_ref' => $form['freight_ref'],
						//'tax_party' => $form['tax_party'],
						//'tax' => $form['tax'],
						//'tax_ref' => $form['tax_ref'],
						//'loading_party' => $form['loading_party'],
						//'loading' => $form['loading'],
						//'load_ref' => $form['load_ref'],
						//'other_expense_party' => $form['other_expense_party'],
						//'other_expenses' => $form['other_expenses'],
						//'other_expense_ref' => $form['other_expense_ref'],
						//'note' => $form['note'],
						'total' => $form['total'],
						'author' =>$this->_author,
						'created' =>$this->_created,
						'modified' =>$this->_modified,
					);
					$result = $this->getDefinedTable(Purchase\PRDetailsTable::class)->save($data);
				endif;
			endfor;
					if($result > 0){
						$data = array(
						'id' => $this->_id,
						'total' => $form['total'],
						'author' =>$this->_author,
						'created' =>$this->_created,
						'modified' =>$this->_modified,
					);
					$result1 = $this->getDefinedTable(Purchase\PurchaseReceiptTable::class)->save($data);
						 $this->flashMessenger()->addMessage("success^ Successfully added new Purchase Receipt :". $prn_no);
					}
					else{
						$this->flashMessenger()->addMessage("Failed^ Failed to add new Purchase Receipt");
					}
					 return $this->redirect()->toRoute('receipt', array('action'=> 'viewreceipt', 'id'=>$this->_id));
			}
		  
		return new ViewModel( array(
				'purchase'     => $this->getDefinedTable(Purchase\PODetailsTable::class)->get(array('purchase_order'=>$pon)),
				'itemObj'		=>$this->getDefinedTable(Stock\ItemTable::class),
				'pr'=>$this->_id,
				'partyObj'  	    	=> $this->getDefinedTable(Accounts\PartyTable::class),
				
				
		));
	}
	
	/**
	 * Edit Receipt detail Action
	 */
	public function editreceiptdtlAction()
	{
		$this->init();
	
		$pon = $this->getDefinedTable(Purchase\PurchaseReceiptTable::class)->getColumn($this->_id,'purchase_order');
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();
			$item=$form['item'];
			$id=$form['id'];
			$rate=$form['rate'];
			$accepted_qty=$form['accepted_qty'];
			$po_quantity=$form['po_quantity'];
			$damage_qty=$form['damage_qty'];
			$discount=$form['discount'];
			$ref_no=$form['ref_no'];
			$note=$form['note'];
			$total=$form['total'];
			for($i=0; $i < sizeof($item); $i++):
				if(isset($item[$i]) && is_numeric($item[$i])):
					$data = array(
						'id'				=> $id[$i],
						'purchase_receipt' 	=> $this->_id,
						'item'				=> $item[$i],
						'uom' 				=> 1,
						'rate' 				=> $rate[$i],
						'batch'				=>$form['batch'][$i],
						'accept_qty' 		=> $accepted_qty[$i],
						'po_quantity' 		=> $po_quantity[$i],
						'damage_qty' 		=> $damage_qty[$i],
						'discount' 		    => $discount[$i],
						'ref_no'            => $form['ref_no'][$i],
						'note'              => $form['note'],
						'total'             => $form['total'],
						'author' 			=>$this->_author,
						'created' 			=>$this->_created,
						'modified' 			=>$this->_modified,
					);
					$result = $this->getDefinedTable(Purchase\PRDetailsTable::class)->save($data);
				// echo '<pre>';
				// var_dump($data);
				// echo '</pre>';
				endif;
			endfor;
					if($result > 0){
						$data = array(
						'id' => $this->_id,
						'total' => $form['total'],
						'author' =>$this->_author,
						'created' =>$this->_created,
						'modified' =>$this->_modified,
					);
					$result1 = $this->getDefinedTable(Purchase\PurchaseReceiptTable::class)->save($data);
						 $this->flashMessenger()->addMessage("success^ Successfully added new Purchase Receipt :". $prn_no);
					}
					else{
						$this->flashMessenger()->addMessage("Failed^ Failed to add new Purchase Receipt");
					}
					 return $this->redirect()->toRoute('receipt', array('action'=> 'viewreceipt', 'id'=>$this->_id));
			}
		  
		return new ViewModel( array(
				'receipt'     => $this->getDefinedTable(Purchase\PRDetailsTable::class)->get(array('purchase_receipt'=>$this->_id)),
				'itemObj'		=>$this->getDefinedTable(Stock\ItemTable::class),
				'pr'=>$this->getDefinedTable(Purchase\PurchaseReceiptTable::class)->get($this->_id),
				
				
		));
	}


	/**
	 * commit purchase receipt Action
	 */
	public function commitreceiptAction()
{
    $this->init();

    // Fetch purchase receipt details
    $pur_receipt_details = $this->getDefinedTable(Purchase\PRDetailsTable::class)->get(['purchase_receipt' => $this->_id]);
    $purchase_order_id = $this->getDefinedTable(Purchase\PurchaseReceiptTable::class)->getColumn($this->_id, 'purchase_order');

    // Fetch purchase order details
    $po_details = $this->getDefinedTable(Purchase\PODetailsTable::class)->get(['purchase_order' => $purchase_order_id]);

    // Fetch location
    $location = $this->getDefinedTable(Purchase\PurchaseReceiptTable::class)->getColumn($this->_id, 'location');

    // Fetch purchase receipt info
    $preciept = $this->getDefinedTable(Purchase\PurchaseReceiptTable::class)->get($this->_id);
    if (!$preciept) {
        $this->flashMessenger()->addMessage("error^ Purchase receipt not found.");
        return $this->redirect()->toRoute('receipt', ['action' => 'viewreceipt', 'id' => $this->_id]);
    }
	foreach($preciept as $pr);
    foreach ($pur_receipt_details as $row) {
        $opening = $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->get(['item' => $row['item'], 'location' => $location]);
        $op = $opening ? current($opening) : null;

        // Calculate Weighted Moving Average if applicable
        $item_group = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($row['item'], 'item_group');
        $tot_qty = $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->getSMSUM(['item' => $row['item']], 'quantity');

        if ($op) {
            $priceid = $this->getDefinedTable(Stock\PriceTable::class)->getMax('id', ['opening' => $op['opening_stock']]);
            $pd = $priceid ? $this->getDefinedTable(Stock\PriceTable::class)->get($priceid) : null;
            $pd = $pd ? current($pd) : null;

            $wma = ($tot_qty * ($pd['weighted_price'] ?? 0) + $row['accept_qty'] * $row['rate']) / ($tot_qty + $row['accept_qty']);
			
            $count = $this->getDefinedTable(Stock\PriceTable::class)->getByDate($row['item'], $pr['prn_date']);
            $cost_price = !empty($count) ? ($pd['cost_price'] ?? 0) : ($pd['weighted_price'] ?? 0);

            $dataprice = [
                'opening'        => $op['opening_stock'],
                'item'           => $row['item'],
                'uom'            => $row['uom'],
                'selling_price'  => $pd['selling_price'] ?? 0,
                'cost_price'     => $cost_price,
                'weighted_price' => $wma,
                'date'           => $pr['prn_date'],
                'author'         => $this->_author,
            ];
           // echo '<pre>';print_r($dataprice) ;exit;
            $dataprice = $this->_safedataObj->rteSafe($dataprice);
            $this->getDefinedTable(Stock\PriceTable::class)->save($dataprice);
        }

        if ($op) {
            $quantity = $op['quantity'] + $row['accept_qty'];
            $opening_data = [
                'id'       => $op['id'],
                'quantity' => $quantity,
                'author'   => $this->_author,
                'modified' => $this->_modified,
            ];

            $opening_data = $this->_safedataObj->rteSafe($opening_data);
            $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->save($opening_data);
        }
    }

    // Update purchase receipt status
    $pr_data = [
        'id'       => $this->_id,
        'status'   => 4,
        'author'   => $this->_author,
        'modified' => $this->_modified,
    ];

    $pr_data = $this->_safedataObj->rteSafe($pr_data);
    $po_details_result = $this->getDefinedTable(Purchase\PurchaseReceiptTable::class)->save($pr_data);

    if ($po_details_result > 0) {
        $Prn_no = $this->getDefinedTable(Purchase\PurchaseReceiptTable::class)->getColumn($this->_id, 'prn_no');
        $this->flashMessenger()->addMessage("success^ Successfully committed Purchase receipt no." . $Prn_no);
    } else {
        $this->flashMessenger()->addMessage("error^ Something went wrong, try again later.");
    }

    return $this->redirect()->toRoute('receipt', ['action' => 'viewreceipt', 'id' => $this->_id]);
}
	public function getsupplierAction()
	{
		$form = $this->getRequest()->getPost();
		$po_no = $form['po_no'];

		$purchase = $this->getDefinedTable(Purchase\PurchaseOrderTable::class)->get($po_no);
		$selectedsup = $purchase[0]['supplier'];
		
		$sup = $this->getDefinedTable(Accounts\PartyTable::class)->get($selectedsup);
		$supplier = "<option value=''></option>";
		foreach ($sup as $sup) {
			$isSelected = ($sup['id'] == $selectedsup) ? ' selected' : '';
			$supplier .= "<option value='" . $sup['id'] . "'" . $isSelected . ">" . $sup['name'] . "</option>";
		}
			echo json_encode(array(
					'supplier' => $supplier,
			));
			exit;
	}
	/**
	 * checkavailability Action
	**/
	/*public function getcheckavailabilityAction()
	{
		$this->init();
		$form = $this->getRequest()->getPost();
		switch ($form['type']) {
			case 'po_no':
				$article_no =$form['po_no'];
				$result = $this->getDefinedTable(Purchase\PurchaseReceiptTable::class)->isPresent('purchase_order', $po_no);
				break;
		}
		
		echo json_encode(array(
					'valid' => $result,
		));
		exit;
	}*/
}
