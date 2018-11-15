<?php
/**
 *  DataExchangeHandler 
 *  - CLASS for .
 *    + key functions
 *  
 *  
 *  - WUSM - Washington University School of Medicine. 
 * @author David L. Heskett
 * @version 1.0
 * @date 20181108
 * @copyright &copy; 2018 Washington University, School of Medicine, Institute for Infomatics <a href="https://redcap.wustl.edu">redcap.wustl.edu</a>
 */

namespace WashingtonUniversity\ProjectToProjectFieldReplicationExternalModule;

include_once 'CurlCommunications.php';

use REDCap;

/** 
 * DataExchangeHandler - .
 */
class DataExchangeHandler
{
	public $message;
	public $flagError;
	public $flagErrorType;
	
	private $commLink;

	private $destToken;
	private $sorcToken;
	
	private $flagOverWriteBlanks;

	CONST ERR_NO_COMM      = 1;
	CONST ERR_NO_SRC_TOKEN = 2;
	CONST ERR_NO_DST_TOKEN = 3;
	
	/**
	 * - set up our defaults.
	 */
	function __construct()
	{
		$this->commLink = null;
		$this->flagError = null;
		$this->flagErrorType = 0;
		$this->flagOverWriteBlanks = false;
		$this->destToken = null;
		$this->sorcToken = null;
		$this->message = '';

		$this->errMsgs[] = 'Clear';
		$this->errMsgs[] = 'No Curl Comms';
		$this->errMsgs[] = 'No Source Token';
		$this->errMsgs[] = 'No Destination Token';
	}
		
	/**
	 * setComm - set the curl comm.
	 */
	public function setComm($commObj)
	{
		$this->commLink = $commObj;
	}

	/**
	 * setComm - set the curl comm.
	 */
	public function setCommApiUrl($apiUrl)
	{
		if ($this->commLink) {
			$this->commLink->setApiUrl($apiUrl);
		}
	}

	/**
	 * getErrorMsg - get the error message.
	 */
	public function getErrorMsg()
	{
		return $this->errMsgs[$this->flagErrorType];
	}

	/**
	 * hasErrors - tell us the error flag.
	 */
	public function hasErrors()
	{
		return $this->flagError;
	}

	/**
	 * addErrorMsg - get the error message.
	 */
	public function addErrorMsg($msg = null)
	{
		if ($this->message) {
			$this->message .= "\n";
		}
		
		if ($msg) {
			$this->message .= $msg;
		} else {
			$this->message .= $this->getErrorMsg();
		}
	}

	/**
	 * getMessage - get the debug message.
	 */
	public function getMessage()
	{
		return $this->message;
	}
	
	/**
	 * setComm - set the curl comm.
	 */
	public function setTokenSorc($token)
	{
		$this->sorcToken = $token;
	}

	/**
	 * setComm - set the curl comm.
	 */
	public function setTokenDest($token)
	{
		$this->destToken = $token;
	}

	/**
	 * setOverWriteBlanks - set the flag as per REDCap::saveData, false = 'normal', true = 'overwrite'. All blank values will be ignored and will not be saved (existing saved values will be kept), but with 'overwrite', any blank values will overwrite any existing saved data. By default, 'normal' is used..
	 */
	public function setOverWriteBlanks($flag = false)
	{
		$this->flagOverWriteBlanks = $flag;
	}
	
	/**
	 * getSourceDataApi - get source data.
	 */
	public function getSourceDataApi($recordsArray, $formsArray, $eventsArray)  // read source
	{
		if (!$this->commLink) {
			$this->flagError = true;
			$this->flagErrorType = self::ERR_NO_COMM;
			$this->addErrorMsg();
		}

		if (!$this->sorcToken) {
			$this->flagError = true;
			$this->flagErrorType = self::ERR_NO_SRC_TOKEN;
			$this->addErrorMsg();
		}
		
		if ($this->flagError) {
			$this->addErrorMsg('getSourceDataApi');
			return null;
		}

		$apiQueryData = array(
		    'token'                  => $this->sorcToken,
		    'content'                => 'record',
		    'format'                 => 'json',
		    'type'                   => 'eav',
		    'records'                => $recordsArray,
		    'forms'                  => $formsArray,
		    'events'                 => $eventsArray,
		    'rawOrLabel'             => 'raw',
		    'rawOrLabelHeaders'      => 'raw',
		    'exportCheckboxLabel'    => 'false',
		    'exportSurveyFields'     => 'false',
		    'exportDataAccessGroups' => 'false',
		    'returnFormat'           => 'json'
		);
		
		$output = $this->commLink->communicateCurl($apiQueryData);

		if ($output == null) {
			$this->flagError = true;
			$this->addErrorMsg($this->commLink->getMessage());
			return null;
		}
				
		return $output;
	}

