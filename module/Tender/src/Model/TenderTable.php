<?php
namespace Tender\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\ResultSet\HydratingResultSet;
use Laminas\Db\Adapter\AdapterAwareInterface;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;

class TenderTable extends AbstractTableGateway 
{
	protected $table = 't_tender';   //tablename

	public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
    }	

	/**
	 * Return All records of table
	 * @return Array
	 */
	public function getAll()
	{  
	    $adapter = $this->adapter;
	    $sql = new Sql($adapter);
	    $select = $sql->select();
	    $select->from($this->table)
	    	   ->order(array('id DESC'));
	    $selectString = $sql->getSqlStringForSqlObject($select);
	    $results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	    return $results;
	}
	
	
	/**
	 * Return records of given year and month
	 * @param Int $id
	 * @return Array
	 */
	public function getDateWise($column,$year,$month)
	{	
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
                 ->columns(array('id','tender_no','date','agency','amount','bid_security','bid_date','bid_exp_date','performance_security','status','location','note','author',
					'year' => new Expression('YEAR('.$column.')'),
					'month' => new Expression('MONTH('.$column.')'),
			   ))->having(array('year' => $year))
	    	   ->order(array('id DESC'));
		  if ($month != '-1') {
			$select->having(array('month' => $month));
		}     
		$selectString = $sql->getSqlStringForSqlObject($select);
		
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	
	/**
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function get($param)
	{   
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
	           ->where($where)
	    	   ->order(array('id DESC'));
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		return $results;
	}
	
	/**
	 * Return column value of given id
	 * @param Int $id
	 * @param String $column
	 * @return String | Int
	 */
	public function getColumn($param, $column)
	{
		$where = ( is_array($param) )? $param: array('id' => $param);
		$fetch = array($column);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->columns($fetch);
		$select->where($where);
	
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	
		foreach ($results as $result):
		   $columns =  $result[$column];
		endforeach;
		 
		return $columns;
	}
	
	/**
	 * Save record
	 * @param String $array
	 * @return Int 
	 */
	public function save($data)
	{
	    if ( !is_array($data) ) $data = $data->toArray();
	    $id = isset($data['id']) ? (int)$data['id'] : 0;
	    
	    if ( $id > 0 )
	    {
	    	$result = ($this->update($data, array('id'=>$id)))?$id:0;
	    } else {
	        $this->insert($data);
	    	$result = $this->getLastInsertValue(); 
	    }	    	    
	    return $result;	     
	}
	
	/**
	 * Return records of given condition array
	 * @param Int $column
	 * @param Int $param
	 * @return Array
	 */
	public function getMonthlyPayment($date)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->where->like('bill_no', "%".$date."%");
			
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	
		return  $results;
	}

	/**
     *  Delete a record
     *  @param int $id
     *  @return true | false
     */
	public function remove($id)
	{
		return $this->delete(array('id' => $id));
	}
	/**
	* check particular row is present in the table 
	* with given column and its value
	* 
	*/
	public function isPresent($column, $value)
	{
		$column = $column; $value = $value;
		$resultSet = $this->select(function(Select $select) use ($column, $value){
			$select->where(array($column => $value));
		});		
		$resultSet = $resultSet->toArray();
		return (sizeof($resultSet)>0)? TRUE:FALSE;
	}  
	
   /**
	 * Return max value of the column
	 * @param Array $where
	 * @param String $column
	 * @return String | Int
	 */
	public function getMax($column, $where=NULL)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->columns(array(
				'max' => new Expression('MAX('.$column.')')
		));		
		if($where!=NULL){
			$select->where($where);
		}
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
			
		foreach ($results as $result):
		  $column =  $result['max'];
		endforeach;	
		return $column;
	}
	
	/**
	 * Return Min value of the column
	 * @param Array $where
	 * @param String $column
	 * @return String | Int
	 */
	public function getMin($column, $where = NULL)
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->columns(array(
				'min' => new Expression('MIN('.$column.')')
		));
		if($where!=NULL){
			$select->where($where);
		}
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	
		foreach ($results as $result):
			$column =  $result['min'];
		endforeach;
	
		return $column;
	}
	/**
	 * Return max empid of the column
	 * @param String $year
	 * @return String | Int
	 */
	public function getLastNo()
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table);
		$select->columns(array(
				'max' => new Expression('MAX(tender_no)')
		));
		
		//$select->where->like('ta_no',$no."%");
		
		 $selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		//print_r($results);exit;
		foreach ($results as $result):
		$column =  $result['max'];
		endforeach;
	
		return (int)$column;
	}
		/**
	 * Return id's|columns'value  which is not present in given array
	 * @param Array $param
	 * @param String column
	 * @return Array
	 */
	public function getTender($param)
	{
	$param = (is_array($param)) ? $param : array($param);
    $adapter = $this->adapter;
    $sql = new Sql($adapter);
    $select = new Select();
    
    // Constructing the SQL query
    $select->from(array('t' => $this->table))
           ->join(array('p' => 'pur_purchase_order'), 'p.tender = t.id', array(), 'left')
           ->where($param);
          // ->where->isNull('p.tender');
    
    // Get the SQL string
    $selectString = $sql->getSqlStringForSqlObject($select);
    
    // Execute the query
    $results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
    
    return $results;
	}
	/**
	 * Return id's|columns'value  which is not present in given array
	 * @param Array $param
	 * @param String column
	 * @return Array
	 */
	public function getTenderS()
	{
	//$param = (is_array($param)) ? $param : array($param);
    $adapter = $this->adapter;
    $sql = new Sql($adapter);
    $select = new Select();
    
    // Constructing the SQL query
    $select->from(array('t' => $this->table))
           ->join(array('s' => 'st_supply'), 's.tender = t.id', array(), 'left')
           //->where($param)
           ->where->isNull('s.tender');
    
    // Get the SQL string
    $selectString = $sql->getSqlStringForSqlObject($select);
    
    // Execute the query
    $results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
    
    return $results;
	}


	public function getTRDetails($data,$start_date,$end_date,$where=NULL)
	{   
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)	 	
			   ->where->between('date', $start_date, $end_date);
		if ($data['status'] != '-1') {
			$select->where(array('status' => $data['status']));
		}
		if ($data['agency'] != '-1') {
			$select->where(array('agency' => $data['agency']));
		}
		if($where!=NULL){
			$select->where($where);
		}
		$selectString = $sql->getSqlStringForSqlObject($select);		
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
		//print_r($results);exit;
	}
	/**
	 * Return sum of given column and condition array | given id
	 * @param Int $id
	 * @param String $column
	 * @return String | Int
	 */
	public function getCount($data, $param)
	{
		$where = (is_array($param)) ? $param : array('id' => $param);
		
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
			   ->columns([
				   'count' => new Expression('COUNT(*)'),
				   'year'  => new Expression('YEAR(date)')
			   ])
			   ->where($where)
			   ->group(new Expression('YEAR(date)'))
			   ->having(['year' => $data]);

		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();

		$sum = isset($results[0]['count']) ? $results[0]['count'] : 0;

		return $sum;
	}
	/**
	 * Return sum of given column and condition array | given id
	 * @param Int $id
	 * @param String $column
	 * @return String | Int
	 */
	public function getCounthalfyear($subp,$param)
	{
		if($subp==2){
			$start_date=date('Y').'-01-01';
			$end_date=date('Y').'-06-30';
		}
		
		else{
			$start_date=date('Y').'-7-01';
			$end_date=date('Y').'-12-31';
		}
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
				->columns(array(
				'count' => new Expression('COUNT(*)')
				))->where->between('date', $start_date, $end_date);
				
		$select->where($where);
				
		$selectString = $sql->getSqlStringForSqlObject($select);
    
		// Execute the query
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		// Extract the sum result, if exists
		$count = isset($results[0]['count']) ? $results[0]['count'] : 0;
		
		return $count;
	}
	/**
	 * Return sum of given column and condition array | given id
	 * @param Int $id
	 * @param String $column
	 * @return String | Int
	 */
	public function getCountQuaterly($data,$where = NULL)
	{
		//$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		
		if($data==4){
			$start_date=date('Y').'-01-01';
			$end_date=date('Y').'-03-31';
		}
		else if($data==3){
			$start_date=date('Y').'-04-01';
			$end_date=date('Y').'-06-30';
		}
		else if($data==2){
			$start_date=date('Y').'-07-01';
			$end_date=date('Y').'-09-30';
		}
		else{
			$start_date=date('Y').'-10-01';
			$end_date=date('Y').'-12-31';
		}
		$select->from($this->table)
				->columns(array(
				'count' => new Expression('COUNT(*)')
				))->where->between('date', $start_date, $end_date);
				if($where!=NULL){
					$select->where($where);
				}
		$selectString = $sql->getSqlStringForSqlObject($select);
    
		// Execute the query
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		// Extract the count result, if exists
		$count = isset($results[0]['count']) ? $results[0]['count'] : 0;
		
		return $count;
	}
			/**
	 * Return sum of given column and condition array | given id
	 * @param Int $id
	 * @param String $column
	 * @return String | Int
	 */
	public function getSum($param,$column)
	{
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
				->columns(array(
				'sum' => new Expression('SUM('.$column.')')
				))
		       ->where($where);
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		$column = '';
		foreach ($results as $result):
			$column = $result['sum'];
		endforeach;
		
		return $column;
	}	
	/* Return sum of given column and condition array | given id
	 * @param Int $id
	 * @param String $column
	 * @return String | Int
	 */
	public function getSumA($column,$data,$param)
	{
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
				->columns(array(
				'sum' => new Expression('SUM('.$column.')'),
				'year'  => new Expression('YEAR(date)')
			   ))
			   ->where($where)
			   ->group(new Expression('YEAR(date)'))
			   ->having(['year' => $data]);
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		$column = '';
		foreach ($results as $result):
			$column = $result['sum'];
		endforeach;
		
		return $column;
	}
	/**
	 * Return sum of given column and condition array | given id
	 * @param Int $id
	 * @param String $column
	 * @return String | Int
	 */
	public function getSumhalfyear($column,$subp,$param)
	{
		if($subp==2){
			$start_date=date('Y').'-01-01';
			$end_date=date('Y').'-06-30';
		}
		
		else{
			$start_date=date('Y').'-7-01';
			$end_date=date('Y').'-12-31';
		}
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
				->columns(array(
				'sum' => new Expression('SUM('.$column.')'),
				))->where->between('date', $start_date, $end_date);
				
		$select->where($where);
				
		$selectString = $sql->getSqlStringForSqlObject($select);
    
		// Execute the query
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		
		// Extract the sum result, if exists
		$sum = isset($results[0]['sum']) ? $results[0]['sum'] : 0;
		
		return $sum;
	}
	/**
	 * Return sum of given column and condition array | given id
	 * @param Int $id
	 * @param String $column
	 * @return String | Int
	 */
	public function getSumQuaterly($column,$data,$where = NULL)
	{
		//$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		
		if($data==4){
			$start_date=date('Y').'-01-01';
			$end_date=date('Y').'-03-31';
		}
		else if($data==3){
			$start_date=date('Y').'-04-01';
			$end_date=date('Y').'-06-30';
		}
		else if($data==2){
			$start_date=date('Y').'-07-01';
			$end_date=date('Y').'-09-30';
		}
		else{
			$start_date=date('Y').'-10-01';
			$end_date=date('Y').'-12-31';
		}
		$select->from($this->table)
				->columns(array(
				'sum' => new Expression('SUM('.$column.')'),
				))->where->between('date', $start_date, $end_date);
				if($where!=NULL){
					$select->where($where);
				}
		$selectString = $sql->getSqlStringForSqlObject($select);
    
		// Execute the query
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		// Extract the sum result, if exists
		$sum = isset($results[0]['sum']) ? $results[0]['sum'] : 0;
		
		return $sum;
	}
}


