<?php
/**
 * This class implements a persistent queue that tracks objects to be added
 * or removed from the Embase queue. It is implemented with the singleton
 * pattern and will only commit the results once when the request ends or when 
 * an explicit commit is called.
 * 
 * <p>Usage:</p>
 * <ul>
 * <li>EmbaseQueue::get()->add(ut)
 * <li>EmbaseQueue::get()->remove(ut)
 * </ul>
 *
 * After commiting the object(s) to the database, this class will trigger
 * a background process to process the queue. It is up to this background
 * process to deal with concurrency issues that come from multiple processes.
 *
 * @author Aaron Brown <a.brown@library.uq.edu.au>
 * @version 1.0, December 2012
 */

include_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."config.inc.php");
include_once(APP_INC_PATH . "class.queue.php");
include_once(APP_INC_PATH . "class.bgp_embase.php");
include_once(APP_INC_PATH . "class.eventum.php");
include_once(APP_INC_PATH . "class.date.php");
include_once(APP_INC_PATH . "class.embase_service.php");
include_once(APP_INC_PATH . "class.embase_record.php");
include_once(APP_INC_PATH . "class.org_structure.php");
include_once(APP_INC_PATH . "class.matching_conferences.php");
include_once(APP_INC_PATH . "class.mail.php");
define("SIMILARITY_THRESHOLD", 80);

class EmbaseQueue extends Queue
{
  protected $_bgp;
  protected $_bgp_details;
  // Max number of pids to send to Embase in each service call
  protected $_batch_size;
  // If we've registered the commit shutdown function
  protected $_commit_shutdown_registered;
  // Time to wait (in seconds) between successive Embase service calls
  protected $_time_between_calls;
  // Author queue table column prefix
  protected $_dbap;
  
  /**
   * Returns the singleton queue instance.
   *
   * @return instance of class Embase
   */
  public static function get() 
  {
    $log = FezLog::get();
    
    try {
      $instance = Zend_Registry::get('Embase');
    }
    catch(Exception $ex) {
      // Create a new instance
      $instance = new EmbaseQueue();
      $instance->_dbtp = APP_TABLE_PREFIX . 'embase_';
      $instance->_dblp = 'ebl_';
      $instance->_dbqp = 'ebq_';
      $instance->_lock = 'embase';
      $instance->_use_locking = TRUE;
      $instance->_batch_size = EMBASE_BATCH_SIZE;
      $instance->_time_between_calls = EMBASE_SECONDS_BETWEEN_CALLS;
      $instance->_commit_shutdown_registered = FALSE;
      Zend_Registry::set('Embase', $instance);
    }
    return $instance;
  }

    /**
     * Processes the queue.
     */
    protected function process()
    {
        $log = FezLog::get();

        $bgp = new BackgroundProcess_Embase();
        // TODO: maybe take something other than admin
        $bgp->register(serialize(array()), APP_SYSTEM_USER_ID);
    }

    /**
     * Links this instance to a corresponding background process started above
     *
     * @param BackgroundProcess_LinksAmr $bgp
     */
    public function setBGP(&$bgp)
    {
        $this->_bgp = &$bgp;
    }

    /**
     * Processes the queue in the background. Retrieves an item using the pop()
     * function of the queue and calls the index or remove methods.
     */
    public function bgProcess()
    {
        $log = FezLog::get();

          // Mark lock with pid
        if ($this->_use_locking) {
            if (!$this->updateLock()) {
                return false;
            }
        }

        $this->_bgp->setStatus("Embase queue processing started");
        $started = time();
        $countDocs = 0;
        $uts = array();
        do {
            $empty = FALSE;
            $result = $this->pop();

            if (is_array($result)) {
                extract($result, EXTR_REFS);

                $qOp = $this->_dbqp.'op';
                $qUt = $this->_dbqp.'id';

                if ($$qOp == self::ACTION_ADD) {
                    $uts[] = $$qUt;
                    $countDocs++;
                }
                $this->_bgp->setStatus("Emabse queue popped ".$qUt." for operation ".$qOp.". Count is now ".$countDocs);

                if ($countDocs % $this->_batch_size == 0) {
                    // Batch process UTs
                    $this->_bgp->setStatus("Embase queue sending now because count_docs ".$countDocs." mod ".$this->_batch_size." = 0, with: \n".print_r($uts,true));
                    $this->sendToEmbase($uts);
                    $uts = array(); // reset
                    // Sleep before next batch to avoid triggering the service throttling.
                    sleep($this->_time_between_calls);
                }
            } else {
                $empty = TRUE;
            }
            unset($result);
            unset($$qOp);
            unset($$qUt);
        } while (!$empty);

        if (count($uts) > 0) {
            // Process remainder of UTs
            $this->_bgp->setStatus("Embase queue sending remainder with: \n".print_r($uts,true));
            $this->sendToEmbase($uts);
            $uts = array(); // reset
            sleep($this->_time_between_calls); // same as above
        }

        if ($this->_bgp) {
            $plural = $countDocs > 1 ? 's' : '';
            $this->_bgp->setStatus(
                "Processed $countDocs record$plural in ". Date_API::getFormattedDateDiff(time(), $started)
            );
        }
        if ($this->_use_locking) {
            $this->releaseLock();
        }
        return $countDocs;
    }

    /**
     * Send the list of UTs to the WoK service
     *
     * @param array $uts the array of UTs to send
     */
    function sendToEmbase($uts)
    {
        $log = FezLog::get();
        // Find out which already exist in the repository. For these we'll be adding
        // additional bib data
        foreach ($uts as $ut) {
            $search = new EmbaseService;
            $xml = $search->search($ut, false, 1);
            $records = new DomDocument();
            $records->loadXML($xml);
            $recordsNodes = $records->getElementsByTagName('item');
            foreach ($recordsNodes as $recordsNode) {
                $pmR = new EmbaseRecItem();
                $pmR->load($recordsNode);
                $pid = $pmR->save();
                if ($pid) {
                    if ($this->_bgp) {
                        $this->_bgp->setStatus('Created new PID: '.$pid." for UT: ".$rec->ut);
                    }
                }
            }
        }
    }
}