	/**
	 * putDestinationApi - put destination data.
	 */
	public function putDestinationApi($data)  // write destination
	{
		if (!$this->commLink) {
			$this->flagError = true;
			$this->flagErrorType = self::ERR_NO_COMM;
			$this->addErrorMsg();
		}

		if (!$this->destToken) {
			$this->flagError = true;
			$this->flagErrorType = self::ERR_NO_DST_TOKEN;
			$this->addErrorMsg();
		}

		if ($this->flagError) {
			$this->addErrorMsg('putDestinationApi');
			return null;
		}

		$overwriteState = 'normal';
		if ($this->flagOverWriteBlanks) {
			$overwriteState = 'overwrite';
		}
		
		$apiQueryData = array(
		    'token'             => $this->destToken,
		    'content'           => 'record',
		    'format'            => 'json',
		    'type'              => 'eav',
		    'overwriteBehavior' => $overwriteState,
		    'forceAutoNumber'   => 'false',
		    'data'              => $data,
		    'returnContent'     => 'count',
		    'returnFormat'      => 'json'
		);

		$output = $this->commLink->communicateCurl($apiQueryData);
		
		if ($output == null) {
			$this->flagError = true;
			$this->addErrorMsg($this->commLink->getMessage());
			return null;
		}
		
		return $output;
	}

	/**
	 * getSourceRedcapData - get source data, using REDCap::getData method.
	 */
	public function getSourceRedcapData($projectId, $recordsArray)  // read source
	{
		if ($this->flagError) {
			$this->addErrorMsg('getSourceRedcapData');
			return null;
		}

		$format = 'json';
		$filter = '';
		$fields = array();
		
		$events                 = null;  // array or null   An array of unique event names or event_id's, or alternatively a single unique event name or event_id 
		$groups                 = null;  // array or null   An array of unique group names or group_id's, or alternatively a single unique group name or group_id
		$combochecks            = false; // true or false   Sets the format in which data from checkbox fields are returned. for Array, use FALSE
		$exportDataAccessGroups = false; // true or false   Specifies whether or not to return the "redcap_data_access_group" field, when DAGS
		$exportSurveyFields     = false; // true or false   Specifies whether or not to return the survey identifier field (e.g., "redcap_survey_identifier") or survey timestamp fields (e.g., form_name+"_timestamp") when surveys are utilized in the project
		$filter                 = null;  // string or null  Advanced filters for reports, branching logic, Data Quality module
		$exportLabels           = false; // true or false   Sets the format of the data returned.   FALSE = raw data, TRUE = labels 
		$useCsvHeaders          = false; // true or false   Sets the format of the CSV headers returned (only applicable to 'csv' return formats).  FALSE = variable names, TRUE = Field Label text
			
		$records = \REDCap::getData($projectId, $format, $recordsArray, $fields, $events, $groups, $combochecks, $exportDataAccessGroups, $exportSurveyFields, $filter, $exportLabels, $useCsvHeaders);

		if ($records == null) {
			$this->flagError = true;
			$this->addErrorMsg('No records');
			return null;
		}
				
		return $records;
	}

	/**
	 * putDestinationRedcapData - put destination data, using REDCap::saveData method and saveData in json format.
	 */
	public function putDestinationRedcapData($destinationProjectId, $saveData)  // write destination
	{
		if ($this->flagError) {
			$this->addErrorMsg('putDestinationRedcapData');
			return null;
		}

		$overwriteState = 'normal';
		if ($this->flagOverWriteBlanks) {
			$overwriteState = 'overwrite';
		}
		
		$response = \REDCap::saveData($destinationProjectId, 'json', $saveData, $overwriteState, 'YMD');
		
		if ($response == null) {
			$this->flagError = true;
			$this->addErrorMsg('saveData problem');
			return null;
		}
		
		return $response;
	}


	// **********************************************************************	
	// **********************************************************************	
	// **********************************************************************	

}  // ***** end class


?>
