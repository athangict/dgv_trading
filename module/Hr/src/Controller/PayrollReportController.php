<?php
namespace Hr\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Authentication\AuthenticationService;
use Interop\Container\ContainerInterface;
use Acl\Model As Acl;
use Administration\Model As Administration;
use Hr\Model As Hr;

class PayrollReportController extends AbstractActionController
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
	/**
	 *  index action
	 */
	public function indexAction()
	{
		$this->init();

		return new ViewModel();
	}
	
	/**
	 * report action
	 */
	public function payregisterAction(){
		$this->init();
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$year = $form['year'];
			$month = $form['month'];
			$region = $form['region'];
			$location = $form['location'];
			$department = $form['department'];
		}else{
			$year = date('Y');
			$month = date('m');
			$region = '-1';
			$location = '-1';
			$department = '-1';
		}
		//list($region, $location, $department, $year, $month) = explode("-", $this->_id . '-0-0-0-0-0');
		$data = array(
				'year' => $year,
				'month' => $month,
				'region' => $region,
				'location' => $location,
				'department' => $department,
		);
		//echo "testing"; exit;
		$ViewModel = new ViewModel(array(
				'title' => 'Payroll Report',
				//'employee' => $this->getDefinedTable(Hr\EmployeeTable::class)->getEmpforReport($data),
				'earningHead' => $this->getDefinedTable(Hr\PayheadTable::class)->get(array('deduction'=>0)),
				'deductionHead' => $this->getDefinedTable(Hr\PayheadTable::class)->get(array('deduction'=>1)),
				'payrollObj' => $this->getDefinedTable(Hr\PayrollTable::class),
				'paydetailObj' => $this->getDefinedTable(Hr\PaydetailTable::class),
				'regions' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'department' => $this->getDefinedTable(Administration\DepartmentTable::class)->getAll(),
				//'payrolls' => $this->getDefinedTable(Hr\PayrollTable::class)->get($this->_id),
				'location' => $this->getDefinedTable(Administration\LocationTable::class)->get(array('region'=>$data['region'])),
				'data' => $data,
				'minYear' => $this->getDefinedTable(Hr\PayrollTable::class)->getMin('year'),
				'regionObj' => $this->getDefinedTable(Administration\RegionTable::class),
				'departmentObj' => $this->getDefinedTable(Administration\DepartmentTable::class),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'temppayrollObj' => $this->getDefinedTable(Hr\TempPayrollTable::class),
				'paystructureObj' => $this->getDefinedTable(Hr\PaystructureTable::class),
		));
        //$this->layout('layout/reportlayout');
		return $ViewModel;
	}
	
	/**
	 * controlsummary of payroll
	 */
	public function controlsummaryAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$year = $form['year'];
			$month = $form['month'];
			$region = $form['region'];
			$location = $form['location'];
			$activity = $form['activity'];
		}else{
			$year = date('Y');
			$month = date('m');
			$region = '-1';
			$location = '-1';
			$activity = '-1';
		}	
		$data = array(
				'year' => $year,
				'month' => $month,
				'region' => $region,
				'location' => $location,
				'activity' => $activity,
		);
		$ViewModel = new ViewModel(array(
				'title' => 'Control Summary',
				'earningHead' => $this->getDefinedTable(Hr\PayheadTable::class)->get(array('deduction'=>0)),
				'deductionHead' => $this->getDefinedTable(Hr\PayheadTable::class)->get(array('deduction'=>1)),
				'payrollObj' => $this->getDefinedTable(Hr\PayrollTable::class),
				'paydetailObj' => $this->getDefinedTable(Hr\PaydetailTable::class),
				'regionObj' => $this->getDefinedTable(Administration\RegionTable::class),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
				'data' => $data,
				'minYear' => $this->getDefinedTable(Hr\PayrollTable::class)->getMin('year'),
				'temppayrollObj' => $this->getDefinedTable(Hr\TempPayrollTable::class),
				'paystructureObj' => $this->getDefinedTable(Hr\PaystructureTable::class),
		));
		//$this->layout('layout/reportlayout');
		return $ViewModel;
	}
	/**
	* pay head report
	*/
	public function phreportAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$year = $form['year'];
			$month = $form['month'];
			$region = $form['region'];
			$location = $form['location'];
			$payheads = $form['payhead'];
		}else{
			$year = date('Y');
			$month = date('m');
			$region = '-1';
			$location = '-1';
			$payheads = array('1');
		}
		if(empty($payheads)):
			$payheads = array('1'); //default selection
		endif;
		if($year == 0):
			$max_year = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('year');
			$max_month = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('month', array('year' => $max_year));
			$year = ($max_month == 12)? $max_year+1 : $max_year;
		endif;
		if($month == 0):
			$max_year = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('year');
			$max_month = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('month', array('year' => $max_year));
			$month = ($max_month == 12)? 1 : $max_month+1;
		endif;
		$data = array(
				'year'=>$year,
				'month'=> $month,
				'region'=> $region,
				'location'=> $location
		);
		$ViewModel = new ViewModel(array(
				'title' 	 => 'Pay Head Report',
				'payheads'	 => $payheads,
				'payheadObj' => $this->getDefinedTable(Hr\PayheadTable::class),
				'paydetailObj' => $this->getDefinedTable(Hr\PaydetailTable::class),
				'payrollObj' => $this->getDefinedTable(Hr\PayrollTable::class),
				'regions' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'department' => $this->getDefinedTable(Administration\DepartmentTable::class)->getAll(),
				'location' => $this->getDefinedTable(Administration\LocationTable::class)->select(array('region'=>$data['region'])),
				'data' => $data,
				'minYear' => $this->getDefinedTable(Hr\PayrollTable::class)->getMin('year'),
				'temppayrollObj' => $this->getDefinedTable(Hr\TempPayrollTable::class),
				'paystructureObj' => $this->getDefinedTable(Hr\PaystructureTable::class),
		));
		//$this->layout('layout/reportlayout');
		return $ViewModel;
	}

	/**
	* loan reports 
	*/
	public function loanreportAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$year = $form['year'];
			$month = $form['month'];
			$region = $form['region'];
			$location = $form['location'];
			$payheads = $form['payhead'];
		}else{
			$year = date('Y');
			$month = date('m');
			$region = '-1';
			$location = '-1';
			$payheads = array('8');
		}
		
		if(empty($payheads)):
			$payheads = array('8'); //default selection
		endif;
		if($year == 0):
			$max_year = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('year');
			$max_month = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('month', array('year' => $max_year));
			$year = ($max_month == 12)? $max_year+1 : $max_year;
		endif;
		if($month == 0):
			$max_year = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('year');
			$max_month = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('month', array('year' => $max_year));
			$month = ($max_month == 12)? 1 : $max_month+1;
		endif;
		$data = array(
				'year'=>$year,
				'month'=> $month,
				'region'=> $region,
				'location'=> $location
		);
		$ViewModel = new ViewModel(array(
				'title' 	 	=> 'Loan Report',
				'payheads'	 	=> $payheads,
				'payheadObj' 	=> $this->getDefinedTable(Hr\PayheadTable::class),
				'paydetailObj'  => $this->getDefinedTable(Hr\PaydetailTable::class),
				'payrollObj' 	=> $this->getDefinedTable(Hr\PayrollTable::class),
				'payheadtypeObj'=> $this->getDefinedTable(Hr\PayheadtypeTable::class),
				'regions' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'department' => $this->getDefinedTable(Administration\DepartmentTable::class)->getAll(),
				'location' => $this->getDefinedTable(Administration\LocationTable::class)->select(array('region'=>$data['region'])),
				'data' => $data,
				'minYear' => $this->getDefinedTable(Hr\PayrollTable::class)->getMin('year'),
				'temppayrollObj' => $this->getDefinedTable(Hr\TempPayrollTable::class),
				'paystructureObj' => $this->getDefinedTable(Hr\PaystructureTable::class),
		));
		//$this->layout('layout/reportlayout');
		return $ViewModel;
	}
	/**
	* group insurence scheme action
	*/
	public function gisAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$year = $form['year'];
			$month = $form['month'];
			$region = $form['region'];
			$location = $form['location'];
		}else{
			$year = date('Y');
			$month = date('m');
			$region = '-1';
			$location = '-1';
		}
		if($year == 0):
			$max_year = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('year');
			$max_month = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('month', array('year' => $max_year));
			$year = ($max_month == 12)? $max_year+1 : $max_year;
		endif;
		if($month == 0):
			$max_year = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('year');
			$max_month = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('month', array('year' => $max_year));
			$month = ($max_month == 12)? 1 : $max_month+1;
		endif;
		$data = array(
				'year'=>$year,
				'month'=> $month,
				'data_region'=> $region,
				'data_location'=> $location,
		);
		$ViewModel = new ViewModel(array(
				'title' 	 	=> 'Group Insurence Scheme',
				'payheadObj' 	=> $this->getDefinedTable(Hr\PayheadTable::class),
				'employeeObj'   => $this->getDefinedTable(Hr\EmployeeTable::class),
				'paydetailObj'  => $this->getDefinedTable(Hr\PaydetailTable::class),
				'payrollObj' 	=> $this->getDefinedTable(Hr\PayrollTable::class),
				'payheadtypeObj'=> $this->getDefinedTable(Hr\PayheadtypeTable::class),
				'paygroupObj'=> $this->getDefinedTable(Hr\PaygroupTable::class),
				'regions' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'department' => $this->getDefinedTable(Administration\DepartmentTable::class)->getAll(),
				'location' => $this->getDefinedTable(Administration\LocationTable::class)->select(array('region'=>$data['data_region'])),
				'data' => $data,
				'temppayrollObj' => $this->getDefinedTable(Hr\TempPayrollTable::class),
				'paystructureObj' => $this->getDefinedTable(Hr\PaystructureTable::class),
		));
		//$this->layout('layout/reportlayout');
		return $ViewModel;
	}
	/**
	* saving action
	*/
	public function savingAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$year = $form['year'];
			$month = $form['month'];
			$region = $form['region'];
			$location = $form['location'];
		}else{
			$year = date('Y');
			$month = date('m');
			$region = '-1';
			$location = '-1';
		}
		if($year == 0):
			$max_year = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('year');
			$max_month = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('month', array('year' => $max_year));
			$year = ($max_month == 12)? $max_year+1 : $max_year;
		endif;
		if($month == 0):
			$max_year = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('year');
			$max_month = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('month', array('year' => $max_year));
			$month = ($max_month == 12)? 1 : $max_month+1;
		endif;
		
		$data = array(
				'year'=>$year,
				'month'=> $month,
				'region'=> $region,
				'location'=> $location,
		);
		$ViewModel = new ViewModel(array(
				'title' 	 	=> 'Bank Report',
				'payheadObj' 	=> $this->getDefinedTable(Hr\PayheadTable::class),
				'paydetailObj'  => $this->getDefinedTable(Hr\PaydetailTable::class),
				'payrollObj' 	=> $this->getDefinedTable(Hr\PayrollTable::class),
				'payheadtypeObj'=> $this->getDefinedTable(Hr\PayheadtypeTable::class),
				'employeeObj'   => $this->getDefinedTable(Hr\EmployeeTable::class),
				'regions' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'department' => $this->getDefinedTable(Administration\DepartmentTable::class)->getAll(),
				'location' => $this->getDefinedTable(Administration\LocationTable::class)->select(array('region'=>$data['region'])),
				'data' => $data,
				'minYear' => $this->getDefinedTable(Hr\PayrollTable::class)->getMin('year'),
				'temppayrollObj' => $this->getDefinedTable(Hr\TempPayrollTable::class),
				'paystructureObj' => $this->getDefinedTable(Hr\PaystructureTable::class),
		));
		//$this->layout('layout/reportlayout');
		return $ViewModel;
	}
	/**
	* saving action
	*/
	public function cashreportAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$year = $form['year'];
			$month = $form['month'];
			$region = $form['region'];
			$location = $form['location'];
		}else{
			$year = date('Y');
			$month = date('m');
			$region = '-1';
			$location = '-1';
		}
		if($year == 0):
			$max_year = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('year');
			$max_month = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('month', array('year' => $max_year));
			$year = ($max_month == 12)? $max_year+1 : $max_year;
		endif;
		if($month == 0):
			$max_year = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('year');
			$max_month = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('month', array('year' => $max_year));
			$month = ($max_month == 12)? 1 : $max_month+1;
		endif;
		
		$data = array(
				'year'=>$year,
				'month'=> $month,
				'region'=> $region,
				'location'=> $location,
		);
		//print_r($data);exit;
		$ViewModel = new ViewModel(array(
				'title' 	 	=> 'Cash Report',
				'payheadObj' 	=> $this->getDefinedTable(Hr\PayheadTable::class),
				'paydetailObj'  => $this->getDefinedTable(Hr\PaydetailTable::class),
				'payrollObj' 	=> $this->getDefinedTable(Hr\PayrollTable::class),
				'payheadtypeObj'=> $this->getDefinedTable(Hr\PayheadtypeTable::class),
				'employeeObj'   => $this->getDefinedTable(Hr\EmployeeTable::class),
				'regions' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'department' => $this->getDefinedTable(Administration\DepartmentTable::class)->getAll(),
				'location' => $this->getDefinedTable(Administration\LocationTable::class)->select(array('region'=>$data['region'])),
				'data' => $data,
				'minYear' => $this->getDefinedTable(Hr\PayrollTable::class)->getMin('year'),
				'temppayrollObj' => $this->getDefinedTable(Hr\TempPayrollTable::class),
				'paystructureObj' => $this->getDefinedTable(Hr\PaystructureTable::class),
		));
		//$this->layout('layout/reportlayout');
		return $ViewModel;
	}
	/**
	 * Provident Fund Report
	**/
	public function pfreportAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$year = $form['year'];
			$month = $form['month'];
			$region = $form['region'];
			$location = $form['location'];
			$activity = $form['activity'];
		}else{
			$year = date('Y');
			$month = date('m');
			$region = '-1';
			$location = '-1';
			$activity = '-1';
		}
		if($year == 0):
			$max_year = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('year');
			$max_month = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('month', array('year' => $max_year));
			$year = ($max_month == 12)? $max_year+1 : $max_year;
		endif;
		if($month == 0):
			$max_year = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('year');
			$max_month = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('month', array('year' => $max_year));
			$month = ($max_month == 12)? 1 : $max_month+1;
		endif;
		$data = array(
				'year' => $year,
				'month' => $month,
				'region' => $region,
				'location' => $location,
				'activity' => $activity,
		);
		//print_r($data);exit;
		$ViewModel = new ViewModel(array(
				'title' 	 	=> 'Provident Fund Report',
				'payheadObj' 	=> $this->getDefinedTable(Hr\PayheadTable::class),
				'employeeObj'   => $this->getDefinedTable(Hr\EmployeeTable::class),
				'paydetailObj'  => $this->getDefinedTable(Hr\PaydetailTable::class),
				'payrollObj' 	=> $this->getDefinedTable(Hr\PayrollTable::class),
				'payheadtypeObj'=> $this->getDefinedTable(Hr\PayheadtypeTable::class),
				'paygroupObj'=> $this->getDefinedTable(Hr\PaygroupTable::class),
				'regions' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'activity' => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
				'location' => $this->getDefinedTable(Administration\LocationTable::class)->select(array('region'=>$data['region'])),
				'data' => $data,
				'minYear' => $this->getDefinedTable(Hr\PayrollTable::class)->getMin('year'),
				'temppayrollObj' => $this->getDefinedTable(Hr\TempPayrollTable::class),
				'paystructureObj' => $this->getDefinedTable(Hr\PaystructureTable::class),
		));
		//$this->layout('layout/accreportlayout');
		return $ViewModel;
	}
	/**
	 * Health Tax and Personal Income Tax
	**/
	public function htpitreportAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$year = $form['year'];
			$month = $form['month'];
			$region = $form['region'];
			$location = $form['location'];
		}else{
			$year = date('Y');
			$month = date('m');
			$region = '-1';
			$location = '-1';
		}
		if($year == 0):
			$max_year = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('year');
			$max_month = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('month', array('year' => $max_year));
			$year = ($max_month == 12)? $max_year+1 : $max_year;
		endif;
		if($month == 0):
			$max_year = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('year');
			$max_month = $this->getDefinedTable(Hr\PayrollTable::class)->getMax('month', array('year' => $max_year));
			$month = ($max_month == 12)? 1 : $max_month+1;
		endif;
		$data = array(
				'year' => $year,
				'month' => $month,
				'region' => $region,
				'location' => $location,
		);
	    $ViewModel = new ViewModel(array(
				'title' 	 	=> 'HT & PIT Report',
				'payheadObj' 	=> $this->getDefinedTable(Hr\PayheadTable::class),
				'employeeObj'   => $this->getDefinedTable(Hr\EmployeeTable::class),
				'paydetailObj'  => $this->getDefinedTable(Hr\PaydetailTable::class),
				'payrollObj' 	=> $this->getDefinedTable(Hr\PayrollTable::class),
				'payheadtypeObj'=> $this->getDefinedTable(Hr\PayheadtypeTable::class),
				'paygroupObj'=> $this->getDefinedTable(Hr\PaygroupTable::class),
				'regions' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'location' => $this->getDefinedTable(Administration\LocationTable::class)->select(array('region'=>$data['region'])),
				'data' => $data,
				'temppayrollObj' => $this->getDefinedTable(Hr\TempPayrollTable::class),
				'paystructureObj' => $this->getDefinedTable(Hr\PaystructureTable::class),
				'payheadObj' => $this->getDefinedTable(Hr\PayheadTable::class),
		));
		//$this->layout('layout/accreportlayout');
		return $ViewModel;
	}
}
